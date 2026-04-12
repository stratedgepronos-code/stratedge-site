<?php
require_once __DIR__ . '/includes/auth.php';
$db = getDB();
$membre = isLoggedIn() ? getMembre() : null;
$abonnement = $membre ? getAbonnementActif($membre['id']) : null;
require_once __DIR__ . '/includes/bet-access.php';
$hasAccesGlobal = (isLoggedIn() && isAdmin()); // admin = accès total toujours
$hasAcces = $hasAccesGlobal; // valeur par défaut, sera recalculée par bet
$currentPage = 'bets';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;

$typeAbo = $abonnement['type'] ?? '';
if (isAdmin() && $membre) {
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 ORDER BY date_post DESC");
} elseif ($typeAbo === 'rasstoss') {
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 ORDER BY date_post DESC");
} elseif ($typeAbo === 'tennis') {
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 AND categorie = 'tennis' ORDER BY date_post DESC");
} else {
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 AND categorie = 'multi' ORDER BY date_post DESC");
}
$bets = $stmt->fetchAll();
$betsSafe = array_filter($bets, function($b) {
    $t = $b['type'];
    return (strpos($t, 'safe') !== false) && (strpos($t, 'live') === false) && (strpos($t, 'fun') === false);
});
$betsLive = array_filter($bets, function($b) { return strpos($b['type'], 'live') !== false; });
$betsFun  = array_filter($bets, function($b) { return strpos($b['type'], 'fun') !== false; });
$typeLabels = ['safe'=>'🛡️ Safe','fun'=>'🎯 Fun','live'=>'⚡ Live'];
$typeColors = ['safe'=>'#00d4ff','fun'=>'#a855f7','live'=>'#ff2d78'];
$nbSafe = count($betsSafe);
$nbLive = count($betsLive);
$nbFun  = count($betsFun);
$nbTotal = count($bets);

$sections = [
  'safe' => ['bets' => $betsSafe, 'title' => 'Safe', 'icon' => '🛡️', 'color' => '#00d4ff', 'count' => $nbSafe],
  'live' => ['bets' => $betsLive, 'title' => 'Live', 'icon' => '⚡', 'color' => '#ff2d78', 'count' => $nbLive],
  'fun'  => ['bets' => $betsFun,  'title' => 'Fun',  'icon' => '🎯', 'color' => '#a855f7', 'count' => $nbFun],
];
$availableSections = array_filter($sections, fn($s) => !empty($s['bets']));
$firstTab = array_key_first($availableSections) ?? 'safe';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Les Bets – StratEdge Pronos</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<meta name="apple-mobile-web-app-capable" content="yes"><link rel="apple-touch-icon" href="/assets/images/mascotte.png">
<?php if ($membre): ?>
  <?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<?php else: ?>
<style>
:root{--bg:#050810;--card:#111827;--pink:#ff2d78;--pink-dim:#d6245f;--blue:#00d4ff;--purple:#a855f7;--txt:#f0f4f8;--txt2:#b0bec9;--txt3:#8a9bb0;--border:rgba(255,45,120,0.15);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;overflow-x:hidden;}
nav{background:rgba(5,8,16,0.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 2rem;position:sticky;top:0;z-index:100;}
.nav-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:65px;}
.logo img{height:35px;}
.nav-right{display:flex;align-items:center;gap:1.5rem;}
.nav-right a{color:var(--txt2);text-decoration:none;font-size:0.95rem;font-weight:600;transition:color .3s;}
.nav-right a:hover{color:var(--pink);}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:5px;background:none;border:none;}
.hamburger span{display:block;width:24px;height:2px;background:var(--txt);border-radius:2px;}
.mobile-menu{display:none;position:fixed;inset:0;top:65px;background:rgba(5,8,16,0.98);backdrop-filter:blur(20px);z-index:99;padding:2rem;flex-direction:column;}
.mobile-menu.open{display:flex;}
.mobile-menu a{color:var(--txt2);text-decoration:none;font-size:1.05rem;font-weight:600;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}
@media(max-width:700px){.nav-right{display:none;}.hamburger{display:flex;}}
</style>
<?php endif; ?>
<style>
/* ═══ BETS PAGE V2 ═══ */

