<?php
// ============================================================
// DIAGNOSTIC — Supprime ce fichier après usage
// public_html/diag-offre.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "PHP Version: " . phpversion() . "\n\n";

// Check all required files
$files = [
    'includes/auth.php',
    'includes/promo.php',
    'includes/giveaway-functions.php',
    'includes/sidebar.php',
    'includes/sidebar-css.php',
    'includes/footer-main.php',
    'includes/payment-config.php',
    'includes/offre-template.php',
];

foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    echo $f . ': ' . (file_exists($path) ? '✅ OK (' . filesize($path) . ' bytes)' : '❌ MISSING') . "\n";
}

echo "\n=== Test require auth.php ===\n";
try {
    require_once __DIR__ . '/includes/auth.php';
    echo "✅ auth.php loaded\n";
    echo "Session: " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive') . "\n";
    echo "isLoggedIn: " . (isLoggedIn() ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo "❌ auth.php ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test require promo.php ===\n";
try {
    require_once __DIR__ . '/includes/promo.php';
    echo "✅ promo.php loaded\n";
} catch (Throwable $e) {
    echo "❌ promo.php ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test require giveaway-functions.php ===\n";
try {
    require_once __DIR__ . '/includes/giveaway-functions.php';
    echo "✅ giveaway-functions.php loaded\n";
} catch (Throwable $e) {
    echo "❌ giveaway-functions.php ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test offre type=fun ===\n";
try {
    $type = 'fun';
    $offres = [
        'fun' => [
            'titre' => 'Fun Only', 'subtitle' => 'Grosses Cotes', 'emoji' => '🎲',
            'prix' => '10', 'idd' => '', 'idp' => '',
            'duree' => 'Du vendredi au dimanche',
            'avantages' => ['Test'],
            'color' => '#a855f7', 'glow' => 'rgba(168,85,247,0.18)',
            'gradient' => 'linear-gradient(135deg,#a855f7,#ff6b2b)',
            'video' => 'assets/images/mascotte-fun.mp4',
            'activate' => 'activate.php?type=fun',
            'badge' => 'FUN', 'tag' => 'Grosses cotes',
        ],
    ];
    $o = $offres[$type];
    echo "✅ Fun config OK: prix=" . $o['prix'] . " idd=" . var_export($o['idd'], true) . "\n";
} catch (Throwable $e) {
    echo "❌ Fun config ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Check functions exist ===\n";
$funcs = ['getMembre','getDB','requireLogin','isLoggedIn','isAdmin','clean','getAvatarUrl',
          'isAnniversaireEligible','getAnniversairePercent','calculerPrixAvecPromo'];
foreach ($funcs as $fn) {
    echo $fn . ': ' . (function_exists($fn) ? '✅' : '❌ MISSING') . "\n";
}

echo "\n=== Check last PHP error ===\n";
$err = error_get_last();
if ($err) {
    echo "Type: " . $err['type'] . "\n";
    echo "Message: " . $err['message'] . "\n";
    echo "File: " . $err['file'] . "\n";
    echo "Line: " . $err['line'] . "\n";
} else {
    echo "No error\n";
}

echo "\n=== Error log (last 20 lines) ===\n";
$logPaths = [
    __DIR__ . '/logs/php-error.log',
    '/home/u527192911/logs/error.log',
    ini_get('error_log'),
];
foreach ($logPaths as $lp) {
    if ($lp && file_exists($lp)) {
        echo "Found: $lp\n";
        $lines = file($lp);
        $last20 = array_slice($lines, -20);
        echo implode('', $last20);
        break;
    }
}

echo "</pre>";
