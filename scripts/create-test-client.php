<?php
/**
 * Crée un compte membre « client lambda » pour tests (paiements, parcours site).
 *
 * Usage (sur le serveur ou en local avec accès MySQL) :
 *   php scripts/create-test-client.php
 *   php scripts/create-test-client.php mon@email.com "MonMotDePasse!"
 *   php scripts/create-test-client.php mon@email.com "MonMotDePasse!" "Prénom Nom"
 *
 * Compte par défaut (si aucun argument) :
 *   Email : stratedgepronos+clienttest@gmail.com  (alias Gmail → même boîte que l’admin)
 *   Mot de passe : TestSandbox2026!
 *
 * ⚠️ Exécution CLI uniquement (pas d’accès HTTP).
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script est réservé à la ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/public_html/includes/auth.php';

$email = $argv[1] ?? 'stratedgepronos+clienttest@gmail.com';
$password = $argv[2] ?? 'TestSandbox2026!';
$nom = $argv[3] ?? 'Client Test Paiements';

if (strlen($password) < 8) {
    fwrite(STDERR, "Le mot de passe doit faire au moins 8 caractères.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email invalide.\n");
    exit(1);
}

$db = getDB();
$stmt = $db->prepare('SELECT id, nom, actif, banni FROM membres WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    fwrite(STDOUT, "Un compte existe déjà avec cet email.\n");
    fwrite(STDOUT, '  id=' . (int) $existing['id'] . ' | nom=' . $existing['nom'] . "\n");
    fwrite(STDOUT, '  actif=' . (int) $existing['actif'] . ' | banni=' . (int) $existing['banni'] . "\n");
    fwrite(STDOUT, "\nPour réinitialiser le mot de passe, utilisez « mot de passe oublié » sur le site\n");
    fwrite(STDOUT, "ou exécutez une requête SQL / un petit script UPDATE avec password_hash().\n");
    exit(0);
}

$result = registerMembre($nom, $email, $password, 1, null);

if (!$result['success']) {
    fwrite(STDERR, 'Erreur : ' . ($result['error'] ?? 'inconnue') . "\n");
    exit(1);
}

fwrite(STDOUT, "Compte de test créé.\n\n");
fwrite(STDOUT, "  URL connexion : " . (defined('SITE_URL') ? SITE_URL : '') . "/login.php\n");
fwrite(STDOUT, "  Email         : {$email}\n");
fwrite(STDOUT, "  Mot de passe  : {$password}\n");
fwrite(STDOUT, "  id membre     : " . (int) $result['id'] . "\n");
fwrite(STDOUT, "\nTu peux t’en servir comme un client normal (StarPass, crypto, etc.).\n");
exit(0);
