<?php
require_once __DIR__ . '/includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

echo "=== SCHEMA TABLE bets ===\n\n";
$cols = $db->query("SHOW COLUMNS FROM bets")->fetchAll();
foreach ($cols as $c) {
    echo sprintf("  %-30s %-20s %s\n", $c['Field'], $c['Type'], $c['Null']);
}

echo "\n=== Exemples de 'type' distincts en BDD ===\n";
$types = $db->query("SELECT DISTINCT type, COUNT(*) as n FROM bets GROUP BY type ORDER BY n DESC")->fetchAll();
foreach ($types as $t) {
    echo "  type='{$t['type']}' -> {$t['n']} bet(s)\n";
}

echo "\n=== Est-ce qu'un admin Fun a deja poste des bets ? ===\n";
echo "(Cherche dans poster-bet.php qui pose categorie='multi' type='fun')\n";
echo "Les 'Fun Combine' comme ID:277 sont postes par QUI actuellement ?\n";
echo "Impossible de le savoir en BDD car aucune colonne 'posted_by' n'existe.\n\n";

echo "=== Comptage bets type='fun' avec categorie='multi' ===\n";
$fun = $db->query("SELECT id, titre, date_post FROM bets WHERE categorie='multi' AND type LIKE '%fun%' ORDER BY id DESC LIMIT 10")->fetchAll();
foreach ($fun as $b) echo "  ID:{$b['id']} | {$b['titre']} | {$b['date_post']}\n";
