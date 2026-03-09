<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'membres';

$db = getDB();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $error = 'Erreur sécurité.'; }
    else {
        $action = $_POST['action'] ?? '';
        $mid = (int)($_POST['membre_id'] ?? 0);

        if ($action === 'toggle_ban') {
            $db->prepare("UPDATE membres SET banni = NOT banni WHERE id = ? AND email != 'stratedgepronos@gmail.com'")->execute([$mid]);
            $success = 'Statut membre modifié.';
        } elseif ($action === 'add_abo') {
            $type = $_POST['type'] ?? 'daily';
            activerAbonnement($mid, $type);
            $success = 'Abonnement ajouté manuellement.';
        } elseif ($action === 'expire_abo') {
            $aboId = (int)($_POST['abo_id'] ?? 0);
            $db->prepare("UPDATE abonnements SET actif=0 WHERE id=?")->execute([$aboId]);
            $success = 'Abonnement expiré.';
        } elseif ($action === 'supprimer_membre') {
            // Protéger le compte admin
            $checkAdmin = $db->prepare("SELECT email FROM membres WHERE id = ?");
            $checkAdmin->execute([$mid]);
            $memEmail = $checkAdmin->fetchColumn();
            if ($memEmail && $memEmail !== ADMIN_EMAIL) {
                // Supprimer dans l'ordre : abonnements, messages, tickets, push_subscriptions, chats, puis membre
                $db->prepare("DELETE FROM abonnements WHERE membre_id = ?")->execute([$mid]);
                $db->prepare("DELETE FROM messages WHERE membre_id = ?")->execute([$mid]);
                $db->prepare("DELETE FROM push_subscriptions WHERE membre_id = ?")->execute([$mid]);
                // Supprimer les chats et leurs messages
                $chatIds = $db->prepare("SELECT id FROM chats WHERE membre_id = ?");
                $chatIds->execute([$mid]);
                foreach ($chatIds->fetchAll() as $c) {
                    $db->prepare("DELETE FROM chat_messages WHERE chat_id = ?")->execute([$c['id']]);
                }
                $db->prepare("DELETE FROM chats WHERE membre_id = ?")->execute([$mid]);
                // Supprimer les tickets et leurs messages
                $ticketIds = $db->prepare("SELECT id FROM tickets WHERE membre_id = ?");
                $ticketIds->execute([$mid]);
                foreach ($ticketIds->fetchAll() as $t) {
                    $db->prepare("DELETE FROM ticket_messages WHERE ticket_id = ?")->execute([$t['id']]);
                }
                $db->prepare("DELETE FROM tickets WHERE membre_id = ?")->execute([$mid]);
                // Supprimer le membre
                $db->prepare("DELETE FROM membres WHERE id = ? AND email != '" . ADMIN_EMAIL . "'")->execute([$mid]);
                $success = 'Membre supprimé définitivement.';
                // Rediriger vers la liste
                header('Location: membres.php?deleted=1');
                exit;
            } else {
                $error = 'Impossible de supprimer le compte admin.';
            }
        }
    }
}

// Recherche
$search = clean($_GET['q'] ?? '');
$membres = [];
if ($search) {
    $stmt = $db->prepare("SELECT * FROM membres WHERE (nom LIKE ? OR email LIKE ?) AND email != 'stratedgepronos@gmail.com' ORDER BY date_inscription DESC");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $db->prepare("SELECT * FROM membres WHERE email != 'stratedgepronos@gmail.com' ORDER BY date_inscription DESC");
    $stmt->execute();
}
$membres = $stmt->fetchAll();

