<?php
// ============================================================
// STRATEDGE — Paysafecard Webhook (notification)
// paysafe-webhook.php
// Backup: si paysafe-return.php n'a pas capté le paiement
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';

$logFile = __DIR__ . '/logs/paysafe-log.txt';
function pscWLog(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | WEBHOOK | ' . $msg . "\n", FILE_APPEND);
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data || !isset($data['mtid'])) {
    http_response_code(400);
    pscWLog('ERREUR: payload invalide');
    exit;
}

$paysafeId = $data['mtid'] ?? '';
$status    = $data['eventType'] ?? '';

pscWLog("Notification reçue: psc_id=$paysafeId status=$status");

// Chercher en BDD
$db = getDB();
$stmt = $db->prepare("SELECT * FROM paysafe_payments WHERE paysafe_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$paysafeId]);
$payment = $stmt->fetch();

if (!$payment) {
    pscWLog("WARN: paysafe_id $paysafeId introuvable en BDD");
    http_response_code(200);
    echo 'OK';
    exit;
}

if ($payment['statut'] === 'validé') {
    pscWLog("Doublon: $paysafeId déjà validé");
    http_response_code(200);
    echo 'OK';
    exit;
}

// Si le paiement est AUTHORIZED mais pas encore capturé,
// le capture ici en backup
if ($status === 'PAYMENT_CAPTURED' || $status === 'PAYMENT_AUTHORIZED') {
    $offreType = $payment['offre'];
    $membreId = (int)$payment['membre_id'];
    $packKeys = ['unique', 'duo', 'trio', 'quinte', 'semaine', 'pack10'];
    $isPack = in_array($offreType, $packKeys, true);

    if ($payment['statut'] !== 'validé') {
        $activated = false;

        if ($isPack) {
            require_once __DIR__ . '/includes/credits-manager.php';
            require_once __DIR__ . '/includes/packs-config.php';
            $packId = stratedge_credits_ajouter($membreId, $offreType, 'paysafecard', $payment['order_id']);
            $activated = ($packId > 0);
            if ($activated) pscWLog("✅ Pack crédits activé via webhook: membre #$membreId → $offreType");
        } else {
            $typeActivation = preg_replace('/_fun$/', '', $offreType);
            $activated = activerAbonnement($membreId, $typeActivation);
            if ($activated) pscWLog("✅ Abo activé via webhook: membre #$membreId → $typeActivation");
        }

        if ($activated) {
            $db->prepare("UPDATE paysafe_payments SET statut = 'validé', date_validation = NOW() WHERE id = ?")->execute([$payment['id']]);
            try {
                require_once __DIR__ . '/includes/giveaway-functions.php';
                ajouterPointsGiveaway($membreId, $offreType);
            } catch (Throwable $e) { /* silencieux */ }
        } else {
            pscWLog("❌ Activation échouée via webhook: membre #$membreId → $offreType");
        }
    }
}

http_response_code(200);
echo 'OK';
