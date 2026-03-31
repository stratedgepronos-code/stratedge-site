<?php
/**
 * Contexte JSON d’un match SportMonks stocké (Claude / outils internes).
 *
 * URL : /plugins/football_sportmonks/football_internal_api.php?fixture_sm_id=ID&token=AUTH_TOKEN
 * Headers acceptés : X-STRATEDGE-TOKEN, X-Football-Internal-Token (même valeur secrète).
 *
 * Secret : FOOTBALL_CONTEXT_TOKEN dans config-keys.php si défini, sinon AUTH_TOKEN (comme stats-api.php).
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
    echo json_encode(['ok' => false, 'error' => 'AUTH_TOKEN ou FOOTBALL_CONTEXT_TOKEN requis dans config-keys.php'], JSON_UNESCAPED_UNICODE);
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

$fixtureId = isset($_GET['fixture_sm_id']) ? (int) $_GET['fixture_sm_id'] : 0;
if ($fixtureId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'fixture_sm_id requis'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/lib/FootballDataStore.php';

try {
    $pdo = require __DIR__ . '/inc/bootstrap_pdo.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'BDD'], JSON_UNESCAPED_UNICODE);
    exit;
}

$store = new FootballDataStore($pdo);
$ctx = $store->getFixtureContext($fixtureId);

if ($ctx === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Fixture inconnu'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'data' => $ctx], JSON_UNESCAPED_UNICODE);
