<?php
declare(strict_types=1);
// Deployment diagnostic only: never expose a public credential-check endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = $argv[1] ?? dirname(__DIR__);
$web = is_file($root . '/public_html/admin/football-lab/lib/FootyStats.php') ? $root . '/public_html' : $root;
try {
    require_once $web . '/admin/football-lab/lib/FootyStats.php';
    $client = \StratEdgeLab\FootyStats::configured();
    if (!$client->ready()) { echo "FOOTYSTATS_NOT_CONFIGURED: clé absente de la configuration PHP du serveur.\n"; exit(2); }
    $result = $client->checkConnection();
    echo 'FOOTYSTATS_CONNECTED: ' . $result['leagues'] . " compétitions sélectionnées ; clé acceptée.\n";
} catch (Throwable $e) {
    // No raw exception, URL, response, environment variable or key in CI logs.
    echo "FOOTYSTATS_CHECK_FAILED: vérifier l’accès API, le quota et cURL sur le serveur.\n";
    exit(3);
}
