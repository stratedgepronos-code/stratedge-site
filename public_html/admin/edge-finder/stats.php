<?php
/**
 * StratEdge Edge Finder — Page Stats Analytics
 * URL : /panel-x9k3m/edge-finder/stats.php
 *
 * Analyse ROI par marché, ligue, cote, conviction, EV.
 * Permet d'identifier les marchés gagnants/perdants pour ajuster
 * la stratégie de filtrage.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

// =============================================================================
// Filtres GET
// =============================================================================
$filter_period = $_GET['period'] ?? 'all';  // all, last30, last90
$filter_status = $_GET['status'] ?? '';     // auto, manual

// Construction du WHERE selon la période
$date_filter = '';
$params = [];
if ($filter_period === 'last30') {
    $date_filter = ' AND c.decision_at >= NOW() - INTERVAL 30 DAY';
} elseif ($filter_period === 'last90') {
    $date_filter = ' AND c.decision_at >= NOW() - INTERVAL 90 DAY';
}

$status_filter = '';
if ($filter_status === 'auto' || $filter_status === 'manual') {
    $status_filter = ' AND c.status = ?';
    $params[] = $filter_status;
}

// =============================================================================
// Stats globales — résultats résolus uniquement
// =============================================================================
$global = SE_Db::queryOne(
    "SELECT
        COUNT(*) AS n_settled,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        SUM(CASE WHEN c.user_decision = 'void' THEN 1 ELSE 0 END) AS n_void,
        SUM(CASE WHEN c.user_decision = 'won'  THEN c.odds - 1 ELSE 0 END) -
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1         ELSE 0 END) AS net_units,
        AVG(c.odds) AS avg_odds,
        AVG(c.ev)   AS avg_ev,
        AVG(c.conviction) AS avg_conv
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('won','lost','void')" . $date_filter . $status_filter,
    $params
);

$total_decided = (int)($global['n_won'] ?? 0) + (int)($global['n_lost'] ?? 0);
$win_rate = $total_decided > 0
    ? 100 * (int)$global['n_won'] / $total_decided
    : 0;
$roi = $total_decided > 0
    ? 100 * (float)$global['net_units'] / $total_decided
    : 0;

// =============================================================================
// ROI par marché
// =============================================================================
$by_market = SE_Db::queryAll(
    "SELECT
        c.market,
        c.market_group,
        COUNT(*) AS n,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        SUM(CASE WHEN c.user_decision = 'won'  THEN c.odds - 1 ELSE 0 END) -
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1         ELSE 0 END) AS net_units,
        AVG(c.odds) AS avg_odds,
        AVG(c.ev)   AS avg_ev
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('won','lost')" . $date_filter . $status_filter . "
     GROUP BY c.market, c.market_group
     ORDER BY net_units DESC",
    $params
);

// =============================================================================
// ROI par ligue
// =============================================================================
$by_league = SE_Db::queryAll(
    "SELECT
        m.league_name,
        m.league_tier,
        COUNT(*) AS n,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        SUM(CASE WHEN c.user_decision = 'won'  THEN c.odds - 1 ELSE 0 END) -
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1         ELSE 0 END) AS net_units
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('won','lost')" . $date_filter . $status_filter . "
     GROUP BY m.league_name, m.league_tier
     ORDER BY net_units DESC",
    $params
);

// =============================================================================
// ROI par range de cote
// =============================================================================
$by_odds_range = SE_Db::queryAll(
    "SELECT
        CASE
            WHEN c.odds < 1.80 THEN '1.70 - 1.79'
            WHEN c.odds < 2.00 THEN '1.80 - 1.99'
            WHEN c.odds < 2.50 THEN '2.00 - 2.49'
            WHEN c.odds < 3.00 THEN '2.50 - 2.99'
            WHEN c.odds < 4.00 THEN '3.00 - 3.99'
            WHEN c.odds < 6.00 THEN '4.00 - 5.99'
            ELSE                       '6.00 +'
        END AS odds_bucket,
        COUNT(*) AS n,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        SUM(CASE WHEN c.user_decision = 'won'  THEN c.odds - 1 ELSE 0 END) -
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1         ELSE 0 END) AS net_units,
        AVG(c.odds) AS avg_odds
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('won','lost')" . $date_filter . $status_filter . "
     GROUP BY odds_bucket
     ORDER BY MIN(c.odds)",
    $params
);

// =============================================================================
// ROI par bucket de conviction
// =============================================================================
$by_conv = SE_Db::queryAll(
    "SELECT
        CASE
            WHEN c.conviction < 50 THEN '0-49'
            WHEN c.conviction < 60 THEN '50-59'
            WHEN c.conviction < 70 THEN '60-69'
            WHEN c.conviction < 80 THEN '70-79'
            WHEN c.conviction < 90 THEN '80-89'
            ELSE                        '90-100'
        END AS conv_bucket,
        COUNT(*) AS n,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        SUM(CASE WHEN c.user_decision = 'won'  THEN c.odds - 1 ELSE 0 END) -
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1         ELSE 0 END) AS net_units
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('won','lost')" . $date_filter . $status_filter . "
     GROUP BY conv_bucket
     ORDER BY MIN(c.conviction) DESC",
    $params
);

// =============================================================================
// ROI par bucket d'EV
// =============================================================================
$by_ev = SE_Db::queryAll(
    "SELECT
        CASE
            WHEN c.ev < 0.03 THEN '< 3%'
            WHEN c.ev < 0.05 THEN '3-5%'
            WHEN c.ev < 0.07 THEN '5-7%'
            WHEN c.ev < 0.10 THEN '7-10%'
            ELSE                  '> 10%'
        END AS ev_bucket,
        COUNT(*) AS n,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        SUM(CASE WHEN c.user_decision = 'won'  THEN c.odds - 1 ELSE 0 END) -
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1         ELSE 0 END) AS net_units
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('won','lost')" . $date_filter . $status_filter . "
     GROUP BY ev_bucket
     ORDER BY MIN(c.ev)",
    $params
);

// =============================================================================
// Historique detaille : tous les paris decides (tracked + won + lost)
// =============================================================================
$history = SE_Db::queryAll(
    "SELECT c.candidate_id, c.market, c.market_group, c.odds, c.ev,
            c.conviction, c.conv_tier, c.user_decision, c.decision_at,
            m.match_id, m.home_name, m.away_name, m.kickoff_utc,
            m.league_name, m.league_country
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE c.user_decision IN ('tracked','won','lost')" . $date_filter . $status_filter . "
     ORDER BY m.kickoff_utc DESC, c.decision_at DESC",
    $params
);

// =============================================================================
// Helpers d'affichage
// =============================================================================

function color_roi(float $roi): string {
    if ($roi > 5)   return '#00ff9d';
    if ($roi > 0)   return '#7eff7e';
    if ($roi > -5)  return '#ffb800';
    return '#ff3b3b';
}

function compute_roi(int $n_won, int $n_lost, float $net): array {
    $n = $n_won + $n_lost;
    if ($n === 0) return ['roi' => 0, 'win' => 0];
    return [
        'roi' => 100 * $net / $n,
        'win' => 100 * $n_won / $n,
    ];
}

function flag_emoji(string $country): string {
    $map = [
        'England'     => 'gb-eng',
        'France'      => 'fr',
        'Spain'       => 'es',
        'Italy'       => 'it',
        'Germany'     => 'de',
        'Netherlands' => 'nl',
        'Belgium'     => 'be',
        'Brazil'      => 'br',
        'USA'         => 'us',
        'Japan'       => 'jp',
        'Argentina'   => 'ar',
        'Portugal'    => 'pt',
        'Scotland'    => 'gb-sct',
        'Wales'       => 'gb-wls',
        'Mexico'      => 'mx',
        'Australia'   => 'au',
        'South Korea' => 'kr',
        'Saudi Arabia'=> 'sa',
        'Turkey'      => 'tr',
        'Greece'      => 'gr',
        'Switzerland' => 'ch',
        'Austria'     => 'at',
        'Denmark'     => 'dk',
        'Sweden'      => 'se',
        'Norway'      => 'no',
        'Poland'      => 'pl',
        'Czech Republic' => 'cz',
        'Croatia'     => 'hr',
        'Serbia'      => 'rs',
        'Romania'     => 'ro',
        'Russia'      => 'ru',
        'Ukraine'     => 'ua',
    ];
    $code = $map[$country] ?? null;
    if ($code === null) return '🌍';
    return '<img src="https://flagcdn.com/24x18/' . htmlspecialchars($code) . '.png" '
         . 'width="22" height="16" '
         . 'alt="' . htmlspecialchars($country) . '" '
         . 'style="display: inline-block; vertical-align: middle; border-radius: 2px;">';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>📊 Stats — Edge Finder</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/dashboard.css">
  <style>
    .ef-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
    .ef-tab {
      padding: 0.5rem 1.2rem;
      background: var(--ef-bg-card-2);
      color: var(--ef-text-2);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 8px;
      text-decoration: none;
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 0.1em;
      transition: all 0.2s;
    }
    .ef-tab:hover { color: var(--ef-cyan); border-color: var(--ef-cyan); }
    .ef-tab.active {
      background: linear-gradient(135deg, var(--ef-pink), var(--ef-pink-dim));
      color: white;
      border-color: var(--ef-pink);
    }

    .ef-stats-block {
      background: var(--ef-bg-card);
      border: 1px solid var(--ef-border);
      border-radius: 14px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 1.5rem;
    }
    .ef-stats-block h2 {
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 0.2em;
      color: var(--ef-cyan);
      margin-bottom: 1rem;
      font-size: 1.3rem;
    }

    .ef-data-table {
      width: 100%;
      border-collapse: collapse;
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.85rem;
    }
    .ef-data-table th {
      text-align: left;
      padding: 0.6rem 0.8rem;
      color: var(--ef-text-3);
      letter-spacing: 0.1em;
      text-transform: uppercase;
      font-size: 0.7rem;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .ef-data-table td {
      padding: 0.6rem 0.8rem;
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .ef-data-table tr:hover { background: rgba(255,255,255,0.02); }
    .ef-data-table .num { text-align: right; font-weight: 700; }
    .ef-data-table .roi-cell {
      font-family: 'Orbitron', sans-serif;
      font-weight: 700;
    }

    .ef-empty-stats {
      text-align: center;
      padding: 3rem;
      color: var(--ef-text-3);
    }
    .ef-empty-stats p { margin-bottom: 0.5rem; }

    .ef-global-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }
    @media (max-width: 768px) {
      .ef-global-stats { grid-template-columns: repeat(2, 1fr); }
    }

    /* ── Historique détaillé ── */
    .ef-history-sub {
      color: var(--ef-text-3);
      font-size: 0.85rem;
      margin: -0.5rem 0 1rem;
    }
    .ef-history-empty {
      text-align: center;
      padding: 2rem;
      color: var(--ef-text-3);
    }
    .ef-history-scroll {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .ef-history-table {
      min-width: 720px;
    }
    .ef-history-match {
      min-width: 240px;
    }
    .ef-history-link {
      text-decoration: none;
      display: block;
    }
    .ef-history-teams {
      font-family: 'Rajdhani', sans-serif;
      font-weight: 700;
      font-size: 0.95rem;
      color: var(--ef-text);
      transition: color 0.15s;
    }
    .ef-history-link:hover .ef-history-teams {
      color: var(--ef-cyan);
    }
    .ef-history-vs {
      color: var(--ef-text-3);
      font-weight: 400;
      font-size: 0.8rem;
      margin: 0 0.2rem;
    }
    .ef-history-meta {
      display: block;
      font-size: 0.72rem;
      color: var(--ef-text-3);
      margin-top: 0.15rem;
    }
    @media (max-width: 768px) {
      .ef-history-table { font-size: 0.78rem; }
      .ef-history-teams { font-size: 0.85rem; }
    }
  </style>
</head>
<body>

<?php
try {
    $db = getDB();
    $pageActive = 'edge-finder';
    @require_once __DIR__ . '/../sidebar.php';
} catch (Throwable $e) {}
?>

<div class="main">
<div class="ef-main">

  <!-- HEADER -->
  <header class="ef-header">
    <div class="ef-title">
      <h1>📊 STATS</h1>
      <p class="ef-subtitle">Performance des picks par marché, ligue et profil</p>
    </div>
  </header>
  <div class="ef-gradient-strip"></div>

  <!-- TABS -->
  <div class="ef-tabs">
    <a href="./" class="ef-tab">🎯 Dashboard</a>
    <a href="stats.php" class="ef-tab active">📊 Stats</a>
  </div>

  <!-- FILTRES -->
  <form method="get" class="ef-filters">
    <select name="period">
      <option value="all"     <?= $filter_period==='all'?'selected':'' ?>>Toute la période</option>
      <option value="last30"  <?= $filter_period==='last30'?'selected':'' ?>>30 derniers jours</option>
      <option value="last90"  <?= $filter_period==='last90'?'selected':'' ?>>90 derniers jours</option>
    </select>

    <select name="status">
      <option value="">Tous types</option>
      <option value="auto"    <?= $filter_status==='auto'?'selected':'' ?>>🟢 Sweet auto uniquement</option>
      <option value="manual"  <?= $filter_status==='manual'?'selected':'' ?>>🟡 Manual review uniquement</option>
    </select>

    <button type="submit">Filtrer</button>
    <a href="stats.php" class="ef-filter-reset">Reset</a>
  </form>

  <?php if ($total_decided === 0): ?>
    <div class="ef-empty-stats">
      <p>📭 Aucun pick résolu pour le moment.</p>
      <p class="ef-empty-sub">Marque des picks comme <strong>gagné/perdu</strong> dans le dashboard pour voir tes stats apparaître ici.</p>
      <p class="ef-empty-sub">Astuce : configure le cron Hostinger pour auto-résoudre les résultats (voir <code>api/resolve_results.php</code>)</p>
    </div>
  <?php else: ?>

  <!-- GLOBAL STATS -->
  <div class="ef-global-stats">
    <div class="ef-stat">
      <div class="ef-stat-label">PICKS RÉSOLUS</div>
      <div class="ef-stat-value"><?= (int)$global['n_settled'] ?></div>
      <div class="ef-stat-sub"><?= (int)$global['n_won'] ?> 🏆 / <?= (int)$global['n_lost'] ?> 💀 / <?= (int)$global['n_void'] ?> ⏸</div>
    </div>
    <div class="ef-stat">
      <div class="ef-stat-label">WIN RATE</div>
      <div class="ef-stat-value" style="color: <?= $win_rate >= 50 ? 'var(--ef-green)' : 'var(--ef-yellow)' ?>"><?= number_format($win_rate, 1) ?>%</div>
      <div class="ef-stat-sub">sur <?= $total_decided ?> tranchés</div>
    </div>
    <div class="ef-stat">
      <div class="ef-stat-label">ROI</div>
      <div class="ef-stat-value" style="color: <?= color_roi($roi) ?>"><?= ($roi >= 0 ? '+' : '') . number_format($roi, 1) ?>%</div>
      <div class="ef-stat-sub"><?= number_format((float)$global['net_units'], 2) ?>u nettes</div>
    </div>
    <div class="ef-stat">
      <div class="ef-stat-label">COTE MOYENNE</div>
      <div class="ef-stat-value" style="color: var(--ef-cyan)"><?= number_format((float)$global['avg_odds'], 2) ?></div>
      <div class="ef-stat-sub">EV avg <?= number_format((float)$global['avg_ev'] * 100, 1) ?>% · Conv <?= number_format((float)$global['avg_conv']) ?></div>
    </div>
  </div>

  <!-- ROI PAR MARCHÉ -->
  <div class="ef-stats-block">
    <h2>ROI par marché</h2>
    <?php if (empty($by_market)): ?>
      <p style="color: var(--ef-text-3); padding: 1rem 0;">Aucune donnée pour cette période.</p>
    <?php else: ?>
    <table class="ef-data-table">
      <thead>
        <tr>
          <th>MARCHÉ</th>
          <th>GROUPE</th>
          <th class="num">N</th>
          <th class="num">W/L</th>
          <th class="num">WIN%</th>
          <th class="num">COTE AVG</th>
          <th class="num">EV AVG</th>
          <th class="num">NET</th>
          <th class="num">ROI</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($by_market as $row):
        $s = compute_roi((int)$row['n_won'], (int)$row['n_lost'], (float)$row['net_units']);
      ?>
        <tr>
          <td style="color: var(--ef-text)"><strong><?= htmlspecialchars($row['market']) ?></strong></td>
          <td style="color: var(--ef-text-3)"><?= htmlspecialchars($row['market_group']) ?></td>
          <td class="num"><?= (int)$row['n'] ?></td>
          <td class="num"><?= (int)$row['n_won'] ?>/<?= (int)$row['n_lost'] ?></td>
          <td class="num"><?= number_format($s['win'], 1) ?>%</td>
          <td class="num"><?= number_format((float)$row['avg_odds'], 2) ?></td>
          <td class="num"><?= number_format((float)$row['avg_ev'] * 100, 1) ?>%</td>
          <td class="num"><?= number_format((float)$row['net_units'], 2) ?></td>
          <td class="num roi-cell" style="color: <?= color_roi($s['roi']) ?>">
            <?= ($s['roi'] >= 0 ? '+' : '') . number_format($s['roi'], 1) ?>%
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ROI PAR LIGUE -->
  <div class="ef-stats-block">
    <h2>ROI par ligue</h2>
    <?php if (empty($by_league)): ?>
      <p style="color: var(--ef-text-3); padding: 1rem 0;">Aucune donnée.</p>
    <?php else: ?>
    <table class="ef-data-table">
      <thead>
        <tr>
          <th>LIGUE</th>
          <th>TIER</th>
          <th class="num">N</th>
          <th class="num">W/L</th>
          <th class="num">WIN%</th>
          <th class="num">NET</th>
          <th class="num">ROI</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($by_league as $row):
        $s = compute_roi((int)$row['n_won'], (int)$row['n_lost'], (float)$row['net_units']);
      ?>
        <tr>
          <td><strong><?= htmlspecialchars($row['league_name']) ?></strong></td>
          <td style="color: var(--ef-text-3)"><?= htmlspecialchars($row['league_tier'] ?? '') ?></td>
          <td class="num"><?= (int)$row['n'] ?></td>
          <td class="num"><?= (int)$row['n_won'] ?>/<?= (int)$row['n_lost'] ?></td>
          <td class="num"><?= number_format($s['win'], 1) ?>%</td>
          <td class="num"><?= number_format((float)$row['net_units'], 2) ?></td>
          <td class="num roi-cell" style="color: <?= color_roi($s['roi']) ?>">
            <?= ($s['roi'] >= 0 ? '+' : '') . number_format($s['roi'], 1) ?>%
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ROI PAR RANGE DE COTE -->
  <div class="ef-stats-block">
    <h2>ROI par range de cote</h2>
    <?php if (empty($by_odds_range)): ?>
      <p style="color: var(--ef-text-3); padding: 1rem 0;">Aucune donnée.</p>
    <?php else: ?>
    <table class="ef-data-table">
      <thead>
        <tr>
          <th>RANGE COTE</th>
          <th class="num">N</th>
          <th class="num">W/L</th>
          <th class="num">WIN%</th>
          <th class="num">COTE AVG</th>
          <th class="num">NET</th>
          <th class="num">ROI</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($by_odds_range as $row):
        $s = compute_roi((int)$row['n_won'], (int)$row['n_lost'], (float)$row['net_units']);
      ?>
        <tr>
          <td style="color: var(--ef-cyan)"><strong><?= htmlspecialchars($row['odds_bucket']) ?></strong></td>
          <td class="num"><?= (int)$row['n'] ?></td>
          <td class="num"><?= (int)$row['n_won'] ?>/<?= (int)$row['n_lost'] ?></td>
          <td class="num"><?= number_format($s['win'], 1) ?>%</td>
          <td class="num"><?= number_format((float)$row['avg_odds'], 2) ?></td>
          <td class="num"><?= number_format((float)$row['net_units'], 2) ?></td>
          <td class="num roi-cell" style="color: <?= color_roi($s['roi']) ?>">
            <?= ($s['roi'] >= 0 ? '+' : '') . number_format($s['roi'], 1) ?>%
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ROI PAR CONVICTION -->
  <div class="ef-stats-block">
    <h2>ROI par bucket de conviction</h2>
    <?php if (empty($by_conv)): ?>
      <p style="color: var(--ef-text-3); padding: 1rem 0;">Aucune donnée.</p>
    <?php else: ?>
    <table class="ef-data-table">
      <thead>
        <tr>
          <th>CONVICTION</th>
          <th class="num">N</th>
          <th class="num">W/L</th>
          <th class="num">WIN%</th>
          <th class="num">NET</th>
          <th class="num">ROI</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($by_conv as $row):
        $s = compute_roi((int)$row['n_won'], (int)$row['n_lost'], (float)$row['net_units']);
      ?>
        <tr>
          <td style="color: var(--ef-pink)"><strong><?= htmlspecialchars($row['conv_bucket']) ?></strong></td>
          <td class="num"><?= (int)$row['n'] ?></td>
          <td class="num"><?= (int)$row['n_won'] ?>/<?= (int)$row['n_lost'] ?></td>
          <td class="num"><?= number_format($s['win'], 1) ?>%</td>
          <td class="num"><?= number_format((float)$row['net_units'], 2) ?></td>
          <td class="num roi-cell" style="color: <?= color_roi($s['roi']) ?>">
            <?= ($s['roi'] >= 0 ? '+' : '') . number_format($s['roi'], 1) ?>%
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ROI PAR EV -->
  <div class="ef-stats-block">
    <h2>ROI par bucket d'EV</h2>
    <?php if (empty($by_ev)): ?>
      <p style="color: var(--ef-text-3); padding: 1rem 0;">Aucune donnée.</p>
    <?php else: ?>
    <table class="ef-data-table">
      <thead>
        <tr>
          <th>EV BUCKET</th>
          <th class="num">N</th>
          <th class="num">W/L</th>
          <th class="num">WIN%</th>
          <th class="num">NET</th>
          <th class="num">ROI</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($by_ev as $row):
        $s = compute_roi((int)$row['n_won'], (int)$row['n_lost'], (float)$row['net_units']);
      ?>
        <tr>
          <td style="color: var(--ef-green)"><strong><?= htmlspecialchars($row['ev_bucket']) ?></strong></td>
          <td class="num"><?= (int)$row['n'] ?></td>
          <td class="num"><?= (int)$row['n_won'] ?>/<?= (int)$row['n_lost'] ?></td>
          <td class="num"><?= number_format($s['win'], 1) ?>%</td>
          <td class="num"><?= number_format((float)$row['net_units'], 2) ?></td>
          <td class="num roi-cell" style="color: <?= color_roi($s['roi']) ?>">
            <?= ($s['roi'] >= 0 ? '+' : '') . number_format($s['roi'], 1) ?>%
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ─────────────────────────────────── HISTORIQUE DÉTAILLÉ ─── -->
  <div class="ef-stats-block ef-history-block">
    <h2>📋 Historique détaillé des paris</h2>
    <p class="ef-history-sub">Tous les paris décidés : 📌 suivis (en cours) + 🏆 gagnés + 💀 perdus</p>

    <?php if (empty($history)): ?>
      <p class="ef-history-empty">Aucun pari décidé sur la période sélectionnée.</p>
    <?php else: ?>
    <div class="ef-history-scroll">
    <table class="ef-data-table ef-history-table">
      <thead>
        <tr>
          <th>Match</th>
          <th>Marché</th>
          <th class="num">Cote</th>
          <th class="num">EV</th>
          <th class="num">CONV</th>
          <th class="num">Statut</th>
          <th class="num">Gain/Perte</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($history as $h):
        $dec = $h['user_decision'];
        // Calcul gain/perte en unites (mise 1u)
        if ($dec === 'won') {
          $pnl = (float)$h['odds'] - 1;
          $pnl_color = '#00ff9d';
          $pnl_str = '+' . number_format($pnl, 2) . 'u';
          $status_label = '🏆 GAGNÉ';
          $status_color = '#00ff9d';
        } elseif ($dec === 'lost') {
          $pnl = -1.0;
          $pnl_color = '#ff3b3b';
          $pnl_str = '-1.00u';
          $status_label = '💀 PERDU';
          $status_color = '#ff3b3b';
        } else { // tracked
          $pnl_color = 'var(--ef-text-3)';
          $pnl_str = '—';
          $status_label = '📌 EN COURS';
          $status_color = 'var(--ef-cyan)';
        }
        $kickoff = (new DateTime($h['kickoff_utc']))->format('d/m H:i');
      ?>
        <tr>
          <td class="ef-history-match">
            <a href="match.php?id=<?= (int)$h['match_id'] ?>" class="ef-history-link">
              <span class="ef-history-teams"><?= htmlspecialchars($h['home_name']) ?> <span class="ef-history-vs">vs</span> <?= htmlspecialchars($h['away_name']) ?></span>
            </a>
            <span class="ef-history-meta">
              <?= htmlspecialchars($h['league_name'] ?? '') ?> · <?= $kickoff ?>
            </span>
          </td>
          <td><strong><?= htmlspecialchars($h['market']) ?></strong></td>
          <td class="num"><?= number_format((float)$h['odds'], 2) ?></td>
          <td class="num" style="color: <?= (float)$h['ev'] >= 0 ? 'var(--ef-green)' : 'var(--ef-red)' ?>">
            <?= ((float)$h['ev'] >= 0 ? '+' : '') . number_format((float)$h['ev'] * 100, 1) ?>%
          </td>
          <td class="num"><?= (int)$h['conviction'] ?></td>
          <td class="num"><span style="color: <?= $status_color ?>; font-weight: 700; font-size: 0.78rem;"><?= $status_label ?></span></td>
          <td class="num" style="color: <?= $pnl_color ?>; font-weight: 700;"><?= $pnl_str ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    </div>
    <?php endif ?>
  </div>

  <?php endif ?>

  <footer class="ef-footer">
    <p>StratEdge Edge Finder v1.0 · Stats Analytics</p>
    <p class="ef-disclaimer">Mesure de performance basée sur les picks marqués won/lost. Validation humaine requise.</p>
  </footer>
</div>
</div>

</body>
</html>
