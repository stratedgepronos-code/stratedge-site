<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'messages';

$db = getDB();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // Envoyer un message (à tous les membres du groupe)
    if ($action === 'send_message') {
        $contenu    = trim($_POST['contenu'] ?? '');
        $membresRaw = $_POST['membres_ids'] ?? '';
        $mids = [];
        foreach (explode(',', $membresRaw) as $id) {
            $id = (int)trim($id);
            if ($id > 0) $mids[] = $id;
        }
        if (!empty($mids) && !empty($contenu)) {
            $stmt = $db->prepare("INSERT INTO messages (membre_id, contenu, expediteur) VALUES (?, ?, 'admin')");
            foreach ($mids as $mid) {
                $stmt->execute([$mid, $contenu]);
            }
            $success = 'Message envoyé (' . count($mids) . ' membre' . (count($mids)>1?'s':'') . ').';
        }
    }

    // Supprimer un message
    if ($action === 'delete_message') {
        $msgId    = (int)($_POST['message_id'] ?? 0);
        $membreId = (int)($_POST['membre_id'] ?? 0);
        $db->prepare("DELETE FROM messages WHERE id=?")->execute([$msgId]);
        $success = 'Message supprimé.';
        header("Location: messages.php?membre={$membreId}&deleted=1");
        exit;
    }

    // Supprimer toute la conversation
    if ($action === 'delete_conversation') {
        $membresRaw = $_POST['membres_ids'] ?? '';
        $mids = [];
        foreach (explode(',', $membresRaw) as $id) { $id=(int)trim($id); if($id>0) $mids[]=$id; }
        if (!empty($mids)) {
            $ph = implode(',', array_fill(0, count($mids), '?'));
            $db->prepare("DELETE FROM messages WHERE membre_id IN ($ph)")->execute($mids);
            $success = 'Conversation supprimée.';
            header("Location: messages.php?conv_deleted=1");
            exit;
        }
    }
}

// Support multi-membres : ?membres=1,3 ou ?membre=1 (compat)
$selectedIds = [];
if (!empty($_GET['membres'])) {
    foreach (explode(',', $_GET['membres']) as $id) {
        $id = (int)trim($id);
        if ($id > 0) $selectedIds[] = $id;
    }
}
if (empty($selectedIds) && !empty($_GET['membre'])) {
    $selectedIds = [(int)$_GET['membre']];
}
$selectedIds = array_unique(array_slice($selectedIds, 0, 10)); // max 10 membres

$selectedId      = count($selectedIds) === 1 ? $selectedIds[0] : 0; // compat ancienne logique
$selectedMembre  = null;
$selectedMembres = []; // tableau de tous les membres sélectionnés
$messages        = [];

if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $s = $db->prepare("SELECT * FROM membres WHERE id IN ($placeholders) AND email != '" . ADMIN_EMAIL . "'");
    $s->execute($selectedIds);
    $selectedMembres = $s->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($selectedMembres)) {
        $selectedMembre = $selectedMembres[0]; // Pour compat
        // Charger messages de TOUS les membres sélectionnés
        $s2 = $db->prepare("SELECT m.*, mb.nom as membre_nom FROM messages m JOIN membres mb ON mb.id = m.membre_id WHERE m.membre_id IN ($placeholders) ORDER BY m.date_envoi ASC");
        $s2->execute($selectedIds);
        $messages = $s2->fetchAll(PDO::FETCH_ASSOC);
        // Marquer comme lus
        $db->prepare("UPDATE messages SET lu=1 WHERE membre_id IN ($placeholders) AND expediteur='membre' AND lu=0")
           ->execute($selectedIds);
    }
}

// URL courante avec les membres sélectionnés
$currentUrl = 'messages.php?membres=' . implode(',', $selectedIds);

// Alertes GET
if (isset($_GET['deleted']))      $success = 'Message supprimé.';
if (isset($_GET['conv_deleted'])) $success = 'Conversation supprimée.';

