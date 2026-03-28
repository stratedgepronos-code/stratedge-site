<?php
// ============================================================
// STRATEDGE — Page de paiement — Design v2
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/promo.php';
require_once __DIR__ . '/giveaway-functions.php';
requireLogin();

$offres = [
    'daily' => [
        'titre'      => 'Daily',
        'subtitle'   => 'Prochain Bet',
        'emoji'      => '⚡',
        'prix'       => '4.50',
        'idd'        => '446908',
        'idp'        => '263723',
        'duree'      => 'Valable jusqu\'au prochain bet publié',
        'avantages'  => ['Accès au prochain bet Safe', 'Accès au prochain bet Live', 'Idéal pour tester', 'Sans engagement'],
        'color'      => '#ff2d78',
        'glow'       => 'rgba(255,45,120,0.18)',
        'gradient'   => 'linear-gradient(135deg,#ff2d78,#c4185a)',
        'video'      => 'assets/images/DOIGT.mp4',
        'activate'   => 'activate.php?type=daily',
        'badge'      => 'ENTRÉE',
        'tag'        => 'Tester StratEdge',
    ],
    'weekend' => [
        'titre'      => 'Week-End',
        'subtitle'   => 'Vendredi → Dimanche',
        'emoji'      => '📅',
        'prix'       => '10',
        'prix_fun'   => '20',
        /* Code StarPass distinct à 20€ (Safe + Fun) — ou variable d’env STARPASS_WEEKEND_FUN_IDD */
        'idd'        => '446904',
        'idd_with_fun' => '',
        'idp'        => '263723',
        'duree'      => 'Du vendredi 00h00 au dimanche 23h59',
        'avantages'  => [
            '<div class="avantage-safe-fun-block"><span class="av-safe-line">Accès bets « Safe »</span><br><span class="fun-supplement-pulse">Fun bets avec supplément (+10€ si option cochée)</span></div>',
            'Bets LIVE par mail &amp; Push',
            'Tous les matchs du week-end',
            'Sans engagement',
        ],
        'color'      => '#00d4ff',
        'glow'       => 'rgba(0,212,255,0.18)',
        'gradient'   => 'linear-gradient(135deg,#00d4ff,#0099cc)',
        'video'      => 'assets/images/air.mp4',
        'activate'   => 'activate.php?type=weekend',
        'badge'      => 'POPULAIRE',
        'tag'        => 'Idéal week-end',
    ],
    'weekly' => [
        'titre'      => 'Weekly',
        'subtitle'   => '7 Jours Complets',
        'emoji'      => '🏆',
        'prix'       => '20',
        'idd'        => '446905',
        'idp'        => '263723',
        'duree'      => '7 jours glissants à partir de l\'achat',
        'avantages'  => ['Accès TOUS les bets Safe & Fun', 'Bets LIVE par mail &amp; Push', 'Foot, NBA, Hockey…', 'Sans engagement'],
        'color'      => '#a855f7',
        'glow'       => 'rgba(168,85,247,0.18)',
        'gradient'   => 'linear-gradient(135deg,#a855f7,#7c3aed)',
        'video'      => 'assets/images/SAM.mp4',
        'activate'   => 'activate.php?type=weekly',
        'badge'      => 'MEILLEURE VALEUR',
        'tag'        => 'Accès illimité',
    ],
    'tennis' => [
        'titre'      => 'Tennis Weekly',
        'subtitle'   => 'Spécialité Tennis',
        'emoji'      => '🎾',
        'prix'       => '15',
        'idd'        => '446913',
        'idp'        => '263734',
        'duree'      => '7 jours glissants à partir de l\'achat',
        'avantages'  => ['Analyses ATP & WTA exclusives', 'Bets Tennis Safe & Fun', 'Bets LIVE par mail & Push', '7 jours d\'accès complet'],
        'color'      => '#00d46a',
        'glow'       => 'rgba(0,212,106,0.18)',
        'gradient'   => 'linear-gradient(135deg,#00d46a,#00a852)',
        'video'      => 'assets/images/mascotte_tennis.mp4',
        'activate'   => 'activate.php?type=tennis',
        'badge'      => '🎾 TENNIS',
        'tag'        => 'Spécialité Tennis',
    ],
    'vip_max' => [
        'titre'      => 'VIP Max',
        'subtitle'   => 'Accès Total',
        'emoji'      => '👑',
        'prix'       => '50',
        'idd'        => '446906',
        'idp'        => '263723',
        'duree'      => '30 jours à partir de l\'achat',
        'avantages'  => ['Tous les bets Multi-sport', 'Tennis ATP & WTA exclusif', 'Bets LIVE & Fun bets inclus', 'Accès illimité 30 jours'],
        'color'      => '#f5c842',
        'glow'       => 'rgba(245,200,66,0.18)',
        'gradient'   => 'linear-gradient(135deg,#c8960c,#f5c842,#fffbe6,#e8a020)',
        'video'      => 'assets/images/vip_max.mp4',
        'activate'   => 'activate.php?type=vip_max',
        'badge'      => 'VIP MAX',
        'tag'        => 'Accès Total',
    ],
];

if (!isset($offres[$type])) { header('Location: /souscrire.php'); exit; }

$envWeekendFunIdd = getenv('STARPASS_WEEKEND_FUN_IDD');
if ($envWeekendFunIdd !== false && trim($envWeekendFunIdd) !== '') {
    $offres['weekend']['idd_with_fun'] = trim($envWeekendFunIdd);
}

