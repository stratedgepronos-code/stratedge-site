<?php
/**
 * StratEdge Quant Engine
 * Dashboard quantitatif séparé de l'Edge Finder.
 */

declare(strict_types=1);

require_once __DIR__ . '/../edge-finder/lib/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireSuperAdmin();

$db = getDB();
$pageActive = 'quant-engine';

$lastImport = SE_Db::queryOne(
    "SELECT *
     FROM picks_imports
     ORDER BY imported_at DESC
     LIMIT 1"
);

$quant = [];

try {
    $quant = SE_Db::queryOne(
        "SELECT
            COUNT(
                DISTINCT CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                    THEN m.match_id
                END
            ) AS active_matches,

            SUM(
                CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                     AND c.user_decision = 'pending'
                     AND c.recommendable = 1
                     AND c.tracking_only = 0
                     AND m.data_suspect = 0
                     AND m.quarantine = 0
                    THEN 1 ELSE 0
                END
            ) AS exploitable,

            SUM(
                CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                     AND c.user_decision = 'pending'
                     AND c.tracking_only = 1
                    THEN 1 ELSE 0
                END
            ) AS tracking_only,

            COUNT(
                DISTINCT CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                     AND m.data_suspect = 1
                    THEN m.match_id
                END
            ) AS data_suspect,

            COUNT(
                DISTINCT CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                     AND m.quarantine = 1
                    THEN m.match_id
                END
            ) AS quarantined,

            SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
            SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
            SUM(CASE WHEN c.user_decision = 'void' THEN 1 ELSE 0 END) AS n_void,

            SUM(
                CASE
                    WHEN c.user_decision = 'won'  THEN c.odds - 1
                    WHEN c.user_decision = 'lost' THEN -1
                    ELSE 0
                END
            ) AS net_units,

            AVG(
                CASE
                    WHEN c.user_decision IN ('won','lost')
                    THEN c.odds
                END
            ) AS avg_odds,

            AVG(
                CASE
                    WHEN c.user_decision IN ('won','lost')
                    THEN c.ev
                END
            ) AS avg_ev,

            AVG(
                CASE
                    WHEN c.user_decision IN ('won','lost')
                    THEN c.conviction
                END
            ) AS avg_conviction

         FROM pick_candidates c
         JOIN pick_matches m ON m.match_id = c.match_id
         WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 30 DAY"
    ) ?? [];

} catch (Throwable $e) {
    // Compatibilité si les colonnes de cohérence v8.2 ne sont pas disponibles.
    $quant = SE_Db::queryOne(
        "SELECT
            COUNT(
                DISTINCT CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                    THEN m.match_id
                END
            ) AS active_matches,

            SUM(
                CASE
                    WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                     AND c.user_decision = 'pending'
                     AND c.status IN ('auto','manual')
                    THEN 1 ELSE 0
                END
            ) AS exploitable,

            0 AS tracking_only,
            0 AS data_suspect,
            0 AS quarantined,

            SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
            SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
            SUM(CASE WHEN c.user_decision = 'void' THEN 1 ELSE 0 END) AS n_void,

            SUM(
                CASE
                    WHEN c.user_decision = 'won'  THEN c.odds - 1
                    WHEN c.user_decision = 'lost' THEN -1
                    ELSE 0
                END
            ) AS net_units,

            AVG(
                CASE
                    WHEN c.user_decision IN ('won','lost')
                    THEN c.odds
                END
            ) AS avg_odds,

            AVG(
                CASE
                    WHEN c.user_decision IN ('won','lost')
                    THEN c.ev
                END
            ) AS avg_ev,

            AVG(
                CASE
                    WHEN c.user_decision IN ('won','lost')
                    THEN c.conviction
                END
            ) AS avg_conviction

         FROM pick_candidates c
         JOIN pick_matches m ON m.match_id = c.match_id
         WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 30 DAY"
    ) ?? [];
}

