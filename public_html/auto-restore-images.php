<?php
/**
 * StratEdge — Auto-restauration images BDD → disque (post-deploy).
 * Appelé automatiquement par le workflow GitHub Actions après chaque déploiement.
 * Sécurisé par un token secret passé en paramètre.
 */

$expectedToken = getenv('RESTORE_TOKEN') ?: ($_SERVER['RESTORE_TOKEN'] ?? '');
$givenToken    = $_GET['token'] ?? '';

if ($expectedToken === '' || !hash_equals($expectedToken, $givenToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$betsDir   = __DIR__ . '/uploads/bets/';
$lockedDir = __DIR__ . '/uploads/locked/';
if (!is_dir($betsDir))   @mkdir($betsDir, 0755, true);
if (!is_dir($lockedDir)) @mkdir($lockedDir, 0755, true);

$restored = 0;
$errors   = 0;

try {
    $db   = getDB();
    $bets = $db->query("SELECT id, image_path, locked_image_path, image_data, locked_image_data FROM bets")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bets as $b) {
        if (!empty($b['image_path']) && !empty($b['image_data'])) {
            $fp = __DIR__ . '/' . ltrim($b['image_path'], '/');
            if (!file_exists($fp)) {
                $dir = dirname($fp);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (@file_put_contents($fp, $b['image_data']) !== false) {
                    $restored++;
                } else {
                    $errors++;
                }
            }
        }
        if (!empty($b['locked_image_path']) && !empty($b['locked_image_data'])) {
            $fp = __DIR__ . '/' . ltrim($b['locked_image_path'], '/');
            if (!file_exists($fp)) {
                $dir = dirname($fp);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (@file_put_contents($fp, $b['locked_image_data']) !== false) {
                    $restored++;
                } else {
                    $errors++;
                }
            }
        }
    }
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'restored' => $restored,
    'errors'   => $errors,
    'status'   => 'ok'
]);
