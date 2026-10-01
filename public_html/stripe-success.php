<?php
// ============================================================
// STRATEDGE — Stripe Success (redirection après paiement)
// stripe-success.php
// Note: NE PAS requireLogin() ici car le cookie de session peut
// être perdu au retour de Stripe (SameSite, navigateur, etc.)
// Le webhook Stripe active l'abonnement côté serveur indépendamment.
// ============================================================

require_once __DIR__ . '/includes/auth.php';

// Routing intelligent: ?pack=xxx (packs credits) OU ?type=xxx (abos tennis/fun)
$pack = $_GET['pack'] ?? '';
$type = $_GET['type'] ?? '';

if ($pack !== '') {
    header('Location: /merci.php?type=' . urlencode($pack));
} elseif ($type !== '') {
    header('Location: /merci.php?type=' . urlencode($type));
} else {
    header('Location: /merci.php?type=multi_pack');
}
exit;
