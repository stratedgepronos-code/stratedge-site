<?php
/**
 * Traite la file d'attente email + push (nouveaux bets).
 * CRON recommandé : toutes les 1–2 minutes
 *   wget -q -O /dev/null "https://stratedgepronos.fr/cron/process-notif-queue.php?token=VOTRE_TOKEN"
 *
 * Le token = même valeur que NOTIF_CRON_TOKEN dans includes/db.php (à ajouter).
 * Sinon : hash sha256 de SECRET_KEY + 'NOTIF_CRON_V1' (voir ci-dessous).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notif-queue.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expected = defined('NOTIF_CRON_TOKEN') && NOTIF_CRON_TOKEN !== ''
    ? NOTIF_CRON_TOKEN
    : hash('sha256', (defined('SECRET_KEY') ? SECRET_KEY : '') . 'NOTIF_CRON_V1');

if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$db = getDB();
$pending = notifQueuePendingCount($db);
$totalProc = 0;
$maxRounds = 50;
$round = 0;

while ($round < $maxRounds) {
    $st = notifQueueProcessBatch($db, 120);
    $totalProc += $st['processed'];
    if ($st['processed'] === 0) {
        break;
    }
    $round++;
}

$left = notifQueuePendingCount($db);
echo "OK pending_start={$pending} processed={$totalProc} pending_end={$left}\n";
if ($left > 0) {
    echo "Note: relancer le cron ou attendre la prochaine exécution pour vider la file.\n";
}
