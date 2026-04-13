<?php
// =============================================================
// STRATEDGE — Historique HUB (3 tipsters cards cyberpunk)
// Refonte avec mascottes + donut + sparkline + ROI
// Pages détaillées: historique-multi.php / historique-tennis.php / historique-fun.php
// =============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$db = getDB();
// Tous les bets dont le resultat est connu (pas seulement les actifs)
$bets = $db->query("SELECT * FROM bets WHERE resultat IS NOT NULL AND resultat NOT IN ('en_cours','pending','') ORDER BY date_post DESC")->fetchAll();

// Calcul stats pour un set de bets
function calcStats(array $arr): array {
    $g = count(array_filter($arr, fn($b) => $b['resultat'] === 'gagne'));
    $p = count(array_filter($arr, fn($b) => $b['resultat'] === 'perdu'));
    $a = count(array_filter($arr, fn($b) => $b['resultat'] === 'annule'));
    $total = count($arr);
    $taux = ($g + $p) > 0 ? round($g / ($g + $p) * 100) : 0;
    $cotes = array_filter(array_map(fn($b) => (float)str_replace(',', '.', $b['cote'] ?? 0), $arr), fn($c) => $c > 0);
    $coteMoy = count($cotes) > 0 ? round(array_sum($cotes) / count($cotes), 2) : 0;

    // ROI : (gain net / mise totale) * 100
    // Mise = 1 unité par pari, gain = (cote-1) si win, -1 si perdu, 0 si annulé
    $miseTotale = $g + $p; // hors annulés
    $gainNet = 0;
    foreach ($arr as $b) {
        if ($b['resultat'] === 'gagne') {
            $c = (float)str_replace(',', '.', $b['cote'] ?? 0);
            if ($c > 0) $gainNet += ($c - 1);
        } elseif ($b['resultat'] === 'perdu') {
            $gainNet -= 1;
        }
    }
    $roi = $miseTotale > 0 ? round(($gainNet / $miseTotale) * 100, 1) : 0;

    // Streak max win
    $streakMax = 0; $streakCurrent = 0;
    $arrChrono = array_reverse($arr);
    foreach ($arrChrono as $b) {
        if ($b['resultat'] === 'gagne') { $streakCurrent++; $streakMax = max($streakMax, $streakCurrent); }
        elseif ($b['resultat'] === 'perdu') { $streakCurrent = 0; }
    }

    // 5 derniers (résultats finalisés seulement)
    $finalises = array_filter($arr, fn($b) => in_array($b['resultat'], ['gagne','perdu']));
    $last5 = array_slice($finalises, 0, 5);
    $form = array_map(fn($b) => $b['resultat'] === 'gagne' ? 'w' : 'l', $last5);

    // Évolution bankroll (40 derniers points en chronologique)
    $bankroll = []; $cumul = 0;
    foreach (array_reverse($arrChrono) as $b) { /* skip */ }
    foreach ($arrChrono as $b) {
        if ($b['resultat'] === 'gagne') {
            $c = (float)str_replace(',', '.', $b['cote'] ?? 0);
            if ($c > 0) $cumul += ($c - 1);
        } elseif ($b['resultat'] === 'perdu') {
            $cumul -= 1;
        }
        $bankroll[] = $cumul;
    }
    // Sample 40 points pour le sparkline
    $bankroll = array_slice($bankroll, -40);

    return [
        'gagnes'      => $g,
        'perdus'      => $p,
        'annules'     => $a,
        'total'       => $total,
        'taux'        => $taux,
        'cote_moy'    => $coteMoy,
        'roi'         => $roi,
        'streak_max'  => $streakMax,
        'form'        => $form,
        'bankroll'    => $bankroll,
    ];
}

// Filtrer par tipster:
// - MULTI = categorie='multi' SAUF type='fun' (Fun part dans son tipster)
// - TENNIS = categorie='tennis' (incl. tennis_fun)
// - FUN = type='fun' (peu importe la categorie multi/tennis - sauf les tennis_fun qui restent dans Tennis)
function _isFun($b) { return strpos($b['type'] ?? '', 'fun') !== false; }
function _isTennis($b) { return ($b['categorie'] ?? '') === 'tennis'; }

