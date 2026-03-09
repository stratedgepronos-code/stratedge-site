<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
requireAdmin();
$pageActive = 'tickets';

$db = getDB();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_ticket_msg') {
        $msgId    = (int)($_POST['msg_id'] ?? 0);
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($msgId) {
            // Supprimer l'image associée si elle existe
            $stmt = $db->prepare("SELECT contenu FROM ticket_messages WHERE id = ? AND auteur = 'admin'");
            $stmt->execute([$msgId]);
            $row = $stmt->fetch();
            if ($row && preg_match('/\[IMG:(uploads\/tickets\/[^\]]+)\]/', $row['contenu'], $m)) {
                $imgFile = __DIR__ . '/../' . $m[1];
                if (file_exists($imgFile)) unlink($imgFile);
            }
            $db->prepare("DELETE FROM ticket_messages WHERE id = ? AND auteur = 'admin'")->execute([$msgId]);
        }
        header('Location: tickets.php?ticket=' . $ticketId . '&deleted=1');
        exit;
    } elseif ($action === 'reply_ticket') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $contenu  = trim($_POST['contenu'] ?? '');
        $statut   = $_POST['statut'] ?? 'en_cours';
        // Gérer image collée/uploadée (base64 ou fichier)
        $imageUrl = '';
        $imageData = $_POST['image_data'] ?? '';
        if (!empty($imageData) && strpos($imageData, 'data:image/') === 0) {
            // Décoder le base64
            $matches = [];
            preg_match('/data:(image\/[a-z]+);base64,(.+)/', $imageData, $matches);
            if (count($matches) === 3) {
                $ext     = str_replace('image/', '', $matches[1]);
                $ext     = in_array($ext, ['jpeg','jpg','png','gif','webp']) ? $ext : 'png';
                $dir     = __DIR__ . '/../uploads/tickets/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname   = 'ticket_' . $ticketId . '_' . time() . '.' . $ext;
                file_put_contents($dir . $fname, base64_decode($matches[2]));
                $imageUrl = 'uploads/tickets/' . $fname;
            }
        }
        // Fichier uploadé
        if (empty($imageUrl) && !empty($_FILES['image_file']['tmp_name'])) {
            $dir = __DIR__ . '/../uploads/tickets/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $fname = 'ticket_' . $ticketId . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image_file']['tmp_name'], $dir . $fname);
                $imageUrl = 'uploads/tickets/' . $fname;
            }
        }
        // Construire le contenu final
        $contenuFinal = trim($contenu);
        if ($imageUrl) {
            $contenuFinal .= ($contenuFinal ? "\n" : '') . '[IMG:' . $imageUrl . ']';
        }

        if ($ticketId && (!empty($contenuFinal))) {
            $db->prepare("INSERT INTO ticket_messages (ticket_id, contenu, auteur) VALUES (?, ?, 'admin')")->execute([$ticketId, $contenuFinal]);
            $db->prepare("UPDATE tickets SET statut = ? WHERE id = ?")->execute([$statut, $ticketId]);
            // Notifier le membre par push + email
            $stmt_mem = $db->prepare("SELECT m.id, m.email, m.nom, t.sujet FROM membres m JOIN tickets t ON t.membre_id=m.id WHERE t.id=? LIMIT 1");
            $stmt_mem->execute([$ticketId]);
            $mem = $stmt_mem->fetch();
            if ($mem) {
                pushReponseTicket($mem['id'], $mem['sujet']);
                emailReponseTicket($mem['email'], $mem['nom'], $mem['sujet'], $contenuFinal);
            }
            $success = 'Réponse envoyée.';
        }
    } elseif ($action === 'change_statut') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $statut   = $_POST['statut'] ?? 'ouvert';
        $db->prepare("UPDATE tickets SET statut = ? WHERE id = ?")->execute([$statut, $ticketId]);
        $success = 'Statut mis à jour.';
    }
}

// Ticket détail
$ticketDetail = null;
$ticketMessages = [];
if (isset($_GET['ticket'])) {
    $tid = (int)$_GET['ticket'];
    $s = $db->prepare("SELECT t.*, m.nom, m.email FROM tickets t JOIN membres m ON t.membre_id=m.id WHERE t.id=?");
    $s->execute([$tid]);
    $ticketDetail = $s->fetch();
    if ($ticketDetail) {
        $s2 = $db->prepare("SELECT * FROM ticket_messages WHERE ticket_id=? ORDER BY date_envoi ASC");
        $s2->execute([$tid]);
        $ticketMessages = $s2->fetchAll();
    }
}

