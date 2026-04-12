<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/credits-widget.php';
requireLogin();

$db = getDB();
$membre = getMembre();
$abonnement = getAbonnementActif($membre['id']);
$historique = getHistoriqueAbonnements($membre['id']);
$success = '';
$errors  = [];
$activeTab = $_GET['tab'] ?? 'dashboard';
$currentPage = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_nom') {
        $nom = trim($_POST['nom'] ?? '');
        if (strlen($nom) < 2) { $errors['nom'] = 'Le nom doit faire au moins 2 caractères.'; }
        else { $db->prepare("UPDATE membres SET nom = ? WHERE id = ?")->execute([$nom, $membre['id']]); $_SESSION['membre_nom'] = $nom; $success = 'Nom mis à jour !'; $membre['nom'] = $nom; }
        $activeTab = 'profil';
    }
    if ($action === 'update_email') {
        $newEmail = trim(strtolower($_POST['email'] ?? '')); $mdp = $_POST['password_confirm'] ?? '';
        $stmtP = $db->prepare("SELECT password FROM membres WHERE id = ?"); $stmtP->execute([$membre['id']]); $hEmail = $stmtP->fetchColumn();
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Adresse email invalide.'; }
        elseif (!password_verify($mdp, $hEmail)) { $errors['email'] = 'Mot de passe incorrect.'; }
        elseif ($newEmail === $membre['email']) { $errors['email'] = 'C\'est déjà ton adresse actuelle.'; }
        else { $ex = $db->prepare("SELECT id FROM membres WHERE email = ? AND id != ?"); $ex->execute([$newEmail, $membre['id']]);
          if ($ex->fetch()) { $errors['email'] = 'Adresse déjà utilisée.'; }
          else { $ancien = $membre['email']; $db->prepare("UPDATE membres SET email = ? WHERE id = ?")->execute([$newEmail, $membre['id']]); emailChangementEmail($ancien, $membre['nom'], $newEmail); $success = 'Email mis à jour !'; $membre['email'] = $newEmail; }
        } $activeTab = 'profil';
    }
    if ($action === 'update_password') {
        $ancien = $_POST['ancien_mdp'] ?? ''; $nouveau = $_POST['nouveau_mdp'] ?? ''; $confirm = $_POST['confirm_mdp'] ?? '';
        $stmtH = $db->prepare("SELECT password FROM membres WHERE id = ?"); $stmtH->execute([$membre['id']]); $hashA = $stmtH->fetchColumn();
        if (!password_verify($ancien, $hashA)) { $errors['password'] = 'Mot de passe actuel incorrect.'; }
        elseif (strlen($nouveau) < 8) { $errors['password'] = 'Minimum 8 caractères.'; }
        elseif ($nouveau !== $confirm) { $errors['password'] = 'Les mots de passe ne correspondent pas.'; }
        else { $hash = password_hash($nouveau, PASSWORD_BCRYPT, ['cost' => 12]); $db->prepare("UPDATE membres SET password = ? WHERE id = ?")->execute([$hash, $membre['id']]); $success = 'Mot de passe modifié !'; }
        $activeTab = 'profil';
    }
    if ($action === 'update_avatar') {
        $migOk = false; try { $db->query("SELECT photo_profil FROM membres LIMIT 0"); $migOk = true; } catch (Exception $e) { $errors['avatar'] = 'Migration SQL requise.'; }
        if ($migOk && !empty($_FILES['avatar']['tmp_name'])) {
            $file = $_FILES['avatar']; $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $fi = finfo_open(FILEINFO_MIME_TYPE); $mt = finfo_file($fi, $file['tmp_name']); finfo_close($fi);
            if (!in_array($mt, $allowed)) { $errors['avatar'] = 'Format non autorisé.'; }
            elseif ($file['size'] > 3*1024*1024) { $errors['avatar'] = 'Max 3 Mo.'; }
            else { $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mt]; $dir = __DIR__.'/uploads/avatars/'; if (!is_dir($dir)) mkdir($dir, 0755, true);
              if (!empty($membre['photo_profil'])) { $old = $dir.basename($membre['photo_profil']); if (file_exists($old)) unlink($old); }
              $fn = 'avatar_'.$membre['id'].'_'.time().'.'.$ext;
              if (move_uploaded_file($file['tmp_name'], $dir.$fn)) { $db->prepare("UPDATE membres SET photo_profil = ? WHERE id = ?")->execute([$fn, $membre['id']]); $membre['photo_profil'] = $fn; $success = 'Photo mise à jour !'; }
              else { $errors['avatar'] = 'Erreur upload.'; }
            }
        } elseif ($migOk) { $errors['avatar'] = 'Aucun fichier.'; }
        $activeTab = 'profil';
    }
    if ($action === 'delete_avatar' && !empty($membre['photo_profil'])) {
        $p = __DIR__.'/uploads/avatars/'.basename($membre['photo_profil']); if (file_exists($p)) unlink($p);
        $db->prepare("UPDATE membres SET photo_profil = NULL WHERE id = ?")->execute([$membre['id']]); $membre['photo_profil'] = null; $success = 'Photo supprimée.'; $activeTab = 'profil';
    }
    if ($action === 'update_accepte_emails') {
        $val = isset($_POST['accepte_emails']) ? 1 : 0;
        try {
            $db->prepare("UPDATE membres SET accepte_emails = ? WHERE id = ?")->execute([$val, $membre['id']]);
            $membre['accepte_emails'] = $val;
            $success = $val ? 'Notifications par email activées.' : 'Notifications par email désactivées.';
        } catch (Exception $e) {
            $errors['accepte_emails'] = 'Option non disponible (migration base requise).';
        }
        $activeTab = 'profil';
    }
}

