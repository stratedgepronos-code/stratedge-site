<?php
// ============================================================
// STRATEDGE — Valider les bets (page mobile-first)
// /panel-x9k3m/valider-bets.php
//
// Design compact en cards, optimisé mobile pour valider les résultats
// des bets en déplacement : grands boutons ✅ GAGNÉ / ❌ PERDU / ↺ ANNULÉ
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../includes/tweet-ai.php';
requireAdmin();
$pageActive = 'valider-bets';

// Charger config Twitter si configuré
$twitterActif  = false;
$twitterConfig = [];
$twitterConfigFile = __DIR__ . '/../includes/twitter_keys.php';
if (file_exists($twitterConfigFile)) {
    $twitterConfig = include $twitterConfigFile;
    if (!empty($twitterConfig['actif']) && !empty($twitterConfig['webhook_url'])) {
        require_once __DIR__ . '/../includes/twitter.php';
        $twitterActif = true;
    }
}

$db = getDB();
$success = '';
$error = '';

// ── POST: set_resultat ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';
    } elseif (($_POST['action'] ?? '') === 'set_resultat') {
        $betId = (int)($_POST['bet_id'] ?? 0);
        $resultat = $_POST['resultat'] ?? '';
        if ($betId && in_array($resultat, ['gagne', 'perdu', 'annule'])) {
            try {
                $db->prepare("UPDATE bets SET resultat=?, date_resultat=NOW(), actif=0 WHERE id=?")
                   ->execute([$resultat, $betId]);
            } catch (Throwable $e) {
                try {
                    $db->prepare("UPDATE bets SET actif=0 WHERE id=?")->execute([$betId]);
                } catch (Throwable $e2) {}
            }

            // Récupérer le bet pour les notifications
            $bet = $db->prepare("SELECT * FROM bets WHERE id=?");
            $bet->execute([$betId]);
            $bet = $bet->fetch(PDO::FETCH_ASSOC);

            if ($bet) {
                // Push + email résultat (via la file existante)
                try {
                    $resMap = ['gagne' => 'win', 'perdu' => 'lose', 'annule' => 'void'];
                    $resCode = $resMap[$resultat] ?? $resultat;
                    $titreResult = trim($bet['titre'] ?? '');
                    if ($titreResult !== '' && function_exists('resultatQueueEnqueue')) {
                        resultatQueueEnqueue($db, $titreResult, $resCode);
                        if (function_exists('resultatQueueProcessBatch')) {
                            resultatQueueProcessBatch($db, 90);
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[valider-bets] notif: ' . $e->getMessage());
                }

                // Tweet sauf si perdu
                if ($twitterActif && !empty($twitterConfig['webhook_url']) && $resultat !== 'perdu') {
                    try {
                        $matchName = trim($bet['titre'] ?? '');
                        $coteRaw = (string)($bet['cote'] ?? '');
                        $coteAt = ($coteRaw !== '' && $coteRaw !== '0' && $coteRaw !== '0.00') ? " @ $coteRaw" : '';
                        $isFun  = (stripos((string)($bet['categorie'] ?? ''), 'fun') !== false);
                        $msg = $isFun
                            ? ($resultat === 'gagne' ? "🎲 Bet Fun validé{$coteAt} ✅\n\n{$matchName}\n\n📲 stratedgepronos.fr" : "↺ Bet Fun annulé — mise remboursée\n\n{$matchName}\n\n📲 stratedgepronos.fr")
                            : ($resultat === 'gagne' ? "🎾 Bet validé{$coteAt} ✅\n\n{$matchName}\n\n📲 stratedgepronos.fr" : "↺ Bet annulé — mise remboursée\n\n{$matchName}\n\n📲 stratedgepronos.fr");
                        if (function_exists('twitter_post_from_webhook')) {
                            twitter_post_from_webhook($msg, null, $twitterConfig['webhook_url']);
                        }
                    } catch (Throwable $e) {
                        error_log('[valider-bets] tweet: ' . $e->getMessage());
                    }
                }
            }

            $labels = ['gagne' => '✅ GAGNÉ', 'perdu' => '❌ PERDU', 'annule' => '↺ ANNULÉ'];
            $success = "Bet #{$betId} marqué : " . ($labels[$resultat] ?? $resultat);
        }
    }

    // Redirect pour éviter resubmit
    $filter = $_POST['filter'] ?? 'en_cours';
    header("Location: ?filter=$filter&msg=" . urlencode($success ?: $error) . "&msg_type=" . ($success ? 'success' : 'error'));
    exit;
}

// ── GET: filtre ────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'en_cours';
$msgGet = $_GET['msg'] ?? '';
$msgTypeGet = $_GET['msg_type'] ?? '';

// Query: selon le filtre
$sql = "SELECT * FROM bets ";
switch ($filter) {
    case 'en_cours':
        $sql .= "WHERE (resultat IS NULL OR resultat = '' OR resultat = 'en_cours') AND actif = 1 ";
        break;
    case 'gagne':
        $sql .= "WHERE resultat = 'gagne' ";
        break;
    case 'perdu':
        $sql .= "WHERE resultat = 'perdu' ";
        break;
    case 'annule':
        $sql .= "WHERE resultat = 'annule' ";
        break;
    case 'all':
    default:
        break;
}
$sql .= "ORDER BY date_post DESC LIMIT 80";
$bets = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Compteurs pour les onglets
$counts = [
    'en_cours' => (int)$db->query("SELECT COUNT(*) FROM bets WHERE (resultat IS NULL OR resultat = '' OR resultat = 'en_cours') AND actif = 1")->fetchColumn(),
    'gagne'    => (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat = 'gagne'")->fetchColumn(),
    'perdu'    => (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat = 'perdu'")->fetchColumn(),
    'annule'   => (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat = 'annule'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#050810">
<title>Valider les bets · StratEdge</title>
<link rel="icon" type="image/png" href="../assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg-dark: #050810;
    --bg-card: #0d1220;
    --bg-card-hover: #111827;
    --neon-pink: #ff2d78;
    --neon-cyan: #00d4ff;
    --success: #00c864;
    --danger: #ff4444;
    --warning: #f59e0b;
    --txt1: #f0f4f8;
    --txt2: #b0bec9;
    --txt3: #8a9bb0;
    --border: rgba(255,45,120,0.15);
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body {
    font-family: 'Rajdhani', sans-serif;
    background: var(--bg-dark);
    color: var(--txt1);
    min-height: 100vh;
    padding-bottom: 80px;
  }

  /* Top bar sticky */
  .top-bar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: linear-gradient(180deg, rgba(5,8,16,.98), rgba(5,8,16,.92));
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
    padding: 14px 16px 12px;
  }
  .top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }
  .title {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.15rem;
    font-weight: 900;
    letter-spacing: 1px;
    color: var(--txt1);
  }
  .title .accent { color: var(--neon-pink); }
  .back-link {
    color: var(--txt3);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .back-link:hover { color: var(--neon-cyan); }

  /* Tabs */
  .tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 2px;
    scrollbar-width: none;
  }
  .tabs::-webkit-scrollbar { display: none; }
  .tab {
    flex: 0 0 auto;
    padding: 8px 14px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 20px;
    color: var(--txt2);
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: .15s;
  }
  .tab:hover { background: rgba(255,255,255,.08); }
  .tab.active {
    background: rgba(255,45,120,.15);
    border-color: var(--neon-pink);
    color: var(--neon-pink);
  }
  .tab .count {
    display: inline-block;
    background: rgba(255,45,120,.2);
    color: var(--neon-pink);
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 700;
    margin-left: 4px;
  }
  .tab:not(.active) .count {
    background: rgba(255,255,255,.08);
    color: var(--txt2);
  }

  /* Toast message */
  .toast {
    position: fixed;
    top: 96px;
    left: 16px;
    right: 16px;
    padding: 12px 16px;
    border-radius: 10px;
    font-weight: 600;
    z-index: 100;
    animation: slideDown .3s ease, fadeOut .3s 3.5s ease forwards;
  }
  .toast.success { background: rgba(0,200,100,.2); border: 1px solid var(--success); color: var(--success); }
  .toast.error { background: rgba(255,68,68,.15); border: 1px solid var(--danger); color: var(--danger); }
  @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  @keyframes fadeOut { to { opacity: 0; transform: translateY(-10px); } }

  /* Cards container */
  .bets-list {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 640px;
    margin: 0 auto;
  }

  /* Bet card */
  .bet-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    transition: .2s;
  }
  .bet-card:hover { border-color: rgba(255,45,120,.35); }

  /* Header: image + badges */
  .bet-top {
    position: relative;
    display: flex;
    gap: 12px;
    padding: 12px;
    align-items: flex-start;
  }
  .bet-thumb {
    width: 90px;
    height: 90px;
    flex-shrink: 0;
    border-radius: 10px;
    overflow: hidden;
    background: #0a0a12;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .bet-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .bet-thumb .no-img {
    font-size: 1.8rem;
    color: var(--txt3);
    opacity: .5;
  }
  .bet-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .bet-badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }
  .badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
  }
  .badge-safe { background: rgba(255,45,120,.15); color: var(--neon-pink); border: 1px solid rgba(255,45,120,.3); }
  .badge-live { background: rgba(255,68,68,.15); color: var(--danger); border: 1px solid rgba(255,68,68,.3); }
  .badge-combi { background: rgba(0,212,255,.15); color: var(--neon-cyan); border: 1px solid rgba(0,212,255,.3); }
  .badge-fun { background: rgba(176,38,255,.15); color: #b026ff; border: 1px solid rgba(176,38,255,.3); }
  .badge-tennis { background: rgba(0,200,100,.15); color: var(--success); border: 1px solid rgba(0,200,100,.3); }
  .badge-cote {
    background: rgba(255,255,255,.06);
    color: var(--neon-pink);
    border: 1px solid rgba(255,45,120,.25);
    font-family: 'Orbitron', sans-serif;
    font-weight: 900;
  }

  .bet-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--txt1);
    line-height: 1.25;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
  .bet-meta {
    font-size: 0.78rem;
    color: var(--txt3);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  /* Current result */
  .current-result {
    padding: 8px 14px;
    margin: 0 12px 10px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 700;
    text-align: center;
  }
  .current-result.gagne { background: rgba(0,200,100,.12); color: var(--success); border: 1px solid rgba(0,200,100,.3); }
  .current-result.perdu { background: rgba(255,68,68,.12); color: var(--danger); border: 1px solid rgba(255,68,68,.3); }
  .current-result.annule { background: rgba(245,158,11,.12); color: var(--warning); border: 1px solid rgba(245,158,11,.3); }

  /* Big action buttons (mobile-friendly) */
  .actions {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    padding: 0 12px 12px;
  }
  .action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 14px 6px;
    border: 2px solid;
    border-radius: 12px;
    background: transparent;
    font-family: 'Rajdhani', sans-serif;
    font-weight: 700;
    font-size: 0.88rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all .15s;
    -webkit-tap-highlight-color: transparent;
  }
  .action-btn:active { transform: scale(0.97); }
  .action-btn .icon { font-size: 1.4rem; margin-bottom: 2px; }
  .action-gagne {
    color: var(--success);
    border-color: rgba(0,200,100,.45);
    background: rgba(0,200,100,.08);
  }
  .action-gagne:hover { background: rgba(0,200,100,.18); border-color: var(--success); }
  .action-perdu {
    color: var(--danger);
    border-color: rgba(255,68,68,.45);
    background: rgba(255,68,68,.05);
  }
  .action-perdu:hover { background: rgba(255,68,68,.15); border-color: var(--danger); }
  .action-annule {
    color: var(--warning);
    border-color: rgba(245,158,11,.45);
    background: rgba(245,158,11,.05);
  }
  .action-annule:hover { background: rgba(245,158,11,.15); border-color: var(--warning); }

  .action-btn.current {
    opacity: .6;
    border-style: dashed;
  }

  /* Empty state */
  .empty {
    text-align: center;
    padding: 80px 20px;
    color: var(--txt3);
  }
  .empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: .4;
  }
  .empty-title {
    font-size: 1.2rem;
    color: var(--txt1);
    margin-bottom: 6px;
    font-weight: 700;
  }
  .empty-text {
    font-size: 0.9rem;
    max-width: 400px;
    margin: 0 auto;
    line-height: 1.5;
  }

  /* Desktop tweaks */
  @media (min-width: 768px) {
    .top-bar { padding: 18px 24px 14px; }
    .title { font-size: 1.4rem; }
    .bets-list { padding: 24px; gap: 16px; }
    .bet-thumb { width: 120px; height: 120px; }
    .action-btn { padding: 18px 6px; font-size: 0.95rem; }
  }
