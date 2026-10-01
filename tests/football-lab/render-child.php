<?php
// Isolated CLI request against a disposable fixture site, never production.
declare(strict_types=1);
$root = $argv[1];
$page = $argv[2];
$request = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
putenv('STRATEDGE_LAB_OPENAI_KEY=');
putenv('STRATEDGE_LAB_OPENAI_MODEL=');
session_start();
$_SESSION = $request['session'] ?? ['membre_id' => 123, 'membre_email' => 'lab-test@example.test', 'is_admin' => true, 'csrf_token' => 'fixture-token'];
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = '/panel-x9k3m/football-lab/' . $page;
register_shutdown_function(static function () {
    file_put_contents('php://stderr', 'HTTP_STATUS=' . (http_response_code() ?: 200));
});
require $root . '/public_html/admin/football-lab/' . $page;
