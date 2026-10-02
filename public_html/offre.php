<?php
// ============================================================
// STRATEDGE — offre.php — Routeur de paiement
// Usage : /offre.php?type=daily|weekend|weekly|tennis|vip_max
// ============================================================

$type = $_GET['type'] ?? '';

// Sécurité : n'autoriser que les types connus
$allowed = ['daily', 'weekend', 'weekly', 'tennis', 'vip_max'];
if (!in_array($type, $allowed)) {
    header('Location: /offres.php');
    exit;
}

require_once __DIR__ . '/includes/auth.php';
if (!isLoggedIn()) { require __DIR__ . '/offres.php'; exit; }

// Inclure le template commun (qui utilise $type)
require_once __DIR__ . '/includes/offre-template.php';