$membresAvecMsg = $db->query("
    SELECT m.id, m.nom, m.email, m.photo_profil, COUNT(msg.id) as nb_msg,
    SUM(CASE WHEN msg.expediteur='membre' AND msg.lu=0 THEN 1 ELSE 0 END) as non_lus
    FROM membres m LEFT JOIN messages msg ON msg.membre_id=m.id
    WHERE m.email != 'stratedgepronos@gmail.com'
    GROUP BY m.id HAVING nb_msg > 0
    ORDER BY non_lus DESC, MAX(msg.date_envoi) DESC")->fetchAll();

$tousMembers = $db->query("SELECT id, nom, photo_profil FROM membres WHERE email != 'stratedgepronos@gmail.com' ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages – Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);--glow-green:0 0 20px rgba(255,45,120,0.3);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);height:100vh;display:flex;overflow:hidden;}

    .sidebar{width:240px;background:var(--bg-card);border-right:1px solid var(--border-subtle);height:100vh;position:fixed;top:0;left:0;display:flex;flex-direction:column;z-index:100;}
    .sidebar-logo{padding:1.5rem;border-bottom:1px solid var(--border-subtle);}
    .sidebar-logo img{height:35px;}
    .sidebar-label{font-family:'Space Mono',monospace;font-size:0.6rem;letter-spacing:3px;text-transform:uppercase;color:var(--text-muted);padding:1.5rem 1.5rem 0.5rem;}
    .sidebar nav a{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1.5rem;color:var(--text-secondary);text-decoration:none;font-size:0.95rem;font-weight:500;transition:all 0.2s;border-left:3px solid transparent;}
    .sidebar nav a:hover,.sidebar nav a.active{color:var(--text-primary);background:rgba(255,45,120,0.06);border-left-color:var(--neon-green);}
    .sidebar-footer{margin-top:auto;padding:1.5rem;border-top:1px solid var(--border-subtle);display:flex;flex-direction:column;gap:0.75rem;}
    .sidebar-footer a.site-link{color:var(--text-muted);text-decoration:none;font-size:0.85rem;transition:color 0.2s;}
    .sidebar-footer a.site-link:hover{color:var(--text-primary);}
    .btn-logout{display:flex;align-items:center;justify-content:center;gap:0.5rem;background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);color:var(--neon-green);padding:0.65rem 1rem;border-radius:8px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.9rem;text-decoration:none;transition:all 0.2s;}
    .btn-logout:hover{background:rgba(255,45,120,0.18);color:#fff;}

    .main{margin-left:240px;flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden;}
    .topbar{padding:1.2rem 2rem;border-bottom:1px solid var(--border-subtle);flex-shrink:0;}
    .topbar h1{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:700;}
    .alert{padding:0.7rem 1rem;border-radius:8px;font-size:0.88rem;margin:0.75rem 2rem 0;flex-shrink:0;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.2);color:#00c864;}

    .chat-layout{display:grid;grid-template-columns:270px 1fr;flex:1;overflow:hidden;margin:1.2rem;gap:1.2rem;min-height:0;}

    /* Contacts */
    .contacts-panel{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;}
    .contacts-top{padding:0.9rem;border-bottom:1px solid var(--border-subtle);}
    .btn-new-conv{width:100%;background:linear-gradient(135deg,rgba(255,45,120,0.15),rgba(255,45,120,0.08));border:1px solid rgba(255,45,120,0.3);color:#fff;padding:0.7rem 1rem;border-radius:10px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.9rem;cursor:pointer;transition:all .2s;text-align:left;}
    .btn-new-conv:hover{background:rgba(255,45,120,0.2);border-color:rgba(255,45,120,0.5);}
    .membre-result-item:hover{background:rgba(255,45,120,0.08)!important;color:#fff!important;}
    .contacts-list-inner{overflow-y:auto;flex:1;}
    .contacts-header-label{padding:0.7rem 1rem;font-family:'Space Mono',monospace;font-size:0.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid rgba(255,255,255,0.04);}
    .contact-item{display:flex;align-items:center;gap:0.75rem;padding:0.85rem 1rem;text-decoration:none;color:inherit;border-bottom:1px solid rgba(255,255,255,0.03);transition:all 0.2s;border-left:3px solid transparent;}
    .contact-item:hover,.contact-item.active{background:rgba(255,45,120,0.06);border-left-color:var(--neon-green);}
    .contact-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--neon-green),var(--neon-blue));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.82rem;color:#050810;font-family:'Orbitron',sans-serif;flex-shrink:0;overflow:hidden;}
    .contact-info{flex:1;min-width:0;}
    .contact-name{font-weight:600;font-size:0.86rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .contact-count{font-size:0.73rem;color:var(--text-muted);}
    .badge-unread{background:var(--neon-green);color:white;font-size:0.62rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:10px;flex-shrink:0;}

    /* Chat */
    .chat-panel{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;}
    .chat-header{padding:1rem 1.3rem;border-bottom:1px solid var(--border-subtle);display:flex;align-items:center;gap:1rem;flex-shrink:0;}
    .chat-header-info{flex:1;}
    .chat-header h3{font-family:'Orbitron',sans-serif;font-size:0.92rem;font-weight:700;}
    .chat-header .sub{font-size:0.78rem;color:var(--text-muted);}

    /* Bouton supprimer conversation */
    .btn-del-conv{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);color:#ff6b9d;padding:0.45rem 0.9rem;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:0.4rem;}
    .btn-del-conv:hover{background:rgba(255,45,120,0.2);}
    .btn-add-membre{background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.3);color:#00d4ff;padding:0.45rem 0.9rem;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:0.4rem;}
    .btn-add-membre:hover{background:rgba(0,212,255,0.18);}
    .add-membre-item:hover{background:rgba(255,45,120,0.08)!important;color:#fff!important;}

    .chat-area{flex:1;overflow-y:auto;padding:1.2rem 1.3rem;display:flex;flex-direction:column;gap:0.9rem;}
    .chat-empty{text-align:center;color:var(--text-muted);margin:auto;}
    .msg-row{display:flex;align-items:flex-end;gap:0.5rem;}
    .msg-row.from-me{flex-direction:row-reverse;}
    .msg-bubble{max-width:74%;min-width:60px;}
    .msg-content{padding:0.7rem 1rem;border-radius:12px;font-size:0.93rem;line-height:1.5;position:relative;word-break:break-word;overflow-wrap:break-word;white-space:pre-wrap;}
    .from-me .msg-content{background:linear-gradient(135deg,rgba(255,45,120,0.2),rgba(255,45,120,0.1));border:1px solid rgba(255,45,120,0.2);}
    .from-client .msg-content{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);}
    .msg-meta{font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem;display:flex;align-items:center;gap:0.5rem;}
    .from-me .msg-meta{justify-content:flex-end;}
    .client-label{font-family:'Orbitron',sans-serif;font-size:0.6rem;color:var(--neon-blue);letter-spacing:1px;margin-bottom:0.25rem;}

    /* Bouton supprimer message */
    .btn-del-msg{background:none;border:none;cursor:pointer;color:rgba(255,45,120,0.4);font-size:0.8rem;padding:0.1rem 0.3rem;border-radius:4px;transition:all 0.2s;opacity:0;}
    .msg-row:hover .btn-del-msg{opacity:1;}
    .btn-del-msg:hover{color:#ff2d78;background:rgba(255,45,120,0.1);}

    .chat-compose{padding:0.9rem 1.3rem;border-top:1px solid var(--border-subtle);display:flex;gap:0.8rem;flex-shrink:0;}
    .chat-compose textarea{flex:1;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.75rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.95rem;resize:none;height:65px;outline:none;transition:border 0.3s;}
    .chat-compose textarea:focus{border-color:var(--neon-green);}
    .chat-compose textarea::placeholder{color:var(--text-muted);}
    .btn-send{background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:white;padding:0 1.3rem;height:65px;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.3s;white-space:nowrap;}
    .btn-send:hover{box-shadow:var(--glow-green);}
    .no-chat{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);gap:0.75rem;}
    .no-chat .icon{font-size:3rem;}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="topbar"><h1>💬 Messagerie membres</h1></div>

  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= clean($success) ?></div>
  <?php endif; ?>

  <div class="chat-layout">

    <!-- Contacts -->
    <div class="contacts-panel">
      <div class="contacts-top">
        <button onclick="toggleNewConv()" class="btn-new-conv" id="btnNewConv">
          ✉️ Nouvelle conversation
        </button>
        <!-- Panel recherche/sélection membre -->
        <div id="newConvPanel" style="display:none;margin-top:0.7rem;">
          <input type="text" id="membreSearch" placeholder="Rechercher un membre…"
            oninput="filterMembres(this.value)"
            style="width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:0.6rem 0.9rem;color:#fff;font-family:'Rajdhani',sans-serif;font-size:0.9rem;outline:none;margin-bottom:0.4rem;">
          <div id="membreResults" style="max-height:200px;overflow-y:auto;border:1px solid rgba(255,255,255,0.08);border-radius:8px;background:rgba(10,14,23,0.9);">
            <?php foreach ($tousMembers as $m): ?>
              <a href="?membre=<?= $m['id'] ?>"
                class="membre-result-item"
                data-nom="<?= strtolower(clean($m['nom'])) ?>"
                style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.9rem;color:var(--text-secondary);text-decoration:none;border-bottom:1px solid rgba(255,255,255,0.04);transition:all .15s;font-size:0.88rem;">
                <span style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#ff2d78,#00d4ff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.7rem;color:#050810;flex-shrink:0;"><?= strtoupper(substr($m['nom'],0,1)) ?></span>
                <?= clean($m['nom']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="contacts-list-inner">
        <div class="contacts-header-label">Conversations (<?= count($membresAvecMsg) ?>)</div>
        <?php foreach ($membresAvecMsg as $m): ?>
          <a href="?membres=<?= $m['id'] ?>" class="contact-item <?= in_array($m['id'], $selectedIds)?'active':'' ?>">
            <div class="contact-avatar"><?php if (!empty($m['photo_profil']) && file_exists(__DIR__.'/../uploads/avatars/'.basename($m['photo_profil']))): ?><img src="/uploads/avatars/<?= clean(basename($m['photo_profil'])) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><?= strtoupper(substr($m['nom'],0,1)) ?><?php endif; ?></div>
            <div class="contact-info">
              <div class="contact-name"><?= clean($m['nom']) ?></div>
              <div class="contact-count"><?= $m['nb_msg'] ?> message<?= $m['nb_msg']>1?'s':'' ?></div>
            </div>
            <?php if ($m['non_lus'] > 0): ?>
              <span class="badge-unread"><?= $m['non_lus'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (empty($membresAvecMsg)): ?>
          <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">Aucune conversation</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Chat -->
    <div class="chat-panel">
      <?php if ($selectedMembre): ?>

        <div class="chat-header" style="flex-wrap:wrap;gap:0.6rem;">
          <!-- Avatars groupe -->
          <div style="display:flex;gap:-6px;">
            <?php foreach ($selectedMembres as $sm): ?>
              <div class="contact-avatar" style="margin-right:-6px;border:2px solid var(--bg-card);overflow:hidden;" title="<?= clean($sm['nom']) ?>"><?php if (!empty($sm['photo_profil']) && file_exists(__DIR__.'/../uploads/avatars/'.basename($sm['photo_profil']))): ?><img src="/uploads/avatars/<?= clean(basename($sm['photo_profil'])) ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= strtoupper(substr($sm['nom'],0,1)) ?><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
          <div class="chat-header-info">
            <h3><?php
              $noms = array_map(fn($m)=>clean($m['nom']), $selectedMembres);
              echo implode(', ', $noms);
            ?></h3>
            <div class="sub"><?= count($messages) ?> message<?= count($messages)>1?'s':'' ?> · <?= count($selectedMembres) ?> membre<?= count($selectedMembres)>1?'s':'' ?></div>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;flex-shrink:0;">
            <!-- Bouton Ajouter un membre -->
            <div style="position:relative;">
              <button type="button" onclick="toggleAddMembre()" class="btn-add-membre" id="btnAddMembre">
                👥 Ajouter
              </button>
              <div id="addMembrePanel" style="display:none;position:fixed;width:260px;background:#0d1220;border:1px solid rgba(0,212,255,0.25);border-radius:12px;z-index:9999;box-shadow:0 16px 48px rgba(0,0,0,0.7);padding:0.8rem;">
                <input type="text" id="addMembreSearch" placeholder="Rechercher…" oninput="filterAddMembre(this.value)"
                  style="width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:7px;padding:0.5rem 0.8rem;color:#fff;font-family:'Rajdhani',sans-serif;font-size:0.85rem;outline:none;margin-bottom:0.4rem;">
                <div style="max-height:180px;overflow-y:auto;">
                  <?php foreach ($tousMembers as $tm):
                    $alreadyIn = in_array($tm['id'], $selectedIds);
                  ?>
                  <?php if (!$alreadyIn): ?>
                    <a href="messages.php?membres=<?= implode(',', $selectedIds) ?>,<?= $tm['id'] ?>"
                       class="add-membre-item"
                       data-nom="<?= strtolower(clean($tm['nom'])) ?>"
                       style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.6rem;border-radius:7px;color:var(--text-secondary);text-decoration:none;font-size:0.85rem;transition:background .15s;">
                      <span style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#ff2d78,#00d4ff);display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:900;color:#050810;flex-shrink:0;"><?= strtoupper(substr($tm['nom'],0,1)) ?></span>
                      <?= clean($tm['nom']) ?>
                    </a>
                  <?php else: ?>
                    <div class="add-membre-item" data-nom="<?= strtolower(clean($tm['nom'])) ?>"
                      style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.6rem;border-radius:7px;color:rgba(0,212,106,0.7);font-size:0.85rem;cursor:default;">
                      <span style="width:26px;height:26px;border-radius:50%;background:rgba(0,212,106,0.15);display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:900;color:#00d46a;flex-shrink:0;">✓</span>
                      <?= clean($tm['nom']) ?> <span style="font-size:0.7rem;margin-left:auto;">déjà ajouté</span>
                    </div>
                  <?php endif; ?>
                  <?php endforeach; ?>
                </div>
                <?php if (count($selectedIds) > 1): ?>
                <div style="border-top:1px solid rgba(255,255,255,0.06);margin-top:0.4rem;padding-top:0.4rem;">
                  <?php foreach ($selectedMembres as $sm): ?>
                  <div style="display:flex;align-items:center;justify-content:space-between;padding:0.3rem 0.6rem;font-size:0.8rem;color:var(--text-muted);">
                    <span><?= clean($sm['nom']) ?></span>
                    <?php
                      $remainIds = array_filter($selectedIds, fn($i) => $i !== (int)$sm['id']);
                    ?>
                    <?php if (!empty($remainIds)): ?>
                    <a href="messages.php?membres=<?= implode(',', $remainIds) ?>" style="color:rgba(255,100,100,0.6);font-size:0.72rem;text-decoration:none;" title="Retirer">✕</a>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <!-- Supprimer conversation -->
            <form method="POST" onsubmit="return confirm('Supprimer cette conversation ?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete_conversation">
              <input type="hidden" name="membres_ids" value="<?= implode(',', $selectedIds) ?>">
              <button type="submit" class="btn-del-conv">🗑</button>
            </form>
          </div>
        </div>

        <div class="chat-area" id="chatArea">
          <?php if (empty($messages)): ?>
            <div class="chat-empty">Commencez la conversation ci-dessous.</div>
          <?php else: ?>
            <?php foreach ($messages as $msg): ?>
              <div class="msg-row <?= $msg['expediteur']==='admin'?'from-me':'from-client' ?>">
                <div class="msg-bubble">
                  <?php if ($msg['expediteur']==='membre'): ?>
                    <div class="client-label"><?= clean($selectedMembre['nom']) ?></div>
                  <?php endif; ?>
                  <div class="msg-content"><?= nl2br(clean($msg['contenu'])) ?></div>
                  <div class="msg-meta">
                    <?= date('d/m/Y à H:i', strtotime($msg['date_envoi'])) ?>
                    <!-- Supprimer ce message -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?')">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="action" value="delete_message">
                      <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                      <input type="hidden" name="membre_id" value="<?= $selectedMembre['id'] ?>">
                      <button type="submit" class="btn-del-msg" title="Supprimer ce message">✕</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="send_message">
          <input type="hidden" name="membres_ids" value="<?= implode(',', $selectedIds) ?>">
          <div class="chat-compose">
            <textarea name="contenu" placeholder="Message à <?php echo implode(', ', array_map(fn($m)=>clean($m['nom']), $selectedMembres)); ?>…" required maxlength="2000"></textarea>
            <button type="submit" class="btn-send">Envoyer →</button>
          </div>
        </form>

      <?php else: ?>
        <div class="no-chat">
          <div class="icon">💬</div>
          <p>Sélectionnez un membre pour voir la conversation.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>
<script>
const c=document.getElementById('chatArea');if(c)c.scrollTop=c.scrollHeight;

function toggleAddMembre() {
  const panel = document.getElementById('addMembrePanel');
  const btn = document.getElementById('btnAddMembre');
  if (!panel) return;
  const isOpen = panel.style.display !== 'none';
  if (isOpen) {
    panel.style.display = 'none';
  } else {
    // Position the panel below the button
    const rect = btn.getBoundingClientRect();
    panel.style.display = 'block';
    panel.style.top = (rect.bottom + 6) + 'px';
    panel.style.right = (window.innerWidth - rect.right) + 'px';
    panel.style.left = 'auto';
    setTimeout(function(){ document.getElementById('addMembreSearch').focus(); }, 50);
  }
}

function filterAddMembre(val) {
  const q = val.toLowerCase().trim();
  document.querySelectorAll('#addMembrePanel .add-membre-item').forEach(function(el) {
    el.style.display = (!q || el.dataset.nom.includes(q)) ? 'flex' : 'none';
  });
}

// Close panel on outside click
document.addEventListener('click', function(e) {
  var panel = document.getElementById('addMembrePanel');
  var btn = document.getElementById('btnAddMembre');
  if (panel && panel.style.display !== 'none' && !panel.contains(e.target) && e.target !== btn) {
    panel.style.display = 'none';
  }
});

function toggleNewConv() {
  const panel = document.getElementById('newConvPanel');
  const btn = document.getElementById('btnNewConv');
  const isOpen = panel.style.display !== 'none';
  panel.style.display = isOpen ? 'none' : 'block';
  btn.textContent = isOpen ? '✉️ Nouvelle conversation' : '✕ Fermer';
  if (!isOpen) document.getElementById('membreSearch').focus();
}

function filterMembres(val) {
  const q = val.toLowerCase().trim();
  document.querySelectorAll('.membre-result-item').forEach(el => {
    el.style.display = (!q || el.dataset.nom.includes(q)) ? 'flex' : 'none';
  });
}
</script>
</body>
</html>
