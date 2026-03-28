<?php
// ============================================================
// STRATEDGE — Configuration paiements CB + Paysafecard
// includes/payment-config.php
// ============================================================

// ── STRIPE ──────────────────────────────────────────────────
// Dashboard: https://dashboard.stripe.com
// 1. Crée un compte sur stripe.com
// 2. Active ton compte (vérification identité)
// 3. Récupère tes clés dans Developers → API keys
// 4. Configure le webhook dans Developers → Webhooks
//    URL: https://stratedgepronos.fr/stripe-webhook.php
//    Events: checkout.session.completed

define('STRIPE_SECRET_KEY',      getenv('STRIPE_SECRET_KEY')      ?: '');
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: '');
define('STRIPE_WEBHOOK_SECRET',  getenv('STRIPE_WEBHOOK_SECRET')  ?: '');

// ── PAYSAFECARD ─────────────────────────────────────────────
// Dashboard: https://merchant.paysafecard.com
// 1. Crée un compte marchand sur paysafecard.com/business
// 2. Demande l'accès API (validation ~48h)
// 3. Récupère ton API Key dans le dashboard marchand
// Test: https://apitest.paysafecard.com/v1/payments
// Prod: https://api.paysafecard.com/v1/payments

define('PAYSAFE_API_KEY',  getenv('PAYSAFE_API_KEY')  ?: '');
define('PAYSAFE_ENV',      getenv('PAYSAFE_ENV')      ?: 'TEST'); // TEST ou PROD

function paysafeBaseUrl(): string {
    return PAYSAFE_ENV === 'PROD'
        ? 'https://api.paysafecard.com/v1'
        : 'https://apitest.paysafecard.com/v1';
}

// ── COMMUN ──────────────────────────────────────────────────
define('SITE_BASE_URL', 'https://stratedgepronos.fr');

// Montants par offre
define('PAYMENT_AMOUNTS', [
    'weekend'      => 10.00,
    'weekend_fun'  => 20.00,
    'weekly'       => 20.00,
    'weekly_fun'   => 30.00,
    'tennis'       => 15.00,
    'vip_max'      => 50.00,
    'fun'          => 10.00,
]);

// Libellés pour Stripe/Paysafe
define('PAYMENT_LABELS', [
    'weekend'      => 'StratEdge — Pack Week-End',
    'weekend_fun'  => 'StratEdge — Pack Week-End + Fun',
    'weekly'       => 'StratEdge — Pack Weekly',
    'weekly_fun'   => 'StratEdge — Pack Weekly + Fun',
    'tennis'       => 'StratEdge — Tennis Weekly',
    'vip_max'      => 'StratEdge — VIP Max (30 jours)',
    'fun'          => 'StratEdge — Fun Only (Week-End)',
]);
