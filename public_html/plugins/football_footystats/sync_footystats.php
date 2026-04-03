<?php
/**
 * Sync FootyStats → tables fd_fy_*.
 *
 *   php sync_footystats.php 2026-03-20 2026-03-27
 *   php sync_footystats.php 2026-03-20 2026-03-27 --enrich
 *   php sync_footystats.php 2026-03-20 2026-03-27 --leagues
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/load_config.php';
require_once __DIR__ . '/lib/FootyStatsClient.php';
require_once __DIR__ . '/lib/FootyStatsStore.php';
require_once __DIR__ . '/lib/SyncFootyStatsRunner.php';

$pdo = require __DIR__ . '/inc/bootstrap_pdo.php';

$args = array_slice($argv, 1);
$enrich = in_array('--enrich', $args, true);
$leagues = in_array('--leagues', $args, true);
$args = array_values(array_filter($args, static fn ($a) => $a !== '--enrich' && $a !== '--leagues'));

if (count($args) < 2) {
    fwrite(STDERR, "Usage: php sync_footystats.php YYYY-MM-DD YYYY-MM-DD [--enrich] [--leagues]\n");
    exit(1);
}

$start = $args[0];
$end = $args[1];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    fwrite(STDERR, "Dates invalides.\n");
    exit(1);
}

try {
    $client = FootyStatsClient::fromEnv();
    $store = new FootyStatsStore($pdo);
    $runner = new SyncFootyStatsRunner($pdo, $client, $store);
    $r = $runner->syncDateRange($start, $end, $enrich, $leagues, static fn (string $l) => fwrite(STDOUT, $l));
    echo $r['message'] . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