try {
    $opportunities = SE_Db::queryAll(
        "SELECT
            c.market,
            c.market_group,
            c.model_proba,
            c.devig_proba,
            c.odds,
            c.ev,
            c.conviction,
            c.status,
            c.recommended,
            m.match_id,
            m.home_name,
            m.away_name,
            m.league_name,
            m.league_country,
            m.kickoff_utc

         FROM pick_candidates c
         JOIN pick_matches m ON m.match_id = c.match_id

         WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
           AND c.user_decision = 'pending'
           AND c.recommendable = 1
           AND c.tracking_only = 0
           AND m.data_suspect = 0
           AND m.quarantine = 0

         ORDER BY
            c.recommended DESC,
            c.conviction DESC,
            c.ev DESC

         LIMIT 10"
    );

} catch (Throwable $e) {
    $opportunities = SE_Db::queryAll(
        "SELECT
            c.market,
            c.market_group,
            c.model_proba,
            c.devig_proba,
            c.odds,
            c.ev,
            c.conviction,
            c.status,
            c.recommended,
            m.match_id,
            m.home_name,
            m.away_name,
            m.league_name,
            m.league_country,
            m.kickoff_utc

         FROM pick_candidates c
         JOIN pick_matches m ON m.match_id = c.match_id

         WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
           AND c.user_decision = 'pending'
           AND c.status IN ('auto','manual')

         ORDER BY
            c.recommended DESC,
            c.conviction DESC,
            c.ev DESC

         LIMIT 10"
    );
}

$imports = SE_Db::queryAll(
    "SELECT
        import_id,
        generated_at,
        imported_at,
        version,
        horizon_days,
        matchs_total,
        matchs_analyses,
        candidates_auto,
        candidates_manual,
        candidates_warn
     FROM picks_imports
     ORDER BY imported_at DESC
     LIMIT 8"
);

$won = (int)($quant['n_won'] ?? 0);
$lost = (int)($quant['n_lost'] ?? 0);
$void = (int)($quant['n_void'] ?? 0);
$resolved = $won + $lost;

$netUnits = (float)($quant['net_units'] ?? 0);
$winRate = $resolved > 0 ? ($won * 100 / $resolved) : 0.0;
$roi = $resolved > 0 ? ($netUnits * 100 / $resolved) : 0.0;

$avgOdds = (float)($quant['avg_odds'] ?? 0);
$avgEv = (float)($quant['avg_ev'] ?? 0);
$avgConviction = (float)($quant['avg_conviction'] ?? 0);

$activeMatches = (int)($quant['active_matches'] ?? 0);
$exploitable = (int)($quant['exploitable'] ?? 0);
$trackingOnly = (int)($quant['tracking_only'] ?? 0);
$dataSuspect = (int)($quant['data_suspect'] ?? 0);
$quarantined = (int)($quant['quarantined'] ?? 0);

function qe_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function qe_percent(float $value, int $decimals = 1): string
{
    return ($value >= 0 ? '+' : '') . number_format($value, $decimals, ',', ' ') . '%';
}

function qe_number(float $value, int $decimals = 1): string
{
    return number_format($value, $decimals, ',', ' ');
}

