<?php
/**
 * StratEdge — Restauration / Upload d'images en masse.
 * Permet de ré-uploader les images manquantes via le navigateur
 * ET de sauvegarder automatiquement les binaires en BDD.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
if ($_SESSION['membre_email'] !== ADMIN_EMAIL) { header('Location: index.php'); exit; }

$db = getDB();
$msg = '';
$msgType = '';

// Créer les dossiers si besoin
$betsDir   = __DIR__ . '/../uploads/bets/';
$lockedDir = __DIR__ . '/../uploads/locked/';
if (!is_dir($betsDir))   @mkdir($betsDir, 0755, true);
if (!is_dir($lockedDir)) @mkdir($lockedDir, 0755, true);

// ── ACTION : Upload de fichiers images ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $files   = $_FILES['images'];
    $total   = is_array($files['name']) ? count($files['name']) : 0;
    $ok      = 0;
    $savedDb = 0;

    for ($i = 0; $i < $total; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $name = basename($files['name'][$i]);
        if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $name)) continue;

        $isLocked = (strpos($name, 'locked_') === 0);
        $destDir  = $isLocked ? $lockedDir : $betsDir;
        $subDir   = $isLocked ? 'locked' : 'bets';

        if (move_uploaded_file($files['tmp_name'][$i], $destDir . $name)) {
            $ok++;
            $relativePath = 'uploads/' . $subDir . '/' . $name;
            $blob = @file_get_contents($destDir . $name);
            if ($blob) {
                $col     = $isLocked ? 'locked_image_data' : 'image_data';
                $pathCol = $isLocked ? 'locked_image_path' : 'image_path';
                try {
                    $stmt = $db->prepare("UPDATE bets SET $col = ? WHERE $pathCol = ? OR $pathCol LIKE ?");
                    $stmt->execute([$blob, $relativePath, '%' . $name]);
                    if ($stmt->rowCount() > 0) $savedDb++;
                } catch (Throwable $e) {
                    error_log('[upload-restore] DB save error: ' . $e->getMessage());
                }
            }
        }
    }
    $msg = "$ok image(s) uploadée(s), $savedDb sauvegardée(s) en BDD.";
    $msgType = ($ok > 0) ? 'success' : 'error';
}

// ── ACTION : Sauvegarder les fichiers existants sur disque vers la BDD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_to_db'])) {
    $saved = 0;
    $bets = $db->query("SELECT id, image_path, locked_image_path FROM bets")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bets as $b) {
        if (!empty($b['image_path'])) {
            $fp = __DIR__ . '/../' . ltrim($b['image_path'], '/');
            if (file_exists($fp)) {
                $blob = file_get_contents($fp);
                if ($blob) {
                    try {
                        $db->prepare("UPDATE bets SET image_data = ? WHERE id = ?")->execute([$blob, $b['id']]);
                        $saved++;
                    } catch (Throwable $e) { error_log('[backup-db] ' . $e->getMessage()); }
                }
            }
        }
        if (!empty($b['locked_image_path'])) {
            $fp = __DIR__ . '/../' . ltrim($b['locked_image_path'], '/');
            if (file_exists($fp)) {
                $blob = file_get_contents($fp);
                if ($blob) {
                    try {
                        $db->prepare("UPDATE bets SET locked_image_data = ? WHERE id = ?")->execute([$blob, $b['id']]);
                        $saved++;
                    } catch (Throwable $e) { error_log('[backup-db] ' . $e->getMessage()); }
                }
            }
        }
    }
    $msg = "$saved image(s) sauvegardée(s) en BDD depuis le disque.";
    $msgType = 'success';
}

// ── ACTION : Restaurer tous les fichiers depuis la BDD vers le disque ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_disk'])) {
    $restored = 0;
    try {
        $bets = $db->query("SELECT id, image_path, locked_image_path, image_data, locked_image_data FROM bets")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $bets = [];
        error_log('[upload-restore] restore_disk SELECT: ' . $e->getMessage());
    }
    foreach ($bets as $b) {
        if (!empty($b['image_path']) && !empty($b['image_data'])) {
            $fp = __DIR__ . '/../' . ltrim($b['image_path'], '/');
            $dir = dirname($fp);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (@file_put_contents($fp, $b['image_data']) !== false) $restored++;
        }
        if (!empty($b['locked_image_path']) && !empty($b['locked_image_data'])) {
            $fp = __DIR__ . '/../' . ltrim($b['locked_image_path'], '/');
            $dir = dirname($fp);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (@file_put_contents($fp, $b['locked_image_data']) !== false) $restored++;
        }
    }
    $msg = "$restored fichier(s) restauré(s) sur le disque depuis la BDD.";
    $msgType = 'success';
}

// ── ÉTAT : images en BDD vs sur disque ──
try {
    $betsAll = $db->query("SELECT id, titre, image_path, locked_image_path, 
        (image_data IS NOT NULL AND LENGTH(image_data)>0) as has_blob, 
        (locked_image_data IS NOT NULL AND LENGTH(locked_image_data)>0) as has_locked_blob 
        FROM bets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $betsAll = $db->query("SELECT id, titre, image_path, locked_image_path FROM bets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($betsAll as &$b) { $b['has_blob'] = 0; $b['has_locked_blob'] = 0; }
    unset($b);
}

$stats = ['total' => 0, 'disk_ok' => 0, 'disk_miss' => 0, 'db_ok' => 0, 'db_miss' => 0];
foreach ($betsAll as $b) {
    if (!empty($b['image_path'])) {
        $stats['total']++;
        $fp = $betsDir . basename(str_replace('\\', '/', $b['image_path']));
        file_exists($fp) ? $stats['disk_ok']++ : $stats['disk_miss']++;
        $b['has_blob'] ? $stats['db_ok']++ : $stats['db_miss']++;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restauration Images - StratEdge</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#0d1117;color:#e6edf3;padding:20px}
h1{color:#58a6ff;margin-bottom:20px}
.card{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px;margin-bottom:20px}
.card h2{color:#79c0ff;margin-bottom:12px;font-size:1.1rem}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px}
.stat{background:#0d1117;border-radius:8px;padding:12px;text-align:center}
.stat .num{font-size:1.8rem;font-weight:700}
.stat .label{font-size:.8rem;color:#8b949e;margin-top:4px}
.ok .num{color:#3fb950} .warn .num{color:#d29922} .miss .num{color:#f85149}
.msg{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:600}
.msg.success{background:#0d2818;border:1px solid #3fb950;color:#3fb950}
.msg.error{background:#2d0f0f;border:1px solid #f85149;color:#f85149}
.drop-zone{border:2px dashed #30363d;border-radius:12px;padding:40px;text-align:center;cursor:pointer;transition:.3s}
.drop-zone:hover,.drop-zone.over{border-color:#58a6ff;background:rgba(88,166,255,.05)}
.drop-zone p{color:#8b949e;margin-top:8px}
input[type=file]{display:none}
.btn{display:inline-block;padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:.9rem;margin-top:10px;transition:.2s}
.btn-blue{background:#1f6feb;color:#fff}.btn-blue:hover{background:#388bfd}
.btn-green{background:#238636;color:#fff}.btn-green:hover{background:#2ea043}
.btn-back{background:#30363d;color:#c9d1d9;text-decoration:none;display:inline-block;margin-bottom:16px;padding:8px 16px;border-radius:6px}
.btn-back:hover{background:#484f58}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:8px 10px;text-align:left;border-bottom:1px solid #21262d;font-size:.85rem}
th{color:#8b949e;font-weight:600}
.tag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600}
.tag-ok{background:#0d2818;color:#3fb950}
.tag-miss{background:#2d0f0f;color:#f85149}
</style>
</head>
<body>
<a href="poster-bet.php" class="btn-back">&larr; Retour</a>
<h1>Restauration des images</h1>

<div class="card" style="border-color:#d29922;background:rgba(210,153,34,0.08);margin-bottom:20px;">
<strong>⚠️ Si les images disparaissent après un déploiement</strong> : 1) Exécuter <code>migrate.sql</code> dans phpMyAdmin (colonnes <code>image_data</code>, <code>locked_image_data</code>). 2) Ici : soit ré-uploader les images puis « Sauvegarder tout en BDD », soit cliquer « Restaurer tout BDD → disque » si la BDD contient déjà les images. Les URLs du site passent par <code>restore-image.php</code> pour servir depuis la BDD si le fichier manque.
</div>

<?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
<h2>Etat actuel</h2>
<div class="stats">
    <div class="stat"><div class="num"><?= $stats['total'] ?></div><div class="label">Images totales</div></div>
    <div class="stat ok"><div class="num"><?= $stats['disk_ok'] ?></div><div class="label">Sur disque</div></div>
    <div class="stat miss"><div class="num"><?= $stats['disk_miss'] ?></div><div class="label">Manquantes disque</div></div>
    <div class="stat ok"><div class="num"><?= $stats['db_ok'] ?></div><div class="label">Sauvegardées BDD</div></div>
    <div class="stat warn"><div class="num"><?= $stats['db_miss'] ?></div><div class="label">Pas en BDD</div></div>
</div>
</div>

<div class="card">
<h2>1. Uploader des images (drag & drop ou clic)</h2>
<p style="color:#8b949e;margin-bottom:12px;font-size:.85rem">
    Les fichiers sont automatiquement placés dans <code>bets/</code> ou <code>locked/</code> selon leur nom (préfixe "locked_").
    Ils sont aussi sauvegardés en base pour résister aux futurs déploiements.
</p>
<form method="post" enctype="multipart/form-data" id="uploadForm">
    <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
        <p style="font-size:1.2rem;color:#58a6ff">Glisser-déposer vos images ici</p>
        <p>ou cliquer pour parcourir</p>
        <p id="fileCount" style="color:#3fb950;margin-top:10px;display:none"></p>
    </div>
    <input type="file" name="images[]" id="fileInput" multiple accept="image/*">
    <button type="submit" class="btn btn-blue" id="uploadBtn" style="display:none">Uploader</button>
</form>
</div>

<div class="card">
<h2>2. Sauvegarder les images du disque vers la BDD</h2>
<p style="color:#8b949e;margin-bottom:12px;font-size:.85rem">
    Si les images sont sur le serveur mais pas encore en BDD, ce bouton les copie dans la base de données.
</p>
<form method="post">
    <input type="hidden" name="backup_to_db" value="1">
    <button type="submit" class="btn btn-green">Sauvegarder tout en BDD</button>
</form>
</div>

<div class="card">
<h2>3. Restaurer le disque depuis la BDD (après un déploiement)</h2>
<p style="color:#8b949e;margin-bottom:12px;font-size:.85rem">
    Si les images ont disparu du serveur après un git pull, cliquez ici : les fichiers seront recréés à partir des sauvegardes en base.
</p>
<form method="post">
    <input type="hidden" name="restore_disk" value="1">
    <button type="submit" class="btn btn-blue">Restaurer tout BDD → disque</button>
</form>
</div>

<div class="card">
<h2>Détail par bet</h2>
<table>
<tr><th>ID</th><th>Titre</th><th>Disque</th><th>BDD</th><th>Locked disque</th><th>Locked BDD</th></tr>
<?php foreach ($betsAll as $b):
    $imgPath    = '';
    $lockPath   = '';
    if (!empty($b['image_path'])) {
        $imgPath = $betsDir . basename(str_replace('\\', '/', $b['image_path']));
    }
    if (!empty($b['locked_image_path'])) {
        $lockPath = $lockedDir . basename(str_replace('\\', '/', $b['locked_image_path']));
    }
    $diskOk     = $imgPath !== '' && file_exists($imgPath);
    $diskLockOk = $lockPath !== '' && file_exists($lockPath);
?>
<tr>
    <td><?= $b['id'] ?></td>
    <td><?= htmlspecialchars(mb_substr($b['titre'] ?? '', 0, 40)) ?></td>
    <td><span class="tag <?= $imgPath ? ($diskOk ? 'tag-ok' : 'tag-miss') : '' ?>"><?= $imgPath ? ($diskOk ? 'OK' : 'MANQUANT') : '-' ?></span></td>
    <td><span class="tag <?= !empty($b['image_path']) ? ($b['has_blob'] ? 'tag-ok' : 'tag-miss') : '' ?>"><?= !empty($b['image_path']) ? ($b['has_blob'] ? 'OK' : 'MANQUANT') : '-' ?></span></td>
    <td><span class="tag <?= $lockPath ? ($diskLockOk ? 'tag-ok' : 'tag-miss') : '' ?>"><?= $lockPath ? ($diskLockOk ? 'OK' : 'MANQUANT') : '-' ?></span></td>
    <td><span class="tag <?= !empty($b['locked_image_path']) ? ($b['has_locked_blob'] ? 'tag-ok' : 'tag-miss') : '' ?>"><?= !empty($b['locked_image_path']) ? ($b['has_locked_blob'] ? 'OK' : 'MANQUANT') : '-' ?></span></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<script>
const dz = document.getElementById('dropZone');
const fi = document.getElementById('fileInput');
const fc = document.getElementById('fileCount');
const ub = document.getElementById('uploadBtn');
const form = document.getElementById('uploadForm');

['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('over'); }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('over'); }));

dz.addEventListener('drop', e => {
    fi.files = e.dataTransfer.files;
    updateCount();
});
fi.addEventListener('change', updateCount);

function updateCount() {
    const n = fi.files.length;
    if (n > 0) {
        fc.textContent = n + ' fichier(s) sélectionné(s)';
        fc.style.display = 'block';
        ub.style.display = 'inline-block';
    }
}
</script>
</body>
</html>
