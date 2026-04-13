<?php
// ============================================================
// STRATEDGE — Configuration paiements CB + Paysafecard
// includes/payment-config.php
// ============================================================

// ── STRIPE (3 comptes — 1 par tipster) ──────────────────────
//
// Compte 1: MULTISPORTS (daily, weekend, weekly, vip_max)
//   Webhook URL: https://stratedgepronos.fr/stripe-webhook.php
//   Event: checkout.session.completed
//
// Compte 2: TENNIS
//   Webhook URL: https://stratedgepronos.fr/stripe-webhook.php
//   Event: checkout.session.completed
//
// Compte 3: FUN ONLY
//   Webhook URL: https://stratedgepronos.fr/stripe-webhook.php
//   Event: checkout.session.completed

// Priorité 1: config-keys.php (option B, clés en dur hors Git)
$configKeys = dirname(__DIR__) . '/config-keys.php';
if (is_file($configKeys)) require_once $configKeys;

// Helper: env var OU constante définie dans config-keys.php
if (!function_exists('stratedge_key')) {
    function stratedge_key(string $name): string {
        $v = getenv($name);
        if ($v) return $v;
        if (defined($name)) return constant($name);
        return '';
    }
}

define('STRIPE_ACCOUNTS', [
    'multi' => [
        'secret'      => stratedge_key('STRIPE_MULTI_SK'),
        'publishable' => stratedge_key('STRIPE_MULTI_PK'),
        'webhook'     => stratedge_key('STRIPE_MULTI_WHSEC'),
    ],
    'tennis' => [
        'secret'      => stratedge_key('STRIPE_TENNIS_SK'),
        'publishable' => stratedge_key('STRIPE_TENNIS_PK'),
        'webhook'     => stratedge_key('STRIPE_TENNIS_WHSEC'),
    ],
    'fun' => [
        'secret'      => stratedge_key('STRIPE_FUN_SK'),
        'publishable' => stratedge_key('STRIPE_FUN_PK'),
        'webhook'     => stratedge_key('STRIPE_FUN_WHSEC'),
    ],
]);

// Routing : quel type d'offre va vers quel compte Stripe
define('STRIPE_ROUTING', [
    'daily'        => 'multi',
    'weekend'      => 'multi',
    'weekend_fun'  => 'multi',
    'weekly'       => 'multi',
    'weekly_fun'   => 'multi',
    'vip_max'      => 'multi',
    'tennis'       => 'tennis',
    'fun'          => 'fun',
]);

/**
 * Retourne les cles Stripe du bon compte selon le type d'offre.
 */
function getStripeAccount(string $offerType): array {
    $accountKey = STRIPE_ROUTING[$offerType] ?? 'multi';
    return STRIPE_ACCOUNTS[$accountKey];
}

/**
 * Verifie la signature webhook contre les 3 comptes.
 * Retourne le compte qui match ou null.
 */
function matchStripeWebhook(string $signature, string $payload): ?array {
    $elements = [];
    foreach (explode(',', $signature) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) $elements[trim($kv[0])] = trim($kv[1]);
    }
    $timestamp = $elements['t'] ?? '';
    $sig = $elements['v1'] ?? '';
    if (!$timestamp || !$sig) return null;
    if (abs(time() - (int)$timestamp) > 300) return null;

    $signedPayload = $timestamp . '.' . $payload;

    foreach (STRIPE_ACCOUNTS as $accountKey => $keys) {
        if (empty($keys['webhook'])) continue;
        $expected = hash_hmac('sha256', $signedPayload, $keys['webhook']);
        if (hash_equals($expected, $sig)) {
            return ['account' => $accountKey, 'keys' => $keys];
        }
    }
    return null;
}

// ── PAYSAFECARD ─────────────────────────────────────────────
define('PAYSAFE_API_KEY',  getenv('PAYSAFE_API_KEY')  ?: '');
define('PAYSAFE_ENV',      getenv('PAYSAFE_ENV')      ?: 'TEST');

function paysafeBaseUrl(): string {
    return PAYSAFE_ENV === 'PROD'
        ? 'https://api.paysafecard.com/v1'
        : 'https://apitest.paysafecard.com/v1';
}

// ── COMMUN ──────────────────────────────────────────────────
define('SITE_BASE_URL', 'https://stratedgepronos.fr');

define('PAYMENT_AMOUNTS', [
    'daily'        => 4.50,
    'weekend'      => 10.00,
    'weekend_fun'  => 20.00,
    'weekly'       => 20.00,
    'weekly_fun'   => 30.00,
    'tennis'       => 15.00,
    'vip_max'      => 50.00,
    'fun'          => 10.00,
]);

define('PAYMENT_LABELS', [
    'daily'        => 'StratEdge — Daily',
    'weekend'      => 'StratEdge — Pack Week-End',
    'weekend_fun'  => 'StratEdge — Pack Week-End + Fun',
    'weekly'       => 'StratEdge — Pack Weekly',
    'weekly_fun'   => 'StratEdge — Pack Weekly + Fun',
    'tennis'       => 'StratEdge — Tennis Weekly',
    'vip_max'      => 'StratEdge — VIP Max (30 jours)',
    'fun'          => 'StratEdge — Fun Only (Week-End)',
]);
