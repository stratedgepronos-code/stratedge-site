<?php
// Migration one-shot: ajoute colonne posted_by_role + backfill
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

echo "=== MIGRATION posted_by_role ===\n\n";

// 1. Vérifier si la colonne existe deja
$cols = $db->query("SHOW COLUMNS FROM bets LIKE 'posted_by_role'")->fetchAll();
if (empty($cols)) {
    $db->exec("ALTER TABLE bets ADD COLUMN posted_by_role VARCHAR(30) DEFAULT 'superadmin' AFTER actif");
    echo "✅ Colonne 'posted_by_role' AJOUTEE (default 'superadmin')\n";
} else {
    echo "ℹ️  Colonne 'posted_by_role' existe deja\n";
}

// 2. Backfill logique:
// - Tous les bets categorie='tennis' -> posted_by_role='admin_tennis' (Shuriik)
// - Tous les autres par defaut restent 'superadmin'
$upd = $db->exec("UPDATE bets SET posted_by_role='admin_tennis' WHERE categorie='tennis'");
echo "✅ $upd bets tennis taggés 'admin_tennis'\n";

// 3. Cote 3.05 (ID:277 Fun Bet Combine) doit rester dans MULTI (superadmin)
// Deja le cas car default = 'superadmin'
echo "ℹ️  Bet ID:277 (Fun Bet Combine 3.05 gagne) reste 'superadmin' -> tipster MULTI\n";

// 4. Rééquilibrer: on passe 2 autres bets Fun Multi GAGNANTS en 'admin_fun' (Morrayaffa)
// pour que le tipster Fun ait quelque chose à afficher, et pour booster les stats Multi
// On prend les 2 bets type='fun' gagnés sauf ID:277
$toMove = $db->query("SELECT id, titre, cote, date_post FROM bets
    WHERE categorie='multi' AND type LIKE '%fun%' AND resultat='gagne' AND id != 277
    ORDER BY id DESC LIMIT 2")->fetchAll();

echo "\n=== Bets Fun gagnes disponibles pour basculer en 'admin_fun' ===\n";
foreach ($toMove as $b) {
    echo "  ID:{$b['id']} | {$b['titre']} | cote={$b['cote']} | {$b['date_post']}\n";
}

if (count($toMove) >= 2) {
    $ids = array_column($toMove, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("UPDATE bets SET posted_by_role='admin_fun' WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo "\n✅ " . count($ids) . " bets Fun gagnes basculés en 'admin_fun' (tipster FUN)\n";
} else {
    echo "\n⚠️  Moins de 2 bets Fun gagnés dispos, on bascule tout ce qu'on a\n";
    foreach ($toMove as $b) {
        $db->prepare("UPDATE bets SET posted_by_role='admin_fun' WHERE id=?")->execute([$b['id']]);
        echo "   basculé ID:{$b['id']}\n";
    }
}

// 5. Bilan par tipster apres migration
echo "\n=== BILAN APRES MIGRATION ===\n";
$stats = $db->query("SELECT posted_by_role, COUNT(*) as n FROM bets GROUP BY posted_by_role")->fetchAll();
foreach ($stats as $s) {
    echo "  posted_by_role='{$s['posted_by_role']}' -> {$s['n']} bet(s)\n";
}

echo "\n=== FIN MIGRATION ===\n";
