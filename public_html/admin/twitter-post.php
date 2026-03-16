<?php
// ============================================================
// STRATEDGE — Module X / Twitter Posting Pro
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageActive = 'twitter-post';

$success = '';
$error   = '';
$tweetResult = null;

// ── Config IFTTT ─────────────────────────────────────────────
$configFile = __DIR__ . '/../includes/twitter_keys.php';
$config = ['webhook_url'=>'','webhook_url_image'=>'','actif'=>false];
if (file_exists($configFile)) {
    $tmp = include $configFile;
    if (is_array($tmp)) $config = array_merge($config, $tmp);
}
$apiOk = !empty($config['webhook_url']);

// ── Table historique tweets (création auto si absente) ───────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS tweets_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        texte TEXT NOT NULL,
        tweet_id VARCHAR(30) DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        statut ENUM('envoye','erreur') DEFAULT 'envoye',
        erreur_msg TEXT DEFAULT NULL,
        date_envoi DATETIME DEFAULT NOW()
    )");
} catch(Exception $e) {}

// ── Traitement POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // ── Sauvegarder config IFTTT ────────────────────────────
    if ($action === 'save_config') {
        $newConfig = [
            'webhook_url'       => trim($_POST['webhook_url'] ?? ''),
            'webhook_url_image' => trim($_POST['webhook_url_image'] ?? ''),
            'actif'             => !empty(trim($_POST['webhook_url'] ?? '')),
        ];
        $php = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
        if (file_put_contents($configFile, $php)) {
            $config = $newConfig;
            $apiOk  = !empty($newConfig['webhook_url']);
            $success = '✅ Webhooks IFTTT sauvegardés.';
        } else {
            $error = 'Impossible d\'écrire le fichier de config.';
        }
    }

    // ── Envoyer un tweet via IFTTT ──────────────────────────
    if ($action === 'post_tweet') {
        if (!$apiOk) {
            $error = 'Configure d\'abord le webhook IFTTT dans l\'onglet Config.';
        } else {
            $texte     = trim($_POST['texte'] ?? '');
            $imagePath = null;
            $imageUrl  = '';

            if (mb_strlen($texte) > 280) {
                $error = 'Le tweet dépasse 280 caractères.';
            } elseif (!$texte) {
                $error = 'Le texte est obligatoire.';
            } else {
                // Upload image → URL publique pour IFTTT value3
                if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $f   = $_FILES['image'];
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $f['size'] <= 5*1024*1024) {
                        $dir = __DIR__ . '/../uploads/tweets/';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname = 'tweet_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        if (move_uploaded_file($f['tmp_name'], $dir . $fname)) {
                            $imagePath = 'uploads/tweets/' . $fname;
                            $imageUrl  = 'https://stratedgepronos.fr/' . $imagePath;
                        }
                    }
                }

                // Choisir le bon webhook selon présence d'image
                if ($imageUrl && !empty($config['webhook_url_image'])) {
                    // Applet IFTTT avec image
                    $webhookUrl = $config['webhook_url_image'];
                    $payload = json_encode([
                        'value1' => $texte,
                        'value2' => 'StratEdge Pronos',
                        'value3' => $imageUrl,
                    ]);
                } else {
                    // Applet IFTTT texte seul
                    $webhookUrl = $config['webhook_url'];
                    $payload = json_encode([
                        'value1' => $texte,
                        'value2' => 'StratEdge Pronos',
                        'value3' => '',
                    ]);
                }
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT        => 15,
                ]);
                $response = curl_exec($ch);
                $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $ok = ($code >= 200 && $code < 300);

                $db->prepare("INSERT INTO tweets_log (texte, tweet_id, image_path, statut, erreur_msg) VALUES (?,?,?,?,?)")
                   ->execute([$texte, null, $imagePath, $ok ? 'envoye' : 'erreur', $ok ? null : "HTTP $code — $response"]);

                if ($ok) {
                    $success = '✅ Tweet envoyé à IFTTT ! Il sera posté dans quelques secondes.';
                } else {
                    $error = "❌ Erreur IFTTT (HTTP $code) : " . htmlspecialchars($response);
                }
            }
        }
    }

    // ── Supprimer un log ────────────────────────────────────
    if ($action === 'delete_log') {
        $id = (int)($_POST['log_id'] ?? 0);
        if ($id) $db->prepare("DELETE FROM tweets_log WHERE id=?")->execute([$id]);
        header('Location: twitter-post.php?tab=history');
        exit;
    }
}

// ── Historique tweets ────────────────────────────────────────
$logs = [];
try {
    $logs = $db->query("SELECT * FROM tweets_log ORDER BY date_envoi DESC LIMIT 50")->fetchAll();
} catch(Exception $e) {}