// ── Rank d'un membre selon son meilleur abonnement actif ──
function getMemberRank(PDO $db, int $membreId): array {
    $stmt = $db->prepare("
        SELECT type FROM abonnements
        WHERE membre_id = ? AND actif = 1
          AND (type IN ('daily','rasstoss') OR date_fin > NOW())
        ORDER BY FIELD(type,'vip_max','weekly','tennis','weekend','daily','rasstoss') ASC
        LIMIT 1
    ");
    $stmt->execute([$membreId]);
    $type = $stmt->fetchColumn();
    $ranks = [
        'vip_max' => ['label'=>'👑 VIP MAX',  'class'=>'rank-vip'],
        'weekly'  => ['label'=>'🏆 Weekly',   'class'=>'rank-weekly'],
        'tennis'  => ['label'=>'🎾 Tennis',   'class'=>'rank-tennis'],
        'weekend' => ['label'=>'📅 Week-End', 'class'=>'rank-weekend'],
        'daily'   => ['label'=>'⚡ Daily',    'class'=>'rank-daily'],
        'rasstoss'=> ['label'=>'👑 RassToss', 'class'=>'rank-vip'],
    ];
    return $ranks[$type] ?? ['label'=>'— Membre', 'class'=>'rank-none'];
}

// Vue détail membre
$membreDetail = null;
$membreAbos = [];
if (isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    $s = $db->prepare("SELECT * FROM membres WHERE id = ?");
    $s->execute([$tid]);
    $membreDetail = $s->fetch();
    if ($membreDetail) {
        $s2 = $db->prepare("SELECT * FROM abonnements WHERE membre_id = ? ORDER BY date_achat DESC");
        $s2->execute([$tid]);
        $membreAbos = $s2->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Membres – Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --bg-dark:#050810; --bg-card:#0d1220; --neon-green:#ff2d78; --neon-green-dim:#d6245f; --neon-blue:#00d4ff; --neon-purple:#a855f7; --text-primary:#f0f4f8; --text-secondary:#b0bec9; --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.12); --glow-green:0 0 20px rgba(255,45,120,0.3); }
    * { margin:0; padding:0; box-sizing:border-box; }
    html,body { overflow-x:hidden !important; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; }
    .page-header { margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .page-header h1 { font-family:'Orbitron',sans-serif; font-size:1.6rem; font-weight:700; }
    .search-bar { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:0.7rem 1.2rem; color:var(--text-primary); font-family:'Rajdhani',sans-serif; font-size:1rem; outline:none; width:280px; transition:border 0.3s; }
    .search-bar:focus { border-color:var(--neon-green); }
    .card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:14px; padding:1.5rem; margin-bottom:1.5rem; }
    .card h3 { font-family:'Orbitron',sans-serif; font-size:0.9rem; font-weight:700; margin-bottom:1.5rem; }
    table { width:100%; border-collapse:collapse; }
    th { text-align:left; font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); padding:0.75rem; border-bottom:1px solid rgba(255,255,255,0.05); }
    td { padding:0.85rem 0.75rem; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--text-secondary); font-size:0.85rem; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .btn-sm { padding:0.35rem 0.8rem; border-radius:6px; font-family:'Rajdhani',sans-serif; font-size:0.85rem; font-weight:700; cursor:pointer; border:none; transition:all 0.2s; text-decoration:none; display:inline-block; }
    .btn-primary { background:rgba(255,45,120,0.15); color:var(--neon-green); border:1px solid rgba(255,45,120,0.25); }
    .btn-danger { background:rgba(255,45,120,0.1); color:#ff6b9d; border:1px solid rgba(255,45,120,0.2); }
    .btn-secondary { background:rgba(255,255,255,0.07); color:var(--text-secondary); border:1px solid rgba(255,255,255,0.1); }
    .alert-success { background:rgba(0,200,100,0.1); border:1px solid rgba(0,200,100,0.2); border-radius:10px; padding:1rem; color:#00c864; margin-bottom:1.5rem; }
    .alert-error { background:rgba(255,45,120,0.1); border:1px solid rgba(255,45,120,0.2); border-radius:10px; padding:1rem; color:#ff6b9d; margin-bottom:1.5rem; }
    .badge { padding:0.2rem 0.7rem; border-radius:6px; font-size:0.75rem; font-weight:700; }
    .badge-actif { background:rgba(0,200,100,0.1); color:#00c864; border:1px solid rgba(0,200,100,0.2); }
    .badge-banni { background:rgba(255,45,120,0.1); color:#ff6b9d; border:1px solid rgba(255,45,120,0.2); }
    /* ── Ranks ── */
    .rank { display:inline-flex; align-items:center; gap:0.3rem; padding:0.18rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:700; letter-spacing:0.5px; white-space:nowrap; }
    .rank-none    { background:rgba(255,255,255,0.04); color:#8a9bb0; border:1px solid rgba(255,255,255,0.08); }
    .rank-daily   { background:rgba(255,45,120,0.1);  color:#ff6b9d; border:1px solid rgba(255,45,120,0.25); }
    .rank-weekend { background:rgba(0,212,255,0.1);   color:#00d4ff; border:1px solid rgba(0,212,255,0.25); }
    .rank-weekly  { background:rgba(168,85,247,0.1);  color:#a855f7; border:1px solid rgba(168,85,247,0.25); }
    .rank-tennis  { background:rgba(0,212,106,0.1);   color:#00d46a; border:1px solid rgba(0,212,106,0.25); }
    .rank-vip     { background:linear-gradient(135deg,rgba(200,150,12,0.15),rgba(245,200,66,0.15)); color:#f5c842; border:1px solid rgba(245,200,66,0.45); box-shadow:0 0 8px rgba(245,200,66,0.15); animation:rankPulse 2s ease-in-out infinite; }
    @keyframes rankPulse { 0%,100%{box-shadow:0 0 6px rgba(245,200,66,0.15);} 50%{box-shadow:0 0 14px rgba(245,200,66,0.35);} }
    .back-btn { color:var(--text-muted); text-decoration:none; font-size:0.9rem; display:inline-flex; align-items:center; gap:0.5rem; margin-bottom:1.5rem; transition:color 0.3s; }
    .back-btn:hover { color:var(--text-primary); }
    .detail-header { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .avatar-circle { width:50px; height:50px; border-radius:50%; background:linear-gradient(135deg, var(--neon-green), var(--neon-blue)); display:flex; align-items:center; justify-content:center; font-size:1.3rem; font-weight:700; color:var(--bg-dark); font-family:'Orbitron',sans-serif; overflow:hidden; }
    .form-group { margin-bottom:1rem; }
    .form-group label { display:block; font-size:0.8rem; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:var(--text-secondary); margin-bottom:0.4rem; }
    .form-group select { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:0.7rem 1rem; color:var(--text-primary); font-family:'Rajdhani',sans-serif; font-size:1rem; outline:none; }
    .form-group select option { background:#111827; }
    .btn-add { background:linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color:white; padding:0.7rem 1.5rem; border:none; border-radius:8px; font-family:'Rajdhani',sans-serif; font-weight:700; cursor:pointer; }

    @media(max-width:768px){
      .page-header{flex-direction:column;align-items:flex-start;gap:0.6rem;}
      .page-header h1{font-size:1.15rem;}
      .search-bar{width:100%;font-size:0.92rem;}
      .card{padding:1rem 0.6rem;border-radius:10px;margin-bottom:1rem;overflow-x:auto;-webkit-overflow-scrolling:touch;}
      .card h3{font-size:0.82rem;margin-bottom:1rem;}
      table{min-width:520px;}
      th{font-size:0.58rem;padding:0.5rem 0.4rem;}
      td{font-size:0.8rem;padding:0.6rem 0.4rem;}
      .btn-sm{padding:0.3rem 0.55rem;font-size:0.78rem;min-height:36px;display:inline-flex;align-items:center;}
      .detail-header{flex-direction:column;align-items:flex-start;gap:0.8rem;padding:1rem;border-radius:10px;}
      .avatar-circle{width:42px;height:42px;font-size:1.1rem;}
      .back-btn{font-size:0.85rem;margin-bottom:1rem;}
      .form-group select{width:100%;padding:0.65rem 0.8rem;font-size:0.9rem;}
      .btn-add{width:100%;min-height:44px;font-size:0.92rem;}
      .alert-success,.alert-error{font-size:0.85rem;padding:0.7rem 0.8rem;border-radius:8px;}
    }
    @media(max-width:480px){
      .card{padding:0.7rem 0.4rem;}
      table{min-width:450px;}
      td{font-size:0.75rem;padding:0.5rem 0.35rem;}
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <?php if ($success): ?><div class="alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error">⚠️ <?= clean($error) ?></div><?php endif; ?>

  <?php if ($membreDetail): ?>
    <!-- DÉTAIL MEMBRE -->
    <a href="membres.php" class="back-btn">← Retour aux membres</a>
    <div class="detail-header">
      <div style="display:flex; align-items:center; gap:1rem;">
        <div class="avatar-circle"><?php if (!empty($membreDetail['photo_profil']) && file_exists(__DIR__.'/../uploads/avatars/'.basename($membreDetail['photo_profil']))): ?><img src="/uploads/avatars/<?= clean(basename($membreDetail['photo_profil'])) ?>?v=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><?= strtoupper(substr($membreDetail['nom'], 0, 1)) ?><?php endif; ?></div>
        <div>
          <div style="font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:700;"><?= clean($membreDetail['nom']) ?></div>
          <div style="color:var(--text-muted); font-size:0.9rem;"><?= clean($membreDetail['email']) ?></div>
          <?php $rankDetail = getMemberRank($db, $membreDetail['id']); ?>
          <span class="rank <?= $rankDetail['class'] ?>" style="margin-top:0.4rem;"><?= $rankDetail['label'] ?></span>
          <div style="color:var(--text-muted); font-size:0.8rem;">Inscrit le <?= date('d/m/Y', strtotime($membreDetail['date_inscription'])) ?></div>
        </div>
      </div>
      <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="toggle_ban">
          <input type="hidden" name="membre_id" value="<?= $membreDetail['id'] ?>">
          <button type="submit" class="btn-sm <?= $membreDetail['banni'] ? 'btn-primary' : 'btn-danger' ?>" onclick="return confirm('Confirmer ?')">
            <?= $membreDetail['banni'] ? '✅ Débannir' : '🚫 Bannir' ?>
          </button>
        </form>
        <a href="messages.php?membre=<?= $membreDetail['id'] ?>" class="btn-sm btn-secondary">💬 Messagerie</a>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="supprimer_membre">
          <input type="hidden" name="membre_id" value="<?= $membreDetail['id'] ?>">
          <button type="submit" class="btn-sm" style="background:rgba(255,60,60,0.12);border:1px solid rgba(255,60,60,0.4);color:#ff6060;"
            onclick="return confirm('⚠️ Supprimer définitivement ' + '<?= addslashes(clean($membreDetail['nom'])) ?>' + ' ?\n\nCette action est irréversible. Tous ses données seront supprimées.')">
            🗑️ Supprimer
          </button>
        </form>
      </div>
    </div>

    <!-- Ajouter abonnement manuellement -->
    <div class="card">
      <h3>Ajouter un abonnement manuellement</h3>
      <form method="POST" style="display:flex; gap:1rem; align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_abo">
        <input type="hidden" name="membre_id" value="<?= $membreDetail['id'] ?>">
        <div class="form-group" style="margin:0;">
          <label>Type</label>
          <select name="type">
            <option value="daily">⚡ Daily</option>
            <option value="weekend">📅 Week-End</option>
            <option value="weekly">🏆 Weekly</option>
            <option value="tennis">🎾 Tennis Weekly</option>
            <option value="vip_max">👑 VIP Max</option>
            <option value="rasstoss">👑 Rass-Toss (À vie)</option>
          </select>
        </div>
        <button type="submit" class="btn-add">+ Ajouter</button>
      </form>
    </div>

    <!-- Historique abonnements -->
    <div class="card">
      <h3>Historique abonnements (<?= count($membreAbos) ?>)</h3>
      <table>
        <thead><tr><th>Type</th><th>Acheté le</th><th>Expire le</th><th>Montant</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($membreAbos as $a): 
            $isActif = $a['actif'] && ($a['type'] === 'daily' || $a['type'] === 'rasstoss' || strtotime($a['date_fin']) > time());
          ?>
          <tr>
            <td><strong><?= strtoupper($a['type']) ?></strong></td>
            <td><?= date('d/m/Y H:i', strtotime($a['date_achat'])) ?></td>
            <td><?= $a['date_fin'] ? date('d/m/Y H:i', strtotime($a['date_fin'])) : 'Prochain bet' ?></td>
            <td style="color:var(--neon-green);"><?= $a['montant'] ?>€</td>
            <td><span class="badge <?= $isActif ? 'badge-actif' : 'badge-banni' ?>"><?= $isActif ? '✓ Actif' : 'Expiré' ?></span></td>
            <td>
              <?php if ($isActif): ?>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="expire_abo">
                <input type="hidden" name="abo_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="membre_id" value="<?= $membreDetail['id'] ?>">
                <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Expirer cet abonnement ?')">Expirer</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <!-- LISTE MEMBRES -->
    <div class="page-header">
      <h1>👥 Membres (<?= count($membres) ?>)</h1>
      <form method="GET">
        <input type="text" name="q" class="search-bar" placeholder="Rechercher par nom ou email…"
               value="<?= clean($search) ?>">
      </form>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>Nom</th><th>Email</th><th>Inscrit</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($membres as $m): 
            // Vérifier si abonnement actif
            $stmt = $db->prepare("SELECT COUNT(*) FROM abonnements WHERE membre_id=? AND actif=1 AND (type='daily' OR type='rasstoss' OR date_fin>NOW())");
            $stmt->execute([$m['id']]);
            $hasAbo = $stmt->fetchColumn() > 0;
            $rank = getMemberRank($db, $m['id']);
          ?>
          <tr>
            <td><strong><?= clean($m['nom']) ?></strong></td>
            <td style="font-size:0.8rem;"><?= clean($m['email']) ?></td>
            <td style="font-size:0.8rem;"><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
            <td>
              <?php if ($m['banni']): ?>
                <span class="badge badge-banni">🚫 Banni</span>
              <?php else: ?>
                <span class="rank <?= $rank['class'] ?>"><?= $rank['label'] ?></span>
              <?php endif; ?>
            </td>
            <td style="display:flex; gap:0.5rem; flex-wrap:wrap;">
              <a href="?id=<?= $m['id'] ?>" class="btn-sm btn-primary">👁 Voir</a>
              <a href="messages.php?membre=<?= $m['id'] ?>" class="btn-sm btn-secondary">💬</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($membres)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:2rem;">Aucun membre trouvé.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
