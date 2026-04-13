<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment-config.php';
requireAdmin();
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

function stripePrefix($s) {
    if (strpos($s, 'sk_live_') === 0) return 'LIVE';
    if (strpos($s, 'sk_test_') === 0) return 'TEST';
    return '?';
}

function testStripeAccount($label, $key) {
    echo "\n══════════════════════════════════════════════\n";
    echo "=== $label ===\n";
    echo "══════════════════════════════════════════════\n\n";
    $acc = getStripeAccount($key);
    $sk = $acc['secret'] ?? '';
    $wh = $acc['webhook'] ?? '';

    if (!$sk) { echo "❌ Clé absente\n"; return; }

    echo "1. CLÉ : ✅ " . substr($sk, 0, 14) . "... (" . strlen($sk) . "c)\n";
    echo "2. MODE : " . stripePrefix($sk) . "\n";
    echo "3. WEBHOOK : " . ($wh ? '✅ ' . substr($wh, 0, 12) . '... (' . strlen($wh) . 'c)' : '❌') . "\n";

    // API test
    $ch = curl_init('https://api.stripe.com/v1/account');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $sk],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "4. API Stripe : ";
    if ($code === 200) {
        $data = json_decode($resp, true);
        echo "✅ HTTP 200\n";
        echo "   ID compte   : " . ($data['id'] ?? '?') . "\n";
        echo "   Pays        : " . ($data['country'] ?? '?') . "\n";
        echo "   Paiements   : " . (!empty($data['charges_enabled']) ? '✅' : '❌ (compte pas complètement activé)') . "\n";
        echo "   Payouts     : " . (!empty($data['payouts_enabled']) ? '✅' : '❌ (pas d\'IBAN validé)') . "\n";
    } else {
        echo "❌ HTTP $code\n   Réponse: " . substr($resp, 0, 300) . "\n";
        return;
    }

    // Webhooks configurés
    echo "\n5. WEBHOOKS sur ce compte :\n";
    $ch = curl_init('https://api.stripe.com/v1/webhook_endpoints?limit=20');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $sk],
        CURLOPT_TIMEOUT => 15,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode($r, true);
    $hooks = $d['data'] ?? [];
    if (empty($hooks)) {
        echo "   ⚠ Aucun webhook configuré\n";
    } else {
        foreach ($hooks as $h) {
            echo "   - " . ($h['url'] ?? '?') . "\n";
            echo "     events: " . implode(', ', array_slice($h['enabled_events'] ?? [], 0, 3)) . "\n";
            echo "     status: " . ($h['status'] ?? '?') . "\n";
        }
    }
}

echo "=== DIAGNOSTIC STRIPE COMPLET ===\n";

testStripeAccount('COMPTE MULTI (packs crédits)', 'multi');
testStripeAccount('COMPTE TENNIS (abo 15€/sem)', 'tennis');

echo "\n\n=== FIN ===\n";
