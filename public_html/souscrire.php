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
<div class="sub-hero"><div class="sub-hero-tag">⚡ Nos Tipsters</div><h1 class="sub-hero-title">Choisis ton Tipster</h1><p class="sub-hero-desc">Accède aux analyses de nos experts, bets <strong>Safe</strong>, <strong>Live</strong> et <strong>Fun</strong>. Sans engagement, tu es libre à tout moment.</p></div>

<!-- 4 Tipster Cards -->
<style>
.tipster-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.3rem;margin-bottom:2rem}
.tip-card{background:linear-gradient(165deg,#0c1018,#111827 60%,#0d1220);border:1px solid rgba(255,255,255,0.06);border-radius:22px;overflow:hidden;position:relative;transition:transform .35s,box-shadow .35s,border-color .35s;display:flex;flex-direction:column}
.tip-card:hover{transform:translateY(-6px);border-color:var(--tc);box-shadow:0 20px 60px -15px var(--tg)}
.tip-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--tgrad);z-index:4}
.tip-card--multi{--tc:#ff2d78;--tg:rgba(255,45,120,0.15);--tgrad:linear-gradient(90deg,#ff2d78,#00d4ff)}
.tip-card--tennis{--tc:#00d46a;--tg:rgba(0,212,106,0.15);--tgrad:linear-gradient(90deg,#00d46a,#00a852)}
.tip-card--fun{--tc:#a855f7;--tg:rgba(168,85,247,0.15);--tgrad:linear-gradient(90deg,#a855f7,#ff6b2b)}
.tip-card--vip{--tc:#f5c842;--tg:rgba(245,200,66,0.15);--tgrad:linear-gradient(90deg,#c8960c,#f5c842,#fffbe6,#e8a020);border-color:rgba(245,200,66,0.2);box-shadow:0 0 30px rgba(245,200,66,0.06)}
.tip-inner{padding:1.8rem 1.5rem 1.4rem;display:flex;flex-direction:column;flex:1}
.tip-badge{display:inline-flex;align-items:center;gap:.4rem;font-family:'Space Mono',monospace;font-size:.55rem;letter-spacing:2.5px;text-transform:uppercase;color:var(--tc);background:color-mix(in srgb,var(--tc) 10%,transparent);border:1px solid color-mix(in srgb,var(--tc) 25%,transparent);padding:.3rem .8rem;border-radius:20px;margin-bottom:.8rem;align-self:flex-start}
.tip-mascot{width:100px;height:100px;margin:0 auto 1rem;border-radius:50%;overflow:hidden;background:rgba(255,255,255,0.03);border:2px solid color-mix(in srgb,var(--tc) 40%,transparent);box-shadow:0 0 25px var(--tg)}.tip-mascot video{width:100%;height:100%;object-fit:cover;border-radius:50%}
.tip-name{font-family:'Orbitron',sans-serif;font-size:1.15rem;font-weight:900;text-align:center;margin-bottom:.2rem}
.tip-sub{font-size:.78rem;color:#8a9bb0;text-align:center;margin-bottom:.8rem}
.tip-sports{display:flex;flex-wrap:wrap;gap:.3rem;justify-content:center;margin-bottom:.8rem}
.tip-sport-tag{font-size:.6rem;font-weight:700;padding:.2rem .45rem;border-radius:5px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#b0bec9}
.tip-features{list-style:none;padding:0;margin:0 0 .8rem;flex:1}.tip-features li{padding:.3rem 0;color:#b0bec9;font-size:.82rem;display:flex;align-items:center;gap:.5rem}.tip-features li::before{content:'\2713';color:var(--tc);font-weight:700;flex-shrink:0;font-size:.7rem}
.tip-sep{width:100%;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.06),transparent);margin:.3rem 0 .6rem}
.tip-price-row{display:flex;align-items:baseline;justify-content:center;gap:.15rem;margin-bottom:.2rem}
.tip-price{font-family:'Orbitron',sans-serif;font-size:2rem;font-weight:900;color:var(--tc);line-height:1}.tip-price .cur{font-size:.9rem;vertical-align:super}
.tip-period{font-size:.75rem;color:#8a9bb0;text-align:center;margin-bottom:.7rem}
.tip-pricing-hint{text-align:center;font-size:.78rem;color:#8a9bb0;margin-bottom:.7rem}.tip-pricing-hint strong{color:var(--tc);font-family:'Orbitron',sans-serif;font-size:.85rem}
.tip-cta{display:block;width:100%;padding:.75rem;background:linear-gradient(135deg,var(--tc),color-mix(in srgb,var(--tc) 70%,#000));color:#fff;border:none;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:.68rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;text-decoration:none;text-align:center;cursor:pointer;transition:all .3s;box-shadow:0 6px 18px var(--tg)}.tip-cta:hover{transform:translateY(-2px);box-shadow:0 10px 30px var(--tg)}
.tip-cta-outline{display:block;width:100%;padding:.6rem;background:transparent;color:var(--tc);border:1px solid color-mix(in srgb,var(--tc) 35%,transparent);border-radius:10px;font-family:'Orbitron',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;text-decoration:none;text-align:center;cursor:pointer;transition:all .3s;margin-top:.4rem}.tip-cta-outline:hover{background:color-mix(in srgb,var(--tc) 8%,transparent);border-color:var(--tc)}
.tip-gw{margin-top:.5rem;padding:.4rem .6rem;border-radius:8px;border:1px solid transparent;text-align:center;background:linear-gradient(135deg,#111827,#111827) padding-box,linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff) border-box}
.tip-card--vip .tip-gw{background:linear-gradient(160deg,#111208,#0d1220,#100e05) padding-box,linear-gradient(135deg,#f5c842,#e8a020,#c8960c) border-box}
.tip-gw-txt{font-family:'Orbitron',sans-serif;font-size:.58rem;font-weight:700;letter-spacing:1px;background:linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.tip-card--vip .tip-gw-txt{background:linear-gradient(135deg,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.tip-stake{margin-top:.5rem;text-align:center}
.tip-stake-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:.6rem .8rem;background:linear-gradient(135deg,#00d4ff,#0089ff);color:#fff;font-family:'Orbitron',sans-serif;font-size:.58rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;border:1px solid rgba(0,212,255,0.35);border-radius:8px;cursor:pointer;text-decoration:none;transition:all .25s;box-shadow:0 4px 14px rgba(0,166,255,0.2)}.tip-stake-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,166,255,0.35)}
.tip-stake-note{font-size:.62rem;color:rgba(0,212,255,0.6);margin-top:.25rem}
.tip-free-badge{margin-top:.4rem;padding:.4rem .6rem;border-radius:8px;background:rgba(0,200,100,0.06);border:1px solid rgba(0,200,100,0.2);text-align:center}
.tip-free-badge-txt{font-family:'Orbitron',sans-serif;font-size:.55rem;font-weight:700;letter-spacing:1px;color:#00c864}
.tip-free-badge-sub{font-size:.65rem;color:#8a9bb0;margin-top:.1rem}
.tip-active{position:absolute;top:.8rem;right:.8rem;font-family:'Orbitron',sans-serif;font-size:.5rem;letter-spacing:1.5px;font-weight:700;background:rgba(57,255,20,0.12);border:1px solid rgba(57,255,20,0.3);color:#39ff14;padding:.2rem .6rem;border-radius:20px;text-transform:uppercase;z-index:6}
@media(max-width:1100px){.tipster-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.tipster-grid{grid-template-columns:1fr}.tip-inner{padding:1.4rem 1.2rem}}
</style>

<div class="tipster-grid">

  <!-- MULTISPORTS -->
  <div class="tip-card tip-card--multi fade-up" style="animation:fU .6s ease .1s both">
    <?php if(array_intersect(['daily','weekend','weekly'],$typesActifs)):?><div class="tip-active">✓ ACTIF</div><?php endif;?>
    <div class="tip-inner">
      <div class="tip-badge">🏆 Tipster principal</div>
      <div class="tip-mascot"><video autoplay loop muted playsinline><source src="/assets/images/DOIGT.mp4" type="video/mp4"></video></div>
      <div class="tip-name">Multisports</div>
      <div class="tip-sub">Safe · Fun · LIVE · Montante</div>
      <div class="tip-sports"><span class="tip-sport-tag">⚽ Foot</span><span class="tip-sport-tag">🏀 NBA</span><span class="tip-sport-tag">🏒 NHL</span><span class="tip-sport-tag">⚾ MLB</span></div>
      <ul class="tip-features"><li>Bets Safe &amp; Fun quotidiens</li><li>Bets LIVE par mail &amp; Push</li><li>1 montante par mois</li><li>Analyses data &amp; xG</li></ul>
      <div class="tip-sep"></div>
      <div class="tip-pricing-hint">à partir de <strong>4,50€</strong></div>
      <a href="/offres-multisports.php" class="tip-cta">📊 Voir les offres</a>
      <div class="tip-gw"><span>🎁</span> <span class="tip-gw-txt">Éligible au GiveAway mensuel</span></div>
      <div class="tip-free-badge"><div class="tip-free-badge-txt">🆓 3 paris gratuits / semaine</div><div class="tip-free-badge-sub">« Pari de la commu »</div></div>
      <div class="tip-stake"><a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎰 S’inscrire sur Stake</a><div class="tip-stake-note">1 mois VIP MAX offert</div></div>
    </div>
  </div>

  <!-- TENNIS -->
  <div class="tip-card tip-card--tennis fade-up" style="animation:fU .6s ease .2s both">
    <?php if(in_array('tennis',$typesActifs)):?><div class="tip-active">✓ ACTIF</div><?php endif;?>
    <div class="tip-inner">
      <div class="tip-badge">🎾 Spécialiste</div>
      <div class="tip-mascot"><video autoplay loop muted playsinline><source src="/assets/images/mascotte_tennis.mp4" type="video/mp4"></video></div>
      <div class="tip-name">Tennis</div>
      <div class="tip-sub">ATP · WTA · Grand Chelem</div>
      <div class="tip-sports"><span class="tip-sport-tag">🎾 ATP</span><span class="tip-sport-tag">🎾 WTA</span></div>
      <ul class="tip-features"><li>Analyses ATP &amp; WTA exclusives</li><li>Bets Safe &amp; Fun Tennis</li><li>7 jours d’accès complet</li><li>Notifications Push &amp; Email</li></ul>
      <div class="tip-sep"></div>
      <div class="tip-price-row"><div class="tip-price"><span class="cur">€</span>15</div></div>
      <div class="tip-period">/ semaine (7 jours)</div>
      <?php if(in_array('tennis',$typesActifs)):?><div class="tip-cta" style="opacity:.4;pointer-events:none;text-align:center">✓ Abonnement actif</div>
      <?php else:?><a href="offre-tennis.php" class="tip-cta">💳 Payer — 15€</a><?php endif;?>
      <a href="offre-tennis.php#crypto" class="tip-cta-outline">₿ Payer en Crypto</a>
      <div class="tip-stake"><a href="https://stake.bet/?c=2bd992d384" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎁 S’inscrire sur Stake</a><div class="tip-stake-note">1 mois VIP MAX offert</div></div>
    </div>
  </div>

  <!-- FUN ONLY -->
  <div class="tip-card tip-card--fun fade-up" style="animation:fU .6s ease .3s both">
    <?php if(in_array('fun',$typesActifs)):?><div class="tip-active">✓ ACTIF</div><?php endif;?>
    <div class="tip-inner">
      <div class="tip-badge">🎲 Grosses cotes</div>
      <div class="tip-mascot"><video autoplay loop muted playsinline><source src="/assets/images/mascotte-fun.mp4" type="video/mp4"></video></div>
      <div class="tip-name">Fun Only</div>
      <div class="tip-sub">Week-end · Champions League</div>
      <div class="tip-sports"><span class="tip-sport-tag">⚽ Foot WE</span><span class="tip-sport-tag">🏆 Ligue des Champions</span></div>
      <ul class="tip-features"><li>Combinés grosses cotes</li><li>Spécial week-end &amp; soirées C1</li><li>Bets Fun à forte value</li><li>Notifications Push &amp; Email</li></ul>
      <div class="tip-sep"></div>
      <div class="tip-price-row"><div class="tip-price"><span class="cur">€</span>10</div></div>
      <div class="tip-period">/ week-end (ven→dim)</div>
      <?php if(in_array('fun',$typesActifs)):?><div class="tip-cta" style="opacity:.4;pointer-events:none;text-align:center">✓ Abonnement actif</div>
      <?php else:?><a href="packs-fun.php" class="tip-cta">💳 Payer — 10€</a><?php endif;?>
      <a href="packs-fun.php" class="tip-cta-outline">₿ Payer en Crypto</a>
      <div class="tip-stake"><a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎰 S’inscrire sur Stake</a><div class="tip-stake-note">1 mois VIP MAX offert</div></div>
    </div>
  </div>

  <!-- VIP MAX -->
  <div class="tip-card tip-card--vip fade-up" style="animation:fU .6s ease .4s both">
    <?php if(in_array('vip_max',$typesActifs)):?><div class="tip-active">✓ ACTIF</div><?php endif;?>
    <div class="tip-inner">
      <div class="tip-badge">👑 Accès Total</div>
      <div class="tip-mascot" style="border-color:rgba(245,200,66,0.35);box-shadow:0 0 30px rgba(245,200,66,0.2)"><video autoplay loop muted playsinline><source src="/assets/images/vip_max.mp4" type="video/mp4"></video></div>
      <div class="tip-name" style="background:linear-gradient(135deg,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</div>
      <div class="tip-sub">Tous les tipsters réunis</div>
      <div class="tip-sports"><span class="tip-sport-tag" style="color:#f5c842;border-color:rgba(245,200,66,0.2)">🏆 Multi</span><span class="tip-sport-tag" style="color:#f5c842;border-color:rgba(245,200,66,0.2)">🎾 Tennis</span><span class="tip-sport-tag" style="color:#f5c842;border-color:rgba(245,200,66,0.2)">🎲 Fun</span></div>
      <ul class="tip-features" style="--tc:#f5c842"><li>TOUT : Safe + Fun + LIVE + Tennis</li><li>Accès aux 3 tipsters</li><li>Montantes incluses</li><li>30 jours illimités</li></ul>
      <div class="tip-sep"></div>
      <div class="tip-price-row"><div class="tip-price" style="color:#f5c842"><span class="cur">€</span>50</div></div>
      <div class="tip-period">/ mois (30 jours)</div>
      <?php if(in_array('vip_max',$typesActifs)):?><div class="tip-cta" style="opacity:.4;pointer-events:none;text-align:center;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810">✓ Abonnement actif</div>
      <?php else:?><a href="offre.php?type=vip_max" class="tip-cta" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810">💳 Payer — 50€</a><?php endif;?>
      <a href="offre.php?type=vip_max#crypto" class="tip-cta-outline" style="color:#f5c842;border-color:rgba(245,200,66,0.3)">₿ Payer en Crypto</a>
      <div class="tip-gw"><span>🎁</span> <span class="tip-gw-txt">Éligible au GiveAway mensuel</span></div>
      <div class="tip-stake"><a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎰 S’inscrire sur Stake</a><div class="tip-stake-note">1 mois VIP MAX offert</div></div>
    </div>
  </div>

</div><!-- /.tipster-grid -->

<div class="guarantee"><div class="guarantee-icon">🛡️</div><div class="guarantee-title">Sans engagement</div><p class="guarantee-text">Toutes nos formules sont <strong>sans abonnement récurrent</strong>. Tu payes une fois, tu profites de la durée choisie. Pas de renouvellement automatique.</p></div>
<div class="faq-section"><div class="faq-title">Questions fréquentes</div><div class="faq-grid">
<div class="faq-item"><div class="faq-q">Comment je reçois les bets ?</div><div class="faq-a">Dès ton abonnement activé, les bets apparaissent sur ton dashboard. Tu reçois aussi l'annonce par email et notification push.</div></div>
<div class="faq-item"><div class="faq-q">Puis-je cumuler les formules ?</div><div class="faq-a">Oui ! Par exemple, tu peux avoir un Weekly + un Tennis Weekly actifs en même temps.</div></div>
<div class="faq-item"><div class="faq-q">Comment payer ?</div><div class="faq-a">SMS (Daily), carte bancaire via Stripe, Paysafecard, ou crypto.</div></div>
<div class="faq-item"><div class="faq-q">Qu'est-ce que le VIP MAX ?</div><div class="faq-a">Le VIP MAX inclut TOUS les bets (multi-sport + tennis + fun + live) pendant 30 jours.</div></div>
</div></div>
</div>
<?php if(file_exists(__DIR__.'/includes/footer-legal.php')): ?><?php require_once __DIR__.'/includes/footer-legal.php'; ?><?php endif; ?>
</main></div>
</body></html>
