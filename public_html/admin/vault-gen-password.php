<?php
/**
 * Génération d'un nouveau mot de passe coffre-fort.
 * Accès : super admin uniquement. À SUPPRIMER après utilisation.
 * Écrit le hash ligne par ligne pour éviter toute corruption (notamment les $ dans Argon2).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();
if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    die('Accès réservé au super admin.');
}

$configPath = __DIR__ . '/../includes/vault-config.php';
$done = false;
$newPassword = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $newPassword = 'StratEdgeCoffre' . bin2hex(random_bytes(4)) . '!';
    $hash = password_hash($newPassword, PASSWORD_ARGON2ID);

    $lines = file($configPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        $error = 'Impossible de lire vault-config.php.';
    } else {
        $newDefine = "define('VAULT_MASTER_HASH', " . var_export($hash, true) . ");";
        $replaced = false;
        foreach ($lines as $i => $line) {
            if (preg_match("/define\s*\(\s*['\"]VAULT_MASTER_HASH['\"]\s*,/", $line)) {
                $lines[$i] = $newDefine;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $error = 'Ligne VAULT_MASTER_HASH introuvable dans vault-config.php.';
        } elseif (file_put_contents($configPath, implode("\n", $lines) . "\n") === false) {
            $error = 'Impossible d\'écrire dans vault-config.php. Vérifiez les droits.';
        } else {
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nouveau mot de passe coffre-fort</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0d1220; color: #e2e8f0; padding: 2rem; max-width: 560px; margin: 0 auto; }
    h1 { font-size: 1.25rem; margin-bottom: 1rem; color: #f5c842; }
    .box { background: rgba(255,255,255,0.05); border: 1px solid rgba(245,200,66,0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
    .pwd { font-size: 1.25rem; font-weight: 700; color: #f5c842; letter-spacing: 1px; word-break: break-all; }
    .warn { color: #f59e0b; font-size: 0.9rem; margin-top: 1rem; }
    button { background: linear-gradient(135deg, #f5c842, #c8960c); color: #000; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1rem; }
    button:hover { opacity: 0.95; }
    .err { color: #f87171; margin-top: 0.5rem; }
    a { color: #f5c842; }
  </style>
</head>
<body>
  <h1>🔐 Nouveau mot de passe coffre-fort</h1>

  <?php if ($error): ?>
    <div class="box"><p class="err"><?= htmlspecialchars($error) ?></p></div>
  <?php endif; ?>

  <?php if ($done): ?>
    <div class="box">
      <p><strong>Nouveau mot de passe — copiez-le maintenant et gardez-le en lieu sûr :</strong></p>
      <p class="pwd"><?= htmlspecialchars($newPassword) ?></p>
      <p class="warn">⚠️ Copiez ce mot de passe tout de suite (avant de fermer l’onglet). C’est le seul endroit où il s’affiche.</p>
      <p class="warn">La config a été mise à jour. Utilisez ce mot de passe pour ouvrir le coffre. Puis supprimez ce fichier (vault-gen-password.php) pour la sécurité.</p>
    </div>
    <p><a href="vault.php">→ Ouvrir le coffre-fort</a></p>
  <?php elseif ($newPassword === '' && empty($error)): ?>
    <div class="box">
      <p class="warn" style="margin-bottom: 1rem;">⚠️ Générer un nouveau mot de passe rend les prompts existants illisibles (chiffrés avec l’ancienne clé). Utilise <a href="vault-recovery.php">vault-recovery.php</a> pour remettre l’ancien hash si besoin.</p>
      <p>Cliquez pour générer un nouveau mot de passe maître. Le fichier <code>vault-config.php</code> sera mis à jour correctement.</p>
      <form method="post">
        <button type="submit" name="generate" value="1">Générer un nouveau mot de passe</button>
      </form>
    </div>
  <?php endif; ?>
</body>
</html>
