<?php
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

echo "=== RECHERCHE BET SIRIUS / HAMMARBY ===\n\n";

// Toutes colonnes pour les bets contenant Sirius ou Hammarby dans le titre
$stmt = $db->query("SELECT * FROM bets WHERE titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%' OR titre LIKE '%Robbie%' ORDER BY id DESC LIMIT 5");
$bets = $stmt->fetchAll();

if (empty($bets)) {
    echo "❌ AUCUN bet trouvé avec Sirius/Hammarby/Robbie dans le titre\n\n";
    echo "=== 5 derniers bets toutes catégories ===\n";
    $derniers = $db->query("SELECT id, titre, categorie, type, sport, resultat, actif, cote, date_post FROM bets ORDER BY id DESC LIMIT 5")->fetchAll();
    foreach ($derniers as $b) {
        echo sprintf("ID:%d | titre='%s' | cat=%s | type=%s | sport=%s | resultat=%s | actif=%s | cote=%s | %s\n",
            $b['id'], substr($b['titre'],0,40), $b['categorie'], $b['type'], $b['sport'], $b['resultat']??'NULL', $b['actif']??'NULL', $b['cote']??'NULL', $b['date_post']);
    }
    exit;
}

foreach ($bets as $b) {
    echo "─── BET ID:" . $b['id'] . " ───\n";
    foreach ($b as $col => $val) {
        if (is_string($val) && strlen($val) > 100) $val = substr($val, 0, 100) . '...';
        echo "  $col: " . var_export($val, true) . "\n";
    }
    echo "\n";
}

echo "\n=== TEST DES REQUETES DE FILTRAGE ===\n\n";

$resultFilter = "(resultat IS NULL OR resultat NOT IN ('en_cours','pending'))";
$orderBy = "ORDER BY COALESCE(date_resultat, date_post) DESC";

echo "[Tipster MULTI] WHERE $resultFilter AND categorie='multi' AND type NOT LIKE '%fun%'\n";
$test1 = $db->query("SELECT id, titre, type, categorie FROM bets WHERE $resultFilter AND categorie='multi' AND type NOT LIKE '%fun%' AND (titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%')")->fetchAll();
echo "  → Resultat: " . count($test1) . " bet(s)\n";
foreach ($test1 as $r) echo "    ID:" . $r['id'] . " " . $r['titre'] . "\n";

echo "\n[Tipster FUN] WHERE $resultFilter AND categorie='multi' AND type LIKE '%fun%'\n";
$test2 = $db->query("SELECT id, titre, type, categorie FROM bets WHERE $resultFilter AND categorie='multi' AND type LIKE '%fun%' AND (titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%')")->fetchAll();
echo "  → Resultat: " . count($test2) . " bet(s)\n";
foreach ($test2 as $r) echo "    ID:" . $r['id'] . " " . $r['titre'] . "\n";

echo "\n[Tipster TENNIS] WHERE $resultFilter AND categorie='tennis'\n";
$test3 = $db->query("SELECT id, titre, type, categorie FROM bets WHERE $resultFilter AND categorie='tennis' AND (titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%')")->fetchAll();
echo "  → Resultat: " . count($test3) . " bet(s)\n";

echo "\n=== ADMIN/HISTORIQUE.PHP — meme requete que cote admin ===\n";
$adm = $db->query("SELECT id, titre, type, categorie, sport, resultat FROM bets WHERE resultat != 'en_cours' AND (titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%')")->fetchAll();
echo "  → Resultat: " . count($adm) . " bet(s) (avec resultat != 'en_cours')\n";
foreach ($adm as $r) echo "    ID:" . $r['id'] . " | cat=" . $r['categorie'] . " | type=" . $r['type'] . " | sport=" . $r['sport'] . " | resultat=" . ($r['resultat']??'NULL') . "\n";

echo "\n=== SANS AUCUN FILTRE ===\n";
$all = $db->query("SELECT id, titre, type, categorie, sport, resultat, actif FROM bets WHERE titre LIKE '%Sirius%' OR titre LIKE '%Hammarby%'")->fetchAll();
foreach ($all as $r) echo "  ID:" . $r['id'] . " | cat=" . $r['categorie'] . " | type=" . $r['type'] . " | sport=" . $r['sport'] . " | resultat=" . var_export($r['resultat'], true) . " | actif=" . var_export($r['actif'], true) . "\n";
