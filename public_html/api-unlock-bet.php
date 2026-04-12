<?php
// STRATEDGE — Déblocage d'un bet via 1 crédit (AJAX)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/credits-manager.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok'=>false,'err'=>'not_logged']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'err'=>'method']); exit; }

$membre = getMembre();
$betId = (int)($_POST['bet_id'] ?? 0);
if ($betId <= 0) { echo json_encode(['ok'=>false,'err'=>'invalid_bet']); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, categorie FROM bets WHERE id=? LIMIT 1");
    $stmt->execute([$betId]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bet) { echo json_encode(['ok'=>false,'err'=>'bet_not_found']); exit; }
    // Crédits uniquement pour Multisports
    if ($bet['categorie'] !== 'multi') { echo json_encode(['ok'=>false,'err'=>'wrong_category']); exit; }

    $mid = (int)$membre['id'];
    // Déjà débloqué = gratuit
    if (stratedge_credits_deja_consulte($mid, $betId)) {
        echo json_encode(['ok'=>true,'already'=>true,'solde'=>stratedge_credits_solde($mid)]); exit;
    }
    // Consommer
    if (!stratedge_credits_consommer($mid, $betId)) {
        echo json_encode(['ok'=>false,'err'=>'no_credits','solde'=>0]); exit;
    }
    echo json_encode(['ok'=>true,'already'=>false,'solde'=>stratedge_credits_solde($mid)]);
} catch (Throwable $e) {
    error_log('[api-unlock-bet] '.$e->getMessage());
    echo json_encode(['ok'=>false,'err'=>'server']);
}
