<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/team-logos-db.php';
header('Content-Type: text/plain; charset=utf-8');

$names = ['Sunderland', 'Sunderland AF', 'Sunderland AFC', 'Tottenham Hotspur', 'Tottenham Hotspur FC', 'Tottenham'];

echo "=== MAPPING JSON ===\n";
$f = __DIR__ . '/../assets/logos/mapping.json';
echo "File: $f — exists: ".(is_file($f)?'YES':'NO')."\n";
if (is_file($f)) {
    $m = json_decode(file_get_contents($f), true);
    echo "Sports: ".implode(', ', array_keys($m))."\n";
    echo "Football count: ".count($m['football'] ?? [])."\n";
    echo "Sunderland in mapping: ".(isset($m['football']['sunderland']) ? 'YES' : 'NO')."\n";
    echo "Tottenham-hotspur in mapping: ".(isset($m['football']['tottenham-hotspur']) ? 'YES' : 'NO')."\n";
}

echo "\n=== FILE CHECKS ===\n";
foreach (['football/sunderland.png', 'football/tottenham-hotspur.png'] as $p) {
    $abs = __DIR__ . '/../assets/logos/' . $p;
    echo "$p → exists: ".(is_file($abs)?'YES':'NO').(is_file($abs)?' ('.filesize($abs).'o)':'')."\n";
}

echo "\n=== MATCHING TESTS ===\n";
foreach ($names as $n) {
    $r = stratedge_local_team_logo($n, 'football');
    echo "'$n' → '".($r ?: 'EMPTY')."'\n";
}
