<?php
// ============================================================
// STRATEDGE — Page de test Email & Push
// public_html/admin/test-notifications.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Diagnostic SMTP intégré (même page, pas de fichier séparé → pas de 404)
if (isset($_GET['diagnostic']) && $_GET['diagnostic'] === 'smtp') {
    require_once __DIR__ . '/../includes/mailer.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Diagnostic SMTP</title>';
    echo '<style>body{font-family:monospace;background:#111;color:#eee;padding:2rem;} .ok{color:#0c6;} .err{color:#c66;} pre{margin:0.5em 0;} h1{font-size:1.2rem;} a{color:#8af;}</style></head><body>';
    echo '<h1>Diagnostic SMTP (Brevo)</h1>';
    if (!file_exists(__DIR__ . '/../includes/smtp-config.php')) {
        echo '<p class="err">smtp-config.php absent sur le serveur.</p>';
    } else {
        require_once __DIR__ . '/../includes/smtp-config.php';
        if (!defined('SMTP_HOST') || !SMTP_HOST || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
            echo '<p class="err">SMTP_HOST, SMTP_USER ou SMTP_PASS manquant.</p>';
        } else {
            $host = SMTP_HOST;
            $port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
            $user = SMTP_USER;
            $pass = SMTP_PASS;
            $steps = [];
            $sock = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 15);
            if (!$sock) {
                echo '<p class="err">Connexion impossible ' . htmlspecialchars($host) . ':' . $port . '</p><pre>' . htmlspecialchars($errstr) . ' (errno ' . $errno . ')</pre>';
            } else {
                $read = function () use ($sock) { $l = @fgets($sock, 8192); return $l !== false ? trim($l) : ''; };
                $send = function ($cmd) use ($sock) { @fwrite($sock, $cmd . "\r\n"); };
                $steps[] = ['Connexion TCP', true, $host . ':' . $port];
                $g = $read();
                $steps[] = ['Banner', strpos($g, '220') === 0, $g];
                $send('EHLO localhost');
                $ehlo = []; while (($l = $read()) !== '') { $ehlo[] = $l; if (strlen($l) >= 4 && $l[3] === ' ') break; }
                $steps[] = ['EHLO', !empty($ehlo) && strpos($ehlo[0], '250') === 0, implode("\n", $ehlo)];
                $send('STARTTLS');
                $r = $read();
                $steps[] = ['STARTTLS', strpos($r, '220') === 0, $r];
                if (strpos($r, '220') === 0 && @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $steps[] = ['TLS activé', true, 'OK'];
                    $send('EHLO localhost');
                    while (($l = $read()) !== '') { if (strlen($l) >= 4 && $l[3] === ' ') break; }
                    $send('AUTH LOGIN'); $read();
                    $send(base64_encode($user)); $read();
                    $send(base64_encode($pass));
                    $code = $read();
                    $authOk = strpos($code, '235') === 0;
                    $steps[] = ['AUTH LOGIN', $authOk, $code];
                }
                $send('QUIT');
                fclose($sock);
            }
            if (!empty($steps)) {
                echo '<h2>Résultat</h2>';
                foreach ($steps as $s) {
                    echo '<p class="' . ($s[1] ? 'ok' : 'err') . '">' . htmlspecialchars($s[0]) . ': ' . ($s[1] ? 'OK' : 'ÉCHEC') . '</p>';
                    if (!empty($s[2])) echo '<pre>' . htmlspecialchars($s[2]) . '</pre>';
                }
                $lastOk = end($steps)[1];
                if ($lastOk) echo '<p class="ok"><strong>Connexion SMTP OK.</strong> Si mail-tester ne reçoit rien, vérifier error_log pour [StratEdge SMTP] après envoi.</p>';
                else echo '<p class="err">Vérifier SMTP_USER (email Brevo) et SMTP_PASS (clé SMTP Brevo, pas le mot de passe du compte).</p>';
            }
        }
    }
    echo '<p><a href="test-notifications.php">← Retour Test des notifications</a></p></body></html>';
    exit;
}

