<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/packs-config.php';
require_once __DIR__ . '/includes/credits-manager.php';
requireLogin();

$membre = getMembre();
$currentPage = 'packs-daily';
$avatarUrl = getAvatarUrl($membre);
$solde = stratedge_credits_solde((int)$membre['id']);
$packs = stratedge_packs_config();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Crédits Paris – StratEdge Pronos</title>
<meta name="description" content="Packs crédits paris à vie dès 4,50€ — foot, NBA, NHL, MLB. Achète à l'unité ou en pack, économie jusqu'à -33%.">
<meta property="og:type" content="website">
<meta property="og:title" content="Packs crédits Multisports — StratEdge Pronos">
<meta property="og:description" content="Packs crédits paris à vie dès 4,50€ — foot, NBA, NHL, MLB. Achète à l'unité ou en pack, économie jusqu'à -33%.">
<meta property="og:url" content="https://stratedgepronos.fr/packs-daily.php">
<meta property="og:image" content="https://stratedgepronos.fr/assets/images/logo%20site.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="https://stratedgepronos.fr/packs-daily.php">

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.pk-wrap{max-width:1200px;margin:0 auto;padding:2rem 1.5rem}
.pk-hero{text-align:center;margin-bottom:2.5rem}
.pk-hero h1{font-family:'Orbitron',sans-serif;font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;background:linear-gradient(135deg,#ff2d7a,#c850c0,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 .8rem}
.pk-hero p{font-size:1.05rem;color:rgba(255,255,255,0.6);max-width:600px;margin:0 auto}
.pk-solde{display:inline-flex;align-items:center;gap:.6rem;background:rgba(0,212,255,0.06);border:1px solid rgba(0,212,255,0.25);padding:.7rem 1.5rem;border-radius:50px;margin-top:1.2rem;font-family:'Orbitron',sans-serif;font-size:.85rem;color:rgba(255,255,255,.7)}
.pk-solde b{font-size:1.4rem;color:#00d4ff}
.pk-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem;margin-bottom:3rem}
@media(max-width:960px){.pk-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:580px){.pk-grid{grid-template-columns:1fr;max-width:400px;margin-left:auto;margin-right:auto}}

.pk-card{position:relative;background:rgba(12,10,20,0.92);border:1px solid rgba(255,255,255,0.08);border-radius:18px;padding:1.8rem 1.4rem 1.4rem;text-align:center;transition:all .35s ease;overflow:hidden}
.pk-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#ff2d7a,#c850c0,#00d4ff);opacity:.5;transition:opacity .3s}
.pk-card:hover{transform:translateY(-5px);border-color:rgba(200,80,192,0.4);box-shadow:0 12px 40px rgba(200,80,192,0.15)}
.pk-card:hover::after{opacity:1}
.pk-card.hot{border-color:rgba(0,212,255,0.4);box-shadow:0 0 30px rgba(0,212,255,0.1)}
.pk-card.hot::after{opacity:1;height:3px}

.pk-tag{position:absolute;top:.8rem;right:.8rem;background:linear-gradient(135deg,#ff2d7a,#c850c0);color:#fff;padding:.25rem .7rem;border-radius:14px;font-family:'Orbitron',sans-serif;font-size:.58rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase}
.pk-num{font-family:'Bebas Neue',sans-serif;font-size:4rem;line-height:1;background:linear-gradient(180deg,#fff 30%,rgba(255,255,255,.3));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:.5rem 0 .1rem}
.pk-num-lbl{font-family:'Rajdhani',sans-serif;font-size:.8rem;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:2px;margin-bottom:1.2rem}
.pk-name{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:.15rem}
.pk-sub{font-size:.8rem;color:rgba(255,255,255,0.4);margin-bottom:1rem;min-height:1em}
.pk-price{font-family:'Orbitron',sans-serif;font-size:2rem;font-weight:900;color:#fff;margin-bottom:.2rem}.pk-price span{font-size:1rem;color:#00d4ff}
.pk-unit{font-size:.75rem;color:rgba(255,255,255,0.4);margin-bottom:.3rem}
.pk-eco{display:inline-block;background:rgba(57,255,20,0.08);border:1px solid rgba(57,255,20,0.25);color:#39ff14;padding:.2rem .6rem;border-radius:10px;font-size:.7rem;font-weight:700;margin-bottom:1.2rem;font-family:'Orbitron',sans-serif}
.pk-btns{display:flex;flex-direction:column;gap:.45rem}
.pk-btn{display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.65rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:.68rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;text-decoration:none;transition:all .25s;cursor:pointer;border:none}
.pk-btn-cb{background:linear-gradient(135deg,#ff2d7a,#c850c0);color:#fff}.pk-btn-cb:hover{box-shadow:0 6px 18px rgba(200,80,192,0.4);transform:translateY(-1px)}
.pk-btn-cr{background:transparent;color:#ffc107;border:1px solid rgba(255,193,7,0.25)}.pk-btn-cr:hover{background:rgba(255,193,7,0.08)}
.pk-btn-sm{background:transparent;color:#00d4ff;border:1px solid rgba(0,212,255,0.25)}.pk-btn-sm:hover{background:rgba(0,212,255,0.08)}
.pk-btn-psc{background:linear-gradient(135deg,#0074d9,#00a8e8);color:#fff;border:none}.pk-btn-psc:hover{box-shadow:0 6px 18px rgba(0,116,217,0.4);transform:translateY(-1px)}

.pk-faq{max-width:700px;margin:2rem auto 3rem;padding:0 1rem}
.pk-faq h2{font-family:'Orbitron',sans-serif;text-align:center;font-size:1.3rem;margin-bottom:1.5rem;color:rgba(255,255,255,.8)}
.pk-fq{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:1rem 1.2rem;margin-bottom:.6rem}
.pk-fq strong{color:#00d4ff;display:block;margin-bottom:.3rem;font-family:'Orbitron',sans-serif;font-size:.8rem}
.pk-fq p{color:rgba(255,255,255,0.55);margin:0;font-size:.9rem;line-height:1.5}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="pk-wrap">
  <div class="pk-hero">
    <h1>Crédits Multisports</h1>
    <p>Achète tes analyses à l'unité ou en pack — <strong style="color:#00d4ff">crédits à vie</strong>, sans limite de temps.</p>
    <div class="pk-solde">💎 Crédits disponibles : <b><?= $solde ?></b></div>
  </div>

  <!-- Code promo -->
  <div style="max-width:400px;margin:0 auto 1.5rem;">
    <div style="display:flex;gap:.5rem;">
      <input type="text" id="promoCode" placeholder="Code promo" maxlength="30"
        style="flex:1;background:#0a0e17;border:1px solid rgba(200,80,192,0.2);border-radius:8px;padding:.6rem .8rem;color:#fff;font-family:'Share Tech Mono',monospace;font-size:.85rem;text-transform:uppercase;letter-spacing:1px;">
      <button type="button" onclick="checkPromo()" id="promoBtn"
        style="background:rgba(200,80,192,0.15);border:1px solid rgba(200,80,192,0.3);border-radius:8px;padding:.6rem 1rem;color:#c850c0;font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;cursor:pointer;letter-spacing:1px;">APPLIQUER</button>
    </div>
    <div id="promoResult" style="margin-top:.4rem;font-size:.82rem;text-align:center;min-height:1.2rem;"></div>
  </div>

  <div class="pk-grid">
    <?php foreach($packs as $k => $p): $hot = in_array($k, ['trio','quinte']); ?>
    <div class="pk-card <?= $hot ? 'hot' : '' ?>">
      <?php if($p['badge']): ?><div class="pk-tag"><?= $p['badge'] ?></div><?php endif; ?>
      <div class="pk-num"><?= $p['nb'] ?></div>
      <div class="pk-num-lbl"><?= $p['nb'] > 1 ? 'analyses' : 'analyse' ?></div>
      <div class="pk-name"><?= htmlspecialchars($p['label']) ?></div>
      <div class="pk-sub"><?= htmlspecialchars($p['sub']) ?></div>
      <div class="pk-price"><span>€</span><?= number_format($p['prix'], 2, ',', '') ?></div>
      <div class="pk-unit"><?= number_format($p['prix_unit'], 2, ',', '') ?>€ / analyse</div>
      <?php if($p['economie'] > 0): ?><div class="pk-eco">-<?= $p['economie'] ?>%</div>
      <?php else: ?><div style="height:1.6rem"></div><?php endif; ?>
      <div class="pk-btns">
        <?php if(in_array('stripe', $p['methodes'])): ?>
        <a href="/stripe-create-pack.php?pack=<?= $k ?>" class="pk-btn pk-btn-cb" data-pack="<?= $k ?>" data-base="<?= number_format($p['prix'], 2, '.', '') ?>">💳 Carte bancaire</a>
        <?php endif; ?>
        <?php if(in_array('crypto', $p['methodes'])): ?>
        <a href="/nowpayments-create-pack.php?pack=<?= $k ?>" class="pk-btn pk-btn-cr">₿ Crypto</a>
        <?php endif; ?>
        <button type="button" onclick="payPSC('<?= $k ?>',<?= number_format($p['prix'], 2, '.', '') ?>)" class="pk-btn pk-btn-psc">🔒 Paysafecard</button>
        <?php if(in_array('sms', $p['methodes'])): ?>
        <a href="/starpass-pack-unique.php" class="pk-btn pk-btn-sm">📱 SMS (4,50€)</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="pk-faq">
    <h2>Questions fréquentes</h2>
    <div class="pk-fq"><strong>Les crédits expirent-ils ?</strong><p>Non. Tes crédits sont à vie. Utilise-les quand tu veux, sans pression.</p></div>
    <div class="pk-fq"><strong>Comment ça marche ?</strong><p>Chaque bet Multisports que tu débloques pour la première fois consomme 1 crédit. Tu peux le reconsulter gratuitement ensuite.</p></div>
    <div class="pk-fq"><strong>Quels sports ?</strong><p>⚽ Football · 🏀 NBA · 🏒 NHL · ⚾ MLB — tous les bets Safe, Fun et Live. Le Tennis a son propre abonnement.</p></div>
    <div class="pk-fq"><strong>Cumul de packs ?</strong><p>Oui, tes crédits s'additionnent. Trio (3) + Quinté (5) = 8 crédits total.</p></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
<script>
async function checkPromo(){
  const code=document.getElementById('promoCode').value.trim();
  const res=document.getElementById('promoResult');
  if(!code){res.innerHTML='<span style="color:#ff6b6b">Saisis un code promo</span>';return;}
  res.innerHTML='<span style="color:#aaa">Vérification...</span>';
  const btns=document.querySelectorAll('.pk-btn-cb[data-pack]');
  let anyOk=false;
  for(const btn of btns){
    const pack=btn.dataset.pack;
    const base=parseFloat(btn.dataset.base);
    try{
      const fd=new FormData();fd.append('code',code);fd.append('offre',pack);
      const r=await fetch('/api-check-promo.php',{method:'POST',body:fd,credentials:'same-origin'});
      const d=await r.json();
      if(d.ok){
        anyOk=true;
        btn.href='/stripe-create-pack.php?pack='+pack+'&promo='+encodeURIComponent(code);
        btn.innerHTML='💳 <s style="opacity:.5">'+base.toFixed(2)+'€</s> '+d.prix_final.toFixed(2)+'€';
        btn.style.boxShadow='0 0 15px rgba(0,212,106,0.4)';
      }else{
        btn.href='/stripe-create-pack.php?pack='+pack;
        btn.textContent='💳 Carte bancaire';
        btn.style.boxShadow='';
      }
    }catch(e){}
  }
  if(anyOk) res.innerHTML='<span style="color:#00d46a">✅ Code appliqué ! Les prix réduits sont affichés ci-dessous.</span>';
  else res.innerHTML='<span style="color:#ff6b6b">❌ Code invalide ou non applicable à ces formules</span>';
async function payPSC(pack, prix){
  const btn=event.target;btn.disabled=true;btn.textContent='⏳ Redirection...';
  try{
    const fd=new FormData();fd.append('offre',pack);
    const r=await fetch('/paysafe-create.php',{method:'POST',body:fd,credentials:'same-origin'});
    const d=await r.json();
    if(d.url){window.location.href=d.url;}
    else{alert('Erreur Paysafecard : '+(d.error||'inconnue'));btn.disabled=false;btn.textContent='🔒 Paysafecard';}
  }catch(e){alert('Erreur réseau');btn.disabled=false;btn.textContent='🔒 Paysafecard';}
}
</script>
</body>
</html>
