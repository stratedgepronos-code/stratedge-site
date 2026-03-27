<?php
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
  <style>
    :root { --bg-dark:#050810; --bg-card:#0d1220; --neon-green:#ff2d78; --neon-green-dim:#d6245f; --neon-blue:#00d4ff; --neon-purple:#a855f7; --text-primary:#f0f4f8; --text-secondary:#b0bec9; --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.12); }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; display:flex; overflow-x:hidden; }
    .main { padding:2rem; flex:1; min-height:100vh; margin-left:240px; }
    @media(max-width:900px){ .main { margin-left:0 !important; padding-top:58px; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <h1 style="font-family:'Orbitron',monospace;color:#00d4ff;margin-bottom:1rem;">Command Center</h1>

  <div id="app" style="margin-top:1rem;background:#111;border:1px solid #333;border-radius:12px;padding:16px;color:#e0e0e0;font-family:'Rajdhani',sans-serif;">
    <p>Chargement du Command Center...</p>
  </div>

  <script src="scanner-app.js"></script>
</div>
</body>
</html>
