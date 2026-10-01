<?php
// ============================================================
// STRATEDGE — Forgot Password (demande de reset)
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Déjà connecté ? → dashboard
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$db      = getDB();
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // Chercher le membre (même si non trouvé, on affiche le même message → sécurité)
        $stmt = $db->prepare("SELECT id, nom FROM membres WHERE email = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$email]);
        $membre = $stmt->fetch();

        if ($membre) {
            // Générer un token sécurisé
            $token   = bin2hex(random_bytes(32)); // 64 chars hex
            $expiry  = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

            try {
                $db->prepare("UPDATE membres SET reset_token = ?, reset_expiry = ? WHERE id = ?")
                   ->execute([$token, $expiry, $membre['id']]);
                emailResetPassword($email, $membre['nom'], $token);
            } catch (Exception $e) {
                // Colonnes pas encore créées → message d'erreur technique
                $error = 'Fonctionnalité temporairement indisponible. Exécute la migration SQL.';
            }
        }
        // Toujours afficher le succès (même si email non trouvé → sécurité anti-enumération)
        if (!$error) $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mot de passe oublié — StratEdge</title>
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
    /* Particules décoratives */
    body::before {
      content:''; position:fixed; inset:0;
      background:radial-gradient(ellipse at 20% 50%, rgba(255,45,120,0.07) 0%, transparent 60%),
                 radial-gradient(ellipse at 80% 20%, rgba(0,212,255,0.05) 0%, transparent 50%);
      pointer-events:none; z-index:0;
    }

    .wrap { position:relative; z-index:1; width:100%; max-width:420px; }

    /* Logo */
    .logo-wrap { text-align:center; margin-bottom:2.5rem; }
    .logo-wrap img { height:32px; object-fit:contain; }
    .logo-fallback { font-family:'Orbitron',sans-serif; font-size:1.3rem; font-weight:900; color:#fff; display:none; }
    .logo-fallback em { color:var(--pink); font-style:normal; }

    /* Card */
    .auth-card {
      background:var(--card); border:1px solid var(--border);
      border-radius:20px; padding:2.2rem; position:relative; overflow:hidden;
    }
    .auth-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg, var(--pink), var(--blue), var(--pink));
    }

    .auth-tag {
      font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:3px;
      text-transform:uppercase; color:var(--pink); margin-bottom:0.6rem;
    }
    .auth-title { font-family:'Orbitron',sans-serif; font-size:1.3rem; font-weight:900; margin-bottom:0.4rem; }
    .auth-sub { color:var(--txt3); font-size:0.88rem; margin-bottom:2rem; line-height:1.5; }

    /* Success state */
    .success-box {
      text-align:center; padding:1.5rem 0;
    }
    .success-icon { font-size:3rem; margin-bottom:1rem; }
    .success-title { font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:700; margin-bottom:0.6rem; }
    .success-text { color:var(--txt3); font-size:0.88rem; line-height:1.6; }

    /* Form */
    .form-group { display:flex; flex-direction:column; gap:0.4rem; margin-bottom:1.2rem; }
    .form-group label {
      font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:2px;
      text-transform:uppercase; color:var(--txt3);
    }
    .form-group input {
      background:rgba(255,255,255,0.04); border:1px solid var(--border-soft);
      border-radius:10px; padding:0.85rem 1.1rem; color:var(--txt);
      font-family:'Rajdhani',sans-serif; font-size:1rem; outline:none; transition:border-color .2s;
    }
    .form-group input:focus { border-color:rgba(255,45,120,0.5); }
    .form-group input::placeholder { color:var(--txt3); }

    .btn-submit {
      width:100%; background:linear-gradient(135deg,var(--pink),var(--pink-dim));
      color:#fff; border:none; border-radius:12px; padding:1rem;
      font-family:'Rajdhani',sans-serif; font-size:1.05rem; font-weight:700;
      text-transform:uppercase; letter-spacing:1px; cursor:pointer;
      transition:all .3s; box-shadow:0 4px 20px rgba(255,45,120,0.25);
    }
    .btn-submit:hover { box-shadow:0 6px 30px rgba(255,45,120,0.45); transform:translateY(-2px); }

    .alert-error {
      background:rgba(255,100,100,0.08); border:1px solid rgba(255,100,100,0.2);
      border-radius:10px; padding:0.75rem 1rem; color:#ff6b9d;
      font-size:0.85rem; margin-bottom:1.2rem;
    }
    .auth-footer { text-align:center; margin-top:1.5rem; color:var(--txt3); font-size:0.85rem; }
    .auth-footer a { color:var(--pink); text-decoration:none; font-weight:600; }
    .auth-footer a:hover { text-decoration:underline; }
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
        <div class="success-icon">📬</div>
        <div class="success-title">Email envoyé !</div>
        <p class="success-text">
          Si cette adresse est associée à un compte, tu recevras un lien de réinitialisation dans les prochaines minutes.<br><br>
          <strong>Pense à vérifier tes spams.</strong><br>
          Le lien est valable <strong>30 minutes</strong>.
        </p>
      </div>
    <?php else: ?>

      <div class="auth-tag">// Récupération</div>
      <h1 class="auth-title">Mot de passe oublié ?</h1>
      <p class="auth-sub">Entre ton adresse email et on t'envoie un lien pour créer un nouveau mot de passe.</p>

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label>Adresse email</label>
          <input type="email" name="email" placeholder="ton@email.com" required autofocus
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn-submit">📨 Envoyer le lien</button>
      </form>

    <?php endif; ?>

  </div>

  <div class="auth-footer">
    <a href="login.php">← Retour à la connexion</a>
  </div>

</div>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