// ── Templates de tweets prédéfinis ──────────────────────────
$templates = [
    ['label'=>'🛡️ Nouveau Bet Safe',    'emoji'=>'🛡️',
     'texte'=>"🛡️ Nouveau BET SAFE vient d'être posté !\n\n📊 Analyse complète réservée aux abonnés\n🔒 Connecte-toi sur stratedgepronos.fr\n\n#Pronostics #Betting #StratEdge #Safe #Football"],
    ['label'=>'⚡ Bet Live',             'emoji'=>'⚡',
     'texte'=>"⚡ BET LIVE EN COURS — AGIS MAINTENANT !\n\n🔴 Opportunité en temps réel\n🔒 Accès immédiat sur stratedgepronos.fr\n\n#LiveBet #Betting #StratEdge #Live"],
    ['label'=>'🎯 Bet Fun',              'emoji'=>'🎯',
     'texte'=>"🎯 BET FUN disponible — grosse cote !\n\n💥 Pour les amateurs de sensations fortes\n🔒 stratedgepronos.fr\n\n#Fun #GrosseCote #StratEdge #Betting"],
    ['label'=>'✅ Résultat Gagné',       'emoji'=>'✅',
     'texte'=>"✅ GAGNÉ ! Un autre bet dans le vert ! 🟢\n\n📈 Notre taux de réussite parle pour nous\n📊 Historique complet sur stratedgepronos.fr\n\n#Gagnant #Pronostics #StratEdge"],
    ['label'=>'📢 Promo abonnement',    'emoji'=>'📢',
     'texte'=>"📢 Tu veux accéder à nos analyses complètes ?\n\n🎯 Formules dès aujourd'hui sur stratedgepronos.fr\n✅ Résultats transparents, aucun filtre\n\n#Abonnement #Pronostics #StratEdge"],
    ['label'=>'📊 Stats du mois',       'emoji'=>'📊',
     'texte'=>"📊 BILAN DU MOIS :\n\n🎯 X bets joués\n✅ X gagnés\n📈 Taux de réussite : X%\n\nHistorique complet sur stratedgepronos.fr\n\n#Stats #Pronostics #StratEdge"],
    ['label'=>'🎾 Montante Tennis',    'emoji'=>'🎾',
     'texte'=>"🎾 MONTANTE TENNIS — Étape en cours !\n\n📊 Suivi en direct : chaque step, chaque mise, chaque résultat\n✅ Montante gratuite pour tous les membres inscrits sur le site.\n🔗 stratedgepronos.fr\n\n#Montante #Tennis #Pronostics #StratEdge #teampronos"],
];

