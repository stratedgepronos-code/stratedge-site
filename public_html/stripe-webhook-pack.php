<?php
// STRATEDGE — Webhook Stripe pour packs de crédits
require_once __DIR__ . '/includes/payment-config.php';
require_once __DIR__ . '/includes/credits-manager.php';
require_once __DIR__ . '/includes/mailer.php';

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400); echo 'Missing payload or signature'; exit;
}

// Valider signature sur les 3 comptes Stripe (Multi/Tennis/Fun)
// Attention: ordre des args = (signature, payload)
$matchResult = matchStripeWebhook($sigHeader, $payload);
if (!$matchResult) {
    http_response_code(400); echo 'Invalid signature'; exit;
}

// Parser le payload JSON (c'est l'event Stripe)
$event = json_decode($payload, true);
if (!$event || !isset($event['type'])) {
    http_response_code(400); echo 'Invalid JSON'; exit;
}

if ($event['type'] !== 'checkout.session.completed') {
    http_response_code(200); echo 'ignored (not checkout.session.completed)'; exit;
}

$session = $event['data']['object'] ?? [];
$meta = $session['metadata'] ?? [];

// Seulement les sessions de type pack_credits
if (($meta['type'] ?? '') !== 'pack_credits') {
    http_response_code(200); echo 'not a pack session'; exit;
}

$membreId = (int)($meta['membre_id'] ?? 0);
$packKey  = $meta['pack_key'] ?? '';
$txRef    = $session['payment_intent'] ?? $session['id'] ?? '';

if ($membreId <= 0 || $packKey === '') {
    error_log('[stripe-webhook-pack] Invalid metadata: ' . json_encode($meta));
    http_response_code(400); echo 'Invalid metadata'; exit;
}

$packId = stratedge_credits_ajouter($membreId, $packKey, 'stripe', $txRef);

if ($packId > 0) {
    // Enregistrer l'utilisation du code promo (si présent)
    $promoId = (int)($meta['promo_id'] ?? 0);
    $promoCodeUsed = $meta['promo_code'] ?? '';
    if ($promoId > 0 && $promoCodeUsed !== '') {
        try {
            require_once __DIR__ . '/includes/promo.php';
            $pack = stratedge_pack_get($packKey);
            $prixInitial = $pack ? $pack['prix'] : 0;
            $prixPaye = ($session['amount_total'] ?? 0) / 100;
            useCodePromo($promoId, $membreId, $packKey, (float)$prixInitial, $prixPaye);
            error_log("[stripe-webhook-pack] Promo '$promoCodeUsed' enregistrée pour membre #$membreId");
        } catch (Throwable $e) {
            error_log('[stripe-webhook-pack] promo error: ' . $e->getMessage());
        }
    }

    // Email de confirmation
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, nom FROM membres WHERE id = ?");
        $stmt->execute([$membreId]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($m && function_exists('sendEmail')) {
            $pack = stratedge_pack_get($packKey);
            $solde = stratedge_credits_solde($membreId);
            sendEmail(
                $m['email'],
                '✅ Pack activé — ' . $pack['label'],
                "Salut " . ($m['nom'] ?? '') . ",\n\n"
                . "Ton pack " . $pack['label'] . " (" . $pack['nb'] . " analyses) est activé !\n"
                . "Tu as maintenant " . $solde . " crédits disponibles.\n\n"
                . "Direction les bets : https://stratedgepronos.fr/bets.php\n\n"
                . "— StratEdge"
            );
        }
    } catch (Throwable $e) {
        error_log('[stripe-webhook-pack] email error: ' . $e->getMessage());
    }
    http_response_code(200); echo 'ok';
} else {
    error_log("[stripe-webhook-pack] Échec ajout crédit membre=$membreId pack=$packKey");
    http_response_code(500); echo 'credit add failed';
}
