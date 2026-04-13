<?php
// Stripe Checkout direct (GET) pour abo Tennis 15€/semaine
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
requireLogin();

$membre = getMembre();
$type = 'tennis';
$amount = PAYMENT_AMOUNTS[$type];
$label = PAYMENT_LABELS[$type] ?? 'StratEdge Tennis';
$orderId = 'SE_' . $membre['id'] . '_' . $type . '_' . time();

$account = getStripeAccount($type);
$stripeKey = $account['secret'] ?? '';
$accountName = STRIPE_ROUTING[$type] ?? 'tennis';

if (empty($stripeKey)) {
    die('Paiement Tennis non configuré. Contacte le support.');
}

$payload = [
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'client_reference_id' => $orderId,
    'customer_email' => $membre['email'],
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][unit_amount]' => (int)($amount * 100),
    'line_items[0][price_data][product_data][name]' => $label,
    'line_items[0][quantity]' => 1,
    'success_url' => SITE_BASE_URL . '/stripe-success.php?session_id={CHECKOUT_SESSION_ID}&type=' . $type,
    'cancel_url' => SITE_BASE_URL . '/offre-tennis.php?cancelled=1',
    'metadata[membre_id]' => $membre['id'],
    'metadata[type]' => $type,
    'metadata[order_id]' => $orderId,
    'metadata[stripe_account]' => $accountName,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $stripeKey],
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode === 200 && !empty($data['url'])) {
    header('Location: ' . $data['url']);
    exit;
}

@file_put_contents(__DIR__ . '/logs/stripe-log.txt',
    date('Y-m-d H:i:s') . " | TENNIS ERROR | HTTP=$httpCode | " . substr($response, 0, 300) . "\n", FILE_APPEND);
die('Erreur lors de la création du paiement Tennis. Réessaie ou contacte le support.');
