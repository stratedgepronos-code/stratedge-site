<?php
// ============================================================
// STRATEDGE — Paysafecard (créer un paiement)
// paysafe-create.php
// Appelé en AJAX depuis la page d'offre
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
requireLogin();

header('Content-Type: application/json');

$membre = getMembre();
$type   = trim($_POST['offre'] ?? '');
$funOn  = !empty($_POST['option_fun']) && $_POST['option_fun'] === '1';

$effectiveType = $type;
if ($funOn && in_array($type, ['weekend', 'weekly'])) {
    $effectiveType = $type . '_fun';
}

if (!isset(PAYMENT_AMOUNTS[$effectiveType])) {
    http_response_code(400);
    echo json_encode(['error' => 'Offre invalide']);
    exit;
}

$amount  = PAYMENT_AMOUNTS[$effectiveType];
$orderId = 'PSC_' . $membre['id'] . '_' . $effectiveType . '_' . time();

// ── Appel API Paysafecard ────────────────────────────────────
$apiUrl = paysafeBaseUrl() . '/payments';

$payload = [
    'type'     => 'PAYSAFECARD',
    'amount'   => number_format($amount, 2, '.', ''),
    'currency' => 'EUR',
    'redirect' => [
        'success_url' => SITE_BASE_URL . '/paysafe-return.php?order_id=' . urlencode($orderId) . '&type=' . urlencode($type),
        'failure_url' => SITE_BASE_URL . '/offre-' . urlencode($type) . '.php?psc_error=1',
    ],
    'notification_url' => SITE_BASE_URL . '/paysafe-webhook.php',
    'customer'  => [
        'id' => 'SE_MEMBRE_' . $membre['id'],
    ],
    'submerchant_id' => $orderId,
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode('psc_' . PAYSAFE_API_KEY . ':'),
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode >= 400 || !isset($data['redirect']['auth_url'])) {
    $errMsg = $data['message'] ?? $data['error'] ?? 'Erreur Paysafecard';
    @file_put_contents(__DIR__ . '/logs/paysafe-log.txt',
        date('Y-m-d H:i:s') . " | CREATE ERROR | $orderId | HTTP $httpCode | $errMsg | " . substr($response, 0, 500) . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$paymentId = $data['id'] ?? '';
$authUrl   = $data['redirect']['auth_url'];

// Sauvegarder en BDD pour le retour
$db = getDB();
try {
    $db->exec("CREATE TABLE IF NOT EXISTS paysafe_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        membre_id INT UNSIGNED NOT NULL,
        offre VARCHAR(30) NOT NULL,
        paysafe_id VARCHAR(120) NOT NULL,
        order_id VARCHAR(80) NOT NULL,
        montant_eur DECIMAL(8,2) NOT NULL,
        statut VARCHAR(30) DEFAULT 'created',
        date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_validation DATETIME DEFAULT NULL,
        KEY idx_paysafe (paysafe_id),
        KEY idx_order (order_id),
        KEY idx_membre (membre_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $db->prepare("INSERT INTO paysafe_payments
        (membre_id, offre, paysafe_id, order_id, montant_eur, statut)
        VALUES (?, ?, ?, ?, ?, 'created')");
    $stmt->execute([$membre['id'], $effectiveType, $paymentId, $orderId, $amount]);
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/logs/paysafe-log.txt',
        date('Y-m-d H:i:s') . " | DB ERROR | " . $e->getMessage() . "\n", FILE_APPEND);
}

@file_put_contents(__DIR__ . '/logs/paysafe-log.txt',
    date('Y-m-d H:i:s') . " | CREATE OK | $orderId | psc_id=$paymentId | {$amount}€\n", FILE_APPEND);

echo json_encode([
    'url'        => $authUrl,
    'payment_id' => $paymentId,
]);
