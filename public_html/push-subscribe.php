<?php
// ============================================================
// STRATEDGE — Gestion souscriptions push
// POST /push-subscribe.php   → enregistrer
// DELETE /push-subscribe.php → désabonner
// ============================================================

require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');

requireLogin();
$membre = getMembre();
if (!$membre) { echo json_encode(['error' => 'Non connecté']); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $endpoint = $body['endpoint']    ?? '';
    $p256dh   = $body['keys']['p256dh'] ?? '';
    $auth     = $body['keys']['auth']   ?? '';
    $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if (!$endpoint || !$p256dh || !$auth) {
        echo json_encode(['error' => 'Données manquantes']); exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO push_subscriptions (membre_id, endpoint, p256dh, auth, user_agent)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                membre_id  = VALUES(membre_id),
                p256dh     = VALUES(p256dh),
                auth       = VALUES(auth),
                user_agent = VALUES(user_agent),
                date_ajout = NOW()
        ");
        $stmt->execute([$membre['id'], $endpoint, $p256dh, $auth, $ua]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erreur DB']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $endpoint = $body['endpoint'] ?? '';
    if ($endpoint) {
        $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND membre_id = ?")
           ->execute([$endpoint, $membre['id']]);
    }
    echo json_encode(['success' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non supportée']);
}
