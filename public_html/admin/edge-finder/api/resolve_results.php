<?php
/**
 * StratEdge Edge Finder — Auto-résolution won/lost
 *
 * Ce script est appelé par cron Hostinger (1x/heure ou toutes les 2h).
 * Pour chaque candidat avec user_decision='published' dont le match
 * est terminé depuis plus de 2h, on va chercher le score sur FootyStats
 * et on marque le candidat won/lost selon le résultat.
 *
 * Sécurité :
 *   - Accès via token X-StratEdge-Token (même token que import.php)
 *   - Ou via cron Hostinger (qui peut appeler avec ?token=)
 *
 * Usage :
 *   curl https://stratedgepronos.fr/panel-x9k3m/edge-finder/api/resolve_results.php?token=XXX
 *
 *   Ou cron Hostinger :
 *   0 *\/2 * * * curl -s "https://stratedgepronos.fr/panel-x9k3m/edge-finder/api/resolve_results.php?token=$TOKEN"
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// Auth — accepte token via header OU GET (pour cron Hostinger)
// =============================================================================

$provided = $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? $_GET['token'] ?? '';
if (!defined('SE_IMPORT_TOKEN') || !hash_equals(SE_IMPORT_TOKEN, $provided)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// =============================================================================
// Configuration
// =============================================================================

$FOOTYSTATS_PROXY = 'https://stratedgepronos.fr/footystats-api.php';
$MIN_HOURS_AFTER_KICKOFF = 2;  // attend 2h après le coup d'envoi avant de chercher le score
$MAX_MATCHES_PER_RUN = 50;     // limite pour éviter timeout (60s Hostinger)

// =============================================================================
// Récupérer les matchs publiés à résoudre
// =============================================================================

$matches_to_resolve = SE_Db::queryAll(
    "SELECT DISTINCT
        m.match_id,
        m.home_id,
        m.away_id,
        m.home_name,
        m.away_name,
        m.kickoff_utc,
        m.league_name
     FROM pick_matches m
     JOIN pick_candidates c ON c.match_id = m.match_id
     WHERE c.user_decision = 'published'
       AND m.kickoff_utc < UTC_TIMESTAMP() - INTERVAL ? HOUR
     ORDER BY m.kickoff_utc DESC
     LIMIT ?",
    [$MIN_HOURS_AFTER_KICKOFF, $MAX_MATCHES_PER_RUN]
);

if (empty($matches_to_resolve)) {
    echo json_encode([
        'success'  => true,
        'message'  => 'No matches to resolve',
        'resolved' => 0,
    ]);
    exit;
}

// =============================================================================
// Récupère le token FootyStats proxy (depuis config-keys.php du site principal)
// =============================================================================

$keysFile = $_SERVER['DOCUMENT_ROOT'] . '/config-keys.php';
if (!file_exists($keysFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'config-keys.php not found']);
    exit;
}
require_once $keysFile;

if (!defined('AUTH_TOKEN')) {
    http_response_code(500);
    echo json_encode(['error' => 'AUTH_TOKEN not defined (footystats proxy token)']);
    exit;
}

// =============================================================================
// Helpers
// =============================================================================

function fetch_match_score(string $proxyUrl, string $proxyToken, int $matchId): ?array {
    $url = $proxyUrl . '?action=match&id=' . urlencode((string)$matchId) . '&token=' . urlencode($proxyToken);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }

    // FootyStats peut retourner {data: {match}, success: true} ou directement les data
    if (isset($data['data'])) {
        $data = $data['data'];
    }

    // Fields utiles
    $status = (string)($data['status'] ?? '');
    $home_g = is_numeric($data['homeGoalCount'] ?? null) ? (int)$data['homeGoalCount'] : null;
    $away_g = is_numeric($data['awayGoalCount'] ?? null) ? (int)$data['awayGoalCount'] : null;
    $ht_h   = is_numeric($data['ht_goals_team_a'] ?? null) ? (int)$data['ht_goals_team_a'] : null;
    $ht_a   = is_numeric($data['ht_goals_team_b'] ?? null) ? (int)$data['ht_goals_team_b'] : null;

    // Match doit être 'complete' ET avoir des scores valides
    if (strtolower($status) !== 'complete' || $home_g === null || $away_g === null) {
        return null;
    }

    return [
        'home_goals' => $home_g,
        'away_goals' => $away_g,
        'ht_home'    => $ht_h,
        'ht_away'    => $ht_a,
        'status'     => $status,
    ];
}

/**
 * Détermine si une sélection a gagné ou perdu selon le score final.
 * Retourne 'won', 'lost', ou 'void' (si on peut pas déterminer).
 */
