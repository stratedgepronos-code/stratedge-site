<?php
// STRATEDGE — Création session Stripe Checkout pour pack de crédits
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
require_once __DIR__ . '/includes/packs-config.php';
require_once __DIR__ . '/includes/promo.php';
requireLogin();

$membre = getMembre();
$packKey = trim($_GET['pack'] ?? '');
$pack = stratedge_pack_get($packKey);

if (!$pack || !in_array('stripe', $pack['methodes'], true)) {
    header('Location: /packs-daily.php?err=invalid_pack'); exit;
}

$sportAccount = stratedge_pack_stripe_account($packKey);
$account = getStripeAccount($sportAccount);
$stripeKey = $account['secret'] ?? '';
if (!$stripeKey) { header('Location: /packs-daily.php?err=stripe_not_ready'); exit; }

$amount = $pack['prix'];
$productName = 'StratEdge Pack ' . $pack['label'] . ' (' . $pack['nb'] . ' pari' . ($pack['nb']>1?'s':'') . ')';

// Code promo
$promoCode = strtoupper(trim($_GET['promo'] ?? ''));
$promoResult = null;
if ($promoCode !== '') {
    $promoResult = calculerPrixAvecPromo($amount, $packKey, (int)$membre['id'], $promoCode);
    if ($promoResult['label'] !== null) {
        $amount = $promoResult['montant'];
        $productName .= ' — ' . $promoResult['label'];
    }
}

$amountCents = (int) round($amount * 100);
$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://stratedgepronos.fr';

$postData = [
    'mode' => 'payment',
    'payment_method_types[]' => 'card',
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][product_data][name]' => $productName,
    'line_items[0][price_data][product_data][description]' => 'Crédits à vie · ' . number_format($pack['prix_unit'],2,',','') . '€/pari',
    'line_items[0][price_data][unit_amount]' => $amountCents,
    'line_items[0][quantity]' => 1,
    'success_url' => $siteUrl . '/stripe-success.php?session_id={CHECKOUT_SESSION_ID}&pack=' . $packKey,
    'cancel_url' => $siteUrl . '/packs-daily.php?err=cancelled',
    'customer_email' => $membre['email'] ?? '',
    'metadata[membre_id]' => $membre['id'],
    'metadata[pack_key]' => $packKey,
    'metadata[pack_nb]' => $pack['nb'],
    'metadata[type]' => 'pack_credits',
    'metadata[promo_code]' => $promoCode,
    'metadata[promo_id]' => $promoResult['code_promo_id'] ?? '',
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $stripeKey],
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

if (!empty($data['url'])) {
    header('Location: ' . $data['url']); exit;
}
error_log('[stripe-create-pack] Erreur: ' . $response);
header('Location: /packs-daily.php?err=stripe_fail'); exit;
