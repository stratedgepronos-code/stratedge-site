<?php
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

echo "=== CORRECTION ATTRIBUTION DES FUN ===\n\n";

echo "Objectif: tous les bets type LIKE '%fun%' SAUF ID:277 doivent aller chez admin_fun\n";
echo "         ID:277 (Fun Bet Combine 3.05 gagne) reste chez superadmin\n\n";

// 1. Voir l'etat actuel
echo "=== AVANT ===\n";
$etatAvant = $db->query("SELECT id, titre, cote, resultat, posted_by_role FROM bets
    WHERE type LIKE '%fun%' AND categorie='multi'
    ORDER BY id DESC")->fetchAll();
foreach ($etatAvant as $b) {
    echo sprintf("  ID:%-4d | role=%-13s | cote=%-6s | resultat=%-10s | %s\n",
        $b['id'], $b['posted_by_role'], $b['cote'], $b['resultat'] ?? 'NULL', substr($b['titre'], 0, 40));
}

echo "\n=== ACTION ===\n";

// 2. Basculer TOUS les Fun (type LIKE '%fun%' + categorie='multi') vers admin_fun SAUF ID:277
$upd = $db->exec("UPDATE bets SET posted_by_role='admin_fun'
    WHERE type LIKE '%fun%' AND categorie='multi' AND id != 277");
echo "✅ $upd bets Fun (hors ID:277) basculés vers admin_fun\n";

// 3. S'assurer que ID:277 reste chez superadmin
$upd2 = $db->exec("UPDATE bets SET posted_by_role='superadmin' WHERE id=277");
echo "✅ ID:277 (Fun Bet 3.05) force a posted_by_role='superadmin'\n";

// 4. Bilan apres
echo "\n=== APRES ===\n";
$etatApres = $db->query("SELECT id, titre, cote, resultat, posted_by_role FROM bets
    WHERE type LIKE '%fun%' AND categorie='multi'
    ORDER BY id DESC")->fetchAll();
foreach ($etatApres as $b) {
    echo sprintf("  ID:%-4d | role=%-13s | cote=%-6s | resultat=%-10s | %s\n",
        $b['id'], $b['posted_by_role'], $b['cote'], $b['resultat'] ?? 'NULL', substr($b['titre'], 0, 40));
}

// 5. Bilan total
echo "\n=== BILAN TIPSTERS ===\n";
$totaux = $db->query("SELECT posted_by_role, COUNT(*) as n FROM bets GROUP BY posted_by_role")->fetchAll();
foreach ($totaux as $t) echo "  {$t['posted_by_role']} -> {$t['n']} bet(s)\n";

echo "\n=== FIN ===\n";
