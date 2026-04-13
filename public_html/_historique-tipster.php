<?php
// =============================================================
// STRATEDGE — Template page détaillée d'un tipster
// Inclut: header tipster (mascotte+stats), filtres, liste bets, charts
// Réutilise la logique de l'ancienne historique mais filtrée
// =============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$tipsterFilter = $_GET['_tipster_filter'] ?? 'multi';
$validTipsters = ['multi', 'tennis', 'fun'];
if (!in_array($tipsterFilter, $validTipsters)) $tipsterFilter = 'multi';

// Configuration tipster
$tipsterConfig = [
    'multi' => [
        'name'   => 'STRATEDGE MULTI',
        'tag'    => '⚽ NBA · 🏒 NHL · ⚾ MLB',
        'mascot' => '/assets/images/mascotte-rose.png',
        'c1'     => '#ff2d7a',
        'c2'     => '#c850c0',
        'sections' => ['football_safe', 'football_live', 'hockey_safe', 'hockey_live', 'basket_safe', 'basket_live', 'baseball_safe', 'baseball_live', 'football_fun', 'hockey_fun', 'basket_fun', 'baseball_fun'],
    ],
    'tennis' => [
        'name'   => 'STRATEDGE TENNIS',
        'tag'    => 'ATP · WTA · GRAND SLAM',
        'mascot' => '/assets/images/mascotte-tennis-nobg.png',
        'c1'     => '#39ff14',
        'c2'     => '#00d46a',
        'sections' => ['tennis_safe', 'tennis_live', 'tennis_fun'],
    ],
    'fun' => [
        'name'   => 'STRATEDGE FUN',
        'tag'    => 'COTES FOLLES · DÉLIRE',
        'mascot' => '/assets/images/mascotte-fun-crazy-nobg.png',
        'c1'     => '#a855f7',
        'c2'     => '#ec4899',
        'sections' => ['football_fun', 'hockey_fun', 'basket_fun', 'baseball_fun'],
    ],
];
$tConf = $tipsterConfig[$tipsterFilter];

// Charger les bets filtrés par catégorie
$db = getDB();
// Inclut tous les bets historiques (gagnes/perdus/annules), pas seulement actifs
$resultFilter = "resultat IS NOT NULL AND resultat NOT IN ('en_cours','pending','')";
if ($tipsterFilter === 'fun') {
    $bets = $db->query("SELECT * FROM bets WHERE $resultFilter AND categorie = 'fun' ORDER BY date_post DESC")->fetchAll();
} else {
    $bets = $db->query("SELECT * FROM bets WHERE $resultFilter AND categorie = '" . $tipsterFilter . "' ORDER BY date_post DESC")->fetchAll();
}

// === Filtres GET ===
$filtreType   = $_GET['type'] ?? 'tous';      // tous|safe|fun|live
$filtreSport  = $_GET['sport'] ?? 'tous';     // tous|football|tennis|...
$filtreResult = $_GET['result'] ?? 'tous';    // tous|gagne|perdu|annule

// Appliquer filtres
$betsFiltres = $bets;
if ($filtreType !== 'tous') {
    $betsFiltres = array_filter($betsFiltres, fn($b) => strpos($b['type'] ?? '', $filtreType) !== false);
}
if ($filtreSport !== 'tous') {
    $betsFiltres = array_filter($betsFiltres, fn($b) => ($b['sport'] ?? '') === $filtreSport);
}
$betsAvantResult = $betsFiltres; // sauvegarder pour les charts (avant filtre résultat)
if ($filtreResult !== 'tous') {
    $betsFiltres = array_filter($betsFiltres, fn($b) => $b['resultat'] === $filtreResult);
}

