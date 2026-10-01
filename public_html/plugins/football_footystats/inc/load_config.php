<?php
declare(strict_types=1);

$keys = dirname(__DIR__, 3) . '/config-keys.php';
if (is_readable($keys)) {
    require_once $keys;
}

$local = dirname(__DIR__) . '/local_config.php';
if (is_readable($local)) {
    require_once $local;
}
