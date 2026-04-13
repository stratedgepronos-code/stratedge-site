<?php
require_once __DIR__ . '/includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

echo "=== TOUS LES BETS RECENTS (10 derniers IDs) ===\n\n";
$derniers = $db->query("SELECT id, titre, categorie, type, sport, resultat, actif, cote, date_post FROM bets ORDER BY id DESC LIMIT 10")->fetchAll();
foreach ($derniers as $b) {
    echo sprintf("ID:%d | %-40s | cat=%-7s | type=%-10s | sport=%-10s | resultat=%-10s | actif=%s | cote=%s | %s\n",
        $b['id'],
        substr($b['titre'] ?? '', 0, 40),
        $b['categorie'] ?? 'NULL',
        $b['type'] ?? 'NULL',
        $b['sport'] ?? 'NULL',
        var_export($b['resultat'], true),
        var_export($b['actif'], true),
        $b['cote'] ?? 'NULL',
        $b['date_post']
    );
}

echo "\n\n=== RECHERCHE 'Sirius' / 'Hammarby' / 'Robbie' ===\n\n";
$found = $db->query("SELECT * FROM bets WHERE titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%' OR titre LIKE '%Robbie%' OR titre LIKE '%IK %' OR titre LIKE '%IF%' ORDER BY id DESC LIMIT 5")->fetchAll();
if (empty($found)) {
    echo "AUCUN match dans le titre.\n";
    echo "Le bet a peut-etre un titre different. Cherche dans la liste ci-dessus.\n";
} else {
    foreach ($found as $b) {
        echo "─── ID:" . $b['id'] . " ───\n";
        foreach ($b as $col => $val) {
            if (is_string($val) && strlen($val) > 80) $val = substr($val, 0, 80) . '...';
            echo "  $col = " . var_export($val, true) . "\n";
        }
        echo "\n";
    }
}
