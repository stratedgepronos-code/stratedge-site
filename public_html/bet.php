<?php
require_once __DIR__ . '/includes/auth.php';
$db = getDB();
$membre = isLoggedIn() ? getMembre() : null;
$abonnement = $membre ? getAbonnementActif($membre['id']) : null;
$hasAcces = ($abonnement !== null) || (isLoggedIn() && isAdmin());
$currentPage = 'bets';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;

$betId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($betId <= 0) {
    header('Location: /bets.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM bets WHERE id = ? AND actif = 1 LIMIT 1");
$stmt->execute([$betId]);
$bet = $stmt->fetch();
if (!$bet) {
    header('Location: /bets.php');
    exit;
}

// Vérifier accès (même logique que bets.php)
$typeAbo = $abonnement['type'] ?? '';
$autorisé = false;
if (isAdmin() && $membre) {
    $autorisé = true;
} elseif ($typeAbo === 'rasstoss') {
    $autorisé = true;
} elseif ($typeAbo === 'tennis') {
    $autorisé = (($bet['categorie'] ?? 'multi') === 'tennis');
} else {
    $autorisé = (($bet['categorie'] ?? 'multi') === 'multi');
}
if (!$autorisé && $membre) {
    header('Location: /bets.php');
    exit;
}
if (!$membre) {
    header('Location: /login.php?redirect=' . urlencode('/bet.php?id=' . $betId));
    exit;
}

// POST : ajouter un commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasAcces && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $commentError = 'Erreur de sécurité.';
    } else {
        $contenu = trim((string) ($_POST['contenu'] ?? ''));
        $contenu = mb_substr($contenu, 0, 2000);
        if ($contenu !== '') {
            $ins = $db->prepare("INSERT INTO bet_comments (bet_id, membre_id, contenu) VALUES (?, ?, ?)");
            $ins->execute([$betId, $membre['id'], $contenu]);
        }
        header('Location: /bet.php?id=' . $betId . '#commentaires');
        exit;
    }
}

