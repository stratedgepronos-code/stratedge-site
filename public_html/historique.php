<?php
require_once __DIR__ . '/includes/auth.php';

$db = getDB();
$membre = isLoggedIn() ? getMembre() : null;

// Bets avec résultat (hors en_cours), triés par date résultat
$bets = $db->query("
    SELECT * FROM bets 
    WHERE resultat != 'en_cours' 
    ORDER BY date_resultat DESC, date_post DESC
")->fetchAll();

$stats = $db->query("
    SELECT 
        SUM(resultat='gagne')  as gagnes,
        SUM(resultat='perdu')  as perdus,
        SUM(resultat='annule') as annules,
        COUNT(*) as total
    FROM bets WHERE resultat != 'en_cours'
")->fetch();

$typeLabels = ['safe'=>'🛡️ Safe','fun'=>'🎯 Fun','live'=>'⚡ Live','safe,fun'=>'Safe+Fun','safe,live'=>'Safe+Live'];
$typeColors = ['safe'=>'#00d4ff','fun'=>'#a855f7','live'=>'#ff2d78'];

$resultatConfig = [
    'gagne'  => ['label'=>'✅ Gagné',   'color'=>'#ff2d78', 'bg'=>'rgba(0,200,100,0.12)',  'border'=>'rgba(0,200,100,0.35)',  'icon'=>'✅', 'overlay'=>'rgba(0,200,100,0.15)'],
    'perdu'  => ['label'=>'❌ Perdu',   'color'=>'#ff4444', 'bg'=>'rgba(255,68,68,0.12)',   'border'=>'rgba(255,68,68,0.35)',   'icon'=>'❌', 'overlay'=>'rgba(255,68,68,0.15)'],
    'annule' => ['label'=>'↺ Annulé', 'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,0.12)', 'border'=>'rgba(245,158,11,0.35)', 'icon'=>'↺', 'overlay'=>'rgba(245,158,11,0.1)'],
];

$tauxReussite = ($stats['total'] > 0 && ($stats['gagnes'] + $stats['perdus']) > 0)
    ? round($stats['gagnes'] / ($stats['gagnes'] + $stats['perdus']) * 100)
    : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historique des Bets – StratEdge Pronos</title>
  <link rel="icon" type="image/png" href="assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#0a0e17;--bg-card:#111827;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.15);--glow-green:0 0 30px rgba(255,45,120,0.35);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;}
    body::before{content:'';position:fixed;inset:0;background:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");pointer-events:none;z-index:0;}
    
    nav{background:rgba(10,14,23,0.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-subtle);padding:0 2rem;position:sticky;top:0;z-index:100;}
    .nav-inner{max-width:1600px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:70px;}
    .logo img{height:45px;}
    .nav-links{display:flex;align-items:center;gap:2rem;}
    .nav-links a{color:var(--text-secondary);text-decoration:none;font-size:0.9rem;font-weight:500;text-transform:uppercase;letter-spacing:1px;transition:color 0.3s;}
    .nav-links a:hover,.nav-links a.active{color:var(--neon-green);}
    .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:5px;background:none;border:none;}
    .hamburger span{display:block;width:24px;height:2px;background:var(--text-primary);border-radius:2px;}
    .mobile-menu{display:none;position:fixed;inset:0;top:70px;background:rgba(5,8,16,0.98);backdrop-filter:blur(20px);z-index:99;padding:2rem;flex-direction:column;overflow-y:auto;}
    .mobile-menu.open{display:flex;}
    .mobile-menu a{color:var(--text-secondary);text-decoration:none;font-size:1.1rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;padding:1.2rem 0;border-bottom:1px solid rgba(255,255,255,0.05);transition:color 0.2s;}
    .mobile-menu a:hover{color:var(--neon-green);}
    @media(max-width:768px){
      .nav-links{display:none;}
      .hamburger{display:flex;}
    }

    .page-header{background:linear-gradient(180deg,#0d1220 0%,var(--bg-dark) 100%);padding:3.5rem 2rem;text-align:center;position:relative;overflow:hidden;}
    .page-header::before{content:'';position:absolute;width:700px;height:400px;background:radial-gradient(circle,rgba(255,45,120,0.07) 0%,transparent 70%);top:-150px;left:50%;transform:translateX(-50%);pointer-events:none;}
    .page-tag{font-family:'Space Mono',monospace;font-size:0.7rem;letter-spacing:3px;text-transform:uppercase;color:var(--neon-green);margin-bottom:0.75rem;}
    .page-title{font-family:'Orbitron',sans-serif;font-size:2.2rem;font-weight:900;margin-bottom:0.75rem;}
    .page-sub{color:var(--text-secondary);font-size:1rem;}

    /* STATS GLOBALES */
    .main{max-width:1600px;margin:0 auto;padding:2.5rem 3rem;position:relative;z-index:1;}
    .stats-bar{display:grid;grid-template-columns:repeat(5,1fr);gap:1.2rem;margin-bottom:2.5rem;}
    .stat-card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.4rem;text-align:center;}
    .stat-value{font-family:'Orbitron',sans-serif;font-size:2rem;font-weight:900;margin-bottom:0.25rem;}
    .stat-label{font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;}
    .stat-card.gagne{border-color:rgba(0,200,100,0.25);background:rgba(0,200,100,0.04);}
    .stat-card.perdu{border-color:rgba(255,68,68,0.25);background:rgba(255,68,68,0.04);}
    .stat-card.taux{border-color:rgba(255,45,120,0.25);background:rgba(255,45,120,0.04);}

    /* FILTRE */
    .filters{display:flex;gap:0.6rem;margin-bottom:2rem;flex-wrap:wrap;}
    .filter-btn{padding:0.5rem 1.2rem;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:0.9rem;font-weight:600;border:1px solid rgba(255,255,255,0.1);color:var(--text-muted);cursor:pointer;transition:all 0.2s;text-decoration:none;background:transparent;}
    .filter-btn:hover,.filter-btn.active{background:rgba(255,45,120,0.1);border-color:var(--neon-green);color:var(--text-primary);}
    .filter-btn.f-gagne.active{background:rgba(255,45,120,0.08);border-color:#ff2d78;color:#ff2d78;}
    .filter-btn.f-perdu.active{background:rgba(255,68,68,0.1);border-color:#ff4444;color:#ff4444;}
    .filter-btn.f-annule.active{background:rgba(245,158,11,0.1);border-color:#f59e0b;color:#f59e0b;}

    /* GRILLE BETS */
    .bets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(320px,100%),1fr));gap:1.5rem;}
    .bet-card{background:var(--bg-card);border-radius:16px;overflow:hidden;transition:all 0.3s;position:relative;}
    .bet-card:hover{transform:translateY(-4px);box-shadow:0 20px 50px rgba(0,0,0,0.4);}

    .bet-card-header{padding:0.9rem 1.2rem;display:flex;align-items:center;justify-content:space-between;}
    .bet-type-badge{padding:0.25rem 0.7rem;border-radius:6px;font-family:'Orbitron',sans-serif;font-size:0.62rem;font-weight:700;letter-spacing:1px;}
    .bet-date{font-size:0.78rem;color:var(--text-muted);}
    .bet-titre{font-family:'Orbitron',sans-serif;font-size:0.85rem;padding:0 1.2rem 0.7rem;color:var(--text-secondary);}

    /* IMAGE + OVERLAY RÉSULTAT */
    .bet-image-wrap{position:relative;cursor:zoom-in;}
    .bet-image{width:100%;display:block;}
    .result-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.5rem;}
    .result-icon{font-size:4rem;filter:drop-shadow(0 0 20px currentColor);}
    .result-label{font-family:'Orbitron',sans-serif;font-size:1.2rem;font-weight:900;letter-spacing:2px;text-shadow:0 0 20px currentColor;}
    .zoom-hint{position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.65);color:white;font-size:0.7rem;padding:0.25rem 0.55rem;border-radius:5px;pointer-events:none;opacity:0;transition:opacity 0.2s;}
    .bet-image-wrap:hover .zoom-hint{opacity:1;}

    /* BADGE RÉSULTAT bas de carte */
    .bet-footer{padding:0.75rem 1.2rem;border-top:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:space-between;}
    .result-badge{padding:0.3rem 0.9rem;border-radius:6px;font-size:0.78rem;font-weight:700;}
    .result-date{font-size:0.75rem;color:var(--text-muted);}

    /* VIDE */
    .empty-state{text-align:center;padding:4rem 2rem;color:var(--text-muted);}
    .empty-state .big{font-size:3rem;margin-bottom:1rem;}
    .empty-state h3{font-family:'Orbitron',sans-serif;font-size:1.2rem;color:var(--text-secondary);margin-bottom:0.5rem;}

    /* LIGHTBOX */
    .lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(5,8,16,0.96);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:2rem;}
    .lightbox.open{display:flex;}
    .lightbox-img{max-width:95vw;max-height:90vh;border-radius:12px;box-shadow:0 0 60px rgba(255,45,120,0.2);display:block;}
    .lightbox-close{position:fixed;top:1.2rem;right:1.5rem;background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.3);color:white;width:42px;height:42px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
    .lightbox-close:hover{background:var(--neon-green);}
    .lightbox-caption{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);color:var(--text-muted);font-size:0.85rem;text-align:center;}

    @media(max-width:900px){
      .stats-bar{grid-template-columns:repeat(3,1fr);}
      .main{padding:2rem 1.5rem;}
    }
    @media(max-width:768px){
      html{overflow-x:hidden;}
      body{overflow-x:hidden;}
      nav{padding:0 0.8rem;}
      .nav-inner{height:50px;}
      .logo img{height:28px;}
      .mobile-menu{top:50px;}
      .page-header{padding:1.8rem 0.8rem;overflow:hidden;}
      .page-header::before{width:400px;height:250px;top:-100px;}
      .page-title{font-size:1.5rem;}
      .page-tag{font-size:0.62rem;letter-spacing:2px;}
      .page-sub{font-size:0.85rem;}
      .main{padding:1.2rem 0.8rem;}
      .stats-bar{grid-template-columns:repeat(2,1fr);gap:0.7rem;}
      .stat-value{font-size:1.4rem;}
      .stat-label{font-size:0.65rem;letter-spacing:0.5px;}
      .stat-card{padding:0.9rem 0.7rem;border-radius:10px;}
      .bets-grid{grid-template-columns:1fr;gap:1rem;}
      .bet-card{border-radius:12px;}
      .bet-card:hover{transform:none;box-shadow:none;}
      .filters{gap:0.4rem;}
      .filter-btn{padding:0.4rem 0.85rem;font-size:0.8rem;min-height:36px;}
      .bet-footer{flex-direction:column;gap:0.3rem;align-items:flex-start;padding:0.6rem 1rem;}
      .result-icon{font-size:2.8rem;}
      .result-label{font-size:0.95rem;}
      .empty-state{padding:3rem 1.5rem;}
      .empty-state .big{font-size:2.2rem;}
      .empty-state h3{font-size:1rem;}
      .lightbox-img{max-height:80dvh;border-radius:8px;}
    }
    @media(max-width:380px){
      .stats-bar{grid-template-columns:1fr 1fr;gap:0.5rem;}
      .stat-value{font-size:1.1rem;}
      .stat-card{padding:0.7rem 0.5rem;}
      .page-title{font-size:1.2rem;}
      .main{padding:1rem 0.5rem;}
      .filter-btn{padding:0.3rem 0.55rem;font-size:0.72rem;}
      .bet-card-header{padding:0.6rem 0.8rem;}
      .bet-type-badge{font-size:0.52rem;}
      .bet-titre{font-size:0.75rem;padding:0 0.8rem 0.5rem;}
      .result-badge{font-size:0.7rem;padding:0.2rem 0.6rem;}
      .result-date{font-size:0.68rem;}
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
    <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge Pronos"></a>
    <div class="nav-links">
      <a href="/#pricing">Tarifs</a>
      <a href="bets.php">Les Bets</a>
      <a href="historique.php" class="active">Historique</a>
      <?php if (isLoggedIn()): ?>
        <a href="dashboard.php">👤 <?= clean($membre['nom']) ?></a>
      <?php else: ?>
        <a href="login.php">Connexion</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
  </div>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <a href="/#pricing" onclick="toggleMenu()">Tarifs</a>
  <a href="bets.php" onclick="toggleMenu()">Les Bets</a>
  <a href="historique.php" onclick="toggleMenu()">Historique</a>
  <?php if (isLoggedIn()): ?>
    <a href="dashboard.php" onclick="toggleMenu()">👤 Mon Espace</a>
    <a href="logout.php">Déconnexion</a>
  <?php else: ?>
    <a href="login.php" onclick="toggleMenu()">Connexion</a>
    <a href="register.php" onclick="toggleMenu()">S'inscrire</a>
  <?php endif; ?>
</div>

<div class="page-header">
  <div class="page-tag">Transparence totale</div>
  <h1 class="page-title">📊 Historique des <span style="color:var(--neon-green)">Bets</span></h1>
  <p class="page-sub">Tous nos résultats passés, en toute transparence. Aucun filtre, aucune triche.</p>
</div>

<div class="main">

  <!-- STATS -->
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-value" style="color:var(--text-primary)"><?= $stats['total'] ?? 0 ?></div>
      <div class="stat-label">Total bets</div>
    </div>
    <div class="stat-card gagne">
      <div class="stat-value" style="color:#ff2d78"><?= $stats['gagnes'] ?? 0 ?></div>
      <div class="stat-label">✅ Gagnés</div>
    </div>
    <div class="stat-card perdu">
      <div class="stat-value" style="color:#ff4444"><?= $stats['perdus'] ?? 0 ?></div>
      <div class="stat-label">❌ Perdus</div>
    </div>
    <div class="stat-card" style="border-color:rgba(245,158,11,0.25);background:rgba(245,158,11,0.04);">
      <div class="stat-value" style="color:#f59e0b"><?= $stats['annules'] ?? 0 ?></div>
      <div class="stat-label">↺ Annulés</div>
    </div>
    <div class="stat-card taux">
      <div class="stat-value" style="color:var(--neon-green)"><?= $tauxReussite !== null ? $tauxReussite . '%' : '—' ?></div>
      <div class="stat-label">Taux de réussite</div>
    </div>
  </div>

  <!-- FILTRES -->
  <?php $filtre = $_GET['filtre'] ?? 'tous'; ?>
  <div class="filters">
    <a href="?filtre=tous"   class="filter-btn <?= $filtre==='tous'?'active':'' ?>">Tous (<?= $stats['total'] ?? 0 ?>)</a>
    <a href="?filtre=gagne"  class="filter-btn f-gagne <?= $filtre==='gagne'?'active':'' ?>">✅ Gagnés (<?= $stats['gagnes'] ?? 0 ?>)</a>
    <a href="?filtre=perdu"  class="filter-btn f-perdu <?= $filtre==='perdu'?'active':'' ?>">❌ Perdus (<?= $stats['perdus'] ?? 0 ?>)</a>
    <a href="?filtre=annule" class="filter-btn f-annule <?= $filtre==='annule'?'active':'' ?>">↺ Annulés (<?= $stats['annules'] ?? 0 ?>)</a>
  </div>

  <!-- GRILLE -->
  <?php
  $betsFiltres = $filtre === 'tous' ? $bets : array_filter($bets, fn($b) => $b['resultat'] === $filtre);
  ?>
  <?php if (empty($betsFiltres)): ?>
    <div class="empty-state">
      <div class="big">📭</div>
      <h3>Aucun résultat pour ce filtre</h3>
      <p>Les bets terminés apparaîtront ici automatiquement.</p>
    </div>
  <?php else: ?>
    <div class="bets-grid">
      <?php foreach ($betsFiltres as $bet):
        $rc  = $resultatConfig[$bet['resultat']];
        $types = explode(',', $bet['type']);
        $rawPath = !empty($bet['image_path']) ? $bet['image_path'] : ($bet['locked_image_path'] ?? '');
        if (!empty($rawPath)) {
          $rawPath = str_replace('\\', '/', trim($rawPath));
          $imgSrc = (strpos($rawPath, 'http') === 0) ? $rawPath : (defined('SITE_URL') ? rtrim(SITE_URL,'/').'/'.ltrim($rawPath,'/') : '/'.ltrim($rawPath,'/'));
        } else {
          $imgSrc = '';
        }
      ?>
      <div class="bet-card" style="border:1px solid <?= $rc['border'] ?>;">
        <div class="bet-card-header">
          <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
            <?php foreach ($types as $t): $t = trim($t); ?>
              <span class="bet-type-badge" style="background:<?= $typeColors[$t] ?? '#ff2d78' ?>22;color:<?= $typeColors[$t] ?? '#ff2d78' ?>;border:1px solid <?= $typeColors[$t] ?? '#ff2d78' ?>44;">
                <?= $typeLabels[$t] ?? $t ?>
              </span>
            <?php endforeach; ?>
          </div>
          <span class="bet-date"><?= date('d/m/Y', strtotime($bet['date_post'])) ?></span>
        </div>

        <?php if ($bet['titre']): ?>
          <div class="bet-titre"><?= clean($bet['titre']) ?></div>
        <?php endif; ?>

        <?php if ($imgSrc): ?>
          <div class="bet-image-wrap"
               data-src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>"
               data-caption="<?= htmlspecialchars($bet['titre'] ?: 'Bet StratEdge', ENT_QUOTES) ?>">
            <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" class="bet-image" alt="Bet">
            <!-- Overlay résultat semi-transparent sur l'image -->
            <div class="result-overlay" style="background:<?= $rc['overlay'] ?>;backdrop-filter:blur(1px);">
              <div class="result-icon" style="color:<?= $rc['color'] ?>;"><?= $rc['icon'] ?></div>
              <div class="result-label" style="color:<?= $rc['color'] ?>;"><?= strtoupper(str_replace(['✅ ','❌ ','↺ '], '', $rc['label'])) ?></div>
            </div>
            <div class="zoom-hint">🔍 Agrandir</div>
          </div>
        <?php else: ?>
          <div style="width:100%;aspect-ratio:16/9;background:<?= $rc['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:4rem;">
            <?= $rc['icon'] ?>
          </div>
        <?php endif; ?>

        <div class="bet-footer">
          <span class="result-badge" style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;border:1px solid <?= $rc['border'] ?>;">
            <?= $rc['label'] ?>
          </span>
          <?php if ($bet['date_resultat']): ?>
            <span class="result-date">le <?= date('d/m/Y', strtotime($bet['date_resultat'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()">✕</button>
  <img src="" alt="" class="lightbox-img" id="lightboxImg">
  <div class="lightbox-caption" id="lightboxCaption"></div>
</div>

<script>
document.querySelectorAll('.bet-image-wrap[data-src]').forEach(el => {
  el.addEventListener('click', () => {
    document.getElementById('lightboxImg').src     = el.dataset.src;
    document.getElementById('lightboxCaption').textContent = el.dataset.caption || '';
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
  });
});
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.getElementById('lightboxImg').src = '';
  document.body.style.overflow = '';
}
document.getElementById('lightbox').addEventListener('click', function(e){ if(e.target===this) closeLightbox(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeLightbox(); });
</script>
<script>
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