$multiBets = array_filter($bets, fn($b) => !_isTennis($b) && !_isFun($b));
$tennisBets = array_filter($bets, fn($b) => _isTennis($b)); // inclut tennis_fun
$funBets = array_filter($bets, fn($b) => !_isTennis($b) && _isFun($b)); // multi+fun uniquement

// Récupérer les 3 derniers bets de chaque tipster (avec image valide)
function lastBetsWithImg(array $arr, int $n = 3): array {
    $withImg = array_filter($arr, fn($b) => !empty($b['image_path']));
    return array_slice($withImg, 0, $n);
}

$tipsters = [
    'multi' => [
        'id'        => '001',
        'name'      => 'STRATEDGE MULTI',
        'tag'       => '⚽ NBA · 🏒 NHL · ⚾ MLB',
        'status'    => 'ACTIF · 24/7',
        'mascot'    => '/assets/images/mascotte-rose.png',
        'c1'        => '#ff2d7a',
        'c2'        => '#c850c0',
        'href'      => '/historique-multi.php',
        'stats'     => calcStats($multiBets),
        'lastBets'  => lastBetsWithImg($multiBets),
    ],
    'tennis' => [
        'id'        => '002',
        'name'      => 'STRATEDGE TENNIS',
        'tag'       => 'ATP · WTA · GRAND SLAM',
        'status'    => 'PREMIUM · BASELINE v1.4',
        'mascot'    => '/assets/images/mascotte-tennis-nobg.png',
        'c1'        => '#39ff14',
        'c2'        => '#00d46a',
        'href'      => '/historique-tennis.php',
        'stats'     => calcStats($tennisBets),
        'lastBets'  => lastBetsWithImg($tennisBets),
    ],
    'fun' => [
        'id'        => '003',
        'name'      => 'STRATEDGE FUN',
        'tag'       => 'COTES FOLLES · DÉLIRE',
        'status'    => 'CHAOS MODE · ON',
        'mascot'    => '/assets/images/mascotte-fun-crazy-nobg.png',
        'c1'        => '#a855f7',
        'c2'        => '#ec4899',
        'href'      => '/historique-fun.php',
        'stats'     => calcStats($funBets),
        'lastBets'  => lastBetsWithImg($funBets),
    ],
];

