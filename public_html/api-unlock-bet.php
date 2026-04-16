<?php
// STRATEDGE — Déblocage d'un bet via crédits (AJAX)
// Règle: 1 crédit = 24h d'accès à tous les bets du superadmin
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
    $stmt = $db->prepare("SELECT id, categorie, posted_by_role FROM bets WHERE id=? LIMIT 1");
    $stmt->execute([$betId]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bet) { echo json_encode(['ok'=>false,'err'=>'bet_not_found']); exit; }
    
    // Crédits Multi = uniquement pour les bets du superadmin (Alex)
    // Les bets Tennis (admin_tennis) et Fun (admin_fun) passent par abonnement, pas crédits
    $betRole = $bet['posted_by_role'] ?? 'superadmin';
    if ($betRole !== 'superadmin') {
        echo json_encode(['ok'=>false,'err'=>'wrong_category','msg'=>'Ce bet nécessite un abonnement, pas des crédits.']);
        exit;
    }

    $mid = (int)$membre['id'];
    
    // Déjà débloqué (individuel ou pass 24h actif) = gratuit
    if (stratedge_credits_deja_consulte($mid, $betId)) {
        $passExpire = stratedge_credits_pass_expire($mid);
        echo json_encode(['ok'=>true,'already'=>true,'solde'=>stratedge_credits_solde($mid),'pass_expire'=>$passExpire]);
        exit;
    }
    
    // Consommer (crée un pass 24h si pas actif, gratuit si pass actif)
    if (!stratedge_credits_consommer($mid, $betId)) {
        echo json_encode(['ok'=>false,'err'=>'no_credits','solde'=>0]);
        exit;
    }
    
    $passExpire = stratedge_credits_pass_expire($mid);
    echo json_encode([
        'ok'=>true,
        'already'=>false,
        'solde'=>stratedge_credits_solde($mid),
        'pass_expire'=>$passExpire,
        'msg'=>'Pass 24h activé ! Tous les bets Multi sont accessibles.'
    ]);
} catch (Throwable $e) {
    error_log('[api-unlock-bet] '.$e->getMessage());
    echo json_encode(['ok'=>false,'err'=>'server']);
}
