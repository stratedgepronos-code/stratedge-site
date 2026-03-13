<?php
/**
 * Migration unique : remet les rôles admin corrects.
 * Appeler une fois : https://stratedgepronos.fr/migrate-roles.php
 * Puis supprimer ce fichier.
 */
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$updates = [
    ['email' => 'Dilancarreira94@gmail.com', 'role' => 'admin_tennis'],
    ['email' => 'poze51@hotmail.fr',         'role' => 'admin_fun_sport'],
];

echo "<pre>\n";
foreach ($updates as $u) {
    $stmt = $db->prepare("UPDATE membres SET role = ? WHERE email = ?");
    $stmt->execute([$u['role'], $u['email']]);
    $n = $stmt->rowCount();
    echo "{$u['email']} → {$u['role']} : {$n} ligne(s) modifiée(s)\n";
}
echo "\nTerminé. Supprime ce fichier.\n</pre>";
