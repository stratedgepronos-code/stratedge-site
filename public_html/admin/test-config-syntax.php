<?php
// Test syntaxe config-keys.php SANS le charger dans le contexte du site
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC config-keys.php ===\n\n";

$paths = [
    __DIR__ . '/../config-keys.php',
    __DIR__ . '/../../config-keys.php',
    dirname(__DIR__, 2) . '/config-keys.php',
];

$found = null;
foreach ($paths as $p) {
    echo "Test: $p\n";
    if (is_file($p)) {
        echo "  ✅ EXISTE (taille: " . filesize($p) . "o)\n";
        $found = $p;
        break;
    } else {
        echo "  ❌ pas trouvé\n";
    }
}

if (!$found) {
    echo "\n❌ config-keys.php INTROUVABLE. Il doit être dans public_html/\n";
    exit;
}

echo "\nFichier trouvé: $found\n";

// Test syntaxe PHP via CLI si dispo, sinon via eval sécurisé
echo "\n=== CONTENU (premières/dernières lignes) ===\n";
$content = file_get_contents($found);
$lines = explode("\n", $content);
echo "Nombre de lignes: " . count($lines) . "\n\n";

echo "5 premières lignes:\n";
for ($i = 0; $i < min(5, count($lines)); $i++) {
    echo "  " . ($i+1) . ": " . $lines[$i] . "\n";
}

echo "\n5 dernières lignes:\n";
$start = max(0, count($lines) - 5);
for ($i = $start; $i < count($lines); $i++) {
    echo "  " . ($i+1) . ": " . $lines[$i] . "\n";
}

// Check basique : balises PHP
echo "\n=== CHECKS SYNTAXE ===\n";
echo "Commence par <?php : " . (str_starts_with(ltrim($content), '<?php') ? '✅' : '❌') . "\n";
echo "Contient STRIPE_MULTI_SK : " . (strpos($content, 'STRIPE_MULTI_SK') !== false ? '✅' : '❌') . "\n";
echo "Contient STRIPE_MULTI_PK : " . (strpos($content, 'STRIPE_MULTI_PK') !== false ? '✅' : '❌') . "\n";
echo "Contient STRIPE_MULTI_WHSEC : " . (strpos($content, 'STRIPE_MULTI_WHSEC') !== false ? '✅' : '❌') . "\n";
echo "Balance parenthèses : " . (substr_count($content, '(') === substr_count($content, ')') ? '✅' : "❌ (".substr_count($content,'(')." ouvertes, ".substr_count($content,')')." fermées)") . "\n";

// Tenter shell_exec pour php -l (lint)
echo "\n=== LINT PHP ===\n";
if (function_exists('shell_exec')) {
    $lint = @shell_exec('php -l ' . escapeshellarg($found) . ' 2>&1');
    if ($lint) {
        echo $lint;
    } else {
        echo "shell_exec indisponible ou php CLI introuvable\n";
    }
} else {
    echo "shell_exec désactivé\n";
}

// Vérifier les constantes définies
echo "\n=== CONSTANTES STRIPE DÉFINIES ===\n";
if (defined('STRIPE_MULTI_SK')) {
    $sk = constant('STRIPE_MULTI_SK');
    echo "STRIPE_MULTI_SK : ✅ définie (" . substr($sk, 0, 12) . "... " . strlen($sk) . "c)\n";
} else {
    echo "STRIPE_MULTI_SK : ❌ NON DÉFINIE\n";
}
if (defined('STRIPE_MULTI_PK')) {
    $pk = constant('STRIPE_MULTI_PK');
    echo "STRIPE_MULTI_PK : ✅ définie (" . substr($pk, 0, 12) . "... " . strlen($pk) . "c)\n";
} else {
    echo "STRIPE_MULTI_PK : ❌ NON DÉFINIE\n";
}
if (defined('STRIPE_MULTI_WHSEC')) {
    $wh = constant('STRIPE_MULTI_WHSEC');
    echo "STRIPE_MULTI_WHSEC : ✅ définie (" . substr($wh, 0, 12) . "... " . strlen($wh) . "c)\n";
} else {
    echo "STRIPE_MULTI_WHSEC : ❌ NON DÉFINIE\n";
}
