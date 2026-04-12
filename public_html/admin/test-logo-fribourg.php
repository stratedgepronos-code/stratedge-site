<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/team-logos-db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST FRIBOURG/FREIBURG ===\n\n";

// 1. Check file
$f = __DIR__ . '/../assets/logos/football/freiburg.png';
echo "1. File freiburg.png: " . (is_file($f)?'YES ('.filesize($f).'o)':'NO') . "\n";

// 2. Check mapping
$map = json_decode(@file_get_contents(__DIR__.'/../assets/logos/mapping.json'), true);
echo "2. In mapping['football']['freiburg']: " . (isset($map['football']['freiburg'])?'YES':'NO') . "\n";

// 3. Test alias
echo "3. Alias 'Fribourg' → '" . stratedge_normalize_team_alias('Fribourg') . "'\n";

// 4. Test matching
foreach (['Fribourg','fribourg','Freiburg','SC Freiburg','Friburgo'] as $n) {
    echo "4. Match '$n' → '" . (stratedge_local_team_logo($n,'football') ?: 'EMPTY') . "'\n";
}

// 5. Purge cache
$cache = __DIR__ . '/../assets/logos-cache';
if (is_dir($cache)) {
    $files = glob($cache . '/*.url');
    $deleted = 0;
    foreach ($files as $file) { if (unlink($file)) $deleted++; }
    echo "\n5. Cache purgé: $deleted fichiers supprimés de $cache\n";
} else {
    echo "\n5. Pas de cache /assets/logos-cache\n";
}
