<?php
// ============================================================
// STRATEDGE — Stripe Success (redirection après paiement)
// stripe-success.php
// ============================================================

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$type = $_GET['type'] ?? 'weekly';
// Redirige vers merci.php qui gère déjà le design par type
header('Location: /merci.php?type=' . urlencode($type));
exit;
