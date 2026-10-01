<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$membre = getMembre();
$currentPage = 'sav';
$avatarUrl = getAvatarUrl($membre);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'new_ticket') {
            $sujet   = trim($_POST['sujet'] ?? '');
            $message = trim($_POST['message'] ?? '');
            if (empty($sujet) || empty($message)) { $error = 'Veuillez remplir tous les champs.'; }
            else {
                $stmt = $db->prepare("INSERT INTO tickets (membre_id, sujet) VALUES (?, ?)");
                $stmt->execute([$membre['id'], $sujet]);
                $ticketId = $db->lastInsertId();
                $db->prepare("INSERT INTO ticket_messages (ticket_id, contenu, auteur) VALUES (?, ?, 'membre')")->execute([$ticketId, $message]);
                $success = 'Ticket créé ! Réponse dans les plus brefs délais.';
            }
        }
        elseif ($_POST['action'] === 'reply_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $contenu  = trim($_POST['contenu'] ?? '');
            $stmt = $db->prepare("SELECT id, statut FROM tickets WHERE id = ? AND membre_id = ? LIMIT 1");
            $stmt->execute([$ticketId, $membre['id']]);
            $ticketCheck = $stmt->fetch();
            if ($ticketCheck && $ticketCheck['statut'] !== 'resolu' && !empty($contenu)) {
                $db->prepare("INSERT INTO ticket_messages (ticket_id, contenu, auteur) VALUES (?, ?, 'membre')")->execute([$ticketId, $contenu]);
                $success = 'Réponse envoyée.';
            } elseif ($ticketCheck && $ticketCheck['statut'] === 'resolu') {
                $error = 'Ce ticket est résolu.';
            }
        }
    }
}

$stmt = $db->prepare("SELECT * FROM tickets WHERE membre_id = ? ORDER BY date_creation DESC");
$stmt->execute([$membre['id']]);
$tickets = $stmt->fetchAll();

$ticketDetail = null;
$ticketMessages = [];
if (isset($_GET['ticket'])) {
    $tid = (int)$_GET['ticket'];
    $stmt = $db->prepare("SELECT * FROM tickets WHERE id = ? AND membre_id = ? LIMIT 1");
    $stmt->execute([$tid, $membre['id']]);
    $ticketDetail = $stmt->fetch();
    if ($ticketDetail) {
        $stmt2 = $db->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY date_envoi ASC");
        $stmt2->execute([$tid]);
        $ticketMessages = $stmt2->fetchAll();
    }
}

