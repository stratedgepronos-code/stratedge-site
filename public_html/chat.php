<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/push.php';
require_once __DIR__ . '/includes/mailer.php';
requireLogin();
$membre = getMembre(); $db = getDB(); $currentPage = 'chat'; $avatarUrl = getAvatarUrl($membre);

$stmt = $db->prepare("SELECT * FROM chats WHERE membre_id = ? LIMIT 1"); $stmt->execute([$membre['id']]); $chat = $stmt->fetch();
if (!$chat) { $db->prepare("INSERT INTO chats (membre_id, titre) VALUES (?, ?)")->execute([$membre['id'], 'Discussion avec ' . $membre['nom']]); $stmt->execute([$membre['id']]); $chat = $stmt->fetch(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['contenu'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $error = 'Erreur de sécurité.'; }
    else { $contenu = trim($_POST['contenu']); if (mb_strlen($contenu) > 2000) $contenu = mb_substr($contenu, 0, 2000);
      if (!empty($contenu)) {
        $db->prepare("INSERT INTO chat_messages (chat_id, contenu, expediteur) VALUES (?, ?, 'membre')")->execute([$chat['id'], $contenu]);
        $db->prepare("UPDATE chats SET lu_admin = 0, date_dernier_msg = NOW() WHERE id = ?")->execute([$chat['id']]);
        envoyerEmail(ADMIN_EMAIL, '💬 Nouveau message de '.$membre['nom'].' — StratEdge',
          emailTemplate('Nouveau message', '<p><strong>'.htmlspecialchars($membre['nom']).'</strong> t\'a envoyé un message :</p>
          <blockquote style="border-left:3px solid #ff2d78;padding-left:1rem;color:#ccc;">'.nl2br(htmlspecialchars($contenu)).'</blockquote>
          <p><a href="https://stratedgepronos.fr/panel-x9k3m/chat.php" style="color:#ff2d78;">Répondre →</a></p>'));
        header('Location: chat.php?sent=1'); exit;
      }
    }
}
$db->prepare("UPDATE chat_messages SET lu = 1 WHERE chat_id = ? AND expediteur = 'admin' AND lu = 0")->execute([$chat['id']]);
$db->prepare("UPDATE chats SET lu_membre = 1 WHERE id = ?")->execute([$chat['id']]);
$stmt = $db->prepare("SELECT * FROM chat_messages WHERE chat_id = ? ORDER BY date_envoi ASC"); $stmt->execute([$chat['id']]); $messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>💬 Chat — StratEdge</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<meta name="apple-mobile-web-app-capable" content="yes"><link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.chat-box{max-width:820px;width:100%;margin:0 auto;}
.chat-header{background:var(--card);border:1px solid var(--border-soft);border-radius:16px 16px 0 0;padding:1.2rem 1.8rem;display:flex;align-items:center;gap:1rem;}
.chat-avatar{width:48px;height:48px;border-radius:50%;overflow:hidden;flex-shrink:0;}
.chat-avatar img{width:100%;height:100%;object-fit:cover;}
.chat-header-info h3{font-size:1.1rem;font-weight:700;}
.chat-header-info p{font-size:0.85rem;color:var(--txt3);}
.online-dot{width:9px;height:9px;background:var(--pink);border-radius:50%;display:inline-block;margin-left:0.5rem;box-shadow:0 0 8px var(--pink);}
.chat-messages{background:rgba(10,14,23,0.5);border:1px solid var(--border-soft);border-top:none;border-bottom:none;min-height:420px;max-height:calc(100vh - 340px);overflow-y:auto;padding:1.8rem;display:flex;flex-direction:column;gap:1.1rem;}
.chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--txt3);text-align:center;padding:3rem;gap:0.6rem;}
.chat-empty span{font-size:3rem;}
.chat-empty strong{font-size:1.1rem;}
.msg-row{display:flex;gap:0.7rem;align-items:flex-end;}
.msg-row.from-me{flex-direction:row-reverse;}
.msg-av-sm{width:34px;height:34px;border-radius:50%;flex-shrink:0;background:var(--card);display:flex;align-items:center;justify-content:center;font-size:1rem;overflow:hidden;}
.msg-av-sm img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.msg-bubble{max-width:72%;padding:0.9rem 1.2rem;border-radius:16px;font-size:1rem;line-height:1.6;word-break:break-word;}
.msg-bubble.admin{background:linear-gradient(135deg,rgba(0,212,106,0.12),rgba(0,168,84,0.08));border:1px solid rgba(0,212,106,0.2);border-bottom-left-radius:4px;}
.msg-bubble.membre{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);border-bottom-right-radius:4px;}
.msg-meta{font-size:0.72rem;color:var(--txt3);margin-top:0.3rem;}
.msg-row.from-me .msg-meta{text-align:right;}
.msg-row:not(.from-me) .msg-meta{text-align:left;}
.chat-input-area{background:var(--card);border:1px solid var(--border-soft);border-radius:0 0 16px 16px;padding:1.2rem 1.8rem;}
.chat-input-row{display:flex;gap:1rem;align-items:flex-end;}
.chat-input-row textarea{flex:1;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:0.9rem 1.2rem;color:var(--txt);font-family:'Rajdhani',sans-serif;font-size:1.05rem;resize:none;height:90px;outline:none;transition:border-color .2s;}
.chat-input-row textarea:focus{border-color:var(--pink);}
.chat-input-row textarea::placeholder{color:var(--txt3);}
.btn-send{background:linear-gradient(135deg,var(--pink),var(--pink-dim));border:none;border-radius:14px;padding:0 1.4rem;color:#fff;font-weight:700;cursor:pointer;font-size:1.3rem;transition:transform .2s,box-shadow .2s;flex-shrink:0;height:54px;width:54px;display:flex;align-items:center;justify-content:center;}
.btn-send:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,45,120,0.3);}
.chat-hint{font-size:0.8rem;color:var(--txt3);margin-top:0.6rem;}
.notif-ok{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);color:var(--pink);border-radius:10px;padding:0.8rem 1rem;font-size:0.92rem;margin-bottom:1rem;font-weight:600;max-width:820px;margin-left:auto;margin-right:auto;}
@media(max-width:768px){
  .chat-box{max-width:100%;}
  .chat-input-row{flex-direction:column;gap:0.6rem;}
  .btn-send{width:100%;height:48px;border-radius:12px;font-size:1.1rem;min-height:48px;}
  .chat-messages{max-height:calc(100dvh - 340px);min-height:200px;padding:0.8rem;border-radius:0;}
  .chat-header{padding:0.8rem 1rem;border-radius:12px 12px 0 0;}
  .chat-header-info h3{font-size:0.9rem;}
  .chat-header-info p{font-size:0.75rem;}
  .chat-avatar{width:36px;height:36px;}
  .chat-input-area{padding:0.8rem 1rem;border-radius:0 0 12px 12px;}
  .chat-input-row textarea{height:60px;font-size:0.92rem;padding:0.7rem 1rem;border-radius:10px;}
  .msg-bubble{max-width:85%;font-size:0.9rem;padding:0.65rem 0.9rem;border-radius:12px;}
  .msg-av-sm{width:28px;height:28px;font-size:0.85rem;}
  .msg-meta{font-size:0.65rem;}
  .chat-hint{font-size:0.7rem;}
  .chat-empty span{font-size:2.2rem;}
  .chat-empty strong{font-size:0.95rem;}
  .notif-ok{font-size:0.85rem;padding:0.7rem 0.8rem;border-radius:8px;max-width:100%;}
}
@media(max-width:380px){
  .chat-messages{padding:0.5rem;max-height:calc(100dvh - 360px);}
  .msg-bubble{max-width:92%;font-size:0.85rem;padding:0.55rem 0.75rem;}
  .msg-av-sm{width:24px;height:24px;font-size:0.75rem;}
  .chat-header{padding:0.6rem 0.8rem;}
  .chat-input-area{padding:0.6rem 0.8rem;}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<?php if (isset($_GET['sent'])): ?><div class="notif-ok">✓ Message envoyé !</div><?php endif; ?>

<div class="chat-box">
<div class="chat-header">
  <div class="chat-avatar"><img src="assets/images/mascotte.png" alt=""></div>
  <div class="chat-header-info">
    <h3>StratEdge Support <span class="online-dot"></span></h3>
    <p>Répond généralement sous quelques heures</p>
  </div>
</div>
<div class="chat-messages" id="chatMessages">
  <?php if (empty($messages)): ?>
  <div class="chat-empty"><span>💬</span><strong>Début de la conversation</strong><p>Envoie ton premier message à l'équipe StratEdge</p></div>
  <?php else: ?>
  <?php foreach ($messages as $msg): $isMe = ($msg['expediteur'] === 'membre'); ?>
  <div class="msg-row <?= $isMe ? 'from-me' : '' ?>">
    <div class="msg-av-sm"><?= $isMe ? '👤' : '<img src="assets/images/mascotte.png">' ?></div>
    <div><div class="msg-bubble <?= $msg['expediteur'] ?>"><?= nl2br(htmlspecialchars($msg['contenu'])) ?></div>
    <div class="msg-meta"><?= date('d/m à H:i', strtotime($msg['date_envoi'])) ?><?php if ($isMe && $msg['lu']): ?> · ✓✓<?php endif; ?></div></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<div class="chat-input-area">
  <?php if ($error): ?><p style="color:var(--pink);font-size:0.88rem;margin-bottom:0.5rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="chat-input-row">
      <textarea name="contenu" placeholder="Écris ton message…" maxlength="2000" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
      <button type="submit" class="btn-send">➤</button>
    </div>
  </form>
  <p class="chat-hint">Entrée pour envoyer · Shift+Entrée pour sauter une ligne</p>
</div>
</div>

</main></div>
<script>var c=document.getElementById('chatMessages');if(c)c.scrollTop=c.scrollHeight;function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
