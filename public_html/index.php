<?php
// require_once __DIR__ . '/gate.php'; // GATE désactivé le 14/04/2026 — ouverture publique du site
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/stratedge-bet-categories.php';
require_once __DIR__ . '/includes/visiteurs-log.php';
$membre = isLoggedIn() ? getMembre() : null;
log_visite();

$cotesMoyennesAccueil = ['multisport' => null, 'tennis' => null, 'fun' => null];
try {
    $cotesMoyennesAccueil = stratedge_cotes_moyennes_par_categorie();
} catch (Throwable $e) {
    /* BDD indisponible : placeholders — */
}

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
<meta name="google-site-verification" content="IHz4FUCFXkOenciMKzmtQrh4DGornlOblhQO-E_vQTI" />
<meta name="description" content="StratEdge Pronos — Analyses paris sportifs premium (foot, tennis, NBA, NHL, MLB). Packs crédits à partir de 4,50€. Méthodologie précise, stats transparentes.">
<meta name="keywords" content="paris sportifs, pronostics, analyses foot, tennis, NBA, NHL, MLB, tipster, StratEdge">
<meta property="og:type" content="website">
<meta property="og:title" content="StratEdge Pronos — Analyses paris sportifs premium">
<meta property="og:description" content="Packs crédits à vie dès 4,50€ · Analyses Multi, Tennis, Fun · Transparence totale sur historique et ROI.">
<meta property="og:url" content="https://stratedgepronos.fr/">
<meta property="og:image" content="https://stratedgepronos.fr/assets/images/logo%20site.png">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="StratEdge Pronos — Analyses paris sportifs premium">
<link rel="canonical" href="https://stratedgepronos.fr/">

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
    html { scroll-behavior: smooth; overflow-x: hidden; }
    body { font-family: 'Rajdhani', sans-serif; background: var(--bg-dark); color: var(--text-primary); overflow-x: hidden; line-height: 1.6; max-width: 100vw; }
    body::before { content: ''; position: fixed; inset: 0; background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E"); pointer-events: none; z-index: 9999; }

    /* NAVBAR */
    nav { position: fixed; top: 0; width: 100%; z-index: 1000; background: rgba(10,14,23,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-subtle); padding: 0 2rem; overflow: hidden; }
    .nav-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 70px; }
    .logo { text-decoration: none; display: flex; align-items: center; }
    .logo-img { height: 45px; width: auto; }
    .nav-links { display: flex; gap: 1.1rem; list-style: none; align-items: center; }
    .nav-links a { color: var(--text-secondary); text-decoration: none; font-size: 0.92rem; font-weight: 500; letter-spacing: 0.5px; transition: color 0.3s; white-space:nowrap; }
    .nav-links a:hover { color: var(--neon-green); }
    .nav-cta { background: linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color: var(--bg-dark) !important; padding: 0.5rem 1.2rem; border-radius: 6px; font-weight: 700; }
    .nav-member { background: rgba(255,45,120,0.1); border: 1px solid var(--border-subtle); color: var(--neon-green) !important; padding: 0.5rem 1.2rem; border-radius: 6px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding:4px; }
    .hamburger span { width: 28px; height: 2px; background: var(--neon-green); transition: 0.35s cubic-bezier(0.4,0,0.2,1); display:block; }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* HERO */
    .hero { height: 100vh; min-height: 100vh; display: flex; position: relative; padding: 0; overflow: hidden; max-width: 100vw; width: 100%; }
    .hero-bg-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(255,45,120,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,45,120,0.03) 1px, transparent 1px); background-size: 60px 60px; mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black 30%, transparent 70%); }
    .hero-glow { position: absolute; width: 900px; height: 900px; background: radial-gradient(circle, rgba(255,45,120,0.1) 0%, transparent 70%); top: -200px; left: -200px; pointer-events: none; }
    .hero-glow-2 { position: absolute; width: 700px; height: 700px; background: radial-gradient(circle, rgba(0,212,255,0.06) 0%, transparent 70%); bottom: -300px; right: -150px; pointer-events: none; }
    .hero-glow-mascot { position: absolute; width: 800px; height: 800px; background: radial-gradient(circle, rgba(255,45,120,0.12) 0%, transparent 60%); bottom: -450px; left: 50%; transform: translateX(-50%); pointer-events: none; }
    .hero-inner { max-width: 1440px; width: 100%; margin: 0 auto; position: relative; z-index: 5; padding: 0 5rem; padding-top: 90px; padding-bottom: 3rem; display: flex; flex-direction: column; justify-content: center; }
    .hero-text { max-width: 700px; position: relative; z-index: 10; }
    .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,45,120,0.08); border: 1px solid rgba(255,45,120,0.25); color: var(--neon-green); padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; animation: pulse-badge 2s ease-in-out infinite; }
    @keyframes pulse-badge { 0%, 100% { box-shadow: 0 0 0 0 rgba(255,45,120,0.25); } 50% { box-shadow: 0 0 0 8px rgba(255,45,120,0); } }
    .hero h1 { font-family: 'Orbitron', sans-serif; font-size: clamp(2.8rem, 5vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.5rem; }
    .hero h1 .highlight { background: linear-gradient(135deg, var(--neon-green), var(--neon-blue)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .hero-slogan { font-family: 'Rajdhani', sans-serif; font-size: clamp(1.3rem, 2.2vw, 1.8rem); font-weight: 600; letter-spacing: 3px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 1.5rem; border-left: 3px solid var(--neon-green); padding-left: 1rem; }
    .hero-slogan span { color: var(--neon-green); font-weight: 700; }
    .hero-desc { font-size: 1.25rem; color: var(--text-secondary); margin-bottom: 2rem; max-width: 580px; line-height: 1.7; }
    .hero-stats { display: flex; gap: 3rem; margin-bottom: 2rem; align-items: flex-start; }
    .stat { text-align: center; }
    .stat-value { font-family: 'Orbitron', sans-serif; font-size: 2.5rem; font-weight: 900; color: var(--neon-green); }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
    .stat-cotes-hero {
      text-align: left;
      min-width: min(100%, 220px);
      padding: 0.35rem 0.5rem 0;
      border-left: 2px solid rgba(255,45,120,0.35);
      padding-left: 1rem;
    }
    .stat-cotes-hero .stat-label { text-align: left; margin-bottom: 0.5rem; line-height: 1.3; }
    .stat-cotes-rows {
      display: grid;
      grid-template-columns: max-content minmax(3.5em, max-content);
      column-gap: 1rem;
      row-gap: 0.35rem;
      align-items: baseline;
      width: max-content;
      max-width: 100%;
    }
    .stat-cote-line { display: contents; }
    .stat-cote-name {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: none;
      letter-spacing: 0;
    }
    .stat-cote-val {
      font-family: 'Orbitron', sans-serif;
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--neon-green);
      line-height: 1;
      text-align: right;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum" 1;
    }
    .hero-btns { display: flex; gap: 1rem; }
    /* Mascotte responsive : largeur max 1100px, fixée en bas du block */
    .hero-visual { position: absolute; bottom: 0; top: auto; right: 2%; left: auto; transform-origin: bottom right; z-index: 2; pointer-events: none; width: min(1100px, min(42vw, 80vh)); max-width: 100%; max-height: 100%; display: flex; align-items: flex-end; }
    .mascot-container { position: relative; width: 100%; height: auto; aspect-ratio: 900/1050; max-height: min(85vh, 100%); }
    .mascot-ring { position: absolute; top: -6%; left: -6%; right: -6%; bottom: -6%; border-radius: 50%; border: 2px solid rgba(255,45,120,0.2); animation: ring-rotate 20s linear infinite; }
    .mascot-ring::before { content: ''; position: absolute; top: -7px; left: 50%; width: 14px; height: 14px; background: var(--neon-green); border-radius: 50%; box-shadow: var(--glow-green); }
    @keyframes ring-rotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .mascot-img { position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; height: auto; max-width: 100%; filter: drop-shadow(0 0 80px rgba(255,45,120,0.35)); animation: hero-mascot-eyes 3.2s ease-in-out infinite; }
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
    .feature-card--giveaway { padding-top: 2.35rem; }
    .feature-coming-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 2;
      font-family: 'Orbitron', sans-serif;
      font-size: 0.55rem;
      font-weight: 800;
      letter-spacing: 1.1px;
      text-transform: uppercase;
      color: #fff;
      background: linear-gradient(135deg, #a855f7, #ff2d78);
      padding: 0.4rem 0.7rem;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.2);
      box-shadow: 0 0 0 0 rgba(168,85,247,0.55);
      animation: featureSeptPulse 2.2s ease-in-out infinite;
    }
    @keyframes featureSeptPulse {
      0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255,45,120,0.45); }
      50% { transform: scale(1.06); box-shadow: 0 0 18px 6px rgba(168,85,247,0.4); }
    }

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
    .giveaway-badge { margin-top: 0.7rem; padding: 0.55rem 0.8rem; border-radius: 10px; border: 1px solid transparent; position: relative; text-align: center; animation: giveawayPulse 3s ease-in-out infinite; background: linear-gradient(135deg, #111827, #111827) padding-box, linear-gradient(135deg, #ff2d78, #a855f7, #00d4ff) border-box; }
    .price-card-vip .giveaway-badge { background: linear-gradient(160deg, #111208, #0d1220, #100e05) padding-box, linear-gradient(135deg, #f5c842, #e8a020, #c8960c) border-box; }
    .giveaway-badge .gw-emoji { position: relative; z-index: 2; }
    .giveaway-badge .gw-txt { font-family: 'Orbitron', sans-serif; font-size: 0.7rem; font-weight: 700; letter-spacing: 1.5px; background: linear-gradient(135deg, #ff2d78, #a855f7, #00d4ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 0 8px rgba(255,45,120,0.3)); }
    .price-card-vip .giveaway-badge .gw-txt { background: linear-gradient(135deg, #f5c842, #fffbe6, #e8a020); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 0 8px rgba(245,200,66,0.3)); }
    .giveaway-badge .gw-main { display: flex; align-items: center; justify-content: center; gap: 0.4rem; position: relative; z-index: 2; }
    .giveaway-badge .gw-date { font-family: 'Space Mono', monospace; font-size: 0.6rem; color: var(--text-muted); margin-top: 0.2rem; letter-spacing: 1px; position: relative; z-index: 2; }
    @keyframes giveawayPulse { 0%,100% { box-shadow: 0 0 8px rgba(255,45,120,0.06), 0 0 8px rgba(0,212,255,0.06); } 50% { box-shadow: 0 0 18px rgba(255,45,120,0.12), 0 0 18px rgba(0,212,255,0.12); } }
    .price-card-vip .giveaway-badge { animation-name: giveawayPulseGold; }
    @keyframes giveawayPulseGold { 0%,100% { box-shadow: 0 0 8px rgba(245,200,66,0.06); } 50% { box-shadow: 0 0 18px rgba(245,200,66,0.15); } }
    .stake-wrap { margin-top: 0.6rem; text-align: center; }
    .stake-sep { font-family: 'Space Mono', monospace; font-size: 0.6rem; letter-spacing: 2px; color: var(--text-muted); margin-bottom: 0.4rem; text-transform: uppercase; }
    .stake-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 0.95rem 1.2rem; background: linear-gradient(135deg, #00d4ff, #0089ff); color: #fff; font-family: 'Orbitron', sans-serif; font-size: 0.76rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; border: 1px solid rgba(0,212,255,0.35); border-radius: 10px; cursor: pointer; text-decoration: none; transition: all 0.25s; box-shadow: 0 6px 18px rgba(0,166,255,0.22); }
    .stake-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,166,255,0.38); }
    .stake-offer { display: flex; align-items: center; justify-content: center; gap: 0.35rem; flex-wrap: wrap; font-family: 'Rajdhani', sans-serif; font-size: 0.78rem; font-weight: 600; color: rgba(0,212,255,0.85); margin-top: 0.4rem; }
    .stake-offer .vip-mini { display: inline-flex; align-items: center; gap: 3px; background: linear-gradient(135deg, rgba(200,150,12,0.15), rgba(245,200,66,0.08)); border: 1px solid rgba(245,200,66,0.3); border-radius: 5px; padding: 1px 6px; vertical-align: middle; }
    .stake-offer .vip-mini svg { width: 14px; height: 14px; flex-shrink: 0; }
    .stake-offer .vip-mini-txt { font-family: 'Orbitron', sans-serif; font-size: 0.55rem; font-weight: 900; letter-spacing: 0.5px; background: linear-gradient(135deg, #c8960c, #f5c842, #fffbe6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1; }
    .stake-offer .vip-mini-vip { font-size: 0.6rem; }
    .stake-offer .vip-mini-max { font-size: 0.5rem; }
    .fun-supplement { color: #ff2d78; font-size: 0.85em; font-weight: 700; text-shadow: 0 0 8px rgba(255,45,120,0.5); animation: funPulse 2s ease-in-out infinite; display: inline-block; }
    @keyframes funPulse { 0%,100% { opacity: 0.75; text-shadow: 0 0 6px rgba(255,45,120,0.3); } 50% { opacity: 1; text-shadow: 0 0 14px rgba(255,45,120,0.7), 0 0 25px rgba(255,45,120,0.3); } }
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
    .vip-card-inner{
      box-shadow:
        0 0 8px rgba(245,200,66,0.15),
        0 0 25px rgba(245,200,66,0.06),
        inset 0 0 15px rgba(245,200,66,0.03);
    }
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
    .btn-stake {
      background: linear-gradient(135deg, #00d4ff, #008fff 55%, #00d46a);
      color: #fff;
      padding: 0.95rem 2.2rem;
      border-radius: 10px;
      border: 1px solid rgba(0,212,255,0.35);
      font-family: 'Orbitron', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.4px;
      text-decoration: none;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 8px 24px rgba(0,166,255,0.28);
      position: relative;
      overflow: hidden;
    }
    .btn-stake::before {
      content: '';
      position: absolute;
      top: -140%;
      left: -20%;
      width: 40%;
      height: 300%;
      background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.34), rgba(255,255,255,0));
      transform: rotate(24deg);
      transition: left 0.45s ease;
      pointer-events: none;
    }
    .btn-stake:hover {
      box-shadow: 0 12px 32px rgba(0,166,255,0.45);
      transform: translateY(-2px);
    }
    .btn-stake:hover::before { left: 118%; }
    @keyframes stakePulse {
      0%, 100% { box-shadow: 0 8px 24px rgba(0,166,255,0.28); }
      50% { box-shadow: 0 12px 34px rgba(0,166,255,0.46); }
    }
    @media (hover:hover) and (pointer:fine) and (min-width:901px) {
      .btn-stake,
      .tennis-btn-stake {
        animation: stakePulse 2.4s ease-in-out infinite;
      }
      .btn-stake:hover,
      .tennis-btn-stake:hover { animation-play-state: paused; }
    }
    @media (prefers-reduced-motion: reduce) {
      .btn-stake,
      .tennis-btn-stake { animation: none !important; }
      .offer-gw-shimmer { animation: none !important; }
      .offer-gw-banner:hover { transform: none; }
    }
    #stake .stake-offer { padding: 0.5rem 0; text-align: left; margin-top: 1rem; }
    #stake .stake-offer strong { color: var(--neon-blue); font-family: 'Orbitron', sans-serif; font-size: 0.9rem; }
    .stake-visual { text-align: center; }
    .stake-visual img { width: 100%; max-width: 400px; border-radius: 16px; }

    /* FOOTER */
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
    .tennis-stake-sep { font-size:0.72rem; color:rgba(255,255,255,0.2); margin:0.8rem 0 0.55rem; letter-spacing:1px; }
    .tennis-btn-stake {
      display:block; width:100%; padding:0.95rem 1.2rem;
      background:linear-gradient(135deg,#00d4ff,#0089ff);
      color:#fff; font-family:'Orbitron',sans-serif; font-size:0.76rem;
      font-weight:700; letter-spacing:1.2px; text-transform:uppercase;
      border:1px solid rgba(0,212,255,0.35); border-radius:10px; text-decoration:none;
      transition:all 0.25s; box-shadow:0 6px 18px rgba(0,166,255,0.22);
      text-align:center;
    }
    .tennis-btn-stake:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,166,255,0.38); }
    .tennis-stake-note { font-size:0.68rem; color:rgba(0,212,255,0.85); margin-top:0.4rem; }
    @media(max-width:900px){
      .price-card-tennis { grid-template-columns:1fr; text-align:center; gap:1.5rem; padding:2rem 1.5rem; }
      .tennis-mascot { margin:0 auto; }
      .tennis-features { justify-content:center; }
      .tennis-price-row { justify-content:center; }
      .tennis-payment { width:100%; }
    }
    @media(max-width:600px){
      .tennis-wrapper{padding:0 0.8rem;margin:1rem auto 0;}
      .price-card-tennis{padding:1.3rem 1rem;border-radius:16px;gap:1rem;}
      .tennis-mascot{width:80px;height:80px;}
      .tennis-payment{min-width:auto;}
      .tennis-btn{font-size:0.85rem;padding:0.6rem 1rem;min-height:44px;}
      .tennis-btn-crypto{font-size:0.72rem;padding:0.5rem 0.8rem;min-height:44px;}
      .tennis-btn-stake{font-size:0.68rem;padding:0.72rem 0.8rem;min-height:44px;}
    }
    .fade-up.visible { opacity: 1; transform: translateY(0); }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
      .hero-visual { width: min(700px, min(42vw, 72vh)); }
    }
    @media (max-width: 1100px) {
      .hamburger { display: flex; } .nav-links { display: none; }
    }
    @media (max-width: 900px) {
      html,body{overflow-x:hidden;}
      .hero { height: auto; min-height: auto; overflow: hidden; width:100%; }
      .hero-inner { padding: 80px 1.2rem 2.5rem; }
      .hero-text { text-align: center; max-width: 100%; }
      .hero-stats { justify-content: center; gap: 1.2rem; flex-wrap: wrap; }
      .stat-cotes-hero { text-align: center; border-left: none; padding-left: 0; margin: 0 auto; min-width: auto; max-width: 320px; }
      .stat-cotes-hero .stat-label { text-align: center; }
      .stat-cotes-rows { margin-inline: auto; }
      .hero-btns { justify-content: center; flex-wrap: wrap; }
      .hero-visual { display: none; }
      .hero-glow, .hero-glow-2, .hero-glow-mascot { display: none; }
      .hero-bg-grid { display: none; }
      .features-grid, .reviews-grid { grid-template-columns: 1fr; }
      .pricing-grid { grid-template-columns: 1fr 1fr; max-width: 700px; margin-left: auto; margin-right: auto; }
      .price-card.featured { transform: none; }
      .price-card.featured:hover { transform: translateY(-4px); }
      .stake-banner { grid-template-columns: 1fr; }
      .stake-visual { display: none; }
      .section-title { font-size: 1.6rem; }
      .footer-main { grid-template-columns: 1fr 1fr; gap: 1.5rem; }
      .stat-value { font-size: 1.8rem; }
    }
    @media (max-width: 600px) {
      nav { padding: 0 0.8rem; }
      .nav-inner{height:50px;}
      .logo img, .logo-img { height: 30px; }
      .mobile-menu{top:50px;width:100%;}
      .hero-inner { padding: 70px 0.8rem 2rem; }
      .hero h1 { font-size: clamp(1.5rem, 7vw, 2rem); }
      .hero-slogan { font-size: 0.9rem; letter-spacing: 0.8px; }
      .hero-desc { font-size: 0.9rem; }
      .hero-stats { flex-wrap: wrap; gap: 0.7rem; justify-content: center; }
      .stat { min-width: 85px; text-align: center; }
      .stat-cote-val { font-size: 1.15rem; }
      .stat-value { font-size: 1.5rem; }
      .stat-label { font-size: 0.65rem; }
      .hero-btns { flex-direction: column; width: 100%; gap: 0.7rem; }
      .hero-btns a, .hero-btns button { width: 100%; text-align: center; justify-content: center; min-height:48px; }
      .hero-badge { font-size: 0.68rem; padding: 0.3rem 0.65rem; }
      section { padding: 2.5rem 0.8rem !important; }
      .section-tag{font-size:0.65rem;}
      .section-title { font-size: 1.3rem; }
      .section-subtitle { font-size: 0.85rem; }
      .pricing-grid { max-width: 100%; padding: 0; gap: 1rem; grid-template-columns: 1fr; }
      .price-card { padding: 1.3rem 1rem; border-radius:16px; }
      .price-amount { font-size: 2rem; }
      .price-mascot { width: 100px; height: 100px; }
      .price-name{font-size:1.3rem;}
      .price-features li{font-size:0.88rem;}
      .starpass-zone{padding:0.8rem 1rem;}
      .vip-price-num { font-size: 2rem; }
      .vip-mascot { width: 100px; height: 100px; }
      .vip-logo-vip { font-size: 0.95rem; }
      .fondateur-strip { flex-direction: column; gap: 0.3rem; text-align: center; }
      .fondateur-strip-left { font-size: 0.5rem; }
      .fondateur-strip-right { font-size: 0.52rem; }
      .footer-main { grid-template-columns: 1fr; gap: 1.5rem; }
      .footer-bottom { flex-direction: column; text-align: center; gap: 0.6rem; }
      .footer-legal { flex-wrap: wrap; justify-content: center; gap: 0.8rem; font-size:0.78rem; }
      .stake-banner { padding: 1.3rem 0.8rem; border-radius: 14px; }
      .stake-content h3 { font-size: 1.2rem; }
      .stake-content p{font-size:0.88rem;}
      .btn-stake{min-height:48px;font-size:0.9rem;}
      .step { gap: 0.8rem; padding: 1.2rem 0; }
      .step-number { width: 40px; height: 40px; font-size: 0.95rem; flex-shrink: 0; }
      .step-content h4{font-size:1rem;}
      .step-content p{font-size:0.88rem;}
      .steps-container::before { left: 19px; }
      .features-grid { gap: 0.8rem; }
      .feature-card { padding: 1.2rem; border-radius:14px; }
      .feature-icon{font-size:1.8rem;}
      .feature-card h3{font-size:1.05rem;}
      .feature-card p{font-size:0.85rem;}
      .review-card { padding: 1.2rem; border-radius:14px; }
      .review-text { font-size: 0.88rem; }
      .review-avatar{width:36px;height:36px;}
      .starpass-btn { font-size: 0.85rem; padding: 0.6rem 0.9rem; min-height:44px; }
      .stake-btn { font-size: 0.7rem; padding: 10px 14px; min-height:44px; }
      .giveaway-badge .gw-main { font-size: 0.62rem; }
      .giveaway-badge .gw-date { font-size: 0.55rem; }
      .crypto-btn { font-size: 0.75rem; padding: 10px 14px; min-height:44px; }
      .stake-btn { font-size: 0.7rem; padding: 10px 14px; min-height:44px; }
      .offer-gw-n { font-size: 1.35rem; }
      .offer-gw-inner { padding: 0.6rem 0.85rem; gap: 0.55rem; }
      .offer-gw-icon { font-size: 1.2rem; }
      .discount-badge{font-size:0.6rem;}
    }
    @media (max-width: 380px) {
      .hero-inner{padding:60px 0.6rem 1.5rem;}
      .hero h1 { font-size: 1.35rem; }
      .hero-slogan { font-size: 0.8rem; letter-spacing: 0.4px; }
      .stat { min-width: 75px; }
      .stat-value { font-size: 1.3rem; }
      .stat-label{font-size:0.58rem;}
      .price-name { font-size: 1.1rem; }
      .price-amount { font-size: 1.7rem; }
      .price-card{padding:1.1rem 0.8rem;}
      .section-title { font-size: 1.1rem; }
      section{padding:2rem 0.6rem !important;}
      .btn-primary { padding: 0.75rem 1.2rem; font-size: 0.92rem; }
      .btn-outline { padding: 0.75rem 1.2rem; font-size: 0.88rem; }
      .feature-card{padding:1rem;}
      .feature-coming-badge { font-size: 0.48rem; padding: 0.32rem 0.55rem; right: 8px; top: 8px; }
      .review-card{padding:1rem;}
    }
    @media (min-width: 1600px) {
      .hero-visual { width: min(900px, min(35vw, 70vh)); }
    }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0a0e17">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StratEdge">
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
</head>
<body>

<!-- NAVBAR -->
<nav>
  <div class="nav-inner">
    <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge Pronos" class="logo-img"></a>
    <ul class="nav-links">
      <li><a href="bets.php">🔥 Bets</a></li>
      <li><a href="historique.php">📋 Historique</a></li>
      <li><a href="giveaway.php">🎁 GiveAway</a></li>
      <li><a href="prono-commu.php">⚽ Prono commu</a></li>
      <li><a href="montante-tennis.php">🎾 Montante</a></li>
      <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
          <li><a href="panel-x9k3m/index.php" style="background:rgba(255,193,7,0.15);border:1px solid rgba(255,193,7,0.3);color:#ffc107;padding:0.4rem 0.9rem;border-radius:6px;font-weight:700;">⚙️ Panel</a></li>
        <?php endif; ?>
        <li><a href="dashboard.php" class="nav-member">👤 <?= clean($membre['nom']) ?></a></li>
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
      <span class="m-icon">🔥</span> Les Bets
    </a>
    <a href="historique.php" onclick="closeMenu()">
      <span class="m-icon">📋</span> Historique
    </a>
    <a href="giveaway.php" onclick="closeMenu()">
      <span class="m-icon">🎁</span> GiveAway
    </a>
    <a href="prono-commu.php" onclick="closeMenu()">
      <span class="m-icon">⚽</span> Prono de la commu
    </a>
    <a href="montante-tennis.php" onclick="closeMenu()">
      <span class="m-icon">🎾</span> Montante Tennis
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

<!-- Mobile bottom tab bar -->
<div class="home-mob-tabs">
  <a href="/bets.php" class="hmt-lnk"><span class="hmt-ico">🔥</span> Bets</a>
  <a href="/prono-commu.php" class="hmt-lnk"><span class="hmt-ico">⚽</span> Prono</a>
  <a href="/giveaway.php" class="hmt-lnk"><span class="hmt-ico">🎁</span> GiveAway</a>
  <a href="/montante-tennis.php" class="hmt-lnk"><span class="hmt-ico">🎾</span> Montante</a>
  <?php if (isLoggedIn()): ?>
  <a href="/dashboard.php" class="hmt-lnk"><span class="hmt-ico">📊</span> Compte</a>
  <?php else: ?>
  <a href="/login.php" class="hmt-lnk"><span class="hmt-ico">🔐</span> Connexion</a>
  <?php endif; ?>
</div>
<style>
.home-mob-tabs{display:none;}
@media(max-width:900px){
  .home-mob-tabs{
    display:flex;position:fixed;bottom:0;left:0;right:0;z-index:300;
    background:rgba(5,8,16,0.97);backdrop-filter:blur(20px);
    border-top:1px solid rgba(255,45,120,0.15);
    justify-content:space-around;align-items:stretch;
    height:calc(60px + env(safe-area-inset-bottom,0px));
    padding-bottom:env(safe-area-inset-bottom,0px);
    box-shadow:0 -4px 20px rgba(0,0,0,0.5);
  }
  .home-mob-tabs .hmt-lnk{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:0.2rem;flex:1;
    padding:0.4rem 0;
    font-size:0.62rem;font-weight:700;letter-spacing:0.3px;
    color:rgba(255,255,255,0.5);text-decoration:none;
    min-height:52px;
    -webkit-tap-highlight-color:transparent;
  }
  .home-mob-tabs .hmt-lnk:hover{color:#ff2d78;}
  .home-mob-tabs .hmt-ico{font-size:1.25rem;line-height:1;}
  body{padding-bottom:calc(60px + env(safe-area-inset-bottom,0px));}
}
</style>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg-grid"></div>
  <div class="hero-glow"></div>
  <div class="hero-glow-2"></div>
  <div class="hero-glow-mascot"></div>
  <div class="hero-inner">
    <div class="hero-text">
      <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem; justify-content:center; max-width:100%; overflow:hidden;">
        <div class="hero-badge">🔥 SMS · Paysafecard · Crypto</div>
        <div class="hero-badge" style="background: linear-gradient(135deg, rgba(247,147,26,0.15), rgba(247,147,26,0.05)); border-color: rgba(247,147,26,0.3); color: #f7931a;">₿ Paiement Crypto</div>
      </div>
      <h1>Analyse précise.<br><span class="highlight">Data croisée.</span><br>Bets LIVE.</h1>
      <p class="hero-slogan">Ta stratégie. Notre Edge. <span>Leur défaite.</span></p>
      <p class="hero-desc">Fort de 11 ans d'expérience, StratEdge analyse, croise et compare toutes les statistiques des grands championnats. xG, Poisson, Value — tout est analysé pour ne laisser aucune chance aux bookmakers.</p>
      <div class="hero-stats">
        <div class="stat"><div class="stat-value">11+</div><div class="stat-label">Ans d'XP</div></div>
        <div class="stat stat-cotes-hero">
          <div class="stat-label">Cotes moyennes<br><span style="font-size:0.65rem;opacity:0.85;font-weight:500;">(historique des bets)</span></div>
          <div class="stat-cotes-rows">
            <div class="stat-cote-line"><span class="stat-cote-name">Multisports</span><span class="stat-cote-val"><?= $cotesMoyennesAccueil['multisport'] !== null ? htmlspecialchars(number_format($cotesMoyennesAccueil['multisport'], 2, '.', '')) : '—' ?></span></div>
            <div class="stat-cote-line"><span class="stat-cote-name">Tennis</span><span class="stat-cote-val"><?= $cotesMoyennesAccueil['tennis'] !== null ? htmlspecialchars(number_format($cotesMoyennesAccueil['tennis'], 2, '.', '')) : '—' ?></span></div>
            <div class="stat-cote-line"><span class="stat-cote-name">Fun</span><span class="stat-cote-val"><?= $cotesMoyennesAccueil['fun'] !== null ? htmlspecialchars(number_format($cotesMoyennesAccueil['fun'], 2, '.', '')) : '—' ?></span></div>
          </div>
        </div>
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
    <div class="feature-card fade-up"><div class="feature-icon">🎯</div><h3>Cotes moyennes par univers</h3><p>Multisports, Tennis et Fun : les moyennes affichées en page d’accueil sont calculées à partir de l’<a href="historique.php" style="color:var(--neon-green);">historique des bets</a> (même logique que la page historique).</p></div>
    <div class="feature-card fade-up"><div class="feature-icon">🏆</div><h3>11 ans d'expérience</h3><p>Des hauts, des bas, puis la maîtrise. Gestion de bankroll, contrôle des émotions, stratégies éprouvées sur la durée.</p></div>
    <div class="feature-card fade-up feature-card--giveaway">
      <span class="feature-coming-badge" title="Programme cadeaux">À partir de septembre</span>
      <div class="feature-icon">🎁</div>
      <h3>Cadeaux mensuels</h3>
      <p>Chaque mois, un tirage au sort parmi les abonnés : séjour, places de parc, argent sur Stake.bet… On récompense la fidélité.</p>
    </div>
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
    <div class="step fade-up"><div class="step-number">02</div><div class="step-content"><h3>Choisissez votre formule</h3><p>Multi dès 4,50€ (packs crédits) · Tennis 15€/sem · Fun 10€/sem — par CB ou crypto.</p></div></div>
    <div class="step fade-up"><div class="step-number">03</div><div class="step-content"><h3>Accédez aux bets</h3><p>Votre espace membre est débloqué. Consultez les cards de bets avec toute l'analyse.</p></div></div>
    <div class="step fade-up"><div class="step-number">04</div><div class="step-content"><h3>Misez et gagnez</h3><p>Placez les bets sur votre bookmaker favori. Stake.bet recommandé pour les meilleures cotes.</p></div></div>
  </div>
</section>

<!-- NOS TIPSTERS -->
<!-- ============================================================
     STRATEDGE — SECTION TIPSTERS (remplace l'ancienne section #pricing)
     Colle ce bloc dans index.php à la place de tout le contenu
     entre <section id="pricing"> et </section> (inclus)
     ============================================================ -->

<style>
/* ═══ TIPSTER CARDS ═══ */
.tipster-grid {
  max-width: 1300px; margin: 2.5rem auto 0;
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 1.3rem; align-items: stretch;
}
.tip-card {
  background: linear-gradient(165deg, #0c1018, #111827 60%, #0d1220);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 22px; overflow: hidden; position: relative;
  transition: transform .35s, box-shadow .35s, border-color .35s;
  display: flex; flex-direction: column;
}
.tip-card:hover {
  transform: translateY(-6px);
  border-color: var(--tc);
  box-shadow: 0 20px 60px -15px var(--tg);
}
.tip-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: var(--tgrad); z-index: 4;
}
.tip-card::after {
  content: ''; position: absolute; top: -80px; right: -80px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, var(--tg) 0%, transparent 70%);
  border-radius: 50%; opacity: 0; transition: opacity .4s; pointer-events: none;
}
.tip-card:hover::after { opacity: 1; }

/* Variantes couleurs */
.tip-card--multi  { --tc: #ff2d78; --tg: rgba(255,45,120,0.15); --tgrad: linear-gradient(90deg,#ff2d78,#00d4ff); }
.tip-card--tennis { --tc: #00d46a; --tg: rgba(0,212,106,0.15); --tgrad: linear-gradient(90deg,#00d46a,#00a852); }
.tip-card--fun    { --tc: #a855f7; --tg: rgba(168,85,247,0.15); --tgrad: linear-gradient(90deg,#a855f7,#ff6b2b); }
.tip-card--vip    { --tc: #f5c842; --tg: rgba(245,200,66,0.15); --tgrad: linear-gradient(90deg,#c8960c,#f5c842,#fffbe6,#e8a020); }

.tip-card--vip {
  border-color: rgba(245,200,66,0.2);
  box-shadow: 0 0 30px rgba(245,200,66,0.06);
}

/* Inner */
.tip-inner { padding: 1.8rem 1.5rem 1.4rem; display: flex; flex-direction: column; flex: 1; }
.tip-card--vip .tip-inner { padding-top: 1.5rem; }

/* Badge */
.tip-badge {
  display: inline-flex; align-items: center; gap: .4rem;
  font-family: 'Space Mono', monospace; font-size: .55rem;
  letter-spacing: 2.5px; text-transform: uppercase;
  color: var(--tc); background: color-mix(in srgb, var(--tc) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--tc) 25%, transparent);
  padding: .3rem .8rem; border-radius: 20px;
  margin-bottom: .8rem; align-self: flex-start;
}

/* Mascot */
.tip-mascot {
  width: 110px; height: 110px; margin: 0 auto 1rem;
  border-radius: 50%; overflow: hidden;
  background: rgba(255,255,255,0.03);
  border: 2px solid color-mix(in srgb, var(--tc) 40%, transparent);
  box-shadow: 0 0 25px var(--tg);
}
.tip-mascot video { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

/* Name */
.tip-name {
  font-family: 'Orbitron', sans-serif; font-size: 1.2rem; font-weight: 900;
  text-align: center; margin-bottom: .2rem; color: #fff;
}
.tip-sub {
  font-size: .78rem; color: var(--txt3, #8a9bb0);
  text-align: center; margin-bottom: .8rem;
}

/* Sport tags */
.tip-sports {
  display: flex; flex-wrap: wrap; gap: .35rem;
  justify-content: center; margin-bottom: 1rem;
}
.tip-sport-tag {
  font-size: .62rem; font-weight: 700; letter-spacing: .5px;
  padding: .2rem .5rem; border-radius: 5px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  color: var(--txt2, #b0bec9);
}

/* Features */
.tip-features {
  list-style: none; padding: 0; margin: 0 0 1rem;
  flex: 1;
}
.tip-features li {
  padding: .35rem 0; color: var(--txt2, #b0bec9); font-size: .85rem;
  display: flex; align-items: center; gap: .5rem;
}
.tip-features li::before {
  content: '✓'; color: var(--tc); font-weight: 700; flex-shrink: 0; font-size: .75rem;
}

/* Separator */
.tip-sep {
  width: 100%; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent);
  margin: .3rem 0 .8rem;
}

/* Price preview (for Tennis/VIP) */
.tip-price-row { display: flex; align-items: baseline; justify-content: center; gap: .15rem; margin-bottom: .3rem; }
.tip-price { font-family: 'Orbitron', sans-serif; font-size: 2.2rem; font-weight: 900; color: var(--tc); line-height: 1; }
.tip-price .cur { font-size: 1rem; vertical-align: super; }
.tip-period { font-size: .78rem; color: var(--txt3); text-align: center; margin-bottom: .8rem; }

/* Pricing hint (for Multisports/Fun) */
.tip-pricing-hint {
  text-align: center; font-size: .78rem; color: var(--txt3);
  margin-bottom: .8rem;
}
.tip-pricing-hint strong { color: var(--tc); font-family: 'Orbitron', sans-serif; font-size: .85rem; }

/* Buttons */
.tip-cta {
  display: block; width: 100%; padding: .85rem;
  background: linear-gradient(135deg, var(--tc), color-mix(in srgb, var(--tc) 70%, #000));
  color: #fff; border: none; border-radius: 10px;
  font-family: 'Orbitron', sans-serif; font-size: .72rem; font-weight: 700;
  letter-spacing: 1.5px; text-transform: uppercase;
  text-decoration: none; text-align: center; cursor: pointer;
  transition: all .3s; box-shadow: 0 6px 18px var(--tg);
}
.tip-cta:hover { transform: translateY(-2px); box-shadow: 0 10px 30px var(--tg); }

.tip-cta-outline {
  display: block; width: 100%; padding: .7rem;
  background: transparent; color: var(--tc);
  border: 1px solid color-mix(in srgb, var(--tc) 35%, transparent);
  border-radius: 10px;
  font-family: 'Orbitron', sans-serif; font-size: .65rem; font-weight: 700;
  letter-spacing: 1.5px; text-transform: uppercase;
  text-decoration: none; text-align: center; cursor: pointer;
  transition: all .3s; margin-top: .5rem;
}
.tip-cta-outline:hover {
  background: color-mix(in srgb, var(--tc) 8%, transparent);
  border-color: var(--tc);
}

/* Stake mini block */
.tip-stake {
  margin-top: .7rem; text-align: center;
}
.tip-stake-label {
  font-family: 'Space Mono', monospace; font-size: .55rem;
  letter-spacing: 2px; color: var(--txt3, #8a9bb0);
  text-transform: uppercase; margin-bottom: .35rem;
}
.tip-stake-btn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  width: 100%; padding: .7rem .8rem;
  background: linear-gradient(135deg, #00d4ff, #0089ff);
  color: #fff; font-family: 'Orbitron', sans-serif; font-size: .62rem; font-weight: 700;
  letter-spacing: 1px; text-transform: uppercase;
  border: 1px solid rgba(0,212,255,0.35); border-radius: 8px;
  cursor: pointer; text-decoration: none; transition: all .25s;
  box-shadow: 0 4px 14px rgba(0,166,255,0.2);
}
.tip-stake-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,166,255,0.35); }
.tip-stake-note {
  display: flex; align-items: center; justify-content: center; gap: .3rem;
  font-size: .68rem; color: rgba(0,212,255,0.7); margin-top: .3rem;
}

/* GiveAway badge (compact) */
.tip-gw {
  margin-top: .6rem; padding: .45rem .65rem; border-radius: 8px;
  border: 1px solid transparent; text-align: center;
  background: linear-gradient(135deg, #111827, #111827) padding-box,
              linear-gradient(135deg, #ff2d78, #a855f7, #00d4ff) border-box;
  animation: giveawayPulse 3s ease-in-out infinite;
}
.tip-card--vip .tip-gw {
  background: linear-gradient(160deg, #111208, #0d1220, #100e05) padding-box,
              linear-gradient(135deg, #f5c842, #e8a020, #c8960c) border-box;
  animation-name: giveawayPulseGold;
}
.tip-gw-txt {
  font-family: 'Orbitron', sans-serif; font-size: .6rem; font-weight: 700; letter-spacing: 1px;
  background: linear-gradient(135deg, #ff2d78, #a855f7, #00d4ff);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.tip-card--vip .tip-gw-txt {
  background: linear-gradient(135deg, #f5c842, #fffbe6, #e8a020);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}

/* Commu free badge */
.tip-free-badge {
  margin-top: .5rem; padding: .5rem .7rem; border-radius: 8px;
  background: rgba(0,200,100,0.06);
  border: 1px solid rgba(0,200,100,0.2);
  text-align: center;
}
.tip-free-badge-txt {
  font-family: 'Orbitron', sans-serif; font-size: .58rem; font-weight: 700;
  letter-spacing: 1px; color: #00c864;
}
.tip-free-badge-sub {
  font-size: .68rem; color: var(--txt3); margin-top: .15rem;
}

/* VIP crown inline */
.tip-vip-crown { display: inline-block; width: 14px; height: 14px; vertical-align: middle; }

/* ═══ RESPONSIVE ═══ */
@media (max-width: 1100px) {
  .tipster-grid { grid-template-columns: repeat(2, 1fr); max-width: 800px; }
}
@media (max-width: 600px) {
  .tipster-grid { grid-template-columns: 1fr; max-width: 420px; }
  .tip-inner { padding: 1.4rem 1.2rem 1.2rem; }
  .tip-mascot { width: 90px; height: 90px; }
  .tip-name { font-size: 1.05rem; }
}
</style>

<section id="pricing">
  <div style="max-width:1200px; margin:0 auto;">
    <div class="section-tag fade-up">Nos Tipsters</div>
    <h2 class="section-title fade-up">Choisis ton <span style="color:var(--neon-green)">Tipster</span></h2>
    <p class="section-subtitle fade-up">3 spécialistes · chacun son style · sans engagement.</p>
  </div>

  <div class="tipster-grid">

    <!-- ══════ TIPSTER MULTISPORTS ══════ -->
    <div class="tip-card tip-card--multi fade-up">
      <div class="tip-inner">
        <div class="tip-badge">🏆 Tipster principal</div>
        <div class="tip-mascot">
          <video autoplay loop muted playsinline>
            <source src="assets/images/DOIGT.mp4" type="video/mp4">
          </video>
        </div>
        <div class="tip-name">Multisports</div>
        <div class="tip-sub">Safe · Fun · LIVE · Montante</div>

        <div class="tip-sports">
          <span class="tip-sport-tag">⚽ Foot</span>
          <span class="tip-sport-tag">🏀 NBA</span>
          <span class="tip-sport-tag">🏒 NHL</span>
          <span class="tip-sport-tag">⚾ MLB</span>
                  </div>

        <ul class="tip-features">
          <li>Bets Safe &amp; Fun quotidiens</li>
          <li>Bets LIVE par mail &amp; Push</li>
          <li>1 montante par mois</li>
          <li>Analyses data &amp; xG</li>
        </ul>

        <div class="tip-sep"></div>

        <div class="tip-pricing-hint">à partir de <strong>4,50€</strong></div>

        <a href="/offres-multisports.php" class="tip-cta">📊 Voir les offres</a>

        <div class="tip-gw">
          <span>🎁</span> <span class="tip-gw-txt">Éligible au GiveAway mensuel</span>
        </div>

        <div class="tip-free-badge">
          <div class="tip-free-badge-txt">🆓 3 paris gratuits / semaine</div>
          <div class="tip-free-badge-sub">« Pari de la commu » · sur simple inscription</div>
        </div>

        <div class="tip-stake">
          <div class="tip-stake-label">Bonus Partenaire</div>
          <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎰 S'inscrire sur Stake</a>
          <div class="tip-stake-note">1 mois <svg class="tip-vip-crown" viewBox="0 0 44 44" fill="none"><defs><linearGradient id="tvc1" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c8960c"/><stop offset="40%" stop-color="#f5c842"/><stop offset="65%" stop-color="#fffbe6"/><stop offset="100%" stop-color="#e8a020"/></linearGradient></defs><rect x="6" y="30" width="32" height="6" rx="3" fill="url(#tvc1)"/><path d="M6 30 L6 18 L14 24 L22 10 L30 24 L38 18 L38 30 Z" fill="url(#tvc1)"/><circle cx="6" cy="17" r="3" fill="url(#tvc1)"/><circle cx="22" cy="9" r="3.5" fill="url(#tvc1)"/><circle cx="38" cy="17" r="3" fill="url(#tvc1)"/></svg> <span style="font-family:Orbitron,sans-serif;font-size:.5rem;font-weight:900;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</span> offert</div>
        </div>
      </div>
    </div>

    <!-- ══════ TIPSTER TENNIS ══════ -->
    <div class="tip-card tip-card--tennis fade-up">
      <div class="tip-inner">
        <div class="tip-badge">🎾 Spécialiste</div>
        <div class="tip-mascot">
          <video autoplay loop muted playsinline>
            <source src="assets/images/mascotte_tennis.mp4" type="video/mp4">
          </video>
        </div>
        <div class="tip-name">Tennis</div>
        <div class="tip-sub">ATP · WTA · Grand Chelem</div>

        <div class="tip-sports">
          <span class="tip-sport-tag">🎾 ATP</span>
          <span class="tip-sport-tag">🎾 WTA</span>
        </div>

        <ul class="tip-features">
          <li>Analyses ATP &amp; WTA exclusives</li>
          <li>Bets Safe &amp; Fun Tennis</li>
          <li>7 jours d'accès complet</li>
          <li>Notifications Push &amp; Email</li>
        </ul>

        <div class="tip-sep"></div>

        <div class="tip-price-row">
          <div class="tip-price"><span class="cur">€</span>15</div>
        </div>
        <div class="tip-period">/ semaine (7 jours)</div>

        <?php if (isLoggedIn()): ?>
          <a href="offre-tennis.php" class="tip-cta">💳 Payer — 15€</a>
        <?php else: ?>
          <a href="offre-tennis.php" class="tip-cta">🎾 Voir l'offre Tennis →</a>
        <?php endif; ?>
        <a href="offre-tennis.php" class="tip-cta-outline">₿ Payer en Crypto</a>

        <div class="tip-stake">
          <div class="tip-stake-label">Bonus Partenaire</div>
          <a href="https://stake.bet/?c=2bd992d384" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎁 S'inscrire sur Stake</a>
          <div class="tip-stake-note">1 mois <svg class="tip-vip-crown" viewBox="0 0 44 44" fill="none"><defs><linearGradient id="tvc4" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c8960c"/><stop offset="40%" stop-color="#f5c842"/><stop offset="65%" stop-color="#fffbe6"/><stop offset="100%" stop-color="#e8a020"/></linearGradient></defs><rect x="6" y="30" width="32" height="6" rx="3" fill="url(#tvc4)"/><path d="M6 30 L6 18 L14 24 L22 10 L30 24 L38 18 L38 30 Z" fill="url(#tvc4)"/><circle cx="6" cy="17" r="3" fill="url(#tvc4)"/><circle cx="22" cy="9" r="3.5" fill="url(#tvc4)"/><circle cx="38" cy="17" r="3" fill="url(#tvc4)"/></svg> <span style="font-family:Orbitron,sans-serif;font-size:.5rem;font-weight:900;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</span> offert</div>
        </div>
      </div>
    </div>

    <!-- ══════ TIPSTER FUN ONLY ══════ -->
    <div class="tip-card tip-card--fun fade-up">
      <div class="tip-inner">
        <div class="tip-badge">🎲 Grosses cotes</div>
        <div class="tip-mascot">
          <video autoplay loop muted playsinline>
            <source src="assets/images/mascotte-fun.mp4" type="video/mp4">
          </video>
        </div>
        <div class="tip-name">Fun Only</div>
        <div class="tip-sub">7 jours · Tous les matchs</div>

        <div class="tip-sports">
          <span class="tip-sport-tag">⚽ Foot</span>
          <span class="tip-sport-tag">🏆 Ligue des Champions</span>
        </div>

        <ul class="tip-features">
          <li>Combinés grosses cotes</li>
          <li>Tous les matchs de la semaine</li>
          <li>Bets Fun à forte value</li>
          <li>Notifications Push &amp; Email</li>
        </ul>

        <div class="tip-sep"></div>

        <div class="tip-price-row">
          <div class="tip-price"><span class="cur">€</span>10</div>
        </div>
        <div class="tip-period">/ semaine (7 jours)</div>

        <?php if (isLoggedIn()): ?>
          <a href="offre-fun.php" class="tip-cta">💳 Payer — 10€</a>
        <?php else: ?>
          <a href="offre-fun.php" class="tip-cta">🎲 Voir l'offre Fun →</a>
        <?php endif; ?>
        <a href="offre-fun.php" class="tip-cta-outline">₿ Payer en Crypto</a>

        <div class="tip-stake">
          <div class="tip-stake-label">Bonus Partenaire</div>
          <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎰 S'inscrire sur Stake</a>
          <div class="tip-stake-note">1 mois <svg class="tip-vip-crown" viewBox="0 0 44 44" fill="none"><defs><linearGradient id="tvc2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c8960c"/><stop offset="40%" stop-color="#f5c842"/><stop offset="65%" stop-color="#fffbe6"/><stop offset="100%" stop-color="#e8a020"/></linearGradient></defs><rect x="6" y="30" width="32" height="6" rx="3" fill="url(#tvc2)"/><path d="M6 30 L6 18 L14 24 L22 10 L30 24 L38 18 L38 30 Z" fill="url(#tvc2)"/><circle cx="6" cy="17" r="3" fill="url(#tvc2)"/><circle cx="22" cy="9" r="3.5" fill="url(#tvc2)"/><circle cx="38" cy="17" r="3" fill="url(#tvc2)"/></svg> <span style="font-family:Orbitron,sans-serif;font-size:.5rem;font-weight:900;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</span> offert</div>
        </div>
      </div>
    </div>

    <!-- ══════ VIP MAX ══════ -->
    <div class="tip-card tip-card--vip fade-up">
      <div class="tip-inner">
        <div class="tip-badge">👑 Accès Total</div>
        <div class="tip-mascot" style="border-color:rgba(245,200,66,0.35);box-shadow:0 0 30px rgba(245,200,66,0.2);">
          <video autoplay loop muted playsinline>
            <source src="assets/images/vip_max.mp4" type="video/mp4">
          </video>
        </div>
        <div class="tip-name" style="background:linear-gradient(135deg,#f5c842,#fffbe6,#e8a020);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</div>
        <div class="tip-sub">Tous les tipsters réunis</div>

        <div class="tip-sports">
          <span class="tip-sport-tag" style="color:#f5c842;border-color:rgba(245,200,66,0.2);">🏆 Multisports</span>
          <span class="tip-sport-tag" style="color:#f5c842;border-color:rgba(245,200,66,0.2);">🎾 Tennis</span>
          <span class="tip-sport-tag" style="color:#f5c842;border-color:rgba(245,200,66,0.2);">🎲 Fun</span>
        </div>

        <ul class="tip-features">
          <li>TOUT : Safe + Fun + LIVE + Tennis</li>
          <li>Accès aux 3 tipsters</li>
          <li>Montantes incluses</li>
          <li>30 jours illimités</li>
        </ul>

        <div class="tip-sep"></div>

        <div class="tip-price-row">
          <div class="tip-price" style="color:#f5c842;"><span class="cur">€</span>50</div>
        </div>
        <div class="tip-period">/ mois (30 jours)</div>

        <?php if (isLoggedIn()): ?>
          <a href="offre.php?type=vip_max" class="tip-cta" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810;">💳 Payer — 50€</a>
        <?php else: ?>
          <a href="offre.php?type=vip_max" class="tip-cta" style="background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020);color:#050810;">👑 Voir l'offre VIP Max →</a>
        <?php endif; ?>
        <a href="offre.php?type=vip_max#crypto" class="tip-cta-outline" style="color:#f5c842;border-color:rgba(245,200,66,0.3);">₿ Payer en Crypto</a>

        <div class="tip-gw">
          <span>🎁</span> <span class="tip-gw-txt">Éligible au GiveAway mensuel</span>
        </div>

        <div class="tip-stake">
          <div class="tip-stake-label">Bonus Partenaire</div>
          <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="tip-stake-btn">🎰 S'inscrire sur Stake</a>
          <div class="tip-stake-note">1 mois <svg class="tip-vip-crown" viewBox="0 0 44 44" fill="none"><defs><linearGradient id="tvc3" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c8960c"/><stop offset="40%" stop-color="#f5c842"/><stop offset="65%" stop-color="#fffbe6"/><stop offset="100%" stop-color="#e8a020"/></linearGradient></defs><rect x="6" y="30" width="32" height="6" rx="3" fill="url(#tvc3)"/><path d="M6 30 L6 18 L14 24 L22 10 L30 24 L38 18 L38 30 Z" fill="url(#tvc3)"/><circle cx="6" cy="17" r="3" fill="url(#tvc3)"/><circle cx="22" cy="9" r="3.5" fill="url(#tvc3)"/><circle cx="38" cy="17" r="3" fill="url(#tvc3)"/></svg> <span style="font-family:Orbitron,sans-serif;font-size:.5rem;font-weight:900;background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">VIP MAX</span> offert</div>
        </div>
      </div>
    </div>

  </div><!-- /.tipster-grid -->
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
      <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="btn-stake">S'inscrire sur Stake.bet →</a>
      <div class="stake-offer"><strong>🎁 1 MOIS GRATUIT chez StratEdge Pronos pour une inscription via ce lien</strong></div>
    </div>
    <div class="stake-visual">
      <img src="assets/images/stake bet.jpg" alt="Stake.bet">
    </div>
  </div>
</section>

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
/* ── StratEdge VIP Max : "Edge Flow" — flux d'énergie + particules dorées sur le contour ── */
(function(){
  window.addEventListener('load',function(){
    setTimeout(function(){
      var card=document.getElementById('vipMaxCard');
      var svg=document.getElementById('vipMaxSvg');
      if(!svg||!card)return;
      var rc=card.getBoundingClientRect(), W=rc.width+4, H=rc.height+4, R=22;
      svg.setAttribute('width',W); svg.setAttribute('height',H);
      svg.setAttribute('viewBox','0 0 '+W+' '+H);
      var ns='http://www.w3.org/2000/svg';

      var pathD='M'+R+',0 L'+(W-R)+',0 A'+R+','+R+' 0 0 1 '+W+','+R+' L'+W+','+(H-R)+' A'+R+','+R+' 0 0 1 '+(W-R)+','+H+' L'+R+','+H+' A'+R+','+R+' 0 0 1 0,'+(H-R)+' L0,'+R+' A'+R+','+R+' 0 0 1 '+R+',0 Z';

      var df=document.createElementNS(ns,'defs');
      var blurF=document.createElementNS(ns,'filter');
      blurF.setAttribute('id','vipGlow'); blurF.setAttribute('x','-60%'); blurF.setAttribute('y','-60%'); blurF.setAttribute('width','220%'); blurF.setAttribute('height','220%');
      var feG=document.createElementNS(ns,'feGaussianBlur'); feG.setAttribute('stdDeviation','4'); feG.setAttribute('result','blur');
      var feM=document.createElementNS(ns,'feMerge');
      feM.appendChild(document.createElementNS(ns,'feMergeNode')).setAttribute('in','blur');
      feM.appendChild(document.createElementNS(ns,'feMergeNode')).setAttribute('in','SourceGraphic');
      blurF.appendChild(feG); blurF.appendChild(feM); df.appendChild(blurF);
      svg.appendChild(df);

      var totalLen=0;
      try{
        var pathEl=document.createElementNS(ns,'path');
        pathEl.setAttribute('d',pathD);
        pathEl.setAttribute('fill','none');
        svg.appendChild(pathEl);
        totalLen=pathEl.getTotalLength();
        svg.removeChild(pathEl);
      }catch(e){ totalLen=2*(W+H); }

      var segLen=Math.min(140,totalLen*0.35);
      var speed1=0.42, speed2=-0.38;
      var offset1=0, offset2=totalLen*0.5;

      function addRunner(className){
        var g=document.createElementNS(ns,'g');
        var glow=document.createElementNS(ns,'path');
        glow.setAttribute('d',pathD); glow.setAttribute('fill','none');
        glow.setAttribute('stroke','#ffd700');
        glow.setAttribute('stroke-width','6');
        glow.setAttribute('stroke-linecap','round');
        glow.setAttribute('stroke-dasharray',segLen+' '+totalLen);
        glow.setAttribute('filter','url(#vipGlow)');
        glow.setAttribute('opacity','0.5');
        g.appendChild(glow);
        var stroke=document.createElementNS(ns,'path');
        stroke.setAttribute('d',pathD); stroke.setAttribute('fill','none');
        stroke.setAttribute('stroke',className==='runner1'?'#fffbe6':'#f5c842');
        stroke.setAttribute('stroke-width','2.5');
        stroke.setAttribute('stroke-linecap','round');
        stroke.setAttribute('stroke-dasharray',segLen+' '+totalLen);
        stroke.setAttribute('class',className);
        g.appendChild(stroke);
        svg.appendChild(g);
        return stroke;
      }
      var runner1=addRunner('runner1');
      var runner2=addRunner('runner2');

      var border=document.createElementNS(ns,'path');
      border.setAttribute('d',pathD);
      border.setAttribute('fill','none');
      border.setAttribute('stroke','rgba(245,200,66,0.35)');
      border.setAttribute('stroke-width','1');
      border.setAttribute('stroke-linecap','round');
      svg.insertBefore(border,svg.childNodes[1]);

      var particles=[];
      function spawnParticle(){
        var t=Math.random();
        var perim=2*(W-2*R)+2*(H-2*R)+2*Math.PI*R;
        var d=(t*perim)%perim;
        var segs=[
          {l:W-2*R,x:R,y:0,dx:1,dy:0},{l:Math.PI/2*R,cx:W-R,cy:R,sa:-Math.PI/2},
          {l:H-2*R,x:W,y:R,dx:0,dy:1},{l:Math.PI/2*R,cx:W-R,cy:H-R,sa:0},
          {l:W-2*R,x:W-R,y:H,dx:-1,dy:0},{l:Math.PI/2*R,cx:R,cy:H-R,sa:Math.PI/2},
          {l:H-2*R,x:0,y:H-R,dx:0,dy:-1},{l:Math.PI/2*R,cx:R,cy:R,sa:Math.PI}
        ];
        var x=0,y=0,nx=0,ny=0;
        for(var i=0;i<segs.length;i++){
          var s=segs[i];
          if(d<=s.l){
            var f=d/s.l;
            if(s.x!==undefined){ x=s.x+s.dx*d; y=s.y+s.dy*d; nx=s.dy; ny=-s.dx; }
            else{ var a=s.sa+f*Math.PI/2; x=s.cx+Math.cos(a)*R; y=s.cy+Math.sin(a)*R; nx=Math.cos(a); ny=Math.sin(a); }
            break;
          }
          d-=s.l;
        }
        var circle=document.createElementNS(ns,'circle');
        circle.setAttribute('r','2.5');
        circle.setAttribute('fill','#ffd700');
        circle.setAttribute('opacity','0.9');
        svg.appendChild(circle);
        particles.push({el:circle,x:x,y:y,nx:nx,ny:ny,life:1,v:2+Math.random()*3});
      }
      var lastSpawn=0;

      function loop(now){
        now=performance.now();
        var dt=Math.min(0.06,(now-(loop._t||now))/1000);
        loop._t=now;
        offset1=(offset1+speed1*60*dt)%totalLen; if(offset1<0)offset1+=totalLen;
        offset2=(offset2+speed2*60*dt)%totalLen; if(offset2<0)offset2+=totalLen;
        runner1.setAttribute('stroke-dashoffset',-offset1);
        runner2.setAttribute('stroke-dashoffset',-offset2);
        runner1.parentNode.childNodes[0].setAttribute('stroke-dashoffset',-offset1);
        runner2.parentNode.childNodes[0].setAttribute('stroke-dashoffset',-offset2);

        for(var i=particles.length-1;i>=0;i--){
          var p=particles[i];
          p.x+=p.nx*p.v; p.y+=p.ny*p.v;
          p.life-=0.018;
          p.el.setAttribute('cx',p.x); p.el.setAttribute('cy',p.y);
          p.el.setAttribute('opacity',(p.life*0.9).toFixed(2));
          if(p.life<=0){ svg.removeChild(p.el); particles.splice(i,1); }
        }
        if(now-lastSpawn>180){ spawnParticle(); lastSpawn=now; }
        requestAnimationFrame(loop);
      }
      requestAnimationFrame(loop);
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
