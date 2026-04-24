<?php
// ============================================================
// STRATEDGE — Poster un bet depuis Créer une Card (flux auto)
// Reçoit : image, locked_image, titre, type, sport, analyse_html, cote, etc.
// ============================================================
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'post_from_card') {
    echo json_encode(['success' => false, 'error' => 'Requête invalide.']);
    exit;
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Erreur de sécurité (CSRF).']);
    exit;
}

$image      = $_FILES['image'] ?? null;
$lockedImage = $_FILES['locked_image'] ?? null;
$titre      = trim((string)($_POST['titre'] ?? ''));
$type       = in_array($_POST['type'] ?? '', ['safe','live','fun']) ? $_POST['type'] : 'safe';
$description = trim((string)($_POST['description'] ?? ''));
$categorie  = ($_POST['categorie'] ?? '') === 'tennis' ? 'tennis' : 'multi';
$sport      = in_array($_POST['sport'] ?? '', ['tennis','football','basket','hockey','baseball']) ? $_POST['sport'] : 'football';
$analyseHtml = trim((string)($_POST['analyse_html'] ?? ''));
$coteRaw    = trim((string)($_POST['cote'] ?? ''));

$adminRole = getAdminRole();
$isSuperAdmin = isSuperAdmin();
if ($adminRole === 'admin_fun_sport' || $adminRole === 'admin_fun') {
    // Admin Fun: tous types, sports = foot/basket/hockey/baseball uniquement (jamais tennis)
    $sport = in_array($sport, ['football','basket','hockey','baseball']) ? $sport : 'football';
    $categorie = 'multi';
} elseif ($adminRole === 'admin_tennis') {
    $categorie = 'tennis';
    $sport = 'tennis';
} elseif (!$isSuperAdmin) {
    $categorie = 'tennis';
}

// Sécurité : retirer script/iframe de l'analyse HTML
if ($analyseHtml !== '') {
    $analyseHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $analyseHtml);
    $analyseHtml = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $analyseHtml);
}
$cote = ($coteRaw !== '' && is_numeric($coteRaw)) ? number_format((float)$coteRaw, 2, '.', '') : null;

if (!$image || $image['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Image normale requise.']);
    exit;
}
$ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
    echo json_encode(['success' => false, 'error' => 'Format image non autorisé.']);
    exit;
}
if ($image['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image trop volumineuse.']);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($image['tmp_name']);
$allowedMimes = ['image/jpeg','image/png','image/webp','image/gif'];
if (!in_array($mimeReal, $allowedMimes)) {
    echo json_encode(['success' => false, 'error' => 'Type MIME image non autorisé.']);
    exit;
}

$uploadDir  = __DIR__ . '/../uploads/bets/';
$lockedDir  = __DIR__ . '/../uploads/locked/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
if (!is_dir($lockedDir)) @mkdir($lockedDir, 0755, true);
if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Dossier uploads/bets/ non accessible en écriture.']);
    exit;
}

$filename = 'bet_' . time() . '_0_' . bin2hex(random_bytes(3)) . '.' . $ext;
if (!move_uploaded_file($image['tmp_name'], $uploadDir . $filename)) {
    echo json_encode(['success' => false, 'error' => 'Impossible de sauver l\'image.']);
    exit;
}
$lastImagePath = 'uploads/bets/' . $filename;
$imageBlob = @file_get_contents($uploadDir . $filename);

$lastLockedPath = '';
$lockedBlob = null;
if ($lockedImage && $lockedImage['error'] === UPLOAD_ERR_OK) {
    $lext = strtolower(pathinfo($lockedImage['name'], PATHINFO_EXTENSION));
    if (in_array($lext, ['jpg','jpeg','png','webp','gif']) && $lockedImage['size'] <= 10 * 1024 * 1024) {
        $lmime = $finfo->file($lockedImage['tmp_name']);
        if (in_array($lmime, $allowedMimes)) {
            $lname = 'locked_' . time() . '_0_' . bin2hex(random_bytes(3)) . '.' . $lext;
            if (move_uploaded_file($lockedImage['tmp_name'], $lockedDir . $lname)) {
                $lastLockedPath = 'uploads/locked/' . $lname;
                $lockedBlob = @file_get_contents($lockedDir . $lname);
            }
        }
    }
}

$titre = $titre !== '' ? $titre : 'Bet StratEdge';

// Logger pour débugger si les bets ne s'insèrent pas
$logFile = __DIR__ . '/../logs/poster-bet-from-card.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logCtx = sprintf(
    "[%s] role=%s cat=%s type=%s sport=%s titre=%s cote=%s",
    date('Y-m-d H:i:s'),
    $adminRole ?: 'super',
    $categorie, $type, $sport,
    mb_substr($titre, 0, 40),
    $cote ?? '-'
);

