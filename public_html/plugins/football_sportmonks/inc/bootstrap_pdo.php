<?php
declare(strict_types=1);

/**
 * StratEdge : BDD via includes/db.php (getDB), ou local_config.php / FD_DB_*.
 *
 * @return PDO
 */
$local = __DIR__ . '/../local_config.php';
if (is_readable($local)) {
    require $local;
}

if (!isset($GLOBALS['fd_pdo']) || !($GLOBALS['fd_pdo'] instanceof PDO)) {
    $publicHtml = dirname(__DIR__, 3);
    $dbFile = $publicHtml . '/includes/db.php';
    if (is_readable($dbFile)) {
        require_once $dbFile;
        if (function_exists('getDB')) {
            $GLOBALS['fd_pdo'] = getDB();
        }
    }
}

if (!isset($GLOBALS['fd_pdo']) || !($GLOBALS['fd_pdo'] instanceof PDO)) {
    $host = getenv('FD_DB_HOST') ?: 'localhost';
    $name = getenv('FD_DB_NAME') ?: '';
    $user = getenv('FD_DB_USER') ?: '';
    $pass = getenv('FD_DB_PASS') ?: '';
    if ($name === '' || $user === '') {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "BDD introuvable : includes/db.php, local_config.php ou FD_DB_*.\n");
        }
        exit(1);
    }
    $GLOBALS['fd_pdo'] = new PDO(
        'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

return $GLOBALS['fd_pdo'];
