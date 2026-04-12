<?php
// STRATEDGE — SMS StarPass pour pack Unique (4.50€ uniquement)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/credits-manager.php';
requireLogin();

$membre = getMembre();

// POST : validation du code StarPass
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if ($code === '') { $err = 'Code manquant'; }
    else {
        // Validation via API StarPass (adapter selon ta config existante)
        $siteId = defined('STARPASS_SITE_ID') ? STARPASS_SITE_ID : '';
        $logins = defined('STARPASS_LOGINS') ? STARPASS_LOGINS : '';
        $url = "https://starpass.fr/api_verif.php?site={$siteId}&logins={$logins}&code=" . urlencode($code);
        $resp = @file_get_contents($url);
        if (trim($resp) === 'OK') {
            stratedge_credits_ajouter((int)$membre['id'], 'unique', 'sms', 'SP_' . $code);
            header('Location: /packs-daily.php?sms_ok=1'); exit;
        } else {
            $err = 'Code invalide ou déjà utilisé';
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<div style="max-width:500px;margin:3rem auto;padding:2rem;background:rgba(20,15,30,0.95);border:1px solid rgba(0,212,255,0.3);border-radius:16px;color:#fff;font-family:'Rajdhani',sans-serif">
  <h2 style="font-family:'Orbitron',sans-serif;color:#00d4ff;text-align:center">📱 Pack Unique par SMS</h2>
  <p style="text-align:center;opacity:.8">Envoie <strong style="color:#ff2d7a">STAR</strong> par SMS au <strong style="color:#ff2d7a">81004</strong> (4,50€ TTC)</p>
  <p style="text-align:center;opacity:.8">Tu recevras un code à saisir ci-dessous :</p>
  <?php if(!empty($err)):?><div style="background:rgba(255,45,122,0.1);border:1px solid rgba(255,45,122,0.3);padding:.8rem;border-radius:8px;color:#ff2d7a;margin:1rem 0"><?= htmlspecialchars($err) ?></div><?php endif;?>
  <form method="POST" style="display:flex;flex-direction:column;gap:1rem;margin-top:1.5rem">
    <input type="text" name="code" placeholder="Code reçu par SMS" required style="padding:1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(0,212,255,0.3);border-radius:10px;color:#fff;font-size:1.1rem;text-align:center;letter-spacing:2px">
    <button type="submit" style="padding:1rem;background:linear-gradient(135deg,#ff2d7a,#c850c0);color:#fff;border:none;border-radius:10px;font-family:'Orbitron',sans-serif;font-weight:700;cursor:pointer">✅ Valider le code</button>
  </form>
  <p style="text-align:center;margin-top:1.5rem"><a href="/packs-daily.php" style="color:#00d4ff">← Retour aux packs</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
