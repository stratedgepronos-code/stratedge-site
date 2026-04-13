<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment-config.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

echo "=== AUDIT COMPLET PAIEMENTS STRATEDGE ===\n\n";

// 1. ROUTING DES OFFRES
echo "[1] ROUTING DES OFFRES vers les comptes Stripe\n";
echo str_repeat('─', 60) . "\n";
foreach (STRIPE_ROUTING as $offerType => $accountKey) {
    $acc = STRIPE_ACCOUNTS[$accountKey] ?? null;
    $hasKey = !empty($acc['secret']);
    $hasWh  = !empty($acc['webhook']);
    $price  = PAYMENT_AMOUNTS[$offerType] ?? '?';
    $status = ($hasKey && $hasWh) ? '✅' : '❌';
    printf("  %-15s → %-8s %s (clé %s, webhook %s, prix %s€)\n",
        $offerType, $accountKey, $status,
        $hasKey ? '✓' : '✗', $hasWh ? '✓' : '✗', $price);
}

// 2. ENDPOINTS
echo "\n[2] ENDPOINTS DE PAIEMENT\n";
echo str_repeat('─', 60) . "\n";
$endpoints = [
    'stripe-create.php'         => 'Abos (daily/weekend/weekly/tennis/fun/vip_max)',
    'stripe-create-pack.php'    => 'Packs crédits Multisports',
    'stripe-webhook.php'        => 'Webhook abos',
    'stripe-webhook-pack.php'   => 'Webhook packs crédits',
    'nowpayments-create.php'    => 'Crypto abos',
    'nowpayments-create-pack.php'=> 'Crypto packs',
    'nowpayments-ipn.php'       => 'Webhook crypto abos',
    'nowpayments-webhook-pack.php'=> 'Webhook crypto packs',
    'starpass-pack-unique.php'  => 'SMS pack unique',
];
foreach ($endpoints as $f => $desc) {
    $exists = is_file(__DIR__ . '/../' . $f);
    echo "  " . ($exists ? '✅' : '❌') . " /$f — $desc\n";
}

// 3. NOWPAYMENTS
echo "\n[3] NOWPAYMENTS\n";
echo str_repeat('─', 60) . "\n";
@require_once __DIR__ . '/../includes/nowpayments-config.php';
echo "  NP_API_KEY    : " . (defined('NP_API_KEY') && NP_API_KEY ? '✅ ('.substr(NP_API_KEY,0,8).'...)' : '❌') . "\n";
echo "  NP_IPN_SECRET : " . (defined('NP_IPN_SECRET') && NP_IPN_SECRET ? '✅' : '❌') . "\n";

// 4. TEST API NOWPAYMENTS
if (defined('NP_API_KEY') && NP_API_KEY) {
    $ch = curl_init('https://api.nowpayments.io/v1/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-api-key: ' . NP_API_KEY],
        CURLOPT_TIMEOUT => 10,
    ]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "  API Status   : " . ($code === 200 ? '✅ ' . $r : "❌ HTTP $code") . "\n";
}

// 5. TABLES BDD CRITIQUES
echo "\n[4] TABLES BDD\n";
echo str_repeat('─', 60) . "\n";
try {
    $db = getDB();
    foreach (['abonnements', 'membres', 'credits_paris', 'credits_consommation', 'stripe_payments', 'nowpayments_orders'] as $t) {
        $r = $db->query("SHOW TABLES LIKE '$t'")->fetch();
        $count = $r ? $db->query("SELECT COUNT(*) FROM $t")->fetchColumn() : '?';
        echo "  " . ($r ? '✅' : '❌') . " $t" . ($r ? " ($count rows)" : " MANQUANTE") . "\n";
    }
} catch (Throwable $e) {
    echo "  ❌ DB error: " . $e->getMessage() . "\n";
}

// 6. WEBHOOKS REGISTERED CHECK (pour les 3 comptes Stripe)
echo "\n[5] WEBHOOKS DÉCLARÉS CÔTÉ STRIPE\n";
echo str_repeat('─', 60) . "\n";
foreach (['multi','tennis','fun'] as $accKey) {
    $acc = STRIPE_ACCOUNTS[$accKey] ?? null;
    if (empty($acc['secret'])) { echo "  $accKey : ⚠ pas de clé\n"; continue; }
    
    $ch = curl_init('https://api.stripe.com/v1/webhook_endpoints?limit=20');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $acc['secret']],
        CURLOPT_TIMEOUT => 10,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $hooks = $r['data'] ?? [];
    echo "\n  Compte $accKey:\n";
    if (empty($hooks)) { echo "    ⚠ Aucun webhook\n"; continue; }
    foreach ($hooks as $h) {
        $url = $h['url'] ?? '?';
        $st  = $h['status'] ?? '?';
        $isPack = strpos($url, 'webhook-pack') !== false;
        $kind = $isPack ? '[PACK]' : '[ABO ]';
        echo "    $kind $st → $url\n";
    }
}

echo "\n=== FIN AUDIT ===\n";