// Filtrage
$filtre = $_GET['filtre'] ?? 'actif';
if ($filtre === 'actif') {
    $tickets = $db->query("SELECT t.*,m.nom,m.email FROM tickets t JOIN membres m ON t.membre_id=m.id WHERE t.statut!='resolu' ORDER BY t.date_creation DESC")->fetchAll();
} else {
    $tickets = $db->query("SELECT t.*,m.nom,m.email FROM tickets t JOIN membres m ON t.membre_id=m.id ORDER BY t.date_creation DESC")->fetchAll();
}

$statutColors = ['ouvert'=>['#ffc107','rgba(255,200,0,0.1)','rgba(255,200,0,0.3)'],'en_cours'=>['#00d4ff','rgba(0,212,255,0.1)','rgba(0,212,255,0.3)'],'resolu'=>['#00c864','rgba(0,200,100,0.1)','rgba(0,200,100,0.3)']];
$statutLabels = ['ouvert'=>'🟡 Ouvert','en_cours'=>'🔵 En cours','resolu'=>'✅ Résolu'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tickets SAV – Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--neon-purple:#a855f7;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);--glow-green:0 0 20px rgba(255,45,120,0.3);}
    *{margin:0;padding:0;box-sizing:border-box;}
    html,body{overflow-x:hidden !important;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;}
    .page-header{margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:700;}
    .filters{display:flex;gap:0.5rem;}
    .filter-btn{padding:0.5rem 1.2rem;border-radius:8px;text-decoration:none;font-family:'Rajdhani',sans-serif;font-size:0.9rem;font-weight:600;border:1px solid rgba(255,255,255,0.1);color:var(--text-muted);transition:all 0.2s;}
    .filter-btn.active,.filter-btn:hover{background:rgba(255,45,120,0.1);border-color:var(--neon-green);color:var(--text-primary);}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
    .card h3{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;margin-bottom:1.5rem;}
    .ticket-row{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:1.2rem;margin-bottom:0.8rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;text-decoration:none;color:inherit;transition:all 0.2s;border-left:3px solid transparent;}
    .ticket-row:hover{background:rgba(255,45,120,0.04);border-left-color:var(--neon-green);transform:translateX(3px);}
    .ticket-sujet{font-weight:600;font-size:0.95rem;margin-bottom:0.25rem;}
    .ticket-meta{font-size:0.8rem;color:var(--text-muted);}
    .statut-badge{padding:0.25rem 0.8rem;border-radius:6px;font-size:0.78rem;font-weight:700;white-space:nowrap;}
    .back-btn{color:var(--text-muted);text-decoration:none;font-size:0.9rem;display:inline-flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;transition:color 0.3s;}
    .back-btn:hover{color:var(--text-primary);}
    .ticket-info-card{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;}
    .chat-area{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:1.5rem;min-height:250px;max-height:450px;overflow-y:auto;margin-bottom:1rem;display:flex;flex-direction:column;gap:1rem;}
    .msg-bubble{max-width:78%;}
    .msg-bubble.from-me{align-self:flex-end;}
    .msg-bubble.from-client{align-self:flex-start;}
    .msg-content{padding:0.8rem 1.2rem;border-radius:12px;font-size:0.95rem;line-height:1.5;}
    .from-me .msg-content{background:linear-gradient(135deg,rgba(255,45,120,0.2),rgba(255,45,120,0.1));border:1px solid rgba(255,45,120,0.2);}
    .from-client .msg-content{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
    .msg-meta{font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;}
    .from-me .msg-meta{text-align:right;}
    .client-label{font-family:'Orbitron',sans-serif;font-size:0.65rem;color:var(--neon-blue);letter-spacing:1px;margin-bottom:0.3rem;}
    .reply-form{display:flex;flex-direction:column;gap:1rem;}
    .reply-form textarea{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.9rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:1rem;resize:vertical;min-height:100px;outline:none;transition:border 0.3s;}
    .reply-form textarea:focus{border-color:var(--neon-green);}
    .reply-form textarea::placeholder{color:var(--text-muted);}
    .reply-actions{display:flex;gap:1rem;align-items:center;flex-wrap:wrap;}
    .select-statut{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.7rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.95rem;outline:none;}
    .select-statut option{background:#111827;}
    .btn-reply{background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:white;padding:0.8rem 2rem;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:1rem;cursor:pointer;transition:all 0.3s;}
    .btn-reply:hover{box-shadow:var(--glow-green);}
    /* Image upload/paste */
    .reply-textarea-wrap{position:relative;}
    .reply-textarea-wrap textarea{width:100%;padding-right:3.5rem;}
    .reply-img-btns{position:absolute;bottom:0.6rem;right:0.7rem;display:flex;align-items:center;gap:0.5rem;}
    .btn-img-upload{display:flex;align-items:center;justify-content:center;width:32px;height:32px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:8px;cursor:pointer;font-size:1rem;transition:all .2s;}
    .btn-img-upload:hover{background:rgba(0,212,106,0.15);border-color:rgba(0,212,106,0.4);}
    /* Zone paste active */
    .paste-active{border-color:rgba(0,212,106,0.6) !important;background:rgba(0,212,106,0.04) !important;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.2);border-radius:10px;padding:0.8rem 1rem;color:#00c864;margin-bottom:1.5rem;}
    .empty-state{text-align:center;padding:3rem;color:var(--text-muted);}

    @media(max-width:768px){
      .page-header{flex-direction:column;align-items:flex-start;gap:0.6rem;}
      .page-header h1{font-size:1.15rem;}
      .filters{width:100%;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;}
      .filter-btn{flex:0 0 auto;padding:0.45rem 1rem;font-size:0.82rem;min-height:40px;display:inline-flex;align-items:center;}
      .card{padding:1rem 0.8rem;border-radius:10px;margin-bottom:1rem;}
      .card h3{font-size:0.82rem;margin-bottom:1rem;}
      .ticket-row{flex-direction:column;align-items:flex-start;gap:0.4rem;padding:0.85rem;border-radius:8px;margin-bottom:0.6rem;}
      .ticket-row:hover{transform:none;}
      .ticket-sujet{font-size:0.9rem;}
      .ticket-meta{font-size:0.75rem;}
      .statut-badge{font-size:0.72rem;padding:0.2rem 0.65rem;}
      .back-btn{font-size:0.85rem;margin-bottom:1rem;}
      .ticket-info-card{padding:1rem;border-radius:10px;margin-bottom:1rem;}
      .chat-area{padding:0.8rem;border-radius:8px;max-height:340px;}
      .msg-content{font-size:0.88rem;padding:0.65rem 0.9rem;border-radius:10px;}
      .msg-bubble{max-width:90%;}
      .msg-meta{font-size:0.68rem;}
      .reply-form textarea{font-size:0.9rem;min-height:80px;padding:0.7rem 0.8rem;}
      .reply-actions{flex-direction:column;gap:0.6rem;align-items:stretch;}
      .select-statut{width:100%;padding:0.65rem 0.8rem;font-size:0.9rem;}
      .btn-reply{width:100%;min-height:44px;font-size:0.92rem;}
      .alert-success{font-size:0.85rem;padding:0.7rem 0.8rem;border-radius:8px;}
    }
    @media(max-width:480px){
      .card{padding:0.8rem 0.6rem;}
      .ticket-row{padding:0.65rem;}
      .chat-area{padding:0.6rem;max-height:280px;}
      .msg-content{font-size:0.82rem;padding:0.55rem 0.75rem;}
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <?php if ($success): ?><div class="alert-success">✓ <?= clean($success) ?></div><?php endif; ?>

  <?php if ($ticketDetail): ?>
    <!-- DÉTAIL TICKET -->
    <a href="tickets.php" class="back-btn">← Retour aux tickets</a>

    <div class="ticket-info-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:700;margin-bottom:0.4rem;"><?= clean($ticketDetail['sujet']) ?></div>
          <div style="color:var(--text-muted);font-size:0.85rem;">
            Par <strong style="color:var(--text-secondary);"><?= clean($ticketDetail['nom']) ?></strong> (<?= clean($ticketDetail['email']) ?>)
            — Ouvert le <?= date('d/m/Y à H:i', strtotime($ticketDetail['date_creation'])) ?>
          </div>
        </div>
        <?php $s = $statutColors[$ticketDetail['statut']]; ?>
        <span class="statut-badge" style="color:<?=$s[0]?>;background:<?=$s[1]?>;border:1px solid <?=$s[2]?>;">
          <?= $statutLabels[$ticketDetail['statut']] ?>
        </span>
      </div>

      <!-- Changer statut rapide -->
      <form method="POST" style="margin-top:1rem;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="change_statut">
        <input type="hidden" name="ticket_id" value="<?= $ticketDetail['id'] ?>">
        <span style="font-size:0.85rem;color:var(--text-muted);">Changer le statut :</span>
        <select name="statut" class="select-statut" onchange="this.form.submit()">
          <option value="ouvert" <?= $ticketDetail['statut']==='ouvert'?'selected':'' ?>>🟡 Ouvert</option>
          <option value="en_cours" <?= $ticketDetail['statut']==='en_cours'?'selected':'' ?>>🔵 En cours</option>
          <option value="resolu" <?= $ticketDetail['statut']==='resolu'?'selected':'' ?>>✅ Résolu</option>
        </select>
      </form>
    </div>

    <div class="card">
      <h3>Conversation</h3>
      <div class="chat-area" id="chatArea">
        <?php foreach ($ticketMessages as $msg): ?>
          <div class="msg-bubble <?= $msg['auteur']==='admin' ? 'from-me' : 'from-client' ?>">
            <?php if ($msg['auteur']==='membre'): ?>
              <div class="client-label"><?= clean($ticketDetail['nom']) ?></div>
            <?php endif; ?>
            <div class="msg-content"><?php
              $msgContenu = $msg['contenu'];
              // Séparer les balises [IMG:...] du texte, traiter séparément
              $parts = preg_split('/(\[IMG:[^\]]+\])/', $msgContenu, -1, PREG_SPLIT_DELIM_CAPTURE);
              foreach ($parts as $part) {
                  if (preg_match('/^\[IMG:([^\]]+)\]$/', $part, $imgMatch)) {
                      // Valider le chemin (sécurité)
                      $imgPath = $imgMatch[1];
                      if (preg_match('/^uploads\/tickets\/[a-zA-Z0-9_\-.]+$/', $imgPath)) {
                          $src = htmlspecialchars($imgPath);
                          echo '<a href="/' . $src . '" target="_blank"><img src="/' . $src . '" style="max-width:100%;max-height:320px;border-radius:8px;margin-top:0.5rem;cursor:zoom-in;display:block;border:1px solid rgba(255,255,255,0.1);" loading="lazy"></a>';
                      }
                  } else {
                      echo nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8'));
                  }
              }
            ?></div>
            <div class="msg-meta" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
              <span><?= date('d/m/Y à H:i', strtotime($msg['date_envoi'])) ?></span>
              <?php if ($msg['auteur'] === 'admin'): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete_ticket_msg">
                <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                <input type="hidden" name="ticket_id" value="<?= $ticketDetail['id'] ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;color:rgba(255,100,100,0.5);font-size:0.75rem;padding:0.1rem 0.3rem;border-radius:4px;transition:color .2s;" title="Supprimer" onmouseover="this.style.color='#ff6060'" onmouseout="this.style.color='rgba(255,100,100,0.5)'">🗑️</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="POST" class="reply-form" id="replyForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="reply_ticket">
        <input type="hidden" name="ticket_id" value="<?= $ticketDetail['id'] ?>">
        <input type="hidden" name="image_data" id="imageData">
        <!-- Zone aperçu image collée -->
        <div id="pastePreview" style="display:none;position:relative;margin-bottom:0.5rem;">
          <img id="pasteImg" style="max-height:120px;border-radius:8px;border:1px solid rgba(0,212,106,0.3);">
          <button type="button" onclick="clearPastedImage()" style="position:absolute;top:4px;right:4px;background:rgba(255,45,120,0.8);border:none;color:#fff;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:0.75rem;line-height:1;">✕</button>
        </div>
        <div class="reply-textarea-wrap" id="replyTextareaWrap">
          <textarea name="contenu" id="replyTextarea" placeholder="Votre réponse… (Ctrl+V pour coller une image)" maxlength="2000"></textarea>
          <div class="reply-img-btns">
            <label class="btn-img-upload" title="Joindre une image">
              📎
              <input type="file" name="image_file" id="imageFile" accept="image/*" style="display:none;" onchange="previewFile(this)">
            </label>
            <span style="font-size:0.68rem;color:var(--text-muted);">ou Ctrl+V</span>
          </div>
        </div>
        <div class="reply-actions">
          <span style="font-size:0.85rem;color:var(--text-muted);">Marquer comme :</span>
          <select name="statut" class="select-statut">
            <option value="en_cours">🔵 En cours</option>
            <option value="resolu">✅ Résolu</option>
            <option value="ouvert">🟡 Ouvert</option>
          </select>
          <button type="submit" class="btn-reply">Répondre →</button>
        </div>
      </form>
    </div>

  <?php else: ?>
    <!-- LISTE TICKETS -->
    <div class="page-header">
      <h1>🎫 Tickets SAV (<?= count($tickets) ?>)</h1>
      <div class="filters">
        <a href="?filtre=actif" class="filter-btn <?= $filtre==='actif'?'active':'' ?>">En attente</a>
        <a href="?filtre=tous" class="filter-btn <?= $filtre==='tous'?'active':'' ?>">Tous</a>
      </div>
    </div>

    <div class="card">
      <?php if (empty($tickets)): ?>
        <div class="empty-state">✅ Aucun ticket en attente.</div>
      <?php else: ?>
        <?php foreach ($tickets as $t):
          $s = $statutColors[$t['statut']];
        ?>
          <a href="?ticket=<?= $t['id'] ?>&filtre=<?= $filtre ?>" class="ticket-row">
            <div style="flex:1;min-width:0;">
              <div class="ticket-sujet"><?= clean($t['sujet']) ?></div>
              <div class="ticket-meta">
                <strong><?= clean($t['nom']) ?></strong> · <?= date('d/m/Y à H:i', strtotime($t['date_creation'])) ?>
              </div>
            </div>
            <span class="statut-badge" style="color:<?=$s[0]?>;background:<?=$s[1]?>;border:1px solid <?=$s[2]?>;">
              <?= $statutLabels[$t['statut']] ?>
            </span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<script>
  const c = document.getElementById('chatArea');
  if (c) c.scrollTop = c.scrollHeight;
</script>

<script>
// ── Coller image depuis presse-papiers (Ctrl+V) ────────────
document.addEventListener('paste', function(e) {
  const textarea = document.getElementById('replyTextarea');
  if (!textarea) return;
  const items = e.clipboardData?.items;
  if (!items) return;
  for (const item of items) {
    if (item.type.startsWith('image/')) {
      e.preventDefault();
      const file = item.getAsFile();
      readImageFile(file);
      break;
    }
  }
});

// ── Choisir un fichier via bouton 📎 ──────────────────────
function previewFile(input) {
  if (!input.files || !input.files[0]) return;
  readImageFile(input.files[0]);
}

// ── Lire l'image et l'afficher en aperçu ─────────────────
function readImageFile(file) {
  const reader = new FileReader();
  reader.onload = function(ev) {
    const base64 = ev.target.result;
    document.getElementById('imageData').value = base64;
    document.getElementById('pasteImg').src = base64;
    document.getElementById('pastePreview').style.display = 'block';
    // Feedback visuel sur le textarea
    document.getElementById('replyTextarea').classList.add('paste-active');
  };
  reader.readAsDataURL(file);
}

// ── Supprimer l'image collée ──────────────────────────────
function clearPastedImage() {
  document.getElementById('imageData').value = '';
  document.getElementById('pasteImg').src = '';
  document.getElementById('pastePreview').style.display = 'none';
  document.getElementById('replyTextarea').classList.remove('paste-active');
  document.getElementById('imageFile').value = '';
}

// ── Drag & drop sur le textarea ───────────────────────────
const textarea = document.getElementById('replyTextarea');
if (textarea) {
  textarea.addEventListener('dragover', e => { e.preventDefault(); textarea.classList.add('paste-active'); });
  textarea.addEventListener('dragleave', () => textarea.classList.remove('paste-active'));
  textarea.addEventListener('drop', function(e) {
    e.preventDefault();
    textarea.classList.remove('paste-active');
    const file = e.dataTransfer.files?.[0];
    if (file && file.type.startsWith('image/')) readImageFile(file);
  });
}
</script>
</body>
</html>
