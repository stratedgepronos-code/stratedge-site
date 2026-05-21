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

// Compteurs candidats par statut.
// IMPORTANT : les picks PENDING/SUIVIS/SKIPPES sont comptes sur les matchs futurs.
// Mais les RESULTATS (won/lost) sont comptes SANS limite de temps (30 derniers jours)
// sinon ils disparaissent du dashboard des que le match a 6h+.
$stats = SE_Db::queryOne(
    "SELECT
        SUM(CASE WHEN c.status = 'auto'   AND c.user_decision='pending'
                 AND m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR THEN 1 ELSE 0 END) AS n_auto_pending,
        SUM(CASE WHEN c.status = 'manual' AND c.user_decision='pending'
                 AND m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR THEN 1 ELSE 0 END) AS n_manual_pending,
        SUM(CASE WHEN c.user_decision = 'tracked' THEN 1 ELSE 0 END) AS n_tracked,
        SUM(CASE WHEN c.user_decision = 'skipped'
                 AND m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR THEN 1 ELSE 0 END) AS n_skipped,
        SUM(CASE WHEN c.user_decision = 'won'  THEN 1 ELSE 0 END) AS n_won,
        SUM(CASE WHEN c.user_decision = 'lost' THEN 1 ELSE 0 END) AS n_lost,
        COUNT(DISTINCT CASE WHEN m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
                            THEN m.match_id END) AS n_matches
     FROM pick_candidates c
     JOIN pick_matches m ON m.match_id = c.match_id
     WHERE m.kickoff_utc >= UTC_TIMESTAMP() - INTERVAL 30 DAY"
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
            DATE(m.kickoff_utc) AS day_paris
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
         ORDER BY c.recommended DESC, c.conviction DESC, c.ev DESC",
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
    // NOTE: champ nomme kickoff_utc mais contient deja l'heure Paris
    // (DB MySQL FROM_UNIXTIME utilise TZ du serveur). Pas de conversion.
    $dt = new DateTime($utc_dt);
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
        'pending'  => ['', ''],
        'tracked'  => ['#00d4ff', '📌 SUIVI'],
        'skipped'  => ['#888', '✗ PASSÉ'],
        'won'      => ['#00ff9d', '✓ GAGNÉ'],
        'lost'     => ['#ff3b3b', '✗ PERDU'],
        'void'     => ['#888', 'VOID'],
    ];
    [$color, $label] = $map[$decision] ?? ['', ''];
    if (!$label) return '';
    return '<span class="decision-pill" style="background:' . $color . '20;color:' . $color . ';border:1px solid ' . $color . '40">' . htmlspecialchars($label) . '</span>';
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
        'China'       => 'cn',
        'Colombia'    => 'co',
        'Finland'     => 'fi',
        'Iceland'     => 'is',
        'Republic of Ireland' => 'ie',
        'Ireland'     => 'ie',
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
 *
 * @param string $teamName  Nom de l'equipe (fallback de recherche)
 * @param int    $size      Taille en px
 * @param string $fsLogo    URL logo fournie par FootyStats (depuis pick_matches).
 *                          Peut etre relative ("teams/xxx.png") ou absolue.
 */
