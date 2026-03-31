<?php
/**
 * CRON SportMonks — import matchs vers les tables fd_sm_*.
 *
 * Appel HTTP (éviter les timeouts longs : plage courte, ou plusieurs crons) :
 *   wget -q -O - "https://stratedgepronos.fr/cron/sync-sportmonks-fixtures.php?token=VOTRE_AUTH_TOKEN&from=2026-03-20&to=2026-03-27"
 *
 * Paramètres :
 *   token (requis) = AUTH_TOKEN (config-keys.php), même valeur que stats-api
 *   from, to       = YYYY-MM-DD (défaut : J-3 .. J+14)
 *   enrich=1       = lineups/events/stats (beaucoup d’appels API)
 *
 * Une fois : créer les tables avec
 *   php public_html/plugins/football_sportmonks/bootstrap_tables.php
 */
declare(strict_types=1);

set_time_limit(300);

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

if (!defined('SPORTMONKS_API_TOKEN') || SPORTMONKS_API_TOKEN === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'SPORTMONKS_API_TOKEN manquant dans config-keys.php';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (!is_string($from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = gmdate('Y-m-d', strtotime('-3 days'));
}
if (!is_string($to) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = gmdate('Y-m-d', strtotime('+14 days'));
}

$enrich = isset($_GET['enrich']) && ($_GET['enrich'] === '1' || $_GET['enrich'] === 'true');

$plugin = __DIR__ . '/../plugins/football_sportmonks';
require_once $plugin . '/lib/SportmonksClient.php';
require_once $plugin . '/lib/FootballDataStore.php';
require_once $plugin . '/lib/SyncFixturesRunner.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDB();
    $client = SportmonksClient::fromEnv();
    $store = new FootballDataStore($pdo);
    $runner = new SyncFixturesRunner($pdo, $client, $store);
    $result = $runner->run($from, $to, $enrich, null);
    echo json_encode([
        'ok' => true,
        'from' => $from,
        'to' => $to,
        'enrich' => $enrich,
        'total' => $result['total'],
        'message' => $result['message'],
        'enrich_errors_count' => count($result['errors']),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
