<?php
// ============================================================
// STRATEDGE — Montante Foot
// Progression de paris foot avec suivi étape par étape
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$db = getDB();

$membre = isLoggedIn() ? getMembre() : null;
$currentPage = 'montante';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;

// Auto-création des tables si absentes
try {
    $db->query("SELECT 1 FROM montante_foot_config LIMIT 1");
} catch (Throwable $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'montante_foot_config') !== false) {
        $db->exec("CREATE TABLE IF NOT EXISTS `montante_foot_config` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `nom` VARCHAR(120) NOT NULL DEFAULT 'Montante Foot',
          `bankroll_initial` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
          `mise_depart` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
          `statut` ENUM('active','pause','terminee') DEFAULT 'active',
          `date_debut` DATE DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `montante_foot_steps` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `montante_id` INT UNSIGNED NOT NULL DEFAULT 1,
          `step_number` INT UNSIGNED NOT NULL,
          `match_desc` VARCHAR(255) NOT NULL,
          `competition` VARCHAR(120) DEFAULT NULL,
          `cote` DECIMAL(10,2) NOT NULL,
          `mise` DECIMAL(10,2) NOT NULL,
          `resultat` ENUM('en_cours','gagne','perdu','annule') DEFAULT 'en_cours',
          `gain_perte` DECIMAL(10,2) DEFAULT NULL,
          `bankroll_apres` DECIMAL(10,2) DEFAULT NULL,
          `date_match` DATE DEFAULT NULL,
          `heure` VARCHAR(20) DEFAULT NULL,
          `analyse` TEXT DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        throw $e;
    }
}

$config = $db->query("SELECT * FROM montante_foot_config WHERE statut IN ('active','pause') ORDER BY id DESC LIMIT 1")->fetch();
if (!$config) {
    $config = ['id' => 0, 'nom' => 'Montante Foot', 'bankroll_initial' => 100, 'mise_depart' => 10, 'statut' => 'inactive', 'date_debut' => null];
}

$steps = [];
if ($config['id'] > 0) {
    $stmtSteps = $db->prepare("SELECT * FROM montante_foot_steps WHERE montante_id = ? ORDER BY step_number ASC");
    $stmtSteps->execute([$config['id']]);
    $steps = $stmtSteps->fetchAll();
}

// Montantes archivées (terminées)
$archives = $db->query("SELECT * FROM montante_foot_config WHERE statut = 'terminee' ORDER BY id DESC")->fetchAll();
$archivesData = [];
foreach ($archives as $arc) {
    $arcSteps = $db->prepare("SELECT * FROM montante_foot_steps WHERE montante_id = ? ORDER BY step_number ASC");
    $arcSteps->execute([$arc['id']]);
    $arcRows = $arcSteps->fetchAll();
    $g = 0; $p = 0; $br = (float)$arc['mise_depart'];
    foreach ($arcRows as $as) {
        if ($as['resultat'] === 'gagne') $g++;
        elseif ($as['resultat'] === 'perdu') $p++;
        if ($as['bankroll_apres'] !== null) $br = (float)$as['bankroll_apres'];
    }
    $profit = $br - (float)$arc['mise_depart'];
    $archivesData[] = ['config' => $arc, 'steps' => $arcRows, 'gagnes' => $g, 'perdus' => $p, 'profit' => $profit, 'bankroll_final' => $br];
}

$totalGagnes = 0;
$totalPerdus = 0;
$totalAnnules = 0;
$totalProfit = 0;
// Capital de départ = mise_depart (ex: 10€), pas l'objectif
$currentBankroll = (float)$config['mise_depart'];
$streak = 0;
$bestStreak = 0;

foreach ($steps as &$s) {
    if ($s['resultat'] === 'gagne') {
        $totalGagnes++;
        $streak++;
        $bestStreak = max($bestStreak, $streak);
    } elseif ($s['resultat'] === 'perdu') {
        $totalPerdus++;
        $streak = 0;
    } elseif ($s['resultat'] === 'annule') {
        $totalAnnules++;
    }
    if ($s['bankroll_apres'] !== null) {
        $currentBankroll = (float)$s['bankroll_apres'];
    }
}
unset($s);

