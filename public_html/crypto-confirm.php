<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    header('Location: /'); exit;
}

$membreId = (int)($_POST['membre_id'] ?? 0);
$offre    = $_POST['offre'] ?? '';
$txHash   = trim($_POST['tx_hash'] ?? '');
$crypto   = $_POST['crypto'] ?? '';

$validOffres  = ['daily','weekend','weekly'];
$validCryptos = ['btc','eth','usdt'];

if (!$membreId || !in_array($offre, $validOffres) || !in_array($crypto, $validCryptos) || strlen($txHash) < 10) {
    header('Location: /offre-'.$offre.'.php?error=invalid'); exit;
}

// Enregistrer la demande en BDD
$db->prepare("
    INSERT INTO crypto_payments (membre_id, offre, crypto, tx_hash, statut, date_demande)
    VALUES (?, ?, ?, ?, 'en_attente', NOW())
")->execute([$membreId, $offre, $crypto, $txHash]);

$paymentId = $db->lastInsertId();

// Notifier l'admin par email (via Brevo si configuré)
require_once __DIR__ . '/includes/mailer.php';
$membre = getMembre();
$prixMap = ['daily'=>'4,50€','weekend'=>'10€','weekly'=>'20€','tennis'=>'15€'];
$prix    = $prixMap[$offre] ?? '?';

envoyerEmailTexte(
    defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'stratedgepronos@gmail.com',
    "💰 Nouvelle demande crypto — {$membre['nom']} ({$prix})",
    "Membre : {$membre['nom']} ({$membre['email']})\n"
    . "Offre : {$offre} ({$prix})\n"
    . "Crypto : " . strtoupper($crypto) . "\n"
    . "TX Hash : {$txHash}\n\n"
    . "👉 Valider sur : https://stratedgepronos.fr/panel-x9k3m/crypto-admin.php\n"
    . "ID paiement : {$paymentId}"
);

// Rediriger vers page de confirmation
header('Location: /crypto-pending.php?id='.$paymentId);
exit;