// Charger les commentaires
$comments = $db->prepare("
    SELECT c.*, m.nom AS pseudo, m.photo_profil
    FROM bet_comments c
    JOIN membres m ON m.id = c.membre_id
    WHERE c.bet_id = ?
    ORDER BY c.date_post ASC
");
$comments->execute([$betId]);
$comments = $comments->fetchAll();

$rawPath = !empty($bet['image_path']) ? $bet['image_path'] : ($bet['locked_image_path'] ?? '');
$imgSrc = '';
if (!empty($rawPath)) {
    $subdir = (strpos($rawPath, 'locked') !== false) ? 'locked' : 'bets';
    $imgSrc = function_exists('betImageUrl') ? betImageUrl(trim($rawPath), $subdir) : (defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/' . ltrim($rawPath, '/') : $rawPath);
}

$types = array_map('trim', explode(',', $bet['type']));
$typeLabels = ['safe' => '🛡️ Safe', 'fun' => '🎯 Fun', 'live' => '⚡ Live', 'safe,fun' => 'Safe+Fun', 'safe,live' => 'Safe+Live'];
$typeColors = ['safe' => '#00d4ff', 'fun' => '#a855f7', 'live' => '#ff2d78'];

// On conserve les scripts pour l'affichage des graphiques (Chart.js, ApexCharts, etc.). Contenu défini par l'admin uniquement.
$analyseHtml = $bet['analyse_html'] ?? '';
if ($analyseHtml !== '') {
    $analyseHtml = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $analyseHtml);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= clean($bet['titre'] ?: 'Bet') ?> – StratEdge Pronos</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
:root{--bg:#050810;--card:#111827;--pink:#ff2d78;--txt:#f0f4f8;--txt2:#b0bec9;--txt3:#8a9bb0;--border:rgba(255,45,120,0.15);}
/* Full width : annuler le padding du .content pour utiliser toute la largeur */
.bet-page-wrap{max-width:none;width:100%;margin:0 -3rem;padding:1.5rem 3rem 3rem;box-sizing:border-box;}
.bet-back{margin-bottom:1.5rem;}
.bet-back a{color:var(--txt2);text-decoration:none;font-size:0.9rem;display:inline-flex;align-items:center;gap:0.5rem;}
.bet-back a:hover{color:var(--pink);}
.bet-header{background:var(--card);border-radius:16px;border:1px solid var(--border);overflow:hidden;margin-bottom:1.5rem;}
.bet-header-top{padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;}
.bet-badges{display:flex;gap:0.4rem;flex-wrap:wrap;}
.bet-badge{padding:0.25rem 0.7rem;border-radius:6px;font-family:'Orbitron',sans-serif;font-size:0.65rem;font-weight:700;letter-spacing:1px;}
.bet-date{font-family:'Space Mono',monospace;font-size:0.75rem;color:var(--txt3);}
.bet-titre{font-family:'Orbitron',sans-serif;font-size:1.25rem;font-weight:700;padding:0 1.25rem 1rem;color:var(--txt);line-height:1.3;}
.bet-img-wrap{width:100%;max-height:420px;overflow:hidden;}
.bet-img-wrap img{width:100%;height:auto;display:block;object-fit:contain;}
.bet-analyse{margin-top:1.5rem;}
.bet-analyse-title{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--pink);margin-bottom:1rem;}
.bet-analyse-inner{background:var(--card);border-radius:14px;border:1px solid var(--border);padding:1rem;overflow:visible;width:100%;max-width:none;}
.bet-analyse-inner iframe{max-width:100%;border:none;}
.bet-analyse-inner img{max-width:100%;height:auto;}
/* Forcer le contenu HTML injecté (card) à utiliser toute la largeur */
.bet-analyse-inner>*{max-width:100%!important;box-sizing:border-box;}
.bet-analyse-inner .card-wrapper,
.bet-analyse-inner .card,
.bet-analyse-inner [class*="wrapper"],
.bet-analyse-inner [class*="card"]{max-width:none!important;width:100%!important;}
/* Chart.js : conteneurs avec hauteur explicite, pas de overflow hidden sur les ancêtres */
.bet-analyse-inner .chart-box{height:220px;position:relative;min-height:180px;}
.bet-analyse-inner .sec{overflow:visible!important;}
.bet-analyse-inner canvas{display:block;}
.bet-analyse-inner .player .chart-box{width:280px;min-width:240px;height:220px;position:relative;}
.bet-comments{margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--border);}
.bet-comments h2{font-family:'Orbitron',sans-serif;font-size:1.1rem;margin-bottom:1rem;color:var(--txt);}
.comment-form{background:var(--card);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;border:1px solid var(--border);}
.comment-form textarea{width:100%;min-height:100px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:0.75rem;color:var(--txt);font-family:'Rajdhani',sans-serif;font-size:1rem;resize:vertical;}
.comment-form textarea:focus{outline:none;border-color:var(--pink);}
.comment-form .btn-submit{background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;border:none;padding:0.65rem 1.4rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;margin-top:0.75rem;}
.comment-form .btn-submit:hover{box-shadow:0 0 20px rgba(255,45,120,0.4);}
.comment-list{display:flex;flex-direction:column;gap:1rem;}
.comment-item{background:var(--card);border-radius:12px;padding:1rem 1.2rem;border:1px solid var(--border);}
.comment-author{display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;}
.comment-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--pink),#a855f7);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;}
.comment-meta{font-size:0.8rem;color:var(--txt3);}
.comment-body{color:var(--txt2);font-size:0.95rem;line-height:1.5;white-space:pre-wrap;}
.no-comments{color:var(--txt3);font-size:0.9rem;padding:1rem 0;}
/* Page bet : masquer la mascotte pour utiliser toute la largeur */
body.page-bet .mascotte-bg{display:none;}
@media(max-width:768px){
.bet-page-wrap{margin-left:-0.8rem;margin-right:-0.8rem;padding-left:0.8rem;padding-right:0.8rem;}
.bet-analyse-inner .chart-box,
.bet-analyse-inner .player .chart-box{width:100%!important;height:240px;min-width:0;}
}
</style>
</head>
<body class="page-bet">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="bet-page-wrap">
  <div class="bet-back">
    <a href="/bets.php">← Retour aux bets</a>
  </div>

  <article class="bet-header">
    <div class="bet-header-top">
      <div class="bet-badges">
        <?php foreach ($types as $t): $c = $typeColors[$t] ?? '#ff2d78'; ?>
        <span class="bet-badge" style="background:<?= $c ?>18;color:<?= $c ?>;border:1px solid <?= $c ?>40;"><?= $typeLabels[$t] ?? $t ?></span>
        <?php endforeach; ?>
      </div>
      <span class="bet-date"><?= date('d/m/Y à H:i', strtotime($bet['date_post'])) ?></span>
    </div>
    <?php if ($bet['titre']): ?>
    <h1 class="bet-titre"><?= clean($bet['titre']) ?></h1>
    <?php endif; ?>
  </article>

  <?php if ($analyseHtml !== ''): ?>
  <section class="bet-analyse" id="analyse">
    <h2 class="bet-analyse-title">📋 Analyse</h2>
    <script>
    (function(){
      var _real = null;
      Object.defineProperty(window, 'Chart', {
        configurable: true,
        get: function(){ return _real; },
        set: function(C){
          if (typeof C !== 'function') { _real = C; return; }
          _real = new Proxy(C, {
            construct: function(target, args){
              if (args[1] && args[1].options) {
                args[1].options.responsive = true;
                args[1].options.maintainAspectRatio = false;
              } else if (args[1]) {
                args[1].options = { responsive: true, maintainAspectRatio: false };
              }
              return new target(...args);
            }
          });
          Object.assign(_real, C);
          _real.prototype = C.prototype;
          if (C.defaults) _real.defaults = C.defaults;
          if (C.register) _real.register = C.register.bind(C);
          if (C.instances) _real.instances = C.instances;
        }
      });
    })();
    </script>
    <div class="bet-analyse-inner">
      <?= $analyseHtml ?>
    </div>
    <script>
    (function(){
      document.querySelectorAll('.bet-analyse-inner canvas').forEach(function(c){
        if (!c.getAttribute('height')) c.setAttribute('height','200');
        var box = c.closest('.chart-box');
        if (box) { box.style.height = box.style.height || '220px'; box.style.position = 'relative'; }
        var sec = c.closest('.sec');
        if (sec) sec.style.overflow = 'visible';
      });
      window.addEventListener('load', function(){
        if (!window.Chart || !window.Chart.instances) return;
        Object.values(window.Chart.instances).forEach(function(ch){
          if (ch && ch.options) { ch.options.maintainAspectRatio = false; ch.resize(); }
        });
      });
    })();
    </script>
  </section>
  <?php endif; ?>

  <section class="bet-comments" id="commentaires">
    <h2>💬 Échanges & actus</h2>
    <p class="bet-comments-desc" style="color:var(--txt3);font-size:0.9rem;margin-bottom:1rem;">Partage tes idées sur le match, l'actus, le prono.</p>

    <?php if ($hasAcces): ?>
    <form method="post" class="comment-form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="add_comment">
      <textarea name="contenu" placeholder="Ton message..." required maxlength="2000"></textarea>
      <button type="submit" class="btn-submit">Publier</button>
    </form>
    <?php endif; ?>

    <div class="comment-list">
      <?php if (empty($comments)): ?>
      <p class="no-comments">Aucun commentaire pour l'instant. Sois le premier à réagir !</p>
      <?php else: ?>
      <?php foreach ($comments as $c): ?>
      <div class="comment-item">
        <div class="comment-author">
          <div class="comment-avatar"><?= mb_substr($c['pseudo'] ?? 'M', 0, 1) ?></div>
          <div class="comment-meta">
            <strong><?= clean($c['pseudo'] ?? 'Membre') ?></strong> · <?= date('d/m/Y H:i', strtotime($c['date_post'])) ?>
          </div>
        </div>
        <div class="comment-body"><?= nl2br(clean($c['contenu'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