$totalProfit = $currentBankroll - (float)$config['mise_depart'];
$totalBets = $totalGagnes + $totalPerdus;
$winRate = $totalBets > 0 ? round($totalGagnes / $totalBets * 100) : 0;
// ROI calculé sur la mise de départ (le vrai capital engagé)
$roi = (float)$config['mise_depart'] > 0 ? round($totalProfit / (float)$config['mise_depart'] * 100, 1) : 0;

$currentStep = null;
foreach ($steps as $s) {
    if ($s['resultat'] === 'en_cours') { $currentStep = $s; break; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Montante Foot – StratEdge</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.mt-hero{text-align:center;padding:2rem 1rem 1.5rem;margin:-2.5rem -3rem 2rem;background:linear-gradient(180deg,rgba(255,45,120,0.06) 0%,transparent 100%);border-bottom:1px solid rgba(255,45,120,0.2);}
.mt-tag{font-family:'Space Mono',monospace;font-size:0.7rem;letter-spacing:3px;text-transform:uppercase;color:#c850c0;margin-bottom:0.5rem;}
.mt-title{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;margin-bottom:0.3rem;}
.mt-title span{background:linear-gradient(135deg,#c850c0,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.mt-sub{color:var(--txt2);font-size:0.95rem;}
.mt-status{display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.8rem;padding:0.35rem 1rem;border-radius:50px;font-family:'Orbitron',sans-serif;font-size:0.75rem;font-weight:700;letter-spacing:1px;}
.mt-status.active{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.3);color:#c850c0;}
.mt-status.pause{background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);color:#f59e0b;}
.mt-status .pulse-dot{width:8px;height:8px;border-radius:50%;animation:pulse-dot 1.5s ease-in-out infinite;}
.mt-status.active .pulse-dot{background:#c850c0;}
.mt-status.pause .pulse-dot{background:#f59e0b;}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.4);}}

.mt-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:2rem;}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-align:center;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;}
.stat-card.profit::before{background:linear-gradient(90deg,#c850c0,#00d4ff);}
.stat-card.winrate::before{background:linear-gradient(90deg,#a855f7,#ff2d78);}
.stat-card.streak::before{background:linear-gradient(90deg,#ffc107,#ff6b2b);}
.stat-card.bankroll::before{background:linear-gradient(90deg,#00d4ff,#a855f7);}
.stat-val{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;line-height:1;}
.stat-label{font-size:0.75rem;color:var(--txt3);text-transform:uppercase;letter-spacing:1px;margin-top:0.4rem;}
.profit-pos{color:#c850c0;}
.profit-neg{color:#ff4444;}

.mt-current{background:var(--card, #0d1117);border:1px solid rgba(255,45,120,0.35);border-radius:14px;padding:1.5rem 1.5rem 1.8rem;margin-bottom:2rem;position:relative;overflow:visible;z-index:2;box-shadow:0 0 20px rgba(255,45,120,0.08);}
.mt-current::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#c850c0,#00d4ff);border-radius:14px 14px 0 0;}
.mt-current-tag{font-family:'Space Mono',monospace;font-size:0.75rem;letter-spacing:2px;text-transform:uppercase;color:#c850c0;margin-bottom:1rem;display:block;}
.mt-current-match{font-family:'Orbitron',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:0.8rem;min-height:1.6em;color:#fff;word-break:break-word;}

.mt-current-prono{
  display:inline-flex;align-items:center;gap:12px;
  margin-bottom:1rem;padding:10px 18px;
  background:linear-gradient(135deg,rgba(255,45,120,0.12),rgba(255,45,120,0.04));
  border:1px solid rgba(255,45,120,0.4);
  border-radius:10px;
  box-shadow:0 0 18px rgba(255,45,120,0.2),0 0 4px rgba(255,45,120,0.2) inset;
  position:relative;overflow:hidden;
  animation:pronoGlow 2.5s ease-in-out infinite;
}
.mt-current-prono::before{
  content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,45,120,0.2),transparent);
  animation:pronoShine 3.5s ease-in-out infinite;
}
@keyframes pronoGlow{
  0%,100%{box-shadow:0 0 14px rgba(255,45,120,0.2),0 0 4px rgba(255,45,120,0.18) inset;border-color:rgba(255,45,120,0.4);}
  50%{box-shadow:0 0 22px rgba(255,45,120,0.45),0 0 30px rgba(255,45,120,0.15),0 0 5px rgba(255,45,120,0.25) inset;border-color:rgba(255,45,120,0.7);}
}
@keyframes pronoShine{
  0%,100%{left:-100%;}
  50%{left:150%;}
}
.mt-prono-label{font-family:'Orbitron',sans-serif;font-size:0.72rem;letter-spacing:2.5px;color:rgba(255,45,120,0.75);text-transform:uppercase;font-weight:700;position:relative;z-index:2;}
.mt-prono-value{font-family:'Rajdhani',sans-serif;font-weight:700;font-size:1.05rem;color:#ff2d78;text-shadow:0 0 10px rgba(255,45,120,0.6);position:relative;z-index:2;}

.mt-current-meta{display:flex;flex-wrap:wrap;gap:1.2rem;color:var(--txt2);font-size:0.95rem;padding:0.8rem 0;background:rgba(255,255,255,0.02);border-radius:8px;padding:0.8rem;}
.mt-current-meta span{display:flex;align-items:center;gap:0.4rem;}
.mt-current-analyse{margin-top:1rem;padding:1rem;border-top:1px solid var(--border);color:var(--txt2);font-size:0.95rem;line-height:1.7;background:rgba(255,45,120,0.03);border-radius:0 0 10px 10px;}

.mt-table-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.mt-table-title{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;padding:1rem 1.2rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.5rem;}
table.mt-table{width:100%;border-collapse:collapse;}
.mt-table th{font-family:'Space Mono',monospace;font-size:0.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);padding:0.7rem 0.8rem;text-align:left;border-bottom:1px solid var(--border);}
.mt-table td{padding:0.75rem 0.8rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.9rem;color:var(--txt2);}
.mt-table tr:last-child td{border-bottom:none;}
.mt-table .step-num{font-family:'Orbitron',sans-serif;font-weight:700;color:var(--txt);font-size:0.85rem;}
.res-badge{padding:0.3rem 0.7rem;border-radius:8px;font-size:0.75rem;font-weight:700;position:relative;display:inline-block;}

/* VICTOIRE — vert néon scintillant */
.res-gagne{
  background:linear-gradient(135deg,rgba(255,45,120,0.18),rgba(255,45,120,0.08));
  color:#ff2d78;
  border:1px solid rgba(255,45,120,0.55);
  text-shadow:0 0 8px rgba(255,45,120,0.7);
  box-shadow:0 0 14px rgba(255,45,120,0.35),0 0 4px rgba(255,45,120,0.3) inset;
  animation:winGlow 2.2s ease-in-out infinite;
  overflow:hidden;
}
.res-gagne::before{
  content:'';position:absolute;top:0;left:-100%;
  width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,45,120,0.35),transparent);
  animation:winShine 3s ease-in-out infinite;
}
@keyframes winGlow{
  0%,100%{box-shadow:0 0 12px rgba(255,45,120,0.3),0 0 4px rgba(255,45,120,0.25) inset;border-color:rgba(255,45,120,0.5);}
  50%{box-shadow:0 0 22px rgba(255,45,120,0.65),0 0 30px rgba(255,45,120,0.25),0 0 6px rgba(255,45,120,0.4) inset;border-color:rgba(255,45,120,0.9);}
}
@keyframes winShine{
  0%,100%{left:-100%;}
  50%{left:140%;}
}

/* DEFAITE — rouge plus sobre, sans animation */
.res-perdu{
  background:rgba(255,68,68,0.12);
  color:#ff6b6b;
  border:1px solid rgba(255,68,68,0.3);
  opacity:0.85;
}

.res-annule{background:rgba(245,158,11,0.12);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);}

/* EN COURS — cyan pulse subtil */
.res-encours{
  background:rgba(0,212,255,0.1);
  color:#00d4ff;
  border:1px solid rgba(0,212,255,0.35);
  animation:pendingPulse 2s ease-in-out infinite;
}
@keyframes pendingPulse{
  0%,100%{box-shadow:0 0 8px rgba(0,212,255,0.2);border-color:rgba(0,212,255,0.3);}
  50%{box-shadow:0 0 14px rgba(0,212,255,0.45);border-color:rgba(0,212,255,0.6);}
}

/* Profit cell — vert qui brille quand positif */
.profit-cell{font-family:'Space Mono',monospace;font-weight:700;font-size:0.82rem;}
.profit-cell.profit-win{color:#ff2d78;text-shadow:0 0 8px rgba(255,45,120,0.4);}
.profit-cell.profit-lose{color:#ff6b6b;}

.mt-progress{margin-bottom:2rem;}
.progress-bar{height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;margin-top:0.5rem;}
.progress-fill{height:100%;border-radius:3px;transition:width .5s ease;}
.progress-label{display:flex;justify-content:space-between;font-size:0.78rem;color:var(--txt3);margin-bottom:0.3rem;}

.mt-empty{text-align:center;padding:4rem 2rem;color:var(--txt3);}
.mt-empty .big{font-size:3.5rem;margin-bottom:1rem;}
.mt-empty h3{font-family:'Orbitron',sans-serif;font-size:1.1rem;margin-bottom:0.5rem;color:var(--txt2);}

/* Visuel pleine largeur au-dessus des bannières (échappe au padding du content) */
.mt-promo-visual{margin-left:-3rem;margin-right:-3rem;width:calc(100% + 6rem);margin-bottom:1.5rem;padding:1.75rem 2rem;background:linear-gradient(135deg,rgba(0,212,255,0.07) 0%,rgba(255,45,120,0.05) 50%,rgba(255,45,120,0.07) 100%);border-top:1px solid rgba(255,255,255,0.06);border-bottom:1px solid rgba(255,255,255,0.06);position:relative;overflow:hidden;}
.app .content > .mt-promo-visual{margin-left:calc(-3rem - var(--sidebar-w,270px));width:calc(100% + 6rem + var(--sidebar-w,270px));}
.mt-promo-visual::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(0,212,255,0.04),transparent);animation:mt-shine 6s ease-in-out infinite;}
@keyframes mt-shine{0%,100%{opacity:0}50%{opacity:1}}
.mt-promo-visual-inner{position:relative;z-index:1;text-align:center;}
.mt-promo-visual .mt-promo-tag{font-family:'Orbitron',sans-serif;font-size:0.7rem;font-weight:700;letter-spacing:3px;color:var(--txt3);margin-bottom:0.4rem;}
.mt-promo-visual .mt-promo-title{font-family:'Orbitron',sans-serif;font-size:1.15rem;font-weight:800;background:linear-gradient(90deg,#00d4ff,#ff2d78,#c850c0);background-size:200% auto;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
/* Stake + Packs : 3 colonnes égales */
.stake-promo-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:2rem;}
.stake-banner,.pack-banner{border-radius:14px;padding:1.2rem 1.2rem;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;min-height:220px;}
.stake-banner{background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(255,45,120,0.05));border:1px solid rgba(0,212,255,0.2);}
.stake-banner-icon{font-size:2rem;flex-shrink:0;}
.stake-banner-text{flex:1;min-width:0;}
.stake-banner-text h3{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;color:#00d4ff;margin-bottom:0.3rem;}
.stake-banner-text p{font-size:0.82rem;color:var(--txt2);line-height:1.45;}
.stake-banner-text p strong{color:#00d4ff;}
.btn-stake-mt{display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;background:linear-gradient(135deg,#00d4ff,#0089ff 55%,#c850c0);color:#fff;padding:0.7rem 1rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;white-space:nowrap;}
.btn-stake-mt:hover{box-shadow:0 0 25px rgba(0,166,255,0.4);transform:translateY(-2px);}
.pack-banner{border-radius:14px;}
.pack-banner.vip-max{background:linear-gradient(135deg,rgba(245,200,66,0.14),rgba(200,150,12,0.08));border:1px solid rgba(245,200,66,0.4);}
.pack-banner.vip-max h4{color:#f5c842;font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;}
.pack-banner.vip-max p{font-size:0.82rem;color:var(--txt2);margin:0;}
.pack-banner.multi-pack{background:linear-gradient(135deg,rgba(255,45,120,0.12),rgba(0,212,255,0.08));border:1px solid rgba(255,45,120,0.4);}
.pack-banner.multi-pack h4{color:#c850c0;font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;}
.pack-banner.multi-pack p{font-size:0.82rem;color:var(--txt2);margin:0;}
.mt-pack-mascot{width:80px;height:80px;margin:0 auto 0.75rem;border-radius:50%;overflow:hidden;flex-shrink:0;}
.mt-pack-mascot video{width:100%;height:100%;object-fit:cover;}
.pack-banner.vip-max .mt-pack-mascot{border:2px solid rgba(245,200,66,0.5);box-shadow:0 0 20px rgba(245,200,66,0.25);}
.pack-banner.multi-pack .mt-pack-mascot{border:2px solid rgba(255,45,120,0.5);box-shadow:0 0 20px rgba(255,45,120,0.25);}
.btn-pack{display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;padding:0.55rem 1rem;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;transition:all .25s;white-space:nowrap;}
.pack-banner.vip-max .btn-pack{background:linear-gradient(135deg,#f5c842,#c8960c);color:#050810;}
.pack-banner.vip-max .btn-pack:hover{box-shadow:0 0 20px rgba(245,200,66,0.5);transform:translateY(-1px);}
.pack-banner.multi-pack .btn-pack{background:linear-gradient(135deg,#c850c0,#00a050);color:#fff;}
.pack-banner.multi-pack .btn-pack:hover{box-shadow:0 0 20px rgba(255,45,120,0.5);transform:translateY(-1px);}

/* Archives */
.mt-archives{margin-top:2.5rem;}
.mt-archives-title{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;color:var(--txt2);}
.archive-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.2rem 1.5rem;margin-bottom:1rem;position:relative;overflow:hidden;cursor:pointer;transition:all .3s;}
.archive-card:hover{border-color:rgba(255,255,255,0.15);}
.archive-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;}
.archive-card.won::before{background:linear-gradient(90deg,#c850c0,#00d4ff);}
.archive-card.lost::before{background:linear-gradient(90deg,#ff4444,#ff6b9d);}
.archive-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
.archive-name{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;}
.archive-meta{display:flex;gap:1rem;flex-wrap:wrap;font-size:0.82rem;color:var(--txt3);}
.archive-meta span{display:flex;align-items:center;gap:0.3rem;}
.archive-detail{max-height:0;overflow:hidden;transition:max-height .4s ease;}
.archive-card.open .archive-detail{max-height:2000px;}
.archive-toggle{font-size:0.75rem;color:var(--txt3);margin-top:0.5rem;text-align:center;}

/* Sur mobile : montante en cours ou message "aucune montante" en premier */
@media(max-width:768px){
  .mt-mobile-flex{display:flex;flex-direction:column;}
  .mt-mobile-flex .mt-empty,
  .mt-mobile-flex .mt-current{order:-2;}
  .mt-mobile-flex .mt-promo-visual{order:-1;}
  .mt-mobile-flex .stake-promo-row{order:0;}
  .mt-mobile-flex .mt-stats{order:1;}
  .mt-mobile-flex .mt-progress{order:2;}
  .mt-mobile-flex .mt-table-wrap{order:3;}
}
@media(max-width:768px){
  .mt-hero{margin:-1rem -0.8rem 1.5rem;padding:1.5rem 0.8rem 1.2rem;}
  .mt-title{font-size:1.2rem;}
  .mt-promo-visual,.app .content > .mt-promo-visual{margin-left:-0.8rem;margin-right:-0.8rem;width:calc(100% + 1.6rem);padding:1.25rem 1rem;}
  .mt-promo-visual .mt-promo-title{font-size:0.95rem;}
  .mt-stats{grid-template-columns:1fr 1fr;gap:0.7rem;}
  .stat-val{font-size:1.2rem;}
  .mt-current{padding:1rem;}
  .mt-table-wrap{overflow-x:auto;}
  .mt-table{min-width:600px;}
  .stake-promo-row{grid-template-columns:1fr;}
  .stake-banner,.pack-banner{min-height:auto;}
  .stake-banner{text-align:center;}
  .btn-stake-mt{width:100%;justify-content:center;}
  .mt-pack-mascot{width:64px;height:64px;}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="mt-hero">
  <div class="mt-tag">⚽ Montante Foot</div>
  <h1 class="mt-title"><span><?= clean($config['nom']) ?></span></h1>
  <p class="mt-sub">Suivi en direct de la progression — chaque étape, chaque mise, chaque résultat.</p>
  <?php if ($config['id'] > 0): ?>
  <div class="mt-status <?= $config['statut'] ?>">
    <span class="pulse-dot"></span>
    <?= $config['statut'] === 'active' ? 'En cours' : ($config['statut'] === 'pause' ? 'En pause' : 'Terminée') ?>
    <?php if ($config['date_debut']): ?> · Depuis le <?= date('d/m/Y', strtotime($config['date_debut'])) ?><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="mt-mobile-flex">
<!-- Visuel pleine largeur -->
<div class="mt-promo-visual">
  <div class="mt-promo-visual-inner">
    <div class="mt-promo-tag">PRONOS ANALYSÉS AVEC MINUTIE</div>
    <div class="mt-promo-title">Stake · VIP Max · Multi Pack — Rejoins l'Edge</div>
  </div>
</div>

<!-- 3 bannières : Stake, VIP Max, Multi Pack (même taille) -->
<div class="stake-promo-row">
  <div class="stake-banner">
    <div class="stake-banner-icon">⚽</div>
    <div class="stake-banner-text">
      <h3>Tous les matchs se jouent sur Stake</h3>
      <p>La montante est jouée exclusivement sur <strong>Stake.bet</strong> pour profiter des meilleures cotes foot et des retraits instantanés en crypto. Crée ton compte avec notre lien partenaire pour un <strong>bonus exclusif StratEdge</strong>.</p>
    </div>
    <a href="https://stake.bet/fr?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="btn-stake-mt">🎁 S'inscrire sur Stake</a>
  </div>
  <div class="pack-banner vip-max">
    <div class="mt-pack-mascot">
      <video autoplay loop muted playsinline>
        <source src="assets/images/vip_max.mp4" type="video/mp4">
      </video>
    </div>
    <div>
      <h4>👑 VIP Max</h4>
      <p>1 mois — 50€<br>Tous les pronos + accès complet</p>
    </div>
    <a href="/offre.php?type=vip_max" class="btn-pack">Voir l'offre</a>
  </div>
  <div class="pack-banner multi-pack">
    <div class="mt-pack-mascot">
      <img src="/assets/images/mascotte-rose.png" alt="Multi Mascot" style="width:100%;height:100%;object-fit:contain;">
    </div>
    <div>
      <h4>⚽ Multi Pack</h4>
      <p>À la carte · Dès 4,50€<br>Pronos foot à la demande</p>
    </div>
    <a href="/packs-daily.php" class="btn-pack">Voir les packs</a>
  </div>
</div>

<?php if ($config['id'] === 0 || empty($steps)): ?>
<div class="mt-empty">
  <div class="big">⚽</div>
  <h3>Aucune montante en cours</h3>
  <p>La prochaine montante Foot sera lancée prochainement. Reste connecté !</p>
</div>
<?php else: ?>

<div class="mt-stats">
  <div class="stat-card bankroll">
    <div class="stat-val" style="color:#00d4ff;"><?= number_format($currentBankroll, 2, ',', ' ') ?>€ <span style="font-size:0.5em;color:rgba(255,255,255,0.5);font-weight:400;">/ <?= number_format((float)$config['bankroll_initial'], 0, ',', ' ') ?>€</span></div>
    <div class="stat-label">Objectif</div>
  </div>
  <div class="stat-card profit">
    <div class="stat-val <?= $totalProfit >= 0 ? 'profit-pos' : 'profit-neg' ?>"><?= ($totalProfit >= 0 ? '+' : '') . number_format($totalProfit, 2, ',', ' ') ?>€</div>
    <div class="stat-label">Profit / Perte</div>
  </div>
  <div class="stat-card winrate">
    <div class="stat-val" style="color:#a855f7;"><?= $winRate ?>%</div>
    <div class="stat-label">Win Rate (<?= $totalGagnes ?>W / <?= $totalPerdus ?>L)</div>
  </div>
  <div class="stat-card streak">
    <div class="stat-val" style="color:#ffc107;"><?= $bestStreak ?></div>
    <div class="stat-label">Best Streak 🔥</div>
  </div>
</div>

<?php if ($totalBets > 0): ?>
<div class="mt-progress">
  <div class="progress-label">
    <span>ROI : <?= ($roi >= 0 ? '+' : '') . $roi ?>%</span>
    <span><?= count($steps) ?> étape<?= count($steps) > 1 ? 's' : '' ?></span>
  </div>
  <div class="progress-bar">
    <div class="progress-fill" style="width:<?= min(100, max(5, $winRate)) ?>%;background:linear-gradient(90deg,<?= $totalProfit >= 0 ? '#c850c0,#00d4ff' : '#ff4444,#ff6b9d' ?>);"></div>
  </div>
</div>
<?php endif; ?>

<?php if ($currentStep): ?>
<div class="mt-current">
  <div class="mt-current-tag">⚡ Étape en cours — Step <?= (int)$currentStep['step_number'] ?></div>
  <div class="mt-current-match"><?= trim(clean($currentStep['match_desc'] ?? '')) !== '' ? clean($currentStep['match_desc']) : 'Prono Step ' . (int)$currentStep['step_number'] . ' — Détails à compléter' ?></div>
  <?php if (!empty(trim($currentStep['pronostic'] ?? ''))): ?>
  <div class="mt-current-prono">
    <span class="mt-prono-label">🎯 Pronostic</span>
    <span class="mt-prono-value"><?= clean($currentStep['pronostic']) ?></span>
  </div>
  <?php endif; ?>
  <div class="mt-current-meta">
    <?php if (!empty(trim($currentStep['competition'] ?? ''))): ?><span>🏆 <?= clean($currentStep['competition']) ?></span><?php endif; ?>
    <?php if (!empty($currentStep['date_match'])): ?><span>📅 <?= date('d/m/Y', strtotime($currentStep['date_match'])) ?></span><?php endif; ?>
    <?php if (!empty(trim($currentStep['heure'] ?? ''))): ?><span>🕐 <?= clean($currentStep['heure']) ?></span><?php endif; ?>
    <span>📊 Cote : <strong style="color:#00d4ff;"><?= (float)($currentStep['cote'] ?? 0) > 0 ? number_format((float)$currentStep['cote'], 2) : '—' ?></strong></span>
    <span>💰 Mise : <strong style="color:#ffc107;"><?= (float)($currentStep['mise'] ?? 0) > 0 ? number_format((float)$currentStep['mise'], 2) . '€' : '—' ?></strong></span>
  </div>
  <?php if (!empty(trim($currentStep['analyse'] ?? ''))): ?>
  <div class="mt-current-analyse"><?= nl2br(clean($currentStep['analyse'])) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="mt-table-wrap">
  <div class="mt-table-title">📋 Historique des étapes</div>
  <table class="mt-table">
    <thead>
      <tr><th>#</th><th>Match</th><th>🎯 Pronostic</th><th>Compétition</th><th>Date</th><th>Cote</th><th>Mise</th><th>Résultat</th><th>+/-</th><th>Bankroll</th></tr>
    </thead>
    <tbody>
    <?php foreach (array_reverse($steps) as $s): ?>
      <tr>
        <td class="step-num"><?= (int)$s['step_number'] ?></td>
        <td><?= clean($s['match_desc']) ?></td>
        <td style="color:#ff2d78;font-weight:700;"><?= !empty(trim($s['pronostic'] ?? '')) ? clean($s['pronostic']) : '—' ?></td>
        <td><?= clean($s['competition'] ?? '—') ?></td>
        <td style="white-space:nowrap;"><?= $s['date_match'] ? date('d/m', strtotime($s['date_match'])) : '—' ?><?= $s['heure'] ? ' ' . clean($s['heure']) : '' ?></td>
        <td style="color:#00d4ff;font-weight:600;"><?= number_format((float)$s['cote'], 2) ?></td>
        <td style="font-weight:600;"><?= number_format((float)$s['mise'], 2) ?>€</td>
        <td>
          <?php
          $resClass = ['gagne'=>'res-gagne','perdu'=>'res-perdu','annule'=>'res-annule','en_cours'=>'res-encours'];
          $resLabel = ['gagne'=>'✅ Gagné','perdu'=>'❌ Perdu','annule'=>'↺ Annulé','en_cours'=>'⏳ En cours'];
          $r = $s['resultat'] ?? 'en_cours';
          ?>
          <span class="res-badge <?= $resClass[$r] ?? 'res-encours' ?>"><?= $resLabel[$r] ?? $r ?></span>
        </td>
        <td class="profit-cell <?= ($s['gain_perte'] ?? 0) >= 0 ? 'profit-pos' : 'profit-neg' ?>">
          <?php if ($s['gain_perte'] !== null): ?><?= ($s['gain_perte'] >= 0 ? '+' : '') . number_format((float)$s['gain_perte'], 2) ?>€<?php else: ?>—<?php endif; ?>
        </td>
        <td style="font-weight:600;"><?= $s['bankroll_apres'] !== null ? number_format((float)$s['bankroll_apres'], 2) . '€' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>
</div>

<?php if (!empty($archivesData)): ?>
<div class="mt-archives">
  <div class="mt-archives-title">📂 Montantes précédentes (<?= count($archivesData) ?>)</div>
  <?php foreach ($archivesData as $arc):
    $isWon = $arc['profit'] >= 0;
    $arcConfig = $arc['config'];
  ?>
  <div class="archive-card <?= $isWon ? 'won' : 'lost' ?>" onclick="this.classList.toggle('open')">
    <div class="archive-header">
      <div class="archive-name"><?= clean($arcConfig['nom']) ?></div>
      <div class="archive-meta">
        <?php if ($arcConfig['date_debut']): ?><span>📅 <?= date('d/m/Y', strtotime($arcConfig['date_debut'])) ?></span><?php endif; ?>
        <span>📊 <?= $arc['gagnes'] ?>W / <?= $arc['perdus'] ?>L</span>
        <span class="<?= $isWon ? 'profit-pos' : 'profit-neg' ?>" style="font-weight:700;">
          <?= ($arc['profit'] >= 0 ? '+' : '') . number_format($arc['profit'], 2, ',', ' ') ?>€
        </span>
        <span>🏦 <?= number_format($arc['bankroll_final'], 2, ',', ' ') ?>€</span>
      </div>
    </div>
    <div class="archive-toggle">▼ Cliquer pour voir le détail</div>
    <div class="archive-detail">
      <?php if (!empty($arc['steps'])): ?>
      <div class="mt-table-wrap" style="margin-top:1rem;">
        <table class="mt-table">
          <thead>
            <tr><th>#</th><th>Match</th><th>🎯 Pronostic</th><th>Compétition</th><th>Date</th><th>Cote</th><th>Mise</th><th>Résultat</th><th>+/-</th><th>Bankroll</th></tr>
          </thead>
          <tbody>
          <?php foreach ($arc['steps'] as $as):
            $r = $as['resultat'] ?? 'en_cours';
            $resClass = ['gagne'=>'res-gagne','perdu'=>'res-perdu','annule'=>'res-annule','en_cours'=>'res-encours'];
            $resLabel = ['gagne'=>'✅ Gagné','perdu'=>'❌ Perdu','annule'=>'↺ Annulé','en_cours'=>'⏳ En cours'];
          ?>
            <tr>
              <td class="step-num"><?= (int)$as['step_number'] ?></td>
              <td><?= clean($as['match_desc']) ?></td>
              <td style="color:#ff2d78;font-weight:700;"><?= !empty(trim($as['pronostic'] ?? '')) ? clean($as['pronostic']) : '—' ?></td>
              <td><?= clean($as['competition'] ?? '—') ?></td>
              <td style="white-space:nowrap;"><?= $as['date_match'] ? date('d/m', strtotime($as['date_match'])) : '—' ?></td>
              <td style="color:#00d4ff;font-weight:600;"><?= number_format((float)$as['cote'], 2) ?></td>
              <td style="font-weight:600;"><?= number_format((float)$as['mise'], 2) ?>€</td>
              <td><span class="res-badge <?= $resClass[$r] ?? 'res-encours' ?>"><?= $resLabel[$r] ?? $r ?></span></td>
              <td class="profit-cell <?= ($as['gain_perte'] ?? 0) >= 0 ? 'profit-pos' : 'profit-neg' ?>">
                <?= $as['gain_perte'] !== null ? (($as['gain_perte'] >= 0 ? '+' : '') . number_format((float)$as['gain_perte'], 2) . '€') : '—' ?>
              </td>
              <td style="font-weight:600;"><?= $as['bankroll_apres'] !== null ? number_format((float)$as['bankroll_apres'], 2) . '€' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</main></div>
</body>
</html>
