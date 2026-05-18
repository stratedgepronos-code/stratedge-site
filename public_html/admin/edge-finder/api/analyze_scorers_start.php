<?php
/**
 * STRATEDGE - Edge Finder - START analyse buteurs (asynchrone)
 * ================================================================================
 *
 * GET ?match_id=N&force=0|1
 *
 * - Si une analyse 'done' existe en cache et pas de force -> renvoie 'cached'
 * - Sinon : cree/reset le job en 'pending', lance le worker en tache de fond,
 *   et repond immediatement {status: 'started'}. Le navigateur fait ensuite
 *   du polling sur analyze_scorers_status.php.
 *
 * Reponses JSON :
 *   {status: 'cached'}    -> resultat deja dispo, polling renverra 'done'
 *   {status: 'started'}   -> worker lance, faire du polling
 *   {status: 'running'}   -> un worker tourne deja pour ce match
 *   {error: '...'}
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$match_id = (int)($_GET['match_id'] ?? 0);
$force = !empty($_GET['force']);
if ($match_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'match_id required']);
    exit;
}

// ===== Etat actuel du job pour ce match =====
$existing = SE_Db::queryOne(
    "SELECT status, started_at FROM match_scorer_analysis WHERE match_id = ?",
    [$match_id]
);

// Cache hit : analyse deja faite et pas de force
if ($existing && $existing['status'] === 'done' && !$force) {
    echo json_encode(['status' => 'cached']);
    exit;
}

// Un worker tourne deja (job 'running' recent < 5 min) : ne pas relancer
if ($existing && $existing['status'] === 'running' && !$force) {
    $age = $existing['started_at']
        ? (time() - strtotime($existing['started_at']))
        : 9999;
    if ($age < 300) {
        echo json_encode(['status' => 'running']);
        exit;
    }
    // sinon : worker zombie (>5min), on relance
}

// ===== Cree / reset le job en 'pending' =====
SE_Db::execute(
    "INSERT INTO match_scorer_analysis (match_id, status, started_at)
     VALUES (?, 'pending', NULL)
     ON DUPLICATE KEY UPDATE status = 'pending', error_msg = NULL, started_at = NULL",
    [$match_id]
);

// ===== Lance le worker en tache de fond (HTTP non-bloquant a soi-meme) =====
if (!defined('SE_WORKER_TOKEN')) {
    http_response_code(500);
    echo json_encode(['error' => 'SE_WORKER_TOKEN non configure']);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'stratedgepronos.fr';
$base = dirname($_SERVER['REQUEST_URI'] ?? '/panel-x9k3m/edge-finder/api/start');
$worker_url = $scheme . '://' . $host . $base . '/analyze_scorers_worker.php'
    . '?match_id=' . $match_id
    . '&worker_token=' . urlencode(SE_WORKER_TOKEN);

// curl non-bloquant : timeout tres court, on n'attend PAS la reponse du worker.
// Le worker continue de tourner cote serveur apres qu'on a coupe.
$ch = curl_init($worker_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT_MS => 800,        // on coupe vite : le worker est lance, il continue
    CURLOPT_NOSIGNAL => true,
    CURLOPT_FRESH_CONNECT => true,
]);
curl_exec($ch);
curl_close($ch);
// On ne verifie pas le retour : un timeout ici est NORMAL et attendu.

echo json_encode(['status' => 'started']);
