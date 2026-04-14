<?php
// ============================================================
// STRATEDGE — Reassigner un bet a un autre tipster (super admin only)
// admin/reassign-bet-tipster.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();

header('Content-Type: application/json; charset=utf-8');

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide.']);
    exit;
}

$betId = (int)($_POST['bet_id'] ?? 0);
$newRole = $_POST['new_role'] ?? '';

if (!$betId) {
    echo json_encode(['success' => false, 'error' => 'bet_id manquant.']);
    exit;
}

$validRoles = ['superadmin', 'admin_tennis', 'admin_fun'];
if (!in_array($newRole, $validRoles, true)) {
    echo json_encode(['success' => false, 'error' => 'Role invalide. Doit etre superadmin/admin_tennis/admin_fun.']);
    exit;
}

try {
    $db = getDB();

    // Verifier que le bet existe
    $stmt = $db->prepare("SELECT id, titre, posted_by_role FROM bets WHERE id = ?");
    $stmt->execute([$betId]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bet) {
        echo json_encode(['success' => false, 'error' => 'Bet introuvable.']);
        exit;
    }

    $oldRole = $bet['posted_by_role'] ?? 'superadmin';

    // Si le bet va vers admin_tennis, on le met aussi en categorie='tennis' pour coherence
    // Sinon (vers superadmin ou admin_fun) on le met en categorie='multi'
    $newCategorie = ($newRole === 'admin_tennis') ? 'tennis' : 'multi';

    $upd = $db->prepare("UPDATE bets SET posted_by_role = ?, categorie = ? WHERE id = ?");
    $upd->execute([$newRole, $newCategorie, $betId]);

    // Log pour audit
    error_log("[reassign-bet] Bet ID:$betId ('{$bet['titre']}') reassigne de '$oldRole' vers '$newRole' (categorie=$newCategorie)");

    $tipsterLabels = [
        'superadmin'   => 'Stratedge Multi',
        'admin_tennis' => 'Stratedge Tennis',
        'admin_fun'    => 'Stratedge Fun',
    ];

    echo json_encode([
        'success' => true,
        'bet_id' => $betId,
        'old_role' => $oldRole,
        'new_role' => $newRole,
        'new_tipster_label' => $tipsterLabels[$newRole],
        'message' => "Bet basculé vers " . $tipsterLabels[$newRole],
    ]);
} catch (Throwable $e) {
    error_log('[reassign-bet] Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur BDD: ' . $e->getMessage()]);
}
