<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'scanner';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>⚡ Command Center — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    .main { padding: 0 !important; }
    .scanner-frame {
      width: 100%;
      height: calc(100vh - 0px);
      border: none;
      display: block;
    }
    @media (max-width: 900px) {
      .scanner-frame { height: calc(100vh - 58px); }
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <iframe src="scanner-center.html" class="scanner-frame" title="StratEdge Command Center"></iframe>
</div>

</body>
</html>
