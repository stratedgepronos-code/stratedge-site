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
// On teste plusieurs chemins car dirname peut varier selon le contexte d'appel
$stratedge_config_paths = [
    dirname(__DIR__) . '/config-keys.php',         // /public_html/config-keys.php
    $_SERVER['DOCUMENT_ROOT'] . '/config-keys.php', // via DOCUMENT_ROOT
    __DIR__ . '/../config-keys.php',                // chemin relatif
];
foreach ($stratedge_config_paths as $p) {
    if (is_file($p)) {
        require_once $p;
        break;
    }
}

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
// Routing UNIQUEMENT pour les packs crédits (multi/tennis/fun)
// Les anciens abos (daily/weekend/weekly/vip_max) ont été supprimés
// au profit du système packs crédits universel.
define('STRIPE_ROUTING', [
    // Mappings backward-compat — au cas où d'anciens webhooks arrivent encore
    'daily'        => 'multi',
    'weekend'      => 'multi',
    'weekly'       => 'multi',
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
    // === Packs crédits Multi ===
    'unique'       => 4.50,
    'duo'          => 8.00,
    'trio'         => 12.00,
    'quinte'       => 18.00,
    'semaine'      => 20.00,
    'pack10'       => 30.00,
    // === Abos ===
    'tennis'       => 15.00,
    'fun'          => 10.00,
    'vip_max'      => 50.00,
    // === Legacy (backward compat) ===
    'daily'        => 4.50,
    'weekend'      => 10.00,
    'weekend_fun'  => 20.00,
    'weekly'       => 20.00,
    'weekly_fun'   => 30.00,
]);

define('PAYMENT_LABELS', [
    'unique'       => 'StratEdge — Pack Unique (1 analyse)',
    'duo'          => 'StratEdge — Pack Duo (2 analyses)',
    'trio'         => 'StratEdge — Pack Trio (3 analyses)',
    'quinte'       => 'StratEdge — Quinté (5 analyses)',
    'semaine'      => 'StratEdge — Pack Semaine (7 analyses)',
    'pack10'       => 'StratEdge — Pack 10 (10 analyses)',
    'tennis'       => 'StratEdge — Tennis Semaine',
    'fun'          => 'StratEdge — Fun Semaine',
    'vip_max'      => 'StratEdge — VIP Max (30 jours)',
    'daily'        => 'StratEdge — Daily',
    'weekend'      => 'StratEdge — Pack Week-End',
    'weekend_fun'  => 'StratEdge — Pack Week-End + Fun',
    'weekly'       => 'StratEdge — Pack Weekly',
    'weekly_fun'   => 'StratEdge — Pack Weekly + Fun',
]);
