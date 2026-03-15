<?php
// ============================================================
// STRATEDGE — Page Souscrire (Espace Membre) — V2
// 5 offres : Daily · Week-End · Weekly · VIP MAX · Tennis
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
<title>Souscrire — StratEdge Pronos</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.content::before{content:'';position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,45,120,0.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,45,120,0.025) 1px,transparent 1px);background-size:60px 60px;pointer-events:none}
.bg-orbs{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}.orb{position:absolute;border-radius:50%;filter:blur(100px);opacity:0;animation:orbF 10s ease-in-out infinite}.o1{width:500px;height:500px;background:rgba(255,45,120,0.07);top:-100px;right:-100px}.o2{width:400px;height:400px;background:rgba(0,212,255,0.05);bottom:-80px;left:10%;animation-delay:4s}.o3{width:350px;height:350px;background:rgba(168,85,247,0.05);top:40%;left:40%;animation-delay:7s}@keyframes orbF{0%{opacity:0;transform:scale(.85) translateY(30px)}35%{opacity:1}65%{opacity:1}100%{opacity:0;transform:scale(1.15) translateY(-30px)}}
.sub-hero{text-align:center;margin-bottom:3rem;position:relative;z-index:2;animation:fU .6s ease both}.sub-hero-tag{display:inline-flex;align-items:center;gap:.5rem;font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:#ff2d78;background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);padding:.4rem 1.2rem;border-radius:30px;margin-bottom:1.5rem}.sub-hero-title{font-family:'Orbitron',sans-serif;font-size:clamp(1.6rem,4vw,2.6rem);font-weight:900;line-height:1.15;margin-bottom:.7rem;background:linear-gradient(135deg,#fff 30%,#ff2d78 70%,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}.sub-hero-desc{color:var(--txt3);font-size:1.05rem;max-width:550px;margin:0 auto;line-height:1.6}.sub-hero-desc strong{color:var(--txt2)}
.plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.3rem;position:relative;z-index:2;animation:fU .7s ease .1s both}
.plan-card{background:linear-gradient(165deg,#0c1018,#111827 60%,#0d1220);border:1px solid rgba(255,255,255,0.06);border-radius:20px;overflow:hidden;position:relative;transition:transform .35s,box-shadow .35s,border-color .35s}.plan-card:hover{transform:translateY(-6px);border-color:var(--cc);box-shadow:0 20px 60px -15px var(--cg)}.plan-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--cgrad);z-index:4}.plan-card::after{content:'';position:absolute;top:-80px;right:-80px;width:200px;height:200px;background:radial-gradient(circle,var(--cg) 0%,transparent 70%);border-radius:50%;opacity:0;transition:opacity .4s;pointer-events:none}.plan-card:hover::after{opacity:1}
.plan-card.featured{border-color:rgba(255,45,120,0.3);box-shadow:0 0 40px rgba(255,45,120,0.08)}.plan-card.featured::before{content:'⭐ RECOMMANDÉ';position:absolute;top:0;left:0;right:0;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:2px;padding:.45rem;text-align:center;z-index:5;height:auto}
.plan-inner{padding:2rem 1.8rem 1.6rem;display:flex;flex-direction:column}.plan-card.featured .plan-inner{padding-top:2.8rem}
.plan-mascot{width:120px;height:120px;margin:0 auto 1.2rem;border-radius:50%;overflow:hidden;background:rgba(255,255,255,0.03);border:2px solid color-mix(in srgb,var(--cc) 40%,transparent);box-shadow:0 0 25px var(--cg)}.plan-mascot video{width:100%;height:100%;object-fit:cover;border-radius:50%}
.plan-tier{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:var(--cc);margin-bottom:.4rem;text-align:center}.plan-name{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:700;text-align:center;margin-bottom:1.2rem}
.plan-price-row{display:flex;align-items:baseline;justify-content:center;gap:.2rem}.plan-price{font-family:'Orbitron',sans-serif;font-size:2.8rem;font-weight:900;color:var(--cc);line-height:1}.plan-price .cur{font-size:1.3rem;vertical-align:super}.plan-period{font-size:.85rem;color:var(--txt3);margin-bottom:1.5rem;text-align:center}
.plan-features{list-style:none;padding:0;margin:0 0 1.5rem}.plan-features li{padding:.45rem 0;color:var(--txt2);font-size:.92rem;display:flex;align-items:center;gap:.7rem}.plan-features li::before{content:'✓';color:var(--cc);font-weight:700;flex-shrink:0}.fun-supplement-pulse{display:inline-block;margin-top:0.2rem;padding:0.35rem 0.65rem;border-radius:8px;background:rgba(255,45,120,0.12);color:var(--cc);font-size:0.9em;font-weight:700;border:1px solid rgba(255,45,120,0.3);animation:funSupplementPulse 2.5s ease-in-out infinite}@keyframes funSupplementPulse{0%,100%{box-shadow:0 0 0 0 rgba(255,45,120,0.35);opacity:1}50%{box-shadow:0 0 14px 2px rgba(255,45,120,0.25);opacity:0.92}}
.plan-divider{width:100%;height:1px;background:rgba(255,255,255,0.06);margin:auto 0 1rem}
.plan-pay{background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.08);border-radius:12px;padding:1.2rem;text-align:center}.plan-pay-label{font-family:'Space Mono',monospace;font-size:.65rem;color:var(--cc);letter-spacing:2px;text-transform:uppercase;margin-bottom:.5rem}
.plan-btn{display:block;width:100%;padding:.8rem;background:linear-gradient(135deg,var(--cc),var(--cc-dim,var(--cc)));color:#fff;border:none;border-radius:8px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;cursor:pointer;text-decoration:none;transition:all .3s;text-align:center;box-shadow:0 4px 15px var(--cg)}.plan-btn:hover{box-shadow:0 6px 25px var(--cg);transform:translateY(-2px)}
.sep-or{text-align:center;color:var(--txt3);font-size:.7rem;margin:.6rem 0 .3rem;text-transform:uppercase;letter-spacing:2px}
.btn-crypto{display:block;width:100%;padding:.7rem;background:linear-gradient(135deg,#f7931a,#e2820a);color:#fff;border:none;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;text-decoration:none;text-align:center;cursor:pointer;transition:all .3s;box-shadow:0 4px 15px rgba(247,147,26,0.3)}.btn-crypto:hover{box-shadow:0 6px 25px rgba(247,147,26,0.5);transform:translateY(-2px)}
.plan-pay-info{font-size:.7rem;color:var(--txt3);margin-top:.4rem}
.plan-btn.disabled{background:rgba(255,255,255,0.06);box-shadow:none;color:var(--txt3);cursor:default;pointer-events:none}
.stake-card-btn{display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;margin-top:0.75rem;padding:0.6rem 1rem;width:100%;background:linear-gradient(135deg,#00d4ff,#0099cc);color:#fff;border-radius:8px;font-size:0.9rem;font-weight:700;text-decoration:none;transition:all .3s;border:none;}
.stake-card-btn:hover{box-shadow:0 0 20px rgba(0,212,255,0.4);transform:translateY(-2px);color:#fff;}
.stake-card-btn .stake-btn-icon{height:1.15em;width:auto;vertical-align:middle;}
.discount-badge{position:absolute;top:.8rem;right:.8rem;background:#ff6b2b;color:#fff;font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;padding:.25rem .55rem;border-radius:6px;letter-spacing:1px;z-index:5}.plan-card.featured .discount-badge{top:2.5rem}
.active-tag{position:absolute;top:1rem;right:1rem;font-family:'Orbitron',sans-serif;font-size:.55rem;letter-spacing:1.5px;font-weight:700;background:rgba(57,255,20,0.12);border:1px solid rgba(57,255,20,0.3);color:#39ff14;padding:.25rem .7rem;border-radius:20px;text-transform:uppercase;z-index:6;animation:pG 2s ease-in-out infinite}@keyframes pG{0%,100%{box-shadow:0 0 8px rgba(57,255,20,0.15)}50%{box-shadow:0 0 20px rgba(57,255,20,0.3)}}

/* VIP MAX */
.vip-section{margin-top:1.5rem;position:relative;z-index:2;animation:fU .7s ease .3s both}.vip-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.3rem;align-items:stretch}
.vip-card{background:linear-gradient(160deg,#111208,#0d1220 50%,#100e05);border:1.5px solid rgba(245,200,66,0.28);border-radius:20px;padding:2.2rem 1.8rem;text-align:center;position:relative;overflow:hidden;transition:all .3s;box-shadow:0 0 8px rgba(245,200,66,0.15),0 0 25px rgba(245,200,66,0.06),inset 0 0 15px rgba(245,200,66,0.03);display:flex;flex-direction:column}
.vip-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#c8960c,#f5c842,#fffbe6,#f5c842,#c8960c);background-size:200% 100%;animation:vBar 2.5s linear infinite}@keyframes vBar{from{background-position:-100% 0}to{background-position:100% 0}}
.vip-card::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(245,200,66,0.05),transparent 60%);pointer-events:none}.vip-card:hover{transform:translateY(-6px);box-shadow:0 30px 80px rgba(245,200,66,0.12)}
.vip-tier{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:#f5c842;margin-bottom:.5rem}
.vip-logo-wrap{display:flex;align-items:center;justify-content:center;gap:.6rem;margin-bottom:.4rem}.vip-logo-vip{font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:900;letter-spacing:3px;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent}.vip-logo-max{font-family:'Orbitron',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:5px;color:rgba(245,200,66,0.55)}
.vip-sub-label{font-size:.78rem;color:rgba(245,200,66,0.4);margin-bottom:1rem}
.vip-mascot{width:130px;height:130px;margin:0 auto 1.2rem;border-radius:50%;overflow:hidden;border:2px solid rgba(245,200,66,0.35);box-shadow:0 0 30px rgba(245,200,66,0.2)}.vip-mascot video{width:100%;height:100%;object-fit:cover}
.vip-price{font-family:'Orbitron',sans-serif;font-size:2.8rem;font-weight:900;line-height:1;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent}.vip-price .cur{font-size:1.3rem;vertical-align:super}
.vip-period{color:rgba(245,200,66,0.4);font-size:.85rem;margin-bottom:1.5rem}
.vip-features{list-style:none;padding:0;margin:0 0 1.5rem;text-align:center}.vip-features li{padding:.45rem 0;color:rgba(245,200,66,0.7);font-size:.92rem;display:flex;align-items:center;justify-content:center;gap:.7rem}.vip-features li::before{content:'★';color:#ffd700;font-weight:700;flex-shrink:0;font-size:.8rem}
.fondateur-strip{background:linear-gradient(135deg,rgba(200,150,12,0.12),rgba(245,200,66,0.06));border:1px solid rgba(245,200,66,0.3);border-radius:10px;padding:.6rem .9rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem}.fondateur-strip-left{font-family:'Orbitron',sans-serif;font-size:.55rem;font-weight:900;letter-spacing:1.5px;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6);-webkit-background-clip:text;-webkit-text-fill-color:transparent}.fondateur-strip-right{font-family:'Space Mono',monospace;font-size:.6rem;color:rgba(245,200,66,0.5);white-space:nowrap}.fondateur-jauge{height:4px;background:rgba(245,200,66,0.1);border-radius:2px;margin-top:.3rem;overflow:hidden}.fondateur-fill{height:100%;background:linear-gradient(90deg,#c8960c,#f5c842);border-radius:2px}.fondateur-complet{background:rgba(245,200,66,0.04);border:1px solid rgba(245,200,66,0.1);border-radius:10px;padding:.45rem .8rem;margin-bottom:1rem;font-family:'Space Mono',monospace;font-size:.55rem;color:rgba(245,200,66,0.3);text-align:center;letter-spacing:1px}
.vip-divider{width:100%;height:1px;background:rgba(245,200,66,0.12);margin:auto 0 1rem}
.vip-pay{background:rgba(245,200,66,0.04);border:1px dashed rgba(245,200,66,0.2);border-radius:12px;padding:1.2rem;text-align:center}.vip-pay-label{font-family:'Space Mono',monospace;font-size:.65rem;color:#f5c842;letter-spacing:2px;text-transform:uppercase;margin-bottom:.5rem}
.vip-btn{display:block;width:100%;padding:.8rem;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810;border:none;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:900;text-transform:uppercase;letter-spacing:1.5px;cursor:pointer;text-decoration:none;transition:all .3s;text-align:center;box-shadow:0 4px 20px rgba(245,200,66,0.25);position:relative;overflow:hidden}.vip-btn::before{content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.5),transparent);animation:vSw 3s ease-in-out infinite}@keyframes vSw{0%{left:-100%}60%,100%{left:150%}}.vip-btn:hover{box-shadow:0 8px 30px rgba(245,200,66,0.5);transform:translateY(-2px)}
.vip-crypto{background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020)!important;color:#050810!important;box-shadow:0 4px 20px rgba(245,200,66,0.25)!important}

/* TENNIS */

.guarantee{text-align:center;margin-top:2.5rem;padding:1.8rem;background:linear-gradient(135deg,rgba(255,45,120,0.03),rgba(0,212,255,0.03));border:1px solid rgba(255,255,255,0.05);border-radius:16px;position:relative;z-index:2;animation:fU .8s ease .5s both}.guarantee-icon{font-size:1.8rem;margin-bottom:.5rem}.guarantee-title{font-family:'Orbitron',sans-serif;font-size:.8rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--txt2);margin-bottom:.4rem}.guarantee-text{color:var(--txt3);font-size:.88rem;max-width:600px;margin:0 auto;line-height:1.6}.guarantee-text strong{color:var(--txt2)}
.faq-section{margin-top:2rem;position:relative;z-index:2;animation:fU .8s ease .6s both}.faq-title{font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);margin-bottom:1rem;text-align:center}.faq-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem}.faq-item{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:1.1rem 1.3rem}.faq-q{font-weight:700;font-size:.92rem;color:var(--txt);margin-bottom:.4rem}.faq-a{font-size:.82rem;color:var(--txt3);line-height:1.5}
@keyframes fU{from{opacity:0;transform:translateY(25px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:900px){.plans-grid{grid-template-columns:1fr}.vip-grid{grid-template-columns:1fr}.faq-grid{grid-template-columns:1fr}}
.souscrire-wrap{max-width:1100px;margin:0 auto;width:100%;}
</style>
</head>
<body>
<div class="bg-orbs"><div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div></div>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="content-body">
<div class="souscrire-wrap">
<div class="sub-hero"><div class="sub-hero-tag">⚡ Formules & Tarifs</div><h1 class="sub-hero-title">Choisis ton avantage</h1><p class="sub-hero-desc">Accède aux analyses de nos experts, bets <strong>Safe</strong>, <strong>Live</strong> et <strong>Fun</strong>. Sans engagement, tu es libre à tout moment.</p></div>

<!-- 3 Cards -->
<div class="plans-grid">
<?php
$cards=[
['type'=>'daily','tier'=>'Plan Cool','name'=>'Daily','video'=>'/assets/images/DOIGT.mp4','price'=>'4,50','period'=>'/ prochain bet','color'=>'#ff2d78','dim'=>'#d6245f','glow'=>'rgba(255,45,120,0.20)','grad'=>'linear-gradient(135deg,#ff2d78,#d6245f)','featured'=>false,'discount'=>null,'features'=>['Accès au prochain bet "Safe"','Accès au prochain bet "Live"','Idéal pour maîtriser son budget','Idéal pour débuter'],'link'=>'offre-daily.php','btn'=>'📱 Payer par SMS — 4,50€','info'=>'SMS · Appel · CB · Paysafecard via StarPass'],
['type'=>'weekend','tier'=>'Recommandé','name'=>'Week-End','video'=>'/assets/images/air.mp4','price'=>'10','period'=>'/ souscription (ven → dim)','color'=>'#ff2d78','dim'=>'#d6245f','glow'=>'rgba(255,45,120,0.20)','grad'=>'linear-gradient(135deg,#ff2d78,#d6245f)','featured'=>true,'discount'=>'-10% JEU.','features'=>['Accès bets "Safe" &amp; "Fun"','<span class="fun-supplement-pulse">Fun bets avec suppléments</span>','Du vendredi au dimanche','Bets LIVE par mail &amp; Push','Idéal pour les matchs du week-end'],'link'=>'offre-weekend.php','btn'=>'💳 Payer — 10€','info'=>'CB · PayPal · Paysafecard · Internet+ via StarPass'],
['type'=>'weekly','tier'=>'Pro','name'=>'Weekly','video'=>'/assets/images/SAM.mp4','price'=>'20','period'=>'/ semaine (7 jours glissants)','color'=>'#a855f7','dim'=>'#7c3aed','glow'=>'rgba(168,85,247,0.20)','grad'=>'linear-gradient(135deg,#a855f7,#7c3aed)','featured'=>false,'discount'=>null,'features'=>['Accès bets "Safe" &amp; "Fun"','Abonnement 1 semaine complète','Bets LIVE par mail &amp; Push','Tous sports : Foot, NBA, Hockey…'],'link'=>'offre-weekly.php','btn'=>'💳 Payer — 20€','info'=>'CB · PayPal · Paysafecard · Internet+ via StarPass'],
];
foreach($cards as $i=>$c):$act=in_array($c['type'],$typesActifs);?>
<div class="plan-card<?=$c['featured']?' featured':''?>" style="--cc:<?=$c['color']?>;--cc-dim:<?=$c['dim']?>;--cg:<?=$c['glow']?>;--cgrad:<?=$c['grad']?>;animation:fU .6s ease <?=0.1+$i*0.1?>s both">
<?php if($act):?><div class="active-tag">✓ ACTIF</div><?php endif;?>
<?php if($c['discount']):?><div class="discount-badge"><?=$c['discount']?></div><?php endif;?>
<div class="plan-inner">
<div class="plan-mascot"><video autoplay loop muted playsinline><source src="<?=$c['video']?>" type="video/mp4"></video></div>
<div class="plan-tier"><?=$c['tier']?></div><div class="plan-name"><?=$c['name']?></div>
<div class="plan-price-row"><span class="plan-price"><span class="cur">€</span><?=$c['price']?></span></div>
<div class="plan-period"><?=$c['period']?></div>
<ul class="plan-features"><?php foreach($c['features'] as $f):?><li><?=$f?></li><?php endforeach;?></ul>
<div class="plan-divider"></div>
<div class="plan-pay"><div class="plan-pay-label">💳 Payer maintenant</div>
<?php if($act):?><div class="plan-btn disabled">✓ Abonnement actif</div>
<?php else:?><a href="<?=$c['link']?>" class="plan-btn"><?=$c['btn']?></a><div class="sep-or">— ou —</div><a href="<?=$c['link']?>#crypto" class="btn-crypto">₿ Payer en Crypto</a><?php endif;?>
<a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="stake-card-btn"><img src="/assets/images/stake-s-icon.png" alt="" class="stake-btn-icon"> S'inscrire sur Stake.bet</a>
<div class="plan-pay-info"><?=$c['info']?></div></div></div></div>
<?php endforeach;?>
</div>

<!-- VIP MAX + Tennis -->
<div class="vip-section"><div class="vip-grid">
<div class="vip-card" style="animation:fU .7s ease .4s both">
<?php if(in_array('vip_max',$typesActifs)):?><div class="active-tag">✓ ACTIF</div><?php endif;?>
<div class="vip-tier">Accès Total</div>
<div class="vip-logo-wrap"><div style="display:flex;flex-direction:column;line-height:1.1;text-align:left"><span class="vip-logo-vip">VIP</span><span class="vip-logo-max">MAX</span></div></div>
<div class="vip-sub-label">Tous sports inclus</div>
<div class="vip-mascot"><video autoplay loop muted playsinline><source src="/assets/images/vip_max.mp4" type="video/mp4"></video></div>
<div class="vip-price"><span class="cur">€</span>50</div><div class="vip-period">/ mois (30 jours)</div>
<ul class="vip-features"><li>Tous les bets Multi-sport</li><li>Tennis ATP &amp; WTA exclusif</li><li>Bets LIVE &amp; Fun bets inclus</li><li>Accès illimité 30 jours</li></ul>
<?php if($fondateurActif):?><div class="fondateur-strip"><div><div class="fondateur-strip-left">👑 OFFRE FONDATEUR — 15% DE RÉDUCTION</div><div class="fondateur-jauge"><div class="fondateur-fill" style="width:<?=($fondateurPlaces/10)*100?>%"></div></div></div><div class="fondateur-strip-right">🔥 <?=$fondateurRestant?>/10</div></div><?php elseif($fondateurPlaces>=10):?><div class="fondateur-complet">✅ OFFRE FONDATEUR COMPLÈTE · TARIF NORMAL</div><?php endif;?>
<div class="vip-divider"></div>
<div class="vip-pay"><div class="vip-pay-label">💳 Payer maintenant</div>
<?php if(in_array('vip_max',$typesActifs)):?><div class="vip-btn" style="opacity:.4;pointer-events:none">✓ Abonnement actif</div>
<?php else:?><a href="offre.php?type=vip_max" class="vip-btn">💳 Payer — 50€</a><div class="sep-or" style="color:rgba(245,200,66,0.3)">— ou —</div><a href="offre.php?type=vip_max#crypto" class="btn-crypto vip-crypto">₿ Payer en Crypto</a><?php endif;?>
<a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="stake-card-btn" style="margin-top:0.75rem;"><img src="/assets/images/stake-s-icon.png" alt="" class="stake-btn-icon"> S'inscrire sur Stake.bet</a>
<div class="plan-pay-info" style="color:rgba(245,200,66,0.3)">CB · PayPal · Paysafecard · Internet+ via StarPass</div></div></div>

<!-- Tennis (même format que les autres packs) -->
<div class="plan-card" style="--cc:#00d46a;--cc-dim:#00a852;--cg:rgba(0,212,106,0.20);--cgrad:linear-gradient(135deg,#00d46a,#00a852);animation:fU .7s ease .45s both">
<?php if(in_array('tennis',$typesActifs)):?><div class="active-tag">✓ ACTIF</div><?php endif;?>
<div class="plan-inner">
<div class="plan-mascot"><video autoplay loop muted playsinline><source src="/assets/images/mascotte_tennis.mp4" type="video/mp4"></video></div>
<div class="plan-tier">🎾 Spécialité Tennis</div><div class="plan-name">Tennis Weekly</div>
<div class="plan-price-row"><span class="plan-price"><span class="cur">€</span>15</span></div>
<div class="plan-period">/ semaine (7 jours glissants)</div>
<ul class="plan-features"><li>Analyses ATP &amp; WTA en exclusivité</li><li>Bets Safe &amp; Fun Tennis</li><li>7 jours d'accès complet</li><li>Notifications Push &amp; Email</li></ul>
<div class="plan-divider"></div>
<div class="plan-pay"><div class="plan-pay-label">💳 Payer maintenant</div>
<?php if(in_array('tennis',$typesActifs)):?><div class="plan-btn disabled">✓ Abonnement actif</div>
<?php else:?><a href="offre-tennis.php" class="plan-btn">💳 Payer — 15€</a><div class="sep-or">— ou —</div><a href="offre-tennis.php#crypto" class="btn-crypto">₿ Payer en Crypto</a><?php endif;?>
<a href="https://stake.bet/?c=2bd992d384" target="_blank" rel="noopener noreferrer nofollow" class="stake-card-btn"><img src="/assets/images/stake-s-icon.png" alt="" class="stake-btn-icon"> S'inscrire sur Stake.bet</a>
<div class="plan-pay-info">CB · PayPal · Paysafecard · Crypto</div></div></div></div>
</div></div>

<div class="guarantee"><div class="guarantee-icon">🛡️</div><div class="guarantee-title">Sans engagement</div><p class="guarantee-text">Toutes nos formules sont <strong>sans abonnement récurrent</strong>. Tu payes une fois, tu profites de la durée choisie. Pas de renouvellement automatique.</p></div>
<div class="faq-section"><div class="faq-title">Questions fréquentes</div><div class="faq-grid">
<div class="faq-item"><div class="faq-q">Comment je reçois les bets ?</div><div class="faq-a">Dès ton abonnement activé, les bets apparaissent sur ton dashboard. Tu reçois aussi l'annonce par email et notification push.</div></div>
<div class="faq-item"><div class="faq-q">Puis-je cumuler les formules ?</div><div class="faq-a">Oui ! Par exemple, tu peux avoir un Weekly + un Tennis Weekly actifs en même temps.</div></div>
<div class="faq-item"><div class="faq-q">Comment payer ?</div><div class="faq-a">SMS+ (Daily), carte bancaire, PayPal, Paysafecard via StarPass, ou crypto.</div></div>
<div class="faq-item"><div class="faq-q">Qu'est-ce que le VIP MAX ?</div><div class="faq-a">Le VIP MAX inclut TOUS les bets (multi-sport + tennis + fun + live) pendant 30 jours.</div></div>
</div></div>
</div>
<?php if(file_exists(__DIR__.'/includes/footer-legal.php')): ?><?php require_once __DIR__.'/includes/footer-legal.php'; ?><?php endif; ?>
</main></div>
</body></html>
