<?php
/**
 * Contexte JSON d’un match FootyStats en base (Claude / backend).
 *
 * GET ?fy_match_id=123&token=AUTH_TOKEN
 * Headers : X-STRATEDGE-TOKEN (ou X-Football-Internal-Token)
 *
 * refresh=1 : re-fetch FootyStats /matches puis met à jour la BDD (consomme quota API).
 */
declare(strict_types=1);

$keysFile = dirname(__DIR__, 2) . '/config-keys.php';
if (is_readable($keysFile)) {
    require_once $keysFile;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://stratedgepronos.fr');

$secret = '';
if (defined('FOOTBALL_CONTEXT_TOKEN') && is_string(FOOTBALL_CONTEXT_TOKEN) && FOOTBALL_CONTEXT_TOKEN !== '') {
    $secret = FOOTBALL_CONTEXT_TOKEN;
} elseif (defined('AUTH_TOKEN') && is_string(AUTH_TOKEN) && AUTH_TOKEN !== '' && AUTH_TOKEN !== 'REPLACE-ME-STRONG-TOKEN') {
    $secret = AUTH_TOKEN;
}

if ($secret === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'AUTH_TOKEN ou FOOTBALL_CONTEXT_TOKEN requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $_GET['token'] ?? '';
if ($token === '' || !is_string($token)) {
    $token = $_SERVER['HTTP_X_STRATEDGE_TOKEN'] ?? $_SERVER['HTTP_X_FOOTBALL_INTERNAL_TOKEN'] ?? '';
}
if (!is_string($token) || !hash_equals($secret, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$matchId = isset($_GET['fy_match_id']) ? (int) $_GET['fy_match_id'] : 0;
if ($matchId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'fy_match_id requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/lib/FootyStatsClient.php';
require_once __DIR__ . '/lib/FootyStatsStore.php';

try {
    $pdo = require __DIR__ . '/inc/bootstrap_pdo.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'BDD'], JSON_UNESCAPED_UNICODE);
    exit;
}

$store = new FootyStatsStore($pdo);

$refresh = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || $_GET['refresh'] === 'true');
if ($refresh) {
    if (!defined('FOOTYSTATS_API_KEY') || FOOTYSTATS_API_KEY === '' || FOOTYSTATS_API_KEY === 'REPLACE-ME') {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'FOOTYSTATS_API_KEY manquant'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $client = FootyStatsClient::fromEnv();
        $detail = $client->matchById($matchId);
        $store->mergeMatchDetail($matchId, $detail);
    } catch (Throwable $e) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$ctx = $store->getMatchContext($matchId);
if ($ctx === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Match inconnu en base — lancer sync ou refresh=1'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'data' => $ctx], JSON_UNESCAPED_UNICODE);