// Helper pour générer le path SVG sparkline
function sparklinePath(array $bankroll, float $w = 300, float $h = 48): array {
    if (count($bankroll) < 2) return ['line' => '', 'area' => ''];
    $min = min($bankroll); $max = max($bankroll);
    $range = $max - $min;
    if ($range == 0) $range = 1;
    $points = [];
    $count = count($bankroll);
    foreach ($bankroll as $i => $v) {
        $x = ($i / ($count - 1)) * $w;
        $y = $h - 4 - (($v - $min) / $range) * ($h - 8);
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = 'M' . implode(' L', $points);
    $area = $line . ' L' . round($w, 1) . ',' . $h . ' L0,' . $h . ' Z';
    return ['line' => $line, 'area' => $area];
}

$currentPage = 'historique';
$membre = isLoggedIn() ? getMembre() : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Historique des Tipsters – StratEdge Pronos</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
@keyframes scan{0%{transform:translateY(-100%);}100%{transform:translateY(900%);}}
@keyframes pulse-glow{0%,100%{filter:brightness(1) saturate(1);}50%{filter:brightness(1.18) saturate(1.4);}}
@keyframes rot-bg{0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}
@keyframes bar-fill{from{width:0;}to{width:var(--w);}}
@keyframes blink{0%,49%{opacity:1;}50%,100%{opacity:.3;}}
@keyframes float-mascot{0%,100%{transform:translateY(0) rotate(-2deg);}50%{transform:translateY(-5px) rotate(2deg);}}
@keyframes draw-circle{from{stroke-dashoffset:100;}to{stroke-dashoffset:var(--off);}}
@keyframes draw-line{from{stroke-dashoffset:600;}to{stroke-dashoffset:0;}}
@keyframes glow-ring{0%,100%{filter:drop-shadow(0 0 3px var(--c1));}50%{filter:drop-shadow(0 0 12px var(--c1));}}
@keyframes count-up{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}

body{background:#05060d;color:#fff;font-family:'Rajdhani',sans-serif;margin:0;min-height:100vh;}
.cyber-bg{position:fixed;inset:0;background:radial-gradient(ellipse at 15% 15%,rgba(255,45,122,.07),transparent 50%),radial-gradient(ellipse at 85% 85%,rgba(57,255,20,.06),transparent 50%),radial-gradient(ellipse at 50% 50%,rgba(168,85,247,.05),transparent 60%);pointer-events:none;z-index:0;}
.cyber-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0;}
.h-wrap{max-width:1300px;margin:0 auto;position:relative;z-index:2;padding:2.5rem 1rem;}
.h-hero{text-align:center;margin-bottom:3rem;position:relative;}
.h-hero::before{content:'> ANALYSE.SYS [v3.1]';position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-family:'Share Tech Mono',monospace;font-size:.7rem;color:#00d4ff;letter-spacing:5px;opacity:.6;}
.h-hero h1{font-family:'Orbitron',sans-serif;font-size:clamp(1.7rem,4.2vw,2.6rem);font-weight:900;background:linear-gradient(90deg,#ff2d7a,#c850c0,#00d4ff,#39ff14,#ff2d7a);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:rot-bg 8s ease infinite;margin:0 0 .6rem;letter-spacing:2px;text-transform:uppercase;}
.h-hero p{color:rgba(255,255,255,.5);font-family:'Share Tech Mono',monospace;font-size:.85rem;letter-spacing:2px;margin:0;}
.h-hero p::before{content:'// ';color:#00d4ff;}

.tipsters-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;}
@media(max-width:1024px){.tipsters-grid{grid-template-columns:1fr;max-width:480px;margin:0 auto;}}

.t-card{position:relative;background:rgba(8,8,18,.92);backdrop-filter:blur(10px);overflow:hidden;transition:all .4s cubic-bezier(.2,.9,.3,1.4);clip-path:polygon(0 0,calc(100% - 18px) 0,100% 18px,100% 100%,18px 100%,0 calc(100% - 18px));text-decoration:none;color:#fff;display:block;}
.t-card:hover{transform:translateY(-8px) scale(1.01);}
.t-corners span{position:absolute;width:22px;height:22px;border:2px solid var(--c1);pointer-events:none;}
.t-corners span:nth-child(1){top:6px;left:6px;border-right:none;border-bottom:none;}
.t-corners span:nth-child(2){top:6px;right:24px;border-left:none;border-bottom:none;}
.t-corners span:nth-child(3){bottom:24px;left:6px;border-right:none;border-top:none;}
.t-corners span:nth-child(4){bottom:6px;right:6px;border-left:none;border-top:none;}

.t-multi{box-shadow:0 0 30px rgba(255,45,122,.18),inset 0 0 40px rgba(255,45,122,.04);}
.t-multi:hover{box-shadow:0 18px 70px rgba(255,45,122,.45),inset 0 0 60px rgba(255,45,122,.1);}
.t-tennis{box-shadow:0 0 30px rgba(57,255,20,.18),inset 0 0 40px rgba(57,255,20,.04);}
.t-tennis:hover{box-shadow:0 18px 70px rgba(57,255,20,.45),inset 0 0 60px rgba(57,255,20,.1);}
.t-fun{box-shadow:0 0 30px rgba(168,85,247,.18),inset 0 0 40px rgba(168,85,247,.04);}
.t-fun:hover{box-shadow:0 18px 70px rgba(168,85,247,.45),inset 0 0 60px rgba(168,85,247,.1);}

.t-scan{position:absolute;left:0;right:0;height:80px;background:linear-gradient(180deg,transparent,var(--c1) 50%,transparent);opacity:.06;animation:scan 4s linear infinite;pointer-events:none;}
.t-frame::before,.t-frame::after{content:'';position:absolute;background:linear-gradient(90deg,transparent,var(--c1),transparent);height:1.5px;left:0;right:0;}
.t-frame::before{top:0;}.t-frame::after{bottom:0;}

.t-banner{position:relative;padding:1.4rem 1.4rem 1rem;background:linear-gradient(180deg,rgba(0,0,0,0) 0%,rgba(0,0,0,.4) 100%);}
.t-tipster-id{position:absolute;top:.6rem;right:1.5rem;font-family:'Share Tech Mono',monospace;font-size:.65rem;color:var(--c1);opacity:.75;letter-spacing:2px;}
.t-tipster-id .dot{width:6px;height:6px;border-radius:50%;background:var(--c1);display:inline-block;margin-right:5px;animation:blink 1.4s infinite;box-shadow:0 0 8px var(--c1);}

.t-mascotte-zone{display:flex;align-items:center;gap:1rem;margin-bottom:.5rem;}
.t-mascot-frame{width:90px;height:90px;border-radius:14px;background:radial-gradient(circle at center,var(--c1)25,transparent 70%);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;animation:pulse-glow 3s ease infinite;}

.t-mascot-img{position:relative;z-index:2;width:78px;height:88px;display:flex;align-items:center;justify-content:center;animation:float-mascot 3.5s ease-in-out infinite;filter:drop-shadow(0 0 12px var(--c1));}
.t-mascot-img img{max-width:78px;max-height:88px;object-fit:contain;}
.t-meta{flex:1;min-width:0;}
.t-name{font-family:'Orbitron',sans-serif;font-size:1.05rem;font-weight:900;letter-spacing:1.5px;margin:0 0 .25rem;background:linear-gradient(90deg,var(--c1),var(--c2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.t-tag{font-family:'Share Tech Mono',monospace;font-size:.7rem;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;}
.t-status{display:inline-block;margin-top:.4rem;font-family:'Share Tech Mono',monospace;font-size:.6rem;color:#39ff14;letter-spacing:1.5px;}
.t-status::before{content:'●';margin-right:4px;animation:blink 1.5s infinite;}

.t-divider{height:1px;background:linear-gradient(90deg,transparent,var(--c1),transparent);opacity:.4;margin:0;}

.t-donut-zone{padding:1.2rem 1.4rem 1rem;display:flex;align-items:center;gap:1rem;}
.t-donut-svg{width:120px;height:120px;flex-shrink:0;animation:glow-ring 3s ease infinite;}
.t-donut-bg{stroke:rgba(255,255,255,.06);}
.t-donut-fill{stroke:var(--c1);stroke-linecap:round;transform:rotate(-90deg);transform-origin:center;animation:draw-circle 1.6s cubic-bezier(.4,0,.2,1) forwards;stroke-dasharray:100;stroke-dashoffset:100;}
.t-donut-center{font-family:'Orbitron',sans-serif;font-size:8px;font-weight:900;fill:#fff;text-anchor:middle;dominant-baseline:central;}
.t-donut-pct{font-family:'Share Tech Mono',monospace;font-size:2.5px;fill:var(--c1);text-anchor:middle;text-transform:uppercase;letter-spacing:0.5px;}
.t-donut-info{flex:1;}
.t-donut-info-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem;font-size:.75rem;color:rgba(255,255,255,.7);font-family:'Share Tech Mono',monospace;text-transform:uppercase;letter-spacing:1px;}
.t-donut-info-row .dot-w{width:8px;height:8px;border-radius:50%;background:var(--c1);box-shadow:0 0 6px var(--c1);}
.t-donut-info-row .dot-l{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.15);}
.t-donut-info-row .num{margin-left:auto;font-family:'Bebas Neue',cursive;font-size:1.1rem;color:#fff;}

.t-stats-zone{padding:0 1.4rem 1rem;display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;}
.t-stat{position:relative;text-align:center;padding:.6rem .3rem;background:linear-gradient(180deg,rgba(255,255,255,.025),rgba(255,255,255,.005));border:1px solid rgba(255,255,255,.06);border-radius:6px;animation:count-up .7s ease forwards;}
.t-stat::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:30%;height:1.5px;background:var(--c1);}
.t-stat-val{font-family:'Bebas Neue',cursive;font-size:1.5rem;line-height:1;color:#fff;display:block;margin-bottom:.15rem;letter-spacing:1px;}
.t-stat-lbl{font-family:'Share Tech Mono',monospace;font-size:.55rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;display:block;}

.t-spark-zone{padding:0 1.4rem 1rem;}
.t-spark-lbl{display:flex;justify-content:space-between;align-items:center;font-family:'Share Tech Mono',monospace;font-size:.6rem;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:.4rem;}
.t-spark-lbl .roi{color:var(--c1);font-weight:700;}
.t-spark-svg{width:100%;height:48px;display:block;}
.t-spark-line{fill:none;stroke:var(--c1);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:600;stroke-dashoffset:600;animation:draw-line 2s cubic-bezier(.4,0,.2,1) .3s forwards;filter:drop-shadow(0 0 4px var(--c1));}
.t-spark-area{opacity:.25;}

.t-progress-zone{padding:0 1.4rem 1rem;}
.t-progress-lbl{display:flex;justify-content:space-between;font-family:'Share Tech Mono',monospace;font-size:.6rem;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:.4rem;}
.t-progress-lbl span:last-child{color:var(--c1);font-family:'Bebas Neue',cursive;font-size:.95rem;letter-spacing:1px;}
.t-progress-bar{height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;position:relative;}
.t-progress-fill{height:100%;background:linear-gradient(90deg,var(--c1),var(--c2));border-radius:3px;animation:bar-fill 1.5s cubic-bezier(.4,0,.2,1) forwards;box-shadow:0 0 10px var(--c1);}

.t-streak-zone{padding:0 1.4rem 1rem;}
.t-recent-zone{padding:0 1.4rem 1rem;}
.t-recent-lbl{display:flex;justify-content:space-between;align-items:center;font-family:'Share Tech Mono',monospace;font-size:.6rem;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:.5rem;}
.t-recent-lbl span:last-child{color:var(--c1);font-weight:700;}
.t-recent-thumbs{display:flex;flex-direction:column;gap:6px;}
.t-recent-thumb{display:flex;align-items:stretch;gap:8px;border-radius:6px;overflow:hidden;position:relative;border:1px solid rgba(255,255,255,.08);transition:all .25s;background:rgba(255,255,255,.025);min-height:54px;}
.t-recent-thumb:hover{border-color:var(--c1);transform:translateX(2px);box-shadow:0 0 12px var(--c1)20;}
.t-recent-thumb-img{width:54px;height:54px;flex-shrink:0;background:#0a0a14;overflow:hidden;}
.t-recent-thumb-img img{width:100%;height:100%;object-fit:cover;display:block;}
.t-recent-thumb-info{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;padding:.4rem .5rem .4rem 0;}
.t-recent-thumb-title{font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;color:#fff;letter-spacing:.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.2rem;}
.t-recent-thumb-meta{display:flex;align-items:center;gap:.5rem;font-family:'Share Tech Mono',monospace;font-size:.6rem;color:rgba(255,255,255,.5);letter-spacing:1px;text-transform:uppercase;}
.t-recent-thumb-cote{color:var(--c1);font-family:'Bebas Neue',cursive;font-size:.85rem;letter-spacing:.5px;}
.t-recent-thumb-result{display:inline-flex;align-items:center;justify-content:center;padding:0 .5rem;height:18px;border-radius:9px;font-size:.6rem;font-weight:900;font-family:'Orbitron',sans-serif;letter-spacing:.5px;flex-shrink:0;align-self:center;margin-right:.5rem;}
.t-recent-thumb-result.w{background:#39ff14;color:#000;box-shadow:0 0 6px rgba(57,255,20,.7);}
.t-recent-thumb-result.l{background:#ff2d78;color:#fff;box-shadow:0 0 6px rgba(255,45,120,.6);}
.t-recent-thumb-result.a{background:#f59e0b;color:#000;}
.t-recent-thumb-result.n{background:rgba(255,255,255,.2);color:#fff;}
.t-streak-lbl{display:flex;justify-content:space-between;align-items:center;font-family:'Share Tech Mono',monospace;font-size:.6rem;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:.5rem;}
.t-streak-lbl span:last-child{color:var(--c1);font-weight:700;}
.t-streak-pills{display:flex;gap:4px;}
.s-pill{flex:1;height:24px;border-radius:3px;position:relative;overflow:hidden;}
.s-pill.w{background:linear-gradient(180deg,#39ff14,#00d46a);box-shadow:0 0 8px rgba(57,255,20,.5),inset 0 1px 0 rgba(255,255,255,.3);}
.s-pill.l{background:linear-gradient(180deg,#ff2d78,#c4274d);box-shadow:0 0 8px rgba(255,45,120,.4),inset 0 1px 0 rgba(255,255,255,.2);}
.s-pill.n{background:rgba(255,255,255,.05);border:1px dashed rgba(255,255,255,.1);}
.s-pill::after{content:attr(data-r);position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:'Orbitron',sans-serif;font-size:.55rem;font-weight:900;color:rgba(0,0,0,.7);letter-spacing:1px;}

.t-cta-zone{padding:.6rem 1.4rem 1.4rem;}
.t-cta{display:flex;align-items:center;justify-content:space-between;gap:.5rem;width:100%;padding:.85rem 1.2rem;background:transparent;border:1.5px solid var(--c1);color:var(--c1);font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;cursor:pointer;text-decoration:none;transition:all .25s;clip-path:polygon(0 0,calc(100% - 12px) 0,100% 50%,calc(100% - 12px) 100%,0 100%,12px 50%);position:relative;overflow:hidden;}
.t-cta::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,var(--c1)20,transparent);transform:translateX(-100%);transition:transform .4s;}
.t-card:hover .t-cta::before{transform:translateX(100%);}
.t-card:hover .t-cta{background:var(--c1);color:#000;box-shadow:0 0 25px var(--c1);}
.t-cta-text{flex:1;text-align:center;}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="cyber-bg"></div>
<div class="cyber-grid"></div>

<div class="h-wrap">
  <div class="h-hero">
    <h1>Tipsters Network</h1>
    <p>3 EXPERTS · STATS LIVE · CHOISIS TON ANALYSTE</p>
  </div>

  <div class="tipsters-grid">
    <?php foreach ($tipsters as $key => $t): $s = $t['stats']; ?>
    <?php
      // Win rate dans le donut
      $winLossTotal = $s['gagnes'] + $s['perdus'];
      $winRate = $winLossTotal > 0 ? round($s['gagnes'] / $winLossTotal * 100) : 0;
      $donutOff = 100 - $winRate; // dasharray=100 → offset = 100 - %

      // Sparkline
      $spark = sparklinePath($s['bankroll']);

      // Form pills (compléter à 5 si moins)
      $form = $s['form'];
      while (count($form) < 5) $form[] = 'n';

      // ROI display
      $roiSign = $s['roi'] >= 0 ? '+' : '';
      $roiPct = $s['roi'];
      // ROI bar : visualisation 0-100% (cap à 100%)
      $roiBarW = min(100, max(0, abs($roiPct))) ;

      // Wins/Losses count for streak label
      $wCount = count(array_filter($form, fn($f) => $f === 'w'));
      $lCount = count(array_filter($form, fn($f) => $f === 'l'));
    ?>
    <a href="<?= $t['href'] ?>" class="t-card t-<?= $key ?>" style="--c1:<?= $t['c1'] ?>;--c2:<?= $t['c2'] ?>;">
      <div class="t-scan"></div>
      <div class="t-frame"></div>
      <div class="t-corners"><span></span><span></span><span></span><span></span></div>

      <div class="t-banner">
        <div class="t-tipster-id"><span class="dot"></span>ID:<?= $t['id'] ?></div>
        <div class="t-mascotte-zone">
          <div class="t-mascot-frame">
            <div class="t-mascot-img"><img src="<?= htmlspecialchars($t['mascot']) ?>" alt="<?= htmlspecialchars($t['name']) ?>"></div>
          </div>
          <div class="t-meta">
            <div class="t-name"><?= htmlspecialchars($t['name']) ?></div>
            <div class="t-tag"><?= htmlspecialchars($t['tag']) ?></div>
            <div class="t-status"><?= htmlspecialchars($t['status']) ?></div>
          </div>
        </div>
      </div>

      <div class="t-divider"></div>

      <div class="t-donut-zone">
        <svg class="t-donut-svg" viewBox="0 0 36 36">
          <circle class="t-donut-bg" cx="18" cy="18" r="15.91" fill="transparent" stroke-width="3"/>
          <circle class="t-donut-fill" cx="18" cy="18" r="15.91" fill="transparent" stroke-width="3" pathLength="100" style="--off:<?= $donutOff ?>"/>
          <text class="t-donut-center" x="18" y="15"><?= $winRate ?></text>
          <text class="t-donut-pct" x="18" y="24">% WIN</text>
        </svg>
        <div class="t-donut-info">
          <div class="t-donut-info-row"><span class="dot-w"></span>Gagnés<span class="num"><?= $s['gagnes'] ?></span></div>
          <div class="t-donut-info-row"><span class="dot-l"></span>Perdus<span class="num"><?= $s['perdus'] ?></span></div>
        </div>
      </div>

      <div class="t-stats-zone">
        <div class="t-stat" style="animation-delay:.1s;"><span class="t-stat-val"><?= $s['total'] ?></span><span class="t-stat-lbl">Paris</span></div>
        <div class="t-stat" style="animation-delay:.2s;"><span class="t-stat-val"><?= $s['cote_moy'] ?: '-' ?></span><span class="t-stat-lbl">Cote moy</span></div>
        <div class="t-stat" style="animation-delay:.3s;"><span class="t-stat-val">+<?= $s['streak_max'] ?></span><span class="t-stat-lbl">Streak max</span></div>
      </div>

      <?php if (!empty($spark['line'])): ?>
      <div class="t-spark-zone">
        <div class="t-spark-lbl"><span>Évolution bankroll</span><span class="roi"><?= $roiSign . $roiPct ?>%</span></div>
        <svg class="t-spark-svg" viewBox="0 0 300 48" preserveAspectRatio="none">
          <defs><linearGradient id="grad-area-<?= $key ?>" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="<?= $t['c1'] ?>" stop-opacity=".3"/>
            <stop offset="100%" stop-color="<?= $t['c1'] ?>" stop-opacity="0"/>
          </linearGradient></defs>
          <path class="t-spark-area" d="<?= $spark['area'] ?>" fill="url(#grad-area-<?= $key ?>)"/>
          <path class="t-spark-line" d="<?= $spark['line'] ?>"/>
        </svg>
      </div>
      <?php endif; ?>

      <div class="t-progress-zone">
        <div class="t-progress-lbl"><span>ROI Performance</span><span><?= $roiSign . $roiPct ?>%</span></div>
        <div class="t-progress-bar"><div class="t-progress-fill" style="--w:<?= $roiBarW ?>%;"></div></div>
      </div>

      <?php if (!empty($t['lastBets'])): ?>
      <div class="t-recent-zone">
        <div class="t-recent-lbl"><span>Derniers pronos</span><span><?= count($t['lastBets']) ?></span></div>
        <div class="t-recent-thumbs">
          <?php
          $resultMap = ['gagne' => 'w', 'perdu' => 'l', 'annule' => 'a'];
          $resultLabel = ['w' => 'WIN', 'l' => 'LOSS', 'a' => 'NUL', 'n' => '?'];
          foreach ($t['lastBets'] as $b):
            $rk = $resultMap[$b['resultat'] ?? ''] ?? 'n';
            $imgUrl = betImageUrl($b['image_path']);
            $titre = $b['titre'] ?? 'Bet';
            $cote = $b['cote'] ?? '';
          ?>
          <a href="<?= $t['href'] ?>" class="t-recent-thumb" style="text-decoration:none;color:inherit;">
            <div class="t-recent-thumb-img"><img src="<?= htmlspecialchars($imgUrl) ?>" alt="bet" loading="lazy"></div>
            <div class="t-recent-thumb-info">
              <div class="t-recent-thumb-title"><?= htmlspecialchars($titre) ?></div>
              <div class="t-recent-thumb-meta">
                <?php if ($cote): ?><span class="t-recent-thumb-cote">@<?= htmlspecialchars($cote) ?></span><?php endif; ?>
                <?php if (!empty($b['date_post'])): ?><span><?= date('d/m', strtotime($b['date_post'])) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="t-recent-thumb-result <?= $rk ?>"><?= $resultLabel[$rk] ?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="t-streak-zone">
        <div class="t-streak-lbl"><span>Form / 5 derniers</span><span>+<?= $wCount ?> / -<?= $lCount ?></span></div>
        <div class="t-streak-pills">
          <?php foreach ($form as $f): ?>
            <div class="s-pill <?= $f ?>" data-r="<?= $f === 'w' ? 'W' : ($f === 'l' ? 'L' : '') ?>"></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="t-cta-zone">
        <span class="t-cta">
          <span class="t-cta-text">Analyser ce tipster</span>
          <span>→</span>
        </span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php @require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
