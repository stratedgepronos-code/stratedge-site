<?php
// STRATEDGE — Vérification code promo (AJAX)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/promo.php';
require_once __DIR__ . '/includes/packs-config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(['ok'=>false,'err'=>'not_logged']); exit; }

$code  = strtoupper(trim($_POST['code'] ?? $_GET['code'] ?? ''));
$offre = trim($_POST['offre'] ?? $_GET['offre'] ?? '');

if ($code === '' || $offre === '') {
    echo json_encode(['ok'=>false,'err'=>'Paramètres manquants']);
    exit;
}

// Déterminer le prix initial selon l'offre
$prixMap = [
    'unique'  => 4.50,
    'duo'     => 8.00,
    'trio'    => 12.00,
    'quinte'  => 18.00,
    'semaine' => 20.00,
    'pack10'  => 30.00,
    'tennis'  => 15.00,
    'fun'     => 10.00,
    'vip_max' => 49.90,
];

if (!isset($prixMap[$offre])) {
    echo json_encode(['ok'=>false,'err'=>'Offre inconnue']);
    exit;
}

$membre = getMembre();
$prix = $prixMap[$offre];
$result = calculerPrixAvecPromo($prix, $offre, (int)$membre['id'], $code);

if ($result['label'] !== null) {
    echo json_encode([
        'ok'           => true,
        'prix_initial' => $prix,
        'prix_final'   => $result['montant'],
        'label'        => $result['label'],
        'code_promo_id'=> $result['code_promo_id'],
    ]);
} else {
    // Pas de réduction trouvée → on essaie d'obtenir l'erreur directe
    $detail = appliquerCodePromo($code, $offre, $prix, (int)$membre['id']);
    echo json_encode(['ok'=>false,'err'=> $detail['error'] ?? 'Code invalide']);
}
