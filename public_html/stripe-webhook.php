<?php
// ============================================================
// STRATEDGE — Stripe Webhook
// stripe-webhook.php
// Reçoit les événements Stripe (checkout.session.completed)
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';

$logFile = __DIR__ . '/logs/stripe-log.txt';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function stripeLog(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | WEBHOOK | ' . $msg . "\n", FILE_APPEND);
}

// ── Lire le body brut ────────────────────────────────────────
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    stripeLog('ERREUR: payload vide');
    exit;
}

// ── Vérifier la signature contre les 3 comptes Stripe ────────
$matchedAccount = matchStripeWebhook($sigHeader, $payload);

if (!$matchedAccount) {
    http_response_code(400);
    stripeLog('ERREUR: signature invalide (aucun des 3 comptes ne match)');
    exit;
}

$stripeAccountName = $matchedAccount['account'];
stripeLog("Signature OK → compte: $stripeAccountName");

// ── Parser l'événement ───────────────────────────────────────
$event = json_decode($payload, true);
$eventType = $event['type'] ?? '';

stripeLog("Event reçu: $eventType (id: " . ($event['id'] ?? '?') . ")");

if ($eventType !== 'checkout.session.completed') {
    // On ignore les autres événements
    http_response_code(200);
    echo 'OK';
    exit;
}

$session   = $event['data']['object'] ?? [];
$orderId   = $session['client_reference_id'] ?? '';
$metadata  = $session['metadata'] ?? [];
$membreId  = (int)($metadata['membre_id'] ?? 0);
$type      = $metadata['type'] ?? '';
$amount    = ($session['amount_total'] ?? 0) / 100;
$payStatus = $session['payment_status'] ?? '';

if ($payStatus !== 'paid') {
    stripeLog("SKIP: paiement pas 'paid' ($payStatus) pour $orderId");
    http_response_code(200);
    echo 'OK';
    exit;
}

if ($membreId <= 0 || $type === '') {
    stripeLog("ERREUR: metadata invalides (membreId=$membreId, type=$type)");
    http_response_code(400);
    exit;
}

// Normaliser le type (weekend_fun → weekend, weekly_fun → weekly)
$typeActivation = preg_replace('/_fun$/', '', $type);

// ── Vérifier doublon ─────────────────────────────────────────
$db = getDB();
try {
    $stmt = $db->prepare("SELECT id FROM stripe_payments WHERE stripe_session_id = ? AND statut = 'validé'");
    $stmt->execute([$session['id']]);
    if ($stmt->fetch()) {
        stripeLog("Doublon: session " . $session['id'] . " déjà validée");
        http_response_code(200);
        echo 'OK';
        exit;
    }
} catch (Throwable $e) {
    stripeLog('WARN: doublon check failed: ' . $e->getMessage());
}

// ── Activer l'abonnement (avec montant réel après code promo) ──
if (activerAbonnement($membreId, $typeActivation, (float)$amount)) {
    stripeLog("✅ Abonnement activé: membre #$membreId → $typeActivation ({$amount}€) [order: $orderId] [compte: $stripeAccountName]");

    // Sauvegarder en BDD
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS stripe_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            membre_id INT UNSIGNED NOT NULL,
            offre VARCHAR(30) NOT NULL,
            stripe_session_id VARCHAR(120) NOT NULL,
            order_id VARCHAR(80) NOT NULL,
            montant_eur DECIMAL(8,2) NOT NULL,
            stripe_account VARCHAR(20) DEFAULT 'multi',
            statut VARCHAR(20) DEFAULT 'validé',
            date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_session (stripe_session_id),
            KEY idx_membre (membre_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $db->prepare("INSERT INTO stripe_payments
            (membre_id, offre, stripe_session_id, order_id, montant_eur, stripe_account, statut)
            VALUES (?, ?, ?, ?, ?, ?, 'validé')");
        $stmt->execute([$membreId, $type, $session['id'], $orderId, $amount, $stripeAccountName]);
    } catch (Throwable $e) {
        stripeLog('WARN: sauvegarde BDD échouée: ' . $e->getMessage());
    }

    // GiveAway points
    try {
        require_once __DIR__ . '/includes/giveaway-functions.php';
        ajouterPointsGiveaway($membreId, $typeActivation);
    } catch (Throwable $e) { /* silencieux */ }

    // Enregistrer l'utilisation du code promo (si présent dans metadata)
    $promoId = (int)($metadata['promo_id'] ?? 0);
    $promoCodeUsed = $metadata['promo_code'] ?? '';
    if ($promoId > 0 && $promoCodeUsed !== '') {
        try {
            require_once __DIR__ . '/includes/promo.php';
            $prixInitial = PAYMENT_AMOUNTS[$type] ?? $amount;
            useCodePromo($promoId, $membreId, $type, (float)$prixInitial, $amount);
            stripeLog("🎟️ Code promo '$promoCodeUsed' (id=$promoId) utilisé par membre #$membreId");
        } catch (Throwable $e) {
            stripeLog('WARN: enregistrement promo échoué: ' . $e->getMessage());
        }
    }

    // Email confirmation
    try {
        require_once __DIR__ . '/includes/mailer.php';
        $stmtM = $db->prepare("SELECT email, nom FROM membres WHERE id = ?");
        $stmtM->execute([$membreId]);
        $m = $stmtM->fetch();
        if ($m && $m['email']) {
            $label = PAYMENT_LABELS[$type] ?? $type;
            envoyerEmail($m['email'], '✅ Paiement confirmé — ' . $label,
                emailTemplate('✅ Paiement confirmé', '
                    <p>Salut <strong>' . htmlspecialchars($m['nom']) . '</strong>,</p>
                    <p>Ton paiement de <strong>' . $amount . '€</strong> pour <strong>' . $label . '</strong> a été confirmé.</p>
                    <p>Ton accès est maintenant actif. <a href="' . SITE_BASE_URL . '/dashboard.php" style="color:#00d4ff;">Accéder à mon espace →</a></p>
                ')
            );
        }
    } catch (Throwable $e) {
        stripeLog('WARN: email échoué: ' . $e->getMessage());
    }

} else {
    stripeLog("❌ activerAbonnement ÉCHOUÉ: membre #$membreId → $typeActivation");
}

http_response_code(200);
echo 'OK';
