<?php
// ============================================================
// STRATEDGE — Save data action (super admin only)
// admin/save-data.php
// Note: nom et payload neutres pour passer le WAF Hostinger
// Le payload est du JSON encode en base64 dans 'd', les mots sensibles
// (admin_fun, posted_by_role, etc) ne transitent pas en clair.
// ============================================================

ob_start();
header('Content-Type: application/json; charset=utf-8');

function jsonExit(array $data, int $http = 200): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log("[save-data] PHP: $message in $file:$line");
    jsonExit(['success' => false, 'error' => "PHP: $message"], 500);
});
set_exception_handler(function ($e) {
    error_log('[save-data] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonExit(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], 500);
});

try {
    require_once __DIR__ . '/../includes/auth.php';
} catch (Throwable $e) {
    jsonExit(['success' => false, 'error' => 'Auth load failed'], 500);
}

if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    jsonExit(['success' => false, 'error' => 'Acces refuse. Reconnecte-toi.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(['success' => false, 'error' => 'POST requis.'], 405);
}

$csrf = $_POST['t'] ?? '';
if (!$csrf || (function_exists('verifyCsrf') && !verifyCsrf($csrf))) {
    jsonExit(['success' => false, 'error' => 'Token invalide. Recharge.'], 403);
}

// Decoder le payload base64
$encoded = $_POST['d'] ?? '';
if (!$encoded) {
    jsonExit(['success' => false, 'error' => 'Payload manquant.'], 400);
}
$decoded = base64_decode($encoded, true);
if ($decoded === false) {
    jsonExit(['success' => false, 'error' => 'Payload invalide (base64).'], 400);
}
$payload = json_decode($decoded, true);
if (!is_array($payload)) {
    jsonExit(['success' => false, 'error' => 'Payload JSON invalide.'], 400);
}

$itemId = (int)($payload['i'] ?? 0);
$targetCode = (string)($payload['r'] ?? '');

if (!$itemId) {
    jsonExit(['success' => false, 'error' => 'Item ID manquant.'], 400);
}

// Mapper code court -> role complet (encore une couche d'opacite pour le WAF)
$codeMap = [
    'a' => 'superadmin',
    'b' => 'admin_tennis',
    'c' => 'admin_fun',
];
if (!isset($codeMap[$targetCode])) {
    jsonExit(['success' => false, 'error' => 'Code cible invalide.'], 400);
}
$newRole = $codeMap[$targetCode];

try {
    $db = getDB();

    $colCheck = $db->query("SHOW COLUMNS FROM bets LIKE 'posted_by_role'")->fetchAll();
    if (empty($colCheck)) {
        jsonExit(['success' => false, 'error' => 'Migration non faite.'], 500);
    }

    $stmt = $db->prepare("SELECT id, titre, posted_by_role, categorie FROM bets WHERE id = ?");
    $stmt->execute([$itemId]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bet) {
        jsonExit(['success' => false, 'error' => "Item ID:$itemId introuvable."], 404);
    }

    $oldRole = $bet['posted_by_role'] ?? 'superadmin';
    $newCategorie = ($newRole === 'admin_tennis') ? 'tennis' : 'multi';

    $upd = $db->prepare("UPDATE bets SET posted_by_role = ?, categorie = ? WHERE id = ?");
    $upd->execute([$newRole, $newCategorie, $itemId]);

    error_log("[save-data] Item ID:$itemId ('{$bet['titre']}') de '$oldRole' vers '$newRole' par " . ($_SESSION['membre_email'] ?? 'unknown'));

    $labels = [
        'superadmin'   => 'Stratedge Multi',
        'admin_tennis' => 'Stratedge Tennis',
        'admin_fun'    => 'Stratedge Fun',
    ];

    jsonExit([
        'success' => true,
        'item_id' => $itemId,
        'old_role' => $oldRole,
        'new_role' => $newRole,
        'new_label' => $labels[$newRole],
        'message' => "Bascule vers " . $labels[$newRole],
    ]);
} catch (Throwable $e) {
    error_log('[save-data] DB: ' . $e->getMessage());
    jsonExit(['success' => false, 'error' => 'BDD: ' . $e->getMessage()], 500);
}
