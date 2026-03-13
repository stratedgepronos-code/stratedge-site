<?php
require_once __DIR__ . '/includes/auth.php';

$db = getDB();
$membre = isLoggedIn() ? getMembre() : null;
$currentPage = 'historique';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;

$bets = $db->query("
    SELECT * FROM bets 
    WHERE resultat != 'en_cours' 
    ORDER BY date_resultat DESC, date_post DESC
")->fetchAll();

// 3 grosses catégories : Multisports | Tennis | Fun — mêmes clés que bets.php
$sportNorm = function($b) {
    $s = strtolower(trim($b['sport'] ?? ''));
    if ($s === '' && (($b['categorie'] ?? '') === 'tennis')) return 'tennis';
    if (in_array($s, ['football','foot'], true)) return 'football';
    if (in_array($s, ['basket','nba'], true)) return 'basket';
    if (in_array($s, ['hockey','nhl'], true)) return 'hockey';
    return in_array($s, ['football','basket','hockey'], true) ? $s : 'football';
};
$typeNorm = function($b) {
    $t = strtolower($b['type'] ?? '');
    if (strpos($t, 'fun') !== false) return 'fun';
    if (strpos($t, 'live') !== false) return 'live';
    if (strpos($t, 'combi') !== false) return 'combi';
    return 'safe';
};
$mainCategoriesHisto = [
    'multisports' => ['title' => 'Multisports', 'icon' => '⚽🏀🏒', 'bets' => [], 'subs' => []],
    'tennis'      => ['title' => 'Tennis',      'icon' => '🎾', 'bets' => [], 'subs' => []],
    'fun'         => ['title' => 'Fun',         'icon' => '🎯', 'bets' => [], 'subs' => []],
];
$sportLabels = ['football' => 'Foot', 'basket' => 'NBA', 'hockey' => 'NHL'];
$sectionLabels = [
    'tennis_safe' => '🎾 Tennis Safe', 'tennis_combi' => '🎾 Tennis Combi', 'tennis_fun' => '🎾 Tennis Fun', 'tennis_live' => '🎾 Tennis Live',
    'football_safe' => '⚽ Foot Safe', 'football_combi' => '⚽ Foot Combi', 'football_live' => '⚽ Foot Live', 'football_fun' => '⚽ Foot Fun',
    'basket_safe' => '🏀 NBA Safe', 'basket_combi' => '🏀 NBA Combi', 'basket_live' => '🏀 NBA Live', 'basket_fun' => '🏀 NBA Fun',
    'hockey_safe' => '🏒 NHL Safe', 'hockey_combi' => '🏒 NHL Combi', 'hockey_live' => '🏒 NHL Live', 'hockey_fun' => '🏒 NHL Fun',
    'football' => '⚽ Fun Foot', 'basket' => '🏀 Fun NBA', 'hockey' => '🏒 Fun NHL',
];
foreach ($bets as $b) {
    $cat = $b['categorie'] ?? 'multi';
    $isFun = (strpos(strtolower($b['type'] ?? ''), 'fun') !== false);
    $sport = $sportNorm($b);
    $type = $typeNorm($b);
    if ($cat === 'tennis') {
        $mainCategoriesHisto['tennis']['bets'][] = $b;
        $subKey = 'tennis_' . $type;
        if (!isset($mainCategoriesHisto['tennis']['subs'][$subKey])) $mainCategoriesHisto['tennis']['subs'][$subKey] = [];
        $mainCategoriesHisto['tennis']['subs'][$subKey][] = $b;
    } elseif ($isFun && $cat === 'multi') {
        $mainCategoriesHisto['fun']['bets'][] = $b;
        $subKey = $sport;
        if (!in_array($subKey, ['football','basket','hockey'])) $subKey = 'football';
        if (!isset($mainCategoriesHisto['fun']['subs'][$subKey])) $mainCategoriesHisto['fun']['subs'][$subKey] = [];
        $mainCategoriesHisto['fun']['subs'][$subKey][] = $b;
    } else {
        $mainCategoriesHisto['multisports']['bets'][] = $b;
        $subKey = $sport . '_' . $type;
        if (!isset($mainCategoriesHisto['multisports']['subs'][$subKey])) $mainCategoriesHisto['multisports']['subs'][$subKey] = [];
        $mainCategoriesHisto['multisports']['subs'][$subKey][] = $b;
    }
}
// Flatten for filter pills: section key => bets (compat ancien filtre)
$sectionsBets = [];
foreach ($mainCategoriesHisto as $mCat) {
    foreach ($mCat['subs'] as $subKey => $arr) {
        $sectionsBets[$subKey] = $arr;
    }
}
function sectionStats($arr) {
    $g = count(array_filter($arr, fn($b) => $b['resultat'] === 'gagne'));
    $p = count(array_filter($arr, fn($b) => $b['resultat'] === 'perdu'));
    $a = count(array_filter($arr, fn($b) => $b['resultat'] === 'annule'));
    $total = count($arr);
    $taux = ($g + $p) > 0 ? round($g / ($g + $p) * 100) : null;
    return ['gagnes' => $g, 'perdus' => $p, 'annules' => $a, 'total' => $total, 'taux' => $taux];
}
$sectionStats = [];
foreach ($sectionsBets as $key => $arr) {
    $sectionStats[$key] = sectionStats($arr);
}

$stats = $db->query("
    SELECT 
        SUM(resultat='gagne')  as gagnes,
        SUM(resultat='perdu')  as perdus,
        SUM(resultat='annule') as annules,
        COUNT(*) as total
    FROM bets WHERE resultat != 'en_cours'
")->fetch();

// Cote moyenne (tous les bets avec une cote renseignée)
$coteMoyenneRow = $db->query("SELECT AVG(cote) as cote_moy, COUNT(*) as nb FROM bets WHERE cote IS NOT NULL AND cote > 0")->fetch();
$coteMoyenne = ($coteMoyenneRow && $coteMoyenneRow['nb'] > 0) ? round((float)$coteMoyenneRow['cote_moy'], 2) : null;

$typeLabels = ['safe'=>'🛡️ Safe','fun'=>'🎯 Fun','live'=>'⚡ Live','safe,fun'=>'Safe+Fun','safe,live'=>'Safe+Live'];
$typeColors = ['safe'=>'#00d4ff','fun'=>'#a855f7','live'=>'#ff2d78'];

$resultatConfig = [
    'gagne'  => ['label'=>'Gagne',  'color'=>'#00c864', 'bg'=>'rgba(0,200,100,0.12)',  'border'=>'rgba(0,200,100,0.35)',  'icon'=>'✅', 'overlay'=>'rgba(0,200,100,0.15)', 'band'=>'linear-gradient(to bottom,#00c864,#00a050)'],
    'perdu'  => ['label'=>'Perdu',   'color'=>'#ff4444', 'bg'=>'rgba(255,68,68,0.12)',   'border'=>'rgba(255,68,68,0.35)',   'icon'=>'❌', 'overlay'=>'rgba(255,68,68,0.15)', 'band'=>'linear-gradient(to bottom,#ff4444,#cc2222)'],
    'annule' => ['label'=>'Annule', 'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,0.12)', 'border'=>'rgba(245,158,11,0.35)', 'icon'=>'↺',  'overlay'=>'rgba(245,158,11,0.1)', 'band'=>'linear-gradient(to bottom,#f59e0b,#d97706)'],
];

$filtreSection = $_GET['section'] ?? 'tous';
$filtre = $_GET['filtre'] ?? 'tous';

if ($filtreSection !== 'tous' && isset($sectionsBets[$filtreSection])) {
    $betsFiltres = $sectionsBets[$filtreSection];
    $statsAffichage = $sectionStats[$filtreSection];
    $tauxReussite = $statsAffichage['taux'];
} else {
    $betsFiltres = $bets;
    $statsAffichage = $stats;
    $tauxReussite = ($stats['total'] > 0 && ($stats['gagnes'] + $stats['perdus']) > 0)
        ? round($stats['gagnes'] / ($stats['gagnes'] + $stats['perdus']) * 100)
        : null;
}
if ($filtre !== 'tous') {
    $betsFiltres = array_filter($betsFiltres, fn($b) => $b['resultat'] === $filtre);
}
$betsFiltres = array_values($betsFiltres);
$betsPerPage = 18;
$totalBets = count($betsFiltres);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Historique des Bets – StratEdge Pronos</title>
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
/* ═══ HISTORIQUE V2 ═══ */

/* Hero */
.hist-hero{position:relative;text-align:center;overflow:hidden;background:linear-gradient(180deg,rgba(0,212,255,0.05) 0%,transparent 100%);border-bottom:1px solid var(--border,rgba(255,45,120,0.15));margin:-2.5rem -3rem 0 -3rem;padding:1.7rem 3rem;}
.hist-hero::before{content:'';position:absolute;width:600px;height:400px;background:radial-gradient(circle,rgba(0,212,255,0.08) 0%,transparent 70%);top:-200px;left:50%;transform:translateX(-50%);pointer-events:none;}
body:not(.app-body) .hist-hero{margin-left:-2rem;margin-right:-2rem;margin-top:0;padding:2rem;}
.hist-tag{font-family:'Space Mono',monospace;font-size:0.75rem;letter-spacing:4px;text-transform:uppercase;color:var(--blue,#00d4ff);margin-bottom:0.7rem;}
.hist-title{font-family:'Orbitron',sans-serif;font-size:2.2rem;font-weight:900;margin-bottom:0.5rem;}
.hist-title span{background:linear-gradient(135deg,#00d4ff,#0099cc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hist-sub{color:var(--txt2,#b0bec9);font-size:1rem;max-width:500px;margin:0 auto;}

.hist-wrap{max-width:1400px;width:100%;margin:1.25rem auto 0;padding:0 0.5rem 2rem;box-sizing:border-box;}

/* ═══ Dashboard Stats ═══ */
.stats-dashboard{display:flex;align-items:center;justify-content:center;gap:2.5rem;margin-bottom:2rem;flex-wrap:wrap;}

/* Cercle radial */
.radial-wrap{position:relative;width:160px;height:160px;flex-shrink:0;}
.radial-circle{width:100%;height:100%;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-direction:column;}
.radial-value{font-family:'Orbitron',sans-serif;font-size:2.2rem;font-weight:900;color:var(--txt,#f0f4f8);line-height:1;}
.radial-label{font-size:0.7rem;color:var(--txt3,#8a9bb0);text-transform:uppercase;letter-spacing:1px;margin-top:0.3rem;}

/* Mini stats */
.mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;}
.mini-stat{background:var(--card,#111827);border-radius:12px;padding:1rem 1.4rem;display:flex;align-items:center;gap:0.8rem;border:1px solid var(--border,rgba(255,45,120,0.15));min-width:150px;}
.mini-stat-icon{font-size:1.6rem;flex-shrink:0;}
.mini-stat-val{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:900;line-height:1.1;}
.mini-stat-lbl{font-size:0.72rem;color:var(--txt3,#8a9bb0);text-transform:uppercase;letter-spacing:0.5px;}

/* Winrate bar */
.winrate-bar-wrap{margin-bottom:2rem;}
.winrate-bar-header{display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.8rem;color:var(--txt3,#8a9bb0);}
.winrate-bar{height:6px;border-radius:4px;background:rgba(255,255,255,0.06);overflow:hidden;}
.winrate-fill{height:100%;border-radius:4px;transition:width .8s ease-out;}

/* ═══ Filtres pills ═══ */
.filters-section{margin-bottom:1.5rem;}
.filters-label{font-family:'Orbitron',sans-serif;font-size:0.68rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--txt3,#8a9bb0);margin-bottom:0.5rem;}
.filters{display:flex;gap:0.5rem;flex-wrap:wrap;}
.filters+.filters-label{margin-top:1rem;}
.filter-pill{padding:0.5rem 1.1rem;border-radius:50px;font-family:'Rajdhani',sans-serif;font-size:0.88rem;font-weight:600;border:1px solid rgba(255,255,255,0.1);color:var(--txt3,#8a9bb0);cursor:pointer;transition:all .25s;text-decoration:none;background:transparent;display:inline-flex;align-items:center;gap:0.4rem;}
.filter-pill:hover{border-color:rgba(255,255,255,0.25);color:var(--txt,#f0f4f8);}
.filter-pill.active{color:#fff;border-color:transparent;box-shadow:0 0 20px rgba(255,45,120,0.15);}
.filter-pill.active.f-tous{background:linear-gradient(135deg,rgba(255,45,120,0.2),rgba(168,85,247,0.15));border-color:rgba(255,45,120,0.3);}
.filter-pill.active.f-gagne{background:rgba(0,200,100,0.15);border-color:rgba(0,200,100,0.4);color:#00c864;}
.filter-pill.active.f-perdu{background:rgba(255,68,68,0.15);border-color:rgba(255,68,68,0.4);color:#ff4444;}
.filter-pill.active.f-annule{background:rgba(245,158,11,0.15);border-color:rgba(245,158,11,0.4);color:#f59e0b;}
.filter-pill.active.f-safe{background:rgba(0,212,255,0.15);border-color:rgba(0,212,255,0.4);color:#00d4ff;}
.filter-pill.active.f-live{background:rgba(255,45,120,0.15);border-color:rgba(255,45,120,0.4);color:#ff2d78;}
.filter-pill.active.f-fun{background:rgba(168,85,247,0.15);border-color:rgba(168,85,247,0.4);color:#a855f7;}
.filter-count{font-family:'Space Mono',monospace;font-size:0.68rem;padding:0.1rem 0.4rem;border-radius:50px;background:rgba(255,255,255,0.08);}
.filter-pill.active .filter-count{background:rgba(255,255,255,0.15);}

/* ═══ Cards historique — grille 3 colonnes ═══ */
.hist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(340px,100%),1fr));gap:1.2rem;}
.hist-card{display:flex;flex-direction:column;background:var(--card,#111827);border-radius:14px;overflow:hidden;transition:all .35s;border:1px solid var(--border,rgba(255,45,120,0.15));opacity:0;animation:cardSlide .4s ease-out forwards;position:relative;}
.hist-card:nth-child(1){animation-delay:0s;}
.hist-card:nth-child(2){animation-delay:.04s;}
.hist-card:nth-child(3){animation-delay:.08s;}
.hist-card:nth-child(4){animation-delay:.12s;}
.hist-card:nth-child(5){animation-delay:.16s;}
.hist-card:nth-child(6){animation-delay:.2s;}
@keyframes cardSlide{to{opacity:1;}}
.hist-card:hover{transform:translateY(-5px);box-shadow:0 15px 50px rgba(0,0,0,0.45);border-color:rgba(255,255,255,0.12);}

/* Bandeau haut resultat */
.hist-band{width:100%;height:4px;flex-shrink:0;}

/* Image */
.hist-img-wrap{width:100%;position:relative;overflow:hidden;cursor:zoom-in;aspect-ratio:16/9;}
.hist-img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s;}
.hist-img-wrap:hover .hist-img{transform:scale(1.05);}
.hist-no-img{width:100%;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;font-size:2.5rem;background:linear-gradient(135deg,rgba(255,45,120,0.04),rgba(0,212,255,0.02));}

/* Infos */
.hist-info{flex:1;padding:0.9rem 1.1rem;display:flex;flex-direction:column;gap:0.4rem;min-width:0;}
.hist-info-top{display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;}
.hist-badges{display:flex;gap:0.35rem;flex-wrap:wrap;}
.hist-badge{padding:0.2rem 0.6rem;border-radius:5px;font-family:'Orbitron',sans-serif;font-size:0.6rem;font-weight:700;letter-spacing:0.5px;}
.hist-date{font-family:'Space Mono',monospace;font-size:0.68rem;color:var(--txt3,#8a9bb0);}
.hist-titre{font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:600;color:var(--txt2,#b0bec9);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.hist-info-bottom{display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;margin-top:auto;padding-top:0.3rem;}
.result-badge{padding:0.3rem 0.85rem;border-radius:6px;font-size:0.78rem;font-weight:700;font-family:'Orbitron',sans-serif;letter-spacing:0.5px;display:inline-flex;align-items:center;gap:0.3rem;}
.result-date{font-size:0.72rem;color:var(--txt3,#8a9bb0);}

/* Voir plus */
.load-more-wrap{text-align:center;margin-top:2rem;}
.btn-load-more{background:var(--card,#111827);border:1px solid var(--border,rgba(255,45,120,0.15));color:var(--txt2,#b0bec9);padding:0.8rem 2rem;border-radius:50px;font-family:'Orbitron',sans-serif;font-size:0.82rem;font-weight:700;letter-spacing:1px;cursor:pointer;transition:all .3s;}
.btn-load-more:hover{border-color:var(--pink,#ff2d78);color:var(--txt,#f0f4f8);box-shadow:0 0 25px rgba(255,45,120,0.15);}

.empty-state{text-align:center;padding:4rem 2rem;color:var(--txt3,#8a9bb0);}
.empty-state .big{font-size:3.5rem;margin-bottom:1rem;}
.empty-state h3{font-family:'Orbitron',sans-serif;font-size:1.15rem;color:var(--txt2,#b0bec9);margin-bottom:0.5rem;}

/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(5,8,16,0.96);backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:2rem;}
.lightbox.open{display:flex;}
.lightbox-inner{position:relative;max-width:95vw;max-height:92vh;}
.lightbox-img{max-width:100%;max-height:88vh;border-radius:14px;box-shadow:0 0 80px rgba(255,45,120,0.15);display:block;}
.lightbox-close{position:fixed;top:1.2rem;right:1.5rem;background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.3);color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.3rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.lightbox-close:hover{background:#ff2d78;}
.lightbox-caption{text-align:center;margin-top:0.8rem;color:var(--txt3,#8a9bb0);font-size:0.88rem;}

/* ═══ Responsive ═══ */
@media(max-width:900px){
  .stats-dashboard{gap:1.5rem;}
  .mini-stat{min-width:130px;padding:0.8rem 1rem;}
}
@media(max-width:768px){
  .hist-hero{margin:-1rem -0.8rem 0 -0.8rem !important;padding:1.5rem 0.8rem !important;}
  .hist-hero::before{display:none;}
  .hist-title{font-size:1.5rem;}
  .hist-sub{font-size:0.88rem;max-width:none;}
  .hist-wrap{padding:1rem 0;}
  .stats-dashboard{flex-direction:column;gap:1.2rem;}
  .radial-wrap{width:130px;height:130px;}
  .radial-value{font-size:1.7rem;}
  .mini-stats{grid-template-columns:1fr 1fr;gap:0.6rem;width:100%;}
  .mini-stat{min-width:0;padding:0.7rem 0.8rem;}
  .mini-stat-val{font-size:1rem;}
  .mini-stat-icon{font-size:1.2rem;}
  .hist-grid{grid-template-columns:1fr;gap:0.8rem;}
  .hist-card:hover{transform:none;box-shadow:none;}
  .hist-info{padding:0.8rem 1rem;}
  .hist-titre{white-space:normal;}
  .filters{
    gap:0.4rem;
    flex-wrap:nowrap;
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
    padding-bottom:0.2rem;
    scrollbar-width:thin;
  }
  .filter-pill{padding:0.45rem 0.85rem;font-size:0.78rem;white-space:nowrap;flex:0 0 auto;}
  .lightbox-img{border-radius:8px;max-height:80dvh;}
}
@media(max-width:480px){
  .hist-title{font-size:1.25rem;}
  .hist-tag{font-size:0.6rem;letter-spacing:2px;}
  .radial-wrap{width:110px;height:110px;}
  .radial-value{font-size:1.4rem;}
  .mini-stat-val{font-size:0.9rem;}
  .filter-pill{font-size:0.72rem;padding:0.35rem 0.65rem;}
  .hist-badge{font-size:0.52rem;}
  .result-badge{font-size:0.68rem;padding:0.2rem 0.6rem;}
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
      <a href="bets.php">Les Bets</a>
      <a href="historique.php" style="color:var(--blue,#00d4ff);">Historique</a>
      <?php if (isLoggedIn()): ?>
        <a href="dashboard.php">Mon Espace</a>
      <?php else: ?>
        <a href="login.php">Connexion</a>
        <a href="register.php" style="background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.45rem 1.1rem;border-radius:8px;font-weight:700;">S'inscrire</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
  </div></nav>
  <div class="mobile-menu" id="mobileMenu">
    <a href="/#pricing">Tarifs</a>
    <a href="bets.php">Les Bets</a>
    <a href="historique.php">Historique</a>
    <?php if (isLoggedIn()): ?>
      <a href="dashboard.php">Mon Espace</a>
      <a href="logout.php">Deconnexion</a>
    <?php else: ?>
      <a href="login.php">Connexion</a>
      <a href="register.php">S'inscrire</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="hist-hero">
  <div class="hist-tag">Transparence totale</div>
  <h1 class="hist-title">Historique des <span>Bets</span></h1>
  <p class="hist-sub">Tous nos resultats passes, en toute transparence. Aucun filtre, aucune triche.</p>
</div>

<div class="hist-wrap">

  <!-- Dashboard Stats (global ou section) -->
  <div class="stats-dashboard">
    <div class="radial-wrap">
      <?php
        $pct = $tauxReussite ?? 0;
        $greenDeg = round($pct * 3.6);
      ?>
      <div class="radial-circle" style="background:conic-gradient(#00c864 0deg, #00c864 <?= $greenDeg ?>deg, rgba(255,68,68,0.3) <?= $greenDeg ?>deg, rgba(255,68,68,0.3) 360deg);padding:5px;border-radius:50%;">
        <div style="width:100%;height:100%;border-radius:50%;background:var(--card,#111827);display:flex;align-items:center;justify-content:center;flex-direction:column;">
          <div class="radial-value"><?= $tauxReussite !== null ? $tauxReussite . '%' : '—' ?></div>
          <div class="radial-label">Winrate<?= $filtreSection !== 'tous' ? ' section' : '' ?></div>
        </div>
      </div>
    </div>
    <div class="mini-stats">
      <div class="mini-stat">
        <span class="mini-stat-icon">📊</span>
        <div>
          <div class="mini-stat-val" style="color:var(--txt,#f0f4f8)"><?= $statsAffichage['total'] ?? 0 ?></div>
          <div class="mini-stat-lbl">Total</div>
        </div>
      </div>
      <div class="mini-stat" style="border-color:rgba(0,200,100,0.2);">
        <span class="mini-stat-icon">✅</span>
        <div>
          <div class="mini-stat-val" style="color:#00c864"><?= $statsAffichage['gagnes'] ?? 0 ?></div>
          <div class="mini-stat-lbl">Gagnes</div>
        </div>
      </div>
      <div class="mini-stat" style="border-color:rgba(255,68,68,0.2);">
        <span class="mini-stat-icon">❌</span>
        <div>
          <div class="mini-stat-val" style="color:#ff4444"><?= $statsAffichage['perdus'] ?? 0 ?></div>
          <div class="mini-stat-lbl">Perdus</div>
        </div>
      </div>
      <div class="mini-stat" style="border-color:rgba(245,158,11,0.2);">
        <span class="mini-stat-icon">↺</span>
        <div>
          <div class="mini-stat-val" style="color:#f59e0b"><?= $statsAffichage['annules'] ?? 0 ?></div>
          <div class="mini-stat-lbl">Annules</div>
        </div>
      </div>
      <?php if ($coteMoyenne !== null): ?>
      <div class="mini-stat" style="border-color:rgba(0,212,255,0.2);">
        <span class="mini-stat-icon">📊</span>
        <div>
          <div class="mini-stat-val" style="color:#00d4ff"><?= number_format($coteMoyenne, 2, ',', ' ') ?></div>
          <div class="mini-stat-lbl">Cote moyenne</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Winrate bar -->
  <?php if ($tauxReussite !== null): ?>
  <div class="winrate-bar-wrap">
    <div class="winrate-bar-header">
      <span>Taux de reussite</span>
      <span style="font-weight:700;color:<?= $tauxReussite >= 50 ? '#00c864' : '#ff4444' ?>"><?= $tauxReussite ?>%</span>
    </div>
    <div class="winrate-bar">
      <div class="winrate-fill" style="width:<?= $tauxReussite ?>%;background:linear-gradient(90deg,#00c864,<?= $tauxReussite >= 70 ? '#00d4ff' : ($tauxReussite >= 50 ? '#f59e0b' : '#ff4444') ?>);"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filtre par section (taux de reussite par section) -->
  <div class="filters-section">
    <div class="filters-label">Section (taux de reussite)</div>
    <div class="filters">
      <?php $baseQuery = ($filtre !== 'tous' ? '&filtre='.$filtre : ''); ?>
      <a href="?section=tous<?= $baseQuery ?>" class="filter-pill f-tous <?= $filtreSection==='tous'?'active':'' ?>">Tous <span class="filter-count"><?= $stats['total'] ?? 0 ?></span></a>
      <?php
        $orderSections = ['football_safe','football_combi','football_live','football_fun','basket_safe','basket_combi','basket_live','basket_fun','hockey_safe','hockey_combi','hockey_live','hockey_fun','tennis_safe','tennis_combi','tennis_fun','tennis_live','football','basket','hockey'];
        foreach ($orderSections as $sk):
          if (!isset($sectionStats[$sk])) continue;
          $st = $sectionStats[$sk];
          $tauxStr = $st['taux'] !== null ? $st['taux'].'%' : '—';
      ?>
      <a href="?section=<?= urlencode($sk) ?><?= $baseQuery ?>" class="filter-pill <?= $filtreSection===$sk?'active':'' ?>"><?= $sectionLabels[$sk] ?? $sk ?> <span class="filter-count"><?= $st['total'] ?> · <?= $tauxStr ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="filters-label" style="margin-top:0.8rem;">Resultat</div>
    <div class="filters">
      <a href="?section=<?= urlencode($filtreSection) ?>&filtre=tous" class="filter-pill f-tous <?= $filtre==='tous'?'active':'' ?>">Tous</a>
      <a href="?section=<?= urlencode($filtreSection) ?>&filtre=gagne" class="filter-pill f-gagne <?= $filtre==='gagne'?'active':'' ?>">✅ Gagnes</a>
      <a href="?section=<?= urlencode($filtreSection) ?>&filtre=perdu" class="filter-pill f-perdu <?= $filtre==='perdu'?'active':'' ?>">❌ Perdus</a>
      <a href="?section=<?= urlencode($filtreSection) ?>&filtre=annule" class="filter-pill f-annule <?= $filtre==='annule'?'active':'' ?>">↺ Annules</a>
    </div>
  </div>

  <!-- Cards historique -->
  <?php if (empty($betsFiltres)): ?>
    <div class="empty-state">
      <div class="big">📭</div>
      <h3>Aucun resultat pour ce filtre</h3>
      <p>Les bets termines apparaitront ici automatiquement.</p>
    </div>
  <?php else: ?>
    <div class="hist-grid" id="histGrid">
      <?php foreach ($betsFiltres as $idx => $bet):
        $rc  = $resultatConfig[$bet['resultat']] ?? $resultatConfig['annule'];
        $types = explode(',', $bet['type']);
        $rawPath = !empty($bet['image_path']) ? $bet['image_path'] : ($bet['locked_image_path'] ?? '');
        if (!empty($rawPath)) {
          $subdir = (strpos($rawPath, 'locked') !== false) ? 'locked' : 'bets';
          $imgSrc = function_exists('betImageUrl') ? betImageUrl(trim($rawPath), $subdir) : (defined('SITE_URL') ? rtrim(SITE_URL,'/').'/'.ltrim($rawPath,'/') : $rawPath);
        } else {
          $imgSrc = '';
        }
        $hidden = $idx >= $betsPerPage ? 'style="display:none" data-hidden="1"' : '';
      ?>
      <div class="hist-card" <?= $hidden ?>>
        <!-- Bandeau lateral -->
        <div class="hist-band" style="background:<?= $rc['band'] ?>;"></div>

        <!-- Image -->
        <div class="hist-img-wrap" <?= $imgSrc ? 'data-src="'.$imgSrc.'" data-caption="'.htmlspecialchars($bet['titre']?:'Bet StratEdge',ENT_QUOTES).'"' : '' ?>>
          <?php if ($imgSrc): ?>
            <img src="<?= clean($imgSrc) ?>" alt="Bet" class="hist-img" loading="lazy">
          <?php else: ?>
            <div class="hist-no-img"><?= $rc['icon'] ?></div>
          <?php endif; ?>
        </div>

        <!-- Infos -->
        <div class="hist-info">
          <div class="hist-info-top">
            <div class="hist-badges">
              <?php foreach ($types as $t): $t=trim($t); $c=$typeColors[$t]??'#ff2d78'; ?>
              <span class="hist-badge" style="background:<?=$c?>18;color:<?=$c?>;border:1px solid <?=$c?>40;"><?= $typeLabels[$t]??$t ?></span>
              <?php endforeach; ?>
            </div>
            <span class="hist-date"><?= date('d/m/Y',strtotime($bet['date_post'])) ?></span>
          </div>
          <?php if ($bet['titre']): ?>
            <div class="hist-titre"><?= clean($bet['titre']) ?></div>
          <?php endif; ?>
          <div class="hist-info-bottom">
            <span class="result-badge" style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;border:1px solid <?= $rc['border'] ?>;">
              <?= $rc['icon'] ?> <?= $rc['label'] ?>
            </span>
            <?php if ($bet['date_resultat']): ?>
              <span class="result-date">Resultat le <?= date('d/m/Y',strtotime($bet['date_resultat'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($totalBets > $betsPerPage): ?>
    <div class="load-more-wrap">
      <button class="btn-load-more" id="loadMoreBtn" onclick="loadMore()">
        Voir plus <span id="remainCount">(<?= $totalBets - $betsPerPage ?>)</span>
      </button>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($membre): ?></main></div><?php endif; ?>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
  <button class="lightbox-close" onclick="closeLB()">✕</button>
  <div class="lightbox-inner" onclick="event.stopPropagation()">
    <img src="" alt="" class="lightbox-img" id="lbImg">
    <div class="lightbox-caption" id="lbCap"></div>
  </div>
</div>

<script>
document.querySelectorAll('.hist-img-wrap[data-src]').forEach(function(el){
  el.addEventListener('click',function(){
    var s=el.dataset.src;if(!s)return;
    document.getElementById('lbImg').src=s;
    document.getElementById('lbCap').textContent=el.dataset.caption||'';
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow='hidden';
  });
});
function closeLB(){document.getElementById('lightbox').classList.remove('open');document.getElementById('lbImg').src='';document.body.style.overflow='';}
document.getElementById('lightbox').addEventListener('click',function(e){if(e.target===this)closeLB();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeLB();});

function loadMore(){
  var hidden=document.querySelectorAll('.hist-card[data-hidden="1"]');
  var batch=12;
  var shown=0;
  hidden.forEach(function(card){
    if(shown>=batch)return;
    card.style.display='';
    card.removeAttribute('data-hidden');
    card.style.opacity='0';
    card.style.animation='cardSlide .4s ease-out forwards';
    card.style.animationDelay=(shown*0.04)+'s';
    shown++;
  });
  var remaining=document.querySelectorAll('.hist-card[data-hidden="1"]').length;
  if(remaining<=0){
    var btn=document.getElementById('loadMoreBtn');
    if(btn)btn.style.display='none';
  } else {
    var cnt=document.getElementById('remainCount');
    if(cnt)cnt.textContent='('+remaining+')';
  }
}

function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
