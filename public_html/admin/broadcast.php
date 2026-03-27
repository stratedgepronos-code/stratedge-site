<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../includes/broadcast-sanitize.php';
requireAdmin();
$db = getDB();
$pageActive = 'broadcast';

$success = '';
$error   = '';
$stats   = ['push' => 0, 'email' => 0, 'total' => 0];

// ── Traitement envoi ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $titre       = trim($_POST['titre'] ?? '');
    $message     = $_POST['message'] ?? '';
    $message     = is_string($message) ? $message : '';
    $messageHtml = isset($_POST['message_html']);
    $cibles      = $_POST['cibles'] ?? [];   // tableau (multi-checkbox)
    $envoyerPush = isset($_POST['envoyer_push']);
    $envoyerMail = isset($_POST['envoyer_mail']);
    $url         = trim($_POST['url'] ?? '/dashboard.php');
    $isTest      = in_array('test', $cibles);

    $msgMaxPlain = 200;
    $msgMaxHtml  = 60000;
    $error       = '';

    if ($messageHtml) {
        if (strlen($message) > $msgMaxHtml) {
            $error = 'Message HTML trop long (max ' . $msgMaxHtml . ' caractères).';
        }
    } else {
        $message = trim($message);
        if (strlen($message) > $msgMaxPlain) {
            $error = 'Message trop long (max ' . $msgMaxPlain . ' caractères).';
        }
    }

    $messageVide = $messageHtml ? (trim($message) === '') : ($message === '');
    if (!$titre || $messageVide) {
        $error = $error ?: 'Le titre et le message sont obligatoires.';
    }

    if ($error === '' && !$envoyerPush && !$envoyerMail) {
        $error = 'Choisis au moins un canal : Push et/ou Email.';
    }
    if ($error === '' && empty($cibles)) {
        $error = 'Choisis au moins un groupe de destinataires.';
    }

    if ($error === '') {
        // ── Mode test : une seule adresse ───────────────────────
        if ($isTest) {
            $testEmail = trim($_POST['test_email'] ?? '');
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email de test invalide.';
                goto end_broadcast;
            }
            $membres = [['id' => 0, 'nom' => 'Test', 'email' => $testEmail]];
        } else {
            // ── Multi-sélection : on collecte sans doublons ──────
            $membreIds = [];
            $membres   = [];

            foreach ($cibles as $cible) {
                $cible = trim($cible);
                if ($cible === 'tous') {
                    $rows = $db->query("
                        SELECT DISTINCT m.id, m.nom, m.email
                        FROM membres m
                        WHERE m.actif = 1 AND m.banni = 0
                    ")->fetchAll();
                } elseif ($cible === 'abonnes') {
                    $rows = $db->query("
                        SELECT DISTINCT m.id, m.nom, m.email
                        FROM membres m
                        INNER JOIN abonnements a ON a.membre_id = m.id
                        WHERE a.actif = 1
                          AND (a.type IN ('daily','rasstoss') OR a.date_fin > NOW())
                          AND m.actif = 1 AND m.banni = 0
                    ")->fetchAll();
                } elseif (in_array($cible, ['daily','weekly','weekend','rasstoss'])) {
                    $stmt = $db->prepare("
                        SELECT DISTINCT m.id, m.nom, m.email
                        FROM membres m
                        INNER JOIN abonnements a ON a.membre_id = m.id
                        WHERE a.type = ? AND a.actif = 1
                          AND (a.type IN ('daily','rasstoss') OR a.date_fin > NOW())
                          AND m.actif = 1 AND m.banni = 0
                    ");
                    $stmt->execute([$cible]);
                    $rows = $stmt->fetchAll();
                } else {
                    $rows = [];
                }
                // Dédoublonnage par id
                foreach ($rows as $r) {
                    if (!isset($membreIds[$r['id']])) {
                        $membreIds[$r['id']] = true;
                        $membres[] = $r;
                    }
                }
            }
        }

        $stats['total'] = count($membres);

        $titreMailSafe = htmlspecialchars($titre, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bodyPush      = broadcast_message_to_plain($message, $messageHtml);
        if (function_exists('mb_strlen') && mb_strlen($bodyPush) > 240) {
            $bodyPush = mb_substr($bodyPush, 0, 237) . '…';
        } elseif (strlen($bodyPush) > 240) {
            $bodyPush = substr($bodyPush, 0, 237) . '…';
        }

        // ── Envoi PUSH ──────────────────────────────────────────
        if ($envoyerPush && function_exists('envoyerPush')) {
            foreach ($membres as $m) {
                try {
                    envoyerPush((int)$m['id'], $titre, $bodyPush, $url, 'broadcast-' . time());
                    $stats['push']++;
                } catch (Exception $e) { /* silencieux */ }
            }
        }

        // ── Envoi EMAIL ─────────────────────────────────────────
        if ($envoyerMail) {
            $msgEmailBlock = $messageHtml
                ? sanitize_broadcast_email_html($message)
                : nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $urlBtn    = ($url !== '' && isset($url[0]) && $url[0] === '/') ? $url : '/' . ltrim((string)$url, '/');
            $urlBtnEsc = htmlspecialchars($urlBtn, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach ($membres as $m) {
                try {
                    $nomSafe = htmlspecialchars($m['nom'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $contenu = "
<h2 style=\"color:#ffffff;font-size:22px;margin:0 0 12px;\">{$titreMailSafe}</h2>
<p style=\"color:#ffffff;font-size:15px;line-height:1.7;\">
  Bonjour <strong style=\"color:#ffffff;\">{$nomSafe}</strong>,
</p>
<div style=\"background:#1a1a2e;border:1px solid #333355;border-radius:12px;padding:18px 20px;margin:16px 0;\">
  <div style=\"color:#ffffff;font-size:15px;line-height:1.8;margin:0;\">{$msgEmailBlock}</div>
</div>
<div style=\"text-align:center;margin:24px 0;\">
  <a href=\"https://stratedgepronos.fr{$urlBtnEsc}\"
     style=\"display:inline-block;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;text-decoration:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:15px;letter-spacing:1px;text-transform:uppercase;\">
    Voir sur StratEdge
  </a>
</div>
<p style=\"color:#ffffff;font-size:12px;text-align:center;margin-top:12px;\">
  Vous recevez cet email car vous etes membre de StratEdge Pronos.<br>
  Pour vous desabonner des emails, contactez le support via le SAV.
</p>";
                    envoyerEmail($m['email'], $titre . ' — StratEdge Pronos', emailTemplate($titreMailSafe, $contenu));
                    $stats['email']++;
                } catch (Exception $e) { /* silencieux */ }
            }
        }

        $canaux = [];
        if ($envoyerPush) $canaux[] = "🔔 Push: {$stats['push']}";
        if ($envoyerMail) $canaux[] = "📧 Email: {$stats['email']}";
        $success = "Envoi terminé ! {$stats['total']} destinataire(s). " . implode(' · ', $canaux);
        end_broadcast:;
    }
}

// ── Statistiques membres ─────────────────────────────────────
$nbTotal = (int)$db->query("SELECT COUNT(*) FROM membres WHERE actif=1 AND banni=0")->fetchColumn();
$packStats = [];
foreach (['daily','weekly','weekend','rasstoss'] as $p) {
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT m.id) FROM membres m
        INNER JOIN abonnements a ON a.membre_id = m.id
        WHERE a.type = ? AND a.actif = 1
          AND (a.type IN ('daily','rasstoss') OR a.date_fin > NOW())
          AND m.actif = 1 AND m.banni = 0
    ");
    $stmt->execute([$p]);
    $packStats[$p] = (int)$stmt->fetchColumn();
}
$nbAbonnes = (int)$db->query("
    SELECT COUNT(DISTINCT m.id) FROM membres m
    INNER JOIN abonnements a ON a.membre_id = m.id
    WHERE a.actif = 1 AND (a.type IN ('daily','rasstoss') OR a.date_fin > NOW())
    AND m.actif = 1 AND m.banni = 0
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Broadcast — StratEdge Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,255,255,0.07);--border-pink:rgba(255,45,120,0.2);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;display:flex;}
  </style>
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <style>
    .page-header{margin-bottom:2rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:900;color:#fff;}
    .page-header p{color:var(--text-muted);font-size:0.9rem;margin-top:0.4rem;}
    .two-cols{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;}
    .card-title{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;color:#ff2d78;margin-bottom:1.2rem;display:flex;align-items:center;gap:0.5rem;}
    .form-group{margin-bottom:1.2rem;}
    .form-group label{display:block;font-size:0.85rem;color:var(--text-muted);margin-bottom:0.4rem;font-weight:600;}
    .form-input,.form-textarea,.form-select{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border-subtle);color:var(--text-primary);padding:0.75rem 0.9rem;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:0.95rem;transition:border-color .2s;}
    .form-input:focus,.form-textarea:focus,.form-select:focus{outline:none;border-color:var(--border-pink);}
    .form-textarea{resize:vertical;min-height:100px;}
    .char-count{font-size:0.75rem;color:var(--text-muted);text-align:right;margin-top:0.2rem;}
    /* Cibles */
    .cibles-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;}
    .cible-check{display:none;}
    .cible-label{display:flex;align-items:center;gap:0.6rem;background:rgba(255,255,255,0.03);border:1px solid var(--border-subtle);border-radius:10px;padding:0.7rem 0.9rem;cursor:pointer;transition:all .2s;font-size:0.88rem;font-weight:600;}
    .cible-label:hover{background:rgba(255,45,120,0.06);}
    .cible-check:checked + .cible-label{background:rgba(255,45,120,0.1);border-color:rgba(255,45,120,0.4);color:#ff2d78;}
    .cible-count{margin-left:auto;font-size:0.72rem;background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:5px;color:var(--text-muted);}
    .cible-check:checked + .cible-label .cible-count{background:rgba(255,45,120,0.15);color:#ff2d78;}
    /* Canaux */
    .canaux-row{display:flex;gap:0.8rem;}
    .canal-check{display:none;}
    .canal-label{flex:1;display:flex;align-items:center;justify-content:center;gap:0.5rem;border:1px solid var(--border-subtle);border-radius:10px;padding:0.8rem;cursor:pointer;transition:all .2s;font-size:0.9rem;font-weight:700;}
    .canal-label:hover{background:rgba(255,45,120,0.06);}
    .canal-check:checked + .canal-label{background:rgba(255,45,120,0.12);border-color:rgba(255,45,120,0.4);color:#ff2d78;}
    /* Preview */
    .preview-box{background:rgba(255,45,120,0.04);border:1px solid rgba(255,45,120,0.15);border-radius:12px;padding:1.2rem;margin-top:1rem;}
    .preview-phone{background:#0d1220;border-radius:12px;padding:1rem;font-size:0.85rem;}
    .notif-preview{background:#1a1a2e;border-radius:10px;padding:0.8rem 1rem;display:flex;align-items:flex-start;gap:0.7rem;}
    .notif-icon{width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#ff2d78,#d6245f);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
    .notif-body{flex:1;}
    .notif-title{font-weight:700;font-size:0.82rem;color:#f0f4f8;margin-bottom:0.2rem;}
    .notif-msg{font-size:0.75rem;color:#8a9bb0;line-height:1.4;}
    /* Bouton */
    .btn-send{width:100%;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;border:none;padding:1rem;border-radius:12px;font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:0.5rem;letter-spacing:1px;}
    .btn-send:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(255,45,120,0.4);}
    .btn-send:disabled{opacity:0.5;cursor:not-allowed;transform:none;}
    /* Stats */
    .stat-row{display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid var(--border-subtle);}
    .stat-row:last-child{border-bottom:none;}
    .stat-label{font-size:0.88rem;color:var(--text-secondary);}
    .stat-val{font-family:'Orbitron',sans-serif;font-weight:700;font-size:0.95rem;color:#ff2d78;}
    /* Alert */
    .alert{padding:0.9rem 1.2rem;border-radius:10px;margin-bottom:1.5rem;font-weight:600;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.3);color:#00c864;}
    .alert-error{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.3);color:#ff6b9d;}
    /* Confirm overlay */
    .confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
    .confirm-overlay.open{display:flex;}
    .confirm-box{background:var(--bg-card);border:1px solid var(--border-pink);border-radius:16px;padding:2rem;width:90%;max-width:420px;text-align:center;}
    .confirm-box h3{font-family:'Orbitron',sans-serif;font-size:1rem;color:#ff2d78;margin-bottom:0.8rem;}
    .confirm-box p{color:var(--text-muted);font-size:0.9rem;margin-bottom:1.5rem;line-height:1.6;}
    .confirm-actions{display:flex;gap:0.8rem;}
    .btn-confirm{flex:1;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;border:none;padding:0.85rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;}
    .btn-confirm-cancel{flex:1;background:rgba(255,255,255,0.05);border:1px solid var(--border-subtle);color:var(--text-muted);padding:0.85rem;border-radius:10px;cursor:pointer;font-family:'Rajdhani',sans-serif;font-weight:700;}
  </style>
</head>
<body>

<div class="main" style="padding:2rem;">

  <div class="page-header">
    <h1>📣 Broadcast — Push & Email</h1>
    <p>Envoie une notification push et/ou un email à tes membres selon leur pack.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="two-cols">

    <!-- FORMULAIRE -->
    <div class="card">
      <div class="card-title">📝 Composer le message</div>
      <form id="broadcastForm" method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group">
          <label>Titre de la notification *</label>
          <input class="form-input" type="text" name="titre" id="inputTitre" maxlength="80"
                 placeholder="Ex: 🔥 Nouveau bet disponible !" required
                 value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>"
                 oninput="updatePreview()">
          <div class="char-count"><span id="countTitre">0</span>/80</div>
        </div>

        <div class="form-group">
          <label>Message *</label>
          <textarea class="form-textarea" name="message" id="inputMsg" maxlength="200"
                    placeholder="Ex: Un nouveau bet Safe est disponible. Connecte-toi pour le voir ! (ou du HTML si option cochée)" required
                    oninput="updatePreview()"><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="char-count"><span id="countMsg">0</span>/<span id="countMsgMax">200</span></div>
        </div>

        <div class="form-group" style="margin-top:-0.5rem;">
          <label class="cible-label" style="cursor:pointer;display:flex;align-items:flex-start;gap:0.65rem;text-align:left;white-space:normal;">
            <input type="checkbox" name="message_html" id="chkMsgHtml" value="1" style="margin-top:0.2rem;flex-shrink:0;"
                   <?= isset($_POST['message_html']) ? 'checked' : '' ?> onchange="toggleMsgHtmlMode()">
            <span><strong>HTML dans l’email</strong> — mise en forme, tableaux, images (<code>https://</code> uniquement). Les <strong>push</strong> restent en <strong>texte brut</strong> (aperçu ci-contre).</span>
          </label>
          <div id="msgHtmlHelp" style="display:none;font-size:0.78rem;color:var(--text-muted);margin-top:0.55rem;line-height:1.55;padding-left:1.6rem;">
            Balises filtrées : titres, paragraphes, listes, liens, tableaux, <code>&lt;img src="https://…"&gt;</code>. Pas de JavaScript, pas de <code>data:</code> / <code>javascript:</code>.
          </div>
        </div>

        <div class="form-group">
          <label>Lien au clic (optionnel)</label>
          <input class="form-input" type="text" name="url" value="<?= htmlspecialchars($_POST['url'] ?? '/bets.php') ?>"
                 placeholder="/bets.php">
        </div>

        <!-- Champ email test -->
        <div class="form-group" id="testEmailGroup" style="display:none;">
          <label>📧 Adresse email de test (ex: adresse mail-tester.com)</label>
          <input class="form-input" type="email" name="test_email" id="testEmailInput"
                 placeholder="test-xyz@srv1.mail-tester.com"
                 value="<?= htmlspecialchars($_POST['test_email'] ?? '') ?>">
          <div style="font-size:0.78rem;color:#00d4ff;margin-top:0.3rem;">
            💡 Utilise l'adresse générée sur <a href="https://www.mail-tester.com" target="_blank" style="color:#00d4ff;">mail-tester.com</a> pour tester ta délivrabilité
          </div>
        </div>

        <div class="form-group">
          <label>📡 Canaux d'envoi</label>
          <div class="canaux-row">
            <input type="checkbox" class="canal-check" name="envoyer_push" id="chkPush"
                   <?= isset($_POST['envoyer_push']) || !isset($_POST['csrf_token']) ? 'checked' : '' ?>>
            <label class="canal-label" for="chkPush">🔔 Push</label>
            <input type="checkbox" class="canal-check" name="envoyer_mail" id="chkMail"
                   <?= isset($_POST['envoyer_mail']) ? 'checked' : '' ?>>
            <label class="canal-label" for="chkMail">📧 Email</label>
          </div>
        </div>

        <div class="form-group">
          <label>🎯 Destinataires</label>
          <div class="cibles-grid">
            <input type="checkbox" class="cible-check" name="cibles[]" id="cTest" onchange="onCibleChange(this)" value="test"
                   <?= ($_POST['cible'] ?? '')==='test' ? 'checked' : '' ?>
                   onchange="toggleTestEmail()">
            <label class="cible-label" for="cTest" style="grid-column:1/-1;border-color:rgba(0,212,255,0.3);color:#00d4ff;">
              🧪 Mode test (adresse unique)
            </label>

            <input type="checkbox" class="cible-check" name="cibles[]" id="cTous" onchange="onCibleChange(this)" value="tous"
                   <?= (!isset($_POST['cibles']) || in_array('tous', $_POST['cibles'] ?? [])) ? 'checked' : '' ?>
                   onchange="toggleTestEmail()">
            <label class="cible-label" for="cTous">
              👥 Tous les membres
              <span class="cible-count"><?= $nbTotal ?></span>
            </label>

            <input type="checkbox" class="cible-check" name="cibles[]" id="cAbonnes" onchange="onCibleChange(this)" value="abonnes"
                   <?= ($_POST['cible'] ?? '')==='abonnes' ? 'checked' : '' ?>>
            <label class="cible-label" for="cAbonnes">
              🔓 Abonnés actifs
              <span class="cible-count"><?= $nbAbonnes ?></span>
            </label>

            <input type="checkbox" class="cible-check" name="cibles[]" id="cDaily" onchange="onCibleChange(this)" value="daily"
                   <?= ($_POST['cible'] ?? '')==='daily' ? 'checked' : '' ?>>
            <label class="cible-label" for="cDaily">
              ⚡ Pack Daily
              <span class="cible-count"><?= $packStats['daily'] ?></span>
            </label>

            <input type="checkbox" class="cible-check" name="cibles[]" id="cWeekly" onchange="onCibleChange(this)" value="weekly"
                   <?= ($_POST['cible'] ?? '')==='weekly' ? 'checked' : '' ?>>
            <label class="cible-label" for="cWeekly">
              📅 Pack Weekly
              <span class="cible-count"><?= $packStats['weekly'] ?></span>
            </label>

            <input type="checkbox" class="cible-check" name="cibles[]" id="cWeekend" onchange="onCibleChange(this)" value="weekend"
                   <?= ($_POST['cible'] ?? '')==='weekend' ? 'checked' : '' ?>>
            <label class="cible-label" for="cWeekend">
              🎉 Pack Week-End
              <span class="cible-count"><?= $packStats['weekend'] ?></span>
            </label>

            <input type="checkbox" class="cible-check" name="cibles[]" id="cRass" onchange="onCibleChange(this)" value="rasstoss"
                   <?= ($_POST['cible'] ?? '')==='rasstoss' ? 'checked' : '' ?>>
            <label class="cible-label" for="cRass">
              👑 Rass-Toss
              <span class="cible-count"><?= $packStats['rasstoss'] ?></span>
            </label>
          </div>
        </div>

        <button type="button" class="btn-send" onclick="showConfirm()">
          📣 Envoyer maintenant
        </button>
      </form>
    </div>

    <!-- COLONNE DROITE -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">

      <!-- Aperçu push -->
      <div class="card">
        <div class="card-title">👁️ Aperçu push</div>
        <div class="preview-box">
          <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.6rem;font-family:'Space Mono',monospace;letter-spacing:1px;">NOTIFICATION MOBILE</div>
          <div class="notif-preview">
            <div class="notif-icon">🎯</div>
            <div class="notif-body">
              <div class="notif-title" id="prevTitle">Titre de la notification</div>
              <div class="notif-msg" id="prevMsg">Le message s'affichera ici...</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats membres -->
      <div class="card">
        <div class="card-title">📊 Membres</div>
        <div class="stat-row">
          <span class="stat-label">👥 Inscrits</span>
          <span class="stat-val"><?= $nbTotal ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">🔓 Abonnés actifs</span>
          <span class="stat-val"><?= $nbAbonnes ?></span>
        </div>
        <?php foreach (['daily'=>'⚡ Daily','weekly'=>'📅 Weekly','weekend'=>'🎉 Week-End','rasstoss'=>'👑 Rass-Toss'] as $k => $l): ?>
        <div class="stat-row">
          <span class="stat-label"><?= $l ?></span>
          <span class="stat-val" style="font-size:0.85rem;"><?= $packStats[$k] ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Anti-spam tips -->
      <div class="card" style="border-color:rgba(255,193,7,0.2);">
        <div class="card-title" style="color:#ffc107;">⚠️ Anti-spam — À faire</div>
        <div style="font-size:0.82rem;color:var(--text-muted);line-height:1.7;">
          <p style="margin-bottom:0.5rem;"><strong style="color:#f0f4f8;">1. SPF</strong> — Ajouter dans le DNS de stratedgepronos.fr :</p>
          <code style="background:#0a0e17;border:1px solid rgba(255,193,7,0.2);padding:0.3rem 0.5rem;border-radius:5px;display:block;font-size:0.75rem;color:#ffc107;margin-bottom:0.8rem;word-break:break-all;">v=spf1 a mx include:hostinger.com ~all</code>
          <p style="margin-bottom:0.5rem;"><strong style="color:#f0f4f8;">2. DKIM</strong> — Activer dans cPanel/Hostinger &gt; Email &gt; "Email Authentication"</p>
          <p style="margin-bottom:0.5rem;"><strong style="color:#f0f4f8;">3. DMARC</strong> — Ajouter en DNS (type TXT, nom: <code style="font-size:0.75rem;">_dmarc</code>) :</p>
          <code style="background:#0a0e17;border:1px solid rgba(255,193,7,0.2);padding:0.3rem 0.5rem;border-radius:5px;display:block;font-size:0.75rem;color:#ffc107;margin-bottom:0.8rem;word-break:break-all;">v=DMARC1; p=none; rua=mailto:stratedgepronos@gmail.com</code>
          <p><strong style="color:#f0f4f8;">4. Tester</strong> → <a href="https://www.mail-tester.com" target="_blank" style="color:#00d4ff;">mail-tester.com</a></p>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- CONFIRMATION AVANT ENVOI -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <h3>⚠️ Confirmer l'envoi</h3>
    <p id="confirmText">Tu vas envoyer une notification à tous les membres. Cette action est irréversible.</p>
    <div class="confirm-actions">
      <button class="btn-confirm-cancel" onclick="document.getElementById('confirmOverlay').classList.remove('open')">Annuler</button>
      <button class="btn-confirm" onclick="document.getElementById('broadcastForm').submit()">Envoyer !</button>
    </div>
  </div>
</div>

<script>
function toggleTestEmail() {
  const cTest = document.getElementById('cTest');
  const isTest = cTest && cTest.checked;
  document.getElementById('testEmailGroup').style.display = isTest ? 'block' : 'none';
  if (isTest) document.getElementById('testEmailInput').focus();
  // Si test coché → décocher les autres
  if (isTest) {
    document.querySelectorAll('.cible-check').forEach(cb => {
      if (cb.id !== 'cTest') cb.checked = false;
    });
  }
}
// Si on coche autre chose que test → décocher test
function onCibleChange(el) {
  if (el.id !== 'cTest' && el.checked) {
    const cTest = document.getElementById('cTest');
    if (cTest) { cTest.checked = false; toggleTestEmail(); }
  }
  if (el.id === 'cTest') toggleTestEmail();
}
window.addEventListener('DOMContentLoaded', function() {
  toggleTestEmail();
  toggleMsgHtmlMode();
});

function stripHtmlToPreview(html) {
  let txt = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  if (txt.length > 200) txt = txt.slice(0, 197) + '…';
  return txt || '(aperçu texte push)';
}

function toggleMsgHtmlMode() {
  const chk = document.getElementById('chkMsgHtml');
  const ta = document.getElementById('inputMsg');
  const help = document.getElementById('msgHtmlHelp');
  const maxEl = document.getElementById('countMsgMax');
  const max = chk.checked ? 60000 : 200;
  ta.setAttribute('maxlength', max);
  maxEl.textContent = max;
  if (ta.value.length > max) ta.value = ta.value.slice(0, max);
  help.style.display = chk.checked ? 'block' : 'none';
  updatePreview();
}

function updatePreview() {
  const t = document.getElementById('inputTitre').value || 'Titre de la notification';
  const raw = document.getElementById('inputMsg').value || '';
  const htmlMode = document.getElementById('chkMsgHtml').checked;
  const mPush = htmlMode ? stripHtmlToPreview(raw) : (raw || 'Le message s\'affichera ici...');
  document.getElementById('prevTitle').textContent = t;
  document.getElementById('prevMsg').textContent = mPush;
  document.getElementById('countTitre').textContent = document.getElementById('inputTitre').value.length;
  document.getElementById('countMsg').textContent = raw.length;
}

function showConfirm() {
  const ciblesChecked = [...document.querySelectorAll('.cible-check:checked')].map(c => c.value);
  const cible = { value: ciblesChecked.join('+') };
  const ciblesLabels = {
    'tous': 'tous les membres',
    'abonnes': 'tous les abonnés actifs',
    'daily': 'les membres Daily',
    'weekly': 'les membres Weekly',
    'weekend': 'les membres Week-End',
    'rasstoss': 'les membres Rass-Toss'
  };
  const canaux = [];
  if (document.getElementById('chkPush').checked) canaux.push('Push');
  if (document.getElementById('chkMail').checked) canaux.push('Email');

  if (!canaux.length) { alert('Choisis au moins un canal.'); return; }
  if (!document.getElementById('inputTitre').value.trim()) { alert('Le titre est obligatoire.'); return; }
  if (!document.getElementById('inputMsg').value.trim()) { alert('Le message est obligatoire.'); return; }

  const dest = ciblesChecked.map(v => ciblesLabels[v] || v).join(' + ') || 'membres sélectionnés';
  document.getElementById('confirmText').innerHTML =
    `Tu vas envoyer via <strong style="color:#ff2d78">${canaux.join(' + ')}</strong> à <strong style="color:#ff2d78">${dest}</strong>.<br>Cette action est <strong>irréversible</strong>.`;
  document.getElementById('confirmOverlay').classList.add('open');
}

updatePreview();
</script>
</body>
</html>
