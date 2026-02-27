<?php
require_once __DIR__ . '/gate.php';
require_once __DIR__ . '/includes/auth.php';
$membre = isLoggedIn() ? getMembre() : null;

// ── Places fondateur VIP Max ──────────────────────────────
$fondateurPlaces  = 0;
$fondateurRestant = 0;
$fondateurActif   = false;
try {
    $dbIndex = getDB();
    $fondateurPlaces  = (int)$dbIndex->query("SELECT COUNT(*) FROM vip_max_fondateurs")->fetchColumn();
    $fondateurRestant = max(0, 10 - $fondateurPlaces);
    $fondateurActif   = ($fondateurRestant > 0);
} catch (Exception $e) { /* table pas encore créée */ }
$abonnement = $membre ? getAbonnementActif($membre['id']) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StratEdge Pronos – Ta stratégie. Notre Edge. Leur défaite.</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #0a0e17;
      --bg-card: #111827;
      --bg-card-hover: #1a1630;
      --neon-green: #ff2d78;
      --neon-green-dim: #d6245f;
      --neon-blue: #00d4ff;
      --neon-purple: #a855f7;
      --accent-orange: #ff6b2b;
      --text-primary: #f0f4f8;
      --text-secondary: #b0bec9;
      --text-muted: #8a9bb0;
      --border-subtle: rgba(255,45,120,0.15);
      --glow-green: 0 0 30px rgba(255,45,120,0.35);
      --glow-blue: 0 0 30px rgba(0,212,255,0.3);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; overflow-x: hidden; overflow-y: visible; }
    body { font-family: 'Rajdhani', sans-serif; background: var(--bg-dark); color: var(--text-primary); overflow-x: hidden; overflow-y: visible; line-height: 1.6; max-width: 100vw; }
    body::before { content: ''; position: fixed; inset: 0; background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E"); pointer-events: none; z-index: 9999; }

    /* NAVBAR */
    nav { position: fixed; top: 0; width: 100%; z-index: 1000; background: rgba(10,14,23,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-subtle); padding: 0 2rem; overflow: hidden; }
    .nav-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 70px; }
    .logo { text-decoration: none; display: flex; align-items: center; }
    .logo-img { height: 45px; width: auto; }
    .nav-links { display: flex; gap: 1.5rem; list-style: none; align-items: center; }
    .nav-links a { color: var(--text-secondary); text-decoration: none; font-size: 1rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; transition: color 0.3s; }
    .nav-links a:hover { color: var(--neon-green); }
    .nav-cta { background: linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color: var(--bg-dark) !important; padding: 0.5rem 1.2rem; border-radius: 6px; font-weight: 700; }
    .nav-member { background: rgba(255,45,120,0.1); border: 1px solid var(--border-subtle); color: var(--neon-green) !important; padding: 0.5rem 1.2rem; border-radius: 6px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding:4px; }
    .hamburger span { width: 28px; height: 2px; background: var(--neon-green); transition: 0.35s cubic-bezier(0.4,0,0.2,1); display:block; }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* HERO */
    .hero { min-height: 100vh; height: auto; display: flex; position: relative; padding: 0; overflow: visible; max-width: 100vw; }
    .hero-bg-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(255,45,120,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,45,120,0.03) 1px, transparent 1px); background-size: 60px 60px; mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black 30%, transparent 70%); }
    .hero-glow { position: absolute; width: 900px; height: 900px; background: radial-gradient(circle, rgba(255,45,120,0.1) 0%, transparent 70%); top: -200px; left: -200px; pointer-events: none; }
    .hero-glow-2 { position: absolute; width: 700px; height: 700px; background: radial-gradient(circle, rgba(0,212,255,0.06) 0%, transparent 70%); bottom: -300px; right: -150px; pointer-events: none; }
    .hero-glow-mascot { position: absolute; width: 800px; height: 800px; background: radial-gradient(circle, rgba(255,45,120,0.12) 0%, transparent 60%); bottom: -450px; left: 50%; transform: translateX(-50%); pointer-events: none; }
    .hero-inner { max-width: 1440px; width: 100%; margin: 0 auto; position: relative; z-index: 1; padding: 0 5rem; padding-top: 6rem; padding-bottom: 2rem; display: flex; flex-direction: column; justify-content: center; }
    .hero-text { max-width: 700px; position: relative; z-index: 10; }
    .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,45,120,0.08); border: 1px solid rgba(255,45,120,0.25); color: var(--neon-green); padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; animation: pulse-badge 2s ease-in-out infinite; }
    @keyframes pulse-badge { 0%, 100% { box-shadow: 0 0 0 0 rgba(255,45,120,0.25); } 50% { box-shadow: 0 0 0 8px rgba(255,45,120,0); } }
    .hero h1 { font-family: 'Orbitron', sans-serif; font-size: clamp(2.8rem, 5vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.5rem; }
    .hero h1 .highlight { background: linear-gradient(135deg, var(--neon-green), var(--neon-blue)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .hero-slogan { font-family: 'Rajdhani', sans-serif; font-size: clamp(1.3rem, 2.2vw, 1.8rem); font-weight: 600; letter-spacing: 3px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 1.5rem; border-left: 3px solid var(--neon-green); padding-left: 1rem; }
    .hero-slogan span { color: var(--neon-green); font-weight: 700; }
    .hero-desc { font-size: 1.25rem; color: var(--text-secondary); margin-bottom: 2rem; max-width: 580px; line-height: 1.7; }
    .hero-stats { display: flex; gap: 3rem; margin-bottom: 2rem; }
    .stat { text-align: center; }
    .stat-value { font-family: 'Orbitron', sans-serif; font-size: 2.5rem; font-weight: 900; color: var(--neon-green); }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
    .hero-btns { display: flex; gap: 1rem; }
    .hero-visual { position: absolute; top: 78px; right: -5%; bottom: auto; left: auto; transform: none; z-index: 2; pointer-events: none; width: 900px; height: 1050px; }
    .mascot-container { position: relative; width: 900px; height: 1050px; }
    .mascot-ring { position: absolute; top: -60px; left: -60px; right: -60px; bottom: -60px; border-radius: 50%; border: 2px solid rgba(255,45,120,0.2); animation: ring-rotate 20s linear infinite; }
    .mascot-ring::before { content: ''; position: absolute; top: -7px; left: 50%; width: 14px; height: 14px; background: var(--neon-green); border-radius: 50%; box-shadow: var(--glow-green); }
    @keyframes ring-rotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .mascot-img { position: absolute; top: -170px; left: 50%; transform: translateX(-50%); width: 900px; height: auto; filter: drop-shadow(0 0 80px rgba(255,45,120,0.35)); animation: hero-mascot-breathe 4.5s ease-in-out infinite, hero-mascot-eyes 3.2s ease-in-out infinite; }
    @keyframes hero-mascot-breathe { 0%, 100% { transform: translateX(-50%) translateY(0) scale(1); } 50% { transform: translateX(-50%) translateY(-8px) scale(1.008); } }
    @keyframes hero-mascot-eyes {
      0%, 100% { filter: drop-shadow(0 0 45px rgba(255,45,120,0.5)) drop-shadow(0 0 90px rgba(255,45,120,0.2)); }
      30% { filter: drop-shadow(0 0 55px rgba(255,45,120,0.7)) drop-shadow(0 0 110px rgba(255,45,120,0.35)) drop-shadow(0 0 8px rgba(255,160,200,0.9)); }
      60% { filter: drop-shadow(0 0 40px rgba(255,45,120,0.45)) drop-shadow(0 0 80px rgba(255,45,120,0.15)); }
    }

    /* Effets gate derrière la mascotte (particules, anneaux, glow, ki) */
    .hero-visual { overflow: visible; }
    .mascot-container { z-index: 3; }
    .hero-particles { position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .hero-particle { position: absolute; bottom: -20px; border-radius: 50%; animation: hero-bubble linear infinite; }
    @keyframes hero-bubble {
      0%   { transform: translateY(0) scale(0); opacity: 0; }
      8%   { opacity: 0.8; }
      85%  { opacity: 0.5; }
      100% { transform: translateY(-110vh) scale(1.2); opacity: 0; }
    }
    #hero-ki-canvas { position: absolute; inset: 0; z-index: 1; pointer-events: none; width: 100%; height: 100%; }
    .hero-ground-ring { position: absolute; bottom: 2%; left: 50%; transform: translateX(-50%); border-radius: 50%; border: 2px solid; pointer-events: none; z-index: 2; }
    .hero-gr1 { width: 260px; height: 50px; border-color: rgba(255,45,120,0.85); box-shadow: 0 0 20px rgba(255,45,120,0.6); animation: hero-gr1 1.6s ease-in-out infinite; }
    .hero-gr2 { width: 420px; height: 80px; border-color: rgba(255,45,120,0.5); box-shadow: 0 0 15px rgba(255,45,120,0.25); animation: hero-gr2 2s ease-in-out infinite 0.2s; }
    .hero-gr3 { width: 580px; height: 110px; border-color: rgba(0,212,255,0.35); box-shadow: 0 0 12px rgba(0,212,255,0.2); animation: hero-gr3 2.5s ease-in-out infinite 0.5s; }
    @keyframes hero-gr1 { 0%, 100% { opacity: 0.9; transform: translateX(-50%) scaleY(1); } 50% { opacity: 0.4; transform: translateX(-50%) scaleY(1.15); } }
    @keyframes hero-gr2 { 0%, 100% { opacity: 0.5; transform: translateX(-50%); } 50% { opacity: 0.2; transform: translateX(-50%) scaleY(1.1) translateY(-4px); } }
    @keyframes hero-gr3 { 0%, 100% { opacity: 0.25; } 50% { opacity: 0.08; } }
    .hero-ground-glow {
      position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); z-index: 2; pointer-events: none;
      width: 520px; height: 80px;
      background: radial-gradient(ellipse, rgba(255,45,120,0.7) 0%, rgba(255,45,120,0.25) 45%, rgba(255,45,120,0.08) 70%, transparent 85%);
      filter: blur(12px); animation: hero-gg 1.5s ease-in-out infinite;
    }
    @keyframes hero-gg { 0%, 100% { opacity: 0.8; transform: translateX(-50%) scaleX(1); } 50% { opacity: 0.45; transform: translateX(-50%) scaleX(1.2); } }

    /* BUTTONS */
    .btn-primary { background: linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color: white; padding: 1rem 2.5rem; border-radius: 10px; font-family: 'Rajdhani', sans-serif; font-size: 1.15rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; transition: all 0.3s; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
    .btn-primary:hover { box-shadow: var(--glow-green); transform: translateY(-2px); }
    .btn-outline { background: transparent; color: var(--text-primary); padding: 1rem 2rem; border-radius: 10px; font-family: 'Rajdhani', sans-serif; font-size: 1.05rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; border: 1px solid var(--border-subtle); transition: all 0.3s; }
    .btn-outline:hover { border-color: var(--neon-green); color: var(--neon-green); }

    /* SECTIONS */
    section { padding: 5rem 2rem; position: relative; overflow: hidden; }
    .section-tag { font-family: 'Space Mono', monospace; font-size: 0.75rem; letter-spacing: 3px; text-transform: uppercase; color: var(--neon-green); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.75rem; }
    .section-tag::before { content: ''; width: 30px; height: 1px; background: var(--neon-green); }
    .section-title { font-family: 'Orbitron', sans-serif; font-size: 2.4rem; font-weight: 700; margin-bottom: 1rem; }
    .section-subtitle { color: var(--text-secondary); max-width: 600px; font-size: 1.05rem; }

    /* FEATURES */
    #why { background: linear-gradient(180deg, var(--bg-dark) 0%, #0d1220 100%); }
    .features-grid { max-width: 1200px; margin: 3rem auto 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .feature-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 2rem; transition: all 0.3s; position: relative; overflow: hidden; }
    .feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--neon-green), transparent); opacity: 0; transition: opacity 0.3s; }
    .feature-card:hover::before { opacity: 1; }
    .feature-card:hover { background: var(--bg-card-hover); transform: translateY(-4px); box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    .feature-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(255,45,120,0.1); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1.2rem; }
    .feature-card h3 { font-family: 'Orbitron', sans-serif; font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--text-primary); }
    .feature-card p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; }

    /* HOW IT WORKS */
    #how { background: var(--bg-dark); }
    .steps-container { max-width: 900px; margin: 3rem auto 0; display: flex; flex-direction: column; gap: 0; position: relative; }
    .steps-container::before { content: ''; position: absolute; left: 30px; top: 30px; bottom: 30px; width: 2px; background: linear-gradient(to bottom, var(--neon-green), var(--neon-blue), var(--neon-purple)); opacity: 0.3; }
    .step { display: flex; gap: 2rem; padding: 2rem 0; align-items: flex-start; }
    .step-number { flex-shrink: 0; width: 60px; height: 60px; border-radius: 50%; background: rgba(255,45,120,0.1); border: 2px solid var(--neon-green); display: flex; align-items: center; justify-content: center; font-family: 'Orbitron', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--neon-green); position: relative; z-index: 1; }
    .step-content h3 { font-family: 'Orbitron', sans-serif; font-size: 1.1rem; margin-bottom: 0.5rem; }
    .step-content p { color: var(--text-secondary); font-size: 0.95rem; }

    /* PRICING */
    #pricing { background: linear-gradient(180deg, #0d1220 0%, var(--bg-dark) 100%); }
    .pricing-grid { max-width: 1400px; margin: 3rem auto 0; display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.2rem; align-items: stretch; }
    .price-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 20px; padding: 2.5rem 2rem; text-align: center; position: relative; transition: all 0.3s; overflow: hidden; display: flex; flex-direction: column; }
    .price-card.featured { border-color: var(--neon-green); box-shadow: 0 0 40px rgba(255,45,120,0.1); transform: scale(1.03); }
    .price-card.featured::before { content: '⭐ RECOMMANDÉ'; position: absolute; top: 0; left: 0; right: 0; background: linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color: var(--bg-dark); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; font-weight: 700; letter-spacing: 2px; padding: 0.5rem; }
    .price-card:hover { transform: translateY(-6px); box-shadow: 0 30px 80px rgba(0,0,0,0.4); }
    .price-card.featured:hover { transform: scale(1.03) translateY(-6px); }
    .price-tier { font-family: 'Space Mono', monospace; font-size: 0.7rem; letter-spacing: 3px; text-transform: uppercase; color: var(--neon-green); margin-bottom: 0.5rem; }
    .price-card.featured .price-tier { margin-top: 1.2rem; }
    .price-name { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; }
    .price-mascot { width: 150px; height: 150px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,45,120,0.06); display: flex; align-items: center; justify-content: center; font-size: 3rem; overflow: hidden; }
    .price-mascot img { width: 100%; height: 100%; object-fit: cover; }
    .price-mascot video { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .price-amount { font-family: 'Orbitron', sans-serif; font-size: 3rem; font-weight: 900; color: var(--neon-green); line-height: 1; }
    .price-amount .currency { font-size: 1.5rem; vertical-align: super; }
    .price-period { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; }
    .price-features { list-style: none; margin-bottom: 2rem; text-align: left; }
    .price-features li { padding: 0.5rem 0; color: var(--text-secondary); font-size: 0.95rem; display: flex; align-items: center; gap: 0.75rem; }
    .price-features li::before { content: '✓'; color: var(--neon-green); font-weight: 700; flex-shrink: 0; }
    .price-divider { width: 100%; height: 1px; background: var(--border-subtle); margin: 1.5rem 0; margin-top: auto; }
    .starpass-zone { background: rgba(255,45,120,0.05); border: 1px dashed rgba(255,45,120,0.25); border-radius: 12px; padding: 1.5rem; margin-top: 1rem; }
    .starpass-zone p { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem; }
    .starpass-label { font-family: 'Space Mono', monospace; font-size: 0.7rem; color: var(--neon-green); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 0.5rem; }
    .starpass-btn { display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color: var(--bg-dark); padding: 0.75rem 1.5rem; border-radius: 8px; font-family: 'Rajdhani', sans-serif; font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; border: none; transition: all 0.3s; text-decoration: none; width: 100%; justify-content: center; }
    .starpass-btn:hover { box-shadow: var(--glow-green); transform: translateY(-2px); }
    .starpass-info { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; }
    .crypto-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px 24px; margin-top: 0.5rem; background: linear-gradient(135deg, #f7931a, #e2820a); color: #fff; font-family: 'Orbitron', sans-serif; font-size: 0.85rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(247,147,26,0.3); }
    .crypto-btn:hover { background: linear-gradient(135deg, #ffaa33, #f7931a); box-shadow: 0 6px 25px rgba(247,147,26,0.5); transform: translateY(-2px); }
    .crypto-separator { text-align: center; color: var(--text-muted); font-size: 0.75rem; margin: 0.8rem 0 0.3rem; text-transform: uppercase; letter-spacing: 2px; }
    .discount-badge { position: absolute; top: 1rem; right: 1rem; background: var(--accent-orange); color: white; font-family: 'Orbitron', sans-serif; font-size: 0.65rem; font-weight: 700; padding: 0.3rem 0.6rem; border-radius: 6px; letter-spacing: 1px; }
    .price-card.featured .discount-badge { top: 2.8rem; }

    /* VIP MAX CARD */
    .price-card-vip { position: relative; overflow: visible; height: 100%; }
    .price-card-vip .vip-card-inner {
      background: linear-gradient(160deg, #111208 0%, #0d1220 50%, #100e05 100%);
      border-radius: 20px;
      border: 1.5px solid rgba(245,200,66,0.28);
      padding: 2.5rem 2rem;
      text-align: center;
      position: relative;
      overflow: hidden;
      transition: all 0.3s;
      height: 100%;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }
    .price-card-vip .vip-card-inner::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, #c8960c, #f5c842, #fffbe6, #f5c842, #c8960c);
      background-size: 200% 100%;
      animation: vipShimBar 2.5s linear infinite; z-index: 1;
    }
    @keyframes vipShimBar { from{background-position:-100% 0} to{background-position:100% 0} }
    .price-card-vip .vip-card-inner::after {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse at 50% 0%, rgba(245,200,66,0.05), transparent 60%);
      pointer-events: none;
    }
    .price-card-vip:hover .vip-card-inner { transform: translateY(-6px); box-shadow: 0 30px 80px rgba(245,200,66,0.12); transition: all 0.3s; }
    .vip-card-svg { position: absolute; inset: -2px; pointer-events: none; z-index: 5; overflow: visible; border-radius: 22px; }
    .vip-badge-tag {
      display: inline-flex; align-items: center; gap: 0.3rem;
      font-family: 'Orbitron', sans-serif; font-size: 0.52rem; letter-spacing: 2px;
      padding: 0.22rem 0.7rem; border-radius: 20px;
      background: rgba(245,200,66,0.1); color: #ffd700;
      border: 1px solid rgba(245,200,66,0.45); margin-bottom: 1rem;
    }
    .vip-logo-wrap { display: flex; align-items: center; justify-content: center; gap: 0.6rem; margin-bottom: 0.4rem; }
    .vip-crown-icon { width: 42px; height: 42px; flex-shrink: 0; filter: drop-shadow(0 0 8px rgba(245,200,66,0.7)); }
    .vip-logo-texts { display: flex; flex-direction: column; line-height: 1.1; text-align: left; }
    .vip-logo-vip { font-family: 'Orbitron', sans-serif; font-size: 1.2rem; font-weight: 900; letter-spacing: 3px; background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6, #e8a020); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .vip-logo-max { font-family: 'Orbitron', sans-serif; font-size: 0.6rem; font-weight: 700; letter-spacing: 5px; color: rgba(245,200,66,0.55); }
    .vip-sub-label { font-size: 0.78rem; color: rgba(245,200,66,0.4); margin-bottom: 1.2rem; }
    .vip-mascot { width: 150px; height: 150px; margin: 0 auto 1.5rem; border-radius: 50%; overflow: hidden; border: 2px solid rgba(245,200,66,0.35); box-shadow: 0 0 30px rgba(245,200,66,0.2); }
    .vip-mascot video { width: 100%; height: 100%; object-fit: cover; }
    .vip-price-num { font-family: 'Orbitron', sans-serif; font-size: 3rem; font-weight: 900; line-height: 1; background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6, #e8a020); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .vip-price-num .currency { font-size: 1.5rem; vertical-align: super; }
    .vip-price-dur { color: rgba(245,200,66,0.4); font-size: 0.9rem; margin-bottom: 2rem; }
    .vip-features { list-style: none; margin-bottom: 2rem; text-align: left; }
    .vip-features li { padding: 0.5rem 0; color: rgba(245,200,66,0.7); font-size: 0.95rem; display: flex; align-items: center; gap: 0.75rem; }
    .vip-features li::before { content: '★'; color: #ffd700; font-weight: 700; flex-shrink: 0; font-size: 0.8rem; }
    .vip-divider { width: 100%; height: 1px; background: rgba(245,200,66,0.12); margin: 1.5rem 0; margin-top: auto; }
    .vip-zone { background: rgba(245,200,66,0.04); border: 1px dashed rgba(245,200,66,0.2); border-radius: 12px; padding: 1.5rem; margin-top: 1rem; }
    .vip-zone p { font-size: 0.85rem; color: rgba(245,200,66,0.4); margin-bottom: 0.75rem; }
    .vip-label { font-family: 'Space Mono', monospace; font-size: 0.7rem; color: #f5c842; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 0.5rem; }
    .vip-btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
      background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6, #e8a020);
      color: #050810; padding: 0.75rem 1.5rem; border-radius: 8px;
      font-family: 'Orbitron', sans-serif; font-size: 0.75rem; font-weight: 900;
      text-transform: uppercase; letter-spacing: 1.5px; cursor: pointer; border: none;
      transition: all 0.3s; text-decoration: none; width: 100%; justify-content: center;
      position: relative; overflow: hidden;
      box-shadow: 0 4px 20px rgba(245,200,66,0.25);
    }
    .vip-btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent); animation: vipSweep 3s ease-in-out infinite; }
    @keyframes vipSweep { 0%{left:-100%} 60%,100%{left:150%} }
    .vip-btn:hover { box-shadow: 0 8px 30px rgba(245,200,66,0.5); transform: translateY(-2px); }
    .vip-tier { font-family: 'Space Mono', monospace; font-size: 0.7rem; letter-spacing: 3px; text-transform: uppercase; color: #f5c842; margin-bottom: 0.5rem; }

    .vip-crypto-btn { background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6, #e8a020) !important; color: #050810 !important; box-shadow: 0 4px 20px rgba(245,200,66,0.25) !important; position: relative; overflow: hidden; }
    .vip-crypto-btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent); animation: vipSweep 3s ease-in-out infinite; }
    .vip-crypto-btn:hover { background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6, #e8a020) !important; box-shadow: 0 8px 30px rgba(245,200,66,0.5) !important; transform: translateY(-2px); }

    /* ── Badge fondateur sur la card index ── */
    .fondateur-strip {
      background: linear-gradient(135deg, rgba(200,150,12,0.12), rgba(245,200,66,0.06));
      border: 1px solid rgba(245,200,66,0.3);
      border-radius: 10px;
      padding: 0.65rem 0.9rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
    }
    .fondateur-strip-left {
      font-family: 'Orbitron', sans-serif;
      font-size: 0.58rem;
      font-weight: 900;
      letter-spacing: 1.5px;
      background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .fondateur-strip-right {
      font-family: 'Space Mono', monospace;
      font-size: 0.62rem;
      color: rgba(245,200,66,0.5);
      white-space: nowrap;
    }
    .fondateur-strip-jauge {
      height: 4px;
      background: rgba(245,200,66,0.1);
      border-radius: 2px;
      margin-top: 0.4rem;
      overflow: hidden;
    }
    .fondateur-strip-fill {
      height: 100%;
      background: linear-gradient(90deg, #c8960c, #f5c842);
      border-radius: 2px;
      animation: jaugePulse 2s ease-in-out infinite alternate;
    }
    @keyframes jaugePulse { from{opacity:0.7} to{opacity:1} }
    .fondateur-complet {
      background: rgba(245,200,66,0.04);
      border: 1px solid rgba(245,200,66,0.1);
      border-radius: 10px;
      padding: 0.5rem 0.9rem;
      margin-bottom: 1rem;
      font-family: 'Space Mono', monospace;
      font-size: 0.58rem;
      color: rgba(245,200,66,0.3);
      text-align: center;
      letter-spacing: 1px;
    }
    /* REVIEWS */
    #reviews { background: var(--bg-dark); }
    .reviews-grid { max-width: 1200px; margin: 3rem auto 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .review-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 2rem; }
    .review-stars { color: #fbbf24; font-size: 1rem; margin-bottom: 1rem; letter-spacing: 2px; }
    .review-text { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7; margin-bottom: 1.5rem; font-style: italic; }
    .review-author { display: flex; align-items: center; gap: 0.75rem; }
    .review-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, var(--neon-green), var(--neon-blue)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-dark); }
    .review-name { font-weight: 600; font-size: 0.95rem; }
    .review-sub { font-size: 0.8rem; color: var(--text-muted); }

    /* STAKE */
    #stake { background: linear-gradient(180deg, var(--bg-dark), #0d1220); }
    .stake-banner { max-width: 1000px; margin: 3rem auto 0; background: linear-gradient(135deg, #1a1f35 0%, #0f1628 100%); border: 1px solid rgba(0,212,255,0.15); border-radius: 24px; padding: 3rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; position: relative; overflow: hidden; }
    .stake-content h3 { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; font-weight: 700; margin-bottom: 1rem; }
    .stake-content h3 .blue { color: var(--neon-blue); }
    .stake-content p { color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.7; }
    .stake-perks { list-style: none; margin-bottom: 2rem; }
    .stake-perks li { padding: 0.4rem 0; color: var(--text-secondary); display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; }
    .stake-perks li::before { content: '⚡'; }
    .btn-stake { background: linear-gradient(135deg, var(--neon-blue), #0099cc); color: white; padding: 0.9rem 2rem; border-radius: 8px; font-family: 'Rajdhani', sans-serif; font-size: 1.05rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; }
    .btn-stake:hover { box-shadow: var(--glow-blue); transform: translateY(-2px); }
    .stake-offer { padding: 0.5rem 0; text-align: left; margin-top: 1rem; }
    .stake-offer strong { color: var(--neon-blue); font-family: 'Orbitron', sans-serif; font-size: 0.9rem; }
    .stake-visual { text-align: center; }
    .stake-visual img { width: 100%; max-width: 400px; border-radius: 16px; }

    /* FOOTER */
    .footer-cta { background: linear-gradient(135deg, rgba(255,45,120,0.08), rgba(0,212,255,0.05)); border-top: 1px solid rgba(255,45,120,0.15); border-bottom: 1px solid rgba(255,255,255,0.03); padding: 4rem 2rem; text-align: center; }
    .footer-cta h2 { font-family: 'Orbitron', sans-serif; font-size: clamp(1.5rem, 3vw, 2.2rem); font-weight: 900; color: var(--text-primary); margin-bottom: 1rem; }
    .footer-cta h2 span { color: var(--neon-green); }
    .footer-cta p { color: var(--text-muted); max-width: 550px; margin: 0 auto 2rem; font-size: 0.95rem; line-height: 1.6; }
    .footer-cta-btns { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }
    footer { background: #050810; padding: 0; }
    .footer-main { max-width: 1200px; margin: 0 auto; padding: 4rem 2rem 3rem; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; }
    .footer-brand p { color: var(--text-muted); font-size: 0.85rem; line-height: 1.7; margin-top: 1rem; max-width: 300px; }
    .footer-brand-logo { height: 40px; width: auto; margin-bottom: 0.5rem; }
    .footer-social { display: flex; gap: 0.8rem; margin-top: 1.5rem; }
    .footer-social a { width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; text-decoration: none; transition: all 0.3s ease; }
    .footer-social a:hover { background: rgba(255,45,120,0.15); border-color: var(--neon-green); transform: translateY(-3px); color: var(--neon-green) !important; }
    .footer-col h4 { font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-primary); margin-bottom: 1.5rem; position: relative; }
    .footer-col h4::after { content: ''; position: absolute; bottom: -8px; left: 0; width: 25px; height: 2px; background: var(--neon-green); border-radius: 2px; }
    .footer-col ul { list-style: none; padding: 0; }
    .footer-col ul li { margin-bottom: 0.8rem; }
    .footer-col ul li a { color: var(--text-muted); text-decoration: none; font-size: 0.85rem; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
    .footer-col ul li a:hover { color: var(--neon-green); transform: translateX(5px); }
    .footer-bottom { max-width: 1200px; margin: 0 auto; padding: 2rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .footer-copy { color: var(--text-muted); font-size: 0.78rem; }
    .footer-copy a { color: var(--neon-green); text-decoration: none; }
    .footer-legal { display: flex; gap: 1.5rem; }
    .footer-legal a { color: var(--text-muted); text-decoration: none; font-size: 0.75rem; transition: color 0.3s; }
    .footer-legal a:hover { color: var(--text-primary); }
    .footer-disclaimer { max-width: 1200px; margin: 0 auto; padding: 0 2rem 2rem; text-align: center; color: var(--text-muted); font-size: 0.7rem; line-height: 1.6; opacity: 0.7; }
    .footer-glow { position: relative; }
    .footer-glow::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 400px; height: 1px; background: linear-gradient(90deg, transparent, var(--neon-green), var(--neon-blue), transparent); }

    /* MOBILE MENU */
    /* MOBILE MENU — Drawer moderne */
    .mobile-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:998; }
    .mobile-overlay.open { display:block; }
    .mobile-menu {
      display:block; position:fixed; top:0; right:0; bottom:0; width:280px;
      background:linear-gradient(180deg,#0d1220 0%,#0a0e17 100%);
      border-left:1px solid rgba(255,255,255,0.08);
      z-index:999; padding:0;
      transform:translateX(100%); transition:transform 0.32s cubic-bezier(0.4,0,0.2,1);
      display:flex; flex-direction:column;
      box-shadow:-20px 0 60px rgba(0,0,0,0.5);
    }
    .mobile-menu.open { transform:translateX(0); }
    .mobile-menu-header {
      padding:1.2rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.06);
      display:flex; justify-content:space-between; align-items:center;
    }
    .mobile-menu-logo { font-family:'Orbitron',sans-serif; font-size:1rem; font-weight:900; color:#fff; }
    .mobile-menu-logo span { color:var(--neon-green); }
    .mobile-menu-close { background:none; border:none; color:rgba(255,255,255,0.4); font-size:1.4rem; cursor:pointer; padding:0.2rem; line-height:1; }
    .mobile-menu-close:hover { color:#fff; }
    .mobile-menu-body { flex:1; overflow-y:auto; padding:0.8rem 0; }
    .mobile-menu-section { padding:0.5rem 1.5rem 0.3rem; font-size:0.65rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,0.3); }
    .mobile-menu a {
      display:flex; align-items:center; gap:0.9rem;
      padding:0.85rem 1.5rem; color:rgba(255,255,255,0.7);
      text-decoration:none; font-size:0.95rem; font-weight:500;
      transition:all .2s; border-radius:0;
    }
    .mobile-menu a:hover, .mobile-menu a.active { color:#fff; background:rgba(255,255,255,0.05); }
    .mobile-menu a .m-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; background:rgba(255,255,255,0.05); }
    .mobile-menu-divider { height:1px; background:rgba(255,255,255,0.06); margin:0.5rem 1.5rem; }
    .mobile-menu-cta { padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,0.06); }
    .mobile-cta-btn {
      display:block; width:100%; padding:0.9rem 1.2rem; text-align:center;
      background:linear-gradient(135deg,#ff2d78,#d6245f);
      color:#fff; font-weight:800; text-decoration:none; border-radius:12px;
      font-size:0.95rem; transition:all .2s;
      box-shadow:0 4px 20px rgba(255,45,120,0.3);
    }
    .mobile-cta-btn:hover { opacity:0.9; box-shadow:0 6px 28px rgba(255,45,120,0.5); }
    .mobile-cta-btn.danger { background:rgba(255,45,120,0.12); border:1px solid rgba(255,45,120,0.3); color:#ff2d78; box-shadow:none; }
    .mobile-badge { background:var(--neon-green); color:#000; border-radius:50px; font-size:0.65rem; font-weight:900; padding:0.1rem 0.45rem; margin-left:auto; }

    /* SCROLL ANIMATIONS */
    .fade-up { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }

    /* ── Card Tennis (horizontale, pleine largeur) ── */
    .tennis-wrapper { max-width:1400px; margin:1.5rem auto 0; padding:0 1.5rem; }
    .price-card-tennis {
      background: var(--bg-card);
      border: 1px solid rgba(0,212,106,0.35);
      border-radius: 20px;
      padding: 2.5rem 3rem;
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 3rem;
      align-items: center;
      position: relative;
      overflow: hidden;
      transition: all 0.3s;
      box-shadow: 0 0 40px rgba(0,212,106,0.06);
    }
    .price-card-tennis::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(0,212,106,0.04) 0%, transparent 60%);
      pointer-events: none;
    }
    .price-card-tennis:hover { transform: translateY(-4px); box-shadow: 0 20px 60px rgba(0,212,106,0.15); border-color: rgba(0,212,106,0.6); }
    .tennis-badge {
      position: absolute; top: 0; right: 2.5rem;
      background: linear-gradient(135deg, #00d46a, #00a852);
      color: #050810; font-family: 'Orbitron', sans-serif;
      font-size: 0.65rem; font-weight: 700; letter-spacing: 2px;
      padding: 0.35rem 1rem; border-radius: 0 0 10px 10px;
    }
    .tennis-mascot { width:120px; height:120px; flex-shrink:0; }
    .tennis-mascot video { width:120px; height:120px; object-fit:cover; border-radius:50%; border:3px solid rgba(0,212,106,0.4); box-shadow:0 0 25px rgba(0,212,106,0.3); }
    .tennis-info { }
    .tennis-tag { font-family:'Orbitron',sans-serif; font-size:0.6rem; letter-spacing:3px; color:#00d46a; text-transform:uppercase; margin-bottom:0.5rem; }
    .tennis-title { font-family:'Orbitron',sans-serif; font-size:1.5rem; font-weight:900; color:#fff; margin-bottom:0.4rem; }
    .tennis-price-row { display:flex; align-items:baseline; gap:0.4rem; margin-bottom:1rem; }
    .tennis-price { font-family:'Orbitron',sans-serif; font-size:2.8rem; font-weight:900; color:#00d46a; line-height:1; }
    .tennis-price sup { font-size:1.2rem; vertical-align:super; }
    .tennis-period { color:rgba(255,255,255,0.4); font-size:0.9rem; }
    .tennis-features { list-style:none; display:flex; flex-wrap:wrap; gap:0.5rem 1.5rem; }
    .tennis-features li { font-size:0.9rem; color:var(--text-secondary); display:flex; align-items:center; gap:0.4rem; }
    .tennis-features li::before { content:'✓'; color:#00d46a; font-weight:700; font-size:0.85rem; }
    .tennis-payment { text-align:center; flex-shrink:0; min-width:220px; }
    .tennis-label { font-size:0.75rem; color:rgba(255,255,255,0.4); letter-spacing:1px; text-transform:uppercase; margin-bottom:1rem; }
    .tennis-btn {
      display:block; width:100%; padding:1rem 1.5rem; margin-bottom:0.75rem;
      background: linear-gradient(135deg, #00d46a, #00a852);
      color:#050810; font-family:'Orbitron',sans-serif; font-size:0.82rem;
      font-weight:700; letter-spacing:1px; text-transform:uppercase;
      border:none; border-radius:10px; cursor:pointer; text-decoration:none;
      transition:all 0.3s; box-shadow:0 4px 20px rgba(0,212,106,0.25);
      text-align:center;
    }
    .tennis-btn:hover { box-shadow:0 8px 30px rgba(0,212,106,0.45); transform:translateY(-2px); }
    .tennis-btn-crypto {
      display:block; width:100%; padding:0.85rem 1.5rem;
      background: linear-gradient(135deg, #f7931a, #e2820a);
      color:#fff; font-family:'Orbitron',sans-serif; font-size:0.78rem;
      font-weight:700; letter-spacing:1.5px; text-transform:uppercase;
      border:none; border-radius:8px; cursor:pointer; text-decoration:none;
      transition:all 0.3s; box-shadow:0 4px 15px rgba(247,147,26,0.25);
      text-align:center;
    }
    .tennis-btn-crypto:hover { box-shadow:0 6px 25px rgba(247,147,26,0.45); transform:translateY(-2px); }
    .tennis-sep { font-size:0.75rem; color:rgba(255,255,255,0.2); margin:0.5rem 0; }
    .tennis-methods { font-size:0.7rem; color:rgba(255,255,255,0.3); margin-top:0.4rem; }
    @media(max-width:900px){
      .price-card-tennis { grid-template-columns:1fr; text-align:center; gap:1.5rem; padding:2rem 1.5rem; }
      .tennis-mascot { margin:0 auto; }
      .tennis-features { justify-content:center; }
      .tennis-price-row { justify-content:center; }
      .tennis-payment { width:100%; }
    }
    .fade-up.visible { opacity: 1; transform: translateY(0); }

    /* RESPONSIVE */
    @media (max-width: 900px) {
      .hamburger { display: flex; } .nav-links { display: none; }
      .hero { height: auto; min-height: 100vh; } .hero-inner { padding: 100px 1.5rem 3rem; }
      .hero-text { text-align: center; max-width: 100%; } .hero-stats { justify-content: center; gap: 1.5rem; }
      .hero-btns { justify-content: center; flex-wrap: wrap; }
      /* Mascotte + effets visibles jusqu'à 768px, cachés en dessous */
      .hero-visual { display: block; top: 70px; transform: scale(0.55); transform-origin: top right; }
      .features-grid, .reviews-grid { grid-template-columns: 1fr; }
      .pricing-grid { grid-template-columns: 1fr 1fr; max-width: 700px; margin-left: auto; margin-right: auto; }
      .price-card.featured { transform: none; } .stake-banner { grid-template-columns: 1fr; }
      .stake-visual { display: none; } .section-title { font-size: 1.8rem; }
      .footer-main { grid-template-columns: 1fr 1fr; gap: 2rem; }
    }
    @media (max-width: 768px) {
      .hero-visual { display: none; }
    }
    @media (max-width: 600px) {
      nav { padding: 0 1rem; }
      .logo img { height: 36px; }
      .hero-inner { padding: 85px 1rem 2.5rem; }
      .hero h1 { font-size: clamp(1.6rem, 7vw, 2.2rem); }
      .hero-slogan { font-size: 0.95rem; }
      .hero-desc { font-size: 0.95rem; }
      .hero-stats { flex-wrap: wrap; gap: 0.8rem; justify-content: center; }
      .stat { min-width: 120px; text-align: center; }
      .hero-btns { flex-direction: column; width: 100%; gap: 0.75rem; }
      .hero-btns a, .hero-btns button { width: 100%; text-align: center; justify-content: center; }
      section { padding: 3rem 1rem !important; }
      .section-title { font-size: 1.4rem; }
      .section-subtitle { font-size: 0.9rem; }
      .pricing-grid { max-width: 100%; padding: 0; gap: 1rem; grid-template-columns: 1fr; }
      .price-card { padding: 1.5rem 1rem; }
      .price-amount { font-size: 2.2rem; }
      .price-mascot { width: 110px; height: 110px; }
      .footer-main { grid-template-columns: 1fr; gap: 2rem; }
      .footer-bottom { flex-direction: column; text-align: center; gap: 0.75rem; }
      .footer-legal { flex-wrap: wrap; justify-content: center; gap: 1rem; }
      .footer-cta h2 { font-size: 1.3rem; }
      .footer-cta-btns { flex-direction: column; align-items: center; }
      .footer-cta-btns a { width: 100%; text-align: center; justify-content: center; }
      .mobile-menu { padding: 1.5rem 1rem; }
      .mobile-menu a { font-size: 1.05rem; padding: 0.9rem 0; }
      .stake-banner { padding: 1.5rem 1rem; border-radius: 16px; }
      .stake-content h3 { font-size: 1.3rem; }
      .step { gap: 1rem; padding: 1.5rem 0; }
      .step-number { width: 44px; height: 44px; font-size: 1rem; flex-shrink: 0; }
      .features-grid { gap: 1rem; }
      .feature-card { padding: 1.5rem; }
    }
    @media (min-width: 1600px) {
      .mascot-container { width: 1050px; height: 1200px; }
      .mascot-img { width: 1050px !important; } .hero-visual { right: -3%; top: 0; transform: none; bottom: auto; }
    }
    @media (max-width: 1200px) {
      .mascot-container { width: 700px; height: 820px; }
      .mascot-img { width: 700px !important; } .hero-visual { right: -8%; top: 78px; transform: none; bottom: auto; }
    }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0a0e17">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StratEdge">
  <link rel="apple-touch-icon" href="/assets/images/mascotte.png">
</head>
<body>

<!-- NAVBAR -->
<nav>
  <div class="nav-inner">
    <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge Pronos" class="logo-img"></a>
    <ul class="nav-links">
      <li><a href="historique.php">Historique</a></li>
      <li><a href="#stake">Stake.bet</a></li>
      <li><a href="bets.php">📊 Les Bets</a></li>
      <?php if (isLoggedIn()): ?>
        <li><a href="register.php" class="nav-cta">S'inscrire</a></li>
        <?php if (isAdmin()): ?>
          <li><a href="panel-x9k3m/index.php" style="background:rgba(255,193,7,0.15);border:1px solid rgba(255,193,7,0.3);color:#ffc107;padding:0.5rem 1.2rem;border-radius:6px;font-weight:700;">⚙️ Panel</a></li>
        <?php endif; ?>
        <li><a href="dashboard.php" class="nav-member">👤 <?= clean($membre['nom']) ?></a></li>
        <li><a href="logout.php">Déconnexion</a></li>
      <?php else: ?>
        <li><a href="login.php">Connexion</a></li>
        <li><a href="register.php" class="nav-cta">S'inscrire</a></li>
      <?php endif; ?>
    </ul>
    <button class="hamburger" onclick="toggleMenu()" aria-label="Menu" id="hamburgerBtn">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
<!-- Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeMenu()"></div>

<!-- Drawer mobile -->
<div class="mobile-menu" id="mobileMenu" role="navigation" aria-label="Menu mobile">
  <div class="mobile-menu-header">
    <span class="mobile-menu-logo"><span>Strat</span>Edge</span>
    <button class="mobile-menu-close" onclick="closeMenu()" aria-label="Fermer">✕</button>
  </div>

  <div class="mobile-menu-body">
    <div class="mobile-menu-section">Navigation</div>
    <a href="/" onclick="closeMenu()">
      <span class="m-icon">🏠</span> Accueil
    </a>
    <a href="bets.php" onclick="closeMenu()">
      <span class="m-icon">📊</span> Les Bets
    </a>
    <a href="historique.php" onclick="closeMenu()">
      <span class="m-icon">📅</span> Historique
    </a>
    <a href="#stake" onclick="closeMenu()">
      <span class="m-icon">🎯</span> Stake.bet
    </a>

    <div class="mobile-menu-divider"></div>

    <?php if (isLoggedIn()): ?>
      <div class="mobile-menu-section">Mon compte</div>
      <a href="dashboard.php" onclick="closeMenu()">
        <span class="m-icon">👤</span> Mon espace
      </a>
      <a href="chat.php" onclick="closeMenu()">
        <span class="m-icon">💬</span> Messages
      </a>
      <a href="sav.php" onclick="closeMenu()">
        <span class="m-icon">🎫</span> Support / SAV
      </a>
      <?php if (isAdmin()): ?>
        <div class="mobile-menu-divider"></div>
        <a href="panel-x9k3m/index.php" onclick="closeMenu()" style="color:#ffc107;">
          <span class="m-icon" style="background:rgba(255,193,7,0.1);">⚙️</span> Panel Admin
        </a>
      <?php endif; ?>
    <?php else: ?>
      <div class="mobile-menu-section">Compte</div>
      <a href="login.php" onclick="closeMenu()">
        <span class="m-icon">🔐</span> Connexion
      </a>
    <?php endif; ?>
  </div>

  <div class="mobile-menu-cta">
    <?php if (isLoggedIn()): ?>
      <a href="logout.php" class="mobile-cta-btn danger" onclick="closeMenu()">Déconnexion</a>
    <?php else: ?>
      <a href="register.php" class="mobile-cta-btn" onclick="closeMenu()">S'inscrire gratuitement</a>
    <?php endif; ?>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg-grid"></div>
  <div class="hero-glow"></div>
  <div class="hero-glow-2"></div>
  <div class="hero-glow-mascot"></div>
  <div class="hero-inner">
    <div class="hero-text">
      <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem; justify-content:center; max-width:100%;">
        <div class="hero-badge">🔥 SMS · Paysafecard · Crypto</div>
        <div class="hero-badge" style="background: linear-gradient(135deg, rgba(247,147,26,0.15), rgba(247,147,26,0.05)); border-color: rgba(247,147,26,0.3); color: #f7931a;">₿ Paiement par Crypto disponible</div>
      </div>
      <h1>Analyse précise.<br><span class="highlight">Data croisée.</span><br>Bets LIVE.</h1>
      <p class="hero-slogan">Ta stratégie. Notre Edge. <span>Leur défaite.</span></p>
      <p class="hero-desc">Fort de 11 ans d'expérience, StratEdge analyse, croise et compare toutes les statistiques des grands championnats. xG, Poisson, Value — tout est analysé pour ne laisser aucune chance aux bookmakers.</p>
      <div class="hero-stats">
        <div class="stat"><div class="stat-value">11+</div><div class="stat-label">Ans d'XP</div></div>
        <div class="stat"><div class="stat-value">1.93</div><div class="stat-label">Cote moyenne</div></div>
        <div class="stat"><div class="stat-value">4.8</div><div class="stat-label">/5 ★</div></div>
      </div>
      <div class="hero-btns">
        <a href="#pricing" class="btn-primary">Voir les formules ↓</a>
        <a href="#how" class="btn-outline">Comment ça marche ?</a>
      </div>
    </div>
  </div>
  <div class="hero-visual" data-version="hero-mascot-effects">
    <div class="hero-particles" id="hero-particles"></div>
    <canvas id="hero-ki-canvas"></canvas>
    <div class="hero-ground-ring hero-gr1"></div>
    <div class="hero-ground-ring hero-gr2"></div>
    <div class="hero-ground-ring hero-gr3"></div>
    <div class="hero-ground-glow"></div>
    <div class="mascot-container">
      <div class="mascot-ring"></div>
      <img class="mascot-img" src="assets/images/mascotte.png" alt="StratEdge Mascotte"
           onerror="this.style.display='none'">
    </div>
  </div>
</section>

<!-- WHY -->
<section id="why">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="section-tag fade-up">Nos avantages</div>
    <h2 class="section-title fade-up">Pourquoi <span style="color:var(--neon-green)">StratEdge</span> ?</h2>
    <p class="section-subtitle fade-up">Nous ne vendons pas du rêve, nous vendons de l'analyse et de la data.</p>
  </div>
  <div class="features-grid">
    <div class="feature-card fade-up"><div class="feature-icon">📊</div><h3>Analyse Data</h3><p>Croisement de xG, modèle de Poisson, value bets… Chaque pronostic est le fruit d'une analyse statistique rigoureuse, pas d'un feeling.</p></div>
    <div class="feature-card fade-up"><div class="feature-icon">📱</div><h3>Paiement par SMS · Crypto · Paysafecard</h3><p>Payez par SMS (Daily), carte bancaire, PayPal ou <strong>Paysafecard</strong> via StarPass. Simple, rapide, sécurisé.</p></div>
    <div class="feature-card fade-up"><div class="feature-icon">⚡</div><h3>Bets LIVE par mail &amp; Push</h3><p>Recevez les pronos LIVE directement par email et notification push. Le LIVE offre les meilleures cotes et moins d'aléatoire.</p></div>
    <div class="feature-card fade-up"><div class="feature-icon">🎯</div><h3>Cotes 1.93+</h3><p>On ne joue pas en dessous de 1.93. Des cotes élevées pour un ratio risque/gain optimal sur chaque bet.</p></div>
    <div class="feature-card fade-up"><div class="feature-icon">🏆</div><h3>11 ans d'expérience</h3><p>Des hauts, des bas, puis la maîtrise. Gestion de bankroll, contrôle des émotions, stratégies éprouvées sur la durée.</p></div>
    <div class="feature-card fade-up"><div class="feature-icon">🎁</div><h3>Cadeaux mensuels</h3><p>Chaque mois, un tirage au sort parmi les abonnés : séjour, places de parc, argent sur Stake.bet… On récompense la fidélité.</p></div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="section-tag fade-up">Process</div>
    <h2 class="section-title fade-up">Comment ça <span style="color:var(--neon-green)">marche</span> ?</h2>
    <p class="section-subtitle fade-up">En 4 étapes simples, de l'inscription au gain.</p>
  </div>
  <div class="steps-container">
    <div class="step fade-up"><div class="step-number">01</div><div class="step-content"><h3>Créez votre compte</h3><p>Inscription gratuite en 30 secondes. Juste un email et un mot de passe.</p></div></div>
    <div class="step fade-up"><div class="step-number">02</div><div class="step-content"><h3>Choisissez votre formule</h3><p>Daily (4,50€) par SMS · Week-End (10€) et Weekly (20€) par CB, PayPal, Paysafecard ou crypto.</p></div></div>
    <div class="step fade-up"><div class="step-number">03</div><div class="step-content"><h3>Accédez aux bets</h3><p>Votre espace membre est débloqué. Consultez les cards de bets avec toute l'analyse.</p></div></div>
    <div class="step fade-up"><div class="step-number">04</div><div class="step-content"><h3>Misez et gagnez</h3><p>Placez les bets sur votre bookmaker favori. Stake.bet recommandé pour les meilleures cotes.</p></div></div>
  </div>
</section>

<!-- PRICING -->
<section id="pricing">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="section-tag fade-up">Tarifs</div>
    <h2 class="section-title fade-up">Choisissez votre <span style="color:var(--neon-green)">formule</span></h2>
    <p class="section-subtitle fade-up">Sans engagement. Payez uniquement ce dont vous avez besoin.</p>
  </div>
  <div class="pricing-grid">

    <!-- DAILY -->
    <div class="price-card fade-up">
      <div class="price-tier">Plan Cool</div>
      <div class="price-name">Daily</div>
      <div class="price-mascot">
        <video autoplay loop muted playsinline>
          <source src="assets/images/DOIGT.mp4" type="video/mp4">
        </video>
      </div>
      <div class="price-amount"><span class="currency">€</span>4,50</div>
      <div class="price-period">/ prochain bet</div>
      <ul class="price-features">
        <li>Accès au prochain bet "Safe"</li>
        <li>Accès au prochain bet "Live"</li>
        <li>Idéal pour maîtriser son budget</li>
        <li>Idéal pour débuter</li>
      </ul>
      <div class="price-divider"></div>
      <div class="starpass-zone">
        <p class="starpass-label">📱 Payer maintenant</p>
        <p>SMS, appel, carte bancaire ou <strong>Paysafecard</strong> :</p>
        <?php if (isLoggedIn()): ?>
          <a href="offre-daily.php" class="starpass-btn">📱 Payer par SMS — 4,50€</a>
        <?php else: ?>
          <a href="login.php?redirect=offre-daily.php" class="starpass-btn">🔒 Se connecter pour payer</a>
        <?php endif; ?>
        <p class="starpass-info">SMS · Appel · CB · Paysafecard via StarPass</p>
        <div class="crypto-separator">— ou —</div>
        <a href="offre-daily.php#crypto" class="crypto-btn">₿ Payer en Crypto</a>
      </div>
    </div>

    <!-- WEEK-END (featured) -->
    <div class="price-card featured fade-up">
      <div class="discount-badge">-10% JEU.</div>
      <div class="price-tier">Recommandé</div>
      <div class="price-name">Week-End</div>
      <div class="price-mascot">
        <video autoplay loop muted playsinline>
          <source src="assets/images/air.mp4" type="video/mp4">
        </video>
      </div>
      <div class="price-amount"><span class="currency">€</span>10</div>
      <div class="price-period">/ souscription (ven → dim)</div>
      <ul class="price-features">
        <li>Accès bets "Safe" &amp; "Fun"</li>
        <li>Du vendredi au dimanche</li>
        <li>Bets LIVE par mail &amp; notification Push</li>
        <li>Idéal pour les matchs du week-end</li>
      </ul>
      <div class="price-divider"></div>
      <div class="starpass-zone">
        <p class="starpass-label">💳 Payer maintenant</p>
        <p>Carte bancaire, PayPal, Paysafecard ou Internet+ :</p>
        <?php if (isLoggedIn()): ?>
          <a href="offre-weekend.php" class="starpass-btn">💳 Payer — 10€</a>
        <?php else: ?>
          <a href="login.php?redirect=offre-weekend.php" class="starpass-btn">🔒 Se connecter pour payer</a>
        <?php endif; ?>
        <p class="starpass-info">CB · PayPal · Paysafecard · Internet+ via StarPass</p>
        <div class="crypto-separator">— ou —</div>
        <a href="offre-weekend.php#crypto" class="crypto-btn">₿ Payer en Crypto</a>
      </div>
    </div>

    <!-- WEEKLY -->
    <div class="price-card fade-up">
      <div class="price-tier">Pro</div>
      <div class="price-name">Weekly</div>
      <div class="price-mascot">
        <video autoplay loop muted playsinline>
          <source src="assets/images/SAM.mp4" type="video/mp4">
        </video>
      </div>
      <div class="price-amount"><span class="currency">€</span>20</div>
      <div class="price-period">/ semaine (7 jours glissants)</div>
      <ul class="price-features">
        <li>Accès bets "Safe" &amp; "Fun"</li>
        <li>Abonnement 1 semaine complète</li>
        <li>Bets LIVE par mail &amp; notification Push</li>
        <li>Tous sports : Foot, NBA, Hockey…</li>
      </ul>
      <div class="price-divider"></div>
      <div class="starpass-zone">
        <p class="starpass-label">💳 Payer maintenant</p>
        <p>Carte bancaire, PayPal, Paysafecard ou Internet+ :</p>
        <?php if (isLoggedIn()): ?>
          <a href="offre-weekly.php" class="starpass-btn">💳 Payer — 20€</a>
        <?php else: ?>
          <a href="login.php?redirect=offre-weekly.php" class="starpass-btn">🔒 Se connecter pour payer</a>
        <?php endif; ?>
        <p class="starpass-info">CB · PayPal · Paysafecard · Internet+ via StarPass</p>
        <div class="crypto-separator">— ou —</div>
        <a href="offre-weekly.php#crypto" class="crypto-btn">₿ Payer en Crypto</a>
      </div>
    </div>

    <!-- VIP MAX -->
    <div class="price-card-vip fade-up" id="vipMaxCard">
      <svg class="vip-card-svg" id="vipMaxSvg" xmlns="http://www.w3.org/2000/svg" overflow="visible"></svg>
      <div class="vip-card-inner">
        <div class="vip-tier">Accès Total</div>
        <div class="vip-logo-wrap">
          <div class="vip-crown-icon">
            <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="vg1" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%"   stop-color="#c8960c"/>
                  <stop offset="40%"  stop-color="#f5c842"/>
                  <stop offset="65%"  stop-color="#fffbe6"/>
                  <stop offset="100%" stop-color="#e8a020"/>
                </linearGradient>
                <radialGradient id="vg2" cx="50%" cy="50%" r="50%">
                  <stop offset="0%"   stop-color="#fffbe6"/>
                  <stop offset="60%"  stop-color="#f5c842"/>
                  <stop offset="100%" stop-color="#c8960c"/>
                </radialGradient>
              </defs>
              <rect x="6" y="30" width="32" height="6" rx="3" fill="url(#vg1)"/>
              <path d="M6 30 L6 18 L14 24 L22 10 L30 24 L38 18 L38 30 Z" fill="url(#vg1)"/>
              <circle cx="6"  cy="17" r="3"   fill="url(#vg1)"/>
              <circle cx="22" cy="9"  r="3.5" fill="url(#vg2)"/>
              <circle cx="38" cy="17" r="3"   fill="url(#vg1)"/>
              <polygon points="22,14 25,20 22,23 19,20" fill="url(#vg2)" opacity="0.9"/>
            </svg>
          </div>
          <div class="vip-logo-texts">
            <span class="vip-logo-vip">VIP</span>
            <span class="vip-logo-max">MAX</span>
          </div>
        </div>
        <div class="vip-sub-label">Tous sports inclus</div>
        <div class="vip-mascot">
          <video autoplay loop muted playsinline>
            <source src="assets/images/vip_max.mp4" type="video/mp4">
          </video>
        </div>
        <div class="vip-price-num"><span class="currency">€</span>50</div>
        <div class="vip-price-dur">/ mois (30 jours)</div>
        <ul class="vip-features">
          <li>Tous les bets Multi-sport</li>
          <li>Tennis ATP &amp; WTA exclusif</li>
          <li>Bets LIVE en temps réel</li>
          <li>Accès illimité 30 jours</li>
        </ul>

        <?php if ($fondateurActif): ?>
        <div class="fondateur-strip">
          <div>
            <div class="fondateur-strip-left">👑 OFFRE FONDATEUR — 15% DE RÉDUCTION</div>
            <div class="fondateur-strip-jauge">
              <div class="fondateur-strip-fill" style="width:<?= ($fondateurPlaces / 10) * 100 ?>%"></div>
            </div>
          </div>
          <div class="fondateur-strip-right">🔥 <?= $fondateurRestant ?>/10</div>
        </div>
        <?php elseif ($fondateurPlaces >= 10): ?>
        <div class="fondateur-complet">✅ OFFRE FONDATEUR COMPLÈTE · TARIF NORMAL</div>
        <?php endif; ?>

        <div class="vip-divider"></div>
        <div class="vip-zone">
          <p class="vip-label">💳 Payer maintenant</p>
          <p style="font-size:0.85rem;color:rgba(245,200,66,0.4);margin-bottom:0.75rem;">Carte bancaire, PayPal, Paysafecard ou Internet+ :</p>
          <?php if (isLoggedIn()): ?>
            <a href="offre.php?type=vip_max" class="vip-btn">💳 Payer — 50€</a>
          <?php else: ?>
            <a href="login.php?redirect=offre.php?type=vip_max" class="vip-btn">🔒 Se connecter pour payer</a>
          <?php endif; ?>
          <p style="font-size:0.75rem;color:rgba(245,200,66,0.3);margin-top:0.5rem;">CB · PayPal · Paysafecard · Internet+ via StarPass</p>
          <div class="crypto-separator" style="color:rgba(245,200,66,0.2);">— ou —</div>
          <a href="offre.php?type=vip_max#crypto" class="crypto-btn vip-crypto-btn">₿ Payer en Crypto</a>
        </div>
      </div>
    </div>

  </div><!-- /.pricing-grid -->

  <!-- ── Card Tennis Pleine Largeur ── -->
  <div class="tennis-wrapper fade-up">
    <div class="price-card-tennis">
      <div class="tennis-badge">🎾 NOUVEAU — BET TENNIS</div>

      <!-- Mascotte -->
      <div class="tennis-mascot">
        <video autoplay loop muted playsinline>
          <source src="assets/images/mascotte_tennis.mp4" type="video/mp4">
        </video>
      </div>

      <!-- Infos -->
      <div class="tennis-info">
        <div class="tennis-tag">Spécialité Tennis</div>
        <div class="tennis-title">🎾 Tennis Weekly</div>
        <div class="tennis-price-row">
          <div class="tennis-price"><sup>€</sup>15</div>
          <div class="tennis-period">/ semaine</div>
        </div>
        <ul class="tennis-features">
          <li>Analyses ATP &amp; WTA en exclusivité</li>
          <li>Bets Safe &amp; Fun Tennis</li>
          <li>7 jours d'accès complet</li>
          <li>Notifications Push &amp; Email</li>
          <li>Taux de réussite suivi en live</li>
        </ul>
      </div>

      <!-- Paiement -->
      <div class="tennis-payment">
        <div class="tennis-label">💳 Payer maintenant</div>
        <?php if (isLoggedIn()): ?>
          <a href="offre-tennis.php" class="tennis-btn">💳 Payer — 15€</a>
        <?php else: ?>
          <a href="login.php?redirect=offre-tennis.php" class="tennis-btn">🔒 Se connecter pour payer</a>
        <?php endif; ?>
        <div class="tennis-sep">— ou —</div>
        <a href="offre-tennis.php#crypto" class="tennis-btn-crypto">₿ Payer en Crypto</a>
        <div class="tennis-methods">CB · PayPal · Paysafecard · Crypto</div>
      </div>

    </div>
  </div>

</section>

<!-- REVIEWS -->
<section id="reviews">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="section-tag fade-up">Témoignages</div>
    <h2 class="section-title fade-up">Ce qu'ils en <span style="color:var(--neon-green)">disent</span></h2>
  </div>
  <div class="reviews-grid">
    <div class="review-card fade-up"><div class="review-stars">★★★★★</div><p class="review-text">"Analyse sérieuse, bets rentables. J'ai récupéré mon abonnement dès le premier pari. Je reviens chaque week-end sans hésiter."</p><div class="review-author"><div class="review-avatar">T</div><div><div class="review-name">Thomas</div><div class="review-sub">Abonné Weekly</div></div></div></div>
    <div class="review-card fade-up"><div class="review-stars">★★★★★</div><p class="review-text">"C'est simple, j'attends le weekly Stake, puis je prends mon abbo ! Les paris sont excellents, je suis dans le vert depuis que je suis leurs bets !"</p><div class="review-author"><div class="review-avatar">M</div><div><div class="review-name">Mehdi</div><div class="review-sub">Abonné Weekly</div></div></div></div>
    <div class="review-card fade-up"><div class="review-stars">★★★★★</div><p class="review-text">"Dès que j'ai mon weekly ou le monthly, je viens récupérer mon abonnement ! Y'a du savoir-faire, ça se ressent directement !"</p><div class="review-author"><div class="review-avatar">A</div><div><div class="review-name">ArToM</div><div class="review-sub">Abonné Weekly</div></div></div></div>
  </div>
</section>

<!-- STAKE -->
<section id="stake">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="section-tag fade-up">Partenaire</div>
    <h2 class="section-title fade-up">Pourquoi <span style="color:var(--neon-blue)">Stake.bet</span> ?</h2>
  </div>
  <div class="stake-banner fade-up">
    <div class="stake-content">
      <h3>Les meilleures cotes sur <span class="blue">Stake.bet</span></h3>
      <p>Stake.bet, le futur des paris en ligne. Cotes boostées, paris en direct, cashouts rapides en crypto et promos exclusives chaque semaine.</p>
      <ul class="stake-perks">
        <li>Cotes LIVE meilleures que les books ARJEL</li>
        <li>Rakeback et bonus chaque semaine/mois</li>
        <li>Retraits instantanés en crypto</li>
        <li>Inscrivez-vous via notre lien = 1 mois offert</li>
      </ul>
      <a href="http://stake.bet/?c=n26yI0vn" target="_blank" class="btn-stake">S'inscrire sur Stake.bet →</a>
      <div class="stake-offer"><strong>🎁 1 MOIS GRATUIT chez StratEdge Pronos pour une inscription via ce lien</strong></div>
    </div>
    <div class="stake-visual">
      <img src="assets/images/stake bet.jpg" alt="Stake.bet">
    </div>
  </div>
</section>

<!-- FOOTER CTA -->
<div class="footer-cta fade-up">
  <h2>Prêt à <span>battre les bookmakers</span> ?</h2>
  <p>Rejoins les parieurs qui font confiance à la data, pas au hasard. Ton premier bet gagnant est à un clic.</p>
  <div class="footer-cta-btns">
    <a href="#pricing" class="btn-primary">Voir les formules ↓</a>
    <a href="http://stake.bet/?c=n26yI0vn" target="_blank" class="btn-outline">Ouvrir un compte Stake.bet</a>
  </div>
</div>

<!-- FOOTER -->
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>

<script>
  function toggleMenu() {
    const menu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('mobileOverlay');
    const btn = document.getElementById('hamburgerBtn');
    const isOpen = menu.classList.contains('open');
    menu.classList.toggle('open');
    overlay.classList.toggle('open');
    btn.classList.toggle('open');
    document.body.style.overflow = isOpen ? '' : 'hidden';
  }
  function closeMenu() {
    document.getElementById('mobileMenu').classList.remove('open');
    document.getElementById('mobileOverlay').classList.remove('open');
    document.getElementById('hamburgerBtn').classList.remove('open');
    document.body.style.overflow = '';
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) { setTimeout(() => { entry.target.classList.add('visible'); }, i * 80); observer.unobserve(entry.target); }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
  window.addEventListener('scroll', () => {
    document.querySelector('nav').style.background = window.scrollY > 50 ? 'rgba(10,14,23,0.98)' : 'rgba(10,14,23,0.85)';
  });
</script>

<script>
/* ── Éblouissement VIP Max ── */
(function(){
  function ptRR(t,w,h,r){
    const perim=2*(w-2*r)+2*(h-2*r)+2*Math.PI*r;let d=t*perim;
    const segs=[
      {l:w-2*r,tp:'l',x:r,   y:0,   dx:1, dy:0 },
      {l:Math.PI/2*r,tp:'a', cx:w-r,cy:r,   sa:-Math.PI/2},
      {l:h-2*r,tp:'l',x:w,   y:r,   dx:0, dy:1 },
      {l:Math.PI/2*r,tp:'a', cx:w-r,cy:h-r, sa:0},
      {l:w-2*r,tp:'l',x:w-r, y:h,   dx:-1,dy:0 },
      {l:Math.PI/2*r,tp:'a', cx:r,  cy:h-r, sa:Math.PI/2},
      {l:h-2*r,tp:'l',x:0,   y:h-r, dx:0, dy:-1},
      {l:Math.PI/2*r,tp:'a', cx:r,  cy:r,   sa:Math.PI}
    ];
    for(const s of segs){
      if(d<=s.l){const f=d/s.l;
        if(s.tp==='l')return{x:s.x+s.dx*d,y:s.y+s.dy*d};
        const a=s.sa+f*Math.PI/2;return{x:s.cx+Math.cos(a)*r,y:s.cy+Math.sin(a)*r};
      }d-=s.l;
    }return{x:r,y:0};
  }

  window.addEventListener('load',function(){
    setTimeout(function(){
      const card=document.getElementById('vipMaxCard');
      const svg =document.getElementById('vipMaxSvg');
      if(!svg||!card)return;
      const rc=card.getBoundingClientRect(),W=rc.width+4,H=rc.height+4,R=22;
      svg.setAttribute('width',W);svg.setAttribute('height',H);
      svg.setAttribute('viewBox','0 0 '+W+' '+H);
      const ns='http://www.w3.org/2000/svg';
      const df=document.createElementNS(ns,'defs');

      function mkRg(id,stops){
        const g=document.createElementNS(ns,'radialGradient');
        g.setAttribute('id',id);g.setAttribute('cx','50%');g.setAttribute('cy','50%');g.setAttribute('r','50%');
        stops.forEach(function(s){const e=document.createElementNS(ns,'stop');e.setAttribute('offset',s.o);e.setAttribute('stop-color',s.c);e.setAttribute('stop-opacity',s.a);g.appendChild(e);});
        df.appendChild(g);
      }
      mkRg('vmfg',[{o:'0%',c:'#fff',a:'1'},{o:'15%',c:'#fff',a:'0.95'},{o:'30%',c:'#fffbe6',a:'0.85'},{o:'50%',c:'#ffd700',a:'0.55'},{o:'75%',c:'#f5c842',a:'0.25'},{o:'100%',c:'#f5c842',a:'0'}]);
      mkRg('vmhg',[{o:'0%',c:'#ffd700',a:'0.2'},{o:'50%',c:'#f5c842',a:'0.07'},{o:'100%',c:'#f5c842',a:'0'}]);

      function mkFlt(id,sd){
        const f=document.createElementNS(ns,'filter');
        f.setAttribute('id',id);f.setAttribute('x','-100%');f.setAttribute('y','-100%');
        f.setAttribute('width','300%');f.setAttribute('height','300%');
        const b=document.createElementNS(ns,'feGaussianBlur');b.setAttribute('stdDeviation',sd);
        f.appendChild(b);df.appendChild(f);
      }
      mkFlt('vmfbh','5');mkFlt('vmfbf','2');
      svg.appendChild(df);

      function el(tag,attrs){
        const e=document.createElementNS(ns,tag);
        Object.keys(attrs).forEach(function(k){e.setAttribute(k,attrs[k]);});
        svg.appendChild(e);return e;
      }
      const halo =el('ellipse',{rx:'38',ry:'38',fill:'url(#vmhg)',filter:'url(#vmfbh)'});
      const flare=el('ellipse',{rx:'38',ry:'38',fill:'url(#vmfg)',filter:'url(#vmfbf)'});
      const core =el('circle', {r:'5',fill:'#ffffff',opacity:'0.95'});

      var prog=0;
      (function loop(){
        prog=(prog+0.0018)%1;
        var hp=(prog-0.015+1)%1;
        var pos=ptRR(prog,W,H,R), ph=ptRR(hp,W,H,R);
        flare.setAttribute('cx',pos.x);flare.setAttribute('cy',pos.y);
        core.setAttribute('cx',pos.x);core.setAttribute('cy',pos.y);
        halo.setAttribute('cx',ph.x);halo.setAttribute('cy',ph.y);
        requestAnimationFrame(loop);
      })();
    },100);
  });
})();
</script>
<!-- Effets gate : particules + ki derrière la mascotte hero -->
<script>
(function(){
  var container = document.getElementById('hero-particles');
  if (!container) return;
  var colors = ['#ff2d78','#00d4ff','#ff6bb0','#55e8ff','#ff1a55'];
  for (var i = 0; i < 50; i++) {
    var el = document.createElement('div');
    el.className = 'hero-particle';
    var s = Math.random() * 8 + 2;
    el.style.cssText = [
      'width:' + s + 'px',
      'height:' + s + 'px',
      'left:' + (Math.random() * 100) + '%',
      'background:' + colors[Math.floor(Math.random() * colors.length)],
      'animation-duration:' + (6 + Math.random() * 12) + 's',
      'animation-delay:-' + (Math.random() * 25) + 's',
      'opacity:.75',
      'box-shadow:0 0 ' + (s*2) + 'px currentColor'
    ].join(';');
    container.appendChild(el);
  }
})();
(function(){
  var canvas = document.getElementById('hero-ki-canvas');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var W, H, CX, CY_BASE;
  function resize(){
    var p = canvas.parentElement;
    if (p) { canvas.width = p.offsetWidth; canvas.height = p.offsetHeight; }
  }
  window.addEventListener('resize', resize);
  function drawPillar(t){
    var flicker = 0.6 + Math.sin(t*8)*0.2 + Math.random()*0.15;
    var pillarH = H * 0.85;
    var grd = ctx.createLinearGradient(CX, CY_BASE, CX, CY_BASE - pillarH);
    grd.addColorStop(0, 'rgba(255,45,120,' + (0.55*flicker) + ')');
    grd.addColorStop(0.3, 'rgba(255,80,160,' + (0.35*flicker) + ')');
    grd.addColorStop(0.7, 'rgba(200,50,120,' + (0.15*flicker) + ')');
    grd.addColorStop(1, 'rgba(255,45,120,0)');
    ctx.save();
    ctx.fillStyle = grd;
    ctx.beginPath();
    ctx.ellipse(CX-18, CY_BASE-pillarH/2, 14*(0.8+Math.sin(t*6)*0.2), pillarH/2, 0, 0, Math.PI*2);
    ctx.fill();
    ctx.beginPath();
    ctx.ellipse(CX+18, CY_BASE-pillarH/2, 14*(0.8+Math.sin(t*7+1)*0.2), pillarH/2, 0, 0, Math.PI*2);
    ctx.fill();
    var grd2 = ctx.createLinearGradient(CX, CY_BASE, CX, CY_BASE - pillarH*0.9);
    grd2.addColorStop(0, 'rgba(255,255,255,' + (0.18*flicker) + ')');
    grd2.addColorStop(0.2, 'rgba(255,100,180,' + (0.22*flicker) + ')');
    grd2.addColorStop(0.7, 'rgba(255,45,120,' + (0.08*flicker) + ')');
    grd2.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = grd2;
    ctx.beginPath();
    ctx.ellipse(CX, CY_BASE - pillarH*0.45, 28*(0.85+Math.sin(t*5)*0.15), pillarH*0.45, 0, 0, Math.PI*2);
    ctx.fill();
    ctx.restore();
  }
  var RAYS = [];
  function spawnRay(){
    var angle = Math.PI*1.05 + Math.random()*Math.PI*0.9;
    var len = 80 + Math.random()*200;
    var thick = 0.6 + Math.random()*2.2;
    var spawnY = CY_BASE - (H*0.1 + Math.random()*H*0.55);
    var spawnX = CX + (Math.random()-0.5)*80;
    RAYS.push({ angle:angle, len:len, thick:thick, color: Math.random()>0.7 ? 'rgba(0,212,255,' : 'rgba(255,45,120,', life:0, maxLife: 5+Math.floor(Math.random()*9), x:spawnX, y:spawnY });
  }
  function drawRays(){
    RAYS = RAYS.filter(function(r){
      var fade = 1 - (r.life/r.maxLife);
      ctx.save();
      ctx.shadowColor = r.color + '1)'; ctx.shadowBlur = 8;
      ctx.strokeStyle = r.color + (fade*0.8).toFixed(2) + ')';
      ctx.lineWidth = r.thick; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(r.x, r.y);
      for (var i = 1; i <= 5; i++) {
        var f = i/5, jitter = (Math.random()-0.5)*14*fade;
        ctx.lineTo(r.x + Math.cos(r.angle)*r.len*f + jitter, r.y + Math.sin(r.angle)*r.len*f + jitter);
      }
      ctx.stroke(); ctx.restore();
      r.life++;
      return r.life < r.maxLife;
    });
  }
  var BOLTS = [];
  function spawnBolt(){
    var angle = Math.random()*Math.PI*2;
    var rx = 100 + Math.random()*80, ry = H*0.3;
    var bx = CX + Math.cos(angle)*rx;
    var by = (CY_BASE - H*0.35) + Math.sin(angle)*ry;
    var dir = Math.atan2(by - (CY_BASE - H*0.4), bx - CX);
    var len = 30 + Math.random()*80;
    var pts = [{x:bx,y:by}], cx2 = bx, cy2 = by;
    for (var i = 0; i < 8; i++) {
      cx2 += Math.cos(dir)*len/8 + (Math.random()-0.5)*20;
      cy2 += Math.sin(dir)*len/8 + (Math.random()-0.5)*20;
      pts.push({x:cx2, y:cy2});
    }
    BOLTS.push({ pts:pts, alpha: 0.5+Math.random()*0.5, width: 0.5+Math.random()*1.2, color: Math.random()>0.7 ? 'rgba(0,212,255,' : 'rgba(255,45,120,', life:0, maxLife: 3+Math.floor(Math.random()*7) });
  }
  function drawBolts(){
    BOLTS = BOLTS.filter(function(b){
      var fade = 1 - (b.life/b.maxLife);
      var a = (b.alpha*fade).toFixed(2);
      ctx.save();
      ctx.shadowColor = b.color + '1)'; ctx.shadowBlur = 10;
      ctx.strokeStyle = b.color + a + ')';
      ctx.lineWidth = b.width; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(b.pts[0].x, b.pts[0].y);
      for (var i = 1; i < b.pts.length; i++) ctx.lineTo(b.pts[i].x, b.pts[i].y);
      ctx.stroke();
      ctx.strokeStyle = 'rgba(255,255,255,' + (fade*0.5).toFixed(2) + ')';
      ctx.lineWidth = b.width*0.35; ctx.stroke();
      ctx.restore();
      b.life++;
      return b.life < b.maxLife;
    });
  }
  var frame = 0, t = 0;
  function loop(){
    var p = canvas.parentElement;
    var nw = p ? p.offsetWidth : 400, nh = p ? p.offsetHeight : 800;
    if (nw > 0 && nh > 0 && (canvas.width !== nw || canvas.height !== nh)) { canvas.width = nw; canvas.height = nh; }
    W = canvas.width || 400; H = canvas.height || 800;
    CX = W/2; CY_BASE = H*0.92;
    ctx.clearRect(0, 0, W, H);
    t += 0.016;
    drawPillar(t);
    if (frame % 2 === 0) spawnRay();
    if (frame % 3 === 0) spawnBolt();
    drawRays();
    drawBolts();
    frame++;
    requestAnimationFrame(loop);
  }
  window.addEventListener('load', function(){ setTimeout(function(){ resize(); loop(); }, 50); });
})();
</script>
</body>
</html>
