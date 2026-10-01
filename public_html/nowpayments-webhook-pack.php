<?php
// STRATEDGE — Webhook NowPayments (IPN) pour packs de crédits
require_once __DIR__ . '/includes/payment-config.php';
require_once __DIR__ . '/includes/nowpayments-config.php';
require_once __DIR__ . '/includes/credits-manager.php';

$payload = @file_get_contents('php://input');
$receivedSig = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';

$ipnSecret = defined('NP_IPN_SECRET') ? NP_IPN_SECRET : '';
if ($ipnSecret && $receivedSig) {
    $data = json_decode($payload, true);
    if (is_array($data)) {
        ksort($data);
        $expected = hash_hmac('sha512', json_encode($data, JSON_UNESCAPED_SLASHES), $ipnSecret);
        if (!hash_equals($expected, $receivedSig)) {
            http_response_code(401); echo 'Invalid sig'; exit;
        }
    }
}

$data = json_decode($payload, true);
if (!is_array($data)) { http_response_code(400); exit; }

$status  = $data['payment_status'] ?? '';
$orderId = $data['order_id'] ?? '';

if (!in_array($status, ['finished', 'confirmed', 'partially_paid'], true)) {
    http_response_code(200); echo 'pending'; exit;
}

// Retrouver la commande via nowpayments_orders
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM nowpayments_orders WHERE order_id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { http_response_code(404); echo 'order not found'; exit; }

    // Eviter double crédit
    if (!empty($order['credited_at'])) { http_response_code(200); echo 'already credited'; exit; }

    $packId = stratedge_credits_ajouter(
        (int)$order['membre_id'],
        $order['pack_key'],
        'crypto',
        $data['payment_id'] ?? $orderId
    );

    if ($packId > 0) {
        $db->prepare("UPDATE nowpayments_orders SET credited_at = NOW() WHERE order_id = ?")->execute([$orderId]);
        http_response_code(200); echo 'ok';
    } else {
        http_response_code(500); echo 'credit failed';
    }
} catch (Throwable $e) {
    error_log('[nowpay-webhook-pack] '.$e->getMessage());
    http_response_code(500); echo 'err';
}
