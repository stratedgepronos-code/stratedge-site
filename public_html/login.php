<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$redirect = isset($_GET['redirect']) ? clean($_GET['redirect']) : 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité. Rechargez la page.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $result = loginMembre($email, $password);
            if ($result['success']) {
                // Si admin, rediriger vers le panel
                if (isAdmin()) {
                    header('Location: /panel-x9k3m/');
                    exit;
                }
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion – StratEdge Pronos</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --bg-dark:#0a0e17; --bg-card:#111827; --neon-green:#ff2d78; --neon-green-dim:#d6245f; --neon-blue:#00d4ff; --text-primary:#f0f4f8; --text-secondary:#b0bec9; --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.15); --glow-green:0 0 30px rgba(255,45,120,0.35); }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; display:flex; flex-direction:column; }
    body::before { content:''; position:fixed; inset:0; background:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E"); pointer-events:none; z-index:9999; }

    nav { background:rgba(10,14,23,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border-subtle); padding:0 2rem; }
    .nav-inner { max-width:1200px; margin:0 auto; display:flex; align-items:center; height:70px; }
    .logo img { height:45px; }

    .page-wrapper { flex:1; display:flex; align-items:center; justify-content:center; padding:2rem; position:relative; }
    .glow-bg { position:absolute; width:600px; height:600px; background:radial-gradient(circle, rgba(255,45,120,0.07) 0%, transparent 70%); top:50%; left:50%; transform:translate(-50%,-50%); pointer-events:none; }

    .auth-card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:20px; padding:3rem; width:100%; max-width:460px; position:relative; z-index:1; }
    .auth-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg, var(--neon-green), var(--neon-blue), var(--neon-green)); border-radius:20px 20px 0 0; }

    .auth-tag { font-family:'Space Mono',monospace; font-size:0.7rem; letter-spacing:3px; text-transform:uppercase; color:var(--neon-green); margin-bottom:0.75rem; }
    .auth-title { font-family:'Orbitron',sans-serif; font-size:1.8rem; font-weight:700; margin-bottom:0.5rem; }
    .auth-subtitle { color:var(--text-muted); font-size:0.95rem; margin-bottom:2rem; }

    .form-group { margin-bottom:1.5rem; }
    .form-group label { display:block; font-size:0.85rem; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:var(--text-secondary); margin-bottom:0.5rem; }
    .form-group input { width:100%; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:0.9rem 1.2rem; color:var(--text-primary); font-family:'Rajdhani',sans-serif; font-size:1rem; transition:all 0.3s; outline:none; }
    .form-group input:focus { border-color:var(--neon-green); box-shadow:0 0 0 3px rgba(255,45,120,0.1); }
    .form-group input::placeholder { color:var(--text-muted); }

    .btn-submit { width:100%; background:linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color:white; padding:1rem; border:none; border-radius:10px; font-family:'Rajdhani',sans-serif; font-size:1.1rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; cursor:pointer; transition:all 0.3s; margin-top:0.5rem; }
    .btn-submit:hover { box-shadow:var(--glow-green); transform:translateY(-2px); }

    .error-msg { background:rgba(255,45,120,0.1); border:1px solid rgba(255,45,120,0.3); border-radius:10px; padding:1rem; margin-bottom:1.5rem; color:#ff6b9d; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem; }

    .auth-footer { text-align:center; margin-top:1.5rem; color:var(--text-muted); font-size:0.9rem; }
    .auth-footer a { color:var(--neon-green); text-decoration:none; font-weight:600; }
    .auth-footer a:hover { text-decoration:underline; }

    .back-home { text-align:center; margin-top:1rem; }
    .back-home a { color:var(--text-muted); font-size:0.85rem; text-decoration:none; transition:color 0.3s; }
    .back-home a:hover { color:var(--text-primary); }
    @media (max-width:480px) {
      nav { padding:0 1rem; }
      .logo img { height:36px; }
      .page-wrapper { padding:1rem; align-items:flex-start; padding-top:1.5rem; }
      .auth-card { padding:1.8rem 1.2rem; border-radius:14px; }
      .auth-title { font-size:1.4rem; }
      .auth-subtitle { font-size:0.88rem; }
    }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0a0e17">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StratEdge">
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
</head>
<body>
  <nav>
    <div class="nav-inner">
      <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge Pronos"></a>
    </div>
  </nav>

  <div class="page-wrapper">
    <div class="glow-bg"></div>
    <div class="auth-card">
      <div class="auth-tag">Espace membre</div>
      <h1 class="auth-title">Connexion</h1>
      <p class="auth-subtitle">Accédez à vos bets et à votre espace personnel.</p>

      <?php if ($error): ?>
        <div class="error-msg">⚠️ <?= clean($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="redirect" value="<?= clean($redirect) ?>">

        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" placeholder="votre@email.com" required
                 value="<?= clean($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Se connecter →</button>
      </form>

      <div class="auth-footer">
        Mot de passe oublié ? <a href="forgot-password.php">Réinitialiser</a><br><br>
      Pas encore de compte ? <a href="register.php">S'inscrire gratuitement</a>
      </div>
      <div class="back-home">
        <a href="/">← Retour à l'accueil</a>
      </div>
    </div>
  </div>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