</style>
</head>
<body>

<div class="top-bar">
  <div class="top-row">
    <div class="title">VALIDER <span class="accent">LES BETS</span></div>
    <a href="/panel-x9k3m/" class="back-link">← Dashboard</a>
  </div>
  <div class="tabs">
    <a href="?filter=en_cours" class="tab <?= $filter === 'en_cours' ? 'active' : '' ?>">
      ⏳ En cours <span class="count"><?= $counts['en_cours'] ?></span>
    </a>
    <a href="?filter=gagne" class="tab <?= $filter === 'gagne' ? 'active' : '' ?>">
      ✅ Gagnés <span class="count"><?= $counts['gagne'] ?></span>
    </a>
    <a href="?filter=perdu" class="tab <?= $filter === 'perdu' ? 'active' : '' ?>">
      ❌ Perdus <span class="count"><?= $counts['perdu'] ?></span>
    </a>
    <a href="?filter=annule" class="tab <?= $filter === 'annule' ? 'active' : '' ?>">
      ↺ Annulés <span class="count"><?= $counts['annule'] ?></span>
    </a>
    <a href="?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">Tous</a>
  </div>
</div>

<?php if ($msgGet): ?>
  <div class="toast <?= htmlspecialchars($msgTypeGet) ?>"><?= htmlspecialchars($msgGet) ?></div>
