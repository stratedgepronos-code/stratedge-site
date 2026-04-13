<?php
// ============================================================
// STRATEDGE — Stripe Success (redirection après paiement)
// stripe-success.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Routing intelligent: ?pack=xxx (nouveaux packs credits) OU ?type=xxx (abos tennis/fun)
$pack = $_GET['pack'] ?? '';
$type = $_GET['type'] ?? '';

if ($pack !== '') {
    // Achat de pack credits Multi -> utiliser le pack_key comme type pour afficher le bon design
    header('Location: /merci.php?type=' . urlencode($pack));
} elseif ($type !== '') {
    // Abo tennis ou fun
    header('Location: /merci.php?type=' . urlencode($type));
} else {
    // Fallback
    header('Location: /merci.php?type=multi_pack');
}
exit;
