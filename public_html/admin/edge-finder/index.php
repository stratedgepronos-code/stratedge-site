<?php
/**
 * StratEdge Edge Finder — Dashboard principal
 * URL : /panel-x9k3m/edge-finder/
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

// Auth super-admin via la session existante du site (visible uniquement par toi)
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

// =============================================================================
// Filtres GET
// =============================================================================
$filter_league   = $_GET['league'] ?? '';
$filter_group    = $_GET['group']  ?? '';
$filter_status   = $_GET['status'] ?? '';
$filter_min_conv = isset($_GET['min_conv']) ? (int)$_GET['min_conv'] : 0;
$filter_decision = $_GET['decision'] ?? 'pending';

// =============================================================================
// Stats globales (dernier import)
// =============================================================================
$lastImport = SE_Db::queryOne(
    "SELECT * FROM picks_imports ORDER BY imported_at DESC LIMIT 1"
);

// Compteurs candidats par statut (sur tous les matchs futurs uniquement)
$stats = SE_Db::queryOne(
    "SELECT
        SUM(CASE WHEN c.status = 'auto'   AND c.user_decision='pending' THEN 1 ELSE 0 END) AS n_auto_pending,
        SUM(CASE WHEN c.status = 'manual' AND c.user_decision='pending' THEN 1 ELSE 0 END) AS n_manual_pending,
        SUM(CASE WHEN c.user_decision = 'validated' THEN 1 ELSE 0 END)                     AS n_validated,
        SUM(CASE WHEN c.user_decision = 'rejected'  THEN 1 ELSE 0 END)                     AS n_rejected,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END)                          AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END)                          AS n_lost,
        COUNT(DISTINCT m.match_id)                                                          AS n_matches
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR"
);

// =============================================================================
// Liste des ligues distinctes (pour le filtre)
// =============================================================================
$leagues = SE_Db::queryAll(
    "SELECT DISTINCT m.league_name, m.league_country, m.league_tier
     FROM pick_matches m
     WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
     ORDER BY m.league_name"
);

// =============================================================================
// Query principale : matchs + candidats avec filtres
// =============================================================================
$where = ["m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR"];
$params = [];

if ($filter_league !== '') {
    $where[] = "m.league_name = ?";
    $params[] = $filter_league;
}
if ($filter_group !== '' && in_array($filter_group, ['FT', 'HT', '2H'], true)) {
    $where[] = "EXISTS (SELECT 1 FROM pick_candidates c2
                        WHERE c2.match_id = m.match_id AND c2.market_group = ?)";
    $params[] = $filter_group;
}
$where_sql = implode(' AND ', $where);

$matches = SE_Db::queryAll(
    "SELECT m.*,
            DATE(CONVERT_TZ(m.kickoff_utc, '+00:00', '+02:00')) AS day_paris
     FROM pick_matches m
     WHERE $where_sql
     ORDER BY m.kickoff_utc ASC",
    $params
);

// Pré-charger tous les candidats des matchs affichés (avec filtres)
$candidates_by_match = [];
if (!empty($matches)) {
    $match_ids = array_column($matches, 'match_id');
    $placeholders = implode(',', array_fill(0, count($match_ids), '?'));

    $cand_where = ["c.match_id IN ($placeholders)"];
    $cand_params = $match_ids;

    if ($filter_status !== '') {
        $cand_where[] = "c.status = ?";
        $cand_params[] = $filter_status;
    }
    if ($filter_min_conv > 0) {
        $cand_where[] = "c.conviction >= ?";
        $cand_params[] = $filter_min_conv;
    }
    if ($filter_decision !== '' && $filter_decision !== 'all') {
        $cand_where[] = "c.user_decision = ?";
        $cand_params[] = $filter_decision;
    }
    $cand_where_sql = implode(' AND ', $cand_where);

    $all_candidates = SE_Db::queryAll(
        "SELECT c.* FROM pick_candidates c
         WHERE $cand_where_sql
         ORDER BY c.conviction DESC, c.ev DESC",
        $cand_params
    );

    foreach ($all_candidates as $c) {
        $candidates_by_match[$c['match_id']][] = $c;
    }
}

// Filtrer les matchs sans candidats restants après filtres
$matches = array_filter($matches, fn($m) => isset($candidates_by_match[$m['match_id']]));

// Groupage par jour pour affichage
$matches_by_day = [];
foreach ($matches as $m) {
    $matches_by_day[$m['day_paris']][] = $m;
}

// =============================================================================
// Helpers d'affichage
// =============================================================================
function fmt_kickoff(string $utc_dt): string {
    $dt = new DateTime($utc_dt, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Europe/Paris'));
    return $dt->format('H\hi');
}

function fmt_day(string $day_paris): string {
    $dt = new DateTime($day_paris, new DateTimeZone('Europe/Paris'));
    $today = new DateTime('today', new DateTimeZone('Europe/Paris'));
    $tomorrow = new DateTime('tomorrow', new DateTimeZone('Europe/Paris'));

    if ($dt == $today) return "AUJOURD'HUI";
    if ($dt == $tomorrow) return "DEMAIN";

    $jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    return strtoupper($jours[(int)$dt->format('w')]) . ' ' . $dt->format('d/m');
}

function status_emoji(string $status): string {
    return match($status) {
        'auto'    => '🟢',
        'manual'  => '🟡',
        'warn'    => '⚠️',
        default   => '⚪',
    };
}

function decision_pill(string $decision): string {
    $map = [
        'pending'   => ['', ''],
        'validated' => ['#00ff9d', 'VALIDÉ'],
        'rejected'  => ['#ff3b3b', 'REJETÉ'],
        'published' => ['#00d4ff', 'PUBLIÉ'],
        'won'       => ['#00ff9d', '✓ GAGNÉ'],
        'lost'      => ['#ff3b3b', '✗ PERDU'],
        'void'      => ['#888', 'VOID'],
    ];
    [$color, $label] = $map[$decision] ?? ['', ''];
    if (!$label) return '';
    return '<span class="decision-pill" style="background:' . $color . '20;color:' . $color . ';border:1px solid ' . $color . '40">' . htmlspecialchars($label) . '</span>';
}

function flag_emoji(string $country): string {
    $map = [
        'England'     => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
        'France'      => '🇫🇷',
        'Spain'       => '🇪🇸',
        'Italy'       => '🇮🇹',
        'Germany'     => '🇩🇪',
        'Netherlands' => '🇳🇱',
        'Belgium'     => '🇧🇪',
        'Brazil'      => '🇧🇷',
        'USA'         => '🇺🇸',
        'Japan'       => '🇯🇵',
    ];
    return $map[$country] ?? '🌍';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🎯 Edge Finder — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>

<?php
// Sidebar admin partagée : nécessite $db (PDO du site principal)
try {
    $db = getDB();
    $pageActive = 'edge-finder';
    @require_once __DIR__ . '/../sidebar.php';
} catch (Throwable $e) {
    // Si la sidebar plante, on continue sans (le dashboard reste accessible)
}
?>

<div class="ef-main">

  <!-- ─────────────────────────────────────────────────────────── HEADER -->
  <header class="ef-header">
    <div class="ef-title">
      <h1>🎯 EDGE FINDER</h1>
      <p class="ef-subtitle">Détecteur de candidats value bets — méthodologie v7.7</p>
    </div>
    <div class="ef-sync">
      <?php if ($lastImport): ?>
        <span class="ef-sync-label">DERNIÈRE SYNC</span>
        <span class="ef-sync-value">
          <?= htmlspecialchars((new DateTime($lastImport['imported_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m H:i')) ?>
        </span>
        <span class="ef-sync-meta">#<?= (int)$lastImport['import_id'] ?> · <?= (int)$lastImport['matchs_analyses'] ?> matchs · <?= (int)$lastImport['candidates_auto'] ?>🟢 + <?= (int)$lastImport['candidates_manual'] ?>🟡</span>
      <?php else: ?>
        <span class="ef-sync-empty">Aucun import — lance scripts/export_picks.py</span>
      <?php endif ?>
    </div>
  </header>

  <div class="ef-gradient-strip"></div>

  <!-- ─────────────────────────────────────────────────────────── STATS -->
  <section class="ef-stats">
    <div class="ef-stat ef-stat-auto">
      <div class="ef-stat-label">SWEET 🟢 PENDING</div>
      <div class="ef-stat-value"><?= (int)($stats['n_auto_pending'] ?? 0) ?></div>
      <div class="ef-stat-sub">en attente de décision</div>
    </div>
    <div class="ef-stat ef-stat-manual">
      <div class="ef-stat-label">MANUAL 🟡 PENDING</div>
      <div class="ef-stat-value"><?= (int)($stats['n_manual_pending'] ?? 0) ?></div>
      <div class="ef-stat-sub">à valider manuellement</div>
    </div>
    <div class="ef-stat ef-stat-validated">
      <div class="ef-stat-label">VALIDÉS</div>
      <div class="ef-stat-value"><?= (int)($stats['n_validated'] ?? 0) ?></div>
      <div class="ef-stat-sub">prêts à publier</div>
    </div>
    <div class="ef-stat ef-stat-results">
      <div class="ef-stat-label">RÉSULTATS</div>
      <div class="ef-stat-value">
        <span style="color:#00ff9d"><?= (int)($stats['n_won'] ?? 0) ?></span>
        /
        <span style="color:#ff3b3b"><?= (int)($stats['n_lost'] ?? 0) ?></span>
      </div>
      <div class="ef-stat-sub">gagnés / perdus</div>
    </div>
  </section>

  <!-- ─────────────────────────────────────────────────────────── FILTRES -->
  <form method="get" class="ef-filters">
    <select name="league">
      <option value="">Toutes ligues</option>
      <?php foreach ($leagues as $l): ?>
        <option value="<?= htmlspecialchars($l['league_name']) ?>" <?= $filter_league === $l['league_name'] ? 'selected' : '' ?>>
          <?= flag_emoji($l['league_country']) ?> <?= htmlspecialchars($l['league_name']) ?>
          <?= $l['league_tier'] ? '· ' . htmlspecialchars($l['league_tier']) : '' ?>
        </option>
      <?php endforeach ?>
    </select>

    <select name="group">
      <option value="">Tous moments</option>
      <option value="FT" <?= $filter_group==='FT'?'selected':'' ?>>FT (temps plein)</option>
      <option value="HT" <?= $filter_group==='HT'?'selected':'' ?>>HT (mi-temps)</option>
      <option value="2H" <?= $filter_group==='2H'?'selected':'' ?>>2H (2nde période)</option>
    </select>

    <select name="status">
      <option value="">Tous niveaux</option>
      <option value="auto"   <?= $filter_status==='auto'?'selected':'' ?>>🟢 Sweet auto</option>
      <option value="manual" <?= $filter_status==='manual'?'selected':'' ?>>🟡 Manual review</option>
    </select>

    <select name="decision">
      <option value="pending"   <?= $filter_decision==='pending'?'selected':'' ?>>⏳ En attente</option>
      <option value="validated" <?= $filter_decision==='validated'?'selected':'' ?>>✓ Validés</option>
      <option value="rejected"  <?= $filter_decision==='rejected'?'selected':'' ?>>✗ Rejetés</option>
      <option value="published" <?= $filter_decision==='published'?'selected':'' ?>>📢 Publiés</option>
      <option value="won"       <?= $filter_decision==='won'?'selected':'' ?>>🏆 Gagnés</option>
      <option value="lost"      <?= $filter_decision==='lost'?'selected':'' ?>>💀 Perdus</option>
      <option value="all"       <?= $filter_decision==='all'?'selected':'' ?>>Toutes décisions</option>
    </select>

    <label class="ef-filter-conv">
      Conviction min :
      <input type="number" name="min_conv" min="0" max="100" step="5" value="<?= $filter_min_conv ?>" placeholder="0">
    </label>

    <button type="submit">Filtrer</button>
    <a href="?" class="ef-filter-reset">Reset</a>
  </form>

  <!-- ─────────────────────────────────────────────────────────── MATCHES -->
  <?php if (empty($matches_by_day)): ?>
    <div class="ef-empty">
      <p>🔍 Aucun match avec candidats pour les filtres actifs.</p>
      <p class="ef-empty-sub">Essaie de relâcher les filtres ou re-importe les picks via l'export Python.</p>
    </div>
  <?php else: ?>
    <?php foreach ($matches_by_day as $day => $day_matches): ?>
      <h2 class="ef-day-header"><?= fmt_day($day) ?> — <?= count($day_matches) ?> match<?= count($day_matches) > 1 ? 's' : '' ?></h2>

      <?php foreach ($day_matches as $m): ?>
        <article class="ef-match" data-match-id="<?= (int)$m['match_id'] ?>">
          <header class="ef-match-header">
            <div class="ef-match-league">
              <?= flag_emoji($m['league_country']) ?>
              <span class="ef-match-league-name"><?= htmlspecialchars($m['league_name']) ?></span>
              <?php if ($m['league_tier']): ?>
                <span class="ef-match-tier"><?= htmlspecialchars($m['league_tier']) ?></span>
              <?php endif ?>
            </div>
            <div class="ef-match-time">⏱ <?= fmt_kickoff($m['kickoff_utc']) ?></div>
          </header>

          <div class="ef-match-teams">
            <span class="ef-match-team-home"><?= htmlspecialchars($m['home_name']) ?></span>
            <span class="ef-match-vs">vs</span>
            <span class="ef-match-team-away"><?= htmlspecialchars($m['away_name']) ?></span>
          </div>

          <div class="ef-match-lambdas">
            <span>λ<sub>home</sub> <strong><?= number_format((float)$m['lambda_home'], 2) ?></strong></span>
            <span class="ef-sep">│</span>
            <span>λ<sub>away</sub> <strong><?= number_format((float)$m['lambda_away'], 2) ?></strong></span>
            <span class="ef-sep">│</span>
            <span>λ<sub>total</sub> <strong><?= number_format((float)$m['lambda_total'], 2) ?></strong></span>
          </div>

          <div class="ef-candidates">
            <?php foreach ($candidates_by_match[$m['match_id']] as $c): ?>
              <div class="ef-cand ef-cand-<?= htmlspecialchars($c['status']) ?> ef-cand-decision-<?= htmlspecialchars($c['user_decision']) ?>"
                   data-candidate-id="<?= (int)$c['candidate_id'] ?>">
                <div class="ef-cand-status"><?= status_emoji($c['status']) ?></div>
                <div class="ef-cand-market">
                  <div class="ef-cand-market-label"><?= htmlspecialchars($c['market']) ?></div>
                  <div class="ef-cand-market-group"><?= htmlspecialchars($c['market_group']) ?></div>
                </div>
                <div class="ef-cand-odds">
                  <div class="ef-cand-odds-label">COTE</div>
                  <div class="ef-cand-odds-value"><?= number_format((float)$c['odds'], 2) ?></div>
                </div>
                <div class="ef-cand-ev">
                  <div class="ef-cand-ev-label">EV</div>
                  <div class="ef-cand-ev-value"><?= ((float)$c['ev'] >= 0 ? '+' : '') . number_format((float)$c['ev'] * 100, 1) ?>%</div>
                </div>
                <div class="ef-cand-probas">
                  <div class="ef-cand-proba" title="Proba modèle">M <?= number_format((float)$c['model_proba'] * 100, 1) ?>%</div>
                  <div class="ef-cand-proba" title="Proba marché dévigée">D <?= number_format((float)$c['devig_proba'] * 100, 1) ?>%</div>
                </div>
                <div class="ef-cand-conv">
                  <div class="ef-cand-conv-label">CONV</div>
                  <div class="ef-cand-conv-value">
                    <span class="ef-cand-conv-num"><?= (int)$c['conviction'] ?></span>
                    <span class="ef-cand-conv-bar"><span style="width:<?= (int)$c['conviction'] ?>%"></span></span>
                  </div>
                </div>
                <div class="ef-cand-actions">
                  <?php if ($c['user_decision'] === 'pending'): ?>
                    <button class="ef-btn ef-btn-validate" data-action="validated" title="Valider">✓</button>
                    <button class="ef-btn ef-btn-reject" data-action="rejected" title="Rejeter">✗</button>
                  <?php else: ?>
                    <?= decision_pill($c['user_decision']) ?>
                    <?php if (in_array($c['user_decision'], ['validated','rejected'], true)): ?>
                      <button class="ef-btn ef-btn-undo" data-action="pending" title="Annuler">↩</button>
                    <?php endif ?>
                    <?php if ($c['user_decision'] === 'validated'): ?>
                      <button class="ef-btn ef-btn-publish" data-action="published" title="Marquer publié">📢</button>
                    <?php endif ?>
                    <?php if ($c['user_decision'] === 'published'): ?>
                      <button class="ef-btn ef-btn-won"  data-action="won"  title="Gagné">🏆</button>
                      <button class="ef-btn ef-btn-lost" data-action="lost" title="Perdu">💀</button>
                    <?php endif ?>
                  <?php endif ?>
                </div>
              </div>
            <?php endforeach ?>
          </div>
        </article>
      <?php endforeach ?>
    <?php endforeach ?>
  <?php endif ?>

  <footer class="ef-footer">
    <p>StratEdge Edge Finder v1.0 · Méthodologie v7.7 · Sweet spot [+3% ; +8%]</p>
    <p class="ef-disclaimer">Outil interne d'aide à la décision. Validation humaine requise. Le jeu peut être dangereux : 09 74 75 13 13.</p>
  </footer>
</div>

<script src="assets/dashboard.js"></script>
</body>
</html>
