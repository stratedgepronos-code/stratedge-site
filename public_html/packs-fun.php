<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/packs-config.php';
require_once __DIR__ . '/includes/credits-manager.php';
requireLogin();

$membre = getMembre();
$currentPage = 'packs-fun';
$avatarUrl = getAvatarUrl($membre);
$solde = stratedge_credits_solde((int)$membre['id']);
$packs = stratedge_packs_by_sport('fun');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Fun Only – StratEdge Pronos</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.pk-wrap{max-width:700px;margin:0 auto;padding:2rem 1.5rem}
.pk-hero{text-align:center;margin-bottom:2.5rem}
.pk-hero .badge{display:inline-block;background:linear-gradient(135deg,#a855f7,#ec4899);color:#fff;padding:.4rem 1.2rem;border-radius:20px;font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:1rem}
.pk-hero h1{font-family:'Orbitron',sans-serif;font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;background:linear-gradient(135deg,#a855f7,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 .8rem}
.pk-hero p{font-size:1.05rem;color:rgba(255,255,255,0.6);max-width:550px;margin:0 auto}
.pk-solde{display:inline-flex;align-items:center;gap:.6rem;background:rgba(57,255,20,0.06);border:1px solid rgba(57,255,20,0.25);padding:.7rem 1.5rem;border-radius:50px;margin-top:1.2rem;font-family:'Orbitron',sans-serif;font-size:.85rem;color:rgba(255,255,255,.7)}
.pk-solde b{font-size:1.4rem;color:#a855f7}
.pk-card{position:relative;background:rgba(12,10,20,0.92);border:2px solid #a855f740;border-radius:20px;padding:2.5rem 2rem;text-align:center;overflow:hidden;transition:transform .3s}
.pk-card:hover{transform:translateY(-4px);box-shadow:0 20px 60px #a855f720}
.pk-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#a855f7,#ec4899)}
.pk-emoji{font-size:4rem;line-height:1;margin-bottom:.8rem}
.pk-name{font-family:'Orbitron',sans-serif;font-size:1.8rem;font-weight:900;color:#fff;margin-bottom:.2rem}
.pk-sub{font-size:.95rem;color:rgba(255,255,255,0.6);margin-bottom:2rem}
.pk-price{font-family:'Orbitron',sans-serif;font-size:3.5rem;font-weight:900;color:#fff;margin-bottom:.3rem}.pk-price span{font-size:1.5rem;color:#a855f7}
.pk-unit{font-size:.95rem;color:rgba(255,255,255,0.5);margin-bottom:2rem}
.pk-btns{display:flex;flex-direction:column;gap:.6rem;max-width:320px;margin:0 auto}
.pk-btn{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;text-decoration:none;transition:all .25s;cursor:pointer;border:none}
.pk-btn-cb{background:linear-gradient(135deg,#a855f7,#ec4899);color:#000}.pk-btn-cb:hover{transform:translateY(-2px);box-shadow:0 8px 20px #a855f740}
.pk-btn-cr{background:transparent;color:#ffc107;border:1px solid rgba(255,193,7,0.25)}.pk-btn-cr:hover{background:rgba(255,193,7,0.08)}
.pk-info{margin-top:2rem;padding:1.2rem;background:rgba(255,255,255,0.03);border-radius:10px;font-size:.9rem;color:rgba(255,255,255,.65);line-height:1.6}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="pk-wrap">
  <div class="pk-hero">
    <div class="badge">🎲 Fun Only</div>
    <h1>Fun Only</h1>
    <p>Bets délirants, cotes folles — pour le fun et les gros rendements</p>
    <div class="pk-solde">💎 Crédits disponibles : <b><?= $solde ?></b></div>
  </div>

  <?php foreach($packs as $k => $p): ?>
  <div class="pk-card">
    <div class="pk-emoji">🎲</div>
    <div class="pk-name"><?= htmlspecialchars($p['label']) ?></div>
    <div class="pk-sub"><?= htmlspecialchars($p['sub']) ?></div>
    <div class="pk-price"><span>€</span><?= number_format($p['prix'], 2, ',', '') ?></div>
    <div class="pk-unit"><?= $p['nb'] ?> analyse<?= $p['nb']>1?'s':'' ?> · à vie</div>
    <div class="pk-btns">
      <?php if(in_array('stripe', $p['methodes'])): ?>
      <a href="/stripe-create-pack.php?pack=<?= $k ?>" class="pk-btn pk-btn-cb">💳 Carte bancaire</a>
      <?php endif; ?>
      <?php if(in_array('crypto', $p['methodes'])): ?>
      <a href="/nowpayments-create-pack.php?pack=<?= $k ?>" class="pk-btn pk-btn-cr">₿ Crypto</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="pk-info">
    <strong style="color:#a855f7">À vie</strong> — ton crédit n'expire jamais. Utilise-le quand un bet Fun Only t'intéresse, gratuitement à la reconsultation.
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
