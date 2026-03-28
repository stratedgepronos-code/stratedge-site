<?php
// ============================================================
// STRATEDGE — Paysafecard Return (retour après saisie du PIN)
// paysafe-return.php
// Capture le paiement puis redirige vers merci.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
requireLogin();

$logFile = __DIR__ . '/logs/paysafe-log.txt';
function pscLog(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | RETURN | ' . $msg . "\n", FILE_APPEND);
}

$orderId = $_GET['order_id'] ?? '';
$type    = $_GET['type'] ?? '';

if (!$orderId) {
    pscLog('ERREUR: order_id manquant');
    header('Location: /dashboard.php?error=psc');
    exit;
}

// ── Récupérer le paiement en BDD ─────────────────────────────
$db = getDB();
$stmt = $db->prepare("SELECT * FROM paysafe_payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$orderId]);
$payment = $stmt->fetch();

if (!$payment) {
    pscLog("ERREUR: order_id $orderId introuvable en BDD");
    header('Location: /dashboard.php?error=psc');
    exit;
}

if ($payment['statut'] === 'validé') {
    pscLog("Doublon: $orderId déjà validé");
    header('Location: /merci.php?type=' . urlencode($type));
    exit;
}

$paysafeId = $payment['paysafe_id'];
$membreId  = (int)$payment['membre_id'];
$offreType = $payment['offre'];

// ── Vérifier le statut du paiement ───────────────────────────
$apiUrl = paysafeBaseUrl() . '/payments/' . urlencode($paysafeId);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
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
$status = $data['status'] ?? '';

pscLog("Status check: $orderId → status=$status (HTTP $httpCode)");

if ($status !== 'AUTHORIZED') {
    pscLog("ERREUR: statut $status != AUTHORIZED pour $orderId");
    header('Location: /dashboard.php?error=psc_status');
    exit;
}

// ── Capturer le paiement ─────────────────────────────────────
$captureUrl = paysafeBaseUrl() . '/payments/' . urlencode($paysafeId) . '/capture';

$ch = curl_init($captureUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['id' => $paysafeId]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode('psc_' . PAYSAFE_API_KEY . ':'),
    ],
    CURLOPT_TIMEOUT        => 15,
]);
$captureResp = curl_exec($ch);
$captureCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$captureData = json_decode($captureResp, true);
$captureStatus = $captureData['status'] ?? '';

pscLog("Capture: $orderId → status=$captureStatus (HTTP $captureCode)");

if ($captureStatus !== 'SUCCESS' && $captureCode >= 400) {
    pscLog("ERREUR: capture échouée pour $orderId : " . substr($captureResp, 0, 300));
    header('Location: /dashboard.php?error=psc_capture');
    exit;
}

// ── Activer l'abonnement ─────────────────────────────────────
$typeActivation = preg_replace('/_fun$/', '', $offreType);

if (activerAbonnement($membreId, $typeActivation)) {
    pscLog("✅ Abonnement activé: membre #$membreId → $typeActivation ({$payment['montant_eur']}€)");

    // Marquer comme validé
    $stmt = $db->prepare("UPDATE paysafe_payments SET statut = 'validé', date_validation = NOW() WHERE id = ?");
    $stmt->execute([$payment['id']]);

    // GiveAway points
    try {
        require_once __DIR__ . '/includes/giveaway-functions.php';
        ajouterPointsGiveaway($membreId, $typeActivation);
    } catch (Throwable $e) { /* silencieux */ }

    // Email confirmation
    try {
        require_once __DIR__ . '/includes/mailer.php';
        $stmtM = $db->prepare("SELECT email, nom FROM membres WHERE id = ?");
        $stmtM->execute([$membreId]);
        $m = $stmtM->fetch();
        if ($m && $m['email']) {
            $label = PAYMENT_LABELS[$offreType] ?? $offreType;
            envoyerEmail($m['email'], '✅ Paiement Paysafecard confirmé — ' . $label,
                emailTemplate('✅ Paiement confirmé', '
                    <p>Salut <strong>' . htmlspecialchars($m['nom']) . '</strong>,</p>
                    <p>Ton paiement Paysafecard de <strong>' . $payment['montant_eur'] . '€</strong> pour <strong>' . $label . '</strong> a été confirmé.</p>
                    <p>Ton accès est maintenant actif. <a href="' . SITE_BASE_URL . '/dashboard.php" style="color:#00d4ff;">Accéder à mon espace →</a></p>
                ')
            );
        }
    } catch (Throwable $e) {
        pscLog('WARN: email échoué: ' . $e->getMessage());
    }

    header('Location: /merci.php?type=' . urlencode($type));
} else {
    pscLog("❌ activerAbonnement ÉCHOUÉ: membre #$membreId → $typeActivation");
    header('Location: /dashboard.php?error=activation');
}
exit;
