<?php
// STRATEDGE — Création invoice NowPayments pour pack crédits
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
require_once __DIR__ . '/includes/nowpayments-config.php';
require_once __DIR__ . '/includes/packs-config.php';
requireLogin();

$membre = getMembre();
$packKey = trim($_GET['pack'] ?? '');
$pack = stratedge_pack_get($packKey);

if (!$pack || !in_array('crypto', $pack['methodes'], true)) {
    header('Location: /packs-daily.php?err=invalid_pack'); exit;
}

$apiKey = defined('NP_API_KEY') ? NP_API_KEY : '';
if (!$apiKey) { header('Location: /packs-daily.php?err=crypto_not_ready'); exit; }

$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://stratedgepronos.fr';
$orderId = 'PACK_' . $packKey . '_' . $membre['id'] . '_' . time();

$payload = json_encode([
    'price_amount'      => $pack['prix'],
    'price_currency'    => 'eur',
    'order_id'          => $orderId,
    'order_description' => 'StratEdge Pack ' . $pack['label'] . ' (' . $pack['nb'] . ' paris) - Crédits à vie',
    'ipn_callback_url'  => $siteUrl . '/nowpayments-webhook-pack.php',
    'success_url'       => defined('NP_SUCCESS_URL') ? NP_SUCCESS_URL : $siteUrl . '/packs-daily.php?crypto_ok=1',
    'cancel_url'        => $siteUrl . '/packs-daily.php?err=cancelled',
]);

$ch = curl_init('' . NP_API_BASE . '/invoice'');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $apiKey],
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

// Mémoriser l'order pour validation webhook
if (!empty($data['id']) && !empty($data['invoice_url'])) {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO nowpayments_orders (order_id, membre_id, pack_key, prix, invoice_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
           ->execute([$orderId, $membre['id'], $packKey, $pack['prix'], $data['id']]);
    } catch (Throwable $e) { error_log('[nowpay-create-pack] log: '.$e->getMessage()); }
    header('Location: ' . $data['invoice_url']); exit;
}

error_log('[nowpay-create-pack] Erreur: ' . $response);
header('Location: /packs-daily.php?err=crypto_fail'); exit;
