<?php
// ============================================================
// STRATEDGE — Montante Tennis
// Progression de paris tennis avec suivi étape par étape
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$db = getDB();

$membre = isLoggedIn() ? getMembre() : null;
if (!$membre) {
    header('Location: /login.php?redirect=' . urlencode('/montante-tennis.php'));
    exit;
}
$currentPage = 'montante';
$avatarUrl = getAvatarUrl($membre);

// Auto-création des tables si absentes
try {
    $db->query("SELECT 1 FROM montante_config LIMIT 1");
} catch (Throwable $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'montante_config') !== false) {
        $db->exec("CREATE TABLE IF NOT EXISTS `montante_config` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `nom` VARCHAR(120) NOT NULL DEFAULT 'Montante Tennis',
          `bankroll_initial` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
          `mise_depart` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
          `statut` ENUM('active','pause','terminee') DEFAULT 'active',
          `date_debut` DATE DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `montante_steps` (
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

$config = $db->query("SELECT * FROM montante_config WHERE statut IN ('active','pause') ORDER BY id DESC LIMIT 1")->fetch();
if (!$config) {
    $config = ['id' => 0, 'nom' => 'Montante Tennis', 'bankroll_initial' => 100, 'mise_depart' => 10, 'statut' => 'inactive', 'date_debut' => null];
}

$steps = [];
if ($config['id'] > 0) {
    $stmtSteps = $db->prepare("SELECT * FROM montante_steps WHERE montante_id = ? ORDER BY step_number ASC");
    $stmtSteps->execute([$config['id']]);
    $steps = $stmtSteps->fetchAll();
}

// Montantes archivées (terminées)
$archives = $db->query("SELECT * FROM montante_config WHERE statut = 'terminee' ORDER BY id DESC")->fetchAll();
$archivesData = [];
foreach ($archives as $arc) {
    $arcSteps = $db->prepare("SELECT * FROM montante_steps WHERE montante_id = ? ORDER BY step_number ASC");
    $arcSteps->execute([$arc['id']]);
    $arcRows = $arcSteps->fetchAll();
    $g = 0; $p = 0; $br = (float)$arc['bankroll_initial'];
    foreach ($arcRows as $as) {
        if ($as['resultat'] === 'gagne') $g++;
        elseif ($as['resultat'] === 'perdu') $p++;
        if ($as['bankroll_apres'] !== null) $br = (float)$as['bankroll_apres'];
    }
    $profit = $br - (float)$arc['bankroll_initial'];
    $archivesData[] = ['config' => $arc, 'steps' => $arcRows, 'gagnes' => $g, 'perdus' => $p, 'profit' => $profit, 'bankroll_final' => $br];
}

$totalGagnes = 0;
$totalPerdus = 0;
$totalAnnules = 0;
$totalProfit = 0;
$currentBankroll = (float)$config['bankroll_initial'];
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

$totalProfit = $currentBankroll - (float)$config['bankroll_initial'];
$totalBets = $totalGagnes + $totalPerdus;
$winRate = $totalBets > 0 ? round($totalGagnes / $totalBets * 100) : 0;
$roi = (float)$config['bankroll_initial'] > 0 ? round($totalProfit / (float)$config['bankroll_initial'] * 100, 1) : 0;

$currentStep = null;
foreach ($steps as $s) {
    if ($s['resultat'] === 'en_cours') { $currentStep = $s; break; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Montante Tennis – StratEdge</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.mt-hero{text-align:center;padding:2rem 1rem 1.5rem;margin:-2.5rem -3rem 2rem;background:linear-gradient(180deg,rgba(0,212,106,0.06) 0%,transparent 100%);border-bottom:1px solid rgba(0,212,106,0.2);}
.mt-tag{font-family:'Space Mono',monospace;font-size:0.7rem;letter-spacing:3px;text-transform:uppercase;color:#00d46a;margin-bottom:0.5rem;}
.mt-title{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;margin-bottom:0.3rem;}
.mt-title span{background:linear-gradient(135deg,#00d46a,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.mt-sub{color:var(--txt2);font-size:0.95rem;}
.mt-status{display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.8rem;padding:0.35rem 1rem;border-radius:50px;font-family:'Orbitron',sans-serif;font-size:0.75rem;font-weight:700;letter-spacing:1px;}
.mt-status.active{background:rgba(0,212,106,0.1);border:1px solid rgba(0,212,106,0.3);color:#00d46a;}
.mt-status.pause{background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);color:#f59e0b;}
.mt-status .pulse-dot{width:8px;height:8px;border-radius:50%;animation:pulse-dot 1.5s ease-in-out infinite;}
.mt-status.active .pulse-dot{background:#00d46a;}
.mt-status.pause .pulse-dot{background:#f59e0b;}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.4);}}

.mt-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:2rem;}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-align:center;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;}
.stat-card.profit::before{background:linear-gradient(90deg,#00d46a,#00d4ff);}
.stat-card.winrate::before{background:linear-gradient(90deg,#a855f7,#ff2d78);}
.stat-card.streak::before{background:linear-gradient(90deg,#ffc107,#ff6b2b);}
.stat-card.bankroll::before{background:linear-gradient(90deg,#00d4ff,#a855f7);}
.stat-val{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;line-height:1;}
.stat-label{font-size:0.75rem;color:var(--txt3);text-transform:uppercase;letter-spacing:1px;margin-top:0.4rem;}
.profit-pos{color:#00d46a;}
.profit-neg{color:#ff4444;}

.mt-current{background:var(--card);border:1px solid rgba(0,212,106,0.25);border-radius:14px;padding:1.5rem;margin-bottom:2rem;position:relative;overflow:hidden;}
.mt-current::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#00d46a,#00d4ff);}
.mt-current-tag{font-family:'Space Mono',monospace;font-size:0.7rem;letter-spacing:2px;text-transform:uppercase;color:#00d46a;margin-bottom:0.8rem;}
.mt-current-match{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:0.4rem;}
.mt-current-meta{display:flex;flex-wrap:wrap;gap:1rem;color:var(--txt2);font-size:0.9rem;}
.mt-current-meta span{display:flex;align-items:center;gap:0.3rem;}
.mt-current-analyse{margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);color:var(--txt2);font-size:0.9rem;line-height:1.6;}

.mt-table-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.mt-table-title{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;padding:1rem 1.2rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.5rem;}
table.mt-table{width:100%;border-collapse:collapse;}
.mt-table th{font-family:'Space Mono',monospace;font-size:0.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);padding:0.7rem 0.8rem;text-align:left;border-bottom:1px solid var(--border);}
.mt-table td{padding:0.75rem 0.8rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.9rem;color:var(--txt2);}
.mt-table tr:last-child td{border-bottom:none;}
.mt-table .step-num{font-family:'Orbitron',sans-serif;font-weight:700;color:var(--txt);font-size:0.85rem;}
.res-badge{padding:0.2rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:700;}
.res-gagne{background:rgba(0,200,100,0.12);color:#00c864;border:1px solid rgba(0,200,100,0.3);}
.res-perdu{background:rgba(255,68,68,0.12);color:#ff4444;border:1px solid rgba(255,68,68,0.3);}
.res-annule{background:rgba(245,158,11,0.12);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);}
.res-encours{background:rgba(0,212,255,0.1);color:#00d4ff;border:1px solid rgba(0,212,255,0.25);}
.profit-cell{font-family:'Space Mono',monospace;font-weight:700;font-size:0.82rem;}

.mt-progress{margin-bottom:2rem;}
.progress-bar{height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;margin-top:0.5rem;}
.progress-fill{height:100%;border-radius:3px;transition:width .5s ease;}
.progress-label{display:flex;justify-content:space-between;font-size:0.78rem;color:var(--txt3);margin-bottom:0.3rem;}

.mt-empty{text-align:center;padding:4rem 2rem;color:var(--txt3);}
.mt-empty .big{font-size:3.5rem;margin-bottom:1rem;}
.mt-empty h3{font-family:'Orbitron',sans-serif;font-size:1.1rem;margin-bottom:0.5rem;color:var(--txt2);}

/* Stake banner */
.stake-banner{background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(0,212,106,0.05));border:1px solid rgba(0,212,255,0.2);border-radius:14px;padding:1.2rem 1.5rem;margin-bottom:2rem;display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;}
.stake-banner-icon{font-size:2rem;flex-shrink:0;}
.stake-banner-text{flex:1;min-width:200px;}
.stake-banner-text h3{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;color:#00d4ff;margin-bottom:0.3rem;}
.stake-banner-text p{font-size:0.88rem;color:var(--txt2);line-height:1.5;}
.stake-banner-text p strong{color:#00d4ff;}
.btn-stake-mt{display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,#00d4ff,#0089ff 55%,#00d46a);color:#fff;padding:0.7rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;white-space:nowrap;flex-shrink:0;}
.btn-stake-mt:hover{box-shadow:0 0 25px rgba(0,166,255,0.4);transform:translateY(-2px);}

/* Archives */
.mt-archives{margin-top:2.5rem;}
.mt-archives-title{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;color:var(--txt2);}
.archive-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.2rem 1.5rem;margin-bottom:1rem;position:relative;overflow:hidden;cursor:pointer;transition:all .3s;}
.archive-card:hover{border-color:rgba(255,255,255,0.15);}
.archive-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;}
.archive-card.won::before{background:linear-gradient(90deg,#00d46a,#00d4ff);}
.archive-card.lost::before{background:linear-gradient(90deg,#ff4444,#ff6b9d);}
.archive-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
.archive-name{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;}
.archive-meta{display:flex;gap:1rem;flex-wrap:wrap;font-size:0.82rem;color:var(--txt3);}
.archive-meta span{display:flex;align-items:center;gap:0.3rem;}
.archive-detail{max-height:0;overflow:hidden;transition:max-height .4s ease;}
.archive-card.open .archive-detail{max-height:2000px;}
.archive-toggle{font-size:0.75rem;color:var(--txt3);margin-top:0.5rem;text-align:center;}

@media(max-width:768px){
  .mt-hero{margin:-1rem -0.8rem 1.5rem;padding:1.5rem 0.8rem 1.2rem;}
  .mt-title{font-size:1.2rem;}
  .mt-stats{grid-template-columns:1fr 1fr;gap:0.7rem;}
  .stat-val{font-size:1.2rem;}
  .mt-current{padding:1rem;}
  .mt-table-wrap{overflow-x:auto;}
  .mt-table{min-width:600px;}
  .stake-banner{flex-direction:column;text-align:center;padding:1rem;}
  .btn-stake-mt{width:100%;justify-content:center;}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="mt-hero">
  <div class="mt-tag">🎾 Montante Tennis</div>
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

<!-- Bannière Stake -->
<div class="stake-banner">
  <div class="stake-banner-icon">🎾</div>
  <div class="stake-banner-text">
    <h3>Tous les matchs se jouent sur Stake</h3>
    <p>La montante est jouée exclusivement sur <strong>Stake.bet</strong> pour profiter des meilleures cotes tennis et des retraits instantanés en crypto. Crée ton compte avec notre lien partenaire pour un <strong>bonus exclusif StratEdge</strong>.</p>
  </div>
  <a href="https://stake.bet/?c=2bd992d384" target="_blank" rel="noopener noreferrer nofollow" class="btn-stake-mt">🎁 S'inscrire sur Stake</a>
</div>

<?php if ($config['id'] === 0 || empty($steps)): ?>
<div class="mt-empty">
  <div class="big">🎾</div>
  <h3>Aucune montante en cours</h3>
  <p>La prochaine montante Tennis sera lancée prochainement. Reste connecté !</p>
</div>
<?php else: ?>

<div class="mt-stats">
  <div class="stat-card bankroll">
    <div class="stat-val" style="color:#00d4ff;"><?= number_format($currentBankroll, 2, ',', ' ') ?>€</div>
    <div class="stat-label">Bankroll</div>
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
    <div class="progress-fill" style="width:<?= min(100, max(5, $winRate)) ?>%;background:linear-gradient(90deg,<?= $totalProfit >= 0 ? '#00d46a,#00d4ff' : '#ff4444,#ff6b9d' ?>);"></div>
  </div>
</div>
<?php endif; ?>

<?php if ($currentStep): ?>
<div class="mt-current">
  <div class="mt-current-tag">⚡ Étape en cours — Step <?= (int)$currentStep['step_number'] ?></div>
  <div class="mt-current-match"><?= clean($currentStep['match_desc']) ?></div>
  <div class="mt-current-meta">
    <?php if ($currentStep['competition']): ?><span>🏆 <?= clean($currentStep['competition']) ?></span><?php endif; ?>
    <?php if ($currentStep['date_match']): ?><span>📅 <?= date('d/m/Y', strtotime($currentStep['date_match'])) ?></span><?php endif; ?>
    <?php if ($currentStep['heure']): ?><span>🕐 <?= clean($currentStep['heure']) ?></span><?php endif; ?>
    <span>📊 Cote : <strong style="color:#00d4ff;"><?= number_format((float)$currentStep['cote'], 2) ?></strong></span>
    <span>💰 Mise : <strong style="color:#ffc107;"><?= number_format((float)$currentStep['mise'], 2) ?>€</strong></span>
  </div>
  <?php if (!empty($currentStep['analyse'])): ?>
  <div class="mt-current-analyse"><?= nl2br(clean($currentStep['analyse'])) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="mt-table-wrap">
  <div class="mt-table-title">📋 Historique des étapes</div>
  <table class="mt-table">
    <thead>
      <tr><th>#</th><th>Match</th><th>Compétition</th><th>Date</th><th>Cote</th><th>Mise</th><th>Résultat</th><th>+/-</th><th>Bankroll</th></tr>
    </thead>
    <tbody>
    <?php foreach (array_reverse($steps) as $s): ?>
      <tr>
        <td class="step-num"><?= (int)$s['step_number'] ?></td>
        <td><?= clean($s['match_desc']) ?></td>
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
            <tr><th>#</th><th>Match</th><th>Compétition</th><th>Date</th><th>Cote</th><th>Mise</th><th>Résultat</th><th>+/-</th><th>Bankroll</th></tr>
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