// === Stats globales tipster ===
function _calcStatsT(array $arr): array {
    $g = count(array_filter($arr, fn($b) => $b['resultat'] === 'gagne'));
    $p = count(array_filter($arr, fn($b) => $b['resultat'] === 'perdu'));
    $a = count(array_filter($arr, fn($b) => $b['resultat'] === 'annule'));
    $taux = ($g + $p) > 0 ? round($g / ($g + $p) * 100) : 0;
    $cotes = array_filter(array_map(fn($b) => (float)str_replace(',', '.', $b['cote'] ?? 0), $arr), fn($c) => $c > 0);
    $coteMoy = count($cotes) > 0 ? round(array_sum($cotes) / count($cotes), 2) : 0;
    $miseTotale = $g + $p;
    $gainNet = 0;
    foreach ($arr as $b) {
        if ($b['resultat'] === 'gagne') {
            $c = (float)str_replace(',', '.', $b['cote'] ?? 0);
            if ($c > 0) $gainNet += ($c - 1);
        } elseif ($b['resultat'] === 'perdu') $gainNet -= 1;
    }
    $roi = $miseTotale > 0 ? round(($gainNet / $miseTotale) * 100, 1) : 0;
    return compact('g','p','a','taux','coteMoy','roi') + ['total' => count($arr)];
}
$stats = _calcStatsT($bets);

// Sports disponibles dans ce tipster (pour les filtres sport)
$sportsDispo = array_unique(array_filter(array_map(fn($b) => $b['sport'] ?? '', $bets)));
sort($sportsDispo);

// Évolution bankroll cumulative chronologique
$bankrollPoints = [];
$cumul = 0;
$arrChrono = array_reverse($bets);
foreach ($arrChrono as $b) {
    if ($b['resultat'] === 'gagne') {
        $c = (float)str_replace(',', '.', $b['cote'] ?? 0);
        if ($c > 0) $cumul += ($c - 1);
    } elseif ($b['resultat'] === 'perdu') $cumul -= 1;
    $bankrollPoints[] = round($cumul, 2);
}

$currentPage = 'historique';
$membre = isLoggedIn() ? getMembre() : null;

$resultatConfig = [
    'gagne'  => ['label'=>'Gagné',  'color'=>'#00c864', 'bg'=>'rgba(0,200,100,0.12)',  'icon'=>'✅'],
    'perdu'  => ['label'=>'Perdu',  'color'=>'#ff4444', 'bg'=>'rgba(255,68,68,0.12)',  'icon'=>'❌'],
    'annule' => ['label'=>'Annulé', 'color'=>'#f59e0b', 'bg'=>'rgba(245,158,11,0.12)', 'icon'=>'↺'],
];
$typeColors = ['safe'=>'#00d4ff','fun'=>'#a855f7','live'=>'#ff2d78'];
$sportEmojis = ['football'=>'⚽','tennis'=>'🎾','hockey'=>'🏒','basket'=>'🏀','baseball'=>'⚾'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($tConf['name']) ?> – Historique – StratEdge Pronos</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
@keyframes blink{0%,49%{opacity:1;}50%,100%{opacity:.3;}}
@keyframes pulse-glow{0%,100%{filter:brightness(1) saturate(1);}50%{filter:brightness(1.18) saturate(1.4);}}
@keyframes float-mascot{0%,100%{transform:translateY(0) rotate(-2deg);}50%{transform:translateY(-5px) rotate(2deg);}}
@keyframes draw-circle{from{stroke-dashoffset:100;}to{stroke-dashoffset:var(--off);}}
@keyframes bar-fill{from{width:0;}to{width:var(--w);}}

body{background:#05060d;color:#fff;font-family:'Rajdhani',sans-serif;margin:0;min-height:100vh;}
.cyber-bg{position:fixed;inset:0;background:radial-gradient(ellipse at 20% 20%,<?= $tConf['c1'] ?>15,transparent 50%),radial-gradient(ellipse at 80% 80%,<?= $tConf['c2'] ?>10,transparent 50%);pointer-events:none;z-index:0;}
.cyber-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0;}
.h-wrap{max-width:1300px;margin:0 auto;position:relative;z-index:2;padding:2rem 1rem;}

.back-link{display:inline-flex;align-items:center;gap:.5rem;color:rgba(255,255,255,.5);font-family:'Share Tech Mono',monospace;font-size:.75rem;text-decoration:none;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:1.5rem;transition:color .2s;}
.back-link:hover{color:<?= $tConf['c1'] ?>;}

/* === Header tipster === */
.t-header{position:relative;background:rgba(8,8,18,.92);border:2px solid <?= $tConf['c1'] ?>40;border-radius:16px;padding:1.8rem;margin-bottom:2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;}
.t-header::before,.t-header::after{content:'';position:absolute;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,<?= $tConf['c1'] ?>,transparent);}
.t-header::before{top:0;}.t-header::after{bottom:0;}
.t-header-mascot{width:135px;height:135px;border-radius:18px;background:radial-gradient(circle at center,<?= $tConf['c1'] ?>30,transparent 70%);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;animation:pulse-glow 3s ease infinite;}

