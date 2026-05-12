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
    $dt = new DateTime($utc_dt, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Europe/Paris'));
    return $dt->format('d/m/Y H\hi');
}

function flag_emoji(string $country): string {
    $map = [
        'England' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'France' => '🇫🇷', 'Spain' => '🇪🇸', 'Italy' => '🇮🇹',
        'Germany' => '🇩🇪', 'Netherlands' => '🇳🇱', 'Belgium' => '🇧🇪',
        'Brazil' => '🇧🇷', 'USA' => '🇺🇸', 'Japan' => '🇯🇵',
    ];
    return $map[$country] ?? '🌍';
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
      <?= htmlspecialchars($match['home_name']) ?>
      <span class="vs">vs</span>
      <?= htmlspecialchars($match['away_name']) ?>
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
      <div class="stat-line">
        <span class="label">λ <?= htmlspecialchars($match['home_name']) ?> 1<sup>ère</sup> MT</span>
        <span class="value <?= hot_class((float)$match['lambda_home'] * 0.45, 0.8) ?>"><?= number_format((float)$match['lambda_home'] * 0.45, 2) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">λ <?= htmlspecialchars($match['away_name']) ?> 1<sup>ère</sup> MT</span>
        <span class="value <?= hot_class((float)$match['lambda_away'] * 0.45, 0.8) ?>"><?= number_format((float)$match['lambda_away'] * 0.45, 2) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">λ <?= htmlspecialchars($match['home_name']) ?> 2<sup>ème</sup> MT</span>
        <span class="value <?= hot_class((float)$match['lambda_home'] * 0.55, 1.0) ?>"><?= number_format((float)$match['lambda_home'] * 0.55, 2) ?></span>
      </div>
      <div class="stat-line">
        <span class="label">λ <?= htmlspecialchars($match['away_name']) ?> 2<sup>ème</sup> MT</span>
        <span class="value <?= hot_class((float)$match['lambda_away'] * 0.55, 1.0) ?>"><?= number_format((float)$match['lambda_away'] * 0.55, 2) ?></span>
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
          <div class="num">
            <div style="color: var(--ef-text-3); font-size: 0.65rem;">CONV</div>
            <div style="color: var(--ef-pink); font-family: 'Orbitron'; font-size: 1rem;"><?= (int)$c['conviction'] ?></div>
          </div>
        </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>

  <footer class="ef-footer">
    <p>StratEdge Edge Finder · Page détail match</p>
  </footer>
</div>
</body>
</html>
