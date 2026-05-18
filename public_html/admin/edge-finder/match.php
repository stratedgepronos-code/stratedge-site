<?php
/**
 * StratEdge Edge Finder — Page de détail d'un match
 * URL : /panel-x9k3m/edge-finder/match.php?id=MATCH_ID
 *
 * Affiche toutes les stats FootyStats d'un match + tous les candidats
 * (pas seulement les filtrés), avec analyse contextuelle.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($match_id <= 0) {
    http_response_code(400);
    die('match_id requis');
}

// =============================================================================
// Charger le match avec toutes ses stats
// =============================================================================

$match = SE_Db::queryOne(
    "SELECT * FROM pick_matches WHERE match_id = ?",
    [$match_id]
);

if (!$match) {
    die('Match introuvable');
}

// Candidats triés par conviction
$candidates = SE_Db::queryAll(
    "SELECT * FROM pick_candidates WHERE match_id = ? ORDER BY conviction DESC, ev DESC",
    [$match_id]
);

// =============================================================================
// Helpers
// =============================================================================

function fmt_kickoff_full(?string $utc_dt): string {
    if (!$utc_dt) return '';
    // NOTE: malgre le nom du champ 'kickoff_utc', la DB stocke deja en
    // heure locale (Europe/Paris) car MySQL FROM_UNIXTIME() utilise la TZ
    // du serveur lors de la generation. On affiche tel quel sans conversion.
    $dt = new DateTime($utc_dt);
    return $dt->format('d/m/Y H\hi');
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
    if ($code === null) {
        return '<span style="font-size: 0.9em; color: var(--ef-text-3);">🌍</span>';
    }
    return '<img src="https://flagcdn.com/24x18/' . htmlspecialchars($code) . '.png" '
         . 'srcset="https://flagcdn.com/48x36/' . htmlspecialchars($code) . '.png 2x" '
         . 'width="22" height="16" '
         . 'alt="' . htmlspecialchars($country) . '" '
         . 'style="display: inline-block; vertical-align: middle; border-radius: 2px;">';
}

// Charge la base des logos football (centralisee)
require_once __DIR__ . '/../../includes/football-logos-db.php';

/**
 * Retourne un <img> HTML pour le logo d'une équipe foot.
 * Si pas trouvé dans la base, retourne un placeholder discret.
 */
function team_logo_img(string $teamName, int $size = 28): string {
    if (function_exists('stratedge_football_logo')) {
        $url = stratedge_football_logo($teamName);
        if ($url && $url !== '') {
            return '<img src="' . htmlspecialchars($url) . '" '
                 . 'alt="' . htmlspecialchars($teamName) . '" '
                 . 'width="' . $size . '" height="' . $size . '" '
                 . 'style="display: inline-block; vertical-align: middle; '
                 . 'object-fit: contain; margin-right: 0.4em; '
                 . 'filter: drop-shadow(0 1px 3px rgba(0,0,0,0.4));" '
                 . 'onerror="this.style.display=\'none\'">';
        }
    }
    return '';
}

function status_emoji(string $status): string {
    return match($status) {
        'auto'   => '🟢',
        'manual' => '🟡',
        'warn'   => '⚠️',
        default  => '⚪',
    };
}

function pct_or_dash(mixed $v, int $decimals = 0): string {
    if ($v === null || $v === '') return '<span style="color: var(--ef-text-3)">—</span>';
    $f = is_numeric($v) ? (float)$v : null;
    if ($f === null) return '<span style="color: var(--ef-text-3)">—</span>';
    return number_format($f, $decimals) . '%';
}

function num_or_dash(mixed $v, int $decimals = 2): string {
    if ($v === null || $v === '') return '<span style="color: var(--ef-text-3)">—</span>';
    $f = is_numeric($v) ? (float)$v : null;
    if ($f === null) return '<span style="color: var(--ef-text-3)">—</span>';
    return number_format($f, $decimals);
}

function color_pct(mixed $v): string {
    if ($v === null || $v === '' || !is_numeric($v)) return 'var(--ef-text-3)';
    $f = (float)$v;
    if ($f >= 65) return 'var(--ef-green)';
    if ($f >= 50) return 'var(--ef-cyan)';
    if ($f >= 35) return 'var(--ef-yellow)';
    return 'var(--ef-text-2)';
}

/**
 * Retourne une classe CSS "stat-hot" si la valeur est notable (au-dessus du seuil).
 * Sinon retourne string vide. Permet le pulse vert néon sur les chiffres importants.
 */
function hot_class(mixed $v, float $threshold, bool $reverse = false): string {
    if ($v === null || !is_numeric($v)) return '';
    $f = (float)$v;
    if ($reverse) return $f <= $threshold ? 'stat-hot' : '';
    return $f >= $threshold ? 'stat-hot' : '';
}