$statutColors = ['ouvert'=>['bg'=>'rgba(255,200,0,0.1)','border'=>'rgba(255,200,0,0.3)','color'=>'#ffc107'],'en_cours'=>['bg'=>'rgba(0,212,255,0.1)','border'=>'rgba(0,212,255,0.3)','color'=>'#00d4ff'],'resolu'=>['bg'=>'rgba(255,45,120,0.08)','border'=>'rgba(255,45,120,0.3)','color'=>'#ff2d78']];
$statutLabels = ['ouvert'=>'🟡 Ouvert','en_cours'=>'🔵 En cours','resolu'=>'✅ Résolu'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SAV – StratEdge Pronos</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#050810">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.sec{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.8rem;margin-bottom:1.5rem;}
.sec h3{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:700;margin-bottom:1.3rem;display:flex;align-items:center;gap:0.7rem;}
.sec h3 .dot{width:8px;height:8px;border-radius:50%;background:var(--pink);}
.page-title{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:700;margin-bottom:0.5rem;}
.page-sub{color:var(--txt3);margin-bottom:2rem;font-size:0.95rem;}
.form-group{margin-bottom:1.3rem;}
.form-group label{display:block;font-size:0.85rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--txt2);margin-bottom:0.5rem;}
.form-group input,.form-group textarea,.form-group select{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.9rem 1.2rem;color:var(--txt);font-family:'Rajdhani',sans-serif;font-size:1rem;transition:all .3s;outline:none;}
.form-group input:focus,.form-group textarea:focus{border-color:var(--pink);box-shadow:0 0 0 3px rgba(255,45,120,0.1);}
.form-group textarea{resize:vertical;min-height:120px;}
.form-group input::placeholder,.form-group textarea::placeholder{color:var(--txt3);}
.btn-submit{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff;padding:0.9rem 2rem;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;text-transform:uppercase;cursor:pointer;transition:all .3s;}
.btn-submit:hover{box-shadow:0 0 25px rgba(255,45,120,0.35);transform:translateY(-2px);}
.alert-success{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);border-radius:10px;padding:1rem;color:#ff2d78;margin-bottom:1.5rem;font-weight:600;}
.alert-error{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.2);border-radius:10px;padding:1rem;color:#ff6b9d;margin-bottom:1.5rem;font-weight:600;}
.ticket-item{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:1.2rem;margin-bottom:0.8rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:all .3s;text-decoration:none;color:inherit;}
.ticket-item:hover{border-color:var(--border);background:rgba(255,45,120,0.05);transform:translateX(4px);}
.ticket-sujet{font-weight:600;font-size:1rem;margin-bottom:0.3rem;}
.ticket-date{font-size:0.82rem;color:var(--txt3);}
.statut-badge{padding:0.3rem 0.9rem;border-radius:6px;font-size:0.82rem;font-weight:700;white-space:nowrap;}
.back-btn{color:var(--txt3);text-decoration:none;font-size:0.95rem;display:inline-flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;transition:color .3s;}
.back-btn:hover{color:var(--txt);}
.chat-area{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:1.5rem;min-height:200px;max-height:400px;overflow-y:auto;margin-bottom:1rem;display:flex;flex-direction:column;gap:1rem;}
.msg-bubble{max-width:80%;}
.msg-bubble.from-me{align-self:flex-end;}
.msg-bubble.from-admin{align-self:flex-start;}
.msg-content{padding:0.8rem 1.2rem;border-radius:12px;font-size:0.95rem;line-height:1.5;}
.from-me .msg-content{background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.2);}
.from-admin .msg-content{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
.msg-meta{font-size:0.75rem;color:var(--txt3);margin-top:0.3rem;}
.from-me .msg-meta{text-align:right;}
.admin-label{font-family:'Orbitron',sans-serif;font-size:0.65rem;color:var(--pink);letter-spacing:1px;margin-bottom:0.3rem;}
.chat-form{display:flex;gap:1rem;align-items:flex-end;}
.chat-form textarea{flex:1;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.9rem;color:var(--txt);font-family:'Rajdhani',sans-serif;font-size:1rem;resize:none;height:80px;outline:none;transition:border .3s;}
.chat-form textarea:focus{border-color:var(--pink);}
.chat-form textarea::placeholder{color:var(--txt3);}
.btn-send{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff;padding:0 1.5rem;height:80px;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all .3s;}
.ticket-header-info{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;}
.ticket-header-info h4{font-family:'Orbitron',sans-serif;font-size:0.95rem;margin-bottom:0.4rem;}
@media(max-width:768px){
  .page-title{font-size:1.2rem;}
  .page-sub{font-size:0.85rem;margin-bottom:1.2rem;}
  .sec{padding:1.1rem 0.9rem;border-radius:12px;margin-bottom:1rem;}
  .sec h3{font-size:0.88rem;margin-bottom:1rem;}
  .ticket-item{flex-direction:column;align-items:flex-start;gap:0.4rem;padding:0.9rem;border-radius:10px;margin-bottom:0.6rem;}
  .ticket-item:hover{transform:none;}
  .ticket-sujet{font-size:0.92rem;}
  .ticket-date{font-size:0.75rem;}
  .statut-badge{font-size:0.75rem;padding:0.25rem 0.7rem;}
  .chat-form{flex-direction:column;gap:0.6rem;}
  .chat-form textarea{height:70px;font-size:0.92rem;padding:0.7rem 0.8rem;}
  .btn-send{height:48px;width:100%;border-radius:10px;min-height:48px;}
  .btn-submit{width:100%;padding:0.8rem 1.5rem;font-size:0.92rem;min-height:48px;}
  .chat-area{padding:1rem;border-radius:10px;max-height:350px;}
  .msg-content{padding:0.65rem 0.9rem;font-size:0.88rem;border-radius:10px;}
  .msg-bubble{max-width:88%;}
  .msg-meta{font-size:0.68rem;}
  .form-group label{font-size:0.78rem;}
  .form-group input,.form-group textarea,.form-group select{padding:0.75rem 1rem;font-size:0.92rem;border-radius:8px;}
  .form-group textarea{min-height:100px;}
  .ticket-header-info{padding:1rem;border-radius:10px;margin-bottom:1rem;}
  .ticket-header-info h4{font-size:0.85rem;}
  .back-btn{font-size:0.85rem;margin-bottom:1rem;}
  .alert-success,.alert-error{font-size:0.88rem;padding:0.8rem;border-radius:8px;}
}
@media(max-width:380px){
  .page-title{font-size:1.05rem;}
  .sec{padding:0.8rem 0.6rem;}
  .ticket-item{padding:0.7rem;}
  .chat-area{padding:0.7rem;}
  .msg-content{font-size:0.82rem;padding:0.5rem 0.7rem;}
  .form-group input,.form-group textarea{padding:0.65rem 0.8rem;font-size:0.88rem;}
}
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<?php if ($ticketDetail): ?>
  <a href="sav.php" class="back-btn">← Retour aux tickets</a>
  <?php if ($success): ?><div class="alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
  <div class="ticket-header-info">
    <h4><?= clean($ticketDetail['sujet']) ?></h4>
    <div style="display:flex;gap:1rem;align-items:center;margin-top:0.5rem;">
      <?php $s = $statutColors[$ticketDetail['statut']]; ?>
      <span class="statut-badge" style="background:<?=$s['bg']?>;border:1px solid <?=$s['border']?>;color:<?=$s['color']?>;"><?= $statutLabels[$ticketDetail['statut']] ?></span>
      <span style="color:var(--txt3);font-size:0.88rem;">Ouvert le <?= date('d/m/Y', strtotime($ticketDetail['date_creation'])) ?></span>
    </div>
  </div>
  <div class="sec">
    <h3><span class="dot"></span> Conversation</h3>
    <div class="chat-area" id="chatArea">
      <?php foreach ($ticketMessages as $msg): ?>
      <div class="msg-bubble <?= $msg['auteur']==='membre'?'from-me':'from-admin' ?>">
        <?php if ($msg['auteur']==='admin'): ?><div class="admin-label">⚡ STRATEDGE</div><?php endif; ?>
        <div class="msg-content"><?= nl2br(clean($msg['contenu'])) ?></div>
        <div class="msg-meta"><?= date('d/m/Y à H:i', strtotime($msg['date_envoi'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($ticketDetail['statut'] !== 'resolu'): ?>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="reply_ticket"><input type="hidden" name="ticket_id" value="<?= $ticketDetail['id'] ?>">
      <div class="chat-form"><textarea name="contenu" placeholder="Votre réponse…" maxlength="2000"></textarea><button type="submit" class="btn-send">Envoyer →</button></div>
    </form>
    <?php else: ?>
    <div style="margin:1rem 0;background:rgba(0,200,100,0.06);border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:1.2rem;text-align:center;">
      <div style="font-size:1.5rem;margin-bottom:0.4rem;">✅</div>
      <p style="color:#ff2d78;font-weight:700;margin-bottom:0.3rem;">Ticket résolu</p>
      <p style="color:var(--txt3);font-size:0.88rem;margin-bottom:1rem;">N'accepte plus de réponses.</p>
      <a href="sav.php" style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;text-decoration:none;padding:0.65rem 1.5rem;border-radius:10px;font-weight:700;">+ Nouveau ticket</a>
    </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <h1 class="page-title">🎫 SAV & Support</h1>
  <p class="page-sub">Un problème ? Ouvre un ticket et je te réponds personnellement.</p>
  <?php if ($success): ?><div class="alert-success">✓ <?= clean($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error">⚠️ <?= clean($error) ?></div><?php endif; ?>

  <div class="sec">
    <h3><span class="dot"></span> Nouveau ticket</h3>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="new_ticket">
      <div class="form-group"><label>Sujet</label><input type="text" name="sujet" placeholder="Résumez votre problème" required maxlength="200"></div>
      <div class="form-group"><label>Description</label><textarea name="message" placeholder="Décrivez en détail…" required maxlength="2000"></textarea></div>
      <button type="submit" class="btn-submit">Envoyer le ticket →</button>
    </form>
  </div>

  <div class="sec">
    <h3><span class="dot"></span> Mes tickets (<?= count($tickets) ?>)</h3>
    <?php if (empty($tickets)): ?>
      <p style="color:var(--txt3);text-align:center;padding:1rem;">Aucun ticket.</p>
    <?php else: ?>
      <?php foreach ($tickets as $t): $s = $statutColors[$t['statut']]; ?>
      <a href="?ticket=<?= $t['id'] ?>" class="ticket-item">
        <div><div class="ticket-sujet"><?= clean($t['sujet']) ?></div><div class="ticket-date"><?= date('d/m/Y à H:i', strtotime($t['date_creation'])) ?></div></div>
        <span class="statut-badge" style="background:<?=$s['bg']?>;border:1px solid <?=$s['border']?>;color:<?=$s['color']?>;"><?= $statutLabels[$t['statut']] ?></span>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

</main></div>

<script>
var c=document.getElementById('chatArea');if(c)c.scrollTop=c.scrollHeight;
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