require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/push.php';
$pageActive = 'test-notif';
$db = getDB();

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $test = $_POST['test'] ?? '';
    $dest = trim($_POST['dest_email'] ?? ADMIN_EMAIL);

    switch ($test) {

        case 'email_bienvenue':
            $mailErr = null;
            $ok = envoyerEmail($dest,
                '⚡ TEST — Bienvenue sur StratEdge',
                emailTemplate('Test : Email de bienvenue',
                    '<p>Bonjour <strong>Testeur</strong>,</p>
                     <p>Ceci est un test de l\'email de bienvenue envoyé lors de l\'inscription.</p>
                     <p style="color:#00d46a;font-weight:700;">✅ Si tu reçois cet email, l\'envoi fonctionne correctement.</p>'
                ),
                $mailErr
            );
            $results[] = ['type' => 'email', 'label' => 'Email Bienvenue', 'ok' => $ok, 'dest' => $dest, 'info' => $mailErr];
            break;

        case 'email_nouveau_bet':
            $ok = emailNouveauBet($dest, 'Testeur', 'daily', 'Match test - PSG vs Lyon');
            $results[] = ['type' => 'email', 'label' => 'Email Nouveau Bet', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_resultat_win':
            $ok = emailResultatBet($dest, 'Testeur', 'Match test - PSG vs Lyon', 'win', 'daily');
            $results[] = ['type' => 'email', 'label' => 'Email Résultat WIN ✅', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_resultat_lose':
            $ok = emailResultatBet($dest, 'Testeur', 'Match test - PSG vs Lyon', 'lose', 'daily');
            $results[] = ['type' => 'email', 'label' => 'Email Résultat LOSE ❌', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_abo_confirme':
            $ok = emailConfirmationAbonnement($dest, 'Testeur', 'daily');
            $results[] = ['type' => 'email', 'label' => 'Email Confirmation Abonnement', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_abo_expire':
            $ok = emailAbonnementExpire($dest, 'Testeur', 'daily');
            $results[] = ['type' => 'email', 'label' => 'Email Abonnement Expiré', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_ticket':
            $ok = emailReponseTicket($dest, 'Testeur', 'Problème de connexion', 'Bonjour, voici notre réponse de test au ticket. Tout fonctionne bien !');
            $results[] = ['type' => 'email', 'label' => 'Email Réponse Ticket SAV', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_message_chat':
            $ok = emailNouveauMessageChat($dest, 'Testeur', 'Bonjour ! Ceci est un message test envoyé depuis le panel admin StratEdge.');
            $results[] = ['type' => 'email', 'label' => 'Email Nouveau Message Chat', 'ok' => $ok, 'dest' => $dest];
            break;

        case 'email_tous':
            $tests = [
                ['emailNouveauBet', [$dest, 'Testeur', 'daily', 'Test groupé']],
                ['emailResultatBet', [$dest, 'Testeur', 'Test', 'win', 'daily']],
                ['emailConfirmationAbonnement', [$dest, 'Testeur', 'daily']],
                ['emailReponseTicket', [$dest, 'Testeur', 'Ticket test', 'Réponse test']],
            ];
            foreach ($tests as [$fn, $args]) {
                $ok = $fn(...$args);
                $results[] = ['type' => 'email', 'label' => $fn, 'ok' => $ok, 'dest' => $dest];
                usleep(300000); // 300ms entre chaque
            }
            break;

        case 'push_test':
            $membreId = (int)($_POST['push_membre_id'] ?? 0);
            if ($membreId) {
                // Compter les souscriptions
                $nbSubs = 0;
                try {
                    $nbSubs = (int)$db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE membre_id = ?")
                        ->execute([$membreId]) ? $db->query("SELECT COUNT(*) FROM push_subscriptions WHERE membre_id = $membreId")->fetchColumn() : 0;
                } catch(Exception $e) {}

                envoyerPush($membreId,
                    '🔔 Test Notification StratEdge',
                    'Si tu vois cette notification, le système Push fonctionne ! ✅',
                    '/dashboard.php',
                    'test-push'
                );
                $results[] = ['type' => 'push', 'label' => 'Push Test (membre #' . $membreId . ')', 'ok' => true, 'dest' => 'Membre ID ' . $membreId, 'info' => $nbSubs . ' appareil(s) enregistré(s)'];
            } else {
                $results[] = ['type' => 'push', 'label' => 'Push Test', 'ok' => false, 'dest' => '-', 'info' => 'Aucun membre sélectionné'];
            }
            break;

        case 'push_broadcast':
            try {
                $nbSubs = (int)$db->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
            } catch(Exception $e) { $nbSubs = 0; }
            envoyerPush(null,
                '📢 Test Broadcast StratEdge',
                'Ceci est un test de notification broadcast à tous les abonnés actifs.',
                '/bets.php',
                'test-broadcast'
            );
            $results[] = ['type' => 'push', 'label' => 'Push Broadcast', 'ok' => true, 'dest' => 'Tous abonnés actifs', 'info' => $nbSubs . ' souscription(s) totale(s)'];
            break;
    }
}

// Stats pour la page
$nbMembres   = (int)$db->query("SELECT COUNT(*) FROM membres WHERE email != '" . ADMIN_EMAIL . "'")->fetchColumn();
$nbAbonnes   = (int)$db->query("SELECT COUNT(DISTINCT membre_id) FROM abonnements WHERE actif = 1")->fetchColumn();
try {
    $nbPushSubs = (int)$db->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
} catch(Exception $e) { $nbPushSubs = 0; }

$tousMembers = $db->query("SELECT id, nom, email FROM membres WHERE email != '" . ADMIN_EMAIL . "' ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🔔 Test Notifications — StratEdge Admin</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-blue:#00d4ff;--neon-real-green:#00d46a;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;}
    .main{margin-left:240px;flex:1;padding:2rem;min-height:100vh;}

    /* Header */
    .page-header{margin-bottom:2rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:0.4rem;}
    .page-header p{color:var(--text-muted);font-size:0.9rem;}

    /* Stats */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;}
    .stat-box{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:12px;padding:1.2rem 1.5rem;text-align:center;}
    .stat-box .val{font-family:'Orbitron',sans-serif;font-size:2rem;font-weight:900;color:var(--neon-blue);}
    .stat-box .lbl{font-size:0.72rem;color:var(--text-muted);letter-spacing:2px;text-transform:uppercase;margin-top:0.3rem;}

    /* Grid 2 colonnes */
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;}
    .card-title{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;margin-bottom:1.2rem;display:flex;align-items:center;gap:0.6rem;}
    .card-title .dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .dot-email{background:#ff2d78;}
    .dot-push{background:#00d4ff;}

    /* Champ email destination */
    .dest-field{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.75rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.95rem;width:100%;outline:none;transition:border .2s;margin-bottom:1rem;}
    .dest-field:focus{border-color:#ff2d78;}

    /* Boutons de test */
    .test-btn{display:block;width:100%;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:0.75rem 1rem;color:var(--text-secondary);font-family:'Rajdhani',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;transition:all .2s;text-align:left;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.7rem;}
    .test-btn:hover{background:rgba(255,45,120,0.06);border-color:rgba(255,45,120,0.3);color:var(--text-primary);}
    .test-btn .icon{font-size:1.1rem;width:28px;text-align:center;flex-shrink:0;}
    .test-btn-all{background:rgba(255,45,120,0.08);border-color:rgba(255,45,120,0.3);color:#ff6b9d;}
    .test-btn-all:hover{background:rgba(255,45,120,0.15);}
    .test-btn-push{background:rgba(0,212,255,0.06);border-color:rgba(0,212,255,0.25);color:#00d4ff;}
    .test-btn-push:hover{background:rgba(0,212,255,0.12);}

    /* Select membre pour push */
    .push-select{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.7rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.9rem;width:100%;outline:none;margin-bottom:0.5rem;}
    .push-select option{background:#0d1220;}

    /* Résultats */
    .results{margin-top:1.5rem;}
    .result-item{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1rem;border-radius:10px;margin-bottom:0.5rem;font-size:0.88rem;}
    .result-ok{background:rgba(0,212,106,0.08);border:1px solid rgba(0,212,106,0.2);}
    .result-fail{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);}
    .result-icon{font-size:1.1rem;flex-shrink:0;}
    .result-info{color:var(--text-muted);font-size:0.78rem;margin-top:0.1rem;}

    /* Info box VAPID */
    .info-box{background:rgba(0,212,255,0.05);border:1px solid rgba(0,212,255,0.2);border-radius:10px;padding:1rem 1.2rem;margin-top:1rem;font-size:0.82rem;color:var(--text-secondary);line-height:1.7;}
    .info-box strong{color:#00d4ff;}

    @media(max-width:768px){.grid-2{grid-template-columns:1fr;}.stats-row{grid-template-columns:1fr;}.main{margin-left:0;padding:70px 1rem 1.5rem;}}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">

  <div class="page-header">
    <h1>🔔 Test Notifications</h1>
    <p>Envoie des emails et des push de test pour vérifier que tout fonctionne correctement.</p>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="val"><?= $nbMembres ?></div>
      <div class="lbl">Membres total</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $nbAbonnes ?></div>
      <div class="lbl">Abonnés actifs</div>
    </div>
    <div class="stat-box">
      <div class="val" style="color:var(--neon-blue);"><?= $nbPushSubs ?></div>
      <div class="lbl">Appareils Push</div>
    </div>
  </div>

  <!-- Résultats des tests -->
  <?php if (!empty($results)): ?>
  <div class="results">
    <?php foreach ($results as $r): ?>
      <div class="result-item <?= $r['ok'] ? 'result-ok' : 'result-fail' ?>">
        <span class="result-icon"><?= $r['ok'] ? '✅' : '❌' ?></span>
        <div>
          <div><strong><?= htmlspecialchars($r['label']) ?></strong> → <?= htmlspecialchars($r['dest']) ?></div>
          <div class="result-info">
            <?php if ($r['ok']): ?>
              <?= $r['type']==='email' ? (defined('SMTP_HOST') && SMTP_HOST ? 'Email envoyé via SMTP (Brevo)' : 'Email envoyé via mail()') : 'Push envoyé' ?>
            <?php else: ?>
              <?= !empty($r['info']) ? htmlspecialchars($r['info']) : 'Échec — vérifier error_log sur le serveur (rechercher [StratEdge])' ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid-2">

    <!-- ═══ EMAILS ═══ -->
    <div class="card">
      <div class="card-title">
        <span class="dot dot-email"></span> Tests Email
      </div>

      <?php $smtpOk = defined('SMTP_HOST') && SMTP_HOST; ?>
      <div class="info-box" style="margin-bottom:1rem;border-color:<?= $smtpOk ? 'rgba(0,212,106,0.3)' : 'rgba(255,193,7,0.3)' ?>;background:<?= $smtpOk ? 'rgba(0,212,106,0.05)' : 'rgba(255,193,7,0.05)' ?>;">
        <?php if ($smtpOk): ?>
          <strong style="color:#00d46a;">✅ SMTP (Brevo) configuré</strong> — Les mails partent via Brevo.
        <?php else: ?>
          <strong style="color:#ffc107;">⚠️ SMTP non configuré</strong> — Les mails partent via <code>mail()</code> PHP (hébergeur). Pour mail-tester et une meilleure délivrabilité, ajoute <code>includes/smtp-config.php</code> sur le serveur avec tes identifiants Brevo.
        <?php endif; ?>
        <br><span style="font-size:0.85rem;color:var(--text-muted);">Pour mail-tester : va sur mail-tester.com, copie l’adresse unique (ex. test-xxx@srv1.mail-tester.com), colle-la ci-dessous, envoie le test, puis retourne sur mail-tester et clique sur « Vérifier le score ».</span>
      </div>
      <p><a href="test-notifications.php?diagnostic=smtp" style="font-size:0.85rem;color:#ff2d78;">Diagnostic SMTP (voir pourquoi l'envoi échoue)</a></p>

      <label style="font-size:0.8rem;color:var(--text-muted);display:block;margin-bottom:0.4rem;">Adresse de destination</label>
      <input type="text" id="destEmail" value="<?= htmlspecialchars(ADMIN_EMAIL) ?>" class="dest-field" placeholder="email@exemple.com">

      <form method="POST" id="emailForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="dest_email" id="destEmailHidden">
        <input type="hidden" name="test" id="testName">

        <button type="button" class="test-btn" onclick="runTest('email_bienvenue')">
          <span class="icon">👋</span>
          <div><div>Email Bienvenue</div><div style="font-size:0.75rem;color:var(--text-muted);">Envoyé à l'inscription</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_nouveau_bet')">
          <span class="icon">🔥</span>
          <div><div>Email Nouveau Bet</div><div style="font-size:0.75rem;color:var(--text-muted);">Envoyé quand tu postes un bet</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_resultat_win')">
          <span class="icon">✅</span>
          <div><div>Email Résultat WIN</div><div style="font-size:0.75rem;color:var(--text-muted);">Résultat gagnant</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_resultat_lose')">
          <span class="icon">❌</span>
          <div><div>Email Résultat LOSE</div><div style="font-size:0.75rem;color:var(--text-muted);">Résultat perdant</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_abo_confirme')">
          <span class="icon">✅</span>
          <div><div>Email Confirmation Abonnement</div><div style="font-size:0.75rem;color:var(--text-muted);">Après activation StarPass/crypto</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_abo_expire')">
          <span class="icon">⏰</span>
          <div><div>Email Abonnement Expiré</div><div style="font-size:0.75rem;color:var(--text-muted);">Quand un abo se termine</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_ticket')">
          <span class="icon">🎫</span>
          <div><div>Email Réponse Ticket SAV</div><div style="font-size:0.75rem;color:var(--text-muted);">Quand tu réponds à un ticket</div></div>
        </button>
        <button type="button" class="test-btn" onclick="runTest('email_message_chat')">
          <span class="icon">💬</span>
          <div><div>Email Nouveau Message Chat</div><div style="font-size:0.75rem;color:var(--text-muted);">Quand tu envoies un message</div></div>
        </button>
        <button type="button" class="test-btn test-btn-all" onclick="runTest('email_tous')">
          <span class="icon">📨</span>
          <div><div>Envoyer TOUS les emails</div><div style="font-size:0.75rem;color:rgba(255,107,157,0.7);">4 emails en séquence</div></div>
        </button>
      </form>
    </div>

    <!-- ═══ PUSH ═══ -->
    <div class="card">
      <div class="card-title">
        <span class="dot dot-push"></span> Tests Push Notification
      </div>

      <form method="POST" id="pushForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="test" id="pushTestName">

        <label style="font-size:0.8rem;color:var(--text-muted);display:block;margin-bottom:0.4rem;">Envoyer à un membre spécifique</label>
        <select name="push_membre_id" class="push-select">
          <option value="">— Sélectionner un membre —</option>
          <?php foreach ($tousMembers as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?> (<?= htmlspecialchars($m['email']) ?>)</option>
          <?php endforeach; ?>
        </select>

        <button type="button" class="test-btn test-btn-push" onclick="runPush('push_test')">
          <span class="icon">🔔</span>
          <div><div>Push vers ce membre</div><div style="font-size:0.75rem;color:rgba(0,212,255,0.6);">Requiert le membre sélectionné ci-dessus</div></div>
        </button>

        <div style="height:1px;background:rgba(255,255,255,0.06);margin:0.8rem 0;"></div>

        <button type="button" class="test-btn test-btn-push" onclick="runPush('push_broadcast')">
          <span class="icon">📢</span>
          <div><div>Push Broadcast (tous abonnés)</div><div style="font-size:0.75rem;color:rgba(0,212,255,0.6);"><?= $nbPushSubs ?> appareil(s) enregistré(s)</div></div>
        </button>
      </form>

      <!-- Statut VAPID -->
      <?php
      $vapidOk = defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== 'VOTRE_CLE_PUBLIQUE_VAPID_ICI';
      ?>
      <div class="info-box" style="border-color:<?= $vapidOk ? 'rgba(0,212,106,0.3)' : 'rgba(255,193,7,0.3)' ?>;background:<?= $vapidOk ? 'rgba(0,212,106,0.05)' : 'rgba(255,193,7,0.05)' ?>;">
        <?php if ($vapidOk): ?>
          <strong style="color:#00d46a;">✅ VAPID configuré</strong><br>
          Clé publique : <code style="font-size:0.75rem;"><?= substr(VAPID_PUBLIC_KEY, 0, 20) ?>…</code><br>
          Les push fonctionneront si les membres ont accepté les notifications.
        <?php else: ?>
          <strong style="color:#ffc107;">⚠️ VAPID non configuré</strong><br>
          Les push ne seront pas envoyés.<br>
          Configure <code>vapid-config.php</code> avec tes clés VAPID.<br>
          Génère-les sur <a href="https://vapidkeys.com" target="_blank" style="color:#00d4ff;">vapidkeys.com</a>
        <?php endif; ?>
      </div>

      <!-- Infos appareils enregistrés -->
      <?php if ($nbPushSubs > 0): ?>
      <div style="margin-top:1rem;">
        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.6rem;letter-spacing:1px;text-transform:uppercase;">Appareils enregistrés</div>
        <?php
        try {
            $subs = $db->query("
                SELECT ps.*, m.nom, m.email 
                FROM push_subscriptions ps 
                JOIN membres m ON m.id = ps.membre_id 
                ORDER BY ps.date_ajout DESC LIMIT 10
            ")->fetchAll();
            foreach ($subs as $sub):
        ?>
        <div style="display:flex;align-items:center;gap:0.7rem;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
          <span style="font-size:1.1rem;"><?= strpos(strtolower($sub['user_agent']??''), 'mobile') !== false ? '📱' : '💻' ?></span>
          <div style="flex:1;min-width:0;">
            <div style="font-size:0.84rem;font-weight:600;"><?= htmlspecialchars($sub['nom']) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($sub['date_ajout'])) ?></div>
          </div>
        </div>
        <?php endforeach; } catch(Exception $e) { echo '<p style="color:var(--text-muted);font-size:0.82rem;">Table push_subscriptions non disponible.</p>'; } ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
function runTest(testName) {
  document.getElementById('destEmailHidden').value = document.getElementById('destEmail').value || '<?= ADMIN_EMAIL ?>';
  document.getElementById('testName').value = testName;
  document.getElementById('emailForm').submit();
}

function runPush(testName) {
  document.getElementById('pushTestName').value = testName;
  document.getElementById('pushForm').submit();
}
</script>
</body>
</html>
