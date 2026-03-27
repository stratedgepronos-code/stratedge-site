<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pageActive = 'scanner';
$db = getDB();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Command Center - Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main" style="padding:1.5rem;">
  <h1 style="font-family:'Orbitron',monospace;color:#00d4ff;margin-bottom:1rem;">Command Center</h1>
  <p style="color:#aaa;">Si tu vois ce texte, le PHP fonctionne. Le JS va se charger en dessous.</p>

  <div id="app" style="margin-top:1rem;background:#111;border:1px solid #333;border-radius:12px;padding:16px;color:#e0e0e0;font-family:'Rajdhani',sans-serif;">
    <p>Chargement du Command Center...</p>
  </div>

  <script src="scanner-app.js"></script>
</div>
</body>
</html>
