<?php
// ============================================================
// STRATEDGE — activate.php
// Appelé par StarPass après paiement (via datas= dans le script)
// Format datas : membre_id:type[:montant_optionnel]
// ============================================================

require_once __DIR__ . '/includes/auth.php';

// StarPass envoie les datas en GET ou POST selon la config
$datas = $_GET['datas'] ?? $_POST['datas'] ?? '';

if (empty($datas)) {
    // Accès direct sans datas → rediriger vers dashboard
    header('Location: /dashboard.php');
    exit;
}

$parts      = explode(':', $datas);
$membreId   = (int)($parts[0] ?? 0);
$type       = $parts[1] ?? '';
$montant    = isset($parts[2]) ? (float)$parts[2] : 0;

// Vérifications de base
$typesValides = ['daily', 'weekend', 'weekly', 'tennis', 'vip_max', 'rasstoss'];
if ($membreId <= 0 || !in_array($type, $typesValides)) {
    header('Location: /dashboard.php?error=activation');
    exit;
}

// Activer l'abonnement (avec montant override si passé)
$ok = activerAbonnement($membreId, $type, $montant);

if ($ok) {
    header('Location: /dashboard.php?activated=1&type=' . urlencode($type));
} else {
    header('Location: /merci.php?type=' . urlencode($type));
}
exit;