function qe_date(?string $date, string $format = 'd/m/Y H:i'): string
{
    if (!$date) {
        return '—';
    }

    try {
        return (new DateTime($date))->format($format);
    } catch (Throwable $e) {
        return '—';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quant Engine — StratEdge</title>

    <link rel="icon" type="image/png" href="/assets/images/mascotte.png">

    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --qe-bg: #050810;
            --qe-card: #0d1220;
            --qe-card-2: #111827;
            --qe-pink: #ff2d78;
            --qe-cyan: #00d4ff;
            --qe-gold: #ffd700;
            --qe-green: #00e59b;
            --qe-red: #ff4e6a;
            --qe-purple: #a855f7;
            --qe-text: #f0f4f8;
            --qe-text-2: #b0bec9;
            --qe-muted: #7f90a6;
            --qe-border: rgba(255,255,255,.075);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 85% 0%, rgba(0,212,255,.07), transparent 28%),
                radial-gradient(circle at 20% 0%, rgba(255,45,120,.08), transparent 30%),
                var(--qe-bg);
            color: var(--qe-text);
            font-family: 'Rajdhani', sans-serif;
        }

        .main {
            padding: 2rem;
        }

        .qe-shell {
            width: min(1600px, 100%);
            margin: 0 auto;
        }

        .qe-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1.4rem;
        }

        .qe-eyebrow {
            margin-bottom: .5rem;
            color: var(--qe-cyan);
            font-family: 'Share Tech Mono', monospace;
            font-size: .68rem;
            letter-spacing: .26em;
            text-transform: uppercase;
        }

        .qe-title {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.8rem, 4vw, 3.2rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: .045em;
            background: linear-gradient(90deg, var(--qe-pink), #d268ff 52%, var(--qe-cyan));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .qe-subtitle {
            max-width: 760px;
            margin-top: .7rem;
            color: var(--qe-muted);
            font-size: 1rem;
        }

        .qe-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .65rem;
        }

        .qe-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: .72rem 1rem;
            border: 1px solid rgba(0,212,255,.28);
            border-radius: 9px;
            background: rgba(0,212,255,.075);
            color: var(--qe-cyan);
            font-weight: 700;
            text-decoration: none;
            transition: .2s ease;
        }

        .qe-btn:hover {
            transform: translateY(-2px);
            border-color: rgba(0,212,255,.65);
            background: rgba(0,212,255,.13);
        }

        .qe-btn.pink {
            border-color: rgba(255,45,120,.3);
            background: rgba(255,45,120,.08);
            color: var(--qe-pink);
        }

        .qe-strip {
            height: 3px;
            margin-bottom: 1.5rem;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--qe-pink), var(--qe-purple), var(--qe-cyan));
            box-shadow: 0 0 20px rgba(255,45,120,.28);
        }

        .qe-sync {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            margin-bottom: 1rem;
        }

        .qe-chip {
            padding: .42rem .72rem;
            border: 1px solid var(--qe-border);
            border-radius: 999px;
            background: rgba(255,255,255,.025);
            color: var(--qe-text-2);
            font-family: 'Share Tech Mono', monospace;
            font-size: .72rem;
        }

        .qe-chip strong {
            color: var(--qe-cyan);
        }

        .qe-kpis {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .85rem;
            margin-bottom: 1.25rem;
        }

        .qe-kpi {
            position: relative;
            min-width: 0;
            overflow: hidden;
            padding: 1.15rem;
            border: 1px solid var(--qe-border);
            border-radius: 14px;
            background:
                linear-gradient(145deg, rgba(255,255,255,.025), transparent),
                var(--qe-card);
        }

        .qe-kpi::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 2px;
            background: var(--tone, var(--qe-pink));
        }

        .qe-kpi-label {
            margin-bottom: .55rem;
            overflow: hidden;
            color: var(--qe-muted);
            font-family: 'Share Tech Mono', monospace;
            font-size: .61rem;
            letter-spacing: .13em;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .qe-kpi-value {
            overflow: hidden;
            color: var(--tone, var(--qe-text));
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.25rem, 2vw, 1.85rem);
            font-weight: 900;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .qe-kpi-sub {
            margin-top: .38rem;
            color: var(--qe-muted);
            font-size: .73rem;
        }

        .tone-pink { --tone: var(--qe-pink); }
        .tone-cyan { --tone: var(--qe-cyan); }
        .tone-green { --tone: var(--qe-green); }
        .tone-red { --tone: var(--qe-red); }
        .tone-gold { --tone: var(--qe-gold); }
        .tone-purple { --tone: var(--qe-purple); }

        .qe-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(310px, .7fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .qe-card {
            min-width: 0;
            padding: 1.25rem;
            border: 1px solid var(--qe-border);
            border-radius: 15px;
            background: var(--qe-card);
        }

        .qe-card-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .qe-card-title h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: .9rem;
            letter-spacing: .05em;
        }

        .qe-card-title span {
            color: var(--qe-muted);
            font-family: 'Share Tech Mono', monospace;
            font-size: .66rem;
        }

        .qe-table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: .68rem .6rem;
            border-bottom: 1px solid rgba(255,255,255,.07);
            color: var(--qe-muted);
            font-family: 'Share Tech Mono', monospace;
            font-size: .57rem;
            letter-spacing: .12em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: .78rem .6rem;
            border-bottom: 1px solid rgba(255,255,255,.045);
            color: var(--qe-text-2);
            font-size: .85rem;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .qe-match {
            min-width: 185px;
        }

        .qe-match strong {
            display: block;
            color: var(--qe-text);
            font-size: .88rem;
        }

        .qe-match span {
            color: var(--qe-muted);
            font-size: .7rem;
        }

        .qe-market {
            color: var(--qe-cyan);
            font-weight: 700;
            white-space: nowrap;
        }

        .qe-num {
            font-family: 'Share Tech Mono', monospace;
            white-space: nowrap;
        }

        .qe-positive {
            color: var(--qe-green);
        }

        .qe-negative {
            color: var(--qe-red);
        }

        .qe-reco {
            display: inline-flex;
            padding: .18rem .45rem;
            border: 1px solid rgba(255,45,120,.3);
            border-radius: 999px;
            background: rgba(255,45,120,.08);
            color: var(--qe-pink);
            font-size: .64rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .qe-health {
            display: grid;
            gap: .7rem;
        }

        .qe-health-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .78rem .85rem;
            border: 1px solid rgba(255,255,255,.055);
            border-radius: 10px;
            background: rgba(255,255,255,.018);
        }

        .qe-health-label {
            color: var(--qe-text-2);
            font-size: .84rem;
        }

        .qe-health-value {
            font-family: 'Orbitron', sans-serif;
            font-size: .82rem;
            font-weight: 800;
        }

        .qe-note {
            margin-top: 1rem;
            padding: .9rem;
            border: 1px solid rgba(255,215,0,.18);
            border-radius: 10px;
            background: rgba(255,215,0,.045);
            color: #c6b76d;
            font-size: .76rem;
            line-height: 1.45;
        }

        .qe-empty {
            padding: 2rem 1rem;
            color: var(--qe-muted);
            text-align: center;
        }

        @media (max-width: 1250px) {
            .qe-kpis {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .qe-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main {
                padding: 68px .75rem 5rem !important;
            }

            .qe-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .qe-actions {
                justify-content: flex-start;
            }

            .qe-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .6rem;
            }

            .qe-kpi {
                padding: .85rem;
            }

            .qe-kpi-label {
                font-size: .52rem;
                letter-spacing: .07em;
            }

            .qe-kpi-value {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 390px) {
            .qe-kpis {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . '/../sidebar.php'; ?>

<div class="main">
    <main class="qe-shell">

        <header class="qe-header">
            <div>
                <div class="qe-eyebrow">StratEdge Intelligence Suite</div>
                <h1 class="qe-title">QUANT ENGINE</h1>
                <p class="qe-subtitle">
                    Vue quantitative globale du moteur : performance, qualité des données,
                    rendement théorique et meilleures opportunités détectées.
                </p>
            </div>

            <div class="qe-actions">
                <a class="qe-btn pink" href="/panel-x9k3m/edge-finder/">
                    🎯 Ouvrir Edge Finder
                </a>

                <a class="qe-btn" href="/panel-x9k3m/edge-finder/stats.php">
                    📊 Stats détaillées
                </a>
            </div>
        </header>

        <div class="qe-strip"></div>

        <div class="qe-sync">
            <span class="qe-chip">
                Dernier import :
                <strong><?= qe_date($lastImport['imported_at'] ?? null, 'd/m/Y H:i') ?></strong>
            </span>

            <span class="qe-chip">
                Version :
                <strong><?= qe_h((string)($lastImport['version'] ?? '—')) ?></strong>
            </span>

            <span class="qe-chip">
                Horizon :
                <strong><?= (int)($lastImport['horizon_days'] ?? 0) ?> jours</strong>
            </span>

            <span class="qe-chip">
                Import :
                <strong>#<?= (int)($lastImport['import_id'] ?? 0) ?></strong>
            </span>
        </div>

        <section class="qe-kpis">

            <article class="qe-kpi tone-cyan">
                <div class="qe-kpi-label">Matchs analysés</div>
                <div class="qe-kpi-value">
                    <?= number_format((int)($lastImport['matchs_analyses'] ?? 0), 0, ',', ' ') ?>
                </div>
                <div class="qe-kpi-sub">dernier import moteur</div>
            </article>

            <article class="qe-kpi tone-green">
                <div class="qe-kpi-label">Candidats exploitables</div>
                <div class="qe-kpi-value"><?= number_format($exploitable, 0, ',', ' ') ?></div>
                <div class="qe-kpi-sub">pending, propres et recommandables</div>
            </article>

            <article class="qe-kpi tone-gold">
                <div class="qe-kpi-label">Alertes cohérence</div>
                <div class="qe-kpi-value">
                    <?= number_format($dataSuspect + $quarantined, 0, ',', ' ') ?>
                </div>
                <div class="qe-kpi-sub">suspects et quarantaines</div>
            </article>

            <article class="qe-kpi tone-purple">
                <div class="qe-kpi-label">Picks résolus</div>
                <div class="qe-kpi-value"><?= number_format($resolved, 0, ',', ' ') ?></div>
                <div class="qe-kpi-sub"><?= $won ?> gagnés · <?= $lost ?> perdus</div>
            </article>

            <article class="qe-kpi <?= $winRate >= 50 ? 'tone-green' : 'tone-red' ?>">
                <div class="qe-kpi-label">Win rate</div>
                <div class="qe-kpi-value"><?= qe_number($winRate, 1) ?>%</div>
                <div class="qe-kpi-sub">picks gagnés / tranchés</div>
            </article>

            <article class="qe-kpi <?= $netUnits >= 0 ? 'tone-green' : 'tone-red' ?>">
                <div class="qe-kpi-label">Unités nettes</div>
                <div class="qe-kpi-value">
                    <?= ($netUnits >= 0 ? '+' : '') . qe_number($netUnits, 2) ?>u
                </div>
                <div class="qe-kpi-sub">mise plate de 1 unité</div>
            </article>

            <article class="qe-kpi <?= $roi >= 0 ? 'tone-green' : 'tone-red' ?>">
                <div class="qe-kpi-label">ROI réel</div>
                <div class="qe-kpi-value"><?= qe_percent($roi, 1) ?></div>
                <div class="qe-kpi-sub">profit / picks résolus</div>
            </article>

            <article class="qe-kpi tone-cyan">
                <div class="qe-kpi-label">Cote moyenne</div>
                <div class="qe-kpi-value"><?= qe_number($avgOdds, 2) ?></div>
                <div class="qe-kpi-sub">picks gagnés et perdus</div>
            </article>

            <article class="qe-kpi <?= $avgEv >= 0 ? 'tone-green' : 'tone-red' ?>">
                <div class="qe-kpi-label">EV moyenne</div>
                <div class="qe-kpi-value"><?= qe_percent($avgEv * 100, 1) ?></div>
                <div class="qe-kpi-sub">estimation pré-match</div>
            </article>

            <article class="qe-kpi tone-pink">
                <div class="qe-kpi-label">Conviction moyenne</div>
                <div class="qe-kpi-value"><?= qe_number($avgConviction, 0) ?></div>
                <div class="qe-kpi-sub">score interne du modèle</div>
            </article>

        </section>

        <section class="qe-grid">

            <article class="qe-card">
                <div class="qe-card-title">
                    <h2>Top opportunités modèle</h2>
                    <span><?= count($opportunities) ?> candidats affichés</span>
                </div>

                <?php if (empty($opportunities)): ?>

                    <div class="qe-empty">
                        Aucun candidat exploitable actuellement.
                    </div>

                <?php else: ?>

                    <div class="qe-table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Match</th>
                                <th>Marché</th>
                                <th>Cote</th>
                                <th>Proba modèle</th>
                                <th>EV</th>
                                <th>Conviction</th>
                                <th>Statut</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($opportunities as $candidate): ?>
                                <?php
                                $candidateEv = (float)($candidate['ev'] ?? 0);
                                $modelProbability = (float)($candidate['model_proba'] ?? 0);
                                ?>

                                <tr>
                                    <td class="qe-match">
                                        <strong>
                                            <?= qe_h((string)$candidate['home_name']) ?>
                                            —
                                            <?= qe_h((string)$candidate['away_name']) ?>
                                        </strong>

                                        <span>
                                            <?= qe_h((string)($candidate['league_name'] ?? '')) ?>
                                            ·
                                            <?= qe_date($candidate['kickoff_utc'] ?? null, 'd/m H:i') ?>
                                        </span>
                                    </td>

                                    <td class="qe-market">
                                        <?= qe_h((string)$candidate['market']) ?>
                                        <small>
                                            <?= qe_h((string)$candidate['market_group']) ?>
                                        </small>
                                    </td>

                                    <td class="qe-num">
                                        <?= qe_number((float)$candidate['odds'], 2) ?>
                                    </td>

                                    <td class="qe-num">
                                        <?= qe_number($modelProbability * 100, 1) ?>%
                                    </td>

                                    <td class="qe-num <?= $candidateEv >= 0 ? 'qe-positive' : 'qe-negative' ?>">
                                        <?= qe_percent($candidateEv * 100, 1) ?>
                                    </td>

                                    <td class="qe-num">
                                        <?= (int)$candidate['conviction'] ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($candidate['recommended'])): ?>
                                            <span class="qe-reco">⭐ RECOMMANDÉ</span>
                                        <?php else: ?>
                                            <?= qe_h(strtoupper((string)$candidate['status'])) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </article>

            <aside class="qe-card">
                <div class="qe-card-title">
                    <h2>Contrôle du moteur</h2>
                    <span>30 derniers jours</span>
                </div>

                <div class="qe-health">

                    <div class="qe-health-row">
                        <span class="qe-health-label">Matchs actifs</span>
                        <span class="qe-health-value" style="color:var(--qe-cyan)">
                            <?= $activeMatches ?>
                        </span>
                    </div>

                    <div class="qe-health-row">
                        <span class="qe-health-label">Tracking uniquement</span>
                        <span class="qe-health-value" style="color:var(--qe-gold)">
                            <?= $trackingOnly ?>
                        </span>
                    </div>

                    <div class="qe-health-row">
                        <span class="qe-health-label">Données suspectes</span>
                        <span class="qe-health-value" style="color:<?= $dataSuspect ? 'var(--qe-red)' : 'var(--qe-green)' ?>">
                            <?= $dataSuspect ?>
                        </span>
                    </div>

                    <div class="qe-health-row">
                        <span class="qe-health-label">Matchs en quarantaine</span>
                        <span class="qe-health-value" style="color:<?= $quarantined ? 'var(--qe-red)' : 'var(--qe-green)' ?>">
                            <?= $quarantined ?>
                        </span>
                    </div>

                    <div class="qe-health-row">
                        <span class="qe-health-label">Paris void</span>
                        <span class="qe-health-value" style="color:var(--qe-muted)">
                            <?= $void ?>
                        </span>
                    </div>

                </div>

                <div class="qe-note">
                    Le CLV, la closing odds, les mises réelles et l’historique de bankroll
                    ne sont pas encore enregistrés dans la base. Aucun indicateur fictif
                    n’est affiché.
                </div>
            </aside>

        </section>

        <section class="qe-card">
            <div class="qe-card-title">
                <h2>Historique des imports</h2>
                <span>8 dernières synchronisations</span>
            </div>

            <div class="qe-table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Import</th>
                        <th>Date</th>
                        <th>Version</th>
                        <th>Horizon</th>
                        <th>Matchs</th>
                        <th>Analysés</th>
                        <th>Auto</th>
                        <th>Manuel</th>
                        <th>Warnings</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($imports as $import): ?>
                        <tr>
                            <td class="qe-num">#<?= (int)$import['import_id'] ?></td>
                            <td class="qe-num"><?= qe_date($import['imported_at'] ?? null, 'd/m H:i') ?></td>
                            <td class="qe-num"><?= qe_h((string)($import['version'] ?? '—')) ?></td>
                            <td class="qe-num"><?= (int)($import['horizon_days'] ?? 0) ?> j</td>
                            <td class="qe-num"><?= (int)($import['matchs_total'] ?? 0) ?></td>
                            <td class="qe-num"><?= (int)($import['matchs_analyses'] ?? 0) ?></td>
                            <td class="qe-num qe-positive"><?= (int)($import['candidates_auto'] ?? 0) ?></td>
                            <td class="qe-num" style="color:var(--qe-gold)">
                                <?= (int)($import['candidates_manual'] ?? 0) ?>
                            </td>
                            <td class="qe-num qe-negative"><?= (int)($import['candidates_warn'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

</body>
</html>
