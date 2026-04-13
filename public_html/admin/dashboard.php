<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$membre = getMembre();
$abonnement = getAbonnementActif($membre['id']);
$historique = getHistoriqueAbonnements($membre['id']);

// Gestion envoi message
$msgSuccess = '';
$msgError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $msgError = 'Erreur de sécurité.';
    } elseif ($_POST['action'] === 'send_message') {
        $contenu = trim($_POST['contenu'] ?? '');
        if (strlen($contenu) < 2) {
            $msgError = 'Message trop court.';
        } elseif (strlen($contenu) > 2000) {
            $msgError = 'Message trop long (max 2000 caractères).';
        } else {
            $stmt = $db->prepare("INSERT INTO messages (membre_id, contenu, expediteur) VALUES (?, ?, 'membre')");
            $stmt->execute([$membre['id'], $contenu]);
            $msgSuccess = 'Message envoyé ! Je vous réponds dès que possible.';
        }
    }
}

// Récupérer messages
$stmt = $db->prepare("SELECT * FROM messages WHERE membre_id = ? ORDER BY date_envoi ASC");
$stmt->execute([$membre['id']]);
$messages = $stmt->fetchAll();

// Marquer messages admin comme lus
$db->prepare("UPDATE messages SET lu = 1 WHERE membre_id = ? AND expediteur = 'admin' AND lu = 0")->execute([$membre['id']]);

// Nombre de messages admin non lus (avant marquage)
$nbNonLus = 0;
foreach ($messages as $msg) {
    if ($msg['expediteur'] === 'admin' && !$msg['lu']) $nbNonLus++;
}

$welcome = isset($_GET['welcome']);

