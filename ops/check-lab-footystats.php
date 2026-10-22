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
    // Only fixed classifications from this client's own messages; no raw errors or secrets.
    $categories = [
        'Extension PHP cURL indisponible.' => 'PHP_CURL_MISSING',
        'Clé FootyStats refusée ou accès à cette compétition non autorisé.' => 'ACCESS_DENIED',
        'Quota FootyStats atteint. Réessaie après son renouvellement.' => 'QUOTA_EXCEEDED',
        'FootyStats indisponible : connexion ou réponse HTTP invalide.' => 'NETWORK_OR_HTTP',
        'Réponse FootyStats invalide ou accès non autorisé.' => 'INVALID_RESPONSE_OR_ACCESS',
    ];
    $category = $categories[$e->getMessage()] ?? 'LOCAL_OR_PROVIDER_ERROR';
    echo "FOOTYSTATS_CHECK_FAILED: " . $category . "\n";
    // A spent provider quota is an operational warning, not a broken release.
    // Keep authentication, runtime and malformed-response failures blocking.
    if ($category === 'QUOTA_EXCEEDED') {
        echo "FOOTYSTATS_DEPLOY_WARNING: quota épuisé ; enrichissement FootyStats indisponible, déploiement des autres modules autorisé.\n";
        exit(0);
    }
    exit(3);
}
