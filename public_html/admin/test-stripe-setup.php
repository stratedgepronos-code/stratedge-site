<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment-config.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC STRIPE PACKS ===\n\n";

$acc = getStripeAccount('multi');
$sk  = $acc['secret'] ?? '';
$pk  = $acc['publishable'] ?? '';
$wh  = $acc['webhook'] ?? '';

// 1. Vérif clés
echo "1. CHARGEMENT CLÉS\n";
echo "   STRIPE_MULTI_SK    : " . ($sk ? '✅ chargée ('.substr($sk,0,12).'...)' : '❌ VIDE') . "\n";
echo "   STRIPE_MULTI_PK    : " . ($pk ? '✅ chargée ('.substr($pk,0,12).'...)' : '❌ VIDE') . "\n";
echo "   STRIPE_MULTI_WHSEC : " . ($wh ? '✅ chargée ('.substr($wh,0,12).'...)' : '❌ VIDE') . "\n";

if (!$sk) {
    echo "\n❌ Clé secrète absente → ajoute STRIPE_MULTI_SK dans les variables d'env Hostinger.\n";
    exit;
}

// 2. Vérif mode live/test
echo "\n2. MODE STRIPE\n";
$mode = str_starts_with($sk, 'sk_live_') ? 'LIVE (paiements réels)' : (str_starts_with($sk, 'sk_test_') ? 'TEST (sandbox)' : 'INCONNU');
echo "   Mode détecté : " . $mode . "\n";

// 3. Test API Stripe
echo "\n3. TEST API STRIPE\n";
$ch = curl_init('https://api.stripe.com/v1/account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $sk],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    $data = json_decode($resp, true);
    echo "   ✅ API OK (HTTP $code)\n";
    echo "   Compte ID      : " . ($data['id'] ?? '?') . "\n";
    echo "   Business name  : " . ($data['business_profile']['name'] ?? 'non défini') . "\n";
    echo "   Pays           : " . ($data['country'] ?? '?') . "\n";
    echo "   Devise par déf : " . ($data['default_currency'] ?? '?') . "\n";
    echo "   Paiements OK   : " . (($data['charges_enabled'] ?? false) ? '✅ OUI' : '❌ NON (compte pas activé)') . "\n";
    echo "   Payouts OK     : " . (($data['payouts_enabled'] ?? false) ? '✅ OUI' : '❌ NON (pas d\\'IBAN)') . "\n";
} else {
    echo "   ❌ Erreur API (HTTP $code)\n";
    echo "   Réponse : " . substr($resp, 0, 500) . "\n";
}

// 4. Liste des webhooks configurés
echo "\n4. WEBHOOKS CONFIGURÉS SUR STRIPE\n";
$ch = curl_init('https://api.stripe.com/v1/webhook_endpoints?limit=20');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $sk],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
$data = json_decode($resp, true);
$hooks = $data['data'] ?? [];
if (empty($hooks)) {
    echo "   ⚠ Aucun webhook configuré.\n";
} else {
    foreach ($hooks as $h) {
        $url = $h['url'] ?? '?';
        $events = implode(', ', $h['enabled_events'] ?? []);
        $status = $h['status'] ?? '?';
        $isPackHook = (strpos($url, 'stripe-webhook-pack.php') !== false);
        echo "   " . ($isPackHook ? '📦' : '  ') . " " . $url . "\n";
        echo "      Events : " . substr($events, 0, 80) . "\n";
        echo "      Status : " . $status . "\n";
    }
    $packHook = array_filter($hooks, fn($h) => strpos($h['url'] ?? '', 'stripe-webhook-pack.php') !== false);
    if (empty($packHook)) {
        echo "\n   ❌ Pas de webhook pointant vers /stripe-webhook-pack.php\n";
        echo "   → Crée-le sur https://dashboard.stripe.com/webhooks\n";
        echo "      URL: https://stratedgepronos.fr/stripe-webhook-pack.php\n";
        echo "      Event: checkout.session.completed\n";
    } else {
        echo "\n   ✅ Webhook packs trouvé côté Stripe\n";
    }
}

// 5. Test création d'une session checkout (sans la payer)
echo "\n5. TEST CRÉATION SESSION CHECKOUT (pack unique 4.50€)\n";
$postData = [
    'mode' => 'payment',
    'payment_method_types[]' => 'card',
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][product_data][name]' => 'TEST - StratEdge Pack Unique',
    'line_items[0][price_data][unit_amount]' => 450,
    'line_items[0][quantity]' => 1,
    'success_url' => 'https://stratedgepronos.fr/packs-daily.php?test=ok',
    'cancel_url' => 'https://stratedgepronos.fr/packs-daily.php?test=cancelled',
    'metadata[type]' => 'diagnostic_test',
];
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $sk],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($resp, true);

if ($code === 200 && !empty($data['url'])) {
    echo "   ✅ Session créée avec succès\n";
    echo "   URL de paiement test : " . $data['url'] . "\n";
    echo "   (Tu peux ouvrir ce lien pour voir la page de paiement — NE PAIE PAS en mode live)\n";
} else {
    echo "   ❌ Échec création\n";
    echo "   HTTP: $code\n";
    echo "   Réponse: " . substr($resp, 0, 500) . "\n";
}



// ════════════════════════════════════════════════════════════
// TEST COMPTE TENNIS
// ════════════════════════════════════════════════════════════
echo "\n\n══════════════════════════════════════════════\n";
echo "=== DIAGNOSTIC STRIPE TENNIS ===\n";
echo "══════════════════════════════════════════════\n\n";

$accT = getStripeAccount('tennis');
$skT  = $accT['secret'] ?? '';

echo "1. CLÉ TENNIS : " . ($skT ? '✅ chargée ('.substr($skT,0,12).'...)' : '❌ VIDE') . "\n";

if ($skT) {
    $mode = str_starts_with($skT, 'sk_live_') ? 'LIVE' : (str_starts_with($skT, 'sk_test_') ? 'TEST' : '?');
    echo "2. MODE : " . $mode . "\n";

    $ch = curl_init('https://api.stripe.com/v1/account');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $skT],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($resp, true);
        echo "3. API OK — Compte: " . ($data['id'] ?? '?') . " / Pays: " . ($data['country'] ?? '?') . "\n";
        echo "   Paiements: " . (($data['charges_enabled'] ?? false) ? '✅' : '❌') . " / Payouts: " . (($data['payouts_enabled'] ?? false) ? '✅' : '❌') . "\n";
    } else {
        echo "3. API ❌ HTTP $code\n";
    }

    // Webhooks Tennis
    echo "\n4. WEBHOOKS TENNIS:\n";
    $ch = curl_init('https://api.stripe.com/v1/webhook_endpoints?limit=20');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $skT],
        CURLOPT_TIMEOUT => 15,
    ]);
    $data = json_decode(curl_exec($ch), true);
    $hooks = $data['data'] ?? [];
    if (empty($hooks)) {
        echo "   ⚠ Aucun webhook configuré côté Tennis\n";
    } else {
        foreach ($hooks as $h) {
            echo "   - " . ($h['url'] ?? '?') . "\n";
        }
    }
}

echo "\n=== FIN DIAGNOSTIC ===\n";
