<?php
// ============================================================
// STRATEDGE — Offres Tipster Fun Only
// public_html/offres-fun.php
// ============================================================
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$membre = getMembre();
$currentPage = 'souscrire';
$avatarUrl = getAvatarUrl($membre);
$db = getDB();
$abosActifs = $db->prepare("SELECT type FROM abonnements WHERE membre_id = ? AND actif = 1 AND (type = 'daily' OR date_fin > NOW())");
$abosActifs->execute([$membre['id']]);
$typesActifs = array_column($abosActifs->fetchAll(PDO::FETCH_ASSOC), 'type');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fun Only — StratEdge Pronos</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.content::before{content:'';position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(168,85,247,0.025) 1px,transparent 1px),linear-gradient(90deg,rgba(168,85,247,0.025) 1px,transparent 1px);background-size:60px 60px;pointer-events:none}
.bg-orbs{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}.orb{position:absolute;border-radius:50%;filter:blur(100px);opacity:0;animation:orbF 10s ease-in-out infinite}.o1{width:500px;height:500px;background:rgba(168,85,247,0.07);top:-100px;right:-100px}.o2{width:400px;height:400px;background:rgba(255,107,43,0.05);bottom:-80px;left:10%;animation-delay:4s}@keyframes orbF{0%{opacity:0;transform:scale(.85) translateY(30px)}35%{opacity:1}65%{opacity:1}100%{opacity:0;transform:scale(1.15) translateY(-30px)}}
.sub-hero{text-align:center;margin-bottom:2rem;position:relative;z-index:2;animation:fU .6s ease both}
.sub-hero-tag{display:inline-flex;align-items:center;gap:.5rem;font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:#a855f7;background:rgba(168,85,247,0.08);border:1px solid rgba(168,85,247,0.25);padding:.4rem 1.2rem;border-radius:30px;margin-bottom:1.5rem}
.sub-hero-title{font-family:'Orbitron',sans-serif;font-size:clamp(1.6rem,4vw,2.4rem);font-weight:900;line-height:1.15;margin-bottom:.7rem;background:linear-gradient(135deg,#fff 30%,#a855f7 60%,#ff6b2b);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.sub-hero-desc{color:#8a9bb0;font-size:1rem;max-width:550px;margin:0 auto;line-height:1.6}
.sub-hero-sports{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin-top:1rem}
.sub-hero-sport{font-size:.75rem;font-weight:700;padding:.3rem .7rem;border-radius:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#b0bec9}

/* Commu free banner */
.commu-banner{max-width:600px;margin:0 auto 2rem;padding:1.2rem 1.5rem;background:linear-gradient(135deg,rgba(0,200,100,0.06),rgba(57,255,20,0.04));border:1px solid rgba(0,200,100,0.2);border-radius:16px;text-align:center;position:relative;z-index:2;animation:fU .7s ease .05s both}
.commu-title{font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:900;letter-spacing:2px;color:#00c864;margin-bottom:.3rem}
.commu-desc{font-size:.9rem;color:#b0bec9;line-height:1.5}
.commu-desc strong{color:#00c864}
.commu-cta{display:inline-block;margin-top:.7rem;padding:.5rem 1.5rem;background:rgba(0,200,100,0.12);border:1px solid rgba(0,200,100,0.3);border-radius:8px;color:#00c864;font-family:'Orbitron',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:1px;text-decoration:none;transition:all .2s}
.commu-cta:hover{background:rgba(0,200,100,0.2);border-color:#00c864}

.plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.3rem;position:relative;z-index:2;animation:fU .7s ease .1s both}
.plan-card{background:linear-gradient(165deg,#0c1018,#111827 60%,#0d1220);border:1px solid rgba(255,255,255,0.06);border-radius:20px;overflow:hidden;position:relative;transition:transform .35s,box-shadow .35s,border-color .35s;display:flex;flex-direction:column}
.plan-card:hover{transform:translateY(-6px);border-color:var(--cc);box-shadow:0 20px 60px -15px var(--cg)}
.plan-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--cgrad);z-index:4}
.plan-card.featured{border-color:rgba(168,85,247,0.3);box-shadow:0 0 40px rgba(168,85,247,0.08)}
.plan-card.featured::before{content:'⭐ RECOMMANDÉ';position:absolute;top:0;left:0;right:0;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:2px;padding:.45rem;text-align:center;z-index:5;height:auto}
.plan-inner{padding:2rem 1.8rem 1.6rem;display:flex;flex-direction:column;flex:1}.plan-card.featured .plan-inner{padding-top:2.8rem}
.plan-mascot{width:110px;height:110px;margin:0 auto 1.2rem;border-radius:50%;overflow:hidden;background:rgba(255,255,255,0.03);border:2px solid color-mix(in srgb,var(--cc) 40%,transparent);box-shadow:0 0 25px var(--cg)}.plan-mascot video{width:100%;height:100%;object-fit:cover;border-radius:50%}
.plan-tier{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:var(--cc);margin-bottom:.4rem;text-align:center}
.plan-name{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:700;text-align:center;margin-bottom:1.2rem}
.plan-price-row{display:flex;align-items:baseline;justify-content:center;gap:.2rem}.plan-price{font-family:'Orbitron',sans-serif;font-size:2.8rem;font-weight:900;color:var(--cc);line-height:1}.plan-price .cur{font-size:1.3rem;vertical-align:super}
.plan-period{font-size:.85rem;color:#8a9bb0;margin-bottom:1.5rem;text-align:center}
.plan-features{list-style:none;padding:0;margin:0 0 1.5rem;flex:1}.plan-features li{padding:.45rem 0;color:#b0bec9;font-size:.92rem;display:flex;align-items:center;gap:.7rem}.plan-features li::before{content:'✓';color:var(--cc);font-weight:700;flex-shrink:0}
.plan-divider{width:100%;height:1px;background:rgba(255,255,255,0.06);margin:auto 0 1rem}
.plan-pay{background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.08);border-radius:12px;padding:1.2rem;text-align:center}
.plan-pay-label{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--cc);letter-spacing:2px;text-transform:uppercase;margin-bottom:.5rem}
.plan-btn{display:block;width:100%;padding:.8rem;background:linear-gradient(135deg,var(--cc),var(--cc-dim,var(--cc)));color:#fff;border:none;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;cursor:pointer;text-decoration:none;transition:all .3s;text-align:center;box-shadow:0 4px 15px var(--cg)}.plan-btn:hover{box-shadow:0 6px 25px var(--cg);transform:translateY(-2px)}
.sep-or{text-align:center;color:#8a9bb0;font-size:.7rem;margin:.6rem 0 .3rem;text-transform:uppercase;letter-spacing:2px}
.btn-crypto{display:block;width:100%;padding:.7rem;background:linear-gradient(135deg,#f7931a,#e2820a);color:#fff;border:none;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;text-decoration:none;text-align:center;cursor:pointer;transition:all .3s;box-shadow:0 4px 15px rgba(247,147,26,0.3)}.btn-crypto:hover{box-shadow:0 6px 25px rgba(247,147,26,0.5);transform:translateY(-2px)}
.plan-pay-info{font-size:.7rem;color:#8a9bb0;margin-top:.4rem}
.plan-btn.disabled{background:rgba(255,255,255,0.06);box-shadow:none;color:#8a9bb0;cursor:default;pointer-events:none}
.active-tag{position:absolute;top:1rem;right:1rem;font-family:'Orbitron',sans-serif;font-size:.55rem;letter-spacing:1.5px;font-weight:700;background:rgba(57,255,20,0.12);border:1px solid rgba(57,255,20,0.3);color:#39ff14;padding:.25rem .7rem;border-radius:20px;text-transform:uppercase;z-index:6}
.back-link{display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;color:#8a9bb0;text-decoration:none;margin-bottom:1.5rem;transition:color .2s}.back-link:hover{color:#a855f7}
.guarantee{text-align:center;margin-top:2rem;padding:1.5rem;background:linear-gradient(135deg,rgba(168,85,247,0.03),rgba(255,107,43,0.03));border:1px solid rgba(255,255,255,0.05);border-radius:16px;position:relative;z-index:2}.guarantee-title{font-family:'Orbitron',sans-serif;font-size:.75rem;letter-spacing:2px;text-transform:uppercase;color:#b0bec9;margin-bottom:.3rem}.guarantee-text{color:#8a9bb0;font-size:.85rem;max-width:500px;margin:0 auto}
@keyframes fU{from{opacity:0;transform:translateY(25px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:900px){.plans-grid{grid-template-columns:1fr}}
.wrap{max-width:1100px;margin:0 auto;width:100%}
</style>
</head>
<body>
<div class="bg-orbs"><div class="orb o1"></div><div class="orb o2"></div></div>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-body">
<div class="wrap">

<a href="/#pricing" class="back-link">← Retour aux tipsters</a>

<div class="sub-hero">
  <div class="sub-hero-tag">🎲 Tipster Fun Only</div>
  <h1 class="sub-hero-title">Grosses cotes, gros gains</h1>
  <p class="sub-hero-desc">Combinés à forte value le <strong>week-end</strong> et les soirs de <strong>Ligue des Champions</strong>. Le tipster pour ceux qui aiment le risque calculé.</p>
  <div class="sub-hero-sports">
    <span class="sub-hero-sport">⚽ Foot WE</span>
    <span class="sub-hero-sport">🏆 Champions League</span>
    <span class="sub-hero-sport">🎲 Grosses cotes</span>
  </div>
</div>

<!-- Paris Commu gratuits -->
<div class="commu-banner">
  <div class="commu-title">🆓 3 paris gratuits par semaine</div>
  <p class="commu-desc">Le <strong>« Pari de la commu »</strong> — 3 pronostics Fun accessibles <strong>gratuitement</strong> chaque semaine sur simple inscription. Pas besoin d'abonnement.</p>
  <?php if (!isLoggedIn()): ?>
  <a href="register.php" class="commu-cta">📝 Créer mon compte gratuit</a>
  <?php else: ?>
  <a href="dashboard.php" class="commu-cta">📊 Voir mes paris commu</a>
  <?php endif; ?>
</div>

<div class="plans-grid">
<?php
$cards = [
  ['type'=>'daily','tier'=>'Entrée','name'=>'Fun Daily','video'=>'/assets/images/mascotte-fun.mp4','price'=>'4,50','period'=>'/ prochain bet fun','color'=>'#a855f7','dim'=>'#7c3aed','glow'=>'rgba(168,85,247,0.20)','grad'=>'linear-gradient(135deg,#a855f7,#ff6b2b)','featured'=>false,'features'=>['1 combiné grosse cote','Accès au prochain bet Fun','Idéal pour tester','Paiement par SMS'],'link'=>'offre-daily.php','btn'=>'📱 Payer — 4,50€','info'=>'SMS · CB · Paysafecard'],
  ['type'=>'weekend','tier'=>'Populaire','name'=>'Fun Week-End','video'=>'/assets/images/mascotte-fun.mp4','price'=>'10','period'=>'/ week-end (ven→dim)','color'=>'#ff6b2b','dim'=>'#e85a1a','glow'=>'rgba(255,107,43,0.20)','grad'=>'linear-gradient(135deg,#ff6b2b,#e85a1a)','featured'=>true,'features'=>['Tous les combinés du WE','Soirées Champions League','Bets Fun à forte value','Sans engagement'],'link'=>'offre-weekend.php','btn'=>'💳 Payer — 10€','info'=>'CB · Paysafecard · Crypto'],
  ['type'=>'weekly','tier'=>'Best value','name'=>'Fun Weekly','video'=>'/assets/images/mascotte-fun.mp4','price'=>'20','period'=>'/ semaine (7 jours)','color'=>'#a855f7','dim'=>'#7c3aed','glow'=>'rgba(168,85,247,0.20)','grad'=>'linear-gradient(135deg,#a855f7,#7c3aed)','featured'=>false,'features'=>['TOUS les bets Fun 7 jours','Combinés WE + C1 + semaine','Notifications Push & Email','Sans engagement'],'link'=>'offre-weekly.php','btn'=>'💳 Payer — 20€','info'=>'CB · Paysafecard · Crypto'],
];
foreach($cards as $c):
  $isActive = in_array($c['type'], $typesActifs);
?>
<div class="plan-card <?=$c['featured']?'featured':''?> fade-up" style="--cc:<?=$c['color']?>;--cc-dim:<?=$c['dim']?>;--cg:<?=$c['glow']?>;--cgrad:<?=$c['grad']?>">
  <?php if($isActive):?><div class="active-tag">✓ ACTIF</div><?php endif;?>
  <div class="plan-inner">
    <div class="plan-mascot"><video autoplay loop muted playsinline><source src="<?=$c['video']?>" type="video/mp4"></video></div>
    <div class="plan-tier"><?=$c['tier']?></div>
    <div class="plan-name"><?=$c['name']?></div>
    <div class="plan-price-row"><span class="plan-price"><span class="cur">€</span><?=$c['price']?></span></div>
    <div class="plan-period"><?=$c['period']?></div>
    <ul class="plan-features"><?php foreach($c['features'] as $f):?><li><?=$f?></li><?php endforeach;?></ul>
    <div class="plan-divider"></div>
    <div class="plan-pay">
      <div class="plan-pay-label">💳 Payer maintenant</div>
      <?php if($isActive):?><div class="plan-btn disabled">✓ Abonnement actif</div>
      <?php else:?><a href="<?=$c['link']?>" class="plan-btn"><?=$c['btn']?></a><div class="sep-or">— ou —</div><a href="<?=$c['link']?>#crypto" class="btn-crypto">₿ Payer en Crypto</a><?php endif;?>
      <div class="plan-pay-info"><?=$c['info']?></div>
    </div>
  </div>
</div>
<?php endforeach;?>
</div>

<div class="guarantee"><div class="guarantee-title">🛡️ Sans engagement</div><p class="guarantee-text">Tu payes une fois, tu profites. Pas d'abonnement récurrent. Et n'oublie pas tes 3 paris commu gratuits chaque semaine !</p></div>

</div>
<?php if(file_exists(__DIR__.'/includes/footer-legal.php')): ?><?php require_once __DIR__.'/includes/footer-legal.php'; ?><?php endif; ?>
</main></div>
</body></html>