function resolve_market(string $market, string $group, ?float $line, int $h, int $a, ?int $hth, ?int $hta): string {
    // FT score
    $ft_total = $h + $a;
    $ht_total = ($hth !== null && $hta !== null) ? ($hth + $hta) : null;
    $h2_total = ($ht_total !== null) ? ($ft_total - $ht_total) : null;
    $h2_home  = ($hth !== null) ? ($h - $hth) : null;
    $h2_away  = ($hta !== null) ? ($a - $hta) : null;

    $home_won = $h > $a;
    $away_won = $a > $h;
    $draw     = $h === $a;

    // Helper closure — Pour les marchés de période sans données HT, retourner void
    $needsHT = function() use ($hth, $hta) { return $hth !== null && $hta !== null; };

    switch ($market) {
        // ===== FT 1X2 / Double Chance =====
        case 'DC 1X':  return ($home_won || $draw) ? 'won' : 'lost';
        case 'DC X2':  return ($away_won || $draw) ? 'won' : 'lost';
        case 'DC 12':  return (!$draw) ? 'won' : 'lost';

        // ===== DNB =====
        case 'DNB Home': return $home_won ? 'won' : ($draw ? 'void' : 'lost');
        case 'DNB Away': return $away_won ? 'won' : ($draw ? 'void' : 'lost');

        // ===== BTTS FT =====
        case 'BTTS Yes': return ($h > 0 && $a > 0) ? 'won' : 'lost';
        case 'BTTS No':  return ($h === 0 || $a === 0) ? 'won' : 'lost';

        // ===== Over/Under FT =====
        case 'Over 0.5':  return $ft_total >= 1 ? 'won' : 'lost';
        case 'Under 0.5': return $ft_total < 1  ? 'won' : 'lost';
        case 'Over 1.5':  return $ft_total >= 2 ? 'won' : 'lost';
        case 'Under 1.5': return $ft_total < 2  ? 'won' : 'lost';
        case 'Over 2.5':  return $ft_total >= 3 ? 'won' : 'lost';
        case 'Under 2.5': return $ft_total < 3  ? 'won' : 'lost';
        case 'Over 3.5':  return $ft_total >= 4 ? 'won' : 'lost';
        case 'Under 3.5': return $ft_total < 4  ? 'won' : 'lost';
        case 'Over 4.5':  return $ft_total >= 5 ? 'won' : 'lost';
        case 'Under 4.5': return $ft_total < 5  ? 'won' : 'lost';

        // ===== Clean Sheet =====
        case 'CS Home Yes': return $a === 0 ? 'won' : 'lost';  // away ne marque pas = home clean sheet
        case 'CS Home No':  return $a > 0   ? 'won' : 'lost';
        case 'CS Away Yes': return $h === 0 ? 'won' : 'lost';
        case 'CS Away No':  return $h > 0   ? 'won' : 'lost';

        // ===== Win to Nil =====
        case 'WinToNil Home': return ($home_won && $a === 0) ? 'won' : 'lost';
        case 'WinToNil Away': return ($away_won && $h === 0) ? 'won' : 'lost';

        // ===== HT (mi-temps) =====
        case 'HT Over 0.5':
            if (!$needsHT()) return 'void';
            return $ht_total >= 1 ? 'won' : 'lost';
        case 'HT Over 1.5':
            if (!$needsHT()) return 'void';
            return $ht_total >= 2 ? 'won' : 'lost';
        case 'BTTS HT Yes':
            if (!$needsHT()) return 'void';
            return ($hth > 0 && $hta > 0) ? 'won' : 'lost';

        // ===== 2H (2nde période) =====
        case '2H Over 0.5':
            if (!$needsHT() || $h2_total === null) return 'void';
            return $h2_total >= 1 ? 'won' : 'lost';
        case '2H Over 1.5':
            if (!$needsHT() || $h2_total === null) return 'void';
            return $h2_total >= 2 ? 'won' : 'lost';
        case 'BTTS 2H Yes':
            if (!$needsHT() || $h2_home === null || $h2_away === null) return 'void';
            return ($h2_home > 0 && $h2_away > 0) ? 'won' : 'lost';

        default:
            return 'void';  // marché non géré
    }
}

