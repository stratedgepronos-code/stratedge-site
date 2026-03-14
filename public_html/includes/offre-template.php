<?php
// ============================================================
// STRATEDGE — Page de paiement — Design v2
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/promo.php';
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
        'idd'        => '446904',
        'idp'        => '263723',
        'duree'      => 'Du vendredi 00h00 au dimanche 23h59',
        'avantages'  => ['Accès bets Safe & Fun', 'Bets LIVE par mail &amp; Push', 'Tous les matchs du week-end', 'Sans engagement'],
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
        'idd'        => '446909',
        'idp'        => '263723',
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
];

if (!isset($offres[$type])) { header('Location: /#pricing'); exit; }

$o      = $offres[$type];
$membre = getMembre();
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
      padding:0 2rem;
    }
    .nav-inner {
      max-width:1100px; margin:0 auto;
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

    /* ── PAGE ── */
    .page {
      max-width:1100px; margin:0 auto;
      padding:4rem 2rem 6rem;
      position:relative; z-index:1;
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

    /* ── LAYOUT ── */
    .layout {
      display:grid;
      grid-template-columns:340px 1fr;
      gap:2rem;
      align-items:start;
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
    .payment-col { animation:fadeUp 0.7s ease 0.2s both; }

    .payment-block {
      background:var(--bg2);
      border:1px solid var(--border);
      border-radius:24px;
      padding:2rem;
      margin-bottom:1.5rem;
      position:relative; overflow:hidden;
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

    /* StarPass block — style StratEdge */
    .payment-block.starpass-block {
      background:linear-gradient(165deg, var(--bg2) 0%, rgba(0,0,0,0.35) 100%);
      border:1px solid rgba(255,255,255,0.08);
      border-radius:20px;
      padding:0;
      overflow:hidden;
      box-shadow:0 8px 32px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.03) inset;
    }
    .payment-block.starpass-block::before {
      height:3px;
      background:var(--grad);
      opacity:1;
    }
    .payment-block.starpass-block .block-title {
      padding:1.25rem 1.75rem 0.5rem;
      font-size:0.7rem;
      letter-spacing:2.5px;
      color:var(--color);
    }
    .payment-block.starpass-block .block-desc {
      padding:0 1.75rem 1.25rem;
      margin-bottom:0;
      font-size:0.88rem;
      line-height:1.55;
      color:var(--txt2);
    }
    .payment-block.starpass-block .block-desc strong { color:var(--color); }

    .sp-wrap {
      background:rgba(0,0,0,0.25);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:14px;
      padding:1.75rem 1.75rem 1.5rem;
      text-align:center;
      margin:0 1.25rem 1.25rem;
    }
    .sp-wrap p {
      color:var(--txt2);
      font-size:0.9rem;
      margin-bottom:1.25rem;
      font-family:'Rajdhani',sans-serif;
      font-weight:500;
    }
    .sp-wrap strong { color:var(--color); font-weight:700; }
    .sp-wrap [id^="starpass_"] {
      min-height:100px;
      border-radius:12px;
      overflow:hidden;
      background:rgba(0,0,0,0.2);
      display:block;
    }
    .sp-wrap iframe {
      border:none !important;
      border-radius:12px !important;
      width:100% !important;
      max-width:100% !important;
      min-height:100px !important;
    }

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

    /* Note (dans bloc StarPass) */
    .payment-block.starpass-block .note-box {
      background:rgba(255,255,255,0.03);
      border:1px solid rgba(255,255,255,0.06);
      border-radius:12px;
      padding:1rem 1.35rem;
      margin:0 1.75rem 1.5rem;
    }
    .payment-block.starpass-block .note-box p {
      font-size:0.8rem;
      color:var(--txt3);
      line-height:1.6;
    }
    .payment-block.starpass-block .note-box strong { color:var(--txt2); }
    .note-box {
      background:rgba(255,193,7,0.05);
      border:1px solid rgba(255,193,7,0.18);
      border-radius:12px; padding:1rem 1.2rem;
      margin-top:1.2rem;
    }
    .note-box p { font-size:0.8rem; color:#a09040; line-height:1.6; }
    .note-box strong { color:#ffc107; }

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
      .payment-block.starpass-block{padding:0;}
      .np-info-box{flex-direction:column;align-items:flex-start;gap:0.4rem;}
      .np-amount-value{font-size:1.5rem;}
      .np-success-title{font-size:1.1rem;}
      .btn-generate{font-size:0.85rem;padding:0.85rem;}
      .btn-crypto{font-size:0.72rem;padding:0.8rem;}
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
      .avantage{font-size:0.85rem;padding:0.5rem 0;}
      .payment-block { padding:1.2rem; border-radius:14px; }
      .payment-block.starpass-block .block-title,
      .payment-block.starpass-block .block-desc { padding-left:1rem; padding-right:1rem; }
      .payment-block.starpass-block .note-box { margin-left:0.8rem; margin-right:0.8rem; margin-bottom:1rem; }
      .block-title{font-size:0.72rem;}
      .block-desc{font-size:0.78rem;}
      .sp-wrap { padding:1.1rem 0.9rem; margin-left:0.8rem; margin-right:0.8rem; margin-bottom:1rem; }
      .sp-wrap p{font-size:0.85rem;}
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
    <a href="/#pricing" class="nav-back">← Toutes les formules</a>
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
      <div class="offre-card">
        <div class="offre-card-top">
          <div class="offre-badge"><?= $o['badge'] ?></div>
          <div class="offre-video-wrap">
            <video autoplay loop muted playsinline>
              <source src="<?= $o['video'] ?>" type="video/mp4">
            </video>
          </div>
          <div class="offre-prix">
            <span class="cur">€</span><span class="num"><?= $o['prix'] ?></span>
          </div>
          <div class="offre-duree"><?= $o['duree'] ?></div>
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

      <!-- StarPass -->
      <div class="payment-block starpass-block">
        <?php if ($type === 'daily'): ?>
          <div class="block-title">📱 Payer par SMS · Appel · CB · Paysafecard</div>
          <div class="block-desc">Paiement sécurisé par <strong>StarPass</strong> — SMS, appel surtaxé, carte bancaire ou Paysafecard. Simple et rapide.</div>
        <?php else: ?>
          <div class="block-title">💳 Payer par CB · PayPal · Paysafecard · Internet+</div>
          <div class="block-desc">Paiement sécurisé par <strong>StarPass</strong> — carte bancaire, PayPal, Paysafecard ou Internet+. Activation immédiate.</div>
        <?php endif; ?>
        <div class="sp-wrap">
          <p>Clique sur le bouton ci-dessous pour payer <strong><?= $o['prix'] ?>€</strong></p>
          <div id="starpass_<?= $o['idd'] ?>"></div>
          <script type="text/javascript"
            src="https://script.starpass.fr/script.php?idd=<?= $o['idd'] ?>&datas=<?= urlencode($membre['id'] . ':' . $type) ?>">
          </script>
        </div>
        <div class="note-box">
          <p><strong>À savoir :</strong> Après paiement, tu seras redirigé vers ton espace membre. Sinon, ouvre un ticket SAV depuis ton dashboard.</p>
        </div>
      </div>

      <?php if ($type === 'tennis'): ?>
      <div class="stake-tennis-block">
        <div class="stake-tennis-title">Bonus Partenaire Stake</div>
        <div class="stake-tennis-desc">Crée ton compte Stake avec notre lien partenaire et débloque un bonus exclusif StratEdge.</div>
        <a href="https://stake.bet/?c=2bd992d384" target="_blank" rel="noopener noreferrer nofollow" class="btn-stake-tennis">🎁 S'inscrire sur Stake</a>
        <div class="stake-tennis-note">Lien bonus officiel · 1 mois StratEdge offert</div>
      </div>
      <?php endif; ?>

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

      <!-- Autres offres (masqué pour tennis) -->
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

  try {
    const resp = await fetch('nowpayments-create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `crypto=${selectedCoin}&offre=<?= $type ?>&code_promo=${encodeURIComponent((document.getElementById('code_promo')&&document.getElementById('code_promo').value)||'')}`,
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
    var spBlock = document.querySelector('.payment-block:first-of-type');
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