function team_logo_img(string $teamName, int $size = 24, string $fsLogo = ''): string {
    $url = '';

    // 1) Priorite : le logo FootyStats fourni avec le match (couvre toutes les
    //    equipes, y compris les ligues exotiques absentes de la DB locale).
    if ($fsLogo !== '') {
        if (preg_match('#^https?://#', $fsLogo)) {
            $url = $fsLogo;                       // deja absolue
        } else {
            // relative -> prefixe CDN FootyStats
            $url = 'https://cdn.footystats.org/img/' . ltrim($fsLogo, '/');
        }
    }

    // 2) Fallback : ancienne DB de logos centralisee
    if ($url === '' && function_exists('stratedge_football_logo')) {
        $url = stratedge_football_logo($teamName);
    }

    if ($url !== '') {
        return '<img src="' . htmlspecialchars($url) . '" '
             . 'alt="' . htmlspecialchars($teamName) . '" '
             . 'width="' . $size . '" height="' . $size . '" '
             . 'style="display: inline-block; vertical-align: middle; '
             . 'object-fit: contain; margin-right: 0.35em; '
             . 'filter: drop-shadow(0 1px 3px rgba(0,0,0,0.4));" '
             . 'onerror="this.style.display=\'none\'">';
    }
    return '';
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
// =============================================================================
// EXPORT PICKS EN MARKDOWN POUR ANALYSE CLAUDE
// =============================================================================
// Genere un markdown structure decrivant un match + ses picks, pour que tu
// puisses coller dans une conversation Claude et lui demander une analyse.
// =============================================================================

function ef_format_match_for_claude(array $m, array $candidates): string {
    $home = $m['home_name'] ?? '?';
    $away = $m['away_name'] ?? '?';
    $league = $m['league_name'] ?? '?';
    $country = $m['league_country'] ?? '';
    $tier = $m['league_tier'] ?? '';
    $kickoff = !empty($m['kickoff_utc']) ? fmt_kickoff($m['kickoff_utc']) : '?';

    // Lambdas Poisson
    $lh = isset($m['lambda_home']) ? round((float)$m['lambda_home'], 2) : null;
    $la = isset($m['lambda_away']) ? round((float)$m['lambda_away'], 2) : null;
    $lt = ($lh !== null && $la !== null) ? round($lh + $la, 2) : null;

    // xG FootyStats
    $xg_h = isset($m['home_xg']) ? round((float)$m['home_xg'], 2) : null;
    $xg_a = isset($m['away_xg']) ? round((float)$m['away_xg'], 2) : null;

    // Stats marche
    $o25 = isset($m['o25_potential']) ? (float)$m['o25_potential'] : null;
    $o35 = isset($m['o35_potential']) ? (float)$m['o35_potential'] : null;
    $btts = isset($m['btts_potential']) ? (float)$m['btts_potential'] : null;
    $avg = isset($m['avg_potential']) ? round((float)$m['avg_potential'], 2) : null;

    $out = [];
    $out[] = "### {$home} vs {$away}";
    $out[] = "- **Ligue** : {$league}" . ($country ? " ({$country})" : '') . ($tier ? " · *{$tier}*" : '');
    $out[] = "- **Coup d'envoi** : {$kickoff} (heure Paris)";

    if ($lh !== null) {
        $out[] = "- **Dixon-Coles** : λ_home={$lh}, λ_away={$la}, λ_total={$lt}";
    }
    if ($xg_h !== null) {
        $out[] = "- **xG FootyStats** : home={$xg_h}, away={$xg_a}";
    }

    $stats_parts = [];
    if ($o25 !== null) $stats_parts[] = "O2.5 potential **" . round($o25) . "%**";
    if ($o35 !== null) $stats_parts[] = "O3.5 potential **" . round($o35) . "%**";
    if ($btts !== null) $stats_parts[] = "BTTS potential **" . round($btts) . "%**";
    if ($avg !== null)  $stats_parts[] = "Avg goals attendu **{$avg}**";
    if (!empty($stats_parts)) {
        $out[] = "- **Stats marche** : " . implode(' · ', $stats_parts);
    }

    // Highlights eventuels
    if (!empty($m['highlights'])) {
        $hl = json_decode($m['highlights'], true);
        if (is_array($hl) && !empty($hl)) {
            $hl_lines = [];
            foreach ($hl as $h) {
                $icon = $h['icon'] ?? '•';
                $label = $h['label'] ?? '';
                $reason = $h['reason'] ?? '';
                $level = $h['level'] ?? 'info';
                $level_tag = match($level) {
                    'strong' => '🔥', 'warning' => '⚠️', default => ''
                };
                $hl_lines[] = "  - {$icon} **{$label}** : {$reason} {$level_tag}";
            }
            $out[] = "- **Signaux** :";
            $out = array_merge($out, $hl_lines);
        }
    }

    // Picks (les candidats)
    if (!empty($candidates)) {
        $out[] = "";
        $out[] = "**Picks candidats :**";
        $out[] = "";
        $out[] = "| Pick | Marche | Cote | EV | Modele | De-vig | Conviction | Statut | Reco |";
        $out[] = "|------|--------|-----:|---:|-------:|-------:|-----------:|--------|:----:|";

        foreach ($candidates as $i => $c) {
            $market = $c['market'] ?? '?';
            $group = $c['market_group'] ?? '';
            $market_full = $market . ($group ? " ({$group})" : '');
            $odds = isset($c['odds']) ? number_format((float)$c['odds'], 2) : '-';
            $ev_raw = isset($c['ev']) ? (float)$c['ev'] * 100 : null;
            $ev = $ev_raw !== null ? sprintf('+%.1f%%', $ev_raw) : '-';
            $model_p = isset($c['model_proba']) ? round((float)$c['model_proba'] * 100) . '%' : '-';
            $devig_p = isset($c['devig_proba']) ? round((float)$c['devig_proba'] * 100) . '%' : '-';
            $conv = isset($c['conviction']) ? (int)$c['conviction'] : '-';
            $status = $c['status'] ?? '?';
            $status_label = match($status) {
                'auto' => '🟢 auto', 'manual' => '🟡 manual', 'warn' => '🔴 warn', default => $status
            };
            $reco = !empty($c['recommended']) ? '⭐' : '';
            $idx = $i + 1;

            $out[] = "| #{$idx} | {$market_full} | {$odds} | {$ev} | {$model_p} | {$devig_p} | {$conv} | {$status_label} | {$reco} |";
        }
    }

    return implode("\n", $out);
}

function ef_format_day_intro(string $day_label, int $nb_matches): string {
    return "# Picks Edge Finder — {$day_label}\n\n"
         . "Analyse demande : ci-dessous {$nb_matches} match" . ($nb_matches > 1 ? 's' : '') . " avec leurs picks candidats issus de mon Edge Finder StratEdge (methodologie v7.7, Dixon-Coles + power de-vigging + Kelly).\n\n"
         . "Pour chaque match, **critique les picks** : sont-ils coherents avec le profil du match ? "
         . "Y a-t-il des signaux contradictoires ? Quel pick est le plus solide ? Les modeles (xG vs Dixon-Coles) sont-ils alignes ? "
         . "Le pick ⭐ est mon pick recommande par anti-correlation (1/match).\n\n"
         . "---\n";
}

function ef_format_single_match_intro(): string {
    return "# Pick Edge Finder — Analyse demande\n\n"
         . "Voici un match avec ses picks candidats issus de mon Edge Finder StratEdge (methodologie v7.7).\n\n"
         . "**Critique les picks** : sont-ils coherents ? Y a-t-il des signaux contradictoires ? "
         . "Quel pick recommandes-tu, et lesquels eviter ? Les modeles (xG vs Dixon-Coles) sont-ils alignes ?\n\n"
         . "---\n";
}

?>
<div class="main">
<div class="ef-main">

  <!-- ─────────────────────────────────────────────────────────── HEADER -->
  <header class="ef-header">
    <div class="ef-title">
      <div class="ef-logo-row">
        <svg class="ef-logo" width="48" height="48" viewBox="0 0 56 56" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="efLogoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ff2d78"/>
              <stop offset="100%" stop-color="#00d4ff"/>
            </linearGradient>
          </defs>
          <circle cx="28" cy="28" r="25" fill="none" stroke="url(#efLogoGrad)" stroke-width="2.5" opacity="0.35"/>
          <circle cx="28" cy="28" r="17" fill="none" stroke="url(#efLogoGrad)" stroke-width="2.5" opacity="0.6"/>
          <circle cx="28" cy="28" r="9" fill="none" stroke="url(#efLogoGrad)" stroke-width="2.5"/>
          <circle cx="28" cy="28" r="3.5" fill="url(#efLogoGrad)"/>
          <line x1="28" y1="1" x2="28" y2="11" stroke="url(#efLogoGrad)" stroke-width="2.5" stroke-linecap="round"/>
          <line x1="28" y1="45" x2="28" y2="55" stroke="url(#efLogoGrad)" stroke-width="2.5" stroke-linecap="round"/>
          <line x1="1" y1="28" x2="11" y2="28" stroke="url(#efLogoGrad)" stroke-width="2.5" stroke-linecap="round"/>
          <line x1="45" y1="28" x2="55" y2="28" stroke="url(#efLogoGrad)" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        <h1>EDGE FINDER</h1>
      </div>
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

  <!-- TABS -->
  <div class="ef-tabs">
    <a href="./" class="ef-tab active">🎯 Dashboard</a>
    <a href="stats.php" class="ef-tab">📊 Stats</a>
  </div>

  <!-- ─────────────────────────────────────────────────────────── STATS -->
  <section class="ef-stats">
    <a href="?decision=pending&status=auto" class="ef-stat ef-stat-auto<?= ($filter_decision === 'pending' && $filter_status === 'auto') ? ' ef-stat-active' : '' ?>">
      <div class="ef-stat-label">SWEET 🟢 PENDING</div>
      <div class="ef-stat-value"><?= (int)($stats['n_auto_pending'] ?? 0) ?></div>
      <div class="ef-stat-sub">cliquer pour filtrer</div>
    </a>
    <a href="?decision=pending&status=manual" class="ef-stat ef-stat-manual<?= ($filter_decision === 'pending' && $filter_status === 'manual') ? ' ef-stat-active' : '' ?>">
      <div class="ef-stat-label">MANUAL 🟡 PENDING</div>
      <div class="ef-stat-value"><?= (int)($stats['n_manual_pending'] ?? 0) ?></div>
      <div class="ef-stat-sub">à valider manuellement</div>
    </a>
    <a href="?decision=tracked" class="ef-stat ef-stat-validated<?= ($filter_decision === 'tracked') ? ' ef-stat-active' : '' ?>">
      <div class="ef-stat-label">📌 SUIVIS</div>
      <div class="ef-stat-value"><?= (int)($stats['n_tracked'] ?? 0) ?></div>
      <div class="ef-stat-sub">paris en cours de suivi</div>
    </a>
    <a href="?decision=skipped" class="ef-stat ef-stat-rejected<?= ($filter_decision === 'skipped') ? ' ef-stat-active' : '' ?>">
      <div class="ef-stat-label">✗ PASSÉS</div>
      <div class="ef-stat-value"><?= (int)($stats['n_skipped'] ?? 0) ?></div>
      <div class="ef-stat-sub">picks écartés</div>
    </a>
    <a href="?decision=won" class="ef-stat ef-stat-results<?= ($filter_decision === 'won' || $filter_decision === 'lost') ? ' ef-stat-active' : '' ?>">
      <div class="ef-stat-label">RÉSULTATS</div>
      <div class="ef-stat-value">
        <span style="color:#00ff9d"><?= (int)($stats['n_won'] ?? 0) ?></span>
        /
        <span style="color:#ff3b3b"><?= (int)($stats['n_lost'] ?? 0) ?></span>
      </div>
      <div class="ef-stat-sub">gagnés / perdus</div>
    </a>
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
      <option value="pending"  <?= $filter_decision==='pending'?'selected':'' ?>>⏳ En attente</option>
      <option value="tracked"  <?= $filter_decision==='tracked'?'selected':'' ?>>📌 Suivis</option>
      <option value="skipped"  <?= $filter_decision==='skipped'?'selected':'' ?>>✗ Passés</option>
      <option value="won"      <?= $filter_decision==='won'?'selected':'' ?>>🏆 Gagnés</option>
      <option value="lost"     <?= $filter_decision==='lost'?'selected':'' ?>>💀 Perdus</option>
      <option value="all"      <?= $filter_decision==='all'?'selected':'' ?>>Toutes décisions</option>
    </select>

    <div class="ef-filter-conv">
      <span class="ef-filter-conv-label">CONV min :</span>
      <div class="ef-conv-presets">
        <?php
          $current_min = $filter_min_conv;
          $presets = [
            ['v' => 0,   'label' => 'Tous',   'cls' => ''],
            ['v' => 70,  'label' => '≥70',    'cls' => ''],
            ['v' => 80,  'label' => '≥80',    'cls' => ''],
            ['v' => 90,  'label' => '≥90',    'cls' => 'good'],
            ['v' => 100, 'label' => '≥100🔥', 'cls' => 'exceptional'],
          ];
          // Reconstruit l'URL avec tous les filtres sauf min_conv pour les boutons preset
          $base_qs = [];
          if ($filter_league)   $base_qs['league'] = $filter_league;
          if ($filter_group)    $base_qs['group'] = $filter_group;
          if ($filter_status)   $base_qs['status'] = $filter_status;
          if ($filter_decision) $base_qs['decision'] = $filter_decision;
          foreach ($presets as $p):
            $qs = $base_qs;
            if ($p['v'] > 0) $qs['min_conv'] = $p['v'];
            $url = '?' . http_build_query($qs);
            $is_active = ($current_min == $p['v']);
        ?>
          <a href="<?= htmlspecialchars($url) ?>"
             class="ef-conv-preset <?= $p['cls'] ?> <?= $is_active ? 'active' : '' ?>">
            <?= $p['label'] ?>
          </a>
        <?php endforeach ?>
      </div>
      <input type="number" name="min_conv" min="0" max="150" step="5" value="<?= $filter_min_conv ?>"
             placeholder="custom" class="ef-conv-custom" title="Valeur custom (0-150)">
    </div>

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
      <?php
        // Pre-calcule le markdown de tous les matchs du jour pour le bouton "copier ce jour"
        $day_md_parts = [ef_format_day_intro(fmt_day($day), count($day_matches))];
        foreach ($day_matches as $mm) {
            $cands_mm = $candidates_by_match[$mm['match_id']] ?? [];
            $day_md_parts[] = ef_format_match_for_claude($mm, $cands_mm);
            $day_md_parts[] = "";
        }
        $day_md = implode("\n", $day_md_parts);
      ?>
      <div class="ef-day-header-row">
        <h2 class="ef-day-header"><?= fmt_day($day) ?> — <?= count($day_matches) ?> match<?= count($day_matches) > 1 ? 's' : '' ?></h2>
        <button type="button"
                class="ef-copy-btn ef-copy-btn-day"
                data-copy-text="<?= htmlspecialchars($day_md, ENT_QUOTES) ?>"
                title="Copier tous les picks de ce jour au format markdown (pret pour Claude)">
          <span class="ef-copy-btn-icon">⚡</span>
          <span class="ef-copy-btn-label">Export pour Claude</span>
          <span class="ef-copy-btn-count"><?= count($day_matches) ?></span>
        </button>
      </div>

      <div class="ef-matches-grid">
      <?php foreach ($day_matches as $m):
        $match_highlights = [];
        if (!empty($m['highlights'])) {
            $match_highlights = json_decode($m['highlights'], true) ?: [];
        }
        $has_strong = !empty(array_filter($match_highlights, fn($h) => ($h['level'] ?? '') === 'strong'));
        $has_warning = !empty(array_filter($match_highlights, fn($h) => ($h['level'] ?? '') === 'warning'));
        $star_icon = $has_strong ? '⭐' : ($has_warning ? '⚠️' : '');
        $highlights_tip = '';
        if (!empty($match_highlights)) {
            $tips = array_map(fn($h) => ($h['icon'] ?? '') . ' ' . ($h['label'] ?? '') . ' — ' . ($h['reason'] ?? ''), $match_highlights);
            $highlights_tip = htmlspecialchars(implode("\n", $tips), ENT_QUOTES);
        }
      ?>
        <article class="ef-match" data-match-id="<?= (int)$m['match_id'] ?>">
          <header class="ef-match-header">
            <div class="ef-match-league">
              <?= flag_emoji($m['league_country']) ?>
              <span class="ef-match-league-name"><?= htmlspecialchars($m['league_name']) ?></span>
              <?php if ($m['league_tier']): ?>
                <span class="ef-match-tier"><?= htmlspecialchars($m['league_tier']) ?></span>
              <?php endif ?>
              <?php if ($star_icon): ?>
                <span class="ef-match-star" title="<?= $highlights_tip ?>"><?= $star_icon ?></span>
              <?php endif ?>
            </div>
            <div class="ef-match-time">⏱ <?= fmt_kickoff($m['kickoff_utc']) ?></div>
            <?php
              // Markdown pour ce match seul + intro courte
              $single_md = ef_format_single_match_intro() . "\n" . ef_format_match_for_claude($m, $candidates_by_match[$m['match_id']]);
            ?>
            <button type="button"
                    class="ef-copy-btn ef-copy-btn-match"
                    data-copy-text="<?= htmlspecialchars($single_md, ENT_QUOTES) ?>"
                    title="Copier ce match au format markdown (pret pour Claude)">
              📋
            </button>
          </header>

          <a href="match.php?id=<?= (int)$m['match_id'] ?>" class="ef-match-link" title="Voir le détail du match">
            <div class="ef-match-teams">
              <span class="ef-match-team-home"><?= team_logo_img($m['home_name'], 22, $m['home_logo'] ?? '') ?><?= htmlspecialchars($m['home_name']) ?></span>
              <span class="ef-match-vs">vs</span>
              <span class="ef-match-team-away"><?= team_logo_img($m['away_name'], 22, $m['away_logo'] ?? '') ?><?= htmlspecialchars($m['away_name']) ?></span>
            </div>
          </a>

          <div class="ef-match-lambdas">
            <span>λ<sub>home</sub> <strong><?= number_format((float)$m['lambda_home'], 2) ?></strong></span>
            <span class="ef-sep">│</span>
            <span>λ<sub>away</sub> <strong><?= number_format((float)$m['lambda_away'], 2) ?></strong></span>
            <span class="ef-sep">│</span>
            <span>λ<sub>total</sub> <strong><?= number_format((float)$m['lambda_total'], 2) ?></strong></span>
          </div>

          <div class="ef-candidates">
            <?php
              // Pre-tri : on separe le pick recommande (ou le 1er si pas de reco)
              // des autres picks dits "alternatives".
              $cands = $candidates_by_match[$m['match_id']];
              $reco_idx = -1;
              foreach ($cands as $i => $cc) {
                  if (!empty($cc['recommended'])) { $reco_idx = $i; break; }
              }
              if ($reco_idx < 0) $reco_idx = 0; // fallback : le 1er du tri
              $reco_cand = $cands[$reco_idx];
              $alt_cands = [];
              foreach ($cands as $i => $cc) {
                  if ($i !== $reco_idx) $alt_cands[] = $cc;
              }
              $has_alts = count($alt_cands) > 0;
            ?>

            <?php
              // ===== Pick principal (toujours visible) =====
              $c = $reco_cand;
              $is_reco = !empty($c['recommended']);
            ?>
            <?php include __DIR__ . '/partials/_candidate_card.php'; ?>

            <?php if ($has_alts): ?>
              <button type="button"
                      class="ef-alts-toggle"
                      aria-expanded="false"
                      aria-controls="ef-alts-<?= (int)$m['match_id'] ?>">
                <span class="ef-alts-toggle-arrow">▾</span>
                <span class="ef-alts-toggle-label">
                  + <?= count($alt_cands) ?> alternative<?= count($alt_cands) > 1 ? 's' : '' ?>
                </span>
                <span class="ef-alts-toggle-hint">cliquer pour voir</span>
              </button>

              <div class="ef-alts-container" id="ef-alts-<?= (int)$m['match_id'] ?>" hidden>
                <?php foreach ($alt_cands as $c):
                  $is_reco = false; // par definition les alts ne sont pas reco
                ?>
                  <?php include __DIR__ . '/partials/_candidate_card.php'; ?>
                <?php endforeach ?>
              </div>
            <?php endif ?>
          </div>
        </article>
      <?php endforeach ?>
      </div>
    <?php endforeach ?>
  <?php endif ?>

  <footer class="ef-footer">
    <p>StratEdge Edge Finder v1.0 · Méthodologie v7.7 · Sweet spot [+3% ; +8%]</p>
    <p class="ef-disclaimer">Outil interne d'aide à la décision. Validation humaine requise. Le jeu peut être dangereux : 09 74 75 13 13.</p>
  </footer>
</div>
</div>

<script src="assets/dashboard.js"></script>
</body>
</html>
