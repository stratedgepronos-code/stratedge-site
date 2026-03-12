<?php
// ============================================================
// STRATEDGE — Webhook IPN NOWPayments
// URL à renseigner sur nowpayments.io :
//   Paramètres → Clé secrète IPN → URL de callback
//   → https://stratedgepronos.fr/nowpayments-ipn.php
//
// Ce fichier :
//  1. Reçoit la notification de paiement de NOWPayments
//  2. Vérifie la signature HMAC-SHA512 (authentification)
//  3. Si statut = "finished" → active l'abonnement du membre
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nowpayments-config.php';

define('IPN_LOG', __DIR__ . '/nowpayments_ipn.log');

// ── Lire le corps brut de la requête ──────────────────────
// ⚠️ IMPORTANT : lire AVANT tout traitement — la signature porte sur le corps brut
$rawBody = file_get_contents('php://input');

// ── Logger toutes les requêtes (pour debug) ────────────────
function ipnLog(string $msg): void {
    file_put_contents(IPN_LOG,
        date('Y-m-d H:i:s') . ' | ' . $msg . "\n",
        FILE_APPEND
    );
}

ipnLog('IPN reçu — IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
ipnLog('Body: ' . substr($rawBody, 0, 500));

// ── Vérifier la méthode ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ipnLog('ERREUR: Méthode non POST');
    exit;
}

// ── Vérifier la signature HMAC-SHA512 ─────────────────────
// NOWPayments envoie la signature dans le header x-nowpayments-sig
$receivedSig = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';

if (empty($receivedSig)) {
    http_response_code(400);
    ipnLog('ERREUR: Signature manquante');
    exit;
}

// Trier le body JSON par clés avant de signer (exigence NOWPayments)
$bodyArray = json_decode($rawBody, true);
if (!is_array($bodyArray)) {
    http_response_code(400);
    ipnLog('ERREUR: Body JSON invalide');
    exit;
}

// NOWPayments exige un tri récursif des clés avant de vérifier la signature
ksort($bodyArray);
$sortedBody = json_encode($bodyArray);

$expectedSig = hash_hmac('sha512', $sortedBody, NP_IPN_SECRET);

if (!hash_equals($expectedSig, strtolower($receivedSig))) {
    http_response_code(401);
    ipnLog('ERREUR: Signature invalide. Reçue: ' . $receivedSig);
    exit;
}

ipnLog('✅ Signature valide');

// ── Lire les données du paiement ──────────────────────────
$paymentId    = $bodyArray['payment_id']    ?? '';
$orderId      = $bodyArray['order_id']      ?? '';   // format : SE_{membre_id}_{type}_{ts}
$status       = $bodyArray['payment_status'] ?? '';
$payCurrency  = strtolower($bodyArray['pay_currency'] ?? '');
$payAmount    = $bodyArray['actually_paid'] ?? 0;

ipnLog("payment_id=$paymentId | order_id=$orderId | status=$status");

// ── Mettre à jour le statut en base ───────────────────────
$db = getDB();

try {
    $stmt = $db->prepare("
        UPDATE crypto_payments
        SET statut = ?, date_validation = NOW()
        WHERE payment_id = ?
    ");
    $mappedStatus = mapStatus($status);
    $stmt->execute([$mappedStatus, $paymentId]);
} catch (Exception $e) {
    ipnLog('WARN: DB update failed: ' . $e->getMessage());
}

// ── N'activer QUE si le paiement est "finished" ───────────
// Statuts possibles : waiting, confirming, confirmed, sending, finished, failed, refunded, expired
if (!in_array($status, ['finished', 'partially_paid'])) {
    http_response_code(200);
    ipnLog("Statut '$status' reçu — pas d'activation encore (attente 'finished' ou 'partially_paid')");
    echo 'OK';
    exit;
}

// ── Extraire membre_id et type depuis order_id ────────────
// Format attendu : SE_{membre_id}_{type}_{timestamp}
if (!preg_match('/^SE_(\d+)_(daily|weekend|weekly)_\d+$/', $orderId, $matches)) {
    http_response_code(400);
    ipnLog('ERREUR: order_id malformé: ' . $orderId);
    exit;
}

$membreId = (int) $matches[1];
$type     = $matches[2];

// ── Vérifier qu'il n'a pas déjà été activé (double IPN possible) ──
try {
    $stmt = $db->prepare("
        SELECT id FROM crypto_payments
        WHERE payment_id = ? AND statut = 'validé'
    ");
    $stmt->execute([$paymentId]);
    if ($stmt->fetch()) {
        http_response_code(200);
        ipnLog("Paiement $paymentId déjà validé — rien à faire");
        echo 'OK';
        exit;
    }
} catch (Exception $e) {
    ipnLog('WARN: double-check failed: ' . $e->getMessage());
}

// ── Récupérer les infos du membre ─────────────────────────
try {
    $stmt = $db->prepare("SELECT id, email, nom FROM membres WHERE id = ? AND actif = 1");
    $stmt->execute([$membreId]);
    $membre = $stmt->fetch();
} catch (Exception $e) {
    ipnLog('ERREUR: impossible de récupérer membre #' . $membreId);
    http_response_code(500);
    exit;
}

if (!$membre) {
    ipnLog('ERREUR: membre #' . $membreId . ' introuvable ou inactif');
    http_response_code(400);
    exit;
}

// ── Activer l'abonnement ───────────────────────────────────
if (activerAbonnement($membreId, $type)) {

    // Marquer comme validé en base
    $stmt = $db->prepare("
        UPDATE crypto_payments
        SET statut = 'validé', date_validation = NOW()
        WHERE payment_id = ?
    ");
    $stmt->execute([$paymentId]);

    // Email de confirmation au membre
    emailConfirmationAbonnement($membre['email'], $membre['nom'], $type);

    ipnLog("✅ SUCCÈS: membre #{$membreId} ({$membre['email']}) activé pour $type via crypto ($payCurrency)");

    // Email de notification admin
    $adminEmail = 'noreply@espeu9.fr';
    $subject    = "💎 Paiement crypto confirmé — StratEdge";
    $body       = "Paiement NOWPayments confirmé automatiquement.\n\n"
        . "Membre    : #{$membreId} — {$membre['email']}\n"
        . "Offre     : " . strtoupper($type) . "\n"
        . "Crypto    : " . strtoupper($payCurrency) . "\n"
        . "Montant   : {$payAmount}\n"
        . "Payment ID: {$paymentId}\n"
        . "Order ID  : {$orderId}\n";

    mail($adminEmail, $subject, $body, "From: StratEdge Pronos <noreply@stratedgepronos.fr>\r\nReply-To: support@stratedgepronos.fr\r\nContent-Type: text/plain; charset=UTF-8\r\n", '-f noreply@stratedgepronos.fr');

} else {
    ipnLog('ERREUR SQL: activerAbonnement a échoué pour membre #' . $membreId);
    http_response_code(500);
    exit;
}

http_response_code(200);
echo 'OK';

// ── Helper : mapping statut NOWPayments → notre base ──────
function mapStatus(string $npStatus): string {
    return match($npStatus) {
        'waiting', 'confirming'                      => 'en_attente',
        'confirmed', 'sending', 'finished',
        'partially_paid'                             => 'validé',
        'failed', 'refunded', 'expired'              => 'rejeté',
        default                                      => 'en_attente',
    };
}
