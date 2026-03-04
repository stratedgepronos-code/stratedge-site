<?php
/**
 * Désinscription des notifications par email (RGPD / LCEN).
 * Lien obligatoire dans chaque email commercial / notification.
 * Usage : clic sur le lien dans l'email (GET avec e= et h=) ou formulaire (POST avec email).
 */
require_once __DIR__ . '/includes/db.php';

$done    = false;
$error   = '';
$message = '';

// ── Vérifier signature désinscription (e = base64(email), h = hmac) ──
function verifTokenDesabonnement(string $e, string $h): ?string {
    $email = base64_decode($e, true);
    if ($email === false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $expected = hash_hmac('sha256', $email, SECRET_KEY);
    if (!hash_equals($expected, $h)) {
        return null;
    }
    return $email;
}

// ── GET : clic sur le lien dans l'email ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['e']) && !empty($_GET['h'])) {
    $email = verifTokenDesabonnement($_GET['e'], $_GET['h']);
    if ($email) {
        $db = getDB();
        $db->prepare("UPDATE membres SET accepte_emails = 0 WHERE email = ?")->execute([$email]);
        $done = true;
        $message = 'Vous êtes bien désinscrit des notifications par email. Vous ne recevrez plus les alertes (nouveaux bets, résultats).';
    } else {
        $error = 'Lien invalide ou expiré. Utilisez le formulaire ci-dessous avec votre adresse email.';
    }
}

// ── POST : formulaire (saisie manuelle de l'email) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("UPDATE membres SET accepte_emails = 0 WHERE email = ?");
        $stmt->execute([$email]);
        $done = true;
        // Toujours le même message (éviter énumération)
        $message = 'Si cette adresse est inscrite chez nous, vous ne recevrez plus les notifications par email (nouveaux bets, résultats).';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Désinscription des emails — StratEdge Pronos</title>
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#0a0e17; --card:#111827; --border:rgba(255,45,120,0.2); --accent:#ff2d78; --text:#f0f4f8; --muted:#8a9bb0; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
    .box { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:2rem; max-width:420px; width:100%; }
    h1 { font-size:1.35rem; margin-bottom:0.5rem; color:var(--accent); }
    .sub { color:var(--muted); font-size:0.9rem; margin-bottom:1.5rem; }
    .msg { background:rgba(0,212,106,0.1); border:1px solid rgba(0,212,106,0.3); border-radius:10px; padding:1rem; margin-bottom:1rem; color:#00d46a; font-size:0.95rem; }
    .err { background:rgba(255,45,120,0.1); border:1px solid rgba(255,45,120,0.3); border-radius:10px; padding:1rem; margin-bottom:1rem; color:#ff6b9d; font-size:0.9rem; }
    label { display:block; font-size:0.85rem; color:var(--muted); margin-bottom:0.4rem; }
    input[type=email] { width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); border-radius:8px; padding:0.75rem 1rem; color:var(--text); font-size:1rem; margin-bottom:1rem; }
    input[type=email]:focus { outline:none; border-color:var(--accent); }
    button { width:100%; background:linear-gradient(135deg, var(--accent), #d6245f); color:#fff; border:none; padding:0.85rem; border-radius:8px; font-weight:700; font-size:1rem; cursor:pointer; }
    button:hover { opacity:0.95; }
    .back { text-align:center; margin-top:1.25rem; }
    .back a { color:var(--muted); font-size:0.9rem; text-decoration:none; }
    .back a:hover { color:var(--accent); }
  </style>
</head>
<body>
  <div class="box">
    <h1>📧 Désinscription des emails</h1>
    <p class="sub">StratEdge Pronos — Conformité RGPD / LCEN</p>

    <?php if ($done): ?>
      <div class="msg"><?= htmlspecialchars($message) ?></div>
      <p class="sub" style="margin-bottom:0;">Vous pouvez à tout moment réactiver les notifications depuis votre espace membre (Tableau de bord → Préférences email).</p>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="err"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <p class="sub" style="margin-bottom:1rem;">Saisissez votre adresse email pour ne plus recevoir les notifications (nouveaux bets, résultats, messages).</p>
      <form method="POST">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email" placeholder="votre@email.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <button type="submit">Me désinscrire</button>
      </form>
    <?php endif; ?>

    <div class="back">
      <a href="https://stratedgepronos.fr/">← Retour à l'accueil</a>
    </div>
  </div>
</body>
</html>
