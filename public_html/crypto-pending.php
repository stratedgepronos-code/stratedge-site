<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$db        = getDB();
$paymentId = (int)($_GET['id'] ?? 0);
$payment   = null;
if ($paymentId) {
    $s = $db->prepare("SELECT * FROM crypto_payments WHERE id = ? AND membre_id = ?");
    $s->execute([$paymentId, getMembre()['id']]);
    $payment = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Transaction en cours — StratEdge</title>
  <link rel="icon" type="image/png" href="/assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Rajdhani',sans-serif;background:#060810;color:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
    .card{background:#0c1018;border:1px solid rgba(255,255,255,0.07);border-radius:24px;padding:3rem;max-width:520px;width:100%;text-align:center;position:relative;overflow:hidden;}
    .card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#ff2d78,#00d4ff);}
    .icon{font-size:4rem;margin-bottom:1.5rem;animation:pulse 2s ease-in-out infinite;}
    @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
    h1{font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:900;margin-bottom:0.75rem;}
    .accent{color:#ff2d78;}
    p{color:#8a9bb0;line-height:1.6;margin-bottom:1rem;font-size:0.92rem;}
    .info-box{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:1rem 1.2rem;text-align:left;margin:1.5rem 0;}
    .info-row{display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.85rem;}
    .info-row:last-child{border-bottom:none;}
    .info-label{color:#6b7a90;}
    .info-val{color:#b0bec9;font-weight:600;}
    .status{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(255,193,7,0.1);border:1px solid rgba(255,193,7,0.25);color:#ffc107;border-radius:20px;padding:0.3rem 0.9rem;font-size:0.78rem;font-weight:700;margin-bottom:1.5rem;}
    .btn{display:inline-block;padding:0.8rem 2rem;background:linear-gradient(135deg,#ff2d78,#c4185a);color:#fff;border-radius:10px;text-decoration:none;font-family:'Orbitron',sans-serif;font-size:0.78rem;font-weight:700;letter-spacing:1px;transition:all 0.2s;}
    .btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,45,120,0.35);}
  </style>
</head>
<body>
<div class="card">
  <div class="icon">⏳</div>
  <h1>Transaction <span class="accent">en cours</span></h1>
  <div class="status">🕐 En attente de validation</div>
  <p>Votre transaction a bien été soumise. Notre équipe la vérifie sur la blockchain et activera votre accès sous <strong style="color:#f0f4f8">30 minutes</strong> maximum.</p>

  <?php if ($payment): ?>
  <div class="info-box">
    <div class="info-row"><span class="info-label">Offre</span><span class="info-val"><?= clean(ucfirst($payment['offre'])) ?></span></div>
    <div class="info-row"><span class="info-label">Crypto</span><span class="info-val"><?= strtoupper(clean($payment['crypto'])) ?></span></div>
    <div class="info-row"><span class="info-label">TX Hash</span><span class="info-val" style="font-size:0.72rem;word-break:break-all;"><?= clean(substr($payment['tx_hash'],0,24)) ?>...</span></div>
    <div class="info-row"><span class="info-label">Soumis le</span><span class="info-val"><?= date('d/m/Y à H:i', strtotime($payment['date_demande'])) ?></span></div>
  </div>
  <?php endif; ?>

  <p style="font-size:0.8rem;color:#6b7a90;">Un email vous sera envoyé dès activation. En cas de problème, contactez <strong style="color:#b0bec9">stratedgepronos@gmail.com</strong></p>
  <a href="/dashboard.php" class="btn">← Retour au dashboard</a>
</div>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