// =============================================================================
// Boucle de résolution
// =============================================================================

$results = [
    'matches_checked' => 0,
    'matches_resolved' => 0,
    'matches_not_complete' => 0,
    'matches_fetch_failed' => 0,
    'candidates_won' => 0,
    'candidates_lost' => 0,
    'candidates_void' => 0,
    'details' => [],
];

foreach ($matches_to_resolve as $match) {
    $results['matches_checked']++;
    $matchId = (int)$match['match_id'];

    // Fetch score depuis FootyStats
    $score = fetch_match_score($FOOTYSTATS_PROXY, AUTH_TOKEN, $matchId);

    if ($score === null) {
        $results['matches_fetch_failed']++;
        $results['details'][] = [
            'match_id' => $matchId,
            'teams'    => $match['home_name'] . ' vs ' . $match['away_name'],
            'status'   => 'fetch_failed_or_not_complete',
        ];
        continue;
    }

    $results['matches_resolved']++;

    // Récupère tous les candidats published de ce match
    $candidates = SE_Db::queryAll(
        "SELECT candidate_id, market, market_group, odds
         FROM pick_candidates
         WHERE match_id = ? AND user_decision = 'published'",
        [$matchId]
    );

    $matchDetails = [
        'match_id'    => $matchId,
        'teams'       => $match['home_name'] . ' vs ' . $match['away_name'],
        'score'       => $score['home_goals'] . '-' . $score['away_goals'],
        'ht_score'    => ($score['ht_home'] !== null) ? ($score['ht_home'] . '-' . $score['ht_away']) : null,
        'candidates'  => [],
    ];

    foreach ($candidates as $c) {
        $decision = resolve_market(
            (string)$c['market'],
            (string)$c['market_group'],
            null,
            $score['home_goals'],
            $score['away_goals'],
            $score['ht_home'],
            $score['ht_away']
        );

        SE_Db::execute(
            "UPDATE pick_candidates
             SET user_decision = ?, decision_at = NOW(),
                 decision_note = CONCAT(COALESCE(decision_note, ''), '\nauto-resolved: ', ?)
             WHERE candidate_id = ?",
            [$decision, $score['home_goals'] . '-' . $score['away_goals'], (int)$c['candidate_id']]
        );

        $results['candidates_' . $decision]++;
        $matchDetails['candidates'][] = [
            'candidate_id' => (int)$c['candidate_id'],
            'market'       => $c['market'],
            'group'        => $c['market_group'],
            'odds'         => $c['odds'],
            'decision'     => $decision,
        ];
    }

    $results['details'][] = $matchDetails;
}

echo json_encode([
    'success'           => true,
    'now_utc'           => gmdate('Y-m-d H:i:s'),
    'min_hours_after'   => $MIN_HOURS_AFTER_KICKOFF,
    'matches_checked'   => $results['matches_checked'],
    'matches_resolved'  => $results['matches_resolved'],
    'matches_pending'   => $results['matches_fetch_failed'],
    'candidates_won'    => $results['candidates_won'],
    'candidates_lost'   => $results['candidates_lost'],
    'candidates_void'   => $results['candidates_void'],
    'details'           => $results['details'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