$activeTab = $_GET['tab'] ?? 'compose';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Poster sur X — StratEdge Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#050810;--bg-card:#0d1220;--bg-card2:#111827;--x-blue:#1d9bf0;--pink:#ff2d78;--pink-dim:#d6245f;--green:#00c864;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border:rgba(255,255,255,0.07);--border-x:rgba(29,155,240,0.25);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;display:flex;}
  </style>
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <style>
    /* ── Layout ── */
    .page-header{margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:900;color:#fff;display:flex;align-items:center;gap:0.6rem;}
    .x-badge{background:linear-gradient(135deg,rgba(29,155,240,0.15),rgba(29,155,240,0.05));border:1px solid var(--border-x);border-radius:8px;padding:0.3rem 0.7rem;font-size:0.78rem;font-weight:700;color:var(--x-blue);display:flex;align-items:center;gap:0.3rem;}
    /* ── Tabs ── */
    .tabs{display:flex;gap:0.3rem;margin-bottom:1.5rem;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:0.3rem;}
    .tab{flex:1;padding:0.65rem 1rem;border-radius:9px;border:none;background:transparent;color:var(--text-muted);font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all .2s;text-align:center;}
    .tab.active{background:rgba(29,155,240,0.12);color:var(--x-blue);border:1px solid var(--border-x);}
    .tab-content{display:none;} .tab-content.active{display:block;}
    /* ── Cards ── */
    .card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
    .card-title{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;color:var(--x-blue);margin-bottom:1.2rem;display:flex;align-items:center;gap:0.5rem;}
    .two-cols{display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start;}
    /* ── Formulaire tweet ── */
    .tweet-area{position:relative;}
    .tweet-textarea{width:100%;background:rgba(255,255,255,0.03);border:1.5px solid var(--border);color:var(--text-primary);padding:1rem;border-radius:12px;font-family:'Rajdhani',sans-serif;font-size:1.05rem;line-height:1.6;resize:none;min-height:140px;transition:border-color .2s;}
    .tweet-textarea:focus{outline:none;border-color:var(--x-blue);}
    .tweet-textarea.over{border-color:#ff4444;}
    .char-ring{position:absolute;bottom:12px;right:12px;}
    .char-ring svg{transform:rotate(-90deg);}
    .char-ring .count{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:var(--text-muted);}
    /* ── Image drop zone ── */
    .img-drop{border:2px dashed rgba(29,155,240,0.3);border-radius:12px;padding:1.2rem;text-align:center;cursor:pointer;transition:all .2s;background:rgba(29,155,240,0.02);position:relative;}
    .img-drop:hover,.img-drop.drag{border-color:var(--x-blue);background:rgba(29,155,240,0.07);}
    .img-drop input{display:none;}
    .img-drop .drop-icon{font-size:1.8rem;margin-bottom:0.3rem;}
    .img-drop .drop-label{font-size:0.88rem;color:var(--text-secondary);font-weight:600;}
    .img-drop .drop-sub{font-size:0.75rem;color:var(--text-muted);margin-top:0.2rem;}
    .img-preview-wrap{position:relative;display:inline-block;margin-top:0.8rem;}
    .img-preview-wrap img{max-width:100%;max-height:240px;border-radius:12px;display:block;object-fit:cover;}
    .img-remove{position:absolute;top:-8px;right:-8px;width:24px;height:24px;background:#ff4444;border:none;border-radius:50%;color:#fff;font-size:0.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;}
    /* ── Templates ── */
    .templates-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:0.6rem;}
    .tpl-btn{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:0.7rem 0.8rem;cursor:pointer;transition:all .2s;text-align:left;color:var(--text-secondary);font-family:'Rajdhani',sans-serif;font-size:0.85rem;font-weight:600;}
    .tpl-btn:hover{background:rgba(29,155,240,0.08);border-color:var(--border-x);color:var(--text-primary);}
    .tpl-emoji{font-size:1.2rem;display:block;margin-bottom:0.2rem;}
    /* ── Bouton envoyer ── */
    .btn-tweet{background:var(--x-blue);color:#fff;border:none;padding:0.85rem 2rem;border-radius:50px;font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:0.5rem;letter-spacing:0.5px;}
    .btn-tweet:hover{background:#1a8cd8;transform:translateY(-1px);box-shadow:0 6px 20px rgba(29,155,240,0.35);}
    .btn-tweet:disabled{opacity:0.4;cursor:not-allowed;transform:none;}
    .tweet-actions{display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:0.8rem;}
    /* ── Preview X ── */
    .x-preview-card{background:#000;border:1px solid #2f3336;border-radius:16px;padding:1rem 1rem 0.8rem;font-family:-apple-system,'Segoe UI',Arial,sans-serif;}
    .x-prev-header{display:flex;align-items:flex-start;gap:0.7rem;margin-bottom:0.7rem;}
    .x-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#ff2d78,#1d9bf0);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:900;color:#fff;font-family:'Orbitron',sans-serif;}
    .x-name{font-weight:700;font-size:0.9rem;color:#e7e9ea;}
    .x-handle{font-size:0.8rem;color:#71767b;margin-top:0.1rem;}
    .x-verified{display:inline-flex;align-items:center;gap:0.2rem;}
    .x-verified svg{width:16px;height:16px;fill:var(--x-blue);}
    .x-body{font-size:0.88rem;color:#e7e9ea;line-height:1.55;white-space:pre-wrap;word-break:break-word;}
    .x-image-preview{margin-top:0.7rem;border-radius:12px;overflow:hidden;display:none;}
    .x-image-preview img{width:100%;max-height:280px;object-fit:cover;display:block;}
    .x-actions{display:flex;gap:1.5rem;margin-top:0.8rem;padding-top:0.6rem;border-top:1px solid #2f3336;}
    .x-action{display:flex;align-items:center;gap:0.3rem;color:#71767b;font-size:0.8rem;}
    .x-action svg{width:16px;height:16px;fill:currentColor;}
    .x-date{font-size:0.75rem;color:#71767b;margin-top:0.6rem;}
    /* ── Config API ── */
    .api-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;}
    .form-group label{display:block;font-size:0.82rem;color:var(--text-muted);margin-bottom:0.35rem;font-weight:600;}
    .form-input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);color:var(--text-primary);padding:0.7rem 0.9rem;border-radius:8px;font-family:'Space Mono',monospace;font-size:0.78rem;transition:border-color .2s;}
    .form-input:focus{outline:none;border-color:var(--x-blue);}
    .form-input.secret{letter-spacing:2px;}
    .toggle-secret{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.8rem;margin-top:0.3rem;}
    .btn-save{background:linear-gradient(135deg,var(--x-blue),#1570c4);color:#fff;border:none;padding:0.75rem 1.8rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:1rem;}
    .btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(29,155,240,0.3);}
    /* ── Status ── */
    .api-status{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;padding:0.5rem 0.9rem;border-radius:8px;font-weight:700;}
    .api-status.ok{background:rgba(0,200,100,0.1);color:#00c864;border:1px solid rgba(0,200,100,0.25);}
    .api-status.ko{background:rgba(255,45,120,0.1);color:#ff6b9d;border:1px solid rgba(255,45,120,0.2);}
    /* ── Alert ── */
    .alert{padding:0.9rem 1.2rem;border-radius:10px;margin-bottom:1.5rem;font-weight:600;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.3);color:#00c864;}
    .alert-error{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.3);color:#ff6b9d;}
    /* ── Historique ── */
    .log-list{display:flex;flex-direction:column;gap:0.8rem;}
    .log-item{background:var(--bg-card2);border:1px solid var(--border);border-radius:12px;padding:1rem 1.2rem;display:flex;gap:1rem;align-items:flex-start;}
    .log-item.erreur{border-color:rgba(255,45,120,0.2);}
    .log-status{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
    .log-status.ok{background:rgba(0,200,100,0.1);}
    .log-status.ko{background:rgba(255,45,120,0.1);}
    .log-body{flex:1;min-width:0;}
    .log-texte{font-size:0.88rem;color:var(--text-secondary);white-space:pre-wrap;word-break:break-word;margin-bottom:0.4rem;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
    .log-meta{display:flex;gap:0.8rem;flex-wrap:wrap;align-items:center;}
    .log-date{font-size:0.75rem;color:var(--text-muted);}
    .log-link{font-size:0.75rem;color:var(--x-blue);text-decoration:none;display:flex;align-items:center;gap:0.2rem;}
    .log-link:hover{text-decoration:underline;}
    .log-err-msg{font-size:0.75rem;color:#ff6b9d;margin-top:0.3rem;}
    .log-img{width:60px;height:60px;border-radius:8px;object-fit:cover;flex-shrink:0;}
    .btn-del{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.75rem;padding:0.2rem 0.5rem;border-radius:5px;transition:all .2s;}
    .btn-del:hover{background:rgba(255,45,120,0.1);color:#ff6b9d;}
    .empty-state{text-align:center;padding:3rem;color:var(--text-muted);}
    .empty-state .big{font-size:3rem;margin-bottom:0.8rem;}
    /* ── Confirm overlay ── */
    .confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(6px);}
    .confirm-overlay.open{display:flex;}
    .confirm-box{background:var(--bg-card);border:1px solid var(--border-x);border-radius:16px;padding:2rem;width:90%;max-width:400px;text-align:center;}
    .confirm-box h3{font-family:'Orbitron',sans-serif;font-size:1rem;color:var(--x-blue);margin-bottom:0.8rem;}
    .confirm-preview{background:#000;border:1px solid #2f3336;border-radius:10px;padding:0.8rem;margin:1rem 0;font-size:0.82rem;color:#e7e9ea;white-space:pre-wrap;text-align:left;max-height:150px;overflow:hidden;}
    .confirm-actions{display:flex;gap:0.8rem;margin-top:1.2rem;}
    .btn-confirm{flex:1;background:var(--x-blue);color:#fff;border:none;padding:0.85rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;transition:all .2s;}
    .btn-confirm:hover{background:#1a8cd8;}
    .btn-confirm-cancel{flex:1;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-muted);padding:0.85rem;border-radius:10px;cursor:pointer;font-family:'Rajdhani',sans-serif;font-weight:700;}
    @media(max-width:900px){.two-cols{grid-template-columns:1fr;}.api-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<div class="main" style="padding:2rem;">

  <!-- Header -->
  <div class="page-header">
    <h1>
      <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.259 5.629L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>
      Poster sur X
    </h1>
    <div class="api-status <?= $apiOk ? 'ok' : 'ko' ?>">
      <?= $apiOk ? '✅ API connectée' : '⚠️ API non configurée' ?>
    </div>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab <?= $activeTab==='compose'?'active':'' ?>" onclick="switchTab('compose')">
      ✏️ Composer
    </button>
    <button class="tab <?= $activeTab==='history'?'active':'' ?>" onclick="switchTab('history')">
      📋 Historique <?php if (count($logs) > 0): ?><span style="font-size:0.75rem;">(<?= count($logs) ?>)</span><?php endif; ?>
    </button>
    <button class="tab <?= $activeTab==='config'?'active':'' ?>" onclick="switchTab('config')">
      ⚙️ Config API
    </button>
  </div>

  <!-- ══════════════════════════════════════
       TAB : COMPOSER
  ══════════════════════════════════════ -->
  <div class="tab-content <?= $activeTab==='compose'?'active':'' ?>" id="tab-compose">
    <div class="two-cols">

      <!-- Formulaire -->
      <div>
        <!-- Templates -->
        <div class="card">
          <div class="card-title">⚡ Templates rapides</div>
          <div class="templates-grid">
            <?php foreach ($templates as $tpl): ?>
            <button class="tpl-btn" onclick="applyTemplate(<?= htmlspecialchars(json_encode($tpl['texte'])) ?>)">
              <span class="tpl-emoji"><?= $tpl['emoji'] ?></span>
              <?= htmlspecialchars($tpl['label']) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Zone de rédaction -->
        <div class="card">
          <div class="card-title">✏️ Rédiger le tweet</div>
          <form id="tweetForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="post_tweet">

            <div class="tweet-area" style="margin-bottom:1rem;">
              <textarea
                class="tweet-textarea"
                name="texte"
                id="tweetText"
                maxlength="280"
                placeholder="Qu'est-ce qui se passe ? (280 caractères max)"
                oninput="updateCounter(); updatePreview();"
              ><?= htmlspecialchars($_POST['texte'] ?? '') ?></textarea>
              <!-- Cercle de comptage SVG -->
              <div class="char-ring">
                <svg width="36" height="36" viewBox="0 0 36 36">
                  <circle cx="18" cy="18" r="14" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                  <circle id="ringCircle" cx="18" cy="18" r="14" fill="none" stroke="var(--x-blue)" stroke-width="3"
                    stroke-dasharray="87.96" stroke-dashoffset="87.96" stroke-linecap="round"/>
                </svg>
                <div class="count" id="ringCount" style="color:var(--text-muted);">280</div>
              </div>
            </div>

            <!-- Upload image -->
            <div style="margin-bottom:1.2rem;">
              <label style="font-size:0.82rem;color:var(--text-muted);font-weight:600;display:block;margin-bottom:0.5rem;">
                🖼️ Ajouter une image (optionnel — JPG, PNG, GIF, max 5 Mo)
              </label>
              <div class="img-drop" id="imgDrop"
                   onclick="document.getElementById('imgInput').click()"
                   ondragover="event.preventDefault();this.classList.add('drag')"
                   ondragleave="this.classList.remove('drag')"
                   ondrop="handleImgDrop(event)">
                <input type="file" name="image" id="imgInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                <div class="drop-icon">🖼️</div>
                <div class="drop-label">Cliquer ou glisser une image</div>
                <div class="drop-sub">Recommandé : 1200×675px (ratio 16:9)</div>
              </div>
              <div id="imgPreviewWrap" style="display:none;margin-top:0.8rem;">
                <div class="img-preview-wrap">
                  <img id="imgPreviewLocal" alt="Aperçu">
                  <button type="button" class="img-remove" onclick="removeImage()" title="Supprimer">✕</button>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="tweet-actions">
              <div style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
                <button type="button"
                        class="btn-tweet"
                        onclick="showTweetConfirm()"
                        <?= !$apiOk ? 'disabled title="Configure d\'abord les clés API"' : '' ?>>
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.259 5.629L18.244 2.25z"/>
                  </svg>
                  Publier
                </button>
                <button type="button" onclick="clearForm()"
                        style="background:none;border:1px solid var(--border);color:var(--text-muted);padding:0.6rem 1rem;border-radius:50px;cursor:pointer;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.88rem;transition:all .2s;">
                  🗑️ Effacer
                </button>
              </div>
              <?php if (!$apiOk): ?>
              <a href="?tab=config" style="font-size:0.82rem;color:var(--x-blue);">⚙️ Configurer l'API →</a>
              <?php endif; ?>
            </div>

          </form>
        </div>
      </div>

      <!-- Prévisualisation -->
      <div>
        <div class="card" style="position:sticky;top:80px;">
          <div class="card-title">👁️ Aperçu en temps réel</div>
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.8rem;font-family:'Space Mono',monospace;letter-spacing:1px;">RENDU SUR X / TWITTER</div>
          <div class="x-preview-card">
            <div class="x-prev-header">
              <div class="x-avatar">S</div>
              <div>
                <div class="x-name x-verified">
                  StratedgePronos
                  <svg viewBox="0 0 24 24"><path d="M20.396 11c-.018-.646-.215-1.275-.57-1.816-.354-.54-.852-.972-1.438-1.246.233-.863.22-1.776-.04-2.63-.26-.855-.736-1.627-1.384-2.24-.647-.613-1.44-1.038-2.296-1.23-.857-.192-1.75-.145-2.583.137-.715-.616-1.59-.977-2.513-1.037-.923-.06-1.839.183-2.627.698-.788.515-1.414 1.267-1.8 2.155-.386.888-.518 1.87-.381 2.834-.59.217-1.12.572-1.55 1.04-.43.468-.745 1.034-.915 1.647-.17.613-.19 1.259-.059 1.882.131.622.406 1.205.805 1.7-.398.493-.673 1.076-.804 1.698-.131.623-.111 1.269.059 1.882.17.613.484 1.18.915 1.648.43.468.96.822 1.55 1.04-.137.964-.005 1.945.381 2.834.386.887 1.012 1.64 1.8 2.154.788.516 1.704.76 2.627.698.923-.06 1.798-.42 2.513-1.036.832.281 1.726.328 2.583.137.856-.192 1.65-.618 2.296-1.23.648-.613 1.124-1.385 1.384-2.24.26-.854.273-1.767.04-2.63.586-.274 1.084-.706 1.438-1.246.355-.54.552-1.17.57-1.816zM9.662 14.85l-3.429-3.428 1.293-1.302 2.072 2.072 4.4-4.794 1.347 1.246z"/></svg>
                </div>
                <div class="x-handle">@StratedgePronos</div>
              </div>
              <div style="margin-left:auto;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="#71767b"><path d="M3 12c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9 2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm7 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/></svg>
              </div>
            </div>
            <div class="x-body" id="xPrevBody">Ton tweet apparaîtra ici...</div>
            <div class="x-image-preview" id="xPrevImage">
              <img id="xPrevImgEl" alt="">
            </div>
            <div class="x-date" id="xPrevDate"></div>
            <div class="x-actions">
              <div class="x-action"><svg viewBox="0 0 24 24"><path d="M1.751 10c0-4.42 3.584-8 8.005-8h4.366c4.49 0 7.501 4.27 5.21 8.016l-2.087 3.108c-.24.358-.65.578-1.088.578H9.81c-.44 0-.85-.22-1.09-.58l-2.085-3.11C5.31 7.031 6.831 5 9.015 5H11V3H9.015C5.18 3 1.751 6.42 1.751 10c0 3.58 3.429 7 7.264 7H11v-2H9.015C6.832 15 5.31 12.969 6.635 10.984l2.085 3.11c.24.36.65.58 1.09.58h4.347c.438 0 .847-.22 1.087-.578l2.088-3.108C19.403 6.27 16.392 2 11.902 2H7.756C3.335 2-.25 5.58-.25 10s3.585 8 8.005 8h2.246v-2H7.761C4.327 16 1.751 13.42 1.751 10z"/></svg> Répondre</div>
              <div class="x-action"><svg viewBox="0 0 24 24"><path d="M4.75 3.79l4.603 4.3-1.706 1.82L6 8.38v7.37c0 .97.784 1.75 1.75 1.75H13V19H7.75C5.682 19 4 17.32 4 15.25V8.38L2.353 9.91 .647 8.09l4.103-4.3zM15.5 15h-3v-2h3v-3.13l3.646 4.3-3.646 4.3V15z"/></svg> RT</div>
              <div class="x-action"><svg viewBox="0 0 24 24"><path d="M16.697 5.5c-1.222-.06-2.679.51-3.89 2.16l-.805 1.09-.806-1.09C9.984 6.01 8.526 5.44 7.304 5.5c-1.243.07-2.349.78-2.91 1.91-.552 1.12-.633 2.78.479 4.82 1.074 1.97 3.257 4.27 7.129 6.61 3.87-2.34 6.052-4.64 7.126-6.61 1.111-2.04 1.03-3.7.477-4.82-.561-1.13-1.666-1.84-2.908-1.91zm4.187 7.69c-1.351 2.48-4.001 5.12-8.379 7.67l-.503.3-.504-.3c-4.379-2.55-7.029-5.19-8.382-7.67-1.36-2.5-1.41-4.86-.514-6.67.887-1.79 2.647-2.91 4.601-3.01 1.651-.09 3.368.56 4.798 2.01 1.429-1.45 3.146-2.1 4.796-2.01 1.954.1 3.714 1.22 4.601 3.01.896 1.81.846 4.17-.514 6.67z"/></svg> J'aime</div>
              <div class="x-action"><svg viewBox="0 0 24 24"><path d="M12 2.59l5.7 5.7-1.41 1.42L13 6.41V16h-2V6.41l-3.3 3.3-1.41-1.42L12 2.59zM21 15l-.02 3.51c0 1.38-1.12 2.49-2.5 2.49H5.5C4.11 21 3 19.88 3 18.5V15h2v3.5c0 .28.22.5.5.5h12.98c.28 0 .5-.22.5-.5L19 15h2z"/></svg></div>
            </div>
          </div>

          <!-- Stats temps réel -->
          <div style="display:flex;gap:0.8rem;margin-top:1rem;flex-wrap:wrap;">
            <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;padding:0.5rem 0.8rem;flex:1;text-align:center;">
              <div style="font-family:'Orbitron',sans-serif;font-size:1.2rem;color:var(--x-blue);" id="statChars">0</div>
              <div style="font-size:0.7rem;color:var(--text-muted);">Caractères</div>
            </div>
            <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;padding:0.5rem 0.8rem;flex:1;text-align:center;">
              <div style="font-family:'Orbitron',sans-serif;font-size:1.2rem;color:var(--x-blue);" id="statWords">0</div>
              <div style="font-size:0.7rem;color:var(--text-muted);">Mots</div>
            </div>
            <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;padding:0.5rem 0.8rem;flex:1;text-align:center;">
              <div style="font-family:'Orbitron',sans-serif;font-size:1.2rem;color:var(--x-blue);" id="statLines">0</div>
              <div style="font-size:0.7rem;color:var(--text-muted);">Lignes</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════
       TAB : HISTORIQUE
  ══════════════════════════════════════ -->
  <div class="tab-content <?= $activeTab==='history'?'active':'' ?>" id="tab-history">
    <div class="card">
      <div class="card-title">📋 Tweets envoyés (<?= count($logs) ?>)</div>
      <?php if (empty($logs)): ?>
        <div class="empty-state">
          <div class="big">🐦</div>
          <p>Aucun tweet envoyé pour l'instant.</p>
        </div>
      <?php else: ?>
        <div class="log-list">
          <?php foreach ($logs as $log):
            $imgSrc = $log['image_path'] && file_exists(__DIR__ . '/../' . $log['image_path'])
                    ? '../' . $log['image_path'] : null;
          ?>
          <div class="log-item <?= $log['statut']==='erreur'?'erreur':'' ?>">
            <div class="log-status <?= $log['statut']==='envoye'?'ok':'ko' ?>">
              <?= $log['statut']==='envoye' ? '✅' : '❌' ?>
            </div>
            <?php if ($imgSrc): ?>
              <img class="log-img" src="<?= htmlspecialchars($imgSrc) ?>" alt="" onclick="openLightbox('<?= htmlspecialchars($imgSrc) ?>')" style="cursor:zoom-in;">
            <?php endif; ?>
            <div class="log-body">
              <div class="log-texte"><?= htmlspecialchars($log['texte']) ?></div>
              <?php if ($log['statut']==='erreur' && $log['erreur_msg']): ?>
                <div class="log-err-msg">⚠️ <?= htmlspecialchars($log['erreur_msg']) ?></div>
              <?php endif; ?>
              <div class="log-meta">
                <span class="log-date">🕐 <?= date('d/m/Y à H:i', strtotime($log['date_envoi'])) ?></span>
                <?php if ($log['tweet_id']): ?>
                <a class="log-link" href="https://x.com/StratedgePronos/status/<?= $log['tweet_id'] ?>" target="_blank">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.259 5.629L18.244 2.25z"/></svg>
                  Voir sur X
                </a>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce log ?')">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="delete_log">
                  <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                  <button type="submit" class="btn-del">🗑️ Supprimer</button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════════════════════════
       TAB : CONFIG IFTTT
  ══════════════════════════════════════ -->
  <div class="tab-content <?= $activeTab==='config'?'active':'' ?>" id="tab-config">

    <!-- Statut actuel -->
    <div class="card" style="max-width:700px;<?= $apiOk ? 'border-color:rgba(0,200,100,0.2);' : 'border-color:rgba(255,193,7,0.2);' ?>">
      <div class="card-title" style="color:<?= $apiOk ? 'var(--green)' : '#ffc107' ?>;">
        <?= $apiOk ? '✅ IFTTT connecté' : '⚠️ Webhook non configuré' ?>
      </div>
      <?php if ($apiOk): ?>
        <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:0.5rem;">Webhook actif :</p>
        <code style="background:#0a0e17;border:1px solid var(--border);padding:0.4rem 0.7rem;border-radius:6px;font-size:0.75rem;color:#00d4ff;display:block;word-break:break-all;">
          <?= htmlspecialchars(substr($config['webhook_url'], 0, 60)) ?>...
        </code>
      <?php else: ?>
        <p style="color:var(--text-muted);font-size:0.85rem;">Suis le guide ci-dessous pour connecter IFTTT.</p>
      <?php endif; ?>
    </div>

    <!-- Guide IFTTT -->
    <div class="card" style="max-width:700px;">
      <div class="card-title">📖 Comment configurer IFTTT</div>
      <div style="font-size:0.85rem;color:var(--text-muted);line-height:1.9;">
        <div style="display:flex;gap:0.8rem;align-items:flex-start;margin-bottom:0.8rem;">
          <span style="background:var(--x-blue);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;">1</span>
          <span>Va sur <a href="https://ifttt.com" target="_blank" style="color:var(--x-blue);">ifttt.com</a> → connecte-toi → <strong style="color:var(--text-primary);">Create</strong></span>
        </div>
        <div style="display:flex;gap:0.8rem;align-items:flex-start;margin-bottom:0.8rem;">
          <span style="background:var(--x-blue);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;">2</span>
          <span><strong style="color:var(--text-primary);">If This</strong> → recherche <strong style="color:var(--text-primary);">Webhooks</strong> → <strong style="color:var(--text-primary);">Receive a web request</strong> → Event name : <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">nouveau_tweet</code></span>
        </div>
        <div style="display:flex;gap:0.8rem;align-items:flex-start;margin-bottom:0.8rem;">
          <span style="background:var(--x-blue);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;">3</span>
          <span>
            <strong style="color:#00c864;">Applet 1 — texte seul :</strong> Event name <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">nouveau_tweet</code><br>
            Then That → X → <strong style="color:var(--text-primary);">Post a tweet</strong> → Tweet text : <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">{{Value1}}</code>
          </span>
        </div>
        <div style="display:flex;gap:0.8rem;align-items:flex-start;margin-bottom:0.8rem;">
          <span style="background:#00c864;color:#000;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;">4</span>
          <span>
            <strong style="color:#00c864;">Applet 2 — avec image :</strong> Crée un 2ème applet, Event name <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">nouveau_tweet_image</code><br>
            Then That → X → <strong style="color:var(--text-primary);">Post a tweet with image</strong> → Tweet text : <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">{{Value1}}</code> · Image URL : <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">{{Value3}}</code>
          </span>
        </div>
        <div style="display:flex;gap:0.8rem;align-items:flex-start;margin-bottom:0.8rem;">
          <span style="background:var(--x-blue);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;">5</span>
          <span>Va dans <a href="https://ifttt.com/maker_webhooks/settings" target="_blank" style="color:var(--x-blue);">ifttt.com/maker_webhooks/settings</a> → copie ta clé → l'URL webhook sera : <br>
          <code style="background:rgba(255,255,255,0.04);border:1px solid var(--border);padding:0.3rem 0.6rem;border-radius:6px;font-size:0.72rem;color:#00d4ff;display:block;margin-top:0.3rem;">https://maker.ifttt.com/trigger/nouveau_tweet/with/key/TA_CLE_ICI</code></span>
        </div>
      </div>
    </div>

    <!-- Formulaire URL webhook -->
    <div class="card" style="max-width:700px;">
      <div class="card-title">🔗 URL du Webhook IFTTT</div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="save_config">

        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:flex;align-items:center;gap:0.5rem;">
            <span style="background:rgba(29,155,240,0.15);border:1px solid rgba(29,155,240,0.3);border-radius:6px;padding:0.15rem 0.5rem;font-size:0.75rem;color:var(--x-blue);">📝 Sans image</span>
            URL webhook — tweets texte uniquement
          </label>
          <input type="url" class="form-input" name="webhook_url"
                 value="<?= htmlspecialchars($config['webhook_url'] ?? '') ?>"
                 placeholder="https://maker.ifttt.com/trigger/nouveau_tweet/with/key/...">
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;">Applet IFTTT : "Post a tweet" avec Value1 = texte</div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:flex;align-items:center;gap:0.5rem;">
            <span style="background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.25);border-radius:6px;padding:0.15rem 0.5rem;font-size:0.75rem;color:#00c864;">🖼️ Avec image</span>
            URL webhook — tweets avec image
          </label>
          <input type="url" class="form-input" name="webhook_url_image"
                 value="<?= htmlspecialchars($config['webhook_url_image'] ?? '') ?>"
                 placeholder="https://maker.ifttt.com/trigger/nouveau_tweet_image/with/key/...">
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;">Applet IFTTT : "Post a tweet with image" avec Value1 = texte, Value3 = URL image</div>
        </div>

        <div style="display:flex;gap:0.8rem;align-items:center;flex-wrap:wrap;margin-top:1rem;">
          <button type="submit" class="btn-save" style="margin-top:0;">💾 Sauvegarder</button>
          <?php if ($apiOk): ?>
          <button type="button" class="btn-tweet" style="font-family:'Rajdhani',sans-serif;font-size:0.9rem;"
                  onclick="testWebhook()">🧪 Tester (sans image)</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- LIGHTBOX -->
<div id="lb" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.93);z-index:999;align-items:center;justify-content:center;" onclick="this.style.display='none'">
  <img id="lbImg" style="max-width:95vw;max-height:90vh;border-radius:12px;">
</div>

<!-- CONFIRMATION TWEET -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <h3>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="vertical-align:middle;margin-right:0.4rem;"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.259 5.629L18.244 2.25z"/></svg>
      Confirmer la publication
    </h3>
    <div class="confirm-preview" id="confirmPreview"></div>
    <p style="color:var(--text-muted);font-size:0.82rem;">Ce tweet sera publié immédiatement sur ton compte X. Irréversible.</p>
    <div class="confirm-actions">
      <button class="btn-confirm-cancel" onclick="document.getElementById('confirmOverlay').classList.remove('open')">Annuler</button>
      <button class="btn-confirm" onclick="document.getElementById('tweetForm').submit()">
        Publier maintenant
      </button>
    </div>
  </div>
</div>

<script>
// ── Navigation tabs ──────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tab').forEach((t,i) => {
    const tabs = ['compose','history','config'];
    t.classList.toggle('active', tabs[i] === name);
  });
  document.querySelectorAll('.tab-content').forEach(c => {
    c.classList.toggle('active', c.id === 'tab-' + name);
  });
}

// ── Compteur de caractères ────────────────────────────────────
function updateCounter() {
  const ta = document.getElementById('tweetText');
  const len = ta.value.length;
  const max = 280;
  const rem = max - len;
  const pct = len / max;
  const circ = 87.96;
  const offset = circ - (circ * pct);

  // Cercle SVG
  const ring = document.getElementById('ringCircle');
  ring.style.strokeDashoffset = offset;
  ring.style.stroke = len > 260 ? (len >= 280 ? '#ff4444' : '#f4a623') : 'var(--x-blue)';

  // Texte centre
  const cnt = document.getElementById('ringCount');
  cnt.textContent = rem;
  cnt.style.color = len > 260 ? (len >= 280 ? '#ff4444' : '#f4a623') : 'var(--text-muted)';

  // Textarea border
  ta.classList.toggle('over', len > 280);

  // Stats
  document.getElementById('statChars').textContent = len;
  document.getElementById('statWords').textContent = ta.value.trim() ? ta.value.trim().split(/\s+/).length : 0;
  document.getElementById('statLines').textContent = ta.value.split('\n').length;
}

// ── Prévisualisation ─────────────────────────────────────────
function updatePreview() {
  const ta = document.getElementById('tweetText');
  const body = document.getElementById('xPrevBody');
  body.textContent = ta.value || 'Ton tweet apparaîtra ici...';
  const now = new Date();
  document.getElementById('xPrevDate').textContent = now.toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'}) + ' · ' + now.toLocaleDateString('fr-FR', {day:'numeric',month:'short',year:'numeric'});
}

// ── Image ────────────────────────────────────────────────────
function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('imgPreviewLocal').src = e.target.result;
      document.getElementById('imgPreviewWrap').style.display = 'block';
      document.getElementById('xPrevImgEl').src = e.target.result;
      document.getElementById('xPrevImage').style.display = 'block';
      document.querySelector('.drop-label').textContent = '✅ ' + input.files[0].name;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function handleImgDrop(e) {
  e.preventDefault();
  document.getElementById('imgDrop').classList.remove('drag');
  const dt = e.dataTransfer;
  if (dt.files.length) {
    document.getElementById('imgInput').files = dt.files;
    previewImage(document.getElementById('imgInput'));
  }
}
function removeImage() {
  document.getElementById('imgInput').value = '';
  document.getElementById('imgPreviewWrap').style.display = 'none';
  document.getElementById('xPrevImage').style.display = 'none';
  document.querySelector('.drop-label').textContent = 'Cliquer ou glisser une image';
}

// ── Templates ────────────────────────────────────────────────
function applyTemplate(texte) {
  const ta = document.getElementById('tweetText');
  ta.value = texte;
  updateCounter();
  updatePreview();
  ta.focus();
  // Scroll vers la zone de texte
  ta.scrollIntoView({behavior:'smooth', block:'center'});
}

// ── Effacer form ─────────────────────────────────────────────
function clearForm() {
  document.getElementById('tweetText').value = '';
  removeImage();
  updateCounter();
  updatePreview();
}

// ── Confirmation ──────────────────────────────────────────────
function showTweetConfirm() {
  const t = document.getElementById('tweetText').value.trim();
  if (!t) { alert('Le texte est obligatoire.'); return; }
  if (t.length > 280) { alert('280 caractères maximum.'); return; }
  document.getElementById('confirmPreview').textContent = t;
  document.getElementById('confirmOverlay').classList.add('open');
}
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});

// ── API key toggle ────────────────────────────────────────────
function toggleField(id, btn) {
  const f = document.getElementById(id);
  if (f.type === 'password') { f.type = 'text'; btn.textContent = '🙈 Masquer'; }
  else { f.type = 'password'; btn.textContent = '👁️ Afficher'; }
}

// ── Lightbox ──────────────────────────────────────────────────
function openLightbox(src) {
  document.getElementById('lbImg').src = src;
  document.getElementById('lb').style.display = 'flex';
}

// ── Test webhook ─────────────────────────────────────────────
function testWebhook() {
  if (!confirm('Envoyer un tweet de test via IFTTT ?')) return;
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      csrf_token: document.querySelector('input[name="csrf_token"]').value,
      action: 'post_tweet',
      texte: '🧪 Test de connexion StratEdge Pronos\n\n✅ Le module X fonctionne via IFTTT !\n\n#StratEdge #Test'
    })
  }).then(r => r.text()).then(() => {
    alert('Tweet de test envoyé ! Vérifie ton compte X dans quelques secondes.');
  });
}

// ── Init ──────────────────────────────────────────────────────
updateCounter();
updatePreview();
</script>
</body>
</html>
