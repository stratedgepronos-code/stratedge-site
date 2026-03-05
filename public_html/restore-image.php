<?php
/**
 * StratEdge — Auto-restauration d'images depuis la BDD.
 * Si un fichier uploads/ est manquant sur disque, ce script le restaure
 * depuis la colonne MEDIUMBLOB et le sert au navigateur.
 * Appelé automatiquement via .htaccess (RewriteRule).
 */
require_once __DIR__ . '/includes/db.php';

$dir  = $_GET['dir']  ?? '';
$file = $_GET['file'] ?? '';

if (!in_array($dir, ['bets', 'locked', 'avatars', 'tickets'], true)) {
    http_response_code(404);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_.\-]+\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
    http_response_code(404);
    exit;
}

$relativePath = 'uploads/' . $dir . '/' . $file;
$fullPath     = __DIR__ . '/' . $relativePath;

if (file_exists($fullPath)) {
    $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=2592000');
    readfile($fullPath);
    exit;
}

$imageData = null;
try {
    $db = getDB();
    $fileLike = '%' . $file;
    if ($dir === 'bets') {
        $stmt = $db->prepare("SELECT image_data FROM bets WHERE (image_path = ? OR image_path LIKE ? OR image_path LIKE ?) AND image_data IS NOT NULL AND LENGTH(image_data) > 0 LIMIT 1");
        $stmt->execute([$relativePath, '%/' . $file, $fileLike]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['image_data'])) $imageData = $row['image_data'];
    } elseif ($dir === 'locked') {
        $stmt = $db->prepare("SELECT locked_image_data FROM bets WHERE (locked_image_path = ? OR locked_image_path LIKE ? OR locked_image_path LIKE ?) AND locked_image_data IS NOT NULL AND LENGTH(locked_image_data) > 0 LIMIT 1");
        $stmt->execute([$relativePath, '%/' . $file, $fileLike]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['locked_image_data'])) $imageData = $row['locked_image_data'];
    }
} catch (Throwable $e) {
    error_log('[restore-image] ' . $e->getMessage());
}

if ($imageData) {
    $dirPath = dirname($fullPath);
    if (!is_dir($dirPath)) @mkdir($dirPath, 0755, true);
    @file_put_contents($fullPath, $imageData);

    $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: public, max-age=2592000');
    echo $imageData;
    exit;
}

http_response_code(404);
