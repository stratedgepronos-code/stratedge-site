<?php
/**
 * STRATEDGE — Sauvegarde quotidienne des comptes membres (CRON)
 * À exécuter une fois par jour via crontab :
 * 0 3 * * * /usr/bin/php /chemin/vers/public_html/cron/backup-membres.php
 *
 * Sauvegarde : membres, abonnements, push_subscriptions
 * Fichiers dans public_html/backups/ (accès web refusé via .htaccess)
 * Conservation : 30 jours
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/includes/db.php';

// Exécution en CLI uniquement (ou via URL avec clé secrète pour cron hébergeur)
$key = getenv('CRON_BACKUP_KEY') ?: ($_GET['key'] ?? '');
$secret = 'backup_membres_' . (defined('SECRET_KEY') ? SECRET_KEY : 'changez_moi');
if (php_sapi_name() !== 'cli' && $key !== $secret) {
    http_response_code(403);
    exit('Accès refusé.');
}

$backupDir = $baseDir . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

// Protéger le dossier backups (interdire accès web)
$htaccess = $backupDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Require all denied\n");
}

$date = date('Y-m-d');
$file = $backupDir . '/membres_' . $date . '.sql';

$db = getDB();
$tables = ['membres', 'abonnements', 'push_subscriptions'];
$sql = "-- StratEdge — Sauvegarde comptes membres — " . date('Y-m-d H:i:s') . "\n";
$sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    try {
        $stmt = $db->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row) {
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $row[1] . ";\n\n";
        }

        $stmt = $db->query("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 0) {
            $sql .= "-- Table {$table} : 0 ligne\n\n";
            continue;
        }

        $cols = array_keys($rows[0]);
        $colList = '`' . implode('`,`', $cols) . '`';
        $lines = [];
        foreach ($rows as $r) {
            $vals = [];
            foreach ($cols as $c) {
                $v = $r[$c];
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $db->quote((string) $v);
                }
            }
            $lines[] = '(' . implode(',', $vals) . ')';
        }
        $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $lines) . ";\n\n";
    } catch (Throwable $e) {
        $sql .= "-- Erreur table {$table} : " . $e->getMessage() . "\n\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

if (file_put_contents($file, $sql) !== false) {
    if (php_sapi_name() === 'cli') {
        echo "OK: " . $file . "\n";
    }
} else {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Erreur écriture: " . $file . "\n");
    }
    exit(1);
}

// Conserver uniquement les 30 derniers jours
$keep = 30;
$files = glob($backupDir . '/membres_*.sql');
if ($files) {
    usort($files, function ($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    while (count($files) > $keep) {
        $old = array_shift($files);
        @unlink($old);
    }
}

exit(0);