/* Hero — sans sidebar (visiteur) */
.bets-hero{position:relative;text-align:center;overflow:hidden;background:linear-gradient(180deg,rgba(255,45,120,0.07) 0%,transparent 100%);border-bottom:1px solid var(--border,rgba(255,45,120,0.15));margin-left:-2rem;margin-right:-2rem;margin-top:0;padding:3rem 2rem 2.5rem;}
/* Hero — avec sidebar (membre) : full-bleed */
.app .content > .bets-hero{margin-left:calc(-3rem - var(--sidebar-w,270px));margin-right:-3rem;margin-top:-2.5rem;padding:3.5rem 2rem 2.5rem 3rem;}
.bets-hero::before{content:'';position:absolute;width:700px;height:550px;background:radial-gradient(circle,rgba(255,45,120,0.12) 0%,transparent 65%);top:-380px;left:50%;transform:translateX(-50%);pointer-events:none;}
.bets-tag{font-family:'Space Mono',monospace;font-size:0.75rem;letter-spacing:4px;text-transform:uppercase;color:var(--pink,#ff2d78);margin-bottom:0.7rem;}
.bets-title{font-family:'Orbitron',sans-serif;font-size:2.4rem;font-weight:900;margin-bottom:0.6rem;}
.bets-title span{background:linear-gradient(135deg,#ff2d78,#ff6b9d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.bets-sub{color:var(--txt2,#b0bec9);font-size:1.05rem;max-width:500px;margin:0 auto;}
.bets-counter{display:inline-flex;align-items:center;gap:0.5rem;margin-top:1rem;padding:0.5rem 1.2rem;border-radius:50px;background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;color:var(--pink,#ff2d78);}
.bets-counter .pulse{width:8px;height:8px;border-radius:50%;background:#ff2d78;animation:pulse-dot 1.5s ease-in-out infinite;}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.4);}}

.bets-wrap{max-width:1400px;width:100%;margin:0 auto;padding:1.5rem 0.5rem 2rem;box-sizing:border-box;}

/* Banner abo */
.abo-b{border-radius:14px;padding:1.2rem 1.6rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
.abo-b.ok{background:linear-gradient(135deg,rgba(0,212,106,0.08),rgba(0,212,255,0.04));border:1px solid rgba(0,212,106,0.25);}
.abo-b.no{background:linear-gradient(135deg,rgba(255,45,120,0.06),rgba(255,107,43,0.04));border:1px solid var(--border,rgba(255,45,120,0.15));}
.abo-b h3{font-family:'Orbitron',sans-serif;font-size:0.95rem;margin-bottom:0.2rem;}
.abo-b p{color:var(--txt3,#8a9bb0);font-size:0.9rem;}
.btn-sub{background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.7rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.95rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;display:inline-flex;align-items:center;gap:0.4rem;}
.btn-sub:hover{box-shadow:0 0 30px rgba(255,45,120,0.35);transform:translateY(-2px);}

/* Onglets */
.tabs-bar{display:flex;gap:0;margin-bottom:2rem;background:var(--card,#111827);border-radius:14px;border:1px solid var(--border,rgba(255,45,120,0.15));overflow:hidden;position:relative;}
.tab-btn{flex:1;padding:1rem 1.2rem;text-align:center;cursor:pointer;font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;letter-spacing:1px;color:var(--txt3,#8a9bb0);background:transparent;border:none;transition:all .3s;position:relative;z-index:1;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
.tab-btn:hover{color:var(--txt,#f0f4f8);}
.tab-btn.active{color:#fff;}
.tab-btn .tab-count{font-family:'Space Mono',monospace;font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:50px;background:rgba(255,255,255,0.08);color:inherit;}
.tab-btn.active .tab-count{background:rgba(255,255,255,0.2);}
.tab-indicator{position:absolute;bottom:0;height:100%;background:rgba(255,45,120,0.15);border-radius:14px;transition:left .35s cubic-bezier(.4,0,.2,1), width .35s cubic-bezier(.4,0,.2,1);}
.tab-indicator::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 14px 14px;}
.tab-btn[data-tab="safe"].active ~ .tab-indicator::after,
[data-tab="safe"].active + .tab-indicator::after{background:#00d4ff;}

/* Cards grille */
.tab-panel{display:none;}
.tab-panel.active{display:block;animation:fadeUp .4s ease-out;}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
.bets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(380px,100%),1fr));gap:1.5rem;}

/* Card */
.bet-card{background:var(--card,#111827);border-radius:16px;overflow:hidden;transition:all .35s;position:relative;border:1px solid var(--border,rgba(255,45,120,0.15));opacity:0;animation:cardIn .5s ease-out forwards;}
.bet-card:nth-child(1){animation-delay:0s;}
.bet-card:nth-child(2){animation-delay:.06s;}
.bet-card:nth-child(3){animation-delay:.12s;}
.bet-card:nth-child(4){animation-delay:.18s;}
.bet-card:nth-child(5){animation-delay:.24s;}
.bet-card:nth-child(6){animation-delay:.3s;}
@keyframes cardIn{to{opacity:1;}}
.bet-card::before{content:'';position:absolute;top:0;left:0;bottom:0;width:4px;border-radius:16px 0 0 16px;}
.bet-card[data-type="safe"]::before{background:linear-gradient(to bottom,#00d4ff,#0099cc);}
.bet-card[data-type="live"]::before{background:linear-gradient(to bottom,#ff2d78,#d6245f);}
.bet-card[data-type="fun"]::before{background:linear-gradient(to bottom,#a855f7,#7c3aed);}
.bet-card:hover{transform:translateY(-6px);box-shadow:0 20px 60px rgba(0,0,0,0.5),0 0 25px rgba(255,45,120,0.08);border-color:rgba(255,255,255,0.12);}
.bet-top{padding:1rem 1.2rem 0.6rem 1.4rem;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;}
.bet-badges{display:flex;gap:0.4rem;flex-wrap:wrap;}
.bet-badge{padding:0.25rem 0.7rem;border-radius:6px;font-family:'Orbitron',sans-serif;font-size:0.65rem;font-weight:700;letter-spacing:1px;}
.bet-date{font-family:'Space Mono',monospace;font-size:0.7rem;color:var(--txt3,#8a9bb0);}
.bet-titre{font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:600;padding:0 1.2rem 0.8rem 1.4rem;color:var(--txt2,#b0bec9);line-height:1.3;}

/* Image */
.bet-img-wrap{position:relative;overflow:hidden;aspect-ratio:16/9;}
.bet-img{width:100%;height:100%;display:block;object-fit:cover;transition:transform .4s;}
.bet-img.blur{filter:blur(14px);transform:scale(1.08);}
.bet-img-wrap.zoomable{cursor:zoom-in;}
.bet-img-wrap.zoomable:hover .bet-img{transform:scale(1.03);}
.zoom-tip{position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);color:#fff;font-size:0.72rem;padding:0.3rem 0.65rem;border-radius:6px;pointer-events:none;opacity:0;transition:opacity .25s;}
.bet-img-wrap.zoomable:hover .zoom-tip{opacity:1;}
.bet-no-img{width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,rgba(255,45,120,0.04),rgba(0,212,255,0.02));display:flex;align-items:center;justify-content:center;font-size:3rem;color:var(--txt3,#8a9bb0);}

/* Lock overlay */
.lock-ov{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(5,8,16,0.8);backdrop-filter:blur(8px);text-align:center;gap:0.8rem;padding:2rem;}
.lock-ov .lock-i{font-size:2.5rem;filter:drop-shadow(0 0 12px rgba(255,45,120,0.3));}
.lock-ov .lock-t{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:700;}
.lock-ov .lock-s{color:var(--txt3,#8a9bb0);font-size:0.88rem;max-width:240px;line-height:1.4;}
.lock-ov .lock-b{background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.65rem 1.4rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;}
.lock-ov .lock-b:hover{box-shadow:0 0 25px rgba(255,45,120,0.4);transform:translateY(-1px);}

.no-bets{text-align:center;padding:5rem 2rem;color:var(--txt3,#8a9bb0);}
.no-bets .big{font-size:4rem;margin-bottom:1rem;}
.no-bets h3{font-family:'Orbitron',sans-serif;font-size:1.2rem;margin-bottom:0.5rem;color:var(--txt2,#b0bec9);}

.bet-link-analyse{display:block;padding:0.75rem 1.2rem;font-size:0.85rem;font-weight:700;color:var(--pink,#ff2d78);text-decoration:none;text-align:center;border-top:1px solid var(--border,rgba(255,45,120,0.15));transition:background .2s,color .2s;}
.bet-link-analyse:hover{background:rgba(255,45,120,0.06);color:#ff6b9d;}

/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(5,8,16,0.96);backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:2rem;}
.lightbox.open{display:flex;}
.lightbox-inner{position:relative;max-width:95vw;max-height:92vh;}
.lightbox-img{max-width:100%;max-height:88vh;border-radius:14px;box-shadow:0 0 80px rgba(255,45,120,0.15);display:block;}
.lightbox-close{position:fixed;top:1.2rem;right:1.5rem;background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.3);color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.3rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.lightbox-close:hover{background:#ff2d78;}
.lightbox-caption{text-align:center;margin-top:0.8rem;color:var(--txt3,#8a9bb0);font-size:0.88rem;}

/* Responsive */
@media(max-width:768px){
  .bets-grid{grid-template-columns:1fr;gap:1rem;}
  .tabs-bar{
    border-radius:10px;
    overflow-x:auto;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
    justify-content:flex-start;
  }
  .tab-btn{padding:0.85rem 0.8rem;font-size:0.72rem;gap:0.3rem;min-width:120px;flex:0 0 auto;}
  .tab-btn .tab-count{font-size:0.6rem;padding:0.1rem 0.35rem;}
  .tab-indicator{border-radius:10px;}
  .abo-b{padding:1rem;flex-direction:column;align-items:flex-start;border-radius:10px;}
  .abo-b h3{font-size:0.85rem;}
  .btn-sub{width:100%;justify-content:center;font-size:0.88rem;}
  .bets-hero{margin-left:-0.8rem !important;margin-right:-0.8rem !important;margin-top:-1rem;padding:1.5rem 0.8rem 1.5rem !important;}
  .bets-hero::before{display:none;}
  .bets-title{font-size:1.5rem;}
  .bets-sub{font-size:0.88rem;max-width:none;}
  .bets-wrap{padding:1rem 0;}
  .bet-card{border-radius:12px;}
  .bet-card::before{border-radius:12px 0 0 12px;}
  .bet-card:hover{transform:none;box-shadow:none;}
  .lightbox-img{border-radius:8px;max-height:80dvh;}
}
@media(max-width:480px){
  .bets-title{font-size:1.3rem;}
  .bets-tag{font-size:0.6rem;letter-spacing:2px;}
  .bets-counter{font-size:0.72rem;padding:0.4rem 0.9rem;}
  .tab-btn{font-size:0.65rem;padding:0.7rem 0.3rem;letter-spacing:0;}
  .bet-top{padding:0.7rem 0.8rem 0.4rem 1rem;}
  .bet-titre{padding:0 0.8rem 0.6rem 1rem;font-size:0.85rem;}
  .bet-badge{font-size:0.58rem;padding:0.2rem 0.5rem;}
  .lock-ov .lock-i{font-size:1.8rem;}
  .lock-ov .lock-t{font-size:0.85rem;}
  .lock-ov .lock-b{font-size:0.8rem;padding:0.5rem 1rem;min-height:44px;display:inline-flex;align-items:center;}
}
</style>
</head>
<body>
<?php if ($membre): ?>
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php else: ?>
  <nav><div class="nav-inner">
    <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge"></a>
    <div class="nav-right">
      <a href="/#pricing">Tarifs</a>
      <a href="login.php">Connexion</a>
      <a href="register.php" style="background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.45rem 1.1rem;border-radius:8px;font-weight:700;">S'inscrire</a>
    </div>
    <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
  </div></nav>
  <div class="mobile-menu" id="mobileMenu"><a href="/#pricing">Tarifs</a><a href="login.php">Connexion</a><a href="register.php">S'inscrire</a></div>
<?php endif; ?>

<div class="bets-hero">
  <div class="bets-tag">Pronos en cours</div>
  <h1 class="bets-title">Les <span>Bets</span></h1>
  <p class="bets-sub">Nos analyses et cards de bets. Abonnement requis pour le contenu complet.</p>
  <?php if ($nbTotal > 0): ?>
  <div class="bets-counter"><span class="pulse"></span> <?= $nbTotal ?> bet<?= $nbTotal > 1 ? 's' : '' ?> actif<?= $nbTotal > 1 ? 's' : '' ?></div>
  <?php endif; ?>
</div>

<div class="bets-wrap">
  <?php if ($hasAcces): ?>
  <div class="abo-b ok"><div><h3>Acces complet debloque</h3>
    <p><?php if (isAdmin() && $membre && $abonnement === null): ?><span style="color:#00d4ff;font-weight:700;">Acces admin</span>
    <?php elseif (!empty($abonnement['type']) && $abonnement['type']==='rasstoss'): ?><span style="color:#ffd700;font-weight:700;">Rass-Toss — Life Time</span>
    <?php elseif (!empty($abonnement['type']) && $abonnement['type']==='daily'): ?>Daily — expire au prochain bet
    <?php elseif (!empty($abonnement['type']) && $abonnement['type']==='weekend'): ?>Week-End — expire le <?= date('d/m/Y a H:i',strtotime($abonnement['date_fin'])) ?>
    <?php elseif (!empty($abonnement['type'])): ?>Weekly 7j — expire le <?= date('d/m/Y a H:i',strtotime($abonnement['date_fin'])) ?>
    <?php else: ?><span style="color:#00d4ff;font-weight:700;">Acces admin</span><?php endif; ?></p>
  </div><span style="font-size:1.4rem;">🔓</span></div>
  <?php else: ?>
  <div class="abo-b no"><div><h3>Contenu verrouille</h3><p>Souscris pour acceder aux analyses completes des bets.</p></div>
    <?php if (!isLoggedIn()): ?><a href="login.php" class="btn-sub">Se connecter →</a>
    <?php else: ?><a href="/#pricing" class="btn-sub">Voir les formules →</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($bets)): ?>
  <div class="no-bets"><div class="big">🎯</div><h3>Aucun bet disponible</h3><p>Les nouvelles analyses arrivent bientot, reste connecte !</p></div>
  <?php else: ?>

  <!-- Onglets Safe | Live | Fun -->
  <div class="tabs-bar" id="tabsBar">
    <?php $tabIdx = 0; foreach ($availableSections as $key => $sec): ?>
    <button class="tab-btn <?= $key === $firstTab ? 'active' : '' ?>" data-tab="<?= $key ?>" data-color="<?= $sec['color'] ?>" onclick="switchTab('<?= $key ?>')">
      <?= $sec['icon'] ?> <?= $sec['title'] ?>
      <span class="tab-count"><?= $sec['count'] ?></span>
    </button>
    <?php $tabIdx++; endforeach; ?>
    <div class="tab-indicator" id="tabIndicator"></div>
  </div>

  <!-- Panels -->
  <?php foreach ($availableSections as $key => $sec): ?>
  <div class="tab-panel <?= $key === $firstTab ? 'active' : '' ?>" id="panel-<?= $key ?>">
    <div class="bets-grid">
    <?php foreach ($sec['bets'] as $bet):
      $types = explode(',', $bet['type']);
      $mainType = trim($types[0]);
      $rawPath = !empty($bet['image_path']) ? $bet['image_path'] : ($bet['locked_image_path'] ?? '');
      $hasAcces = $hasAccesGlobal || stratedge_bet_acces($bet, $membre);
      if (!empty($rawPath)) {
        $imgSrc = (strpos($rawPath, 'http') === 0) ? $rawPath : (defined('SITE_URL') ? rtrim(SITE_URL,'/').'/'.ltrim($rawPath,'/') : $rawPath);
      } else {
        $imgSrc = '';
      }
    ?>
    <div class="bet-card" data-type="<?= $mainType ?>">
      <div class="bet-top">
        <div class="bet-badges">
          <?php foreach ($types as $t): $t=trim($t); $c=$typeColors[$t]??'#ff2d78'; ?>
          <span class="bet-badge" style="background:<?=$c?>18;color:<?=$c?>;border:1px solid <?=$c?>40;"><?= $typeLabels[$t]??$t ?></span>
          <?php endforeach; ?>
        </div>
        <span class="bet-date"><?= date('d/m/Y',strtotime($bet['date_post'])) ?></span>
      </div>
      <?php if ($bet['titre']): ?><div class="bet-titre"><?= clean($bet['titre']) ?></div><?php endif; ?>
      <div class="bet-img-wrap <?= ($hasAcces && $imgSrc)?'zoomable':'' ?>"
           <?= ($hasAcces && $imgSrc)?'data-src="'.clean($imgSrc).'" data-caption="'.htmlspecialchars($bet['titre']?:'Bet StratEdge',ENT_QUOTES).'"':'' ?>>
        <?php if ($imgSrc): ?>
        <img src="<?= clean($imgSrc) ?>" alt="Bet" class="bet-img <?= !$hasAcces?'blur':'' ?>" loading="lazy" onerror="this.onerror=null;this.style.display='none';var w=this.closest('.bet-img-wrap');var p=w&&w.querySelector('.bet-no-img');if(p)p.style.display='flex';">
        <div class="bet-no-img <?= !$hasAcces?'blur':'' ?>" style="display:none" aria-hidden="true">📊</div>
        <?php if ($hasAcces): ?><div class="zoom-tip">Cliquer pour agrandir</div><?php endif; ?>
        <?php else: ?><div class="bet-no-img <?= !$hasAcces?'blur':'' ?>">📊</div><?php endif; ?>
        <?php if (!$hasAcces): ?>
        <div class="lock-ov"><div class="lock-i">🔒</div><div class="lock-t">Contenu verrouille</div><div class="lock-s">Souscris pour acceder a l'analyse complete.</div>
          <a href="<?= isLoggedIn()?'/#pricing':'login.php' ?>" class="lock-b"><?= isLoggedIn()?'Voir les formules':'Se connecter' ?> →</a>
        </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($bet['analyse_html'])): ?>
      <a href="/bet.php?id=<?= (int)$bet['id'] ?>" class="bet-link-analyse">Voir l'analyse et commenter →</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php if ($membre): ?></main></div><?php endif; ?>

<div class="lightbox" id="lightbox">
  <button class="lightbox-close" onclick="closeLB()">✕</button>
  <div class="lightbox-inner" onclick="event.stopPropagation()">
    <img src="" alt="" class="lightbox-img" id="lbImg">
    <div class="lightbox-caption" id="lbCap"></div>
  </div>
</div>

<script>
// Tabs
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
  document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});
  var btn = document.querySelector('.tab-btn[data-tab="'+tab+'"]');
  var panel = document.getElementById('panel-'+tab);
  if(btn) btn.classList.add('active');
  if(panel) panel.classList.add('active');
  moveIndicator();
}
function moveIndicator() {
  var bar = document.getElementById('tabsBar');
  var active = bar && bar.querySelector('.tab-btn.active');
  var ind = document.getElementById('tabIndicator');
  if(!active || !ind || !bar) return;
  var color = active.dataset.color || '#ff2d78';
  ind.style.left = active.offsetLeft + 'px';
  ind.style.width = active.offsetWidth + 'px';
  ind.style.background = color + '18';
  ind.querySelector('::after') || (ind.style.borderBottom = '3px solid ' + color);
  ind.style.boxShadow = '0 0 20px ' + color + '15';
}
document.addEventListener('DOMContentLoaded', moveIndicator);
window.addEventListener('resize', moveIndicator);

// Lightbox
document.querySelectorAll('.bet-img-wrap.zoomable').forEach(function(el){
  el.addEventListener('click',function(){var s=el.dataset.src;if(!s)return;
    document.getElementById('lbImg').src=s;document.getElementById('lbCap').textContent=el.dataset.caption||'';
    document.getElementById('lightbox').classList.add('open');document.body.style.overflow='hidden';});
});
function closeLB(){document.getElementById('lightbox').classList.remove('open');document.getElementById('lbImg').src='';document.body.style.overflow='';}
document.getElementById('lightbox').addEventListener('click',function(e){if(e.target===this)closeLB();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeLB();});
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
