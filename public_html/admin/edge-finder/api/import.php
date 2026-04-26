<?php
/**
 * StratEdge Edge Finder — Endpoint d'import des picks
 *
 * Méthode : POST application/json
 * Auth    : header X-StratEdge-Token
 * Body    : JSON généré par scripts/export_picks.py
 *
 * Réponse :
 *   200 { success: true, import_id: N, n_matches: N, n_candidates: N }
 *   401 { error: "Missing token" }
 *   403 { error: "Invalid token" }
 *   400 { error: "Invalid JSON" }
 *   500 { error: "DB error" }
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// 1. Auth
// =============================================================================
se_require_valid_token();

// =============================================================================
// 2. Méthode HTTP
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed (use POST)']);
    exit;
}

// =============================================================================
// 3. Body JSON
// =============================================================================
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// Validation basique
$required = ['generated_at', 'version', 'horizon_days', 'stats', 'matches'];
foreach ($required as $key) {
    if (!array_key_exists($key, $payload)) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $key"]);
        exit;
    }
}

// =============================================================================
// 4. Backup du JSON brut (optionnel, ignore si fail)
// =============================================================================
if (SE_BACKUP_JSON) {
    @mkdir(SE_BACKUP_DIR, 0750, true);
    $ts = date('Ymd_His');
    @file_put_contents(SE_BACKUP_DIR . "/picks_$ts.json", $raw);
}

// =============================================================================
// 5. Import en DB (transaction)
// =============================================================================
try {
    SE_Db::begin();

    // 5a. Insertion picks_imports
    $stats = $payload['stats'];
    SE_Db::execute(
        "INSERT INTO picks_imports
         (generated_at, version, horizon_days, matchs_total, matchs_analyses,
          candidates_auto, candidates_manual, candidates_warn, scope_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            // generated_at en UTC ISO → DATETIME MySQL
            str_replace(['T', 'Z'], [' ', ''], $payload['generated_at']),
            $payload['version'],
            (int)$payload['horizon_days'],
            (int)($stats['matchs_total'] ?? 0),
            (int)($stats['matchs_analyses'] ?? 0),
            (int)($stats['candidates_auto'] ?? 0),
            (int)($stats['candidates_manual'] ?? 0),
            (int)($stats['candidates_warn'] ?? 0),
            json_encode($payload['scope'] ?? []),
        ]
    );
    $import_id = SE_Db::lastInsertId();

    // 5b. Pour chaque match : delete + reinsert (idempotence)
    $n_matches = 0;
    $n_candidates = 0;

    foreach ($payload['matches'] as $m) {
        $match_id = (int)$m['match_id'];

        // Delete éventuelle ligne existante (cascade sur pick_candidates)
        SE_Db::execute("DELETE FROM pick_matches WHERE match_id = ?", [$match_id]);

        SE_Db::execute(
            "INSERT INTO pick_matches
             (match_id, import_id, season_id, league_name, league_tier, league_country,
              kickoff_utc, home_id, home_name, away_id, away_name,
              lambda_home, lambda_away, lambda_total,
              n_auto, n_manual, n_warn, best_conviction)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $match_id,
                $import_id,
                (int)$m['season_id'],
                $m['league']['name'] ?? null,
                $m['league']['tier'] ?? null,
                $m['league']['country'] ?? null,
                str_replace(['T', 'Z'], [' ', ''], $m['kickoff_utc']),
                (int)($m['home']['id'] ?? 0),
                $m['home']['name'] ?? '',
                (int)($m['away']['id'] ?? 0),
                $m['away']['name'] ?? '',
                $m['lambda_home'] ?? null,
                $m['lambda_away'] ?? null,
                $m['lambda_total'] ?? null,
                (int)($m['n_auto'] ?? 0),
                (int)($m['n_manual'] ?? 0),
                (int)($m['n_warn'] ?? 0),
                (int)($m['best_conviction'] ?? 0),
            ]
        );
        $n_matches++;

        foreach ($m['candidates'] ?? [] as $c) {
            SE_Db::execute(
                "INSERT INTO pick_candidates
                 (match_id, market, market_group, model_proba, devig_proba,
                  odds, ev, status, conviction, below_min_odds)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $match_id,
                    $c['market'],
                    $c['group'],
                    $c['model_proba'] ?? null,
                    $c['devig_proba'] ?? null,
                    $c['odds'] ?? null,
                    $c['ev'] ?? null,
                    $c['status'],
                    (int)$c['conviction'],
                    !empty($c['below_min_odds']) ? 1 : 0,
                ]
            );
            $n_candidates++;
        }
    }

    SE_Db::commit();

    // Log
    @file_put_contents(SE_LOG_FILE,
        sprintf("[%s] Import #%d OK : %d matchs, %d candidats\n",
            date('Y-m-d H:i:s'), $import_id, $n_matches, $n_candidates),
        FILE_APPEND);

    echo json_encode([
        'success'      => true,
        'import_id'    => $import_id,
        'n_matches'    => $n_matches,
        'n_candidates' => $n_candidates,
    ]);
} catch (Throwable $e) {
    SE_Db::rollback();
    http_response_code(500);
    @file_put_contents(SE_LOG_FILE,
        sprintf("[%s] Import ERROR : %s\n", date('Y-m-d H:i:s'), $e->getMessage()),
        FILE_APPEND);
    echo json_encode([
        'error' => 'DB error',
        'detail' => SE_DEBUG ? $e->getMessage() : null,
    ]);
}
