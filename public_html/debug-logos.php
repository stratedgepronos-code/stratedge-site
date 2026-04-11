<?php
// Debug logos - accès restreint admin
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/logo-fallback.php';

header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'Stade Rennais FC',
    'Angers SCO',
    'PSG',
    'Paris Saint Germain',
    'Olympique Marseille',
    'Lille OSC',
    'Real Madrid',
    'Manchester City',
    'Boston Celtics',
];

echo "=== TEST DB LOCALE LOGOS ===\n\n";
foreach ($tests as $team) {
    $url = stratedge_fetch_team_logo_url($team, 'football');
    if ($team === 'Boston Celtics') {
        $url = stratedge_fetch_team_logo_url($team, 'basket');
    }
    echo str_pad($team, 30) . " → " . ($url ?: '❌ VIDE') . "\n";
}

echo "\n=== TEST FONCTION LOCALE DIRECTE ===\n\n";
require_once __DIR__ . '/includes/team-logos-db.php';
foreach ($tests as $team) {
    $sport = ($team === 'Boston Celtics') ? 'basket' : 'football';
    $url = stratedge_local_team_logo($team, $sport);
    echo str_pad($team, 30) . " → " . ($url ?: '❌ VIDE') . "\n";
}
