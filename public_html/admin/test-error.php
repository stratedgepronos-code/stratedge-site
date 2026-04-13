<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

// Afficher TOUTES les erreurs PHP
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "=== TEST MINIMAL ERREUR PHP ===\n\n";

echo "[1] Avant require payment-config\n";

try {
    require_once __DIR__ . '/../includes/payment-config.php';
    echo "[2] payment-config chargé ✅\n";
} catch (Throwable $e) {
    echo "[2] ❌ ERREUR: " . $e->getMessage() . "\n";
    echo "    Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "\n";
    exit;
}

echo "\n[3] Fonction getStripeAccount existe: " . (function_exists('getStripeAccount') ? 'OUI' : 'NON') . "\n";
echo "[4] Fonction stratedge_key existe: " . (function_exists('stratedge_key') ? 'OUI' : 'NON') . "\n";
echo "[5] Constante STRIPE_ACCOUNTS définie: " . (defined('STRIPE_ACCOUNTS') ? 'OUI' : 'NON') . "\n";

if (defined('STRIPE_ACCOUNTS')) {
    echo "\n[6] STRIPE_ACCOUNTS contenu:\n";
    foreach (STRIPE_ACCOUNTS as $k => $v) {
        echo "  - $k: secret=" . (empty($v['secret']) ? 'VIDE' : 'rempli') 
             . " / webhook=" . (empty($v['webhook']) ? 'VIDE' : 'rempli') . "\n";
    }
}

echo "\n[7] Test getStripeAccount('multi'):\n";
try {
    $a = getStripeAccount('multi');
    echo "  ✅ OK — secret=" . (empty($a['secret']) ? 'VIDE' : 'rempli(' . strlen($a['secret']) . 'c)') . "\n";
} catch (Throwable $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";
