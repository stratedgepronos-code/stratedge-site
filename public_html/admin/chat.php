<?php
// ============================================================
// STRATEDGE — Chat Admin — dans le layout panel standard
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();
$pageActive = 'chat';
$db = getDB();

// ── Envoi réponse admin ────────────────────────────────────
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $chatId  = (int)($_POST['chat_id'] ?? 0);
    $contenu = trim($_POST['contenu'] ?? '');

    if ($chatId && !empty($contenu)) {
        $db->prepare("INSERT INTO chat_messages (chat_id, contenu, expediteur) VALUES (?, ?, 'admin')")
           ->execute([$chatId, $contenu]);
        $db->prepare("UPDATE chats SET lu_membre = 0, lu_admin = 1, date_dernier_msg = NOW() WHERE id = ?")
           ->execute([$chatId]);

        $stmt = $db->prepare("SELECT m.id, m.email, m.nom FROM membres m JOIN chats c ON c.membre_id = m.id WHERE c.id = ?");
        $stmt->execute([$chatId]);
        $mem = $stmt->fetch();

        if ($mem) {
            pushNouveauMessage($mem['id']);
            envoyerEmail($mem['email'],
                '💬 Nouveau message de StratEdge',
                emailTemplate('Nouveau message',
                    '<p>Bonjour <strong>' . htmlspecialchars($mem['nom']) . '</strong>,</p>
                     <p>Tu as reçu un nouveau message de l\'équipe StratEdge :</p>
                     <blockquote style="border-left:3px solid #00d46a;padding-left:1rem;color:#ccc;">'
                     . nl2br(htmlspecialchars($contenu)) .
                    '</blockquote>
                    <p><a href="https://stratedgepronos.fr/chat.php" style="color:#00d46a;">Voir la conversation →</a></p>'
                )
            );
        }
        header('Location: chat.php?chat=' . $chatId . '&sent=1');
        exit;
    }
}

// ── Conversation sélectionnée ─────────────────────────────
$selectedChatId = (int)($_GET['chat'] ?? 0);
$chatMessages   = [];
$selectedChat   = null;

if ($selectedChatId) {
    $stmt = $db->prepare("SELECT c.*, m.nom, m.email FROM chats c JOIN membres m ON m.id = c.membre_id WHERE c.id = ?");
    $stmt->execute([$selectedChatId]);
    $selectedChat = $stmt->fetch();

    if ($selectedChat) {
        $stmt = $db->prepare("SELECT * FROM chat_messages WHERE chat_id = ? ORDER BY date_envoi ASC");
        $stmt->execute([$selectedChatId]);
        $chatMessages = $stmt->fetchAll();
        $db->prepare("UPDATE chats SET lu_admin = 1 WHERE id = ?")->execute([$selectedChatId]);
        $db->prepare("UPDATE chat_messages SET lu = 1 WHERE chat_id = ? AND expediteur = 'membre'")->execute([$selectedChatId]);
    }
}

