<?php
// ============================================================
// STRATEDGE — Stripe Checkout (créer une session de paiement)
// stripe-create.php
// Appelé en AJAX depuis la page d'offre
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
requireLogin();

header('Content-Type: application/json');

$membre = getMembre();
$type   = trim($_POST['offre'] ?? '');
$funOn  = !empty($_POST['option_fun']) && $_POST['option_fun'] === '1';

// Déterminer le type effectif
$effectiveType = $type;
if ($funOn && in_array($type, ['weekend', 'weekly'])) {
    $effectiveType = $type . '_fun';
}

if (!isset(PAYMENT_AMOUNTS[$effectiveType])) {
    http_response_code(400);
    echo json_encode(['error' => 'Offre invalide']);
    exit;
}

$amount   = PAYMENT_AMOUNTS[$effectiveType];
$label    = PAYMENT_LABELS[$effectiveType] ?? 'StratEdge Pronos';
$orderId  = 'SE_' . $membre['id'] . '_' . $effectiveType . '_' . time();

// ── Appel API Stripe Checkout ────────────────────────────────
$payload = [
    'payment_method_types[]' => 'card',
    'mode'                   => 'payment',
    'client_reference_id'    => $orderId,
    'customer_email'         => $membre['email'],
    'line_items[0][price_data][currency]'     => 'eur',
    'line_items[0][price_data][unit_amount]'  => (int)($amount * 100), // centimes
    'line_items[0][price_data][product_data][name]' => $label,
    'line_items[0][quantity]' => 1,
    'success_url' => SITE_BASE_URL . '/stripe-success.php?session_id={CHECKOUT_SESSION_ID}&type=' . urlencode($type),
    'cancel_url'  => SITE_BASE_URL . '/offre-' . urlencode($type) . '.php?cancelled=1',
    'metadata[membre_id]'    => $membre['id'],
    'metadata[type]'         => $effectiveType,
    'metadata[order_id]'     => $orderId,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode >= 400 || !isset($data['url'])) {
    $errMsg = $data['error']['message'] ?? 'Erreur Stripe inconnue';
    // Log
    @file_put_contents(__DIR__ . '/logs/stripe-log.txt',
        date('Y-m-d H:i:s') . " | CREATE ERROR | $orderId | HTTP $httpCode | $errMsg\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $errMsg]);
    exit;
}

// Log succès
@file_put_contents(__DIR__ . '/logs/stripe-log.txt',
    date('Y-m-d H:i:s') . " | CREATE OK | $orderId | session=" . $data['id'] . " | {$amount}€\n", FILE_APPEND);

echo json_encode([
    'url'        => $data['url'],
    'session_id' => $data['id'],
]);
