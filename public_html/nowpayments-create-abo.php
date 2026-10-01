<?php
// Wrapper GET pour créer un paiement crypto NowPayments d'abo (tennis/fun)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nowpayments-config.php';
require_once __DIR__ . '/includes/payment-config.php';
requireLogin();

$type = $_GET['type'] ?? '';
if (!in_array($type, ['tennis', 'fun'])) {
    die('Type abo invalide');
}

$membre = getMembre();
$amount = PAYMENT_AMOUNTS[$type];
$label = PAYMENT_LABELS[$type] ?? 'StratEdge';
$orderId = 'NP_' . $membre['id'] . '_' . $type . '_' . time();

$payload = [
    'price_amount' => $amount,
    'price_currency' => 'eur',
    'order_id' => $orderId,
    'order_description' => $label,
    'ipn_callback_url' => SITE_BASE_URL . '/nowpayments-ipn.php',
    'success_url' => SITE_BASE_URL . '/nowpayments-status.php?order_id=' . urlencode($orderId),
    'cancel_url' => SITE_BASE_URL . '/offre-' . $type . '.php?cancelled=1',
];

$ch = curl_init('https://api.nowpayments.io/v1/invoice');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . NP_API_KEY,
    ],
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode === 200 && !empty($data['invoice_url'])) {
    // Sauvegarde en BDD pour tracking
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS nowpayments_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            membre_id INT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL,
            order_id VARCHAR(80) NOT NULL UNIQUE,
            invoice_id VARCHAR(80),
            amount_eur DECIMAL(8,2),
            statut VARCHAR(20) DEFAULT 'pending',
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_membre (membre_id),
            KEY idx_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $db->prepare("INSERT INTO nowpayments_orders (membre_id, type, order_id, invoice_id, amount_eur) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$membre['id'], $type, $orderId, $data['id'] ?? '', $amount]);
    } catch (Throwable $e) { /* silencieux */ }

    header('Location: ' . $data['invoice_url']);
    exit;
}

@file_put_contents(__DIR__ . '/logs/nowpayments-log.txt',
    date('Y-m-d H:i:s') . " | ABO ERROR | type=$type | HTTP=$httpCode | " . substr($response, 0, 300) . "\n", FILE_APPEND);
die('Erreur lors de la création du paiement crypto. Réessaie ou contacte le support.');
