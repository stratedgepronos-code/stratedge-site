<?php
// ============================================================
// STRATEDGE — Reset Password (avec token)
// ============================================================
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$db    = getDB();
$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;
$membre  = null;

// Valider le token
if ($token) {
    try {
        $stmt = $db->prepare("SELECT id, nom, email FROM membres WHERE reset_token = ? AND reset_expiry > NOW() AND actif = 1 LIMIT 1");
        $stmt->execute([$token]);
        $membre = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'Fonctionnalité indisponible — exécute la migration SQL.';
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '') && $membre) {
    $nouveau  = $_POST['nouveau_mdp'] ?? '';
    $confirm  = $_POST['confirm_mdp'] ?? '';

    if (strlen($nouveau) < 8) {
        $error = 'Le mot de passe doit faire au moins 8 caractères.';
    } elseif ($nouveau !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($nouveau, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE membres SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?")
           ->execute([$hash, $membre['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nouveau mot de passe — StratEdge</title>
  <link rel="icon" type="image/png" href="assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&family=Space+Mono:wght@400&display=swap" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    :root {
      --bg:#050810; --card:#0d1220;
      --pink:#ff2d78; --pink-dim:#d6245f;
      --blue:#00d4ff;
      --txt:#f0f4f8; --txt2:#b0bec9; --txt3:#8a9bb0;
      --border:rgba(255,45,120,0.12); --border-soft:rgba(255,255,255,0.07);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      background:var(--bg); color:var(--txt); font-family:'Rajdhani',sans-serif;
      min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;
      padding:1.5rem;
    }
    body::before {
      content:''; position:fixed; inset:0;
      background:radial-gradient(ellipse at 20% 50%, rgba(255,45,120,0.07) 0%, transparent 60%),
                 radial-gradient(ellipse at 80% 20%, rgba(0,212,255,0.05) 0%, transparent 50%);
      pointer-events:none; z-index:0;
    }
    .wrap { position:relative; z-index:1; width:100%; max-width:420px; }
    .logo-wrap { text-align:center; margin-bottom:2.5rem; }
    .logo-wrap img { height:32px; object-fit:contain; }
    .logo-fallback { font-family:'Orbitron',sans-serif; font-size:1.3rem; font-weight:900; color:#fff; display:none; }
    .logo-fallback em { color:var(--pink); font-style:normal; }

    .auth-card {
      background:var(--card); border:1px solid var(--border);
      border-radius:20px; padding:2.2rem; position:relative; overflow:hidden;
    }
    .auth-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg, var(--pink), var(--blue), var(--pink));
    }
    .auth-tag { font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:3px; text-transform:uppercase; color:var(--pink); margin-bottom:0.6rem; }
    .auth-title { font-family:'Orbitron',sans-serif; font-size:1.2rem; font-weight:900; margin-bottom:0.4rem; }
    .auth-sub { color:var(--txt3); font-size:0.88rem; margin-bottom:2rem; }

    .form-group { display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1.2rem; }
    .form-group label { font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:2px; text-transform:uppercase; color:var(--txt3); }
    .form-group input {
      background:rgba(255,255,255,0.04); border:1px solid var(--border-soft);
      border-radius:10px; padding:0.85rem 1.1rem; color:var(--txt);
      font-family:'Rajdhani',sans-serif; font-size:1rem; outline:none; transition:border-color .2s;
    }
    .form-group input:focus { border-color:rgba(255,45,120,0.5); }
    .form-group input::placeholder { color:var(--txt3); }

    .strength-bar { height:4px; border-radius:2px; margin-top:0.4rem; background:rgba(255,255,255,0.08); overflow:hidden; }
    .strength-fill { height:100%; border-radius:2px; transition:all .3s; width:0; }
    .strength-label { font-size:0.72rem; color:var(--txt3); margin-top:0.2rem; }

    .btn-submit {
      width:100%; background:linear-gradient(135deg,var(--pink),var(--pink-dim));
      color:#fff; border:none; border-radius:12px; padding:1rem;
      font-family:'Rajdhani',sans-serif; font-size:1.05rem; font-weight:700;
      text-transform:uppercase; letter-spacing:1px; cursor:pointer;
      transition:all .3s; box-shadow:0 4px 20px rgba(255,45,120,0.25);
    }
    .btn-submit:hover { box-shadow:0 6px 30px rgba(255,45,120,0.45); transform:translateY(-2px); }

    .alert-error { background:rgba(255,100,100,0.08); border:1px solid rgba(255,100,100,0.2); border-radius:10px; padding:0.75rem 1rem; color:#ff6b9d; font-size:0.85rem; margin-bottom:1.2rem; }
    .alert-warn  { background:rgba(255,193,7,0.08);  border:1px solid rgba(255,193,7,0.2);  border-radius:10px; padding:1.5rem; text-align:center; color:#ffc107; }

    .success-box { text-align:center; padding:1rem 0; }
    .success-icon { font-size:3rem; margin-bottom:1rem; }
    .success-title { font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:700; margin-bottom:0.6rem; }

    .auth-footer { text-align:center; margin-top:1.5rem; color:var(--txt3); font-size:0.85rem; }
    .auth-footer a { color:var(--pink); text-decoration:none; font-weight:600; }

    #matchMsg { font-size:0.78rem; margin-top:0.3rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="logo-wrap">
    <a href="/">
      <img src="assets/images/logo site.png" alt="StratEdge"
           onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
      <span class="logo-fallback"><em>STRAT</em>EDGE</span>
    </a>
  </div>

  <div class="auth-card">

    <?php if ($success): ?>
      <div class="success-box">
        <div class="success-icon">🔓</div>
        <div class="success-title">Mot de passe modifié !</div>
        <p style="color:var(--txt3);font-size:0.88rem;margin-bottom:1.5rem;">Tu peux maintenant te connecter avec ton nouveau mot de passe.</p>
        <a href="login.php" style="display:inline-block;background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff;text-decoration:none;padding:0.8rem 2rem;border-radius:12px;font-weight:700;">
          Se connecter →
        </a>
      </div>

    <?php elseif (!$membre): ?>
      <div class="alert-warn">
        <div style="font-size:2rem;margin-bottom:0.5rem;">⏰</div>
        <div style="font-weight:700;margin-bottom:0.4rem;">Lien invalide ou expiré</div>
        <div style="font-size:0.85rem;color:rgba(255,193,7,0.7);">Ce lien de réinitialisation a expiré ou n'est plus valide (30 min max).</div>
        <a href="forgot-password.php" style="display:inline-block;margin-top:1.2rem;background:rgba(255,193,7,0.15);border:1px solid rgba(255,193,7,0.3);color:#ffc107;padding:0.6rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.9rem;">
          Faire une nouvelle demande
        </a>
      </div>

    <?php else: ?>

      <div class="auth-tag">// Sécurité</div>
      <h1 class="auth-title">Nouveau mot de passe</h1>
      <p class="auth-sub">Bonjour <strong><?= htmlspecialchars($membre['nom']) ?></strong>, choisis un nouveau mot de passe sécurisé.</p>

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group">
          <label>Nouveau mot de passe</label>
          <input type="password" name="nouveau_mdp" id="pwd" placeholder="Min. 8 caractères" minlength="8" required autofocus oninput="checkStrength(this.value)">
          <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
          <div class="strength-label" id="strengthLabel"></div>
        </div>

        <div class="form-group">
          <label>Confirmer le mot de passe</label>
          <input type="password" name="confirm_mdp" id="confirmPwd" placeholder="••••••••" required oninput="checkMatch()">
          <div id="matchMsg"></div>
        </div>

        <button type="submit" class="btn-submit">🔑 Enregistrer le mot de passe</button>
      </form>

    <?php endif; ?>

  </div>

  <div class="auth-footer">
    <a href="login.php">← Retour à la connexion</a>
  </div>

</div>

<script>
function checkStrength(val) {
  const fill = document.getElementById('strengthFill');
  const lbl  = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^a-zA-Z0-9]/.test(val)) score++;

  const levels = [
    { pct: '20%', color: '#ef4444', label: 'Très faible' },
    { pct: '40%', color: '#f97316', label: 'Faible' },
    { pct: '60%', color: '#eab308', label: 'Moyen' },
    { pct: '80%', color: '#22c55e', label: 'Fort' },
    { pct: '100%', color: '#ff2d78', label: '🔥 Très fort' },
  ];
  const lvl = levels[Math.min(score - 1, 4)] || levels[0];
  fill.style.width = val ? lvl.pct : '0';
  fill.style.background = lvl.color;
  lbl.textContent = val ? lvl.label : '';
  lbl.style.color = lvl.color;
}

function checkMatch() {
  const pwd  = document.getElementById('pwd').value;
  const conf = document.getElementById('confirmPwd').value;
  const msg  = document.getElementById('matchMsg');
  if (!conf) { msg.textContent = ''; return; }
  if (pwd === conf) {
    msg.textContent = '✓ Les mots de passe correspondent';
    msg.style.color = '#ff2d78';
  } else {
    msg.textContent = '✗ Les mots de passe ne correspondent pas';
    msg.style.color = '#ff6b9d';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