// ── Liste des conversations ───────────────────────────────
$chats = $db->query("
    SELECT c.*, m.nom, m.email,
        (SELECT COUNT(*) FROM chat_messages cm WHERE cm.chat_id = c.id AND cm.expediteur = 'membre' AND cm.lu = 0) as nb_nonlus
    FROM chats c
    JOIN membres m ON m.id = c.membre_id
    ORDER BY c.date_dernier_msg DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>💬 Chat — Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);--glow-green:0 0 20px rgba(255,45,120,0.3);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);height:100vh;display:flex;overflow:hidden;}

    .main{margin-left:240px;flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden;}
    .topbar{padding:1.2rem 2rem;border-bottom:1px solid var(--border-subtle);flex-shrink:0;display:flex;align-items:center;justify-content:space-between;}
    .topbar h1{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:700;}

    /* Layout 2 colonnes */
    .chat-layout{display:grid;grid-template-columns:270px 1fr;flex:1;overflow:hidden;margin:1.2rem;gap:1.2rem;min-height:0;}

    /* Colonne gauche — liste des convs */
    .contacts-panel{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;}
    .contacts-header-label{padding:0.8rem 1rem;font-family:'Space Mono',monospace;font-size:0.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid rgba(255,255,255,0.04);}
    .contacts-list-inner{overflow-y:auto;flex:1;}
    .contact-item{display:flex;align-items:center;gap:0.75rem;padding:0.85rem 1rem;text-decoration:none;color:inherit;border-bottom:1px solid rgba(255,255,255,0.03);transition:all 0.2s;border-left:3px solid transparent;}
    .contact-item:hover,.contact-item.active{background:rgba(255,45,120,0.06);border-left-color:var(--neon-green);}
    .contact-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--neon-green),var(--neon-blue));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.82rem;color:#050810;font-family:'Orbitron',sans-serif;flex-shrink:0;}
    .contact-info{flex:1;min-width:0;}
    .contact-name{font-weight:600;font-size:0.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .contact-time{font-size:0.72rem;color:var(--text-muted);}
    .badge-unread{background:var(--neon-green);color:white;font-size:0.62rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:10px;flex-shrink:0;}

    /* Colonne droite — conversation */
    .chat-panel{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;}
    .chat-header{padding:1rem 1.3rem;border-bottom:1px solid var(--border-subtle);display:flex;align-items:center;gap:1rem;flex-shrink:0;}
    .chat-header-info{flex:1;}
    .chat-header h3{font-family:'Orbitron',sans-serif;font-size:0.92rem;font-weight:700;}
    .chat-header .sub{font-size:0.78rem;color:var(--text-muted);}

    .chat-area{flex:1;overflow-y:auto;padding:1.2rem 1.3rem;display:flex;flex-direction:column;gap:0.9rem;}
    .chat-empty{text-align:center;color:var(--text-muted);margin:auto;}

    /* Bulles */
    .msg-row{display:flex;align-items:flex-end;gap:0.5rem;}
    .msg-row.from-me{flex-direction:row-reverse;}
    .msg-bubble{max-width:74%;min-width:60px;}
    .msg-content{padding:0.7rem 1rem;border-radius:12px;font-size:0.93rem;line-height:1.5;position:relative;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;}
    .from-me .msg-content{background:linear-gradient(135deg,rgba(255,45,120,0.2),rgba(255,45,120,0.1));border:1px solid rgba(255,45,120,0.2);}
    .from-client .msg-content{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
    .msg-meta{font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem;display:flex;align-items:center;gap:0.5rem;}
    .from-me .msg-meta{justify-content:flex-end;}

    /* Zone de saisie */
    .chat-compose{padding:0.9rem 1.3rem;border-top:1px solid var(--border-subtle);display:flex;gap:0.8rem;flex-shrink:0;}
    .chat-compose textarea{flex:1;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.75rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.95rem;resize:none;height:65px;outline:none;transition:border 0.3s;}
    .chat-compose textarea:focus{border-color:var(--neon-green);}
    .chat-compose textarea::placeholder{color:var(--text-muted);}
    .btn-send{background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:white;padding:0 1.3rem;height:65px;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.3s;white-space:nowrap;}
    .btn-send:hover{box-shadow:var(--glow-green);}

    .no-chat{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);gap:0.75rem;}
    .no-chat .icon{font-size:3rem;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.2);color:#00c864;padding:0.5rem 1rem;border-radius:8px;font-size:0.85rem;margin-bottom:0.5rem;}

    @media(max-width:768px){
      .main{margin-left:0;padding-top:58px;}
      .chat-layout{grid-template-columns:1fr;height:auto;}
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <h1>💬 Chat Support</h1>
  </div>

  <div class="chat-layout">

    <!-- ── Liste conversations ── -->
    <div class="contacts-panel">
      <div class="contacts-header-label">Conversations (<?= count($chats) ?>)</div>
      <div class="contacts-list-inner">
        <?php foreach ($chats as $c): ?>
          <a href="?chat=<?= $c['id'] ?>" class="contact-item <?= ($c['id'] == $selectedChatId) ? 'active' : '' ?>">
            <div class="contact-avatar"><?= strtoupper(substr($c['nom'], 0, 1)) ?></div>
            <div class="contact-info">
              <div class="contact-name"><?= htmlspecialchars($c['nom']) ?></div>
              <div class="contact-time"><?= date('d/m à H:i', strtotime($c['date_dernier_msg'])) ?></div>
            </div>
            <?php if ($c['nb_nonlus'] > 0): ?>
              <span class="badge-unread"><?= $c['nb_nonlus'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (empty($chats)): ?>
          <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">Aucune conversation</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Conversation ── -->
    <div class="chat-panel">
      <?php if ($selectedChat): ?>

        <div class="chat-header">
          <div class="contact-avatar"><?= strtoupper(substr($selectedChat['nom'], 0, 1)) ?></div>
          <div class="chat-header-info">
            <h3><?= htmlspecialchars($selectedChat['nom']) ?></h3>
            <div class="sub"><?= htmlspecialchars($selectedChat['email']) ?></div>
          </div>
        </div>

        <div class="chat-area" id="chatArea">
          <?php if (empty($chatMessages)): ?>
            <div class="chat-empty">Aucun message encore</div>
          <?php else: ?>
            <?php foreach ($chatMessages as $msg): ?>
              <?php $isAdmin = ($msg['expediteur'] === 'admin'); ?>
              <div class="msg-row <?= $isAdmin ? 'from-me' : 'from-client' ?>">
                <div class="msg-bubble">
                  <div class="msg-content"><?= nl2br(htmlspecialchars($msg['contenu'])) ?></div>
                  <div class="msg-meta"><?= date('d/m à H:i', strtotime($msg['date_envoi'])) ?><?= $isAdmin ? ' · Toi' : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="chat-compose">
          <?php if (isset($_GET['sent'])): ?>
            <div class="alert-success">✓ Message envoyé</div>
          <?php endif; ?>
          <form method="POST" style="display:flex;gap:0.8rem;flex:1;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="chat_id" value="<?= $selectedChat['id'] ?>">
            <textarea name="contenu" placeholder="Ta réponse…" maxlength="2000"
              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
            <button type="submit" class="btn-send">Envoyer ➤</button>
          </form>
        </div>

      <?php else: ?>
        <div class="no-chat">
          <div class="icon">💬</div>
          <div>Sélectionne une conversation</div>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
// Scroll bas automatique
const area = document.getElementById('chatArea');
if (area) area.scrollTop = area.scrollHeight;

// Polling auto toutes les 8s
const chatId = <?= $selectedChatId ?: 'null' ?>;
let lastCount = <?= count($chatMessages) ?>;

setInterval(async function() {
  try {
    const r = await fetch('chat-poll.php?chat_id=' + (chatId || 0));
    const data = await r.json();
    if (chatId && data.count > lastCount) window.location.reload();
    if (data.conversations) {
      data.conversations.forEach(function(c) {
        const item = document.querySelector('.contact-item[href="?chat=' + c.id + '"]');
        if (item) {
          const existing = item.querySelector('.badge-unread');
          if (existing) existing.remove();
          if (c.nb_nonlus > 0) {
            const b = document.createElement('span');
            b.className = 'badge-unread';
            b.textContent = c.nb_nonlus;
            item.appendChild(b);
          }
        }
      });
    }
  } catch(e) {}
}, 8000);
</script>
</body>
</html>
