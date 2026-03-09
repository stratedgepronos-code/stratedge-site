<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageActive = 'historique';

$success = '';
$error   = '';

// ── Traitement POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $betId   = (int)($_POST['bet_id'] ?? 0);
    $champ   = $_POST['champ'] ?? 'image_path'; // image_path ou locked_image_path
    if (!in_array($champ, ['image_path', 'locked_image_path'])) $champ = 'image_path';

    $bet = $db->prepare("SELECT * FROM bets WHERE id = ?")->execute([$betId]) ? null : null;
    $stmt = $db->prepare("SELECT * FROM bets WHERE id = ?");
    $stmt->execute([$betId]);
    $bet = $stmt->fetch();

    if (!$bet) {
        $error = "Bet #$betId introuvable.";
    } elseif (empty($_FILES['nouvelle_image']['tmp_name']) || $_FILES['nouvelle_image']['error'] !== UPLOAD_ERR_OK) {
        $error = "Aucun fichier sélectionné ou erreur d'upload.";
    } else {
        $file = $_FILES['nouvelle_image'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $error = "Format non autorisé. Utilise JPG, PNG, WEBP ou GIF.";
        } elseif ($file['size'] > 15 * 1024 * 1024) {
            $error = "Fichier trop lourd (max 15 Mo).";
        } else {
            $dir = ($champ === 'locked_image_path')
                 ? __DIR__ . '/../uploads/locked/'
                 : __DIR__ . '/../uploads/bets/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            // Supprimer l'ancienne image
            if (!empty($bet[$champ])) {
                $oldPath = __DIR__ . '/../' . $bet[$champ];
                if (file_exists($oldPath)) @unlink($oldPath);
            }

            $prefix   = ($champ === 'locked_image_path') ? 'locked_' : 'bet_';
            $filename = $prefix . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $newPath = ($champ === 'locked_image_path' ? 'uploads/locked/' : 'uploads/bets/') . $filename;
                $db->prepare("UPDATE bets SET $champ = ? WHERE id = ?")->execute([$newPath, $betId]);
                $success = "Image mise à jour pour le bet #$betId (" . ($champ === 'locked_image_path' ? 'version locked' : 'version normale') . ").";
            } else {
                $error = "Échec du déplacement du fichier. Vérifiez les permissions.";
            }
        }
    }
}

