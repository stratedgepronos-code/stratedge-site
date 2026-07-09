<?php
/**
 * STRATEDGE — Sauvegarde quotidienne des comptes membres (CRON)
 * À exécuter une fois par jour via crontab :
 * 0 3 * * * /usr/bin/php /chemin/vers/public_html/cron/backup-membres.php
 *
 * Sauvegarde : membres, abonnements, push_subscriptions
 * Fichiers HORS webroot (private-backups/membres) — CLI uniquement
 * Conservation : 30 jours
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/includes/db.php';

// CLI STRICT : un dump de comptes membres ne doit JAMAIS être déclenchable par HTTP.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Accès refusé.');
}

// Dossier de backup HORS webroot (nginx ignore les .htaccess -> jamais dans public_html).
// Surchargeable via BACKUP_MEMBRES_DIR dans config-keys.php.
$backupDir = defined('BACKUP_MEMBRES_DIR')
    ? BACKUP_MEMBRES_DIR
    : dirname($baseDir) . '/private-backups/membres';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0700, true);
}
@chmod($backupDir, 0700);

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