<?php endif; ?>

<div class="bets-list">
  <?php if (empty($bets)): ?>
    <div class="empty">
      <div class="empty-icon">
        <?php if ($filter === 'en_cours'): ?>🎉<?php else: ?>📭<?php endif; ?>
      </div>
      <div class="empty-title">
        <?php if ($filter === 'en_cours'): ?>Tous les bets sont validés !
        <?php else: ?>Rien dans cette catégorie
        <?php endif; ?>
      </div>
      <div class="empty-text">
        <?php if ($filter === 'en_cours'): ?>Aucun bet en attente de validation. Bon travail !
        <?php else: ?>Aucun bet ne correspond à ce filtre pour le moment.
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($bets as $b):
      $type = strtolower($b['type'] ?? 'safe');
      $categorie = strtolower($b['categorie'] ?? 'multi');
      $resultat = $b['resultat'] ?? 'en_cours';
      if (empty($resultat)) $resultat = 'en_cours';

      $badgeClass = match($type) {
          'live' => 'badge-live',
          'combi' => 'badge-combi',
          'fun' => 'badge-fun',
          default => 'badge-safe',
      };
      $badgeLabel = match($type) {
          'live' => '🔴 LIVE',
          'combi' => '🎯 COMBI',
          'fun' => '🎲 FUN',
          default => '✨ SAFE',
      };

      $catBadge = $categorie === 'tennis' ? 'badge-tennis' : ($categorie === 'fun' ? 'badge-fun' : 'badge-combi');
      $catLabel = $categorie === 'tennis' ? '🎾 TENNIS' : ($categorie === 'fun' ? '🎲 FUN' : '⚽ MULTI');

      $img = $b['image'] ?? '';
      if ($img && !str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
          $img = '/uploads/bets/' . basename($img);
      }
    ?>
    <div class="bet-card">
      <div class="bet-top">
        <div class="bet-thumb">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" onerror="this.style.display='none';this.parentNode.innerHTML='<div class=\'no-img\'>🎫</div>'">
          <?php else: ?>
            <div class="no-img">🎫</div>
          <?php endif; ?>
        </div>
        <div class="bet-info">
          <div class="bet-badges">
            <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
            <span class="badge <?= $catBadge ?>"><?= $catLabel ?></span>
            <?php if (!empty($b['cote']) && $b['cote'] != '0' && $b['cote'] != '0.00'): ?>
              <span class="badge badge-cote"><?= htmlspecialchars($b['cote']) ?></span>
            <?php endif; ?>
          </div>
          <div class="bet-title"><?= htmlspecialchars($b['titre'] ?? '(sans titre)') ?></div>
          <div class="bet-meta">
            <span>📅 <?= date('d/m à H:i', strtotime($b['date_post'])) ?></span>
            <span>#<?= (int)$b['id'] ?></span>
          </div>
        </div>
      </div>

      <?php if ($resultat !== 'en_cours'): ?>
        <div class="current-result <?= $resultat ?>">
          Actuellement :
          <?php if ($resultat === 'gagne'): ?>✅ GAGNÉ
          <?php elseif ($resultat === 'perdu'): ?>❌ PERDU
          <?php else: ?>↺ ANNULÉ
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="actions">
        <form method="POST" onsubmit="return confirmAction('gagne', <?= $resultat !== 'en_cours' ? 'true' : 'false' ?>)">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="set_resultat">
          <input type="hidden" name="bet_id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="resultat" value="gagne">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <button type="submit" class="action-btn action-gagne <?= $resultat === 'gagne' ? 'current' : '' ?>">
            <span class="icon">✅</span>
            <span>Gagné</span>
          </button>
        </form>
        <form method="POST" onsubmit="return confirmAction('perdu', <?= $resultat !== 'en_cours' ? 'true' : 'false' ?>)">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="set_resultat">
          <input type="hidden" name="bet_id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="resultat" value="perdu">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <button type="submit" class="action-btn action-perdu <?= $resultat === 'perdu' ? 'current' : '' ?>">
            <span class="icon">❌</span>
            <span>Perdu</span>
          </button>
        </form>
        <form method="POST" onsubmit="return confirmAction('annule', <?= $resultat !== 'en_cours' ? 'true' : 'false' ?>)">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="set_resultat">
          <input type="hidden" name="bet_id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="resultat" value="annule">
          <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <button type="submit" class="action-btn action-annule <?= $resultat === 'annule' ? 'current' : '' ?>">
            <span class="icon">↺</span>
            <span>Annulé</span>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function confirmAction(type, alreadySet) {
  const labels = { gagne: '✅ GAGNÉ', perdu: '❌ PERDU', annule: '↺ ANNULÉ' };
  const msg = alreadySet
    ? `Ce bet a déjà un résultat. Le changer en ${labels[type]} ?`
    : `Marquer ce bet comme ${labels[type]} ?`;
  return confirm(msg);
}
// Toast auto-hide
setTimeout(() => {
  const t = document.querySelector('.toast');
  if (t) t.remove();
}, 4000);
</script>

</body>
</html>
