<?php
/**
 * Synchronise les matchs SportMonks (StratEdge).
 *
 * Usage :
 *   php sync_fixtures.php 2025-03-01 2025-04-15
 *   php sync_fixtures.php 2025-03-01 2025-04-15 --enrich
 *
 * Clés : SPORTMONKS_API_TOKEN dans config-keys.php (ou env / local_config.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/load_config.php';
require_once __DIR__ . '/lib/SportmonksClient.php';
require_once __DIR__ . '/lib/FootballDataStore.php';
require_once __DIR__ . '/lib/SyncFixturesRunner.php';

$pdo = require __DIR__ . '/inc/bootstrap_pdo.php';

$argvList = array_slice($argv, 1);
$enrich = in_array('--enrich', $argvList, true);
$argvList = array_values(array_filter($argvList, static fn ($a) => $a !== '--enrich'));

if (count($argvList) < 2) {
    fwrite(STDERR, "Usage: php sync_fixtures.php YYYY-MM-DD YYYY-MM-DD [--enrich]\n");
    exit(1);
}

$start = $argvList[0];
$end = $argvList[1];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    fwrite(STDERR, "Dates invalides (attendu YYYY-MM-DD).\n");
    exit(1);
}

try {
    $client = SportmonksClient::fromEnv();
    $store = new FootballDataStore($pdo);
    $runner = new SyncFixturesRunner($pdo, $client, $store);
    $result = $runner->run($start, $end, $enrich, static fn (string $line) => fwrite(STDOUT, $line));
    echo $result['message'] . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