$stmtNl = $db->prepare("SELECT COUNT(*) FROM messages WHERE membre_id = ? AND expediteur = 'admin' AND lu = 0");
$stmtNl->execute([$membre['id']]); $nbNonLus = (int)$stmtNl->fetchColumn();
$welcome = isset($_GET['welcome']); $avatarUrl = getAvatarUrl($membre);
$abo = getAbonnementActif($membre['id']);
$typeLabels = ['daily'=>'⚡ Daily','weekend'=>'📅 Week-End','weekly'=>'🏆 Weekly 7j','rasstoss'=>'👑 Rass-Toss'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><link rel="icon" type="image/png" href="assets/images/mascotte.png">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mon Espace – StratEdge Pronos</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<meta name="apple-mobile-web-app-capable" content="yes"><link rel="apple-touch-icon" href="/assets/images/mascotte.png">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.tab-p{display:none;}.tab-p.active{display:block;}
.alert{border-radius:12px;padding:1rem 1.3rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.8rem;font-size:1rem;font-weight:600;}
.alert-ok{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);color:var(--pink);}
.alert-err{background:rgba(255,100,100,0.08);border:1px solid rgba(255,100,100,0.2);color:#ff6b9d;}
.sec{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:2rem;margin-bottom:1.5rem;}
.sec h3{font-family:'Orbitron',sans-serif;font-size:1.05rem;font-weight:700;margin-bottom:1.3rem;display:flex;align-items:center;gap:0.7rem;}
.sec h3 .dot{width:8px;height:8px;border-radius:50%;background:var(--pink);}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem;margin-bottom:1.5rem;}
.st-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.5rem 1.6rem;}
.st-card .lb{font-family:'Space Mono',monospace;font-size:0.72rem;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);margin-bottom:0.5rem;}
.st-card .val{font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:700;}
.st-card .sub{font-size:0.92rem;color:var(--txt3);margin-top:0.3rem;}
.abo-box{background:linear-gradient(135deg,rgba(255,45,120,0.08),rgba(0,212,255,0.04));border:1px solid rgba(255,45,120,0.25);border-radius:12px;padding:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
.abo-type{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:700;}
.abo-exp{font-size:0.95rem;color:var(--txt3);margin-top:0.25rem;}
.btn-pk{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff!important;padding:0.8rem 1.6rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;display:inline-flex;align-items:center;gap:0.5rem;border:none;cursor:pointer;}
.btn-pk:hover{box-shadow:0 0 25px rgba(255,45,120,0.35);transform:translateY(-2px);}
.no-abo{text-align:center;padding:1.5rem;color:var(--txt3);}
.ht{width:100%;border-collapse:collapse;}
.ht th{text-align:left;font-family:'Space Mono',monospace;font-size:0.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);padding:0.7rem 0.8rem;border-bottom:1px solid rgba(255,255,255,0.05);}
.ht td{padding:0.8rem;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--txt2);font-size:0.95rem;}
.bg-t{padding:0.25rem 0.7rem;border-radius:8px;font-size:0.78rem;font-weight:700;font-family:'Orbitron',sans-serif;letter-spacing:0.5px;white-space:nowrap;display:inline-flex;align-items:center;gap:0.3rem;}
.bg-a{background:rgba(255,45,120,0.1);color:#ff2d78;border:1px solid rgba(255,45,120,0.2);}
.bg-e{background:rgba(255,45,120,0.08);color:var(--txt3);border:1px solid var(--border);}
.bg-r{background:linear-gradient(135deg,rgba(255,200,0,0.15),rgba(255,150,0,0.1));color:#ffd700;border:1px solid rgba(255,200,0,0.4);}
.chat-lk{display:inline-flex;align-items:center;gap:0.7rem;background:linear-gradient(135deg,rgba(255,45,120,0.1),rgba(214,36,95,0.05));border:1px solid rgba(255,45,120,0.3);color:#ff2d78;text-decoration:none;padding:0.9rem 1.5rem;border-radius:12px;font-weight:700;font-size:1rem;transition:all .2s;}
.chat-lk:hover{transform:translateY(-2px);}
.chat-bg{background:var(--pink);color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:900;}
.sav-btn{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(168,85,247,0.1);border:1px solid rgba(168,85,247,0.25);color:var(--purple);padding:0.8rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;transition:all .3s;}
.sav-btn:hover{background:rgba(168,85,247,0.2);transform:translateY(-2px);}

/* ═══ PROFIL ═══ */
.p-grid{display:grid;grid-template-columns:250px 1fr;gap:1.5rem;align-items:start;}
.av-card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:2rem 1.5rem;text-align:center;}
.av-circle{width:100px;height:100px;border-radius:50%;margin:0 auto 1rem;overflow:hidden;border:3px solid rgba(255,45,120,0.4);background:linear-gradient(135deg,var(--pink),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Orbitron',sans-serif;font-size:2.4rem;font-weight:900;color:#fff;box-shadow:0 0 25px rgba(255,45,120,0.2);}
.av-circle img{width:100%;height:100%;object-fit:cover;}
.av-name{font-family:'Orbitron',sans-serif;font-size:1.05rem;font-weight:700;margin-bottom:0.3rem;}
.av-email{color:var(--txt3);font-size:0.85rem;margin-bottom:1rem;word-break:break-all;}
.av-badge{display:inline-flex;align-items:center;gap:0.3rem;padding:0.3rem 0.9rem;border-radius:50px;font-size:0.8rem;font-weight:700;margin-bottom:0.6rem;}
.av-since{color:var(--txt3);font-size:0.78rem;}
.btn-up{display:block;width:100%;background:rgba(255,45,120,0.08);border:1px dashed rgba(255,45,120,0.3);border-radius:10px;padding:0.7rem;color:var(--pink);font-family:'Rajdhani',sans-serif;font-size:0.92rem;font-weight:600;cursor:pointer;text-align:center;margin-bottom:0.4rem;transition:all .2s;}
.btn-up:hover{background:rgba(255,45,120,0.14);}
.btn-del{background:none;border:none;color:var(--txt3);font-size:0.78rem;cursor:pointer;font-family:'Rajdhani',sans-serif;}
.btn-del:hover{color:#ff6b9d;}
.set-col{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.profil-abo-notif-row{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.crd{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:1.7rem 1.8rem;overflow:hidden;position:relative;}
.crd::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--pink),var(--blue));}
.crd-h{display:flex;align-items:center;gap:0.7rem;margin-bottom:1.2rem;}
.crd-h .ico{font-size:1.15rem;}
.crd-h .tl{font-family:'Orbitron',sans-serif;font-size:0.92rem;font-weight:700;}
.fr{display:grid;gap:1rem;}.fr.two{grid-template-columns:1fr 1fr;}
.fg{display:flex;flex-direction:column;gap:0.4rem;}
.fg label{font-size:0.78rem;color:var(--txt3);font-family:'Space Mono',monospace;letter-spacing:1px;text-transform:uppercase;}
.fg input{background:rgba(255,255,255,0.04);border:1px solid var(--border-soft);border-radius:10px;padding:0.8rem 1.1rem;color:var(--txt);font-family:'Rajdhani',sans-serif;font-size:1.05rem;outline:none;transition:border-color .2s;}
.fg input:focus{border-color:rgba(255,45,120,0.5);}
.fg input::placeholder{color:var(--txt3);}
.btn-sv{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff;border:none;border-radius:10px;padding:0.8rem 1.8rem;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:1.05rem;cursor:pointer;transition:all .2s;margin-top:0.5rem;}
.btn-sv:hover{box-shadow:0 4px 18px rgba(255,45,120,0.3);transform:translateY(-1px);}
.hint{font-size:0.82rem;color:var(--txt3);margin-top:0.2rem;}

/* ═══ NOTIFICATIONS ═══ */
.nf-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:2.5rem;margin-bottom:1.5rem;text-align:center;}
.nf-ico{font-size:3.5rem;margin-bottom:1rem;}
.nf-title{font-family:'Orbitron',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:0.6rem;}
.nf-desc{color:var(--txt2);font-size:1rem;line-height:1.6;margin-bottom:1.5rem;max-width:520px;margin-left:auto;margin-right:auto;}
.nf-st{display:inline-flex;align-items:center;gap:0.5rem;padding:0.55rem 1.3rem;border-radius:50px;font-weight:700;font-size:0.92rem;margin-bottom:1.5rem;}
.nf-on{background:rgba(0,212,106,0.1);border:1px solid rgba(0,212,106,0.3);color:#00d46a;}
.nf-off{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);color:var(--pink);}
.nf-blk{background:rgba(255,100,100,0.08);border:1px solid rgba(255,100,100,0.2);color:#ff6b9d;}
.btn-nf{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff;padding:0.9rem 2.5rem;border-radius:12px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:1.1rem;cursor:pointer;border:none;transition:all .3s;text-transform:uppercase;letter-spacing:1px;}
.btn-nf:hover{box-shadow:0 0 30px rgba(255,45,120,0.4);transform:translateY(-2px);}
.btn-nf:disabled{opacity:0.4;cursor:not-allowed;transform:none;box-shadow:none;}
/* Help box for blocked notifs */
.nf-help{background:rgba(255,255,255,0.03);border:1px solid var(--border-soft);border-radius:14px;padding:1.5rem 2rem;margin:1.5rem auto;max-width:560px;text-align:left;display:none;}
.nf-help.show{display:block;}
.nf-help h4{font-family:'Orbitron',sans-serif;font-size:0.92rem;font-weight:700;margin-bottom:1rem;color:var(--pink);}
.nf-help .step{display:flex;align-items:flex-start;gap:0.8rem;margin-bottom:1rem;}
.nf-help .step-n{background:var(--pink);color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:0.78rem;flex-shrink:0;margin-top:0.1rem;}
.nf-help .step-t{font-size:0.95rem;line-height:1.5;color:var(--txt2);}
.nf-help .step-t strong{color:var(--txt);}
.nf-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.5rem;text-align:left;}
.nf-ev{background:rgba(255,255,255,0.03);border:1px solid var(--border-soft);border-radius:12px;padding:1.2rem 1.4rem;}
.nf-ev .ev-i{font-size:1.4rem;margin-bottom:0.4rem;}
.nf-ev .ev-t{font-weight:700;font-size:0.95rem;margin-bottom:0.2rem;}
.nf-ev .ev-d{font-size:0.85rem;color:var(--txt3);line-height:1.4;}
#profil-notifs{overflow-x:auto;-webkit-overflow-scrolling:touch;}
#profil-notifs .nf-grid{grid-template-columns:repeat(4,minmax(200px,1fr));}

@media(max-width:900px){
  .grid3{grid-template-columns:1fr 1fr;}
  .p-grid{grid-template-columns:1fr;}
  .fr.two{grid-template-columns:1fr;}
  .nf-grid{grid-template-columns:1fr;}
  #profil-notifs .nf-grid{grid-template-columns:repeat(4,minmax(200px,1fr));}
  .set-col{grid-template-columns:1fr;}
  .profil-abo-notif-row{grid-template-columns:1fr;}
}
@media(max-width:768px){
  .grid3{grid-template-columns:1fr;}
  #profil-notifs .nf-grid{grid-template-columns:repeat(4,minmax(200px,1fr));}
  .abo-box{flex-direction:column;align-items:flex-start;}
  .btn-pk{width:100%;justify-content:center;padding:0.7rem 1.2rem;font-size:0.92rem;}
  .sec{padding:1.1rem 0.9rem;border-radius:12px;margin-bottom:1rem;}
  .sec h3{font-size:0.92rem;margin-bottom:1rem;}
  .ht{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap;}
  .st-card{padding:1rem 1.1rem;border-radius:10px;}
  .st-card .val{font-size:1.2rem;}
  .st-card .lb{font-size:0.62rem;letter-spacing:1px;}
  .st-card .sub{font-size:0.82rem;}
  .crd{padding:1.1rem 0.9rem;border-radius:12px;}
  .crd-h .tl{font-size:0.82rem;}
  .av-card{padding:1.3rem 0.9rem;border-radius:14px;}
  .av-circle{width:72px;height:72px;font-size:1.6rem;}
  .av-name{font-size:0.92rem;}
  .av-email{font-size:0.78rem;}
  .nf-card{padding:1.5rem 1rem;border-radius:12px;}
  .nf-ico{font-size:2.5rem;}
  .nf-desc{font-size:0.85rem;max-width:none;}
  .nf-title{font-size:0.95rem;}
  .btn-nf{padding:0.8rem 1.5rem;font-size:0.95rem;width:100%;}
  .nf-help{padding:1rem 1.2rem;max-width:none;margin:1rem 0;}
  .nf-help .step-t{font-size:0.85rem;}
  .nf-help .step-n{width:22px;height:22px;font-size:0.7rem;}
  .chat-lk{padding:0.7rem 1rem;font-size:0.9rem;width:100%;justify-content:center;}
  .sav-btn{padding:0.7rem 1rem;font-size:0.9rem;width:100%;justify-content:center;}
  .alert{font-size:0.9rem;padding:0.8rem 1rem;border-radius:10px;}
}
@media(max-width:380px){
  .st-card .val{font-size:1rem;}
  .sec{padding:0.9rem 0.7rem;}
  .crd{padding:0.9rem 0.7rem;}
  .nf-card{padding:1.2rem 0.8rem;}
  .av-circle{width:60px;height:60px;font-size:1.3rem;}
  .btn-nf{font-size:0.85rem;padding:0.7rem 1rem;}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<?php if ($success): ?><div class="alert alert-ok">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($welcome): ?>
<div class="alert alert-ok" style="gap:1rem;"><span style="font-size:1.8rem;">🎉</span><div><strong>Bienvenue <?= clean($membre['nom']) ?> !</strong><br><span style="font-size:0.92rem;color:var(--txt2);">Ton compte est créé. Active les notifications et choisis une formule !</span></div></div>
<?php endif; ?>

<!-- ═══ DASHBOARD ═══ -->
<div class="tab-p <?= $activeTab==='dashboard'?'active':'' ?>" id="tab-dashboard">
<div class="grid3">
  <div class="st-card"><div class="lb">Statut</div><?php if ($abonnement): ?><div class="val" style="color:var(--pink)">Actif</div><div class="sub"><?= $typeLabels[$abonnement['type']] ?? $abonnement['type'] ?></div><?php else: ?><div class="val" style="color:var(--txt3)">Aucun</div><div class="sub">Pas d'abonnement</div><?php endif; ?></div>
  <div class="st-card"><div class="lb">Membre depuis</div><div class="val" style="font-size:1.15rem;"><?= date('d/m/Y', strtotime($membre['date_inscription'])) ?></div><div class="sub"><?= clean($membre['email']) ?></div></div>
  <div class="st-card"><div class="lb">Achats</div><div class="val"><?= count($historique) ?></div><div class="sub">abonnement<?= count($historique)>1?'s':'' ?></div></div>
</div>
<div class="sec"><h3><span class="dot"></span> 💎 Mes crédits paris</h3>
<?= stratedge_render_credits_widget((int)$membre['id']) ?>
</div>
<div class="sec"><h3><span class="dot"></span> Abonnement actif</h3>
<?php if ($abonnement): ?>
<div class="abo-box"><div><div class="abo-type"><?= $typeLabels[$abonnement['type']] ?? $abonnement['type'] ?></div>
<div class="abo-exp"><?php if ($abonnement['type']==='daily'): ?>⚡ Actif jusqu'au prochain bet<?php elseif($abonnement['type']==='rasstoss'): ?>👑 Accès à vie<?php else: ?>📅 Expire le <?= date('d/m/Y à H:i', strtotime($abonnement['date_fin'])) ?><?php endif; ?></div>
</div><a href="/bets.php" class="btn-pk">📊 Voir mes bets →</a></div>
<?php else: ?><div class="no-abo"><p style="font-size:1.2rem;margin-bottom:0.5rem;">Aucun abonnement actif</p><p style="margin-bottom:1rem;">Souscris pour accéder aux bets.</p><a href="/#pricing" class="btn-pk">Voir les formules →</a></div><?php endif; ?>
</div>
<div class="sec"><h3><span class="dot"></span> Historique des achats</h3>
<?php if (empty($historique)): ?><p style="color:var(--txt3);text-align:center;padding:1rem;">Aucun achat.</p>
<?php else: ?><div style="overflow-x:auto;"><table class="ht"><thead><tr><th>Formule</th><th>Achat</th><th>Expiration</th><th>Montant</th><th>Statut</th></tr></thead><tbody>
<?php foreach ($historique as $h): $isA=$h['actif']&&($h['type']==='daily'||$h['type']==='rasstoss'||strtotime($h['date_fin'])>time()); ?>
<tr><td><span style="font-family:'Orbitron',sans-serif;font-size:0.88rem;"><?= $typeLabels[$h['type']]??$h['type'] ?></span></td>
<td><?= date('d/m/Y H:i',strtotime($h['date_achat'])) ?></td>
<td><?= $h['type']==='rasstoss'?'♾️ À vie':($h['date_fin']?date('d/m/Y H:i',strtotime($h['date_fin'])):'Prochain bet') ?></td>
<td style="color:var(--pink);font-weight:700;"><?= number_format($h['montant'],2) ?>€</td>
<td><?php if($h['type']==='rasstoss'):?><span class="bg-t bg-r">👑 À vie</span><?php else:?><span class="bg-t <?= $isA?'bg-a':'bg-e' ?>"><?= $isA?'✓ Actif':'Expiré' ?></span><?php endif;?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<div class="sec"><h3><span class="dot"></span> Messagerie & Support</h3>
<div style="display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;">
  <a href="/chat.php" class="chat-lk">💬 Ouvrir le chat<?php if ($nbNonLus>0): ?><span class="chat-bg"><?= $nbNonLus ?></span><?php endif; ?></a>
  <a href="/sav.php" class="sav-btn">🎫 Ticket SAV</a>
</div></div>
</div>

<!-- ═══ PROFIL ═══ -->
<div class="tab-p <?= $activeTab==='profil'?'active':'' ?>" id="tab-profil">
<div class="p-grid">
<div class="av-card">
  <div class="av-circle"><?php if($avatarUrl):?><img src="<?=$avatarUrl?>?v=<?=time()?>" alt=""><?php else:?><?=strtoupper(substr($membre['nom'],0,1))?><?php endif;?></div>
  <div class="av-name"><?= htmlspecialchars($membre['nom']) ?></div>
  <div class="av-email"><?= htmlspecialchars($membre['email']) ?></div>
  <span class="av-badge <?=$abo?'bg-a':'bg-e'?>"><?= $abo?'⚡ '.strtoupper($abo['type']):'— Aucun abo' ?></span>
  <div class="av-since">Membre depuis <?= date('M Y',strtotime($membre['date_inscription'])) ?></div>
  <div style="margin-top:1.2rem;">
    <?php if(!empty($errors['avatar'])):?><p style="color:#ff6b9d;font-size:0.82rem;margin-bottom:0.5rem;"><?=htmlspecialchars($errors['avatar'])?></p><?php endif;?>
    <form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="update_avatar">
    <label class="btn-up">📷 Changer la photo<input type="file" name="avatar" accept="image/*" style="display:none;" onchange="this.form.submit()"></label></form>
    <?php if($avatarUrl):?><form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="delete_avatar">
    <button type="submit" class="btn-del" onclick="return confirm('Supprimer ?')">🗑️ Supprimer</button></form><?php endif;?>
    <p class="hint" style="margin-top:0.4rem;">JPG, PNG, WEBP — Max 3 Mo</p>
  </div>
</div>
<div class="set-col">
  <div class="crd"><div class="crd-h"><span class="ico">✏️</span><span class="tl">Nom d'affichage</span></div>
  <?php if(!empty($errors['nom'])):?><div class="alert alert-err">⚠️ <?=htmlspecialchars($errors['nom'])?></div><?php endif;?>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="update_nom">
  <div class="fr"><div class="fg"><label>Nom</label><input type="text" name="nom" value="<?=htmlspecialchars($membre['nom'])?>" required minlength="2" maxlength="50"></div></div>
  <button type="submit" class="btn-sv">Enregistrer</button></form></div>

  <div class="crd"><div class="crd-h"><span class="ico">📧</span><span class="tl">Adresse email</span></div>
  <?php if(!empty($errors['email'])):?><div class="alert alert-err">⚠️ <?=htmlspecialchars($errors['email'])?></div><?php endif;?>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="update_email">
  <div class="fr"><div class="fg"><label>Nouvelle adresse</label><input type="email" name="email" value="<?=htmlspecialchars($membre['email'])?>" required></div>
  <div class="fg"><label>Mot de passe actuel</label><input type="password" name="password_confirm" placeholder="Confirme ton mot de passe" required><span class="hint">Requis pour valider</span></div></div>
  <button type="submit" class="btn-sv">Changer l'email</button></form></div>

  <div class="crd"><div class="crd-h"><span class="ico">🔑</span><span class="tl">Mot de passe</span></div>
  <?php if(!empty($errors['password'])):?><div class="alert alert-err">⚠️ <?=htmlspecialchars($errors['password'])?></div><?php endif;?>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="update_password">
  <div class="fr"><div class="fg"><label>Mot de passe actuel</label><input type="password" name="ancien_mdp" placeholder="••••••••" required></div>
  <div class="fr two"><div class="fg"><label>Nouveau</label><input type="password" name="nouveau_mdp" placeholder="Min. 8 caractères" minlength="8" required id="newPwd"></div>
  <div class="fg"><label>Confirmer</label><input type="password" name="confirm_mdp" placeholder="••••••••" required id="confirmPwd"></div></div></div>
  <div id="pwdMatch" style="font-size:0.82rem;margin-top:0.3rem;display:none;"></div>
  <button type="submit" class="btn-sv">Changer le mot de passe</button></form></div>

  <div class="crd"><div class="crd-h"><span class="ico">📧</span><span class="tl">Préférences email</span></div>
  <p style="color:var(--txt3);font-size:0.9rem;margin-bottom:1rem;">Recevoir les notifications par email (nouveaux bets, résultats, messages). Conformité RGPD — vous pouvez vous désinscrire à tout moment.</p>
  <?php if(!empty($errors['accepte_emails'])):?><div class="alert alert-err">⚠️ <?=htmlspecialchars($errors['accepte_emails'])?></div><?php endif;?>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="update_accepte_emails">
  <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;">
    <input type="checkbox" name="accepte_emails" value="1" <?= !empty($membre['accepte_emails']) ? 'checked' : '' ?>>
    <span>Recevoir les notifications par email</span>
  </label>
  <button type="submit" class="btn-sv" style="margin-top:0.75rem;">Enregistrer</button></form></div>

  <div class="profil-abo-notif-row">
  <div class="crd"><div class="crd-h"><span class="ico">⚡</span><span class="tl">Mon abonnement</span></div>
  <?php if($abo):?>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div><div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:700;color:var(--pink);"><?=strtoupper($abo['type'])?></div>
    <div style="color:var(--txt3);font-size:0.9rem;margin-top:0.2rem;"><?=$abo['type']==='daily'?'Prochain bet':($abo['type']==='rasstoss'?'À vie':'Expire le '.date('d/m/Y',strtotime($abo['date_fin'])))?></div></div>
    <span class="av-badge bg-a">✅ Actif</span></div>
  <?php else:?>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div style="color:var(--txt3);font-size:0.95rem;">Pas d'abonnement actif.</div>
    <a href="/#pricing" class="btn-pk" style="padding:0.55rem 1.3rem;font-size:0.9rem;">Voir les offres →</a></div>
  <?php endif;?></div>

  <div class="crd"><div class="crd-h"><span class="ico">🔔</span><span class="tl">Notifications Push</span></div>
  <div style="text-align:center;">
    <p style="color:var(--txt2);font-size:0.92rem;line-height:1.6;margin-bottom:1rem;">Reçois une alerte instantanée dès qu'un bet est posté, qu'un résultat tombe, ou qu'un message t'attend.</p>
    <div id="nfSt" class="nf-st nf-off">⏳ Vérification...</div><br>
    <button class="btn-nf" id="btnNf" onclick="togglePush()" disabled>Activer les notifications</button>
    <div class="nf-help" id="nfHelp">
      <h4>🔓 Comment débloquer ?</h4>
      <div class="step"><span class="step-n">1</span><div class="step-t"><strong>PC :</strong> Clique sur 🔒 à gauche de l'URL</div></div>
      <div class="step"><span class="step-n">2</span><div class="step-t">Change <strong>Notifications</strong> → <strong>Autoriser</strong></div></div>
      <div class="step"><span class="step-n">3</span><div class="step-t"><strong>Recharge</strong> la page (F5)</div></div>
      <div style="border-top:1px solid var(--border-soft);margin-top:0.8rem;padding-top:0.8rem;">
      <div class="step"><span class="step-n">📱</span><div class="step-t"><strong>iPhone :</strong> Safari → Partager → Sur l'écran d'accueil</div></div>
      <div class="step"><span class="step-n">📱</span><div class="step-t"><strong>Android :</strong> Chrome → ⋮ → Paramètres du site → Notifications → Autoriser</div></div>
      </div>
    </div>
  </div></div>
  </div>

</div>
</div>
</div>

</main></div>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
<script>
function sw(t){document.querySelectorAll('.tab-p').forEach(function(p){p.classList.remove('active');});var p=document.getElementById('tab-'+t);if(p)p.classList.add('active');history.replaceState(null,'',window.location.pathname+'?tab='+t);}
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
var np=document.getElementById('newPwd'),cp=document.getElementById('confirmPwd'),pm=document.getElementById('pwdMatch');
function ck(){if(!cp.value){pm.style.display='none';return;}pm.style.display='block';if(np.value===cp.value){pm.textContent='✓ Correspondent';pm.style.color='#ff2d78';}else{pm.textContent='✗ Ne correspondent pas';pm.style.color='#ff6b9d';}}
if(np&&cp){np.addEventListener('input',ck);cp.addEventListener('input',ck);}

var VK='<?= defined("VAPID_PUBLIC_KEY")?VAPID_PUBLIC_KEY:"" ?>',pSub=null;
function u2a(b){var p='='.repeat((4-b.length%4)%4);var r=atob((b+p).replace(/-/g,'+').replace(/_/g,'/'));return new Uint8Array([...r].map(function(c){return c.charCodeAt(0);}));}
function upUI(s){var st=document.getElementById('nfSt'),b=document.getElementById('btnNf'),h=document.getElementById('nfHelp');
if(!st||!b||!h)return;
h.classList.remove('show');
if(s==='active'){st.className='nf-st nf-on';st.innerHTML='✅ Activées';b.textContent='Désactiver';b.disabled=false;}
else if(s==='denied'){st.className='nf-st nf-blk';st.innerHTML='🚫 Bloquées';b.textContent='Bloqué';b.disabled=true;h.classList.add('show');}
else if(s==='unsupported'){st.className='nf-st nf-blk';st.innerHTML='❌ Non disponible';b.textContent='Non disponible';b.disabled=true;}
else{st.className='nf-st nf-off';st.innerHTML='Pas encore activées';b.textContent='🔔 Activer';b.disabled=false;}}
async function chkPush(){if(!('serviceWorker' in navigator)||!('PushManager' in window)||!VK||VK==='VOTRE_CLE_PUBLIQUE_VAPID_ICI'){upUI('unsupported');return;}
try{var r=await navigator.serviceWorker.register('/sw.js');var s=await r.pushManager.getSubscription();pSub=s;
if(Notification.permission==='denied')upUI('denied');else if(s)upUI('active');else upUI('inactive');}catch(e){upUI('unsupported');}}
async function togglePush(){var b=document.getElementById('btnNf');b.disabled=true;b.textContent='⏳ En cours...';
try{var r=await navigator.serviceWorker.ready;
if(pSub){await pSub.unsubscribe();await fetch('/push-subscribe.php',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({endpoint:pSub.endpoint})});pSub=null;upUI('inactive');}
else{var pm=await Notification.requestPermission();if(pm!=='granted'){upUI(pm==='denied'?'denied':'inactive');return;}
var s=await r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:u2a(VK)});
await fetch('/push-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(s)});pSub=s;upUI('active');}}
catch(e){console.log('[Push]',e.message);upUI('inactive');}}
chkPush();
</script>
</body>
</html>
