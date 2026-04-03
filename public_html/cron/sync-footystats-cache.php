<?php
/**
 * CRON — remplit le cache FootyStats (fd_fy_*).
 *
 *   wget -q -O - "https://stratedgepronos.fr/cron/sync-footystats-cache.php?token=AUTH_TOKEN"
 *
 * Paramètres :
 *   from, to   YYYY-MM-DD (défaut : J-2 .. J+10)
 *   enrich=1   appelle /matches pour chaque match (coûteux)
 *   leagues=1  met à jour league-list
 */
declare(strict_types=1);

set_time_limit(600);

$keysFile = __DIR__ . '/../config-keys.php';
if (!is_readable($keysFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'config-keys.php manquant';
    exit;
}
require_once $keysFile;

if (!defined('AUTH_TOKEN') || AUTH_TOKEN === '' || AUTH_TOKEN === 'REPLACE-ME-STRONG-TOKEN') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'AUTH_TOKEN non configuré';
    exit;
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token === '' || !is_string($token) || !hash_equals(AUTH_TOKEN, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

if (!defined('FOOTYSTATS_API_KEY') || FOOTYSTATS_API_KEY === '' || FOOTYSTATS_API_KEY === 'REPLACE-ME') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'FOOTYSTATS_API_KEY manquant';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (!is_string($from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = gmdate('Y-m-d', strtotime('-2 days'));
}
if (!is_string($to) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = gmdate('Y-m-d', strtotime('+10 days'));
}

$enrich = isset($_GET['enrich']) && ($_GET['enrich'] === '1' || $_GET['enrich'] === 'true');
$leagues = isset($_GET['leagues']) && ($_GET['leagues'] === '1' || $_GET['leagues'] === 'true');

$plugin = __DIR__ . '/../plugins/football_footystats';
require_once $plugin . '/lib/FootyStatsClient.php';
require_once $plugin . '/lib/FootyStatsStore.php';
require_once $plugin . '/lib/SyncFootyStatsRunner.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDB();
    $client = FootyStatsClient::fromEnv();
    $store = new FootyStatsStore($pdo);
    $runner = new SyncFootyStatsRunner($pdo, $client, $store);
    $r = $runner->syncDateRange($from, $to, $enrich, $leagues, null);
    echo json_encode([
        'ok' => true,
        'from' => $from,
        'to' => $to,
        'enrich' => $enrich,
        'leagues' => $leagues,
        'matches_upserted' => $r['matches_upserted'],
        'dates' => $r['dates'],
        'message' => $r['message'],
        'enrich_errors' => $r['enrich_errors'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
