<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/state.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
function arena_reply(int $status, array $data): void { http_response_code($status); echo json_encode($data + ['serverNow'=>(int)round(microtime(true)*1000)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') { header('Allow: GET, POST'); arena_reply(405,['error'=>'Méthode non autorisée.']); }
if ($method === 'POST') {
    if (!isAdmin()) arena_reply(403, ['error'=>'Accès organisateur requis.']);
    if (!verifyCsrf((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) arena_reply(403, ['error'=>'Session expirée. Recharge la page organisateur.']);
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') arena_reply(403, ['error'=>'Origine non autorisée.']);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) arena_reply(413, ['error'=>'Tournoi trop volumineux.']);
}
// Do not hold the session lock during spectator polling or database access.
session_write_close();
try {
    if ($method === 'GET') arena_reply(200, arena_read(getDB()));
    $raw = file_get_contents('php://input', false, null, 0, 262145);
    if (strlen($raw) > 262144) arena_reply(413,['error'=>'Tournoi trop volumineux.']);
    $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($body) || !isset($body['revision']) || !is_int($body['revision']) || $body['revision'] < 0) arena_reply(422,['error'=>'Révision invalide.']);
    $state = arena_validate($body['state'] ?? null);
    $db = getDB();
    arena_ensure($db);
    $current = arena_read($db);
    if ($current['revision'] !== $body['revision']) arena_reply(409,['error'=>'Le tournoi a été modifié depuis un autre onglet. Recharge la version en ligne après avoir exporté tes modifications.']);
    $event = arena_event($current['state'], $state, $current['event']);
    $payload = json_encode(['state'=>$state,'event'=>$event], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('UPDATE slot_arena_live SET payload = ?, revision = revision + 1 WHERE id = 1 AND revision = ?');
    $stmt->execute([$payload, $body['revision']]);
    if ($stmt->rowCount() !== 1) arena_reply(409,['error'=>'Une autre sauvegarde a eu lieu. Recharge la version en ligne.']);
    arena_reply(200,['revision'=>$body['revision']+1,'event'=>$event]);
} catch (InvalidArgumentException | JsonException $e) { arena_reply(422,['error'=>$e->getMessage()]); }
catch (Throwable $e) { error_log('Slot Arena: '.$e->getMessage()); arena_reply(503,['error'=>'Synchronisation momentanément indisponible. Tes modifications restent sur cet appareil.']); }
