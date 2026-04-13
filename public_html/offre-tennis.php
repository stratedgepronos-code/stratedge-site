<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/payment-config.php';
requireLogin();

$membre = getMembre();
$currentPage = 'offre-tennis';
$avatarUrl = getAvatarUrl($membre);

// Vérifier si abo tennis déjà actif
$db = getDB();
$stmt = $db->prepare("SELECT * FROM abonnements WHERE membre_id = ? AND type = 'tennis' AND date_fin > NOW() AND actif = 1 ORDER BY date_fin DESC LIMIT 1");
$stmt->execute([$membre['id']]);
$aboActif = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tennis Premium – StratEdge Pronos</title>
<meta name="description" content="Abonnement Tennis Semaine 15€ — toutes les analyses ATP/WTA pendant 7 jours. Méthodologie BASELINE SYNDICATE v1.4.">
<meta property="og:type" content="website">
<meta property="og:title" content="Pack Tennis Premium 15€ — StratEdge Pronos">
<meta property="og:description" content="Abonnement Tennis Semaine 15€ — toutes les analyses ATP/WTA pendant 7 jours. Méthodologie BASELINE SYNDICATE v1.4.">
<meta property="og:url" content="https://stratedgepronos.fr/offre-tennis.php">
<meta property="og:image" content="https://stratedgepronos.fr/assets/images/logo%20site.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="https://stratedgepronos.fr/offre-tennis.php">

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
@keyframes pulse-glow{0%,100%{filter:brightness(1) saturate(1);}50%{filter:brightness(1.18) saturate(1.4);}}
@keyframes float-mascot{0%,100%{transform:translateY(0) rotate(-2deg);}50%{transform:translateY(-5px) rotate(2deg);}}
.o-wrap{max-width:720px;margin:0 auto;padding:2.5rem 1.5rem;}
.o-back{display:inline-flex;align-items:center;gap:.4rem;color:rgba(255,255,255,.5);font-family:'Share Tech Mono',monospace;font-size:.7rem;text-decoration:none;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:1.5rem;}
.o-back:hover{color:#39ff14;}
.o-hero{text-align:center;margin-bottom:2.5rem;}
.o-mascot-wrap{display:inline-block;width:140px;height:140px;border-radius:18px;background:radial-gradient(circle at center,rgba(57,255,20,.4),transparent 70%);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;animation:pulse-glow 3s ease infinite;}
.o-mascot-wrap img{max-width:120px;max-height:130px;object-fit:contain;animation:float-mascot 3.5s ease-in-out infinite;filter:drop-shadow(0 0 18px #39ff14);}
.o-badge{display:inline-block;background:linear-gradient(135deg,#39ff14,#00d46a);color:#000;padding:.4rem 1.2rem;border-radius:20px;font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:1rem;}
.o-hero h1{font-family:'Orbitron',sans-serif;font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;background:linear-gradient(135deg,#39ff14,#00d46a);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 .8rem;letter-spacing:1.5px;}
.o-hero p{color:rgba(255,255,255,.6);font-size:1.05rem;max-width:520px;margin:0 auto;line-height:1.5;}

.o-card{position:relative;background:rgba(8,8,18,.92);border:2px solid rgba(57,255,20,.4);border-radius:18px;padding:2rem 1.8rem;overflow:hidden;}
.o-card::before,.o-card::after{content:'';position:absolute;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,#39ff14,transparent);}
.o-card::before{top:0;}.o-card::after{bottom:0;}
.o-card-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;}
.o-card-title{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:900;color:#fff;letter-spacing:1px;}
.o-card-period{font-family:'Share Tech Mono',monospace;font-size:.8rem;color:#39ff14;letter-spacing:1.5px;text-transform:uppercase;}
.o-price{display:flex;align-items:baseline;gap:.4rem;margin-bottom:1.5rem;}
.o-price .num{font-family:'Bebas Neue',cursive;font-size:4rem;line-height:1;color:#fff;letter-spacing:1px;}
.o-price .eur{font-family:'Bebas Neue',cursive;font-size:2rem;color:#39ff14;}
.o-price .per{font-family:'Share Tech Mono',monospace;font-size:.85rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;margin-left:.5rem;}

.o-features{list-style:none;padding:0;margin:0 0 1.8rem;display:flex;flex-direction:column;gap:.7rem;}
.o-features li{display:flex;align-items:flex-start;gap:.6rem;font-size:.95rem;color:rgba(255,255,255,.75);}
.o-features li::before{content:'▸';color:#39ff14;font-weight:900;flex-shrink:0;margin-top:2px;}

.o-btns{display:flex;flex-direction:column;gap:.7rem;}
.o-btn{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem 1.5rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:.8rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;text-decoration:none;cursor:pointer;border:none;transition:all .25s;}
.o-btn-cb{background:linear-gradient(135deg,#39ff14,#00d46a);color:#000;}
.o-btn-cb:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(57,255,20,.4);}
.o-btn-cr{background:transparent;color:#ffc107;border:1.5px solid rgba(255,193,7,0.3);}
.o-btn-cr:hover{background:rgba(255,193,7,0.08);border-color:#ffc107;}

.o-info{margin-top:1.5rem;padding:1rem 1.2rem;background:rgba(57,255,20,0.04);border:1px solid rgba(57,255,20,0.15);border-radius:10px;font-size:.85rem;color:rgba(255,255,255,.65);line-height:1.6;}
.o-info b{color:#39ff14;}

.o-active{background:rgba(57,255,20,0.08);border:2px solid rgba(57,255,20,0.4);border-radius:14px;padding:1.5rem;text-align:center;margin-bottom:1.5rem;}
.o-active-icon{font-size:2.5rem;margin-bottom:.5rem;}
.o-active-title{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900;color:#39ff14;letter-spacing:1px;margin-bottom:.5rem;}
.o-active-sub{font-family:'Share Tech Mono',monospace;font-size:.85rem;color:rgba(255,255,255,.6);}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="o-wrap">
  <a href="/souscrire.php" class="o-back">← Retour aux offres</a>

  <div class="o-hero">
    <div class="o-mascot-wrap">
      <img src="/assets/images/mascotte-tennis-nobg.png" alt="Tennis Premium">
    </div>
    <div class="o-badge">🎾 Tennis Premium</div>
    <h1>Pack Tennis Pro</h1>
    <p>Accède à toutes les analyses Tennis pendant 7 jours — méthodologie BASELINE SYNDICATE v1.4 appliquée à chaque pronostic ATP / WTA.</p>
  </div>

  <?php if ($aboActif): ?>
    <div class="o-active">
      <div class="o-active-icon">✅</div>
      <div class="o-active-title">Abonnement Tennis actif</div>
      <div class="o-active-sub">Expire le <?= date('d/m/Y à H:i', strtotime($aboActif['date_fin'])) ?></div>
    </div>
  <?php endif; ?>

  <div class="o-card">
    <div class="o-card-head">
      <div class="o-card-title">Tennis Semaine</div>
      <div class="o-card-period">7 jours · Premium</div>
    </div>
    <div class="o-price">
      <span class="num">15</span><span class="eur">€</span>
      <span class="per">/ semaine</span>
    </div>
    <ul class="o-features">
      <li>Toutes les analyses Tennis pendant <b style="color:#39ff14">7 jours</b></li>
      <li>Méthodologie BASELINE SYNDICATE v1.4 (5 agents, GATE LOCK 6 gates)</li>
      <li>Couverture ATP / WTA / Grand Slam / Challenger</li>
      <li>Live + Safe + Fun Tennis inclus</li>
      <li>Analyses détaillées avec H2H, surface, momentum</li>
      <li>Notification push à chaque nouvelle analyse</li>
    </ul>
    <div class="o-btns">
      <a href="/stripe-create-tennis.php" class="o-btn o-btn-cb">💳 Carte bancaire — 15€</a>
      <a href="/nowpayments-create-abo.php?type=tennis" class="o-btn o-btn-cr">₿ Crypto (BTC, ETH, USDT...)</a>
    </div>
  </div>

  <div class="o-info">
    <b>⏱️ Paiement ponctuel</b> — pas de prélèvement automatique. Tu paies une fois, tu as accès pendant 7 jours, ça expire tout seul. Tu peux racheter quand tu veux.
  </div>
</div>

<?php @require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