// Labels offres
$typeLabels = ['daily' => '⚡ Daily — Prochain Bet', 'weekend' => '📅 Week-End', 'weekly' => '🏆 Weekly 7 jours', 'rasstoss' => '👑 Rass-Toss'];
$typeColors = ['daily' => '#ff2d78', 'weekend' => '#00d4ff', 'weekly' => '#a855f7'];
$statutColors = ['actif' => 'rgba(255,45,120,0.1)', 'expiré' => 'rgba(255,45,120,0.1)'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mon Espace – StratEdge Pronos</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --bg-dark:#0a0e17; --bg-card:#111827; --bg-card2:#141c2e; --neon-green:#ff2d78; --neon-green-dim:#d6245f; --neon-blue:#00d4ff; --neon-purple:#a855f7; --text-primary:#f0f4f8; --text-secondary:#b0bec9; --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.15); --glow-green:0 0 30px rgba(255,45,120,0.35); }
    * { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior:smooth; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; }
    body::before { content:''; position:fixed; inset:0; background:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E"); pointer-events:none; z-index:9999; }

    nav { background:rgba(10,14,23,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border-subtle); padding:0 2rem; position:sticky; top:0; z-index:100; }
    .nav-inner { max-width:1200px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; height:70px; }
    .logo img { height:45px; }
    .nav-right { display:flex; align-items:center; gap:1.5rem; }
    .nav-right a { color:var(--text-secondary); text-decoration:none; font-size:0.9rem; font-weight:500; text-transform:uppercase; letter-spacing:1px; transition:color 0.3s; }
    .nav-right a:hover { color:var(--neon-green); }
    .nav-logout { color:var(--text-muted) !important; }
    .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:5px;background:none;border:none;}
    .hamburger span{display:block;width:24px;height:2px;background:var(--text-primary);border-radius:2px;}
    .mobile-menu{display:none;position:fixed;inset:0;top:70px;background:rgba(5,8,16,0.98);backdrop-filter:blur(20px);z-index:99;padding:2rem;flex-direction:column;}
    .mobile-menu.open{display:flex;}
    .mobile-menu a{color:var(--text-secondary);text-decoration:none;font-size:1.1rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;padding:1.2rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}

    .main { max-width:1100px; margin:0 auto; padding:2.5rem 2rem; }

    .welcome-banner { background:linear-gradient(135deg, rgba(255,45,120,0.1), rgba(0,212,255,0.05)); border:1px solid rgba(255,45,120,0.2); border-radius:16px; padding:1.5rem 2rem; margin-bottom:2rem; display:flex; align-items:center; gap:1rem; }
    .welcome-banner h2 { font-family:'Orbitron',sans-serif; font-size:1.2rem; }
    .welcome-banner p { color:var(--text-secondary); font-size:0.95rem; margin-top:0.3rem; }

    .grid-dash { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.5rem; margin-bottom:2rem; }
    .stat-card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:16px; padding:1.5rem; }
    .stat-card .label { font-family:'Space Mono',monospace; font-size:0.7rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem; }
    .stat-card .value { font-family:'Orbitron',sans-serif; font-size:1.6rem; font-weight:700; }
    .stat-card .sub { font-size:0.85rem; color:var(--text-muted); margin-top:0.3rem; }
    .active-badge { display:inline-flex; align-items:center; gap:0.4rem; background:rgba(255,45,120,0.1); border:1px solid rgba(255,45,120,0.3); color:#ff2d78; padding:0.25rem 0.8rem; border-radius:50px; font-size:0.8rem; font-weight:700; }
    .inactive-badge { display:inline-flex; align-items:center; gap:0.4rem; background:rgba(255,45,120,0.1); border:1px solid var(--border-subtle); color:var(--text-muted); padding:0.25rem 0.8rem; border-radius:50px; font-size:0.8rem; }

    .section-block { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:16px; padding:2rem; margin-bottom:1.5rem; }
    .section-block h3 { font-family:'Orbitron',sans-serif; font-size:1rem; font-weight:700; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.7rem; }
    .section-block h3 .dot { width:8px; height:8px; border-radius:50%; background:var(--neon-green); }

    /* ABONNEMENT ACTIF */
    .abo-actif { background:linear-gradient(135deg, rgba(255,45,120,0.08), rgba(0,212,255,0.04)); border:1px solid rgba(255,45,120,0.25); border-radius:12px; padding:1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .abo-type { font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:700; }
    .abo-expire { font-size:0.9rem; color:var(--text-muted); margin-top:0.3rem; }
    .btn-voir-bets { background:linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color:#ffffff !important; padding:0.75rem 1.5rem; border-radius:10px; text-decoration:none; font-family:'Rajdhani',sans-serif; font-weight:700; font-size:1rem; text-transform:uppercase; letter-spacing:1px; transition:all 0.3s; display:inline-flex; align-items:center; gap:0.5rem; }
    .btn-voir-bets:hover { box-shadow:var(--glow-green); transform:translateY(-2px); }
    .no-abo { text-align:center; padding:2rem; color:var(--text-muted); }
    .no-abo a { color:var(--neon-green); text-decoration:none; font-weight:600; }

    /* HISTORIQUE */
    .histo-table { width:100%; border-collapse:collapse; }
    .histo-table th { text-align:left; font-family:'Space Mono',monospace; font-size:0.7rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); padding:0.75rem 1rem; border-bottom:1px solid rgba(255,255,255,0.05); }
    .histo-table td { padding:0.85rem 1rem; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--text-secondary); font-size:0.9rem; }
    .histo-table tr:last-child td { border-bottom:none; }
    .badge-type { padding:0.3rem 0.8rem; border-radius:8px; font-size:0.78rem; font-weight:700; font-family:'Orbitron',sans-serif; letter-spacing:0.5px; white-space:nowrap; display:inline-flex; align-items:center; gap:0.3rem; }
    .badge-actif { background:rgba(255,45,120,0.1); color:#ff2d78; border:1px solid rgba(255,45,120,0.2); }
    .badge-expire { background:rgba(255,45,120,0.08); color:var(--text-muted); border:1px solid var(--border-subtle); }
    .badge-rasstoss { background:linear-gradient(135deg,rgba(255,200,0,0.15),rgba(255,150,0,0.1)); color:#ffd700; border:1px solid rgba(255,200,0,0.4); font-family:'Orbitron',sans-serif; letter-spacing:1px; animation: glow-rt 2s ease-in-out infinite alternate; }
    @keyframes glow-rt { from { box-shadow:0 0 4px rgba(255,200,0,0.2); } to { box-shadow:0 0 12px rgba(255,200,0,0.5); } }

    /* MESSAGERIE */
    .chat-area { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:1.5rem; min-height:200px; max-height:400px; overflow-y:auto; margin-bottom:1rem; display:flex; flex-direction:column; gap:1rem; }
    .chat-empty { text-align:center; color:var(--text-muted); padding:2rem; font-size:0.9rem; }
    .msg-bubble { max-width:80%; }
    .msg-bubble.from-me { align-self:flex-end; }
    .msg-bubble.from-admin { align-self:flex-start; }
    .msg-content { padding:0.8rem 1.2rem; border-radius:12px; font-size:0.95rem; line-height:1.5; }
    .from-me .msg-content { background:linear-gradient(135deg, rgba(255,45,120,0.2), rgba(255,45,120,0.1)); border:1px solid rgba(255,45,120,0.2); }
    .from-admin .msg-content { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); }
    .msg-meta { font-size:0.75rem; color:var(--text-muted); margin-top:0.3rem; }
    .from-me .msg-meta { text-align:right; }
    .admin-label { font-family:'Orbitron',sans-serif; font-size:0.65rem; color:var(--neon-green); letter-spacing:1px; margin-bottom:0.3rem; }

    .chat-form { display:flex; gap:1rem; align-items:flex-end; }
    .chat-form textarea { flex:1; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:0.9rem 1.2rem; color:var(--text-primary); font-family:'Rajdhani',sans-serif; font-size:1rem; resize:none; height:80px; outline:none; transition:border 0.3s; }
    .chat-form textarea:focus { border-color:var(--neon-green); }
    .chat-form textarea::placeholder { color:var(--text-muted); }
    .btn-send { background:linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color:white; padding:0 1.5rem; height:80px; border:none; border-radius:10px; font-family:'Rajdhani',sans-serif; font-size:1rem; font-weight:700; cursor:pointer; transition:all 0.3s; white-space:nowrap; }
    .btn-send:hover { box-shadow:var(--glow-green); }

    .msg-success { background:rgba(255,45,120,0.08); border:1px solid rgba(255,45,120,0.2); border-radius:10px; padding:0.75rem 1rem; color:#ff2d78; font-size:0.9rem; margin-bottom:1rem; }
    .msg-error { background:rgba(255,45,120,0.1); border:1px solid rgba(255,45,120,0.2); border-radius:10px; padding:0.75rem 1rem; color:#ff6b9d; font-size:0.9rem; margin-bottom:1rem; }

    /* SAV LINK */
    .sav-btn { display:inline-flex; align-items:center; gap:0.5rem; background:rgba(168,85,247,0.1); border:1px solid rgba(168,85,247,0.25); color:var(--neon-purple); padding:0.75rem 1.5rem; border-radius:10px; text-decoration:none; font-family:'Rajdhani',sans-serif; font-weight:700; font-size:1rem; transition:all 0.3s; }
    .sav-btn:hover { background:rgba(168,85,247,0.2); transform:translateY(-2px); }

    @media (max-width:900px) {
      .grid-dash { grid-template-columns:1fr 1fr; }
    }
    @media (max-width:600px) {
      nav { padding:0 1rem; }
      .logo img { height:36px; }
      .nav-right { display:none; }
      .hamburger { display:flex; }
      .main { padding:1.5rem 1rem; }
      .grid-dash { grid-template-columns:1fr; gap:1rem; }
      .abo-actif { flex-direction:column; align-items:flex-start; }
      .btn-voir-bets { width:100%; justify-content:center; text-align:center; }
      .welcome-banner { flex-direction:column; padding:1.2rem; gap:0.75rem; }
      .welcome-banner h2 { font-size:1rem; }
      .section-block { padding:1.2rem 1rem; }
      .section-block h3 { font-size:0.9rem; }
      .chat-form { flex-direction:column; }
      .btn-send { height:48px; width:100%; }
      .chat-form textarea { height:70px; width:100%; }
      .sav-btn { width:100%; text-align:center; }
      .histo-table { font-size:0.8rem; }
      .histo-table th, .histo-table td { padding:0.5rem 0.4rem; }
      .badge-type { font-size:0.72rem; padding:0.3rem 0.7rem; white-space:nowrap; }
      .msg-bubble { max-width:92%; }
      .stat-card .value { font-size:1.3rem; }
    }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0a0e17">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StratEdge">
  <link rel="apple-touch-icon" href="/assets/images/mascotte.png">
</head>
<body>
<nav>
  <div class="nav-inner">
    <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge"></a>
    <div class="nav-right">
      <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
      <a href="historique.php">Historique</a>
      <?php else: ?>
      <a href="../historique.php">Historique</a>
      <?php endif; ?>
      <a href="/#stake">Stake.bet</a>
      <a href="/bets.php">📊 Les Bets</a>
      <a href="/#pricing" style="background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:#fff;padding:0.4rem 1rem;border-radius:6px;font-weight:700;">Souscrire</a>
      <?php if (isAdmin()): ?>
        <a href="panel-x9k3m/index.php" style="background:rgba(255,193,7,0.15);border:1px solid rgba(255,193,7,0.3);color:#ffc107;padding:0.4rem 1rem;border-radius:6px;font-weight:700;">⚙️ Panel</a>
      <?php endif; ?>
      <a href="/profil.php">👤 Mon Profil</a>
      <a href="/sav.php">🎫 SAV</a>
      <a href="/logout.php" class="nav-logout">Déconnexion</a>
    </div>
  </div>
</nav>

<div class="main">

  <?php if ($welcome): ?>
  <div class="welcome-banner">
    <span style="font-size:2rem;">🎉</span>
    <div>
      <h2>Bienvenue <?= clean($membre['nom']) ?> !</h2>
      <p>Votre compte est créé. Choisissez une formule pour accéder aux bets.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats rapides -->
  <div class="grid-dash">
    <div class="stat-card">
      <div class="label">Statut abonnement</div>
      <?php if ($abonnement): ?>
        <div class="value" style="color:var(--neon-green)">Actif</div>
        <div class="sub"><?= $typeLabels[$abonnement['type']] ?? $abonnement['type'] ?></div>
      <?php else: ?>
        <div class="value" style="color:var(--text-muted)">Aucun</div>
        <div class="sub">Pas d'abonnement actif</div>
      <?php endif; ?>
    </div>
    <div class="stat-card">
      <div class="label">Membre depuis</div>
      <div class="value" style="font-size:1.2rem;"><?= date('d/m/Y', strtotime($membre['date_inscription'])) ?></div>
      <div class="sub"><?= clean($membre['email']) ?></div>
    </div>
    <div class="stat-card">
      <div class="label">Achats total</div>
      <div class="value"><?= count($historique) ?></div>
      <div class="sub">abonnement<?= count($historique) > 1 ? 's' : '' ?> au total</div>
    </div>
  </div>

  <!-- Abonnement actif -->
  <div class="section-block">
    <h3><span class="dot"></span> Abonnement actif</h3>
    <?php if ($abonnement): ?>
      <div class="abo-actif">
        <div>
          <div class="abo-type"><?= $typeLabels[$abonnement['type']] ?? $abonnement['type'] ?></div>
          <div class="abo-expire">
            <?php if ($abonnement['type'] === 'daily'): ?>
              ⚡ Actif jusqu'au prochain bet posté
            <?php else: ?>
              📅 Expire le <?= date('d/m/Y à H:i', strtotime($abonnement['date_fin'])) ?>
            <?php endif; ?>
          </div>
        </div>
        <a href="/bets.php" class="btn-voir-bets">📊 Voir mes bets →</a>
      </div>
    <?php else: ?>
      <div class="no-abo">
        <p style="font-size:1.2rem; margin-bottom:0.5rem;">Aucun abonnement actif</p>
        <p style="margin-bottom:1rem;">Souscrivez à une formule pour accéder aux bets.</p>
        <a href="/#pricing" class="btn-voir-bets">Voir les formules →</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Historique achats -->
  <div class="section-block">
    <h3><span class="dot"></span> Historique des achats</h3>
    <?php if (empty($historique)): ?>
      <p style="color:var(--text-muted); text-align:center; padding:1rem;">Aucun achat pour le moment.</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="histo-table">
          <thead>
            <tr>
              <th>Formule</th>
              <th>Date d'achat</th>
              <th>Expiration</th>
              <th>Montant</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historique as $h): ?>
            <tr>
              <td><span style="font-family:'Orbitron',sans-serif; font-size:0.85rem;"><?= $typeLabels[$h['type']] ?? $h['type'] ?></span></td>
              <td><?= date('d/m/Y H:i', strtotime($h['date_achat'])) ?></td>
              <td><?= $h['type'] === 'rasstoss' ? '♾️ À vie' : ($h['date_fin'] ? date('d/m/Y H:i', strtotime($h['date_fin'])) : 'Au prochain bet') ?></td>
              <td style="color:var(--neon-green); font-weight:700;"><?= number_format($h['montant'], 2) ?>€</td>
              <td>
                <?php
                $isActif = $h['actif'] && ($h['type'] === 'daily' || $h['type'] === 'rasstoss' || strtotime($h['date_fin']) > time());
                ?>
                <?php if ($h['type'] === 'rasstoss'): ?>
                <span class="badge-type badge-rasstoss">👑 À vie</span>
                <?php else: ?>
                <span class="badge-type <?= $isActif ? 'badge-actif' : 'badge-expire' ?>">
                  <?= $isActif ? '✓ Actif' : 'Expiré' ?>
                </span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Messagerie & Chat -->
  <div class="section-block" id="chat">
    <h3><span class="dot"></span> Messagerie avec le support</h3>
    <?php
    // Compter messages non lus
    $nbNonLus = 0;
    $stmtNl = $db->prepare("SELECT COUNT(*) FROM messages WHERE membre_id = ? AND expediteur = 'admin' AND lu = 0");
    $stmtNl->execute([$membre['id']]);
    $nbNonLus = (int)$stmtNl->fetchColumn();
    ?>
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
      <a href="chat.php" style="display:inline-flex;align-items:center;gap:0.7rem;background:linear-gradient(135deg,rgba(255,45,120,0.12),rgba(214,36,95,0.06));border:1px solid rgba(255,45,120,0.35);color:#ff2d78;text-decoration:none;padding:0.9rem 1.5rem;border-radius:12px;font-weight:700;font-size:0.95rem;transition:all .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='';">
        💬 Ouvrir le chat
        <?php if ($nbNonLus > 0): ?>
          <span style="background:var(--neon-green);color:#000;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:900;"><?= $nbNonLus ?></span>
        <?php endif; ?>
      </a>
      <p style="color:var(--text-secondary);font-size:0.85rem;margin:0;">L'équipe StratEdge répond généralement sous quelques heures.</p>
    </div>
  </div>

  <!-- SAV -->
  <div class="section-block">
    <h3><span class="dot"></span> Besoin d'aide ?</h3>
    <p style="color:var(--text-secondary); margin-bottom:1.5rem;">Pour un problème technique ou une réclamation, ouvrez un ticket SAV. Je traite chaque demande personnellement.</p>
    <a href="/sav.php" class="sav-btn">🎫 Ouvrir un ticket SAV</a>
  </div>

</div>

<script>
  // Scroll vers le bas du chat automatiquement
  const chatArea = document.getElementById('chatArea');
  if (chatArea) chatArea.scrollTop = chatArea.scrollHeight;
</script>
<script>
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
</script>
<?php require_once __DIR__ . '/includes/footer-legal.php'; ?>

</body>
</html>