// ── Charger tous les bets (avec ou sans résultat) ──────────
$bets = $db->query("
    SELECT id, titre, type, image_path, locked_image_path, resultat, date_post
    FROM bets
    ORDER BY date_post DESC
    LIMIT 100
")->fetchAll();

$typeLabels = ['safe'=>'🛡️ Safe','fun'=>'🎯 Fun','live'=>'⚡ Live','safe,fun'=>'Safe+Fun','safe,live'=>'Safe+Live','rasstoss'=>'👑 Rass-Toss'];
$resultatLabels = ['en_cours'=>'⏳ En cours','gagne'=>'✅ Gagné','perdu'=>'❌ Perdu','annule'=>'↺ Annulé'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Modifier image bet — StratEdge Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,255,255,0.07);--border-pink:rgba(255,45,120,0.2);}
    *{box-sizing:border-box;margin:0;padding:0;}
    html,body{overflow-x:hidden!important;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;}
    <?php require_once __DIR__ . '/sidebar.php'; ?>
  </style>
  <style>
    .main{padding:2rem;}
    .page-header{margin-bottom:2rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:900;color:#fff;}
    .page-header p{color:var(--text-muted);font-size:0.9rem;margin-top:0.4rem;}
    .alert{padding:0.9rem 1.2rem;border-radius:10px;margin-bottom:1.5rem;font-weight:600;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.3);color:#00c864;}
    .alert-error{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.3);color:#ff6b9d;}
    .bets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.2rem;}
    .bet-card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;overflow:hidden;transition:border-color .2s;}
    .bet-card:hover{border-color:var(--border-pink);}
    .bet-thumb{position:relative;height:150px;background:#0a0e17;cursor:zoom-in;overflow:hidden;}
    .bet-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s;}
    .bet-thumb:hover img{transform:scale(1.03);}
    .bet-thumb .no-img{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--text-muted);}
    .bet-thumb .result-badge{position:absolute;top:8px;right:8px;font-size:1.3rem;filter:drop-shadow(0 0 5px rgba(0,0,0,0.8));}
    .bet-body{padding:0.9rem;}
    .bet-meta{display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;}
    .bet-type{font-size:0.72rem;background:rgba(255,45,120,0.1);color:#ff2d78;border:1px solid rgba(255,45,120,0.2);padding:0.15rem 0.5rem;border-radius:5px;}
    .bet-date{font-size:0.72rem;color:var(--text-muted);}
    .bet-titre{font-size:0.88rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .btn-edit{display:flex;align-items:center;gap:0.4rem;background:rgba(255,45,120,0.08);border:1px solid var(--border-pink);color:#ff2d78;padding:0.5rem 0.8rem;border-radius:8px;font-size:0.82rem;font-weight:700;cursor:pointer;transition:all .2s;width:100%;justify-content:center;font-family:'Rajdhani',sans-serif;}
    .btn-edit:hover{background:rgba(255,45,120,0.18);transform:translateY(-1px);}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--bg-card);border:1px solid var(--border-pink);border-radius:16px;padding:1.8rem;width:90%;max-width:480px;position:relative;}
    .modal-title{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#ff2d78;margin-bottom:1.2rem;}
    .form-group{margin-bottom:1rem;}
    .form-group label{display:block;font-size:0.85rem;color:var(--text-muted);margin-bottom:0.4rem;font-weight:600;}
    .form-select{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border-subtle);color:var(--text-primary);padding:0.7rem 0.9rem;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:0.9rem;}
    .drop-zone{border:2px dashed var(--border-pink);border-radius:10px;padding:2rem;text-align:center;cursor:pointer;transition:all .2s;background:rgba(255,45,120,0.03);}
    .drop-zone:hover,.drop-zone.drag{border-color:#ff2d78;background:rgba(255,45,120,0.08);}
    .drop-zone input{display:none;}
    .drop-zone .icon{font-size:2rem;margin-bottom:0.5rem;}
    .drop-zone .label{font-size:0.88rem;color:var(--text-secondary);}
    .drop-zone .sub{font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;}
    .preview-img{max-width:100%;max-height:200px;border-radius:8px;margin-top:0.8rem;display:none;}
    .modal-actions{display:flex;gap:0.8rem;margin-top:1.5rem;}
    .btn-submit{flex:1;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;border:none;padding:0.85rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,45,120,0.4);}
    .btn-cancel{background:rgba(255,255,255,0.04);border:1px solid var(--border-subtle);color:var(--text-muted);padding:0.85rem 1.2rem;border-radius:10px;cursor:pointer;font-family:'Rajdhani',sans-serif;font-weight:700;transition:all .2s;}
    .btn-cancel:hover{background:rgba(255,255,255,0.08);}
    .search-bar{margin-bottom:1.5rem;}
    .search-bar input{width:100%;background:var(--bg-card);border:1px solid var(--border-subtle);color:var(--text-primary);padding:0.8rem 1rem;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:0.95rem;}
    .search-bar input:focus{outline:none;border-color:var(--border-pink);}

    @media(max-width:768px){
      .main{margin-left:0!important;width:100%!important;max-width:100vw!important;padding:0.8rem!important;padding-top:62px!important;padding-bottom:calc(78px + env(safe-area-inset-bottom,0px))!important;box-sizing:border-box!important;}
      .page-header h1{font-size:1.1rem;}
      .page-header p{font-size:0.82rem;word-wrap:break-word;}
      .bets-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.8rem;}
      .bet-thumb{height:110px;}
      .bet-body{padding:0.6rem;}
      .bet-meta{flex-direction:column;align-items:flex-start;gap:0.2rem;}
      .bet-titre{font-size:0.8rem;margin-bottom:0.5rem;}
      .btn-edit{font-size:0.75rem;padding:0.45rem 0.5rem;min-height:40px;}
      .alert{font-size:0.85rem;padding:0.7rem 0.9rem;}
      .search-bar input{font-size:0.9rem;padding:0.7rem 0.8rem;}
      .modal{padding:1.2rem;width:95%;border-radius:12px;}
      .modal-title{font-size:0.85rem;margin-bottom:0.8rem;}
      .drop-zone{padding:1.2rem;}
      .drop-zone .icon{font-size:1.6rem;}
      .drop-zone .label{font-size:0.82rem;}
      .drop-zone .sub{font-size:0.7rem;}
      .modal-actions{flex-direction:column;gap:0.6rem;}
      .btn-submit,.btn-cancel{width:100%;min-height:46px;text-align:center;display:flex;align-items:center;justify-content:center;}
      .preview-img{max-height:150px;}
    }
    @media(max-width:400px){
      .main{padding:0.5rem!important;padding-top:58px!important;padding-bottom:calc(72px + env(safe-area-inset-bottom,0px))!important;}
      .bets-grid{grid-template-columns:1fr 1fr;gap:0.5rem;}
      .bet-thumb{height:90px;}
      .bet-body{padding:0.5rem;}
      .bet-titre{font-size:0.75rem;}
      .btn-edit{font-size:0.7rem;padding:0.4rem;}
      .page-header h1{font-size:1rem;}
    }
  </style>
</head>
<body>

