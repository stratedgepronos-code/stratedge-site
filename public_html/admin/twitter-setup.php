<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageActive = 'twitter';

$success = '';
$error   = '';
$testResult = null;

$configFile = __DIR__ . '/../includes/twitter_keys.php';
$config = ['webhook_url' => '', 'actif' => false];
if (file_exists($configFile)) {
    $tmp = include $configFile;
    if (is_array($tmp)) $config = array_merge($config, $tmp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $newConfig = [
            'webhook_url' => trim($_POST['webhook_url'] ?? ''),
            'actif'       => isset($_POST['actif']),
        ];
        $php = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
        if (file_put_contents($configFile, $php)) {
            $config  = $newConfig;
            $success = 'Configuration sauvegardée ✅';
        } else {
            $error = 'Impossible d\'écrire le fichier de config.';
        }
    }

    if ($action === 'test_webhook') {
        if (file_exists($configFile)) {
            $tmp = include $configFile;
            if (is_array($tmp)) $config = array_merge($config, $tmp);
        }
        if (empty($config['webhook_url'])) {
            $error = 'Aucune URL de webhook configurée.';
        } else {
            $payload = json_encode([
                'value1' => "🧪 Test de connexion StratEdge Pronos\n\n✅ L'auto-post Twitter fonctionne via IFTTT !\n\n#StratEdge #Test",
                'value2' => 'Test StratEdge',
                'value3' => 'https://stratedgepronos.fr',
            ]);
            $ch = curl_init($config['webhook_url']);
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
            $testResult = [
                'success'  => ($code >= 200 && $code < 300),
                'code'     => $code,
                'response' => $response,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auto-Post Twitter – Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;}
    .field-label{display:block;font-size:0.8rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-secondary);margin-bottom:0.5rem;}
    .field-input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:0.85rem 1rem;color:var(--text-primary);font-family:'Space Mono',monospace;font-size:0.82rem;outline:none;transition:border 0.2s;}
    .field-input:focus{border-color:var(--neon-green);}
    .btn-save{background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:white;padding:0.9rem 2rem;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;text-transform:uppercase;cursor:pointer;width:100%;}
    .btn-test{background:rgba(0,212,255,0.1);border:1px solid rgba(0,212,255,0.3);color:var(--neon-blue);padding:0.8rem 2rem;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
    .card-title{font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;margin-bottom:1.2rem;}
    .step{display:flex;gap:1rem;align-items:flex-start;margin-bottom:1rem;}
    .step-num{width:28px;height:28px;border-radius:50%;background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.3);display:flex;align-items:center;justify-content:center;font-family:'Orbitron',sans-serif;font-size:0.72rem;font-weight:700;color:var(--neon-green);flex-shrink:0;}
    .step-title{font-weight:700;font-size:0.9rem;}
    .step-desc{color:var(--text-muted);font-size:0.84rem;margin-top:0.2rem;line-height:1.5;}
    .alert-ok{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.2);border-radius:10px;padding:1rem;color:#00c864;margin-bottom:1.5rem;}
    .alert-err{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.2);border-radius:10px;padding:1rem;color:#ff6b9d;margin-bottom:1.5rem;}
    .make-badge{display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,#6c3fc5,#9b59ff);color:white;padding:0.3rem 0.8rem;border-radius:6px;font-size:0.8rem;font-weight:700;}
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main" style="padding:2rem;max-width:820px;">

  <div style="margin-bottom:2rem;">
    <h1 style="font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:700;">🐦 Auto-Post Twitter via <span class="make-badge">Make.com</span></h1>
    <p style="color:var(--text-muted);margin-top:0.5rem;">Chaque bet posté sera automatiquement tweeté. Gratuit et sans code côté Twitter.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert-ok"><?= clean($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-err"><?= clean($error) ?></div>
  <?php endif; ?>
  <?php if ($testResult): ?>
    <div class="<?= $testResult['success'] ? 'alert-ok' : 'alert-err' ?>">
      <?php if ($testResult['success']): ?>
        ✅ Webhook envoyé avec succès (HTTP <?= $testResult['code'] ?>) — vérifie Make.com pour voir si le scénario s'est déclenché !
      <?php else: ?>
        ❌ Erreur HTTP <?= $testResult['code'] ?> — <?= clean($testResult['response']) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- GUIDE MAKE.COM -->
  <div class="card">
    <div class="card-title">📋 Configuration Make.com — étape par étape</div>

    <div class="step">
      <div class="step-num">1</div>
      <div>
        <div class="step-title">Créer un scénario</div>
        <div class="step-desc">Sur make.com → <strong>Create a new scenario</strong></div>
      </div>
    </div>

    <div class="step">
      <div class="step-num">2</div>
      <div>
        <div class="step-title">Ajouter le module "Webhooks"</div>
        <div class="step-desc">Clique sur le <strong>+</strong> → recherche <strong>"Webhooks"</strong> → choisis <strong>"Custom webhook"</strong> → clique <strong>"Add"</strong> → donne un nom (ex: StratEdge Bet) → clique <strong>"Save"</strong><br>
        ⚠️ <strong>Copie l'URL générée</strong> (ex: https://hook.eu1.make.com/xxxx) — tu la colleras ci-dessous</div>
      </div>
    </div>

    <div class="step">
      <div class="step-num">3</div>
      <div>
        <div class="step-title">Ajouter le module "X (Twitter)"</div>
        <div class="step-desc">Clique sur le <strong>+</strong> après le webhook → recherche <strong>"X"</strong> ou <strong>"Twitter"</strong> → choisis <strong>"Create a Tweet"</strong> → connecte ton compte Twitter → dans le champ <strong>Text</strong> mets : <code style="background:rgba(255,255,255,0.06);padding:0.1rem 0.4rem;border-radius:4px;">{{1.texte}}</code></div>
      </div>
    </div>

    <div class="step">
      <div class="step-num">4</div>
      <div>
        <div class="step-title">Activer le scénario</div>
        <div class="step-desc">Clique sur le toggle en bas à gauche → passe de <strong>OFF à ON</strong> → sauvegarde</div>
      </div>
    </div>

    <div class="step">
      <div class="step-num">5</div>
      <div>
        <div class="step-title">Coller l'URL webhook ci-dessous et tester</div>
        <div class="step-desc">Colle l'URL Make dans le champ ci-dessous, active, sauvegarde, puis clique "Tester"</div>
      </div>
    </div>
  </div>

  <!-- FORMULAIRE CONFIG -->
  <div class="card">
    <div class="card-title">🔗 URL du Webhook Make.com</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="save_config">
      <div style="margin-bottom:1.2rem;">
        <label class="field-label">URL Webhook Make.com</label>
        <input type="url" name="webhook_url" class="field-input"
               value="<?= clean($config['webhook_url'] ?? '') ?>"
               placeholder="https://hook.eu1.make.com/xxxxxxxxxxxxxxxxxxxxxxxx">
        <div style="color:var(--text-muted);font-size:0.8rem;margin-top:0.4rem;">Cette URL vient de Make.com → ton scénario → module Webhooks</div>
      </div>
      <label style="display:flex;align-items:center;gap:0.9rem;background:rgba(255,45,120,0.05);border:1px solid rgba(255,45,120,0.15);border-radius:10px;padding:1rem;cursor:pointer;margin-bottom:1.2rem;">
        <input type="checkbox" name="actif" <?= !empty($config['actif']) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--neon-green);">
        <div>
          <div style="font-weight:700;">Activer l'auto-post</div>
          <div style="font-size:0.82rem;color:var(--text-muted);">Chaque nouveau bet sera envoyé à Make.com qui tweetera automatiquement</div>
        </div>
        <span style="margin-left:auto;background:<?= !empty($config['actif']) ? 'rgba(0,200,100,0.15)' : 'rgba(255,255,255,0.05)' ?>;color:<?= !empty($config['actif']) ? '#00c864' : 'var(--text-muted)' ?>;padding:0.2rem 0.7rem;border-radius:6px;font-size:0.75rem;font-weight:700;white-space:nowrap;">
          <?= !empty($config['actif']) ? '✅ Actif' : '❌ Inactif' ?>
        </span>
      </label>
      <button type="submit" class="btn-save">💾 Sauvegarder</button>
    </form>
  </div>

  <!-- TEST -->
  <?php if (!empty($config['webhook_url'])): ?>
  <div class="card">
    <div class="card-title">🧪 Tester la connexion</div>
    <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:1rem;">Envoie un webhook de test à Make.com. Si le scénario est actif, un tweet de test sera posté.</p>
    <form method="POST" onsubmit="return confirm('Envoyer un webhook de test ? Un tweet sera posté sur Twitter !')">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="test_webhook">
      <button type="submit" class="btn-test">🐦 Envoyer un test</button>
    </form>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
