<?php
// ============================================================
// STRATEDGE — Vérification statut paiement crypto
// Appelé en AJAX toutes les 15 secondes par la page d'offre
// Retourne le statut actuel du paiement
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nowpayments-config.php';

header('Content-Type: application/json');

requireLogin();
$membre = getMembre();
if (!$membre) {
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$paymentId = trim($_GET['payment_id'] ?? '');
if (!$paymentId || !preg_match('/^\d+$/', $paymentId)) {
    echo json_encode(['error' => 'payment_id invalide']);
    exit;
}

// ── Option 1 : vérifier en base (renseigné par l'IPN) ─────
try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT statut, date_validation
        FROM crypto_payments
        WHERE payment_id = ? AND membre_id = ?
        LIMIT 1
    ");
    $stmt->execute([$paymentId, $membre['id']]);
    $row = $stmt->fetch();
} catch (Exception $e) {
    $row = null;
}

if ($row && $row['statut'] === 'validé') {
    echo json_encode([
        'status'   => 'finished',
        'message'  => '✅ Paiement confirmé ! Ton accès est actif.',
        'redirect' => 'dashboard.php',
    ]);
    exit;
}

if ($row && $row['statut'] === 'rejeté') {
    echo json_encode([
        'status'  => 'failed',
        'message' => '❌ Paiement échoué ou expiré. Contacte le support.',
    ]);
    exit;
}

// ── Option 2 : vérifier directement via API NOWPayments ───
// (utile si l'IPN n'est pas encore arrivé mais que le paiement est fait)
$ch = curl_init(NP_API_BASE . '/payment/' . $paymentId);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['x-api-key: ' . NP_API_KEY],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['status' => 'waiting', 'message' => 'Vérification en cours…']);
    exit;
}

$data   = json_decode($response, true);
$status = $data['payment_status'] ?? 'waiting';

$messages = [
    'waiting'        => '⏳ En attente de ton virement crypto…',
    'confirming'     => '🔄 Transaction détectée, confirmation en cours…',
    'confirmed'      => '✅ Confirmé ! Activation en cours…',
    'sending'        => '🔄 Finalisation…',
    'finished'       => '✅ Paiement confirmé ! Ton accès est actif.',
    'partially_paid' => '✅ Paiement reçu ! Activation en cours…',
    'failed'         => '❌ Paiement échoué. Contacte le support.',
    'refunded'       => '↩️ Paiement remboursé.',
    'expired'        => '⌛ Paiement expiré. Génère une nouvelle adresse.',
];

$result = [
    'status'  => $status,
    'message' => $messages[$status] ?? 'Statut: ' . $status,
];

// N'activer et rediriger QUE sur "finished" (paiement réellement reçu)
// "confirming"/"confirmed" = détecté sur blockchain, mais pas encore finalisé
// → on affiche juste le message progressif, PAS de redirection
if (in_array($status, ['finished', 'partially_paid'])) {
    $orderId = $data['order_id'] ?? '';
    if (preg_match('/^SE_(\d+)_(daily|weekend_fun|weekend|weekly|tennis|vip_max)_\d+$/', $orderId, $m)) {
        $mid  = (int) $m[1];
        $type = $m[2];
        if ($mid === $membre['id']) {
            $stmt = $db->prepare("SELECT id FROM crypto_payments WHERE payment_id = ? AND statut = 'validé'");
            $stmt->execute([$paymentId]);
            if (!$stmt->fetch()) {
                activerAbonnement($mid, $type);
                $db->prepare("UPDATE crypto_payments SET statut='validé', date_validation=NOW() WHERE payment_id=?")
                   ->execute([$paymentId]);
                emailConfirmationAbonnement($membre['email'], $membre['nom'], $type);
            }
        }
    }
    $result['redirect'] = 'dashboard.php';
}

echo json_encode($result);
