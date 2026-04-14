<?php
// ============================================================
// STRATEDGE — Reassigner un bet a un autre tipster (super admin only)
// admin/reassign-bet-tipster.php
// ============================================================

// IMPORTANT: capturer toute sortie potentielle pour garantir du JSON
ob_start();

// Forcer Content-Type JSON dès le début
header('Content-Type: application/json; charset=utf-8');

// Helper pour quitter proprement avec JSON
function jsonExit(array $data, int $http = 200): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Capturer les erreurs PHP pour les transformer en JSON
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log("[reassign-bet] PHP error: $message in $file:$line");
    jsonExit(['success' => false, 'error' => "PHP error: $message"], 500);
});
set_exception_handler(function ($e) {
    error_log('[reassign-bet] Exception non-attrapee: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonExit(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], 500);
});

try {
    require_once __DIR__ . '/../includes/auth.php';
} catch (Throwable $e) {
    jsonExit(['success' => false, 'error' => 'Impossible de charger auth.php: ' . $e->getMessage()], 500);
}

// Verif super admin SANS redirect (ne pas utiliser requireSuperAdmin qui fait header Location)
if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    jsonExit(['success' => false, 'error' => 'Acces refuse: super admin requis. Ta session a peut-etre expire, reconnecte-toi.'], 403);
}

// Verif methode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(['success' => false, 'error' => 'Methode POST requise.'], 405);
}

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!$csrf || (function_exists('verifyCsrf') && !verifyCsrf($csrf))) {
    jsonExit(['success' => false, 'error' => 'Token CSRF invalide ou absent. Recharge la page.'], 403);
}

$betId = (int)($_POST['bet_id'] ?? 0);
$newRole = $_POST['new_role'] ?? '';

if (!$betId) {
    jsonExit(['success' => false, 'error' => 'Parametre bet_id manquant.'], 400);
}

$validRoles = ['superadmin', 'admin_tennis', 'admin_fun'];
if (!in_array($newRole, $validRoles, true)) {
    jsonExit(['success' => false, 'error' => "Role invalide '$newRole'. Doit etre l'un de: " . implode(', ', $validRoles)], 400);
}

try {
    $db = getDB();

    // Verifier que la colonne posted_by_role existe (sinon migration pas faite)
    $colCheck = $db->query("SHOW COLUMNS FROM bets LIKE 'posted_by_role'")->fetchAll();
    if (empty($colCheck)) {
        jsonExit(['success' => false, 'error' => "La colonne posted_by_role n'existe pas. Lance la migration d'abord."], 500);
    }

    // Verifier que le bet existe
    $stmt = $db->prepare("SELECT id, titre, posted_by_role, categorie FROM bets WHERE id = ?");
    $stmt->execute([$betId]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bet) {
        jsonExit(['success' => false, 'error' => "Bet ID:$betId introuvable."], 404);
    }

    $oldRole = $bet['posted_by_role'] ?? 'superadmin';
    $oldCategorie = $bet['categorie'] ?? 'multi';

    // Si le bet va vers admin_tennis -> categorie='tennis', sinon 'multi'
    $newCategorie = ($newRole === 'admin_tennis') ? 'tennis' : 'multi';

    // Update
    $upd = $db->prepare("UPDATE bets SET posted_by_role = ?, categorie = ? WHERE id = ?");
    $upd->execute([$newRole, $newCategorie, $betId]);

    // Log d'audit
    error_log("[reassign-bet] Bet ID:$betId ('{$bet['titre']}') de '$oldRole'/'$oldCategorie' vers '$newRole'/'$newCategorie' par " . ($_SESSION['membre_email'] ?? 'unknown'));

    $tipsterLabels = [
        'superadmin'   => 'Stratedge Multi',
        'admin_tennis' => 'Stratedge Tennis',
        'admin_fun'    => 'Stratedge Fun',
    ];

    jsonExit([
        'success' => true,
        'bet_id' => $betId,
        'old_role' => $oldRole,
        'new_role' => $newRole,
        'old_categorie' => $oldCategorie,
        'new_categorie' => $newCategorie,
        'new_tipster_label' => $tipsterLabels[$newRole],
        'message' => "Bet basculé vers " . $tipsterLabels[$newRole],
    ]);
} catch (Throwable $e) {
    error_log('[reassign-bet] DB Exception: ' . $e->getMessage());
    jsonExit(['success' => false, 'error' => 'Erreur BDD: ' . $e->getMessage()], 500);
}