$highlights = $match['highlights'] ? json_decode($match['highlights'], true) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>📊 <?= htmlspecialchars($match['home_name']) ?> vs <?= htmlspecialchars($match['away_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/dashboard.css">
  <style>
    .match-back {
      color: var(--ef-text-3);
      text-decoration: none;
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.85rem;
      margin-bottom: 1rem;
      display: inline-block;
    }
    .match-back:hover { color: var(--ef-cyan); }

    .match-hero {
      background: var(--ef-bg-card);
      border: 1px solid var(--ef-border);
      border-radius: 14px;
      padding: 2rem;
      margin-bottom: 2rem;
      position: relative;
    }
    .match-hero::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--ef-pink), var(--ef-cyan));
      border-radius: 14px 14px 0 0;
    }

    .match-meta {
      display: flex;
      gap: 1rem;
      align-items: center;
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.85rem;
      color: var(--ef-text-3);
      margin-bottom: 1rem;
    }

    .match-teams-big {
      font-family: 'Orbitron', sans-serif;
      font-size: 1.8rem;
      font-weight: 900;
      margin-bottom: 1rem;
    }
    .match-teams-big .vs {
      color: var(--ef-pink);
      font-style: italic;
      font-size: 1.2rem;
      font-weight: 400;
      margin: 0 1rem;
    }

    .match-lambdas {
      display: flex;
      gap: 1rem;
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.95rem;
    }
    .match-lambdas strong { color: var(--ef-text); }

    .highlights-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 1.5rem;
    }
    .highlight-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.4rem 0.8rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-family: 'Rajdhani', sans-serif;
      font-weight: 600;
    }
    .highlight-pill.strong  { background: rgba(0,255,157,0.15); color: var(--ef-green); border: 1px solid rgba(0,255,157,0.3); }
    .highlight-pill.warning { background: rgba(255,184,0,0.15); color: var(--ef-yellow); border: 1px solid rgba(255,184,0,0.3); }
    .highlight-pill.info    { background: rgba(0,212,255,0.15); color: var(--ef-cyan); border: 1px solid rgba(0,212,255,0.3); }

    /* Stats grid */
    .stats-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }

    .stats-card {
      background: var(--ef-bg-card);
      border: 1px solid var(--ef-border);
      border-radius: 14px;
      padding: 1.25rem 1.5rem;
    }
    .stats-card h3 {
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 0.15em;
      color: var(--ef-cyan);
      font-size: 1.1rem;
      margin-bottom: 1rem;
    }
    .stat-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.5rem 0;
      border-bottom: 1px dashed rgba(255,255,255,0.05);
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.85rem;
    }
    .stat-line:last-child { border-bottom: none; }
    .stat-line .label { color: var(--ef-text-2); }
    .stat-line .value {
      font-family: 'Orbitron', sans-serif;
      font-weight: 700;
      color: var(--ef-text);
    }

    /* Stats hot — neon pulse pour valeurs notables */
    .stat-line .value.stat-hot,
    .stat-line .value .stat-hot {
      color: #00ff9d !important;
      text-shadow:
        0 0 8px rgba(0, 255, 157, 0.7),
        0 0 16px rgba(0, 255, 157, 0.5),
        0 0 24px rgba(0, 255, 157, 0.3);
      animation: stat-hot-pulse 1.8s ease-in-out infinite;
    }

    @keyframes stat-hot-pulse {
      0%, 100% {
        opacity: 1;
        text-shadow:
          0 0 8px rgba(0, 255, 157, 0.7),
          0 0 16px rgba(0, 255, 157, 0.5);
      }
      50% {
        opacity: 0.85;
        text-shadow:
          0 0 14px rgba(0, 255, 157, 1),
          0 0 24px rgba(0, 255, 157, 0.7),
          0 0 32px rgba(0, 255, 157, 0.4);
      }
    }

    /* Candidats list */
    .cands-card {
      background: var(--ef-bg-card);
      border: 1px solid var(--ef-border);
      border-radius: 14px;
      padding: 1.25rem 1.5rem;
      margin-bottom: 2rem;
    }
    .cands-card h3 {
      font-family: 'Bebas Neue', sans-serif;
      letter-spacing: 0.15em;
      color: var(--ef-pink);
      font-size: 1.1rem;
      margin-bottom: 1rem;
    }

    .cand-row {
      display: grid;
      grid-template-columns: auto 1fr auto auto auto auto;
      gap: 1rem;
      align-items: center;
      padding: 0.7rem 0.8rem;
      background: var(--ef-bg-cand);
      border-left: 3px solid;
      border-radius: 8px;
      margin-bottom: 0.5rem;
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.85rem;
    }
    .cand-row.auto    { border-left-color: var(--ef-green); }
    .cand-row.manual  { border-left-color: var(--ef-yellow); }
    .cand-row.warn    { border-left-color: var(--ef-red); }
    .cand-row.neutral { border-left-color: rgba(255,255,255,0.15); }

    .cand-row .market { font-family: 'Orbitron', sans-serif; font-weight: 700; }
    .cand-row .num { text-align: right; font-weight: 700; }

    @media (max-width: 768px) {
      .cand-row {
        grid-template-columns: auto 1fr auto auto;
        gap: 0.5rem;
      }
      .cand-row .ev, .cand-row .probas { display: none; }
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

  <a href="./" class="match-back">← Retour au dashboard</a>

  <!-- HERO -->
  <article class="match-hero">
    <div class="match-meta">
      <?= flag_emoji($match['league_country'] ?? '') ?>
      <strong style="color: var(--ef-text-2)"><?= htmlspecialchars($match['league_name']) ?></strong>
      <?php if ($match['league_tier']): ?>
        <span style="background: rgba(0,212,255,0.1); color: var(--ef-cyan); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
          <?= htmlspecialchars($match['league_tier']) ?>
        </span>
      <?php endif ?>
      <span>·</span>
      <span style="color: var(--ef-cyan)"><?= fmt_kickoff_full($match['kickoff_utc']) ?></span>
    </div>

    <div class="match-teams-big">
      <?= team_logo_img($match['home_name'], 44) ?><?= htmlspecialchars($match['home_name']) ?>
      <span class="vs">vs</span>
      <?= team_logo_img($match['away_name'], 44) ?><?= htmlspecialchars($match['away_name']) ?>
    </div>

    <div class="match-lambdas">
      <span>λ<sub>home</sub> <strong><?= number_format((float)$match['lambda_home'], 2) ?></strong></span>
      <span style="color: rgba(255,255,255,0.15)">│</span>
      <span>λ<sub>away</sub> <strong><?= number_format((float)$match['lambda_away'], 2) ?></strong></span>
      <span style="color: rgba(255,255,255,0.15)">│</span>
      <span>λ<sub>total</sub> <strong><?= number_format((float)$match['lambda_total'], 2) ?></strong></span>
    </div>

    <?php if (!empty($highlights)): ?>
      <div class="highlights-bar">
        <?php foreach ($highlights as $h):
          $level = $h['level'] ?? 'info';
        ?>
          <div class="highlight-pill <?= htmlspecialchars($level) ?>" title="<?= htmlspecialchars($h['reason'] ?? '') ?>">
            <span><?= htmlspecialchars($h['icon'] ?? '') ?></span>
            <span><?= htmlspecialchars($h['label'] ?? '') ?></span>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </article>

  <!-- STATS GRID -->
  <?php
    $tm = $match['team_metrics'] ? json_decode($match['team_metrics'], true) : [];
  ?>

  <!-- STATS PAR EQUIPE -->
  <?php if (!empty($tm)): ?>
  <div style="background: var(--ef-bg-card); border: 1px solid var(--ef-border); border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
    <h3 style="font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.15em; color: var(--ef-pink); font-size: 1.2rem; margin-bottom: 1rem;">
      🎯 Profil par équipe (calculé depuis le modèle DC)
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">

      <!-- HOME -->
      <div>
        <h4 style="font-family: 'Rajdhani', sans-serif; color: var(--ef-cyan); font-size: 1rem; margin-bottom: 0.6rem; text-transform: uppercase; letter-spacing: 0.1em;">
          <?= team_logo_img($match['home_name'], 28) ?>🏠 <?= htmlspecialchars($match['home_name']) ?> (domicile)
        </h4>
        <div class="stat-line">
          <span class="label">Marque ≥ 1 but (FT)</span>
          <span class="value <?= hot_class($tm['home_scores_ft'] ?? null, 75) ?>" style="color: var(--ef-green)"><?= number_format($tm['home_scores_ft'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Marque ≥ 2 buts (FT)</span>
          <span class="value <?= hot_class($tm['home_scores_2plus_ft'] ?? null, 50) ?>"><?= number_format($tm['home_scores_2plus_ft'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Encaisse ≥ 1 but (FT)</span>
          <span class="value <?= hot_class($tm['home_concedes_ft'] ?? null, 70) ?>" style="color: var(--ef-yellow)"><?= number_format($tm['home_concedes_ft'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Marque en 1ère MT</span>
          <span class="value <?= hot_class($tm['home_scores_ht'] ?? null, 50) ?>"><?= number_format($tm['home_scores_ht'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Encaisse en 1ère MT</span>
          <span class="value <?= hot_class($tm['home_concedes_ht'] ?? null, 50) ?>" style="color: var(--ef-yellow)"><?= number_format($tm['home_concedes_ht'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Marque en 2ème MT</span>
          <span class="value <?= hot_class($tm['home_scores_2h'] ?? null, 55) ?>"><?= number_format($tm['home_scores_2h'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Encaisse en 2ème MT</span>
          <span class="value <?= hot_class($tm['home_concedes_2h'] ?? null, 55) ?>" style="color: var(--ef-yellow)"><?= number_format($tm['home_concedes_2h'] ?? 0, 0) ?>%</span>
        </div>
      </div>

      <!-- AWAY -->
      <div>
        <h4 style="font-family: 'Rajdhani', sans-serif; color: var(--ef-cyan); font-size: 1rem; margin-bottom: 0.6rem; text-transform: uppercase; letter-spacing: 0.1em;">
          <?= team_logo_img($match['away_name'], 28) ?>✈️ <?= htmlspecialchars($match['away_name']) ?> (extérieur)
        </h4>
        <div class="stat-line">
          <span class="label">Marque ≥ 1 but (FT)</span>
          <span class="value <?= hot_class($tm['away_scores_ft'] ?? null, 70) ?>" style="color: var(--ef-green)"><?= number_format($tm['away_scores_ft'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Marque ≥ 2 buts (FT)</span>
          <span class="value <?= hot_class($tm['away_scores_2plus_ft'] ?? null, 45) ?>"><?= number_format($tm['away_scores_2plus_ft'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Encaisse ≥ 1 but (FT)</span>
          <span class="value <?= hot_class($tm['away_concedes_ft'] ?? null, 75) ?>" style="color: var(--ef-yellow)"><?= number_format($tm['away_concedes_ft'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Marque en 1ère MT</span>
          <span class="value <?= hot_class($tm['away_scores_ht'] ?? null, 45) ?>"><?= number_format($tm['away_scores_ht'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Encaisse en 1ère MT</span>
          <span class="value <?= hot_class($tm['away_concedes_ht'] ?? null, 50) ?>" style="color: var(--ef-yellow)"><?= number_format($tm['away_concedes_ht'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Marque en 2ème MT</span>
          <span class="value <?= hot_class($tm['away_scores_2h'] ?? null, 50) ?>"><?= number_format($tm['away_scores_2h'] ?? 0, 0) ?>%</span>
        </div>
        <div class="stat-line">
          <span class="label">Encaisse en 2ème MT</span>
          <span class="value <?= hot_class($tm['away_concedes_2h'] ?? null, 55) ?>" style="color: var(--ef-yellow)"><?= number_format($tm['away_concedes_2h'] ?? 0, 0) ?>%</span>
        </div>
      </div>

    </div>
    <p style="margin-top: 1rem; color: var(--ef-text-3); font-family: 'Share Tech Mono', monospace; font-size: 0.7rem;">
      Pourcentages dérivés du modèle Dixon-Coles calibré sur les 540 derniers jours.
      ≠ stats historiques réelles de l'équipe sur ses derniers matchs.
    </p>
  </div>
  <?php endif ?>

  <div class="stats-grid">

    <!-- xG + PPG -->
    <div class="stats-card">
      <h3>📈 xG & forme</h3>
      <div class="stat-line">
        <span class="label">xG pré-match <?= htmlspecialchars($match['home_name']) ?></span>
        <span class="value <?= hot_class($match['home_xg_prematch'], 1.7) ?>" style="color: var(--ef-cyan)"><?= num_or_dash($match['home_xg_prematch']) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">xG pré-match <?= htmlspecialchars($match['away_name']) ?></span>
        <span class="value <?= hot_class($match['away_xg_prematch'], 1.7) ?>" style="color: var(--ef-cyan)"><?= num_or_dash($match['away_xg_prematch']) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">xG total</span>
        <span class="value">
          <?php if ($match['home_xg_prematch'] && $match['away_xg_prematch']):
            $xg_total_val = (float)$match['home_xg_prematch'] + (float)$match['away_xg_prematch'];
          ?>
            <span class="<?= hot_class($xg_total_val, 3.0) ?>"><?= number_format($xg_total_val, 2) ?></span>
            <span style="font-size: 0.7em; color: var(--ef-text-3);">
              vs DC <?= number_format((float)$match['lambda_total'], 2) ?>
            </span>
          <?php else: ?>—<?php endif ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">PPG <?= htmlspecialchars($match['home_name']) ?> (forme)</span>
        <span class="value <?= hot_class($match['home_ppg'], 2.0) ?>"><?= num_or_dash($match['home_ppg']) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">PPG <?= htmlspecialchars($match['away_name']) ?> (forme)</span>
        <span class="value <?= hot_class($match['away_ppg'], 2.0) ?>"><?= num_or_dash($match['away_ppg']) ?></span>
      </div>
    </div>

    <!-- BTTS + Over -->
    <div class="stats-card">
      <h3>⚽ Buts attendus</h3>
      <div class="stat-line">
        <span class="label">Moyenne buts (avg potential)</span>
        <span class="value <?= hot_class($match['avg_potential'], 3.0) ?>" style="color: var(--ef-pink)"><?= num_or_dash($match['avg_potential']) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">BTTS potential</span>
        <span class="value <?= hot_class($match['btts_potential'], 65) ?>" style="color: <?= color_pct($match['btts_potential']) ?>">
          <?= pct_or_dash($match['btts_potential']) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Over 2.5 potential</span>
        <span class="value <?= hot_class($match['o25_potential'], 65) ?>" style="color: <?= color_pct($match['o25_potential']) ?>">
          <?= pct_or_dash($match['o25_potential']) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Over 3.5 potential</span>
        <span class="value <?= hot_class($match['o35_potential'], 55) ?>" style="color: <?= color_pct($match['o35_potential']) ?>">
          <?= pct_or_dash($match['o35_potential']) ?>
        </span>
      </div>
    </div>

    <!-- HT / 2H -->
    <div class="stats-card">
      <h3>⏱ Mi-temps / 2nde période</h3>
      <div class="stat-line">
        <span class="label">Over 0.5 buts 1ère MT</span>
        <span class="value <?= hot_class($match['o05ht_potential'] ?? null, 60) ?>" style="color: <?= color_pct($match['o05ht_potential'] ?? null) ?>">
          <?= pct_or_dash($match['o05ht_potential'] ?? null) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Over 1.5 buts 1ère MT</span>
        <span class="value <?= hot_class($match['o15ht_potential'] ?? null, 30) ?>" style="color: <?= color_pct($match['o15ht_potential'] ?? null) ?>">
          <?= pct_or_dash($match['o15ht_potential'] ?? null) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Over 0.5 buts 2ème MT</span>
        <span class="value <?= hot_class($match['o05_2h_potential'] ?? null, 65) ?>" style="color: <?= color_pct($match['o05_2h_potential'] ?? null) ?>">
          <?= pct_or_dash($match['o05_2h_potential'] ?? null) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Over 1.5 buts 2ème MT</span>
        <span class="value <?= hot_class($match['o15_2h_potential'] ?? null, 35) ?>" style="color: <?= color_pct($match['o15_2h_potential'] ?? null) ?>">
          <?= pct_or_dash($match['o15_2h_potential'] ?? null) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">BTTS 1ère MT</span>
        <span class="value <?= hot_class($match['btts_fhg_potential'], 30) ?>" style="color: <?= color_pct($match['btts_fhg_potential']) ?>">
          <?= pct_or_dash($match['btts_fhg_potential']) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">BTTS 2ème MT</span>
        <span class="value <?= hot_class($match['btts_2hg_potential'], 30) ?>" style="color: <?= color_pct($match['btts_2hg_potential']) ?>">
          <?= pct_or_dash($match['btts_2hg_potential']) ?>
        </span>
      </div>
    </div>

    <!-- Corners + Cards -->
    <div class="stats-card">
      <h3>🚩 Corners & cartons</h3>
      <div class="stat-line">
        <span class="label">Moyenne corners</span>
        <span class="value <?= hot_class($match['corners_potential'], 10) ?>" style="color: var(--ef-cyan)"><?= num_or_dash($match['corners_potential'], 1) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">Corners Over 8.5</span>
        <span class="value <?= hot_class($match['corners_o85_potential'], 65) ?>" style="color: <?= color_pct($match['corners_o85_potential']) ?>">
          <?= pct_or_dash($match['corners_o85_potential']) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Corners Over 9.5</span>
        <span class="value <?= hot_class($match['corners_o95_potential'], 55) ?>" style="color: <?= color_pct($match['corners_o95_potential']) ?>">
          <?= pct_or_dash($match['corners_o95_potential']) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Corners Over 10.5</span>
        <span class="value <?= hot_class($match['corners_o105_potential'], 45) ?>" style="color: <?= color_pct($match['corners_o105_potential']) ?>">
          <?= pct_or_dash($match['corners_o105_potential']) ?>
        </span>
      </div>
      <div class="stat-line">
        <span class="label">Moyenne cartons</span>
        <span class="value <?= hot_class($match['cards_potential'], 5.5) ?>" style="color: var(--ef-yellow)"><?= num_or_dash($match['cards_potential'], 1) ?></span>
      </div>
    </div>

  </div>

  <!-- CANDIDATES -->
  <div class="cands-card">
    <h3>🎯 Candidats analysés (<?= count($candidates) ?>)</h3>

    <?php if (empty($candidates)): ?>
      <p style="color: var(--ef-text-3); padding: 1rem 0;">Aucun candidat (tous les marchés ont été filtrés ou cotes manquantes).</p>
    <?php else: ?>
      <?php foreach ($candidates as $c): ?>
        <div class="cand-row <?= htmlspecialchars($c['status']) ?>">
          <div><?= status_emoji($c['status']) ?></div>
          <div>
            <div class="market"><?= htmlspecialchars($c['market']) ?></div>
            <div style="color: var(--ef-text-3); font-size: 0.7rem;"><?= htmlspecialchars($c['market_group']) ?></div>
          </div>
          <div class="num">
            <div style="color: var(--ef-text-3); font-size: 0.65rem;">COTE</div>
            <div style="color: var(--ef-cyan); font-family: 'Orbitron'; font-size: 1rem;"><?= number_format((float)$c['odds'], 2) ?></div>
          </div>
          <div class="num ev">
            <div style="color: var(--ef-text-3); font-size: 0.65rem;">EV</div>
            <div style="color: var(--ef-green); font-family: 'Orbitron'; font-size: 0.95rem;">
              <?= ((float)$c['ev'] >= 0 ? '+' : '') . number_format((float)$c['ev'] * 100, 1) ?>%
            </div>
          </div>
          <div class="num probas">
            <div style="color: var(--ef-text-3); font-size: 0.65rem;">M / D</div>
            <div style="font-size: 0.8rem;"><?= number_format((float)$c['model_proba'] * 100, 1) ?>% / <?= number_format((float)$c['devig_proba'] * 100, 1) ?>%</div>
          </div>
          <div class="num" style="position: relative;">
            <div style="color: var(--ef-text-3); font-size: 0.65rem;">CONV<?php if (!empty($c['conv_tier'])): ?> · <?= htmlspecialchars($c['conv_tier']) ?><?php endif ?></div>
            <div
              style="color: var(--ef-pink); font-family: 'Orbitron'; font-size: 1rem; cursor: pointer; <?php if (!empty($c['conv_auto_eligible'])): ?>text-shadow: 0 0 6px rgba(255,45,120,0.6);<?php endif ?>"
              <?php if (!empty($c['conv_breakdown'])): ?>onclick="toggleBreakdown(this)"<?php endif ?>
              title="<?php
                if (!empty($c['conv_breakdown'])) {
                  $bd = json_decode($c['conv_breakdown'], true);
                  if (is_array($bd)) {
                    $parts = [];
                    foreach (['ev','coherence','odds','lambda','status'] as $k) {
                      if (isset($bd[$k])) $parts[] = strtoupper($k) . " " . $bd[$k]['got'] . "/" . $bd[$k]['max'];
                    }
                    if (isset($bd['footystats'])) $parts[] = "FS " . $bd['footystats']['got'];
                    echo htmlspecialchars(implode(' | ', $parts));
                  }
                }
              ?>"
            ><?= (int)$c['conviction'] ?></div>
            <?php if (!empty($c['conv_flags'])):
              $flags = json_decode($c['conv_flags'], true);
              if (is_array($flags) && !empty($flags)):
            ?>
              <div style="font-size: 0.55rem; color: var(--ef-text-3); margin-top: 0.15rem; line-height: 1;">
                <?php foreach ($flags as $f):
                  $is_neg = strpos($f, 'divergent') !== false;
                ?>
                  <span style="color: <?= $is_neg ? 'var(--ef-yellow)' : 'var(--ef-green)' ?>; display: inline-block; margin-right: 0.3em;">
                    <?= $is_neg ? '⚠' : '✓' ?>
                  </span>
                <?php endforeach ?>
              </div>
            <?php endif; endif ?>
          </div>
        </div>

        <?php if (!empty($c['conv_breakdown'])):
          $bd = json_decode($c['conv_breakdown'], true);
          if (is_array($bd)):
        ?>
          <div class="conv-breakdown" style="display: none; margin: 0.4rem 0 0.6rem 0; padding: 0.7rem; background: rgba(0,0,0,0.4); border: 1px solid var(--ef-border); border-radius: 6px; font-family: 'Share Tech Mono', monospace; font-size: 0.75rem;">
            <div style="color: var(--ef-cyan); margin-bottom: 0.4rem; font-weight: 600;">
              📊 Breakdown conviction (tier <?= htmlspecialchars($c['conv_tier'] ?? '?') ?>)
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.35rem;">
              <?php foreach ([
                'ev' => 'EV',
                'coherence' => 'Cohérence',
                'odds' => 'Cote',
                'lambda' => 'λ total',
                'status' => 'Status',
              ] as $key => $label):
                if (!isset($bd[$key])) continue;
                $got = $bd[$key]['got'];
                $max = $bd[$key]['max'];
                $pct = $max > 0 ? ($got / $max) : 0;
                $color = $pct >= 0.9 ? '#00ff9d' : ($pct >= 0.6 ? '#00d4ff' : ($pct >= 0.3 ? '#ffd166' : '#ff5050'));
              ?>
                <div>
                  <div style="color: var(--ef-text-3); font-size: 0.65rem;"><?= $label ?></div>
                  <div style="color: <?= $color ?>; font-size: 0.95rem;"><?= $got ?><span style="color: var(--ef-text-3); font-size: 0.7rem;">/<?= $max ?></span></div>
                </div>
              <?php endforeach ?>
              <?php if (isset($bd['footystats'])):
                $fs_got = $bd['footystats']['got'];
                $fs_color = $fs_got > 0 ? '#00ff9d' : ($fs_got < 0 ? '#ff5050' : 'var(--ef-text-3)');
              ?>
                <div>
                  <div style="color: var(--ef-text-3); font-size: 0.65rem;">FootyStats</div>
                  <div style="color: <?= $fs_color ?>; font-size: 0.95rem;"><?= $fs_got > 0 ? '+' : '' ?><?= $fs_got ?></div>
                </div>
              <?php endif ?>
            </div>
            <div style="margin-top: 0.5rem; color: var(--ef-text-3); font-size: 0.7rem;">
              Base <?= $bd['base'] ?? '?' ?> + FS <?= $bd['footystats']['got'] ?? 0 ?> = total brut <?= $bd['total'] ?? '?' ?>
              <?php if (!empty($c['conv_auto_eligible'])): ?>
                · <span style="color: var(--ef-pink); font-weight: 600;">🎯 AUTO-ELIGIBLE</span>
              <?php endif ?>
            </div>
          </div>
        <?php endif; endif ?>
      <?php endforeach ?>
    <?php endif ?>
  </div>

  <!-- ─────────────────────────────── ANALYSE TOP 3 BUTEURS (Claude Sonnet 4.6 + Web Search) ─── -->
  <section class="ef-scorers-section" id="ef-scorers-section">
    <div class="ef-scorers-header">
      <h2><span class="ef-scorers-icon">🎯</span> TOP 3 BUTEURS PROBABLES</h2>
      <p>Analyse SNIPER 100pts par Claude Sonnet 4.6 + recherches web en temps reel</p>
    </div>

    <!-- Init state : juste un gros bouton -->
    <div id="ef-scorers-idle">
      <button class="ef-scorers-launch-btn" onclick="launchScorersAnalysis(false)">
        <span class="ef-scorers-launch-icon">⚡</span>
        <span class="ef-scorers-launch-label">LANCER L'ANALYSE</span>
        <span class="ef-scorers-launch-sub">Claude Sonnet 4.6 + web search · ~$0.03</span>
      </button>
    </div>

    <!-- Streaming state : panneau temps reel -->
    <div id="ef-scorers-stream" style="display:none;">
      <div class="ef-stream-grid">
        <div class="ef-stream-status">
          <div class="ef-stream-pulse"></div>
          <div class="ef-stream-status-text" id="ef-stream-status-text">Initialisation...</div>
          <div class="ef-stream-timer" id="ef-stream-timer">0s</div>
        </div>
        <div class="ef-stream-meter">
          <div class="ef-stream-meter-label">TOKENS</div>
          <div class="ef-stream-meter-value">
            <span id="ef-stream-tokens-in">0</span> in / <span id="ef-stream-tokens-out">0</span> out
          </div>
        </div>
      </div>
      <div class="ef-stream-searches">
        <div class="ef-stream-searches-label">🌐 RECHERCHES WEB</div>
        <ul class="ef-stream-searches-list" id="ef-stream-searches-list"></ul>
      </div>
    </div>

    <!-- Error state -->
    <div id="ef-scorers-error" style="display:none;" class="ef-scorers-error"></div>

    <!-- Result state -->
    <div id="ef-scorers-result" style="display:none;"></div>
  </section>

  <footer class="ef-footer">
    <p>StratEdge Edge Finder · Page détail match</p>
  </footer>
</div>
</div>

<script>
  // Toggle l'affichage du breakdown au clic sur le score CONV
  function toggleBreakdown(scoreEl) {
    // Trouve le parent .candidate-row et son .conv-breakdown frere
    let row = scoreEl.closest('.candidate-row') || scoreEl.parentElement.parentElement;
    if (!row) return;
    let bd = row.nextElementSibling;
    while (bd && !bd.classList.contains('conv-breakdown')) {
      bd = bd.nextElementSibling;
      if (!bd || bd.classList.contains('candidate-row')) {
        bd = null;
        break;
      }
    }
    if (bd) {
      bd.style.display = (bd.style.display === 'none' || !bd.style.display) ? 'block' : 'none';
    }
  }

  // ===== Analyse TOP 3 buteurs via Claude Sonnet 4.6 + Web Search (stream SSE) =====
  const MATCH_ID = <?= (int)$match_id ?>;
  let streamTimer = null;
  let streamStartTs = 0;
  let eventSource = null;

  function launchScorersAnalysis(force) {
    document.getElementById('ef-scorers-idle').style.display = 'none';
    document.getElementById('ef-scorers-error').style.display = 'none';
    document.getElementById('ef-scorers-result').style.display = 'none';
    document.getElementById('ef-scorers-stream').style.display = 'block';
    document.getElementById('ef-stream-searches-list').innerHTML = '';
    document.getElementById('ef-stream-tokens-in').textContent = '0';
    document.getElementById('ef-stream-tokens-out').textContent = '0';
    setStatus('Connexion au flux...');

    streamStartTs = Date.now();
    streamTimer = setInterval(() => {
      const sec = Math.floor((Date.now() - streamStartTs) / 1000);
      document.getElementById('ef-stream-timer').textContent = sec + 's';
    }, 250);

    const url = './api/analyze_scorers_stream.php?match_id=' + MATCH_ID + (force ? '&force=1' : '');
    eventSource = new EventSource(url, {withCredentials: true});

    eventSource.addEventListener('status', (e) => {
      const d = JSON.parse(e.data);
      setStatus(d.message || '');
    });
    eventSource.addEventListener('tokens', (e) => {
      const d = JSON.parse(e.data);
      document.getElementById('ef-stream-tokens-in').textContent = (d.input || 0).toLocaleString('fr-FR');
      document.getElementById('ef-stream-tokens-out').textContent = (d.output_so_far || 0).toLocaleString('fr-FR');
    });
    eventSource.addEventListener('web_search', (e) => {
      const d = JSON.parse(e.data);
      const ul = document.getElementById('ef-stream-searches-list');
      const li = document.createElement('li');
      li.className = 'ef-stream-search-item';
      li.innerHTML = '<span class="ef-stream-search-arrow">▸</span> <span class="ef-stream-search-q">' +
        escapeHtml(d.query || 'recherche') + '</span>';
      ul.appendChild(li);
      requestAnimationFrame(() => li.classList.add('ef-stream-search-visible'));
    });
    eventSource.addEventListener('complete', (e) => {
      const d = JSON.parse(e.data);
      closeStream();
      renderResult(d);
    });
    eventSource.addEventListener('error', (e) => {
      let err = 'Erreur de connexion au stream';
      if (e.data) {
        try { const d = JSON.parse(e.data); err = d.message || err; } catch(_) {}
      }
      closeStream();
      const box = document.getElementById('ef-scorers-error');
      box.textContent = '❌ ' + err;
      box.style.display = 'block';
      document.getElementById('ef-scorers-idle').style.display = 'block';
    });
  }

  function closeStream() {
    if (eventSource) { eventSource.close(); eventSource = null; }
    if (streamTimer) { clearInterval(streamTimer); streamTimer = null; }
    document.getElementById('ef-scorers-stream').style.display = 'none';
  }

  function setStatus(text) {
    document.getElementById('ef-stream-status-text').textContent = text;
  }

  // ===== Rendu du resultat =====
  function renderResult(d) {
    const result = document.getElementById('ef-scorers-result');
    const scorers = d.scorers || [];
    const warnings = (d.warnings || []).filter(w => w && (w.text || w));
    const isCached = d.cached;
    const cost = d.cost_usd || 0;
    const duration = d.duration_seconds || 0;
    const searches = d.searches || [];

    let html = '';

    // Bannière meta
    html += '<div class="ef-result-meta">';
    if (isCached) {
      html += '<div class="ef-meta-cached">📦 Analyse cachee · genere le ' + escapeHtml(d.generated_at || '') +
        ' · <a href="#" onclick="event.preventDefault(); launchScorersAnalysis(true)">⟳ Regenerer</a></div>';
    } else {
      html += '<div class="ef-meta-fresh">✨ Fraichement genere · ' +
        (d.tokens_input || 0).toLocaleString('fr-FR') + ' in + ' +
        (d.tokens_output || 0).toLocaleString('fr-FR') + ' out · ' +
        (d.web_searches_count || 0) + ' recherches web · ' +
        duration + 's · $' + (cost.toFixed(3)) + '</div>';
    }
    html += '</div>';

    // Match summary
    if (d.match_summary) {
      html += '<div class="ef-result-summary">' + escapeHtml(d.match_summary) + '</div>';
    }

    // Warnings critiques en bandeau (level=critical en premier)
    const critWarnings = warnings.filter(w => (w.level || '').toLowerCase() === 'critical');
    if (critWarnings.length) {
      html += '<div class="ef-warnings-critical">';
      html += '<div class="ef-warnings-critical-title">🚨 ALERTES CRITIQUES</div>';
      html += '<ul>';
      for (const w of critWarnings) html += '<li>' + escapeHtml(w.text || w) + '</li>';
      html += '</ul></div>';
    }

    // TOP 3 buteurs - grid
    html += '<div class="ef-scorers-podium">';
    for (let i = 0; i < scorers.length; i++) {
      html += renderScorerCard(scorers[i], i);
    }
    html += '</div>';

    // Autres warnings (warning + info)
    const otherWarnings = warnings.filter(w => (w.level || '').toLowerCase() !== 'critical');
    if (otherWarnings.length) {
      html += '<div class="ef-warnings-other">';
      html += '<div class="ef-warnings-other-title">⚠️ Notes & avertissements</div>';
      html += '<ul>';
      for (const w of otherWarnings) {
        const lvl = (w.level || 'info').toLowerCase();
        const icon = lvl === 'warning' ? '⚠️' : 'ℹ️';
        html += '<li class="ef-warn-' + lvl + '">' + icon + ' ' + escapeHtml(w.text || w) + '</li>';
      }
      html += '</ul></div>';
    }

    if (d.freshness_note) {
      html += '<div class="ef-freshness-note">🕐 ' + escapeHtml(d.freshness_note) + '</div>';
    }

    // Searches recap (collapsable)
    if (searches.length) {
      html += '<details class="ef-searches-recap">';
      html += '<summary>🌐 ' + searches.length + ' recherches web effectuees</summary>';
      html += '<ul>';
      for (const s of searches) {
        html += '<li><code>' + escapeHtml(s.query || '') + '</code></li>';
      }
      html += '</ul></details>';
    }

    // Markdown full (collapsable)
    if (d.markdown_full) {
      html += '<details class="ef-markdown-full">';
      html += '<summary>📜 Voir l\'analyse complete (markdown)</summary>';
      html += '<pre>' + escapeHtml(d.markdown_full) + '</pre>';
      html += '</details>';
    }

    result.innerHTML = html;
    result.style.display = 'block';
  }

  function renderScorerCard(s, idx) {
    const teamLabel = s.team === 'home' ? '🏠 Domicile' : '✈️ Exterieur';
    const teamColor = s.team === 'home' ? '#00ff9d' : '#00d4ff';
    const podiumIcons = ['🥇', '🥈', '🥉'];
    const stars = '⭐'.repeat(Math.max(1, Math.min(5, s.stars || 3)));
    const sniperScore = s.sniper_score || 0;
    const verdict = s.verdict || '';
    const verdictClass = verdict.toLowerCase().includes('hot') || verdict.toLowerCase().includes('bet') ?
      'ef-verdict-bet' : (verdict.toLowerCase().includes('skip') ? 'ef-verdict-skip' : 'ef-verdict-neutral');

    // Speedomètre SVG du score SNIPER
    const radarSvg = renderRadarChart(s.radar || {});
    const scoreSpeedometer = renderScoreSpeedometer(sniperScore);

    let html = '<div class="ef-scorer-card-pro ef-scorer-rank-' + (idx + 1) + '">';

    // Header podium
    html += '<div class="ef-scorer-podium-badge">' + (podiumIcons[idx] || ('#' + (idx+1))) + '</div>';

    // Hero block
    html += '<div class="ef-scorer-hero">';
    html += '  <div class="ef-scorer-hero-info">';
    html += '    <div class="ef-scorer-name-big">' + escapeHtml(s.name || '?') + '</div>';
    html += '    <div class="ef-scorer-meta">';
    html += '      <span class="ef-scorer-team-tag" style="color:' + teamColor + '">' + teamLabel + ' · ' + escapeHtml(s.team_label || '') + '</span>';
    if (s.position) html += '      <span class="ef-scorer-position">' + escapeHtml(s.position) + '</span>';
    html += '    </div>';
    html += '    <div class="ef-scorer-stars">' + stars + '</div>';
    if (verdict) html += '    <div class="ef-scorer-verdict ' + verdictClass + '">' + escapeHtml(verdict) + '</div>';
    html += '  </div>';
    html += '  <div class="ef-scorer-hero-score">' + scoreSpeedometer + '</div>';
    html += '</div>';

    // Stats marche
    html += '<div class="ef-scorer-market">';
    if (s.odds_estimated) html += '<div class="ef-market-cell"><div class="ef-market-label">COTE EST.</div><div class="ef-market-value">@' + Number(s.odds_estimated).toFixed(2) + '</div></div>';
    if (s.p_buteur_pct != null) html += '<div class="ef-market-cell"><div class="ef-market-label">P BUTEUR</div><div class="ef-market-value">' + Math.round(s.p_buteur_pct) + '%</div></div>';
    if (s.ev_estimated != null) {
      const ev = Number(s.ev_estimated);
      const evColor = ev >= 5 ? '#00ff9d' : (ev >= 0 ? '#ffd700' : '#ff5555');
      html += '<div class="ef-market-cell"><div class="ef-market-label">EV</div><div class="ef-market-value" style="color:' + evColor + '">' + (ev >= 0 ? '+' : '') + ev.toFixed(1) + '%</div></div>';
    }
    if (s.stake_pct_bk != null) html += '<div class="ef-market-cell"><div class="ef-market-label">MISE</div><div class="ef-market-value">' + Number(s.stake_pct_bk).toFixed(1) + '% BK</div></div>';
    html += '</div>';

    // Radar chart
    html += '<div class="ef-scorer-radar">' + radarSvg + '</div>';

    // Key stats grid
    const ks = s.key_stats || {};
    html += '<div class="ef-scorer-keystats">';
    html += statRow('npxG/90', ks.npxg_per90, '≥0.35');
    html += statRow('Tirs/90', ks.shots_per90, '≥2.0');
    html += statRow('TC/90', ks.sot_per90, '≥1.0');
    html += statRow('Touches box', ks.touches_box, '≥5.0');
    html += statRow('Big chances', ks.big_chances_per_match, '≥0.5');
    html += statRow('Buts 5 derniers', ks.goals_last5, null);
    if (ks.is_penalty_taker) html += '<div class="ef-keystat-flag ef-flag-pen">⚽ TIREUR DE PÉNO</div>';
    if (ks.is_freekick_taker) html += '<div class="ef-keystat-flag ef-flag-fk">🎯 COUPS FRANCS</div>';
    if (ks.loi_de_lex) html += '<div class="ef-keystat-flag ef-flag-ex">🔁 LOI DE L\'EX (+10 SNIPER)</div>';
    html += '</div>';

    // Reasoning + Devil
    if (s.reasoning) html += '<div class="ef-scorer-reasoning"><div class="ef-section-label">📝 ANALYSE</div><p>' + escapeHtml(s.reasoning) + '</p></div>';
    if (s.devil_advocate) html += '<div class="ef-scorer-devil"><div class="ef-section-label">😈 DEVIL\'S ADVOCATE</div><p>' + escapeHtml(s.devil_advocate) + '</p></div>';

    // Matchup factors
    const mf = s.matchup_factors;
    if (mf) {
      html += '<details class="ef-scorer-matchup"><summary>⚔️ Facteurs matchup</summary>';
      html += '<ul>';
      if (mf.adv_xga_per90 != null) html += '<li>xGA adverse / 90 : <strong>' + Number(mf.adv_xga_per90).toFixed(2) + '</strong></li>';
      if (Array.isArray(mf.adv_dc_out) && mf.adv_dc_out.length) html += '<li>DC adverses out : <strong>' + mf.adv_dc_out.map(escapeHtml).join(', ') + '</strong></li>';
      if (mf.adv_gk_out) html += '<li>⚠️ Gardien adverse titulaire absent</li>';
      if (mf.adv_style) html += '<li>Style adverse : ' + escapeHtml(mf.adv_style) + '</li>';
      html += '</ul></details>';
    }

    html += '</div>';
    return html;
  }

  function statRow(label, value, threshold) {
    if (value == null || value === '') return '';
    const valStr = (typeof value === 'number') ? value.toFixed(2).replace(/\.00$/, '') : String(value);
    return '<div class="ef-keystat-row"><span class="ef-keystat-label">' + label + '</span>' +
      '<span class="ef-keystat-val">' + escapeHtml(valStr) + '</span>' +
      (threshold ? '<span class="ef-keystat-th">' + escapeHtml(threshold) + '</span>' : '<span class="ef-keystat-th"></span>') +
      '</div>';
  }

  function renderScoreSpeedometer(score) {
    score = Math.max(0, Math.min(100, score));
    const radius = 45;
    const circ = 2 * Math.PI * radius;
    const filled = circ * (score / 100);
    const empty = circ - filled;
    let color = '#ff5555';
    if (score >= 90) color = '#ffd700';
    else if (score >= 75) color = '#00ff9d';
    else if (score >= 65) color = '#00d4ff';
    else if (score >= 50) color = '#ffa500';
    return `
      <svg class="ef-speedo" viewBox="0 0 120 120" width="110" height="110">
        <circle cx="60" cy="60" r="${radius}" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="8"/>
        <circle cx="60" cy="60" r="${radius}" fill="none" stroke="${color}" stroke-width="8"
                stroke-dasharray="${filled} ${empty}" stroke-dashoffset="${circ/4}"
                transform="rotate(-90 60 60)" stroke-linecap="round"
                style="filter: drop-shadow(0 0 4px ${color});"/>
        <text x="60" y="60" text-anchor="middle" dominant-baseline="central"
              font-family="Orbitron,sans-serif" font-size="28" font-weight="900" fill="${color}">${score}</text>
        <text x="60" y="86" text-anchor="middle" font-family="Share Tech Mono" font-size="9" fill="rgba(255,255,255,0.5)" letter-spacing="2">SNIPER</text>
      </svg>`;
  }

  function renderRadarChart(radar) {
    const axes = [
      {label: 'VOLUME', value: radar.volume || 0, max: 25},
      {label: 'QUALITÉ', value: radar.qualite || 0, max: 20},
      {label: 'FORME', value: radar.forme || 0, max: 15},
      {label: 'RÔLE', value: radar.role || 0, max: 15},
      {label: 'MATCHUP', value: radar.matchup || 0, max: 15},
      {label: 'PSYCHO', value: radar.psycho || 0, max: 10},
      {label: 'AVANCÉS', value: radar.avances || 0, max: 5},
    ];
    const cx = 130, cy = 130, R = 100;
    const n = axes.length;
    const angleStep = (2 * Math.PI) / n;
    // Polygones de fond (4 niveaux)
    let polyBg = '';
    for (let lvl = 1; lvl <= 4; lvl++) {
      const r = R * (lvl / 4);
      const pts = axes.map((_, i) => {
        const a = -Math.PI/2 + i * angleStep;
        return (cx + r*Math.cos(a)).toFixed(1) + ',' + (cy + r*Math.sin(a)).toFixed(1);
      }).join(' ');
      polyBg += `<polygon points="${pts}" fill="none" stroke="rgba(255,45,120,${0.05 + lvl*0.04})" stroke-width="1"/>`;
    }
    // Axes
    let axesSvg = '';
    let labelsSvg = '';
    axes.forEach((ax, i) => {
      const a = -Math.PI/2 + i * angleStep;
      const x = cx + R*Math.cos(a);
      const y = cy + R*Math.sin(a);
      axesSvg += `<line x1="${cx}" y1="${cy}" x2="${x}" y2="${y}" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>`;
      const lx = cx + (R+18)*Math.cos(a);
      const ly = cy + (R+18)*Math.sin(a);
      labelsSvg += `<text x="${lx.toFixed(1)}" y="${ly.toFixed(1)}" text-anchor="middle" dominant-baseline="central"
                          font-family="Share Tech Mono" font-size="9" fill="rgba(255,255,255,0.6)" letter-spacing="1">${ax.label}</text>`;
      const vx = cx + (R+5)*Math.cos(a);
      const vy = cy + (R-5)*Math.sin(a);
      labelsSvg += `<text x="${(cx + (R-12)*Math.cos(a)).toFixed(1)}" y="${(cy + (R-12)*Math.sin(a)).toFixed(1)}"
                          text-anchor="middle" dominant-baseline="central"
                          font-family="Orbitron" font-size="10" font-weight="700" fill="#ff2d78">${ax.value}/${ax.max}</text>`;
    });
    // Polygone data
    const dataPts = axes.map((ax, i) => {
      const a = -Math.PI/2 + i * angleStep;
      const ratio = ax.max > 0 ? Math.min(1, ax.value / ax.max) : 0;
      const r = R * ratio;
      return (cx + r*Math.cos(a)).toFixed(1) + ',' + (cy + r*Math.sin(a)).toFixed(1);
    }).join(' ');
    const dataPoly = `<polygon points="${dataPts}" fill="rgba(255,45,120,0.18)" stroke="#ff2d78" stroke-width="2"
                      style="filter: drop-shadow(0 0 8px rgba(255,45,120,0.6));"/>`;
    return `<svg class="ef-radar-svg" viewBox="0 0 260 260" width="100%" style="max-width: 320px;">
              ${polyBg}${axesSvg}${dataPoly}${labelsSvg}
            </svg>`;
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Auto-load si analyse en cache (peek leger, ne declenche pas Claude)
  (async () => {
    try {
      const r = await fetch('./api/analyze_scorers_peek.php?match_id=' + MATCH_ID);
      if (r.ok) {
        const d = await r.json();
        if (d.cached) {
          document.getElementById('ef-scorers-idle').style.display = 'none';
          renderResult(d);
        }
      }
    } catch(e) { /* ignore */ }
  })();
</script>
</body>
</html>
