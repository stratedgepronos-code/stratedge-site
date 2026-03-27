<?php
// ============================================================
// STRATEDGE — activate.php
// Appelé par StarPass après paiement (callback serveur-à-serveur)
// Format datas : membre_id:type
// ============================================================

require_once __DIR__ . '/includes/auth.php';

// ── Log pour debug (à supprimer une fois que tout marche) ───
$logFile = __DIR__ . '/logs/activate-log.txt';
$logDir = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

$logData = date('Y-m-d H:i:s') . ' | '
    . 'METHOD=' . $_SERVER['REQUEST_METHOD'] . ' | '
    . 'GET=' . json_encode($_GET) . ' | '
    . 'POST=' . json_encode($_POST) . ' | '
    . 'IP=' . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n";
@file_put_contents($logFile, $logData, FILE_APPEND);

// StarPass envoie les datas en GET ou POST selon la config
$datas = $_GET['datas'] ?? $_POST['datas'] ?? '';

if (empty($datas)) {
    @file_put_contents($logFile, "  → SKIP: datas vide\n", FILE_APPEND);
    header('Location: /dashboard.php');
    exit;
}

$parts      = explode(':', $datas);
$membreId   = (int)($parts[0] ?? 0);
$type       = $parts[1] ?? '';

// Vérifications de base
$typesValides = ['daily', 'weekend', 'weekly', 'tennis', 'vip_max', 'rasstoss'];
if ($membreId <= 0 || !in_array($type, $typesValides)) {
    @file_put_contents($logFile, "  → ERREUR: membreId=$membreId type=$type invalide\n", FILE_APPEND);
    header('Location: /dashboard.php?error=activation');
    exit;
}

// Activer l'abonnement (2 arguments seulement)
$ok = activerAbonnement($membreId, $type);

@file_put_contents($logFile, "  → activerAbonnement($membreId, $type) = " . ($ok ? 'OK' : 'FAIL') . "\n", FILE_APPEND);

if ($ok) {
    // ✅ Succès → page merci (AVANT c'était inversé !)
    header('Location: /merci.php?type=' . urlencode($type));
} else {
    // ❌ Échec → dashboard avec erreur
    header('Location: /dashboard.php?error=activation');
}
exit;