try {
    // Force actif = 1 explicitement (ne dépend pas du DEFAULT de la colonne)
    $stmt = $db->prepare("INSERT INTO bets (titre, image_path, image_data, locked_image_path, locked_image_data, type, categorie, sport, description, analyse_html, cote, actif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$titre, $lastImagePath, $imageBlob ?: null, $lastLockedPath ?: null, $lockedBlob ?: null, $type, $categorie, $sport, $description, $analyseHtml ?: null, $cote]);
    @file_put_contents($logFile, "$logCtx OK_V1 id=" . $db->lastInsertId() . "\n", FILE_APPEND);
} catch (Throwable $e) {
    @file_put_contents($logFile, "$logCtx FAIL_V1: " . mb_substr($e->getMessage(), 0, 200) . "\n", FILE_APPEND);
    try {
        $stmt = $db->prepare("INSERT INTO bets (titre, image_path, image_data, locked_image_path, locked_image_data, type, categorie, sport, description, actif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$titre, $lastImagePath, $imageBlob ?: null, $lastLockedPath ?: null, $lockedBlob ?: null, $type, $categorie, $sport, $description]);
        @file_put_contents($logFile, "$logCtx OK_V2 id=" . $db->lastInsertId() . "\n", FILE_APPEND);
    } catch (Throwable $e2) {
        @file_put_contents($logFile, "$logCtx FAIL_V2: " . mb_substr($e2->getMessage(), 0, 200) . "\n", FILE_APPEND);
        try {
            $stmt = $db->prepare("INSERT INTO bets (titre, image_path, locked_image_path, type, categorie, sport, description, actif) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$titre, $lastImagePath, $lastLockedPath ?: null, $type, $categorie, $sport, $description]);
            @file_put_contents($logFile, "$logCtx OK_V3 id=" . $db->lastInsertId() . "\n", FILE_APPEND);
        } catch (Throwable $e3) {
            @file_put_contents($logFile, "$logCtx FAIL_V3: " . mb_substr($e3->getMessage(), 0, 200) . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => 'Erreur BDD : ' . $e3->getMessage()]);
            exit;
        }
    }
}

// Tag posted_by_role selon le role de l'admin qui poste (pour routage tipster cote membre)
try {
    $betId = $db->lastInsertId();
    if ($betId) {
        $roleToTag = 'superadmin'; // default
        if ($adminRole === 'admin_tennis') $roleToTag = 'admin_tennis';
        elseif ($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport') $roleToTag = 'admin_fun';
        $db->prepare("UPDATE bets SET posted_by_role=? WHERE id=?")->execute([$roleToTag, $betId]);
    }
} catch (Throwable $e) { /* silencieux si colonne pas encore migrée */ }

// Twitter (IFTTT) si configuré
$twitterMsg = '';
$twitterConfigFile = __DIR__ . '/../includes/twitter_keys.php';
if (file_exists($twitterConfigFile)) {
    $twitterConfig = include $twitterConfigFile;
    if (!empty($twitterConfig['actif']) && !empty($twitterConfig['webhook_url'])) {
        require_once __DIR__ . '/../includes/twitter.php';
        $imageChoisie = $lastLockedPath ?: $lastImagePath;
        $imageDir = (strpos($imageChoisie, 'locked') !== false) ? 'locked' : 'bets';
        $imageUrl = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/restore-image.php?dir=' . rawurlencode($imageDir) . '&file=' . rawurlencode(basename($imageChoisie));
        // Determine role pour hashtags (variable $adminRole existante en haut du fichier)
        $roleForTweet = ($adminRole === 'admin_tennis') ? 'admin_tennis'
                      : (($adminRole === 'admin_fun' || $adminRole === 'admin_fun_sport') ? 'admin_fun' : 'superadmin');
        $texte = twitterPhrase($type, $titre, $roleForTweet);
        $webhookUrl = !empty($twitterConfig['webhook_url_image']) ? $twitterConfig['webhook_url_image'] : $twitterConfig['webhook_url'];
        $payload = json_encode([
            'value1' => $texte,
            'value2' => 'StratEdge Pronos',
            'value3' => !empty($twitterConfig['webhook_url_image']) ? $imageUrl : '',
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

// Notifications : file d'attente + traitement par paquets (évite timeout)
try {
    require_once __DIR__ . '/../includes/notif-queue.php';
    $cat = ($categorie === 'tennis') ? 'tennis' : 'multi';
    $batch = notifQueueEnqueueNouveauBet($db, $cat, $type, $titre);
    for ($r = 0; $r < 30; $r++) {
        $st = notifQueueProcessBatch($db, 80);
        if ($st['processed'] === 0) {
            break;
        }
    }
} catch (Throwable $e) {
    error_log('[poster-bet-from-card] notif-queue: ' . $e->getMessage());
}

echo json_encode(['success' => true]);