.t-header-mascot img{position:relative;z-index:2;max-width:100px;max-height:115px;object-fit:contain;animation:float-mascot 3.5s ease-in-out infinite;filter:drop-shadow(0 0 12px <?= $tConf['c1'] ?>);}
.t-header-info{flex:1;min-width:200px;}
.t-header-name{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;letter-spacing:2px;margin:0 0 .3rem;background:linear-gradient(90deg,<?= $tConf['c1'] ?>,<?= $tConf['c2'] ?>);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.t-header-tag{font-family:'Share Tech Mono',monospace;font-size:.85rem;color:rgba(255,255,255,.5);letter-spacing:1.5px;text-transform:uppercase;}
.t-header-stats{display:flex;gap:1rem;flex-wrap:wrap;}
.t-hstat{text-align:center;padding:.7rem 1.2rem;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:8px;min-width:80px;}
.t-hstat-val{font-family:'Bebas Neue',cursive;font-size:1.6rem;line-height:1;color:<?= $tConf['c1'] ?>;display:block;margin-bottom:.15rem;letter-spacing:1px;}
.t-hstat-lbl{font-family:'Share Tech Mono',monospace;font-size:.6rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;display:block;}

/* === Donut + Sparkline grid === */
.detail-row{display:grid;grid-template-columns:1fr 2fr;gap:1.2rem;margin-bottom:2rem;}
@media(max-width:768px){.detail-row{grid-template-columns:1fr;}}
.detail-card{background:rgba(8,8,18,.85);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:1.4rem;}
.detail-card h3{font-family:'Orbitron',sans-serif;font-size:.85rem;font-weight:700;color:<?= $tConf['c1'] ?>;letter-spacing:2px;margin:0 0 1rem;text-transform:uppercase;}
.donut-display{display:flex;align-items:center;gap:1rem;}
.dd-svg{width:140px;height:140px;flex-shrink:0;}
.dd-bg{stroke:rgba(255,255,255,.06);}
.dd-fill{stroke:<?= $tConf['c1'] ?>;stroke-linecap:round;transform:rotate(-90deg);transform-origin:center;animation:draw-circle 1.6s cubic-bezier(.4,0,.2,1) forwards;stroke-dasharray:100;stroke-dashoffset:100;filter:drop-shadow(0 0 8px <?= $tConf['c1'] ?>);}
.dd-center{font-family:'Orbitron',sans-serif;font-size:8px;font-weight:900;fill:#fff;text-anchor:middle;dominant-baseline:central;}
.dd-pct{font-family:'Share Tech Mono',monospace;font-size:2.5px;fill:<?= $tConf['c1'] ?>;text-anchor:middle;text-transform:uppercase;letter-spacing:0.5px;}
.dd-info{flex:1;}
.dd-info-row{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.06);font-family:'Share Tech Mono',monospace;font-size:.8rem;color:rgba(255,255,255,.7);}
.dd-info-row:last-child{border:none;}
.dd-info-row b{color:#fff;font-family:'Bebas Neue',cursive;font-size:1.1rem;letter-spacing:.5px;}

.bankroll-chart{position:relative;height:200px;}
.bankroll-chart svg{width:100%;height:100%;}

/* === Filtres === */
.filters-row{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;}
.filter-group{display:flex;align-items:center;gap:.5rem;background:rgba(8,8,18,.85);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:.4rem;flex-wrap:wrap;}
.filter-group-lbl{font-family:'Share Tech Mono',monospace;font-size:.65rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;padding:.3rem .6rem;}
.filter-pill{padding:.5rem .9rem;background:transparent;border:1px solid rgba(255,255,255,.12);border-radius:6px;color:rgba(255,255,255,.7);font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;text-decoration:none;transition:all .2s;}
.filter-pill:hover{border-color:<?= $tConf['c1'] ?>;color:<?= $tConf['c1'] ?>;}
.filter-pill.active{background:<?= $tConf['c1'] ?>;color:#000;border-color:<?= $tConf['c1'] ?>;box-shadow:0 0 12px <?= $tConf['c1'] ?>;}

/* === Liste bets === */
.bets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;}
.bet-card{background:rgba(8,8,18,.85);border:1px solid rgba(255,255,255,.08);border-radius:12px;overflow:hidden;transition:all .25s;}
.bet-card:hover{transform:translateY(-3px);border-color:<?= $tConf['c1'] ?>;}
.bet-img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;background:#000;}
.bet-card-body{padding:.9rem 1rem;}
.bet-card-meta{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-family:'Share Tech Mono',monospace;font-size:.65rem;color:rgba(255,255,255,.45);letter-spacing:1px;text-transform:uppercase;margin-bottom:.5rem;}
.bet-result{display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .7rem;border-radius:6px;font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;}
.bet-cote{font-family:'Bebas Neue',cursive;font-size:1.1rem;color:#fff;letter-spacing:1px;}
.no-bets{text-align:center;padding:3rem 1rem;color:rgba(255,255,255,.4);font-family:'Share Tech Mono',monospace;letter-spacing:1.5px;text-transform:uppercase;}

.section-title{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:700;color:<?= $tConf['c1'] ?>;letter-spacing:2px;margin:0 0 1rem;text-transform:uppercase;}
.section-title::before{content:'>> ';color:rgba(255,255,255,.3);}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="cyber-bg"></div>
<div class="cyber-grid"></div>

<div class="h-wrap">
  <a href="/historique.php" class="back-link">← Retour aux tipsters</a>

  <!-- Header tipster -->
  <div class="t-header">
    <div class="t-header-mascot">
      <img src="<?= htmlspecialchars($tConf['mascot']) ?>" alt="<?= htmlspecialchars($tConf['name']) ?>">
    </div>
    <div class="t-header-info">
      <div class="t-header-name"><?= htmlspecialchars($tConf['name']) ?></div>
      <div class="t-header-tag"><?= htmlspecialchars($tConf['tag']) ?></div>
    </div>
    <div class="t-header-stats">
      <div class="t-hstat"><span class="t-hstat-val"><?= $stats['total'] ?></span><span class="t-hstat-lbl">Paris</span></div>
      <div class="t-hstat"><span class="t-hstat-val"><?= $stats['taux'] ?>%</span><span class="t-hstat-lbl">Win rate</span></div>
      <div class="t-hstat"><span class="t-hstat-val"><?= $stats['coteMoy'] ?: '-' ?></span><span class="t-hstat-lbl">Cote moy</span></div>
      <div class="t-hstat"><span class="t-hstat-val"><?= ($stats['roi'] >= 0 ? '+' : '') . $stats['roi'] ?>%</span><span class="t-hstat-lbl">ROI</span></div>
    </div>
  </div>

  <!-- Donut win/loss + Bankroll chart -->
  <div class="detail-row">
    <div class="detail-card">
      <h3>Win / Loss Ratio</h3>
      <?php $donutOff = 100 - $stats['taux']; ?>
      <div class="donut-display">
        <svg class="dd-svg" viewBox="0 0 36 36">
          <circle class="dd-bg" cx="18" cy="18" r="15.91" fill="transparent" stroke-width="3"/>
          <circle class="dd-fill" cx="18" cy="18" r="15.91" fill="transparent" stroke-width="3" pathLength="100" style="--off:<?= $donutOff ?>"/>
          <text class="dd-center" x="18" y="15"><?= $stats['taux'] ?></text>
          <text class="dd-pct" x="18" y="24">% WIN</text>
        </svg>
        <div class="dd-info">
          <div class="dd-info-row">Gagnés <b><?= $stats['g'] ?></b></div>
          <div class="dd-info-row">Perdus <b><?= $stats['p'] ?></b></div>
          <div class="dd-info-row">Annulés <b><?= $stats['a'] ?></b></div>
          <div class="dd-info-row">Total <b><?= $stats['total'] ?></b></div>
        </div>
      </div>
    </div>

    <div class="detail-card">
      <h3>Évolution Bankroll (cumul)</h3>
      <div class="bankroll-chart">
        <?php
        if (count($bankrollPoints) >= 2) {
            $w = 600; $h = 180;
            $min = min($bankrollPoints); $max = max($bankrollPoints);
            $range = max(1, $max - $min);
            $count = count($bankrollPoints);
            $points = [];
            foreach ($bankrollPoints as $i => $v) {
                $x = ($i / ($count - 1)) * $w;
                $y = $h - 10 - (($v - $min) / $range) * ($h - 30);
                $points[] = round($x, 1) . ',' . round($y, 1);
            }
            $line = 'M' . implode(' L', $points);
            $area = $line . ' L' . $w . ',' . $h . ' L0,' . $h . ' Z';
            $zeroY = $h - 10 - ((0 - $min) / $range) * ($h - 30);
        ?>
        <svg viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none">
          <defs>
            <linearGradient id="bk-grad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="<?= $tConf['c1'] ?>" stop-opacity=".4"/>
              <stop offset="100%" stop-color="<?= $tConf['c1'] ?>" stop-opacity="0"/>
            </linearGradient>
          </defs>
          <?php if ($min < 0 && $max > 0): ?>
          <line x1="0" y1="<?= round($zeroY,1) ?>" x2="<?= $w ?>" y2="<?= round($zeroY,1) ?>" stroke="rgba(255,255,255,.15)" stroke-width="1" stroke-dasharray="4,4"/>
          <?php endif; ?>
          <path d="<?= $area ?>" fill="url(#bk-grad)"/>
          <path d="<?= $line ?>" fill="none" stroke="<?= $tConf['c1'] ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter:drop-shadow(0 0 4px <?= $tConf['c1'] ?>);"/>
        </svg>
        <?php } else { ?>
          <div class="no-bets">Pas assez de données</div>
        <?php } ?>
      </div>
    </div>
  </div>

  <!-- Filtres -->
  <h2 class="section-title">Filtres</h2>
  <div class="filters-row">
    <?php
    $baseUrl = '/historique-' . $tipsterFilter . '.php';
    $params = $_GET; unset($params['_tipster_filter']);
    function _buildLink($base, $params, $key, $val) {
        $params[$key] = $val;
        return $base . '?' . http_build_query(array_filter($params, fn($v) => $v !== 'tous'));
    }
    ?>
    <div class="filter-group">
      <span class="filter-group-lbl">Type</span>
      <a class="filter-pill <?= $filtreType === 'tous' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'type', 'tous') ?>">Tous</a>
      <a class="filter-pill <?= $filtreType === 'safe' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'type', 'safe') ?>">🛡️ Safe</a>
      <a class="filter-pill <?= $filtreType === 'live' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'type', 'live') ?>">⚡ Live</a>
      <a class="filter-pill <?= $filtreType === 'fun' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'type', 'fun') ?>">🎯 Fun</a>
    </div>

    <?php if (count($sportsDispo) > 1): ?>
    <div class="filter-group">
      <span class="filter-group-lbl">Sport</span>
      <a class="filter-pill <?= $filtreSport === 'tous' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'sport', 'tous') ?>">Tous</a>
      <?php foreach ($sportsDispo as $sp): ?>
      <a class="filter-pill <?= $filtreSport === $sp ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'sport', $sp) ?>"><?= ($sportEmojis[$sp] ?? '') . ' ' . ucfirst($sp) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="filter-group">
      <span class="filter-group-lbl">Résultat</span>
      <a class="filter-pill <?= $filtreResult === 'tous' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'result', 'tous') ?>">Tous</a>
      <a class="filter-pill <?= $filtreResult === 'gagne' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'result', 'gagne') ?>" style="<?= $filtreResult === 'gagne' ? 'background:#00c864;border-color:#00c864;color:#000;' : '' ?>">✅ Gagnés</a>
      <a class="filter-pill <?= $filtreResult === 'perdu' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'result', 'perdu') ?>" style="<?= $filtreResult === 'perdu' ? 'background:#ff4444;border-color:#ff4444;color:#fff;' : '' ?>">❌ Perdus</a>
      <a class="filter-pill <?= $filtreResult === 'annule' ? 'active' : '' ?>" href="<?= _buildLink($baseUrl, $params, 'result', 'annule') ?>">↺ Annulés</a>
    </div>
  </div>

  <!-- Liste bets -->
  <h2 class="section-title">Pronostics (<?= count($betsFiltres) ?>)</h2>
  <?php if (empty($betsFiltres)): ?>
    <div class="no-bets">Aucun pronostic ne correspond à ces filtres</div>
  <?php else: ?>
    <div class="bets-grid">
      <?php foreach ($betsFiltres as $b):
        $rConf = $resultatConfig[$b['resultat']] ?? null;
        $type = $b['type'] ?? 'safe';
        $typeKey = strpos($type, 'live') !== false ? 'live' : (strpos($type, 'fun') !== false ? 'fun' : 'safe');
        $tColor = $typeColors[$typeKey] ?? '#888';
        $sport = $b['sport'] ?? '';
        $emoji = $sportEmojis[$sport] ?? '';
        $img = $b['image'] ?? '';
        $cote = $b['cote'] ?? '';
        $datePost = $b['date_post'] ?? '';
        $dateAffichee = $datePost ? date('d/m/Y', strtotime($datePost)) : '';
      ?>
      <div class="bet-card">
        <?php if ($img): ?><img class="bet-img" src="<?= htmlspecialchars($img) ?>" alt="bet" loading="lazy"><?php endif; ?>
        <div class="bet-card-body">
          <div class="bet-card-meta">
            <span style="color:<?= $tColor ?>;"><?= $emoji ?> <?= strtoupper($typeKey) ?></span>
            <span><?= htmlspecialchars($dateAffichee) ?></span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
            <?php if ($rConf): ?>
            <span class="bet-result" style="background:<?= $rConf['bg'] ?>;color:<?= $rConf['color'] ?>;border:1px solid <?= $rConf['color'] ?>40;">
              <?= $rConf['icon'] ?> <?= $rConf['label'] ?>
            </span>
            <?php else: ?>
            <span class="bet-result" style="background:rgba(255,255,255,.05);color:rgba(255,255,255,.5);">⏳ En cours</span>
            <?php endif; ?>
            <?php if ($cote): ?><span class="bet-cote">@ <?= htmlspecialchars($cote) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php @require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