$o      = $offres[$type];
$membre = getMembre();
$weekendFunIdd        = ($type === 'weekend') ? trim((string)($o['idd_with_fun'] ?? '')) : '';
$weekendFunStarPassOk = $weekendFunIdd !== '';
$starpassDatasInitial = $membre['id'] . ':' . $type;
$gwPtsPack   = (int)(GIVEAWAY_POINTS[$type] ?? 0);
$gwShowBadge = $gwPtsPack > 0;
/** Modificateur CSS du bandeau GiveAway (aligné sur l’accueil) */
$gwBannerMod = [
    'daily'   => 'offer-gw--daily',
    'weekend' => 'offer-gw--weekend',
    'weekly'  => 'offer-gw--weekly',
    'vip_max' => 'offer-gw--vip',
][$type] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $o['emoji'] ?> <?= $o['titre'] ?> <?= $o['prix'] ?>€ – StratEdge Pronos</title>
  <link rel="icon" type="image/png" href="/assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:       #060810;
      --bg2:      #0c1018;
      --bg3:      #111827;
      --color:    <?= $o['color'] ?>;
      --glow:     <?= $o['glow'] ?>;
      --grad:     <?= $o['gradient'] ?>;
      --rose:     #ff2d78;
      --cyan:     #00d4ff;
      --txt:      #f0f4f8;
      --txt2:     #b0bec9;
      --txt3:     #6b7a90;
      --border:   rgba(255,255,255,0.07);
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family:'Rajdhani',sans-serif;
      background:var(--bg);
      color:var(--txt);
      min-height:100vh;
      overflow-x:hidden;
    }

    /* ── Fond animé ── */
    .bg-orbs {
      position:fixed; inset:0; pointer-events:none; z-index:-1;
      overflow:hidden;
    }
    .orb {
      position:absolute; border-radius:50%;
      filter:blur(80px); opacity:0;
      animation:orbFloat 8s ease-in-out infinite;
    }
    .orb-1 { width:600px; height:600px; background:var(--glow); top:-150px; right:-150px; animation-delay:0s; }
    .orb-2 { width:400px; height:400px; background:rgba(255,45,120,0.1); bottom:-100px; left:-100px; animation-delay:3s; }
    .orb-3 { width:300px; height:300px; background:var(--glow); top:40%; left:20%; animation-delay:6s; }
    @keyframes orbFloat {
      0%   { opacity:0; transform:scale(0.9) translateY(20px); }
      30%  { opacity:1; }
      70%  { opacity:1; }
      100% { opacity:0; transform:scale(1.1) translateY(-20px); }
    }

    /* Grille de fond */
    body::before {
      content:'';
      position:fixed; inset:0; z-index:-1;
      background-image:
        linear-gradient(rgba(255,45,120,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,45,120,0.03) 1px, transparent 1px);
      background-size:50px 50px;
      pointer-events:none;
    }

    /* ── NAV ── */
    nav {
      position:sticky; top:0; z-index:100;
      background:rgba(6,8,16,0.85);
      backdrop-filter:blur(24px);
      border-bottom:1px solid var(--border);
      padding:0 2.35rem 0 1.15rem;
    }
    .nav-inner {
      width: 100%;
      max-width: min(1720px, calc(100vw - 2.5rem));
      margin: 0 auto;
      display:flex; align-items:center; justify-content:space-between;
      height:68px;
    }
    .nav-logo img { height:42px; }
    .nav-back {
      display:flex; align-items:center; gap:0.5rem;
      color:var(--txt3); text-decoration:none;
      font-size:0.88rem; letter-spacing:0.5px;
      transition:color 0.2s;
    }
    .nav-back:hover { color:var(--txt); }
    .nav-badge {
      background:var(--grad);
      color:#fff;
      font-family:'Orbitron',sans-serif;
      font-size:0.65rem; font-weight:700;
      letter-spacing:2px; text-transform:uppercase;
      padding:0.3rem 0.9rem; border-radius:20px;
    }

    /* ── PAGE (pleine largeur utile — packs) ── */
    .page {
      width: 100%;
      max-width: min(1720px, calc(100vw - 2.5rem));
      margin: 0 auto;
      padding: 4rem clamp(1rem, 3vw, 2.5rem) 6rem;
      position: relative;
      z-index: 1;
      box-sizing: border-box;
    }

    /* ── HERO ── */
    .hero {
      text-align:center; margin-bottom:4rem;
      animation:fadeUp 0.6s ease both;
    }
    .hero-tag {
      display:inline-flex; align-items:center; gap:0.5rem;
      font-family:'Space Mono',monospace; font-size:0.68rem;
      letter-spacing:3px; text-transform:uppercase;
      color:var(--color);
      background:color-mix(in srgb, var(--color) 10%, transparent);
      border:1px solid color-mix(in srgb, var(--color) 30%, transparent);
      padding:0.4rem 1.2rem; border-radius:30px;
      margin-bottom:1.5rem;
    }
    .hero-title {
      font-family:'Orbitron',sans-serif;
      font-size:clamp(2rem, 5vw, 3.2rem);
      font-weight:900; line-height:1.1;
      margin-bottom:0.75rem;
    }
    .hero-title .accent { color:var(--color); }
    .hero-subtitle { color:var(--txt3); font-size:1rem; }

    /* ── LAYOUT : carte offre fixe, colonne paiement = tout le reste ── */
    .layout {
      display: grid;
      grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
      gap: clamp(1.25rem, 2.5vw, 2.25rem);
      align-items: start;
    }

    /* ── CARTE OFFRE ── */
    .offre-card {
      background:var(--bg2);
      border:1px solid color-mix(in srgb, var(--color) 25%, transparent);
      border-radius:24px;
      overflow:hidden;
      position:relative;
      animation:fadeUp 0.7s ease 0.1s both;
    }
    /* Barre dégradée en haut */
    .offre-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:var(--grad);
    }
    .offre-card-top {
      padding:2rem;
      text-align:center;
      border-bottom:1px solid var(--border);
    }
    /* Badge */
    .offre-badge {
      display:inline-block;
      font-family:'Orbitron',sans-serif; font-size:0.6rem;
      letter-spacing:2px; font-weight:700;
      background:var(--grad); color:#fff;
      padding:0.25rem 0.8rem; border-radius:20px;
      margin-bottom:1.2rem;
      text-transform:uppercase;
    }
    /* Vidéo circulaire */
    .offre-video-wrap {
      width:110px; height:110px; margin:0 auto 1.2rem;
      border-radius:50%; overflow:hidden;
      border:2px solid color-mix(in srgb, var(--color) 40%, transparent);
      box-shadow:0 0 30px var(--glow);
      position:relative;
    }
    .offre-video-wrap video { width:100%; height:100%; object-fit:cover; }
    /* Prix */
    .offre-prix {
      font-family:'Orbitron',sans-serif; font-weight:900;
      line-height:1; margin-bottom:0.25rem;
    }
    .offre-prix .cur { font-size:1.6rem; color:var(--color); vertical-align:super; }
    .offre-prix .num {
      font-size:4.5rem; color:var(--color);
      text-shadow:0 0 30px color-mix(in srgb, var(--color) 60%, transparent);
    }
    .offre-duree { color:var(--txt3); font-size:0.82rem; margin-bottom:0; }

    @keyframes giveawaySweep { 0% { left: -100%; } 100% { left: 200%; } }

    /* Bandeau GiveAway (même bloc que l’accueil — daily / week-end / weekly / VIP) */
    .offer-gw-banner {
      display: block;
      text-decoration: none;
      color: inherit;
      margin: 1rem 0 0;
      border-radius: 14px;
      padding: 2px;
      position: relative;
      transition: transform 0.28s ease, box-shadow 0.28s ease;
    }
    .offer-gw-banner:hover {
      transform: translateY(-3px);
      box-shadow: 0 14px 36px rgba(255, 45, 120, 0.18), 0 0 24px rgba(0, 212, 255, 0.08);
    }
    .offer-gw--daily { background: linear-gradient(135deg, #ff2d78, #a855f7, #00d4ff); }
    .offer-gw--weekend { background: linear-gradient(135deg, #00d4ff, #7c3aed, #ff2d78); }
    .offer-gw--weekly { background: linear-gradient(135deg, #a855f7, #ff2d78, #00d4ff); }
    .offer-gw--vip {
      background: linear-gradient(135deg, #c8960c, #f5c842, #fff8dc, #e8a020);
      box-shadow: 0 0 20px rgba(245, 200, 66, 0.12);
    }
    .offer-gw-banner:hover.offer-gw--vip { box-shadow: 0 14px 40px rgba(245, 200, 66, 0.22); }

    .avantage-safe-fun-block { line-height: 1.45; }
    .avantage-safe-fun-block .av-safe-line { font-weight: 700; color: var(--txt); }
    .fun-supplement-pulse {
      display: inline-block;
      margin-top: 0.2rem;
      font-weight: 700;
      font-size: 0.82rem;
      color: var(--color);
      animation: funPulse 2.2s ease-in-out infinite;
    }
    @keyframes funPulse {
      0%, 100% { opacity: 1; filter: brightness(1); }
      50% { opacity: 0.88; filter: brightness(1.15); }
    }
    .weekend-fun-opt {
      margin-bottom: 1rem;
      padding: 0.85rem 1rem;
      border-radius: 12px;
      border: 1px solid color-mix(in srgb, var(--color) 28%, transparent);
      background: rgba(0, 0, 0, 0.25);
    }
    .weekend-fun-label {
      display: flex;
      gap: 0.65rem;
      align-items: flex-start;
      cursor: pointer;
      font-size: 0.88rem;
      line-height: 1.45;
      color: var(--txt2);
    }
    .weekend-fun-label input { margin-top: 0.2rem; flex-shrink: 0; accent-color: var(--color); }
    .weekend-fun-warn { margin-top: 0.55rem; font-size: 0.75rem; color: var(--txt3); line-height: 1.4; }
    .weekend-fun-warn code { font-size: 0.7rem; color: var(--cyan); }

    .offer-gw-inner {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.7rem 1rem;
      border-radius: 12px;
      background: linear-gradient(165deg, rgba(13, 18, 32, 0.97), rgba(17, 24, 39, 0.98));
      position: relative;
      overflow: hidden;
      text-align: left;
    }
    .offre-card--vip .offer-gw-inner {
      background: linear-gradient(165deg, rgba(20, 18, 8, 0.96), rgba(13, 18, 32, 0.98));
    }
    .offer-gw-shimmer {
      position: absolute;
      top: 0;
      left: -100%;
      width: 55%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.07), transparent);
      animation: giveawaySweep 4.5s ease-in-out infinite;
      pointer-events: none;
      z-index: 0;
    }
    .offer-gw-icon { font-size: 1.45rem; line-height: 1; position: relative; z-index: 1; filter: drop-shadow(0 0 10px rgba(255, 45, 120, 0.35)); }
    .offre-card--vip .offer-gw-icon { filter: drop-shadow(0 0 10px rgba(245, 200, 66, 0.45)); }
    .offer-gw-copy { position: relative; z-index: 1; flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.15rem; }
    .offer-gw-label {
      font-family: 'Orbitron', sans-serif;
      font-size: 0.58rem;
      font-weight: 800;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      background: linear-gradient(135deg, #ff2d78, #a855f7, #00d4ff);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .offer-gw--vip .offer-gw-label {
      background: linear-gradient(135deg, #f5c842, #fffbe6, #e8a020);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .offer-gw-ptsline {
      display: flex;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 0.2rem 0.45rem;
    }
    .offer-gw-n {
      font-family: 'Orbitron', sans-serif;
      font-size: 1.65rem;
      font-weight: 900;
      line-height: 1;
      color: #00d4ff;
      text-shadow: 0 0 22px rgba(0, 212, 255, 0.35);
    }
    .offer-gw--vip .offer-gw-n {
      background: linear-gradient(135deg, #f5c842, #fffbe6, #e8a020);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      text-shadow: none;
      filter: drop-shadow(0 0 12px rgba(245, 200, 66, 0.35));
    }
    .offer-gw-unit {
      font-family: 'Space Mono', monospace;
      font-size: 0.72rem;
      font-weight: 700;
      color: rgba(0, 212, 255, 0.88);
      letter-spacing: 1px;
    }
    .offer-gw--vip .offer-gw-unit { color: rgba(245, 200, 66, 0.85); }
    .offer-gw-hint {
      font-size: 0.68rem;
      color: var(--txt3);
      letter-spacing: 0.3px;
    }
    .offer-gw-ribbon {
      font-family: 'Space Mono', monospace;
      font-size: 0.52rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.35);
      margin-top: 0.1rem;
    }

    /* Avantages */
    .offre-avantages { padding:1.5rem 2rem; }
    .avantage {
      display:flex; align-items:center; gap:0.75rem;
      padding:0.6rem 0;
      border-bottom:1px solid var(--border);
      color:var(--txt2); font-size:0.9rem;
    }
    .avantage:last-child { border-bottom:none; }
    .av-check {
      width:20px; height:20px; border-radius:50%; flex-shrink:0;
      background:color-mix(in srgb, var(--color) 15%, transparent);
      border:1px solid color-mix(in srgb, var(--color) 40%, transparent);
      display:flex; align-items:center; justify-content:center;
      font-size:0.65rem; color:var(--color); font-weight:700;
    }
    /* Membre connecté */
    .membre-chip {
      margin:0 2rem 1.5rem;
      background:rgba(255,255,255,0.03);
      border:1px solid var(--border);
      border-radius:10px; padding:0.7rem 1rem;
      display:flex; align-items:center; gap:0.6rem;
      font-size:0.82rem; color:var(--txt3);
    }
    .membre-chip .dot { width:7px; height:7px; border-radius:50%; background:#00c864; flex-shrink:0; }
    .membre-chip strong { color:var(--txt2); }

    /* ── PAIEMENT ── */
    .payment-col {
      animation: fadeUp 0.7s ease 0.2s both;
      min-width: 0;
      width: 100%;
    }

    /* StarPass + crypto : deux colonnes qui s’étirent sur toute la largeur dispo */
    .payment-methods-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: clamp(1rem, 2vw, 2rem);
      align-items: start;
      margin-bottom: 1.5rem;
      width: 100%;
    }
    .payment-methods-row > .payment-block { margin-bottom: 0; }
    .payment-methods-row > .payment-crypto-column { margin-bottom: 0; }
    .payment-crypto-column {
      display: flex;
      flex-direction: column;
      gap: clamp(1rem, 2vw, 1.5rem);
      min-width: 0;
      width: 100%;
      align-items: stretch;
    }
    .payment-crypto-column > .payment-block { margin-bottom: 0; }
    .payment-crypto-column > .other-offers { margin-bottom: 0; }
    .payment-block--starpass { min-width: 0; }
    #crypto.payment-block { min-width: 0; }

    .payment-block {
      background:var(--bg2);
      border:1px solid var(--border);
      border-radius:24px;
      padding:2rem;
      margin-bottom:1.5rem;
      position:relative; overflow:hidden;
      width: 100%;
      box-sizing: border-box;
    }
    .payment-block::before {
      content:''; position:absolute; top:0; left:0; right:0; height:2px;
      background:var(--grad); opacity:0.6;
    }

    .block-title {
      font-family:'Orbitron',sans-serif;
      font-size:0.8rem; font-weight:700;
      letter-spacing:2px; text-transform:uppercase;
      color:var(--txt2); margin-bottom:0.5rem;
      display:flex; align-items:center; gap:0.6rem;
    }
    .block-title::after {
      content:''; flex:1; height:1px;
      background:linear-gradient(90deg, var(--border), transparent);
    }
    .block-desc { color:var(--txt3); font-size:0.84rem; margin-bottom:1.2rem; }

    /* StarPass container */
    .sp-wrap {
      background:rgba(255,255,255,0.02);
      border:1px solid var(--border);
      border-radius:14px; padding:1.5rem;
      text-align:center;
      min-width:0;
      max-width:100%;
    }
    .sp-wrap p { color:var(--txt3); font-size:0.83rem; margin-bottom:1rem; }
    .sp-wrap strong { color:var(--color); }

    /* ═══════════════════════════════════════════════════════
       STARPASS OVERRIDE — Style StratEdge cyberpunk
       Sélecteurs haute spécificité pour battre le CSS StarPass
    ═══════════════════════════════════════════════════════ */

    /* ── Container principal ── */
    .sp-wrap [id^="starpass_"] #sk-kit {
      border: 1px solid color-mix(in srgb, var(--color) 25%, transparent) !important;
      background: linear-gradient(165deg, rgba(12,16,24,0.95), rgba(17,24,39,0.9)) !important;
      background-color: transparent !important;
      border-radius: 16px !important;
      width: 100% !important;
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      overflow: hidden !important;
      font-family: 'Rajdhani', sans-serif !important;
      box-sizing: border-box !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit * {
      font-family: 'Rajdhani', sans-serif !important;
      box-sizing: border-box !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit span,
    .sp-wrap [id^="starpass_"] #sk-kit div,
    .sp-wrap [id^="starpass_"] #sk-kit p,
    .sp-wrap [id^="starpass_"] #sk-kit li {
      font-family: 'Rajdhani', sans-serif !important;
      color: var(--txt2) !important;
      text-shadow: none !important;
    }

    /* ── Header (barre du haut) ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-kit-header {
      background: linear-gradient(135deg, color-mix(in srgb, var(--color) 15%, transparent), rgba(0,212,255,0.06)) !important;
      background-position: initial !important;
      height: auto !important;
      padding: 0.9rem 1.2rem !important;
      width: 100% !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      border-bottom: 1px solid color-mix(in srgb, var(--color) 25%, transparent) !important;
      position: relative !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit h1.sk-logo {
      background: none !important;
      background-image: none !important;
      text-indent: 0 !important;
      width: auto !important;
      height: auto !important;
      margin: 0 !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 0.7rem !important;
      font-weight: 700 !important;
      letter-spacing: 2px !important;
      color: var(--color) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit h1.sk-logo span {
      color: var(--color) !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 0.7rem !important;
      font-weight: 700 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit h2.sk-logo {
      display: none !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-kit-header-right {
      position: relative !important;
      height: auto !important;
      width: auto !important;
      top: auto !important;
      right: auto !important;
    }
    /* Langue FR par défaut via paramètre URL ; masquer le sélecteur de langue dans le header */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-kit-header-right select {
      display: none !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-kit-header-right select {
      background: rgba(0,0,0,0.4) !important;
      background-color: rgba(0,0,0,0.4) !important;
      border: 1px solid rgba(255,255,255,0.12) !important;
      border-radius: 8px !important;
      color: #fff !important;
      padding: 0.3rem 0.5rem !important;
      font-size: 0.72rem !important;
      width: auto !important;
      position: relative !important;
      right: auto !important;
      text-align: left !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-kit-header h2.sk-kit-header-right-title {
      display: none !important;
    }

    /* ── Main content ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content {
      padding: 1rem !important;
      background: transparent !important;
      background-color: transparent !important;
      width: 100% !important;
      float: none !important;
    }

    /* ── Steps (les 3 blocs) ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content .sk-step {
      background: rgba(255,255,255,0.03) !important;
      background-color: rgba(255,255,255,0.03) !important;
      border: 1px solid rgba(255,255,255,0.06) !important;
      border-radius: 12px !important;
      margin-bottom: 0.8rem !important;
      padding: 1rem 1.2rem !important;
      float: none !important;
      display: block !important;
      width: auto !important;
    }

    /* ── Titres h3 dans les steps ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content h3 {
      background: none !important;
      background-image: none !important;
      background-position: initial !important;
      height: auto !important;
      font-size: 0.88rem !important;
      line-height: 1.5 !important;
      color: var(--txt) !important;
      font-weight: 700 !important;
      padding: 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content h3.sk-step-image {
      background: none !important;
      background-image: none !important;
      padding: 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content h3.sk-step-image span {
      font-size: 0.88rem !important;
      line-height: 1.5 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step-title {
      color: var(--color) !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 0.72rem !important;
      font-weight: 700 !important;
      letter-spacing: 1px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step-text {
      color: var(--txt2) !important;
      font-weight: 600 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content .sk-step p {
      color: var(--txt2) !important;
      font-size: 0.88rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content .sk-step a.change,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-change-country {
      color: var(--color) !important;
      font-size: 0.78rem !important;
      opacity: 0.7 !important;
    }

    /* ── Onglets de paiement (CB, PayPal, Paysafecard…) ── */
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block {
      margin-top: 0.8rem !important;
      height: auto !important;
      width: auto !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul {
      padding: 0 !important;
      margin: 0 !important;
      display: flex !important;
      gap: 0.35rem !important;
      flex-wrap: wrap !important;
      list-style: none !important;
      width: auto !important;
      height: auto !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li {
      background: rgba(255,255,255,0.04) !important;
      background-color: rgba(255,255,255,0.04) !important;
      border: 1px solid rgba(255,255,255,0.08) !important;
      border-radius: 10px !important;
      padding: 0.5rem 0.75rem !important;
      float: none !important;
      height: auto !important;
      margin: 0 !important;
      cursor: pointer !important;
      transition: all 0.2s !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 0.3rem !important;
      list-style: none !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li:hover {
      border-color: color-mix(in srgb, var(--color) 35%, transparent) !important;
      background: color-mix(in srgb, var(--color) 6%, transparent) !important;
    }
    /* Masquer les moyens de paiement non disponibles (non cliquables) */
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li.sk-disabled,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li.disabled,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li[class*="unavailable"],
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li[class*="no-access"],
    .sp-wrap [id^="starpass_"] #sk-kit #sk-other-access-type-tab-box ul li.sk-disabled,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-other-access-type-tab-box ul li.disabled,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-other-access-type-tab-box ul li[class*="unavailable"] {
      display: none !important;
    }
    /* Masquer "Autres solutions" sauf Daily (filtrage des moyens en JS) */
    .sp-wrap:not(.sp-wrap--daily) [id^="starpass_"] #sk-kit #sk-other-access-type-tab-box {
      display: none !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-payment-method-block ul li.current,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li.current {
      background: color-mix(in srgb, var(--color) 12%, transparent) !important;
      background-color: color-mix(in srgb, var(--color) 12%, transparent) !important;
      border-color: color-mix(in srgb, var(--color) 40%, transparent) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content .sk-step #sk-payment-method-block ul li span,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li span {
      color: var(--txt3) !important;
      font-size: 0.78rem !important;
      font-weight: 600 !important;
      background: none !important;
      background-image: none !important;
      width: auto !important;
      height: auto !important;
      padding: 0 !important;
      cursor: pointer !important;
      line-height: 1.2 !important;
      text-decoration: none !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content .sk-step #sk-payment-method-block ul li.current span,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul li.current span {
      color: var(--color) !important;
    }

    /* ── Zone contenu (formulaire) ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step #sk-payment-method-block .sk-content-text,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text {
      background: color-mix(in srgb, var(--color) 6%, transparent) !important;
      background-color: color-mix(in srgb, var(--color) 6%, transparent) !important;
      border: 1px solid color-mix(in srgb, var(--color) 15%, transparent) !important;
      border-radius: 12px !important;
      padding: 1.2rem !important;
      color: var(--txt2) !important;
      font-size: 0.9rem !important;
      margin-top: 0.6rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text span {
      color: var(--txt2) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .bigtext,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .bigtext span {
      font-family: 'Orbitron', sans-serif !important;
      font-size: 1.6rem !important;
      color: var(--color) !important;
      line-height: 1.2 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .bigtext-alter1,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .bigtext-alter1 span {
      font-size: 1.3rem !important;
      color: var(--color) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .text1 {
      color: var(--txt2) !important;
      font-size: 0.88rem !important;
      margin: 0.3rem 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .mediumtext,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .mediumtext span {
      color: var(--color) !important;
      font-family: 'Orbitron', sans-serif !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-content-text .smalltext {
      color: var(--txt2) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-payment-form {
      width: 100% !important;
    }

    /* ── Texte "Buy 1 code" ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-panel-buy-code-text,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-buy-code-text {
      color: var(--txt) !important;
      font-weight: 700 !important;
      font-size: 0.92rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit [class*="sk-code-value"] {
      color: var(--color) !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 1.1rem !important;
      font-weight: 900 !important;
    }

    /* ── Champs input (prénom, nom, email) ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .sk-text-input-handler-outer {
      background: transparent !important;
      background-color: transparent !important;
      padding: 0 !important;
      border-radius: 10px !important;
      margin-bottom: 0.4rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .sk-text-input-handler-inner {
      background: rgba(0,0,0,0.4) !important;
      background-color: rgba(0,0,0,0.4) !important;
      border: 1px solid rgba(255,255,255,0.1) !important;
      border-radius: 10px !important;
      height: auto !important;
      padding: 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .sk-text-input-handler-inner input {
      background: transparent !important;
      background-color: transparent !important;
      color: #fff !important;
      border: none !important;
      padding: 0.65rem 0.9rem !important;
      font-size: 0.88rem !important;
      font-family: 'Rajdhani', sans-serif !important;
      width: 100% !important;
      height: auto !important;
    }

    /* ── Select pays ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-outer {
      background: transparent !important;
      background-color: transparent !important;
      padding: 0 !important;
      border-radius: 10px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner {
      background: rgba(0,0,0,0.4) !important;
      background-color: rgba(0,0,0,0.4) !important;
      border: 1px solid rgba(255,255,255,0.1) !important;
      border-radius: 10px !important;
      height: auto !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner select {
      background: rgba(0,0,0,0.5) !important;
      background-color: rgba(0,0,0,0.5) !important;
      color: #f0f4f8 !important;
      border: none !important;
      font-size: 0.85rem !important;
      padding: 0.5rem 0.6rem !important;
      width: 100% !important;
    }
    /* Options du menu déroulant : fond sombre + texte lisible (éviter blanc sur blanc) */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner select option,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step select option {
      background: #111827 !important;
      background-color: #111827 !important;
      color: #f0f4f8 !important;
    }
    /* Bloc pays client StarPass (#sk-customer-country) — texte lisible */
    .sp-wrap [id^="starpass_"] #sk-kit #sk-customer-country,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-customer-country,
    .sp-wrap [id^="starpass_"] #sk-kit [id*="customer-country"],
    .sp-wrap [id^="starpass_"] #sk-kit [id*="customer-country"] * {
      color: #f0f4f8 !important;
      -webkit-text-fill-color: #f0f4f8 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-customer-country select,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-customer-country select,
    .sp-wrap [id^="starpass_"] #sk-kit [id*="customer-country"] select {
      color-scheme: dark !important;
      color: #f0f4f8 !important;
      -webkit-text-fill-color: #f0f4f8 !important;
      background: #111827 !important;
      background-color: #111827 !important;
      border: 1px solid rgba(255,255,255,0.14) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-customer-country .selecthandler-inner,
    .sp-wrap [id^="starpass_"] #sk-kit [id*="customer-country"] .selecthandler-inner {
      background: rgba(17,24,39,0.95) !important;
      border: 1px solid rgba(255,255,255,0.12) !important;
    }
    /* Opérateur SMS StarPass : hauteur + contraste (évite barre « trop fine ») */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-sms-select-operator,
    .sp-wrap [id^="starpass_"] #sk-kit div.sk-sms-select-operator,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step [class*="sms-select-operator"] {
      min-height: 48px !important;
      height: auto !important;
      max-height: none !important;
      overflow: visible !important;
      display: block !important;
      box-sizing: border-box !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-sms-select-operator .selecthandler-outer,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-sms-select-operator .selecthandler-inner,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .sk-sms-select-operator .selecthandler-inner {
      min-height: 48px !important;
      height: auto !important;
      max-height: none !important;
      overflow: visible !important;
      display: flex !important;
      align-items: center !important;
      box-sizing: border-box !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-sms-select-operator,
    .sp-wrap [id^="starpass_"] #sk-kit select#sk-sms-select-operator,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-sms-select-operator select,
    .sp-wrap [id^="starpass_"] #sk-kit select.sk-sms-select-operator,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-sms-select-operator select {
      color: #f8fafc !important;
      -webkit-text-fill-color: #f8fafc !important;
      background-color: #1e293b !important;
      border: 1px solid rgba(255, 45, 120, 0.35) !important;
      border-radius: 10px !important;
      min-height: 48px !important;
      height: auto !important;
      line-height: 1.35 !important;
      font-size: 0.95rem !important;
      padding: 0.65rem 2.25rem 0.65rem 0.85rem !important;
      box-sizing: border-box !important;
      appearance: auto !important;
      -webkit-appearance: menulist !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-sms-select-operator option,
    .sp-wrap [id^="starpass_"] #sk-kit select#sk-sms-select-operator option,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-sms-select-operator select option {
      background: #0f172a !important;
      color: #f1f5f9 !important;
      padding: 0.5rem 0.75rem !important;
      min-height: 2.25rem !important;
    }
    /* Liste déroulante custom (si StarPass utilise div/ul au lieu de select natif) */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner [class*="list"],
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner [class*="dropdown"],
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner [class*="option"],
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step [class*="select"] ul,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step [class*="select"] div[role="listbox"] {
      background: #111827 !important;
      background-color: #111827 !important;
      color: #f0f4f8 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner [class*="list"] *,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner [class*="dropdown"] *,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .selecthandler-inner [class*="option"] *,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step [class*="select"] ul li,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step [class*="select"] div[role="listbox"] * {
      background: #111827 !important;
      background-color: #111827 !important;
      color: #f0f4f8 !important;
    }

    /* ── Bouton "Buy now" / "Acheter" ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .buttonhandler-outer {
      background: transparent !important;
      background-color: transparent !important;
      padding: 0 !important;
      border-radius: 12px !important;
      margin-top: 0.6rem !important;
      margin-left: 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .buttonhandler-inner {
      background: var(--grad) !important;
      background-color: transparent !important;
      border-radius: 12px !important;
      height: auto !important;
      text-align: center !important;
      cursor: pointer !important;
      box-shadow: 0 4px 20px var(--glow) !important;
      transition: all 0.3s !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step .buttonhandler-inner:hover {
      box-shadow: 0 8px 30px var(--glow) !important;
      filter: brightness(1.1) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-submit-payment-button {
      color: #fff !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 0.78rem !important;
      font-weight: 700 !important;
      letter-spacing: 1.5px !important;
      text-transform: uppercase !important;
      padding: 0.85rem 1.5rem !important;
      cursor: pointer !important;
      line-height: 1 !important;
      margin: 0 !important;
    }

    /* ── Step 3 : champ code ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-input-code-container {
      text-align: center !important;
      padding: 0.8rem 0 !important;
      margin: 0 !important;
      height: auto !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-input-code-outer {
      background: transparent !important;
      background-color: transparent !important;
      border-radius: 10px !important;
      padding: 0 !important;
      width: auto !important;
      display: inline-block !important;
      float: none !important;
      margin: 0 0.3rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-input-code-inner {
      background: rgba(0,0,0,0.4) !important;
      background-color: rgba(0,0,0,0.4) !important;
      border: 1px solid rgba(255,255,255,0.12) !important;
      border-radius: 10px !important;
      padding: 0.5rem 0.8rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-input-code-field {
      background: transparent !important;
      background-color: transparent !important;
      color: #fff !important;
      border: none !important;
      font-family: 'Space Mono', monospace !important;
      font-size: 1.1rem !important;
      letter-spacing: 3px !important;
      text-align: center !important;
      width: 100px !important;
      height: auto !important;
      padding: 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-step input.sk-submit-code {
      background: var(--grad) !important;
      background-image: none !important;
      background-position: initial !important;
      color: #fff !important;
      border: none !important;
      border-radius: 8px !important;
      padding: 0.55rem 1rem !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 0.7rem !important;
      font-weight: 700 !important;
      letter-spacing: 1px !important;
      text-transform: uppercase !important;
      cursor: pointer !important;
      width: auto !important;
      height: auto !important;
      line-height: 1.2 !important;
      float: none !important;
      display: inline-block !important;
      vertical-align: middle !important;
      box-shadow: 0 3px 12px var(--glow) !important;
    }

    /* ── Footer ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-footer {
      height: auto !important;
      padding: 0.4rem 0 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content span.sk-footer-button {
      background: rgba(255,255,255,0.04) !important;
      background-image: none !important;
      background-position: initial !important;
      border: 1px solid rgba(255,255,255,0.08) !important;
      border-radius: 8px !important;
      color: var(--txt3) !important;
      font-size: 0.65rem !important;
      font-weight: 600 !important;
      padding: 0.3rem 0.6rem !important;
      width: auto !important;
      height: auto !important;
      line-height: 1.3 !important;
      transition: all 0.2s !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content span.sk-footer-button:hover {
      background: color-mix(in srgb, var(--color) 8%, transparent) !important;
      border-color: color-mix(in srgb, var(--color) 20%, transparent) !important;
      color: var(--txt2) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-footer div,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-footer p,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-footer span,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-footer a {
      font-size: 0.65rem !important;
      color: var(--txt3) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .info {
      background: rgba(10,14,23,0.95) !important;
      background-color: rgba(10,14,23,0.95) !important;
      border: 1px solid color-mix(in srgb, var(--color) 20%, transparent) !important;
      border-radius: 10px !important;
      color: var(--txt3) !important;
      font-size: 0.75rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .info span {
      color: var(--txt3) !important;
      font-size: 0.75rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-footer a {
      color: var(--color) !important;
    }

    /* ── Cacher les images/logos StarPass (remplacés par texte) ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-access-type-image {
      display: none !important;
    }

    /* ── Drapeaux pays ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-country-flag-box {
      background-color: rgba(10,14,23,0.95) !important;
      background-image: none !important;
      border: 1px solid color-mix(in srgb, var(--color) 20%, transparent) !important;
      border-radius: 12px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-country-flag-box ul {
      background: rgba(17,24,39,0.95) !important;
      background-color: rgba(17,24,39,0.95) !important;
      border-radius: 10px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .sk-country-flag-box ul li,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-country-flag-box ul li *,
    .sp-wrap [id^="starpass_"] #sk-kit .sk-country-flag-box * {
      color: #f0f4f8 !important;
      background: transparent !important;
      background-color: rgba(17,24,39,0.95) !important;
    }

    /* ── Toute liste / menu déroulant dans le widget : fond sombre, texte lisible ── */
    .sp-wrap [id^="starpass_"] #sk-kit [class*="select"] ul,
    .sp-wrap [id^="starpass_"] #sk-kit [class*="dropdown"] ul,
    .sp-wrap [id^="starpass_"] #sk-kit [class*="list"] ul,
    .sp-wrap [id^="starpass_"] #sk-kit div[style*="position: absolute"] ul,
    .sp-wrap [id^="starpass_"] #sk-kit div[style*="position:absolute"] ul {
      background: #111827 !important;
      background-color: #111827 !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit [class*="select"] ul li,
    .sp-wrap [id^="starpass_"] #sk-kit [class*="dropdown"] ul li,
    .sp-wrap [id^="starpass_"] #sk-kit [class*="list"] ul li {
      color: #f0f4f8 !important;
      background: #111827 !important;
      background-color: #111827 !important;
    }

    /* ── Alert box ── */
    .sp-wrap [id^="starpass_"] #sk-kit #sk-alert-box div.sk-box-content {
      background: rgba(17,24,39,0.98) !important;
      background-color: rgba(17,24,39,0.98) !important;
      border-color: color-mix(in srgb, var(--color) 25%, transparent) !important;
      color: var(--txt2) !important;
      border-radius: 12px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-alert-box-background {
      background: rgba(0,0,0,0.7) !important;
      background-color: rgba(0,0,0,0.7) !important;
    }

    /* ── Email alert ── */
    .sp-wrap [id^="starpass_"] #sk-kit .sk-email-alert {
      color: var(--txt3) !important;
      font-size: 0.78rem !important;
    }

    /* ── SMS panel ── */
    .sp-wrap [id^="starpass_"] #sk-kit #sk-send-text-sms,
    .sp-wrap [id^="starpass_"] #sk-kit #sk-send-text-audiotel {
      font-size: 0.95rem !important;
      color: var(--txt2) !important;
    }

    /* ── Audiotel box ── */
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num {
      font-family: 'Rajdhani', sans-serif !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num::before {
      display: none !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num .num {
      background: rgba(0,0,0,0.3) !important;
      background-image: none !important;
      border: 1px solid rgba(255,255,255,0.1) !important;
      border-radius: 10px 10px 0 0 !important;
      padding: 0.8rem !important;
      height: auto !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num .num span {
      color: var(--color) !important;
      font-family: 'Orbitron', sans-serif !important;
      font-size: 1.4rem !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num .price {
      background: color-mix(in srgb, var(--color) 15%, transparent) !important;
      background-image: none !important;
      border-radius: 0 0 10px 10px !important;
      padding: 0.6rem 0.8rem !important;
      height: auto !important;
      color: var(--txt) !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num .price span {
      color: #fff !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num .price::before,
    .sp-wrap [id^="starpass_"] #sk-kit .audiotel_num .price::after {
      display: none !important;
    }

    /* ── Autres onglets ── */
    .sp-wrap [id^="starpass_"] #sk-kit #sk-other-access-type-tab-box {
      background: rgba(17,24,39,0.95) !important;
      background-color: rgba(17,24,39,0.95) !important;
      border-radius: 10px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-other-access-type-tab-box ul li {
      border-radius: 8px !important;
    }
    .sp-wrap [id^="starpass_"] #sk-kit #sk-payment-method-block #sk-other-access-type-tab-box ul li:hover {
      background: color-mix(in srgb, var(--color) 15%, transparent) !important;
      background-color: color-mix(in srgb, var(--color) 15%, transparent) !important;
    }

    /* ── Responsive mobile ── */
    @media (max-width: 620px) {
      .sp-wrap [id^="starpass_"] #sk-kit {
        width: 100% !important;
      }
      .sp-wrap [id^="starpass_"] #sk-kit .sk-kit-header {
        width: 100% !important;
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
      }
      .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content {
        width: 100% !important;
        padding: 0.8rem !important;
      }
      .sp-wrap [id^="starpass_"] #sk-kit .sk-main-content .sk-step {
        width: auto !important;
        padding: 0.8rem !important;
      }
      .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block {
        width: auto !important;
      }
      .sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block ul {
        width: auto !important;
      }
    }

    /* ═══ Fin StarPass Override ═══ */

    /* Séparateur */
    .sep {
      display:flex; align-items:center; gap:1rem;
      margin:1.2rem 0;
    }
    .sep::before, .sep::after { content:''; flex:1; height:1px; background:var(--border); }
    .sep span { color:var(--txt3); font-size:0.72rem; letter-spacing:2px; text-transform:uppercase; }

    /* ── Styles NOWPayments ────────────────────────────────── */
    .np-info-box {
      display:flex; align-items:center; justify-content:space-between;
      background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
      border-radius:10px; padding:0.8rem 1.1rem; margin-bottom:1.2rem;
    }
    .np-coin-label { font-weight:700; font-size:1rem; color:#fff; }
    .np-network-label { font-size:0.78rem; color:var(--txt3); }
    .btn-generate {
      width:100%; padding:1rem; border:none; border-radius:12px; cursor:pointer;
      background:linear-gradient(135deg,#f7931a,#e67e00);
      color:#fff; font-family:'Orbitron',sans-serif; font-size:0.95rem;
      font-weight:700; letter-spacing:1px;
      transition:transform .2s, box-shadow .2s;
    }
    .btn-generate:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 8px 30px rgba(247,147,26,0.4); }
    .btn-generate:disabled { opacity:0.6; cursor:not-allowed; }
    .np-generate-hint { text-align:center; font-size:0.75rem; color:var(--txt3); margin-top:0.5rem; }
.np-promo-anniv {
      background:rgba(255,193,7,0.08); border:1px solid rgba(255,193,7,0.25);
      border-radius:10px; padding:0.65rem 1rem; margin-bottom:1rem;
      font-size:0.85rem; color:#e6d44c;
    }

    .np-address-block { background:rgba(0,0,0,0.3); border-radius:14px; padding:1.4rem; margin-bottom:1rem; }
    .np-step-label { font-size:0.8rem; color:var(--txt3); margin-bottom:0.7rem; text-transform:uppercase; letter-spacing:1px; }
    .np-amount-box {
      display:flex; align-items:baseline; gap:0.5rem;
      margin-bottom:0.4rem;
    }
    .np-amount-value { font-family:'Orbitron',sans-serif; font-size:2rem; font-weight:900; color:#f7931a; }
    .np-amount-currency { font-size:1.1rem; font-weight:700; color:#f7931a; }
    .np-network-tag {
      display:inline-block; background:rgba(247,147,26,0.15); color:#f7931a;
      border:1px solid rgba(247,147,26,0.3); border-radius:6px;
      padding:0.2rem 0.7rem; font-size:0.75rem; margin-bottom:0.5rem;
    }
    .np-warning {
      background:rgba(255,200,0,0.08); border:1px solid rgba(255,200,0,0.25);
      border-radius:8px; padding:0.7rem 1rem; font-size:0.78rem;
      color:#ffd700; margin-top:0.8rem;
    }
    .np-timer-box {
      text-align:center; margin-top:1rem; font-size:0.85rem; color:var(--txt3);
    }
    #np-countdown { color:#f7931a; font-family:'Orbitron',sans-serif; }

    /* Statut polling */
    .np-status-box {
      border-radius:12px; padding:1.2rem; text-align:center;
      border:1px solid rgba(255,255,255,0.08); margin-bottom:1rem;
      transition:background .3s, border-color .3s;
    }
    .np-status-waiting, .np-status-confirming { background:rgba(247,147,26,0.07); border-color:rgba(247,147,26,0.2); }
    .np-status-confirmed, .np-status-sending, .np-status-finished { background:rgba(0,212,100,0.07); border-color:rgba(0,212,100,0.2); }
    .np-status-failed, .np-status-refunded, .np-status-expired { background:rgba(255,45,80,0.07); border-color:rgba(255,45,80,0.2); }
    .np-status-icon { font-size:2rem; margin-bottom:0.5rem; }
    .np-status-msg { font-size:0.95rem; font-weight:600; color:#fff; margin-bottom:0.3rem; }
    .np-status-sub { font-size:0.75rem; color:var(--txt3); }

    /* Succès */
    .np-success-box { text-align:center; padding:2rem; }
    .np-success-icon { font-size:3.5rem; margin-bottom:0.8rem; }
    .np-success-title { font-family:'Orbitron',sans-serif; font-size:1.4rem; font-weight:800; color:#00d46a; margin-bottom:0.5rem; }
    .np-success-desc { color:var(--txt3); font-size:0.9rem; }

    .btn-reset {
      background:rgba(247,147,26,0.12); border:1px solid rgba(247,147,26,0.5); color:#f7931a;
      border-radius:8px; padding:0.5rem 1rem; cursor:pointer; font-size:0.82rem;
      margin-bottom:1rem; transition:all .2s; font-weight:600;
    }
    .btn-reset:hover { background:rgba(247,147,26,0.25); border-color:#f7931a; color:#ffb347; }
    /* ── Fin styles NOWPayments ─────────────────────────────── */

    /* ── Bouton crypto */
    .btn-crypto {
      display:flex; align-items:center; justify-content:center; gap:0.6rem;
      width:100%; padding:0.95rem;
      background:linear-gradient(135deg,#f7931a,#e8820a);
      color:#fff; border:none; border-radius:12px;
      font-family:'Orbitron',sans-serif; font-size:0.78rem;
      font-weight:700; letter-spacing:1.5px; text-transform:uppercase;
      text-decoration:none; cursor:pointer;
      transition:all 0.25s;
      box-shadow:0 4px 20px rgba(247,147,26,0.2);
    }
    .btn-crypto:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(247,147,26,0.4); }

    /* Badges sécurité */
    .sec-badges {
      display:flex; gap:0.5rem; flex-wrap:wrap;
      margin-top:1.2rem;
    }
    .sec-badge {
      background:rgba(255,255,255,0.03);
      border:1px solid var(--border);
      border-radius:8px; padding:0.35rem 0.75rem;
      font-size:0.72rem; color:var(--txt3);
      display:flex; align-items:center; gap:0.35rem;
    }

    /* Note */
    .note-box {
      background:rgba(255,193,7,0.05);
      border:1px solid rgba(255,193,7,0.18);
      border-radius:12px; padding:1rem 1.2rem;
      margin-top:1.2rem;
    }
    .note-box p { font-size:0.8rem; color:#a09040; line-height:1.6; }
    .note-box strong { color:#ffc107; }

    .stake-block {
      margin-top:1rem;
      background:linear-gradient(135deg,rgba(0,212,255,0.12),color-mix(in srgb, var(--color) 12%, transparent));
      border:1px solid transparent;
      border-radius:14px;
      padding:1rem 1.1rem;
      position:relative;
      overflow:hidden;
    }
    .stake-block::after {
      content:''; position:absolute; inset:-1px; border-radius:15px; padding:1px;
      background:linear-gradient(135deg,#00d4ff,var(--color));
      -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
      -webkit-mask-composite:xor; mask-composite:exclude;
      pointer-events:none;
    }
    .stake-block-title {
      font-family:'Orbitron',sans-serif;
      font-size:0.72rem;
      letter-spacing:1.6px;
      text-transform:uppercase;
      background:linear-gradient(135deg,#00d4ff,var(--color));
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      margin-bottom:0.4rem;
    }
    .stake-block-desc {
      font-size:0.84rem;
      color:var(--txt2);
      margin-bottom:0.8rem;
      line-height:1.45;
      position:relative; z-index:1;
    }
    .btn-stake-offer {
      display:flex; align-items:center; justify-content:center; gap:0.45rem;
      width:100%; padding:0.9rem;
      background:linear-gradient(135deg,#00d4ff,#0089ff 55%,var(--color));
      color:#fff; border:1px solid rgba(0,212,255,0.35); border-radius:12px;
      font-family:'Orbitron',sans-serif; font-size:0.76rem;
      font-weight:700; letter-spacing:1.1px; text-transform:uppercase;
      text-decoration:none; transition:all .25s;
      box-shadow:0 6px 20px rgba(0,166,255,0.24);
      position:relative; overflow:hidden; z-index:1;
    }
    .btn-stake-offer::before {
      content:'';
      position:absolute;
      top:-150%; left:-18%;
      width:36%; height:320%;
      background:linear-gradient(180deg,rgba(255,255,255,0),rgba(255,255,255,0.34),rgba(255,255,255,0));
      transform:rotate(24deg);
      transition:left .45s ease;
      pointer-events:none;
    }
    .btn-stake-offer:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(0,166,255,0.4); }
    .btn-stake-offer:hover::before { left:118%; }
    .stake-block-note {
      margin-top:0.5rem; font-size:0.72rem; text-align:center;
      background:linear-gradient(135deg,#7fdfff,color-mix(in srgb, var(--color) 70%, #fff));
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
      position:relative; z-index:1;
    }
    @keyframes stakePulseBlock {
      0%, 100% { box-shadow:0 6px 20px rgba(0,166,255,0.24); }
      50% { box-shadow:0 11px 32px rgba(0,166,255,0.42); }
    }
    @media (hover:hover) and (pointer:fine) and (min-width:901px) {
      .btn-stake-offer { animation: stakePulseBlock 2.4s ease-in-out infinite; }
      .btn-stake-offer:hover { animation-play-state: paused; }
    }
    @media (prefers-reduced-motion: reduce) {
      .btn-stake-offer { animation:none !important; }
    }

    /* Legacy tennis stake (kept for backward compat) */

    /* ── Stake Wrap (payment page bottom) ── */
    .stake-pay-block { border-color: rgba(0,212,255,0.15); }
    .stake-pay-block .stake-wrap { text-align: center; }
    .stake-pay-block .stake-sep { font-family:'Space Mono',monospace; font-size:0.6rem; letter-spacing:2px; color:var(--txt3); margin-bottom:0.5rem; text-transform:uppercase; }
    .stake-pay-block .stake-btn { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:0.95rem 1.2rem; background:linear-gradient(135deg,#00d4ff,#0089ff); color:#fff; font-family:'Orbitron',sans-serif; font-size:0.76rem; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; border:1px solid rgba(0,212,255,0.35); border-radius:10px; cursor:pointer; text-decoration:none; transition:all 0.25s; box-shadow:0 6px 18px rgba(0,166,255,0.22); }
    .stake-pay-block .stake-btn:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,166,255,0.38); }
    .stake-pay-block .stake-offer { display:flex; align-items:center; justify-content:center; gap:0.35rem; flex-wrap:wrap; font-family:'Rajdhani',sans-serif; font-size:0.78rem; font-weight:600; color:rgba(0,212,255,0.85); margin-top:0.5rem; }
    .stake-pay-block .vip-mini { display:inline-flex; align-items:center; gap:3px; background:linear-gradient(135deg,rgba(200,150,12,0.15),rgba(245,200,66,0.08)); border:1px solid rgba(245,200,66,0.3); border-radius:5px; padding:1px 6px; vertical-align:middle; }
    .stake-pay-block .vip-mini svg { width:14px; height:14px; flex-shrink:0; }
    .stake-pay-block .vip-mini-label { display:inline-flex; flex-direction:column; align-items:center; line-height:1; }
    .stake-pay-block .vip-mini-txt { font-family:'Orbitron',sans-serif; font-size:0.55rem; font-weight:900; letter-spacing:0.5px; background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; line-height:1; }
    .stake-pay-block .vip-mini-vip { font-size:0.6rem; }
    .stake-pay-block .vip-mini-max { font-size:0.5rem; }

    .stake-tennis-block {
      margin-top:1rem;
      background:linear-gradient(135deg,rgba(0,212,255,0.12),rgba(0,212,106,0.08));
      border:1px solid rgba(0,212,255,0.28);
      border-radius:14px;
      padding:1rem 1.1rem;
    }
    .stake-tennis-title {
      font-family:'Orbitron',sans-serif;
      font-size:0.72rem;
      letter-spacing:1.6px;
      text-transform:uppercase;
      color:#00d4ff;
      margin-bottom:0.4rem;
    }
    .stake-tennis-desc {
      font-size:0.84rem;
      color:var(--txt2);
      margin-bottom:0.8rem;
      line-height:1.45;
    }
    .btn-stake-tennis {
      display:flex; align-items:center; justify-content:center; gap:0.45rem;
      width:100%; padding:0.9rem;
      background:linear-gradient(135deg,#00d4ff,#0089ff 55%,#00d46a);
      color:#fff; border:1px solid rgba(0,212,255,0.35); border-radius:12px;
      font-family:'Orbitron',sans-serif; font-size:0.76rem;
      font-weight:700; letter-spacing:1.1px; text-transform:uppercase;
      text-decoration:none; transition:all .25s;
      box-shadow:0 6px 20px rgba(0,166,255,0.24);
      position:relative; overflow:hidden;
    }
    .btn-stake-tennis::before {
      content:'';
      position:absolute;
      top:-150%;
      left:-18%;
      width:36%;
      height:320%;
      background:linear-gradient(180deg,rgba(255,255,255,0),rgba(255,255,255,0.34),rgba(255,255,255,0));
      transform:rotate(24deg);
      transition:left .45s ease;
      pointer-events:none;
    }
    .btn-stake-tennis:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(0,166,255,0.4); }
    .btn-stake-tennis:hover::before { left:118%; }
    .stake-tennis-note { margin-top:0.5rem; font-size:0.72rem; color:#7fdfff; text-align:center; }
    @keyframes stakePulseSoft {
      0%, 100% { box-shadow:0 6px 20px rgba(0,166,255,0.24); }
      50% { box-shadow:0 11px 32px rgba(0,166,255,0.42); }
    }
    @media (hover:hover) and (pointer:fine) and (min-width:901px) {
      .btn-stake-tennis { animation: stakePulseSoft 2.4s ease-in-out infinite; }
      .btn-stake-tennis:hover { animation-play-state: paused; }
    }
    @media (prefers-reduced-motion: reduce) {
      .btn-stake-tennis { animation:none !important; }
      .payment-col .stake-btn { animation:none !important; }
      .offer-gw-shimmer { animation:none !important; }
      .offer-gw-banner:hover { transform:none; }
    }

    /* Crypto tabs */
    .crypto-tabs { display:flex; gap:0.5rem; margin-bottom:1.2rem; flex-wrap:wrap; }
    .crypto-tab {
      padding:0.45rem 1rem; border-radius:8px; border:1px solid var(--border);
      background:rgba(255,255,255,0.03); color:var(--txt3);
      font-family:'Orbitron',sans-serif; font-size:0.7rem;
      font-weight:700; letter-spacing:1px; cursor:pointer;
      transition:all 0.2s;
    }
    .crypto-tab.active, .crypto-tab:hover {
      border-color:var(--color); color:var(--color);
      background:color-mix(in srgb, var(--color) 10%, transparent);
    }

    /* Panels crypto */
    .coin-panel { margin-bottom:1rem; }
    .hidden { display:none !important; }
    .coin-panel.hidden { display:none; }
    .coin-info { font-size:0.82rem; color:var(--txt3); margin-bottom:0.6rem; }
    .coin-info strong { color:var(--txt2); }
    .wallet-box {
      display:flex; align-items:center; gap:0.5rem;
      background:rgba(0,0,0,0.3); border:1px solid var(--border);
      border-radius:10px; padding:0.75rem 1rem;
    }
    .wallet-addr {
      flex:1; font-family:'Space Mono',monospace; font-size:0.72rem;
      color:var(--color); word-break:break-all;
    }
    .copy-btn {
      background:rgba(255,255,255,0.06); border:1px solid var(--border);
      color:var(--txt2); border-radius:7px; padding:0.3rem 0.6rem;
      font-size:0.75rem; cursor:pointer; white-space:nowrap;
      transition:all 0.2s; flex-shrink:0;
    }
    .copy-btn:hover { background:rgba(255,255,255,0.12); color:var(--txt); }
    .copy-btn.copied { color:#00c864; border-color:#00c864; }

    /* Formulaire TX */
    .tx-form {
      background:rgba(255,255,255,0.02); border:1px solid var(--border);
      border-radius:14px; padding:1.2rem; margin:1.2rem 0;
    }
    .tx-title {
      font-family:'Orbitron',sans-serif; font-size:0.78rem;
      font-weight:700; letter-spacing:1px; color:var(--txt2);
      margin-bottom:0.4rem;
    }
    .tx-desc { font-size:0.8rem; color:var(--txt3); margin-bottom:1rem; line-height:1.5; }
    .tx-field { margin-bottom:0.8rem; }
    .tx-field label { display:block; font-size:0.78rem; color:var(--txt3); margin-bottom:0.3rem; }
    .tx-field input, .tx-field select {
      width:100%; background:rgba(255,255,255,0.04);
      border:1px solid var(--border); border-radius:8px;
      padding:0.6rem 0.85rem; color:var(--txt);
      font-family:'Space Mono',monospace; font-size:0.78rem;
      outline:none; transition:border 0.2s;
    }
    .tx-field input:focus, .tx-field select:focus { border-color:var(--color); }
    .tx-field select option { background:var(--bg2); }
    .crypto-selected-badge {
      display:inline-flex; align-items:center; gap:0.5rem;
      background:color-mix(in srgb, var(--color) 12%, transparent);
      border:1px solid color-mix(in srgb, var(--color) 35%, transparent);
      color:var(--color); border-radius:8px;
      padding:0.55rem 1rem; font-family:'Orbitron',sans-serif;
      font-size:0.78rem; font-weight:700; letter-spacing:1px;
      width:100%;
    }
    .btn-tx {
      width:100%; padding:0.85rem;
      background:var(--grad); color:#fff; border:none;
      border-radius:10px; font-family:'Orbitron',sans-serif;
      font-size:0.8rem; font-weight:700; letter-spacing:1.5px;
      text-transform:uppercase; cursor:pointer;
      transition:all 0.25s;
      box-shadow:0 4px 20px var(--glow);
    }
    .btn-tx:hover { transform:translateY(-2px); box-shadow:0 8px 30px var(--glow); }

    /* Autres offres */
    .other-offers {
      background:var(--bg2); border:1px solid var(--border);
      border-radius:24px; padding:1.5rem 2rem;
      animation:fadeUp 0.7s ease 0.3s both;
    }
    .other-title {
      font-family:'Orbitron',sans-serif; font-size:0.72rem;
      letter-spacing:2px; text-transform:uppercase;
      color:var(--txt3); margin-bottom:1.2rem;
    }
    .other-grid { display:flex; gap:0.75rem; flex-wrap:wrap; }
    .other-pill {
      display:inline-flex; align-items:center; gap:0.5rem;
      padding:0.5rem 1rem; border-radius:30px;
      font-size:0.82rem; font-weight:600; text-decoration:none;
      border:1px solid var(--border); color:var(--txt2);
      background:rgba(255,255,255,0.03);
      transition:all 0.2s;
    }
    .other-pill:hover { border-color:rgba(255,255,255,0.2); color:var(--txt); background:rgba(255,255,255,0.06); }
    .other-pill .pill-price {
      font-family:'Orbitron',sans-serif; font-size:0.75rem;
      font-weight:700; margin-left:0.25rem;
    }

    @keyframes fadeUp {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:translateY(0); }
    }

    @media (max-width:1040px) {
      .payment-methods-row {
        grid-template-columns: 1fr;
        gap: 1.25rem;
      }
    }
    @media (max-width:860px) {
      html,body{overflow-x:hidden;}
      .layout { grid-template-columns:1fr; }
      .hero-title { font-size:1.6rem; }
      .hero-subtitle{font-size:0.9rem;}
      .page { padding:1.5rem 0.8rem 5rem; }
      nav{padding:0 0.8rem;}
      .nav-inner { flex-wrap:wrap; gap:0.4rem; height:auto; min-height:50px; padding:0.5rem 0; }
      .nav-logo img { height:28px; }
      .nav-back { font-size:0.75rem; order:3; width:100%; justify-content:center; padding-bottom:0.3rem; }
      .nav-badge { font-size:0.55rem; padding:0.2rem 0.6rem; }
      .offre-card{border-radius:16px;}
      .payment-block{border-radius:16px;padding:1.5rem;}
      .np-info-box{flex-direction:column;align-items:flex-start;gap:0.4rem;}
      .np-amount-value{font-size:1.5rem;}
      .np-success-title{font-size:1.1rem;}
      .btn-generate{font-size:0.85rem;padding:0.85rem;}
      .btn-crypto{font-size:0.72rem;padding:0.8rem;}
      .payment-col .stake-btn{font-size:0.7rem;padding:10px 14px;min-height:44px;}
      .other-offers{padding:1.2rem 1rem;border-radius:16px;}
    }
    @media (max-width:480px) {
      .hero { margin-bottom:2rem; }
      .hero-title { font-size:1.3rem; }
      .hero-subtitle { font-size:0.85rem; }
      .hero-tag{font-size:0.6rem;letter-spacing:2px;padding:0.3rem 0.9rem;}
      .page { padding:1.2rem 0.6rem 4rem; }
      .offre-card-top { padding:1.2rem; }
      .offre-avantages { padding:1rem 1.2rem; }
      .offre-prix .num { font-size:3rem; }
      .offre-prix .cur{font-size:1.3rem;}
      .offre-video-wrap { width:80px; height:80px; }
      .offre-badge{font-size:0.55rem;}
      .offre-duree{font-size:0.78rem;}
      .offer-gw-n { font-size: 1.35rem; }
      .offer-gw-inner { padding: 0.6rem 0.85rem; gap: 0.55rem; }
      .offer-gw-icon { font-size: 1.2rem; }
      .avantage{font-size:0.85rem;padding:0.5rem 0;}
      .payment-block { padding:1.2rem; border-radius:14px; }
      .block-title{font-size:0.72rem;}
      .block-desc{font-size:0.78rem;}
      .sp-wrap { padding:0.9rem; }
      .sp-wrap p{font-size:0.78rem;}
      .crypto-tabs { gap:0.35rem; }
      .crypto-tab { padding:0.3rem 0.6rem; font-size:0.58rem; }
      .wallet-box{padding:0.6rem 0.8rem;border-radius:8px;}
      .wallet-addr { font-size:0.58rem; }
      .copy-btn{font-size:0.68rem;padding:0.25rem 0.5rem;}
      .sec-badges { gap:0.35rem; }
      .sec-badge { font-size:0.62rem; padding:0.2rem 0.5rem; }
      .other-pill { font-size:0.75rem; padding:0.35rem 0.7rem; }
      .other-pill .pill-price{font-size:0.68rem;}
      .membre-chip { margin:0 1.2rem 1rem; padding:0.5rem 0.7rem; font-size:0.75rem; }
      .note-box{padding:0.8rem 1rem;border-radius:10px;}
      .note-box p{font-size:0.75rem;}
      .tx-form{padding:1rem;border-radius:10px;}
      .tx-title{font-size:0.72rem;}
      .tx-desc{font-size:0.75rem;}
      .np-address-block{padding:1rem;border-radius:10px;}
      .np-amount-value{font-size:1.3rem;}
      .np-warning{font-size:0.72rem;padding:0.6rem 0.8rem;}
      .np-status-icon{font-size:1.6rem;}
      .np-status-msg{font-size:0.85rem;}
      .np-success-icon{font-size:2.5rem;}
      .np-success-title{font-size:1rem;}
      .np-success-desc{font-size:0.82rem;}
    }
    @media (max-width:360px){
      .page{padding:1rem 0.4rem 4rem;}
      .offre-card-top{padding:1rem;}
      .offre-avantages{padding:0.8rem 1rem;}
      .offre-prix .num{font-size:2.5rem;}
      .payment-block{padding:1rem;border-radius:12px;}
      .hero-title{font-size:1.15rem;}
    }
  </style>
</head>
<body>

<div class="bg-orbs">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<nav>
  <div class="nav-inner">
    <a href="/" class="nav-logo"><img src="assets/images/logo site.png" alt="StratEdge"></a>
    <span class="nav-badge"><?= $o['badge'] ?></span>
    <a href="/souscrire.php" class="nav-back">← Toutes les formules</a>
  </div>
</nav>

<div class="page">

  <!-- HERO -->
  <div class="hero">
    <div class="hero-tag"><?= $o['tag'] ?></div>
    <h1 class="hero-title">
      <?= $o['emoji'] ?> Formule <span class="accent"><?= $o['titre'] ?></span><br>
      <span style="font-size:0.6em;color:var(--txt3);font-weight:400;"><?= $o['subtitle'] ?></span>
    </h1>
    <p class="hero-subtitle">Choisissez votre mode de paiement — accès immédiat après validation</p>
  </div>

  <div class="layout">

    <!-- ── COLONNE OFFRE ── -->
    <div>
      <div class="offre-card<?= $type === 'vip_max' ? ' offre-card--vip' : '' ?>">
        <div class="offre-card-top">
          <div class="offre-badge"><?= $o['badge'] ?></div>
          <div class="offre-video-wrap">
            <video autoplay loop muted playsinline>
              <source src="<?= $o['video'] ?>" type="video/mp4">
            </video>
          </div>
          <div class="offre-prix">
            <span class="cur">€</span><span class="num" id="offrePrixNum"<?= $type === 'weekend' ? ' data-prix-base="' . htmlspecialchars($o['prix'], ENT_QUOTES, 'UTF-8') . '" data-prix-fun="' . htmlspecialchars($o['prix_fun'] ?? '20', ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= $o['prix'] ?></span>
          </div>
          <div class="offre-duree"><?= $o['duree'] ?></div>
          <?php if ($gwShowBadge && $gwBannerMod !== ''): ?>
          <a href="/giveaway.php" class="offer-gw-banner <?= htmlspecialchars($gwBannerMod) ?>" aria-label="GiveAway mensuel, <?= (int)$gwPtsPack ?> point<?= $gwPtsPack > 1 ? 's' : '' ?> par achat">
            <span class="offer-gw-inner">
              <span class="offer-gw-shimmer" aria-hidden="true"></span>
              <span class="offer-gw-icon">🎁</span>
              <span class="offer-gw-copy">
                <span class="offer-gw-label">GiveAway mensuel</span>
                <span class="offer-gw-ptsline"><strong class="offer-gw-n"><?= (int)$gwPtsPack ?></strong><span class="offer-gw-unit">pts</span><span class="offer-gw-hint">par achat</span></span>
                <span class="offer-gw-ribbon">Tirage &amp; roue chaque mois</span>
              </span>
            </span>
          </a>
          <?php endif; ?>
        </div>

        <div class="offre-avantages">
          <?php foreach ($o['avantages'] as $av): ?>
          <div class="avantage">
            <div class="av-check">✓</div>
            <?= $av ?>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="membre-chip">
          <div class="dot"></div>
          Connecté : <strong><?= clean($membre['nom']) ?></strong>
        </div>
      </div>
    </div>

    <!-- ── COLONNE PAIEMENT ── -->
    <div class="payment-col">

      <?php if ($type !== 'tennis'): ?>
      <div class="stake-block">
        <div class="stake-block-title">Bonus Partenaire Stake</div>
        <div class="stake-block-desc">Crée ton compte Stake avec notre lien partenaire et débloque un bonus exclusif StratEdge.</div>
        <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="btn-stake-offer">🎰 S'inscrire sur Stake · Lien bonus</a>
        <div class="stake-block-note">Lien bonus officiel · 1 mois VIP Max offert via ce lien</div>
      </div>
      <?php endif; ?>

      <!-- StarPass + Crypto = 2 colonnes côte à côte -->
      <div class="payment-methods-row">

      <!-- StarPass -->
      <div class="payment-block payment-block--starpass">
        <?php if ($type === 'daily'): ?>
          <div class="block-title">💳 Paysafecard · Internet+ mobile · CB · SMS</div>
          <div class="block-desc">Paiement sécurisé via <strong style="color:var(--color)">StarPass</strong> — uniquement ces moyens sur l’offre Daily</div>
        <?php else: ?>
          <div class="block-title">💳 CB · PayPal · Paysafecard · Internet+</div>
          <div class="block-desc">Paiement sécurisé via <strong style="color:var(--color)">StarPass</strong> — carte bancaire, PayPal, Paysafecard ou Internet+</div>
        <?php endif; ?>
        <?php
        $offerPriceNum = (float)str_replace(',', '.', $o['prix']);
        $spWrapClass = 'sp-wrap' . ($type === 'daily' ? ' sp-wrap--daily' : '');
        $spWrapAttrs = 'class="' . htmlspecialchars($spWrapClass, ENT_QUOTES, 'UTF-8') . '" id="spWrapRoot" data-offer-price="' . $offerPriceNum . '" data-offer-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
        if ($type === 'weekend') {
            $spWrapAttrs .= ' data-idd-base="' . (int)$o['idd'] . '" data-idd-fun="' . ($weekendFunStarPassOk ? (int)$weekendFunIdd : 0) . '"';
            $spWrapAttrs .= ' data-prix-base="' . htmlspecialchars($o['prix'], ENT_QUOTES, 'UTF-8') . '"';
            $spWrapAttrs .= ' data-prix-fun="' . htmlspecialchars($o['prix_fun'] ?? '20', ENT_QUOTES, 'UTF-8') . '"';
        }
        ?>
        <div <?= $spWrapAttrs ?>>
          <?php if ($type === 'weekend'): ?>
          <div class="weekend-fun-opt">
            <label class="weekend-fun-label" for="chkWeekendFun">
              <input type="checkbox" id="chkWeekendFun" name="weekend_fun" value="1" <?= $weekendFunStarPassOk ? '' : 'disabled' ?>>
              <span><strong>Option Fun bets (+10€)</strong> — accès <strong>Safe</strong> + <strong>Fun</strong> jusqu’au dimanche 23h59 (StarPass à 20€).</span>
            </label>
            <?php if (!$weekendFunStarPassOk): ?>
            <p class="weekend-fun-warn">Pour activer l’option : renseigne <code>idd_with_fun</code> dans ce fichier (code StarPass à <strong>20€</strong>) ou la variable d’environnement <code>STARPASS_WEEKEND_FUN_IDD</code> sur le serveur.</p>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <p id="spPayLine">Cliquez sur le bouton ci-dessous pour payer <strong id="spPayAmount"><?= htmlspecialchars($o['prix']) ?></strong>€ via StarPass</p>
          <div id="starpassMount">
            <div id="starpass_<?= (int)$o['idd'] ?>"></div>
            <script type="text/javascript"
              src="https://script.starpass.fr/script.php?idd=<?= (int)$o['idd'] ?>&datas=<?= urlencode($starpassDatasInitial) ?>&lang=fr">
            </script>
            <?php if ($type === 'tennis'): ?>
            <noscript>
              <p>Veuillez activer le JavaScript pour afficher le paiement StarPass. <a href="https://www.starpass.fr/" rel="noopener noreferrer">En savoir plus sur StarPass</a></p>
            </noscript>
            <?php endif; ?>
          </div>
          <script>
          (function(){
            var packType = <?= json_encode($type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var isDailyOffer = packType === 'daily';
            var spRoot = document.getElementById('spWrapRoot');
            var offerPrice = parseFloat(spRoot && spRoot.getAttribute('data-offer-price')) || 0;
            var hideAboveSms = offerPrice > 4.5;

            function getButtonText(li) {
              return (li.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            }
            /** Daily : uniquement Paysafecard, Internet+ mobile, carte bancaire, SMS (pas PayPal, pas banque en ligne, pas onglet Autres solutions) */
            function isAllowedDailyAccessTab(li) {
              var t = getButtonText(li);
              if (/autres\s*solutions|other\s*solutions|banque\s*en\s*ligne|online\s*banking/.test(t)) return false;
              if (/paypal/.test(t)) return false;
              if (/paysafe|pay\s*safe/.test(t)) return true;
              if (/internet\s*\+\s*mobile|internet\+\s*mobile|^internet\+$/i.test(t.trim())) return true;
              if (/carte\s*bancaire|credit\s*card|debit\s*card|bank\s*card|\bcb\b|card\s*payment|visa|mastercard/.test(t)) return true;
              if (/^sms\b|envoyer\s*un\s*sms|premium\s*sms|text\s*message|facturation\s*mobile|mobile\s*billing/.test(t)) return true;
              return false;
            }
            function shouldHideByLabel(li) {
              var t = getButtonText(li);
              if (/other\s*solutions|autres\s*solutions/i.test(t)) return true;
              if (!hideAboveSms) return false;
              return /sms|phone|mobile\s*call|appel|mobile\s*billing|facturation\s*mobile|internet\+\s*mobile/i.test(t);
            }

            /** Toutes les offres : masquer Banque en ligne / Online banking */
            function isOnlineBankingTab(li) {
              var t = getButtonText(li);
              return /banque\s*en\s*ligne|online\s*banking|internet\s*banking/.test(t);
            }

            function translateStarPassToFrench(wrap) {
              var map = [
                ['Step 1:', 'Étape 1 :'],
                ['Step 1 :', 'Étape 1 :'],
                ['Step 2:', 'Étape 2 :'],
                ['Step 2 :', 'Étape 2 :'],
                ['Step 3:', 'Étape 3 :'],
                ['Step 3 :', 'Étape 3 :'],
                ['Payment', 'Paiement'],
                ['Credit Card', 'Carte bancaire'],
                ['Online Banking', 'Banque en ligne'],
                ['Phone', 'Téléphone'],
                ['Mobile Call', 'Appel mobile'],
                ['Mobile Billing', 'Facturation mobile'],
                ['Internet+ Mobile', 'Internet+ mobile'],
                ['Other solutions', 'Autres solutions'],
                ['Buy', 'Acheter'],
                ['Buy now', 'Acheter'],
                ['Select', 'Choisir'],
                ['Select your country', 'Choisissez votre pays'],
                ['Submit', 'Valider'],
                ['Enter', 'Saisir'],
                ['Enter your code', 'Saisissez votre code'],
                ['Your country', 'Votre pays'],
                ['Country', 'Pays'],
                ['Code', 'Code']
              ];
              function replaceInNode(node) {
                if (node.nodeType === 3) {
                  var text = node.textContent;
                  map.forEach(function(pair) {
                    text = text.split(pair[0]).join(pair[1]);
                  });
                  if (text !== node.textContent) node.textContent = text;
                  return;
                }
                if (node.nodeType === 1 && node.childNodes) {
                  for (var i = 0; i < node.childNodes.length; i++) replaceInNode(node.childNodes[i]);
                }
              }
              if (wrap) replaceInNode(wrap);
            }

            function hideUnavailableStarPassButtons() {
              var wrap = document.querySelector('.sp-wrap [id^="starpass_"] #sk-kit');
              if (!wrap) return;
              var blocks = [
                wrap.querySelector('#sk-access-type-block'),
                wrap.querySelector('#sk-access-type-tab'),
                wrap.querySelector('#sk-other-access-type-tab-box')
              ].filter(Boolean);
              blocks.forEach(function(block){
                var items = block.querySelectorAll('ul li');
                items.forEach(function(li){
                  if (isOnlineBankingTab(li)) {
                    li.style.setProperty('display', 'none', 'important');
                    return;
                  }
                  if (isDailyOffer) {
                    if (!isAllowedDailyAccessTab(li)) {
                      li.style.setProperty('display', 'none', 'important');
                      return;
                    }
                    var st = window.getComputedStyle(li);
                    var hrefD = (li.querySelector('a') || li).getAttribute('href');
                    var ariaD = li.getAttribute('aria-disabled') === 'true';
                    var cls = (li.className || '').toLowerCase();
                    var dis = ariaD
                      || /disabled|unavailable|no-access|off|no-/.test(cls)
                      || st.pointerEvents === 'none'
                      || st.opacity === '0'
                      || (hrefD !== null && (hrefD === '#' || hrefD === ''));
                    if (dis) li.style.setProperty('display', 'none', 'important');
                    return;
                  }
                  var style = window.getComputedStyle(li);
                  var href = (li.querySelector('a') || li).getAttribute('href');
                  var ariaDisabled = li.getAttribute('aria-disabled') === 'true';
                  var className = (li.className || '').toLowerCase();
                  var isDisabled = ariaDisabled
                    || /disabled|unavailable|no-access|off|no-/.test(className)
                    || style.pointerEvents === 'none'
                    || style.opacity === '0'
                    || (href !== null && (href === '#' || href === ''))
                    || shouldHideByLabel(li);
                  if (isDisabled) li.style.setProperty('display', 'none', 'important');
                });
              });
              translateStarPassToFrench(wrap);
            }

            var starPassObs = null;
            function waitForStarPass() {
              if (document.querySelector('.sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block')) {
                hideUnavailableStarPassButtons();
                return;
              }
              if (starPassObs) starPassObs.disconnect();
              starPassObs = new MutationObserver(function() {
                if (document.querySelector('.sp-wrap [id^="starpass_"] #sk-kit #sk-access-type-block')) {
                  starPassObs.disconnect();
                  starPassObs = null;
                  hideUnavailableStarPassButtons();
                }
              });
              var mount = document.getElementById('starpassMount');
              if (mount) starPassObs.observe(mount, { childList: true, subtree: true });
              setTimeout(function(){ hideUnavailableStarPassButtons(); }, 2500);
            }

            function mountStarPassWidget(idd, datasSuffix) {
              var mount = document.getElementById('starpassMount');
              if (!mount) return;
              mount.innerHTML = '';
              var div = document.createElement('div');
              div.id = 'starpass_' + idd;
              mount.appendChild(div);
              var s = document.createElement('script');
              s.type = 'text/javascript';
              s.src = 'https://script.starpass.fr/script.php?idd=' + encodeURIComponent(idd)
                + '&datas=' + encodeURIComponent(String(<?= (int)$membre['id'] ?>) + ':' + datasSuffix)
                + '&lang=fr';
              mount.appendChild(s);
              setTimeout(waitForStarPass, 80);
            }

            function syncWeekendFunUi() {
              if (packType !== 'weekend' || !spRoot) return;
              var chk = document.getElementById('chkWeekendFun');
              var iddFun = parseInt(spRoot.getAttribute('data-idd-fun') || '0', 10);
              var iddBase = parseInt(spRoot.getAttribute('data-idd-base') || '0', 10);
              var funOn = chk && chk.checked && iddFun > 0;
              var idd = funOn ? iddFun : iddBase;
              var datasType = funOn ? 'weekend_fun' : 'weekend';
              var pb = spRoot.getAttribute('data-prix-base') || '10';
              var pf = spRoot.getAttribute('data-prix-fun') || '20';
              var pr = funOn ? pf : pb;
              spRoot.setAttribute('data-offer-price', String(pr).replace(',', '.'));
              var elN = document.getElementById('offrePrixNum');
              var elA = document.getElementById('spPayAmount');
              if (elN) elN.textContent = pr;
              if (elA) elA.textContent = pr;
              mountStarPassWidget(idd, datasType);
            }

            if (packType === 'weekend') {
              var chkW = document.getElementById('chkWeekendFun');
              if (chkW && !chkW.disabled) chkW.addEventListener('change', syncWeekendFunUi);
            }

            if (document.readyState === 'loading') {
              document.addEventListener('DOMContentLoaded', waitForStarPass);
            } else {
              setTimeout(waitForStarPass, 100);
            }
          })();
          </script>
        </div>
        <div class="note-box" style="margin-top:1rem;">
          <p><strong>⚠️ Important :</strong> Après paiement StarPass, vous serez automatiquement redirigé vers votre espace membre. Si ce n'est pas le cas, contactez le support depuis votre dashboard.</p>
        </div>
      </div>

      <div class="payment-crypto-column">
      <div class="payment-block" id="crypto">
        <div class="block-title">₿ Crypto-monnaie</div>
        <div class="block-desc">Choisissez votre crypto, générez une adresse unique et payez — activation automatique en quelques minutes</div>

        <!-- Étape 1 : Choix de la crypto -->
        <div id="np-step1">
          <?php
          $anniv_eligible = isAnniversaireEligible((int)$membre['id']);
          $anniv_pct = $anniv_eligible ? getAnniversairePercent($type) : 0;
          ?>
          <?php if ($anniv_eligible && $anniv_pct > 0): ?>
          <div class="np-promo-anniv">
            🎂 Réduction anniversaire : <strong>-<?= $anniv_pct ?>%</strong> sur cette formule (utilisable une fois cette année)
          </div>
          <?php endif; ?>
          <div class="form-group np-code-wrap" style="margin-bottom:1rem;">
            <label for="code_promo" style="display:block;font-size:0.8rem;color:var(--txt3);margin-bottom:0.4rem;">Code promo (optionnel)</label>
            <input type="text" id="code_promo" name="code_promo" placeholder="Ex. BIENVENUE" maxlength="50" autocomplete="off"
                   style="width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:0.6rem 0.9rem;color:var(--txt);font-size:0.9rem;">
          </div>
          <div class="crypto-tabs" id="cryptoTabs">
            <button class="crypto-tab active" data-coin="btc"  onclick="selectCoin('btc',this)">₿ BTC</button>
            <button class="crypto-tab"        data-coin="eth"  onclick="selectCoin('eth',this)">Ξ ETH</button>
            <button class="crypto-tab"        data-coin="usdc" onclick="selectCoin('usdc',this)">◎ USDC</button>
            <button class="crypto-tab"        data-coin="sol"  onclick="selectCoin('sol',this)">◎ SOL</button>
            <button class="crypto-tab"        data-coin="bnb"  onclick="selectCoin('bnb',this)">⬡ BNB</button>
          </div>

          <div class="np-info-box" id="np-coin-info">
            <span class="np-coin-label">₿ Bitcoin (BTC)</span>
            <span class="np-network-label">Réseau Bitcoin</span>
          </div>

          <button class="btn-generate" id="btnGenerate" onclick="genererAdresse()">
            ⚡ Générer mon adresse de paiement
          </button>
          <p class="np-generate-hint">Une adresse unique sera créée pour ce paiement · Valable 60 min</p>
        </div>

        <!-- Étape 2 : Affichage de l'adresse générée -->
        <div id="np-step2" class="hidden">
          <div class="np-address-block">
            <div class="np-step-label">Envoie exactement ce montant à cette adresse :</div>

            <div class="np-amount-box">
              <span class="np-amount-value" id="np-amount">—</span>
              <span class="np-amount-currency" id="np-currency">BTC</span>
            </div>

            <div class="np-network-tag" id="np-network-tag">Réseau Bitcoin</div>

            <div class="wallet-box" style="margin-top:1rem;">
              <span class="wallet-addr" id="np-address">—</span>
              <button class="copy-btn" onclick="copyText('np-address')">📋 Copier</button>
            </div>

            <div class="np-warning">
              ⚠️ Envoie <strong>uniquement</strong> sur le réseau indiqué ci-dessus.
              Mauvais réseau = fonds perdus définitivement.
            </div>

            <!-- Timer de validité -->
            <div class="np-timer-box">
              ⏱️ Adresse valide encore : <strong id="np-countdown">60:00</strong>
            </div>
          </div>

          <!-- Étape 3 : Suivi du statut en temps réel -->
          <div class="np-status-box" id="np-status-box">
            <div class="np-status-icon" id="np-status-icon">⏳</div>
            <div class="np-status-msg" id="np-status-msg">En attente de ton virement crypto…</div>
            <div class="np-status-sub">Vérification automatique toutes les 15 secondes</div>
          </div>

          <button class="btn-reset" onclick="resetCrypto()">← Choisir une autre crypto</button>
        </div>

        <!-- Étape 4 : Succès -->
        <div id="np-step3" class="hidden">
          <div class="np-success-box">
            <div class="np-success-icon">🎉</div>
            <div class="np-success-title">Paiement confirmé !</div>
            <div class="np-success-desc">Ton accès est maintenant actif. Redirection vers ton espace…</div>
          </div>
        </div>

        <div class="sec-badges">
          <span class="sec-badge">🔒 NOWPayments sécurisé</span>
          <span class="sec-badge">✓ Activation automatique</span>
          <span class="sec-badge">⚡ Sans intervention manuelle</span>
          <span class="sec-badge">🛡️ 0.5% de frais seulement</span>
        </div>
      </div>

      <?php if ($type !== 'tennis'): ?>
      <div class="payment-block stake-pay-block">
        <div class="stake-wrap">
          <div class="stake-sep">Bonus Partenaire</div>
          <a href="https://stake.bet/?c=n26yI0vn" target="_blank" rel="noopener noreferrer nofollow" class="stake-btn">🎰 S'inscrire sur Stake · Lien bonus</a>
          <div class="stake-offer">1 mois <span class="vip-mini"><svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs><linearGradient id="vmStakePay" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c8960c"/><stop offset="40%" stop-color="#f5c842"/><stop offset="65%" stop-color="#fffbe6"/><stop offset="100%" stop-color="#e8a020"/></linearGradient></defs><rect x="6" y="30" width="32" height="6" rx="3" fill="url(#vmStakePay)"/><path d="M6 30 L6 18 L14 24 L22 10 L30 24 L38 18 L38 30 Z" fill="url(#vmStakePay)"/><circle cx="6" cy="17" r="3" fill="url(#vmStakePay)"/><circle cx="22" cy="9" r="3.5" fill="url(#vmStakePay)"/><circle cx="38" cy="17" r="3" fill="url(#vmStakePay)"/></svg><span class="vip-mini-label"><span class="vip-mini-txt vip-mini-vip">VIP</span><span class="vip-mini-txt vip-mini-max">MAX</span></span></span> offert via ce lien</div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($type === 'tennis'): ?>
      <div class="stake-tennis-block">
        <div class="stake-tennis-title">Bonus Partenaire Stake</div>
        <div class="stake-tennis-desc">Crée ton compte Stake avec notre lien partenaire et débloque un bonus exclusif StratEdge.</div>
        <a href="https://stake.bet/?c=2bd992d384" target="_blank" rel="noopener noreferrer nofollow" class="btn-stake-tennis">🎁 S'inscrire sur Stake</a>
        <div class="stake-tennis-note">Lien bonus officiel · 1 mois StratEdge offert</div>
      </div>
      <?php endif; ?>

      <?php if ($type !== 'tennis'): ?>
      <div class="other-offers">
        <div class="other-title">Autres formules disponibles</div>
        <div class="other-grid">
          <?php
          $routesDedicaces = ['daily','weekend','weekly'];
          foreach ($offres as $k => $off):
            if ($k === $type) continue;
            $url = in_array($k, $routesDedicaces) ? "offre-{$k}.php" : "offre.php?type={$k}";
          ?>
          <a href="<?= $url ?>" class="other-pill">
            <?= $off['emoji'] ?> <?= $off['titre'] ?>
            <span class="pill-price" style="color:<?= $off['color'] ?>"><?= $off['prix'] ?>€</span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      </div><!-- /.payment-crypto-column -->

      </div><!-- /.payment-methods-row -->

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer-main.php'; ?>
<script>
// ── Données crypto ─────────────────────────────────────────
const cryptoData = {
  btc:  { label: '₿ Bitcoin (BTC)',        network: 'Réseau Bitcoin' },
  eth:  { label: 'Ξ Ethereum (ETH)',        network: 'Réseau Ethereum (ERC-20)' },
  usdc: { label: '◎ USDC Polygon',          network: 'Réseau Polygon (MATIC)' },
  sol:  { label: '◎ Solana (SOL)',           network: 'Réseau Solana' },
  bnb:  { label: '⬡ BNB — BNB Chain',       network: 'Réseau BNB Chain (BEP-20)' },
};

let selectedCoin   = 'btc';
let currentPayId   = null;
let pollInterval   = null;
let countdownTimer = null;

// ── Sélection de la crypto ─────────────────────────────────
function selectCoin(coin, btn) {
  selectedCoin = coin;
  document.querySelectorAll('.crypto-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const info = cryptoData[coin];
  document.getElementById('np-coin-info').innerHTML =
    `<span class="np-coin-label">${info.label}</span>
     <span class="np-network-label">${info.network}</span>`;
}

// ── Générer l'adresse de paiement ─────────────────────────
async function genererAdresse() {
  const btn = document.getElementById('btnGenerate');
  btn.disabled = true;
  btn.textContent = '⏳ Génération en cours…';

  const chkFun = document.getElementById('chkWeekendFun');
  const optFun = (chkFun && chkFun.checked && !chkFun.disabled) ? '&option_fun=1' : '';

  try {
    const resp = await fetch('nowpayments-create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `crypto=${selectedCoin}&offre=<?= $type ?>&code_promo=${encodeURIComponent((document.getElementById('code_promo')&&document.getElementById('code_promo').value)||'')}` + optFun,
    });

    const data = await resp.json();

    if (data.error) {
      alert('Erreur : ' + data.error);
      btn.disabled = false;
      btn.textContent = '⚡ Générer mon adresse de paiement';
      return;
    }

    currentPayId = data.payment_id;
    document.getElementById('np-amount').textContent   = data.pay_amount;
    document.getElementById('np-currency').textContent = data.pay_currency;
    document.getElementById('np-address').textContent  = data.pay_address;
    document.getElementById('np-network-tag').textContent = data.network;

    document.getElementById('np-step1').classList.add('hidden');
    document.getElementById('np-step2').classList.remove('hidden');

    lancerCountdown(data.expires_at);
    lancerPolling();

  } catch (e) {
    alert('Erreur réseau. Réessaie dans quelques secondes.');
    btn.disabled = false;
    btn.textContent = '⚡ Générer mon adresse de paiement';
  }
}

// ── Countdown de validité ──────────────────────────────────
function lancerCountdown(expiresAt) {
  if (countdownTimer) clearInterval(countdownTimer);
  const el = document.getElementById('np-countdown');
  countdownTimer = setInterval(() => {
    const reste = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
    const min   = Math.floor(reste / 60).toString().padStart(2, '0');
    const sec   = (reste % 60).toString().padStart(2, '0');
    el.textContent = `${min}:${sec}`;
    if (reste === 0) {
      clearInterval(countdownTimer);
      setStatus('expired', '⌛', 'Adresse expirée. Clique sur "← Choisir une autre crypto" pour recommencer.');
      clearInterval(pollInterval);
    }
  }, 1000);
}

// ── Polling du statut ──────────────────────────────────────
function lancerPolling() {
  if (pollInterval) clearInterval(pollInterval);
  setTimeout(checkStatut, 10000);
  pollInterval = setInterval(checkStatut, 15000);
}

async function checkStatut() {
  if (!currentPayId) return;
  try {
    const resp = await fetch(`nowpayments-status.php?payment_id=${currentPayId}`);
    const data = await resp.json();
    const icons = {
      waiting: '⏳', confirming: '🔄', confirmed: '✅',
      sending: '🔄', finished: '✅', failed: '❌',
      refunded: '↩️', expired: '⌛',
    };
    setStatus(data.status, icons[data.status] || '⏳', data.message);
    // Succès uniquement sur 'finished' (paiement réellement reçu et traité)
    if (data.status === 'finished' && data.redirect) {
      clearInterval(pollInterval);
      clearInterval(countdownTimer);
      afficherSucces();
      setTimeout(() => { window.location.href = data.redirect; }, 3000);
    }
    // Arrêt du polling sur erreur définitive
    if (['failed', 'refunded', 'expired'].includes(data.status)) {
      clearInterval(pollInterval);
    }
  } catch (e) { /* silencieux */ }
}

function setStatus(status, icon, msg) {
  document.getElementById('np-status-icon').textContent = icon;
  document.getElementById('np-status-msg').textContent  = msg;
  document.getElementById('np-status-box').className = 'np-status-box np-status-' + status;
}

function afficherSucces() {
  document.getElementById('np-step2').classList.add('hidden');
  document.getElementById('np-step3').classList.remove('hidden');
}

function resetCrypto() {
  if (pollInterval)   clearInterval(pollInterval);
  if (countdownTimer) clearInterval(countdownTimer);
  currentPayId = null;
  document.getElementById('np-step2').classList.add('hidden');
  document.getElementById('np-step3').classList.add('hidden');
  document.getElementById('np-step1').classList.remove('hidden');
  const btn = document.getElementById('btnGenerate');
  btn.disabled = false;
  btn.textContent = '⚡ Générer mon adresse de paiement';
}

function copyText(id) {
  const el  = document.getElementById(id);
  const btn = el.nextElementSibling;
  navigator.clipboard.writeText(el.textContent.trim()).then(() => {
    btn.textContent = '✅ Copié !';
    setTimeout(() => { btn.textContent = '📋 Copier'; }, 2000);
  });
}
</script>

<script>
// Scroll auto vers crypto + masquer StarPass si #crypto dans l'URL
(function() {
  if (window.location.hash === '#crypto') {
    // Masquer le bloc StarPass immédiatement
    var spBlock = document.querySelector('.payment-block--starpass');
    if (spBlock) spBlock.style.display = 'none';
    // Remonter en haut de page instantanément
    window.scrollTo(0, 0);
    // Puis scroller proprement vers le bloc crypto avec offset pour la nav sticky
    setTimeout(function() {
      var el = document.getElementById('crypto');
      if (el) {
        var navH = document.querySelector('nav') ? document.querySelector('nav').offsetHeight : 70;
        var top  = el.getBoundingClientRect().top + window.pageYOffset - navH - 20;
        window.scrollTo({ top: top, behavior: 'smooth' });
      }
    }, 100);
  }
})();
</script>
</body>
</html>
