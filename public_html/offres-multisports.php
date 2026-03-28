<?php
// ============================================================
// STRATEDGE — Offres Tipster Multisports
// public_html/offres-multisports.php
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
$fondateurPlaces = 0; $fondateurRestant = 0; $fondateurActif = false;
try { $fondateurPlaces = (int)$db->query("SELECT COUNT(*) FROM vip_max_fondateurs")->fetchColumn(); $fondateurRestant = max(0, 10 - $fondateurPlaces); $fondateurActif = ($fondateurRestant > 0); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Multisports — StratEdge Pronos</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.content::before{content:'';position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,45,120,0.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,45,120,0.025) 1px,transparent 1px);background-size:60px 60px;pointer-events:none}
.bg-orbs{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}.orb{position:absolute;border-radius:50%;filter:blur(100px);opacity:0;animation:orbF 10s ease-in-out infinite}.o1{width:500px;height:500px;background:rgba(255,45,120,0.07);top:-100px;right:-100px}.o2{width:400px;height:400px;background:rgba(0,212,255,0.05);bottom:-80px;left:10%;animation-delay:4s}@keyframes orbF{0%{opacity:0;transform:scale(.85) translateY(30px)}35%{opacity:1}65%{opacity:1}100%{opacity:0;transform:scale(1.15) translateY(-30px)}}
.sub-hero{text-align:center;margin-bottom:3rem;position:relative;z-index:2;animation:fU .6s ease both}
.sub-hero-tag{display:inline-flex;align-items:center;gap:.5rem;font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:#ff2d78;background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);padding:.4rem 1.2rem;border-radius:30px;margin-bottom:1.5rem}
.sub-hero-title{font-family:'Orbitron',sans-serif;font-size:clamp(1.6rem,4vw,2.4rem);font-weight:900;line-height:1.15;margin-bottom:.7rem;background:linear-gradient(135deg,#fff 30%,#ff2d78 70%,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.sub-hero-desc{color:#8a9bb0;font-size:1rem;max-width:550px;margin:0 auto;line-height:1.6}
.sub-hero-sports{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin-top:1rem}
.sub-hero-sport{font-size:.75rem;font-weight:700;padding:.3rem .7rem;border-radius:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#b0bec9}
.plans-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.3rem;position:relative;z-index:2;animation:fU .7s ease .1s both}
.plan-card{background:linear-gradient(165deg,#0c1018,#111827 60%,#0d1220);border:1px solid rgba(255,255,255,0.06);border-radius:20px;overflow:hidden;position:relative;transition:transform .35s,box-shadow .35s,border-color .35s;display:flex;flex-direction:column}
.plan-card:hover{transform:translateY(-6px);border-color:var(--cc);box-shadow:0 20px 60px -15px var(--cg)}
.plan-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--cgrad);z-index:4}
.plan-card.featured{border-color:rgba(255,45,120,0.3);box-shadow:0 0 40px rgba(255,45,120,0.08)}
.plan-card.featured::before{content:'⭐ RECOMMANDÉ';position:absolute;top:0;left:0;right:0;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:2px;padding:.45rem;text-align:center;z-index:5;height:auto}
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
.active-tag{position:absolute;top:1rem;right:1rem;font-family:'Orbitron',sans-serif;font-size:.55rem;letter-spacing:1.5px;font-weight:700;background:rgba(57,255,20,0.12);border:1px solid rgba(57,255,20,0.3);color:#39ff14;padding:.25rem .7rem;border-radius:20px;text-transform:uppercase;z-index:6;animation:pG 2s ease-in-out infinite}@keyframes pG{0%,100%{box-shadow:0 0 8px rgba(57,255,20,0.15)}50%{box-shadow:0 0 20px rgba(57,255,20,0.3)}}

/* VIP MAX */
.vip-section{margin-top:1.5rem;position:relative;z-index:2;animation:fU .7s ease .3s both}
.vip-card{background:linear-gradient(160deg,#111208,#0d1220 50%,#100e05);border:1.5px solid rgba(245,200,66,0.28);border-radius:20px;padding:2.2rem 1.8rem;text-align:center;position:relative;overflow:hidden;transition:all .3s;box-shadow:0 0 8px rgba(245,200,66,0.15),0 0 25px rgba(245,200,66,0.06),inset 0 0 15px rgba(245,200,66,0.03);max-width:500px;margin:0 auto}
.vip-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#c8960c,#f5c842,#fffbe6,#f5c842,#c8960c);background-size:200% 100%;animation:vBar 2.5s linear infinite}@keyframes vBar{from{background-position:-100% 0}to{background-position:100% 0}}
.vip-card:hover{transform:translateY(-6px);box-shadow:0 30px 80px rgba(245,200,66,0.12)}
.vip-tier{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:#f5c842;margin-bottom:.5rem}
.vip-logo-vip{font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:900;letter-spacing:3px;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.vip-logo-max{font-family:'Orbitron',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:5px;color:rgba(245,200,66,0.55)}
.vip-mascot{width:120px;height:120px;margin:0 auto 1.2rem;border-radius:50%;overflow:hidden;border:2px solid rgba(245,200,66,0.35);box-shadow:0 0 30px rgba(245,200,66,0.2)}.vip-mascot video{width:100%;height:100%;object-fit:cover}
.vip-price{font-family:'Orbitron',sans-serif;font-size:2.8rem;font-weight:900;line-height:1;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent}.vip-price .cur{font-size:1.3rem;vertical-align:super}
.vip-period{color:rgba(245,200,66,0.4);font-size:.85rem;margin-bottom:1.5rem}
.vip-features{list-style:none;padding:0;margin:0 0 1.5rem;text-align:center}.vip-features li{padding:.45rem 0;color:rgba(245,200,66,0.7);font-size:.92rem;display:flex;align-items:center;justify-content:center;gap:.7rem}.vip-features li::before{content:'★';color:#ffd700;font-weight:700}
.vip-divider{width:100%;height:1px;background:rgba(245,200,66,0.12);margin:1rem 0}
.vip-btn{display:block;width:100%;padding:.8rem;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810;border:none;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:900;text-transform:uppercase;letter-spacing:1.5px;cursor:pointer;text-decoration:none;transition:all .3s;text-align:center;box-shadow:0 4px 20px rgba(245,200,66,0.25)}.vip-btn:hover{box-shadow:0 8px 30px rgba(245,200,66,0.5);transform:translateY(-2px)}
.vip-crypto{background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020)!important;color:#050810!important;box-shadow:0 4px 20px rgba(245,200,66,0.25)!important}
.plan-gw{margin-top:.6rem;padding:.45rem .65rem;border-radius:8px;border:1px solid transparent;text-align:center;background:linear-gradient(135deg,#111827,#111827) padding-box,linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff) border-box;animation:giveawayPulse 3s ease-in-out infinite}
.plan-gw-txt{font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:1px;background:linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
@keyframes giveawayPulse{0%,100%{box-shadow:0 0 8px rgba(255,45,120,0.06)}50%{box-shadow:0 0 18px rgba(255,45,120,0.12)}}
.plan-stake{margin-top:.5rem;text-align:center}
.plan-stake-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:.6rem .8rem;background:linear-gradient(135deg,#00d4ff,#0089ff);color:#fff;font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;border:1px solid rgba(0,212,255,0.35);border-radius:8px;cursor:pointer;text-decoration:none;transition:all .25s;box-shadow:0 4px 14px rgba(0,166,255,0.2)}.plan-stake-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,166,255,0.35)}
.plan-stake-note{font-size:.65rem;color:rgba(0,212,255,0.6);margin-top:.3rem}
.back-link{display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;color:#8a9bb0;text-decoration:none;margin-bottom:1.5rem;transition:color .2s}.back-link:hover{color:#ff2d78}
.guarantee{text-align:center;margin-top:2rem;padding:1.5rem;background:linear-gradient(135deg,rgba(255,45,120,0.03),rgba(0,212,255,0.03));border:1px solid rgba(255,255,255,0.05);border-radius:16px;position:relative;z-index:2}.guarantee-title{font-family:'Orbitron',sans-serif;font-size:.75rem;letter-spacing:2px;text-transform:uppercase;color:#b0bec9;margin-bottom:.3rem}.guarantee-text{color:#8a9bb0;font-size:.85rem;max-width:500px;margin:0 auto}
@keyframes fU{from{opacity:0;transform:translateY(25px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1100px){.plans-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.plans-grid{grid-template-columns:1fr}}
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
  <div class="sub-hero-tag">🏆 Tipster Multisports</div>
  <h1 class="sub-hero-title">Choisis ta formule</h1>
  <p class="sub-hero-desc">Bets <strong>Safe</strong>, <strong>Fun</strong> et <strong>LIVE</strong> sur tous les sports. Analyses data, xG, Poisson — sans engagement.</p>
  <div class="sub-hero-sports">
    <span class="sub-hero-sport">⚽ Foot</span>
    <span class="sub-hero-sport">🏀 NBA</span>
    <span class="sub-hero-sport">🏒 NHL</span>
    <span class="sub-hero-sport">⚾ MLB</span>
    <span class="sub-hero-sport">🎾 Tennis</span>
  </div>
</div>

<div class="plans-grid">
<?php
$cards = [
  ['type'=>'daily','tier'=>'Entrée','name'=>'Daily','video'=>'/assets/images/DOIGT.mp4','price'=>'4,50','period'=>'/ prochain bet','color'=>'#ff2d78','dim'=>'#d6245f','glow'=>'rgba(255,45,120,0.20)','grad'=>'linear-gradient(135deg,#ff2d78,#d6245f)','featured'=>false,'features'=>['Accès au prochain bet Safe','Accès au prochain bet Live','Idéal pour tester','Paiement par SMS'],'link'=>'offre-daily.php','btn'=>'📱 Payer — 4,50€','info'=>'SMS · CB · Paysafecard'],
  ['type'=>'weekend','tier'=>'Populaire','name'=>'Week-End','video'=>'/assets/images/air.mp4','price'=>'10','period'=>'/ week-end (ven→dim)','color'=>'#00d4ff','dim'=>'#0099cc','glow'=>'rgba(0,212,255,0.20)','grad'=>'linear-gradient(135deg,#00d4ff,#0099cc)','featured'=>true,'features'=>['Bets Safe &amp; Fun inclus','Bets LIVE par mail &amp; Push','Tous les matchs du week-end','Sans engagement'],'link'=>'offre-weekend.php','btn'=>'💳 Payer — 10€','info'=>'CB · Paysafecard · Crypto'],
  ['type'=>'weekly','tier'=>'Best value','name'=>'Weekly','video'=>'/assets/images/SAM.mp4','price'=>'20','period'=>'/ semaine (7 jours)','color'=>'#a855f7','dim'=>'#7c3aed','glow'=>'rgba(168,85,247,0.20)','grad'=>'linear-gradient(135deg,#a855f7,#7c3aed)','featured'=>false,'features'=>['TOUS les bets Safe & Fun','Bets LIVE par mail & Push','1 montante par mois','Foot, NBA, Hockey…'],'link'=>'offre-weekly.php','btn'=>'💳 Payer — 20€','info'=>'CB · Paysafecard · Crypto'],
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
    <div class="plan-gw">
      <span>🎁</span> <span class="plan-gw-txt">Éligible au GiveAway mensuel</span>
    </div>
    <div class="plan-stake">
      <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="plan-stake-btn">🎰 S’inscrire sur Stake</a>
      <div class="plan-stake-note">1 mois VIP MAX offert</div>
    </div>
  </div>
</div>
<?php endforeach;?>

<!-- VIP MAX in grid -->
<div class="plan-card fade-up" style="--cc:#f5c842;--cc-dim:#e8a020;--cg:rgba(245,200,66,0.20);--cgrad:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);border-color:rgba(245,200,66,0.2);box-shadow:0 0 30px rgba(245,200,66,0.06)">
  <?php if(in_array('vip_max',$typesActifs)):?><div class="active-tag">✓ ACTIF</div><?php endif;?>
  <div class="plan-inner">
    <div class="plan-mascot" style="border-color:rgba(245,200,66,0.35);box-shadow:0 0 25px rgba(245,200,66,0.2)"><video autoplay loop muted playsinline><source src="/assets/images/vip_max.mp4" type="video/mp4"></video></div>
    <div class="plan-tier" style="color:#f5c842">👑 Accès Total</div>
    <div class="plan-name" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</div>
    <div class="plan-price-row"><span class="plan-price" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent;"><span class="cur">€</span>50</span></div>
    <div class="plan-period">/ mois (30 jours)</div>
    <ul class="plan-features" style="--cc:#f5c842"><li>Tous les tipsters réunis</li><li>Multisports + Tennis + Fun</li><li>Bets LIVE &amp; montantes inclus</li><li>30 jours illimités</li></ul>
    <div class="plan-divider" style="background:rgba(245,200,66,0.12)"></div>
    <div class="plan-pay" style="border-color:rgba(245,200,66,0.15);background:rgba(245,200,66,0.03)">
      <div class="plan-pay-label" style="color:#f5c842">💳 Payer maintenant</div>
      <?php if(in_array('vip_max',$typesActifs)):?><div class="plan-btn disabled">✓ Abonnement actif</div>
      <?php else:?><a href="offre.php?type=vip_max" class="plan-btn" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810;">💳 Payer — 50€</a><div class="sep-or" style="color:rgba(245,200,66,0.3)">— ou —</div><a href="offre.php?type=vip_max#crypto" class="btn-crypto" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020)!important;color:#050810!important;">₿ Payer en Crypto</a><?php endif;?>
      <div class="plan-pay-info" style="color:rgba(245,200,66,0.3)">CB · Paysafecard · Crypto</div>
    </div>
    <div class="plan-gw" style="background:linear-gradient(160deg,#111208,#0d1220,#100e05) padding-box,linear-gradient(135deg,#f5c842,#e8a020,#c8960c) border-box;">
      <span>🎁</span> <span class="plan-gw-txt" style="background:linear-gradient(135deg,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Éligible au GiveAway mensuel</span>
    </div>
    <div class="plan-stake">
      <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="plan-stake-btn">🎰 S'inscrire sur Stake</a>
      <div class="plan-stake-note">1 mois VIP MAX offert</div>
    </div>
  </div>
</div>
</div>

<div class="guarantee"><div class="guarantee-title">🛡️ Sans engagement</div><p class="guarantee-text">Toutes nos formules sont sans abonnement récurrent. Tu payes une fois, tu profites.</p></div>

</div>
<?php if(file_exists(__DIR__.'/includes/footer-legal.php')): ?><?php require_once __DIR__.'/includes/footer-legal.php'; ?><?php endif; ?>
</main></div>
</body></html>
