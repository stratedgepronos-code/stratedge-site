<?php
/**
 * RÉCUPÉRATION COFFRE-FORT
 * Remet l'ancien hash pour pouvoir rouvrir le coffre avec l'ancien mot de passe
 * et retrouver les prompts qui étaient dedans.
 * À supprimer après récupération.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();
if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    die('Accès réservé au super admin.');
}

$configPath = __DIR__ . '/../includes/vault-config.php';
// Ancien hash (celui avec lequel tes prompts ont été chiffrés)
$originalHash = '$argon2id$v=19$m=65536,t=4,p=1$Qkl3bHdyV2Rtbm1pT2lwUA$uEPlwYRGVCblmKJ4iaVAcXzvN1SsvEBcxAk/37Nra9M';

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    $content = file_get_contents($configPath);
    $newLine = "define('VAULT_MASTER_HASH', " . var_export($originalHash, true) . ");";
    $content = preg_replace(
        "/define\s*\(\s*'VAULT_MASTER_HASH'\s*,\s*'[^']*'\s*\)\s*;/m",
        $newLine,
        $content,
        1
    );
    if ($content && file_put_contents($configPath, $content) !== false) {
        $done = true;
    } else {
        $error = 'Impossible d\'écrire dans vault-config.php. Vérifiez les droits du fichier.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Récupération coffre-fort</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0d1220; color: #e2e8f0; padding: 2rem; max-width: 600px; margin: 0 auto; }
    h1 { font-size: 1.25rem; margin-bottom: 1rem; color: #f5c842; }
    .box { background: rgba(255,255,255,0.05); border: 1px solid rgba(245,200,66,0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
    .ok { color: #34d399; }
    .warn { color: #f59e0b; font-size: 0.95rem; margin-top: 1rem; }
    button { background: linear-gradient(135deg, #f5c842, #c8960c); color: #000; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1rem; }
    button:hover { opacity: 0.95; }
    .err { color: #f87171; margin-top: 0.5rem; }
    a { color: #00d4ff; }
    ul { margin: 0.75rem 0; padding-left: 1.25rem; }
  </style>
</head>
<body>
  <h1>🔐 Récupération du coffre-fort</h1>
  <?php if ($done): ?>
    <div class="box">
      <p class="ok"><strong>✅ Ancien hash restauré.</strong></p>
      <p>Tu peux maintenant rouvrir le coffre avec le <strong>mot de passe que tu utilisais avant</strong> d’en générer un nouveau.</p>
      <p class="warn">Si tu ne te souviens plus de l’ancien mot de passe, les prompts restent malheureusement indéchiffrables (chiffrement lié au mot de passe). Une fois le coffre ouvert, sauvegarde tout au cas où, puis supprime ce fichier (vault-recovery.php).</p>
    </div>
    <p><a href="vault.php">→ Ouvrir le coffre-fort</a></p>
  <?php else: ?>
    <div class="box">
      <p>En générant un nouveau mot de passe, la clé de chiffrement a changé : les anciens prompts sont toujours en base mais chiffrés avec l’ancienne clé.</p>
      <p>En cliquant ci-dessous, on remet l’<strong>ancien hash</strong> dans la config. Tu pourras alors rouvrir le coffre avec l’<strong>ancien mot de passe</strong> et retrouver tes prompts.</p>
      <ul>
        <li>Si tu te souviens de l’ancien mot de passe → tu récupères tout.</li>
        <li>Si tu ne t’en souviens plus → les contenus restent illisibles (aucune récupération possible sans ce mot de passe).</li>
      </ul>
      <form method="post">
        <button type="submit" name="restore" value="1">Restaurer l’ancien hash (récupérer l’accès)</button>
      </form>
    </div>
  <?php endif; ?>
  <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <p style="margin-top: 2rem;"><a href="index.php">← Tableau de bord</a> · <a href="vault.php">Coffre-fort</a></p>
</body>
</html>
