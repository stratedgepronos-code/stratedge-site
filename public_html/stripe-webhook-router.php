<?php
// ============================================================
// STRATEDGE — Dispatcher unique webhooks Stripe
// Dispatch selon metadata['type']:
//   - 'pack_credits'  → traitement packs (stripe-webhook-pack.php logic)
//   - autres (daily/weekend/weekly/tennis/fun/vip_max) → abos (stripe-webhook.php logic)
// Permet d'avoir UN SEUL webhook par compte Stripe.
// ============================================================
require_once __DIR__ . '/includes/payment-config.php';

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400); echo 'Missing'; exit;
}

// Validation signature (commune)
if (!matchStripeWebhook($sigHeader, $payload)) {
    http_response_code(400); echo 'Invalid signature'; exit;
}

$event = json_decode($payload, true);
if (!$event) { http_response_code(400); echo 'Bad JSON'; exit; }

// Que pour les checkout.session.completed
if (($event['type'] ?? '') !== 'checkout.session.completed') {
    http_response_code(200); echo 'ignored'; exit;
}

$session = $event['data']['object'] ?? [];
$meta = $session['metadata'] ?? [];
$metaType = $meta['type'] ?? '';

// Dispatch: pack_credits vs abo
if ($metaType === 'pack_credits') {
    // Reformer la requête vers stripe-webhook-pack.php en réutilisant son code
    require __DIR__ . '/stripe-webhook-pack.php';
} else {
    // Tous les autres types (daily, weekend, weekly, tennis, fun, vip_max) = abos
    require __DIR__ . '/stripe-webhook.php';
}
