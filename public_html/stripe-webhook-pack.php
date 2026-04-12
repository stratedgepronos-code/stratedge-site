<?php
// STRATEDGE — Webhook Stripe pour packs de crédits
require_once __DIR__ . '/includes/payment-config.php';
require_once __DIR__ . '/includes/credits-manager.php';
require_once __DIR__ . '/includes/mailer.php';

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Valide signature sur les 3 comptes Stripe (Multi/Tennis/Fun)
$event = matchStripeWebhook($payload, $sigHeader);
if (!$event) { http_response_code(400); echo 'Invalid signature'; exit; }

if ($event['type'] !== 'checkout.session.completed') {
    http_response_code(200); echo 'ignored'; exit;
}

$session = $event['data']['object'];
$meta = $session['metadata'] ?? [];

// Seulement les sessions de type pack_credits
if (($meta['type'] ?? '') !== 'pack_credits') {
    http_response_code(200); echo 'not a pack'; exit;
}

$membreId = (int)($meta['membre_id'] ?? 0);
$packKey  = $meta['pack_key'] ?? '';
$txRef    = $session['payment_intent'] ?? $session['id'] ?? '';

$packId = stratedge_credits_ajouter($membreId, $packKey, 'stripe', $txRef);

if ($packId > 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, nom FROM membres WHERE id = ?");
        $stmt->execute([$membreId]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($m && function_exists('sendEmail')) {
            $pack = stratedge_pack_get($packKey);
            $solde = stratedge_credits_solde($membreId);
            sendEmail($m['email'], '✅ Pack activé — ' . $pack['label'],
                "Salut " . ($m['nom'] ?? '') . ",\n\nTon pack " . $pack['label'] . " (" . $pack['nb'] . " paris) est activé !\nTu as maintenant " . $solde . " crédits disponibles.\n\nDirection les bets : https://stratedgepronos.fr/bets.php\n\n— StratEdge"
            );
        }
    } catch (Throwable $e) { error_log('[stripe-webhook-pack] email: '.$e->getMessage()); }
    http_response_code(200); echo 'ok';
} else {
    error_log('[stripe-webhook-pack] Échec ajout crédit membre=' . $membreId . ' pack=' . $packKey);
    http_response_code(500); echo 'add failed';
}
