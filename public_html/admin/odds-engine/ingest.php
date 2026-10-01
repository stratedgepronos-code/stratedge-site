<?php
// STRATEDGE ODDS ENGINE — ingestion (cron toutes les 5-10 min)
// Usage : php ingest.php   |   php ingest.php --sport=tennis_atp
if (php_sapi_name() !== 'cli') die("CLI uniquement\n");
require __DIR__ . '/lib/odds-lib.php';

$cfg = oeConfig();
if ($cfg['api_key'] === '' || $cfg['api_key'] === 'VOTRE_CLE_API') die("config.local.php manquant ou cle vide\n");
$opt = getopt('', ['sport::']);
$sports = !empty($opt['sport']) ? [$opt['sport']] : $cfg['sports'];

$db = oeDb();
$now = gmdate('Y-m-d H:i:s');
$totEv = 0; $totOdds = 0;

foreach ($sports as $sport) {
    $events = oeFetchOdds($sport);
    foreach ($events as $ev) {
        if (empty($ev['odds'])) continue;
        $eid = oeUpsertEvent($db, $ev);
        $totEv++;
        foreach ($ev['odds'] as $row) {
            if (oeRecordOdds($db, $eid, $row, $now)) $totOdds++;
        }
    }
    usleep(300000); // courtoisie API
}

// Capture des clotures : events qui demarrent dans <= closing_window_min et pas encore figes
$win = (int)$cfg['closing_window_min'];
$q = $db->prepare(
    "SELECT id FROM oe_events
     WHERE status='open' AND commence_time <= UTC_TIMESTAMP() + INTERVAL ? MINUTE");
$q->execute([$win]);
$closed = 0;
foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $eid) { oeCaptureClosing($db, (int)$eid); $closed++; }

oeLog("ingest: $totEv events, $totOdds mouvements enregistres, $closed clotures figees");
echo "OK — $totEv events | $totOdds mouvements | $closed clotures\n";