<?php /* sidebar.php déjà affiché plus haut via require */ ?>

<div class="main">

  <div class="page-header">
    <h1>🖼️ Modifier l'image d'un bet</h1>
    <p>Change l'image normale ou locked d'un bet déjà posté — sans envoyer de notification.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="search-bar">
    <input type="text" id="searchInput" placeholder="🔍 Rechercher par titre ou date..." oninput="filterBets()">
  </div>

  <div class="bets-grid" id="betsGrid">
    <?php foreach ($bets as $b):
      $imgSrc = !empty($b['image_path']) ? betImageUrl($b['image_path']) : '';
      $lockedSrc = !empty($b['locked_image_path']) ? betImageUrl($b['locked_image_path'], 'locked') : '';
      $resLabel = $resultatLabels[$b['resultat']] ?? $b['resultat'];
      $typeLabel = $typeLabels[$b['type']] ?? $b['type'];
    ?>
    <div class="bet-card" data-search="<?= strtolower(htmlspecialchars($b['titre'] . ' ' . $b['date_post'])) ?>">
      <div class="bet-thumb" onclick="openLightbox('<?= htmlspecialchars($imgSrc ?: '', ENT_QUOTES, 'UTF-8') ?>')">
        <?php if ($imgSrc): ?>
          <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Bet" loading="lazy" onerror="this.onerror=null;this.parentNode.innerHTML='<div class=\'no-img\'>📊</div>';">
        <?php else: ?>
          <div class="no-img">📊</div>
        <?php endif; ?>
        <div class="result-badge"><?= explode(' ', $resLabel)[0] ?></div>
      </div>
      <div class="bet-body">
        <div class="bet-meta">
          <span class="bet-type"><?= $typeLabel ?></span>
          <span class="bet-date"><?= date('d/m/Y', strtotime($b['date_post'])) ?></span>
        </div>
        <div class="bet-titre"><?= htmlspecialchars($b['titre'] ?: 'Sans titre') ?></div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;">
          <button class="btn-edit" onclick="openModal(<?= $b['id'] ?>, 'image_path', '<?= htmlspecialchars($b['titre'] ?: 'Bet #' . $b['id']) ?>')">
            📸 Changer image normale
          </button>
          <?php if ($lockedSrc || true): ?>
          <button class="btn-edit" onclick="openModal(<?= $b['id'] ?>, 'locked_image_path', '<?= htmlspecialchars($b['titre'] ?: 'Bet #' . $b['id']) ?>')" style="background:rgba(255,193,7,0.07);border-color:rgba(255,193,7,0.25);color:#ffc107;">
            🔒 Changer image locked
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-title" id="modalTitle">Modifier l'image</div>
    <form method="POST" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="bet_id" id="formBetId">
      <input type="hidden" name="champ" id="formChamp">

      <div class="form-group">
        <label>Nouvelle image (JPG, PNG, WEBP — max 15 Mo)</label>
        <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()" ondragover="event.preventDefault();this.classList.add('drag')" ondragleave="this.classList.remove('drag')" ondrop="handleDrop(event)">
          <div class="icon">📁</div>
          <div class="label">Cliquer ou glisser une image ici</div>
          <div class="sub">Remplace l'ancienne — aucune notif envoyée</div>
          <input type="file" name="nouvelle_image" id="fileInput" accept="image/*" onchange="previewFile(this)">
        </div>
        <img id="previewImg" class="preview-img" alt="Aperçu">
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn-submit">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- LIGHTBOX -->
<div id="lb" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:999;align-items:center;justify-content:center;" onclick="this.style.display='none'">
  <img id="lbImg" style="max-width:95vw;max-height:90vh;border-radius:10px;">
</div>

<script>
function openModal(id, champ, titre) {
  document.getElementById('formBetId').value = id;
  document.getElementById('formChamp').value = champ;
  document.getElementById('modalTitle').textContent = (champ === 'locked_image_path' ? '🔒 Image Locked — ' : '📸 Image Normale — ') + titre;
  document.getElementById('previewImg').style.display = 'none';
  document.getElementById('fileInput').value = '';
  document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}
function previewFile(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('previewImg');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
    document.querySelector('.drop-zone .label').textContent = input.files[0].name;
  }
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.remove('drag');
  const dt = e.dataTransfer;
  if (dt.files.length) {
    document.getElementById('fileInput').files = dt.files;
    previewFile(document.getElementById('fileInput'));
  }
}
function openLightbox(src) {
  if (!src) return;
  document.getElementById('lbImg').src = src;
  document.getElementById('lb').style.display = 'flex';
}
function filterBets() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('.bet-card').forEach(card => {
    card.style.display = card.dataset.search.includes(q) ? '' : 'none';
  });
}
// Fermer modal sur click overlay
document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

</body>
</html>
