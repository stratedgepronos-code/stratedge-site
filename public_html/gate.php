<?php
/**
 * StratEdge – Gate de protection v4
 * 1. Placer : public_html/gate.php
 * 2. Ajouter en toute première ligne de index.php :  require_once __DIR__ . '/gate.php';
 */
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { return; }

require_once __DIR__ . '/includes/visiteurs-log.php';

$gate_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gate_submit'])) {
    $csrf = $_POST['gate_csrf'] ?? '';
    if (!verifyCsrf($csrf)) {
        $gate_error = 'Token invalide. Recharge la page.';
    } else {
        $email    = trim($_POST['gate_email']    ?? '');
        $password =      $_POST['gate_password'] ?? '';
        if (empty($email) || empty($password)) {
            $gate_error = 'Remplis tous les champs.';
        } else {
            $result = loginMembre($email, $password);
            if ($result['success']) {
                $redirect = $_GET['redirect'] ?? '/';
                if (!preg_match('#^/#', $redirect)) $redirect = '/';
                header('Location: ' . $redirect);
                exit;
            } else {
                $gate_error = $result['error'] ?? 'Identifiants incorrects.';
            }
        }
    }
}

log_visite();
stratedge_render_gate($gate_error, csrfToken());
exit;

function stratedge_render_gate(string $error, string $csrf_token): void {
    $mascotte_url = '/assets/images/mascotte.png';
    $LOGO_B64     = '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';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StratEdge – Accès Membres</title>
<link rel="icon" href="/assets/images/mascotte.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --pink:#ff2d78; --pink-dim:#a0002e;
  --cyan:#00d4ff;
  --dark:#07070f;
  --card:rgba(10,10,20,0.93);
  --border:rgba(255,45,120,0.25);
  --text:#eeeef6; --muted:rgba(238,238,246,0.45);
}
html,body{height:100%;overflow:hidden;font-family:"Rajdhani",sans-serif;background:var(--dark);color:var(--text)}

/* ── FOND ── */
.bg{position:fixed;inset:0;z-index:0;
  background:
    radial-gradient(ellipse 80% 70% at 10% 50%,rgba(255,45,120,.16) 0%,transparent 60%),
    radial-gradient(ellipse 60% 80% at 90% 50%,rgba(0,212,255,.09) 0%,transparent 60%),
    linear-gradient(180deg,#040407 0%,#08080f 100%)}
.bg::before{content:"";position:absolute;inset:0;
  background-image:linear-gradient(rgba(255,45,120,.03) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,45,120,.03) 1px,transparent 1px);
  background-size:60px 60px;
  mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black 0%,transparent 100%)}

/* ── BARRE BAS ── */
.bottom-bar{position:fixed;bottom:0;left:0;right:0;height:3px;z-index:999;
  background:linear-gradient(90deg,var(--pink),var(--cyan),var(--pink));
  background-size:200% 100%;animation:slidebar 4s linear infinite}
@keyframes slidebar{0%{background-position:0%}100%{background-position:200%}}

/* ── LAYOUT ── */
.wrapper{position:relative;z-index:10;display:flex;height:100vh;width:100vw}

/* ══ GAUCHE ══ */
.left{flex:1;position:relative;display:flex;align-items:flex-end;justify-content:center;overflow:hidden}

/* Particules — dans .left, derrière la mascotte (z-index:1 < mascot z-index:3) */
.particles{position:absolute;inset:0;z-index:1;pointer-events:none;overflow:hidden}
.particle{position:absolute;bottom:-20px;border-radius:50%;animation:bubble linear infinite}
@keyframes bubble{
  0%  {transform:translateY(0) scale(0);opacity:0}
  8%  {opacity:.8}
  85% {opacity:.5}
  100%{transform:translateY(-110vh) scale(1.2);opacity:0}
}

/* Ki DBZ – canvas */
#ki-canvas{position:absolute;inset:0;z-index:2;pointer-events:none}

/* Anneaux au sol */
.ground-ring{
  position:absolute;bottom:2%;left:50%;transform:translateX(-50%);
  border-radius:50%;border:2px solid;pointer-events:none;z-index:2}
.gr1{width:260px;height:50px;border-color:rgba(255,45,120,.7);
  box-shadow:0 0 14px rgba(255,45,120,.5);animation:gr1 1.6s ease-in-out infinite}
.gr2{width:420px;height:80px;border-color:rgba(255,45,120,.35);animation:gr2 2s ease-in-out infinite .2s}
.gr3{width:580px;height:110px;border-color:rgba(0,212,255,.2);animation:gr3 2.5s ease-in-out infinite .5s}
@keyframes gr1{0%,100%{opacity:.9;transform:translateX(-50%) scaleY(1)}50%{opacity:.4;transform:translateX(-50%) scaleY(1.15)}}
@keyframes gr2{0%,100%{opacity:.5;transform:translateX(-50%)}50%{opacity:.2;transform:translateX(-50%) scaleY(1.1) translateY(-4px)}}
@keyframes gr3{0%,100%{opacity:.25}50%{opacity:.08}}

/* Sol glow */
.ground-glow{
  position:absolute;bottom:0;left:50%;transform:translateX(-50%);
  width:480px;height:55px;z-index:2;pointer-events:none;
  background:radial-gradient(ellipse,rgba(255,45,120,.55) 0%,rgba(255,45,120,.1) 55%,transparent 80%);
  filter:blur(8px);animation:gg 1.5s ease-in-out infinite}
@keyframes gg{0%,100%{opacity:.8;transform:translateX(-50%) scaleX(1)}50%{opacity:.45;transform:translateX(-50%) scaleX(1.2)}}

/* Mascotte — z-index:3 pour passer devant les particules (z:1) et le canvas ki (z:2) */
/* ══ COUNTDOWN TIMER ══ */
.countdown{
  display:flex;align-items:center;gap:6px;
  margin:22px 0 14px;
  animation:fadeinL 1s 1s both}
.cd-block{
  display:flex;flex-direction:column;align-items:center;
  background:rgba(255,45,120,.08);
  border:1px solid rgba(255,45,120,.25);
  border-radius:8px;padding:8px 12px;min-width:58px}
.cd-num{
  font-family:"Orbitron",sans-serif;font-size:26px;font-weight:700;
  color:#fff;line-height:1;
  text-shadow:0 0 20px rgba(255,45,120,.8),0 0 40px rgba(255,45,120,.3);
  animation:cd-tick .1s ease}
.cd-label{font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-top:4px}
.cd-sep{font-family:"Orbitron",sans-serif;font-size:22px;font-weight:700;color:var(--pink);
  margin-bottom:14px;animation:cd-blink 1s step-end infinite;opacity:.8}
@keyframes cd-blink{0%,100%{opacity:.8}50%{opacity:.2}}
@keyframes cd-tick{0%{transform:scale(1.15);color:var(--pink)}100%{transform:scale(1);color:#fff}}

/* Ligne date lancement */
.launch-date{
  font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.6px;
  margin-bottom:20px;
  animation:fadeinL 1s 1.2s both}

/* ══ ANIMATIONS MASCOTTE ══ */
@keyframes mascot-fadein{
  0%  {opacity:0}
  100%{opacity:1}
}
@keyframes mascot-breathe{
  0%,100%{transform:translateY(0) scale(1)}
  50%    {transform:translateY(-8px) scale(1.008)}
}
@keyframes mascot-eyes{
  0%,100%{filter:
    drop-shadow(0 0 45px rgba(255,45,120,.50))
    drop-shadow(0 0 90px rgba(255,45,120,.20))}
  30%{filter:
    drop-shadow(0 0 55px rgba(255,45,120,.70))
    drop-shadow(0 0 110px rgba(255,45,120,.35))
    drop-shadow(0 0 8px rgba(255,160,200,.9))}
  60%{filter:
    drop-shadow(0 0 40px rgba(255,45,120,.45))
    drop-shadow(0 0 80px rgba(255,45,120,.15))}
}

.mascot-img{
  position:relative;z-index:3;
  height:96vh;width:auto;max-width:none;
  object-fit:contain;object-position:bottom center;
  filter:drop-shadow(0 0 45px rgba(255,45,120,.5)) drop-shadow(0 0 90px rgba(255,45,120,.2));
  animation:
    mascot-fadein   1.6s cubic-bezier(.22,1,.36,1) both,
    mascot-breathe  4.5s ease-in-out 2s infinite,
    mascot-eyes     3.2s ease-in-out 2s infinite;
}

/* Texte gauche */
.left-text{position:absolute;top:50%;left:44px;transform:translateY(-50%);z-index:4;max-width:290px}
.badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(255,45,120,.12);border:1px solid var(--pink);border-radius:4px;
  padding:6px 14px;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;
  color:var(--pink);margin-bottom:22px;animation:fadeinL 1s .4s both}
.badge::before{content:"";width:6px;height:6px;background:var(--pink);border-radius:50%;animation:blink 1.2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.15}}
.hero-title{font-family:"Bebas Neue",sans-serif;font-size:clamp(46px,5.5vw,80px);line-height:.88;letter-spacing:2px;color:#fff;animation:fadeinL 1s .6s both}
.hero-title em{font-style:normal;background:linear-gradient(135deg,var(--pink) 0%,var(--cyan) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero-sub{margin-top:18px;font-size:14px;font-weight:500;color:var(--muted);letter-spacing:.8px;line-height:1.65;animation:fadeinL 1s .8s both}
.stats{margin-top:28px;display:flex;flex-direction:column;gap:10px;animation:fadeinL 1s 1s both}
.stat{display:flex;align-items:center;gap:12px;font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.8px}
.dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
@keyframes fadeinL{from{opacity:0;transform:translateX(-18px)}to{opacity:1;transform:translateX(0)}}

/* Séparateur */
.divider{position:absolute;top:0;right:0;width:1px;height:100%;z-index:5;
  background:linear-gradient(180deg,transparent 0%,rgba(255,45,120,.5) 30%,rgba(0,212,255,.4) 70%,transparent 100%)}

/* ══ DROITE : LOGIN ══ */
.right{width:430px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;position:relative}

.site-logo{width:190px;margin-bottom:8px;mix-blend-mode:screen;animation:fadeinT .9s .2s both}
.tagline{font-size:10px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:var(--muted);margin-bottom:32px;animation:fadeinT .9s .4s both}
@keyframes fadeinT{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}

/* Card */
.card{
  width:100%;background:var(--card);border:1px solid var(--border);
  border-radius:18px;padding:36px 32px 30px;
  backdrop-filter:blur(24px);overflow:hidden;
  box-shadow:0 0 60px rgba(255,45,120,.08),0 30px 80px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.04);
  animation:card-in 1s .5s cubic-bezier(.22,1,.36,1) both}
@keyframes card-in{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}

/* Trait haut — overflow:hidden clippe naturellement sur le border-radius */
.card-bar{height:3px;margin:-36px -32px 32px;
  background:linear-gradient(90deg,var(--pink) 0%,var(--cyan) 50%,var(--pink) 100%);
  background-size:200% 100%;animation:slidebar 3s linear infinite}

.card-title{font-family:"Bebas Neue",sans-serif;font-size:30px;letter-spacing:3px;color:#fff;margin-bottom:4px}
.card-sub{font-size:13px;color:var(--muted);letter-spacing:.4px;margin-bottom:26px}

/* Champs */
.field{margin-bottom:18px}
.field label{display:block;font-size:10px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.field-wrap{position:relative}
.field-ico{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:16px;opacity:.35;pointer-events:none}
.input{
  width:100%;padding:13px 14px 13px 44px;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:9px;
  color:#fff;font-family:"Rajdhani",sans-serif;font-size:15px;font-weight:500;
  outline:none;transition:border-color .25s,background .25s,box-shadow .25s}
.input::placeholder{color:rgba(255,255,255,.18)}
.input:focus{border-color:var(--pink);background:rgba(255,45,120,.06);box-shadow:0 0 22px rgba(255,45,120,.18)}
.eye-btn{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px;line-height:1;transition:color .2s}
.eye-btn:hover{color:var(--pink)}

/* Remember */
.remember{display:flex;align-items:center;gap:10px;margin-bottom:24px}
.remember input{accent-color:var(--pink);width:16px;height:16px;cursor:pointer}
.remember label{font-size:13px;color:var(--muted);cursor:pointer}

/* Erreur */
.error-msg{display:flex;align-items:center;gap:8px;background:rgba(255,45,120,.1);border:1px solid rgba(255,45,120,.4);border-radius:9px;padding:12px 14px;margin-bottom:18px;font-size:13px;font-weight:600;color:var(--pink);animation:shake .4s}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}

/* ══ BOUTON — design sobre et premium ══ */
.btn-login{
  width:100%;padding:0;
  background:none;border:none;cursor:pointer;
  position:relative;border-radius:11px;
  transition:transform .2s}
.btn-login:hover{transform:translateY(-2px)}
.btn-login:active{transform:scale(.98)}
.btn-login:disabled{opacity:.55;cursor:not-allowed;transform:none}

/* Fond principal */
.btn-body{
  display:flex;align-items:center;justify-content:space-between;
  padding:0 22px;height:54px;border-radius:11px;
  background:linear-gradient(110deg,#1a0510 0%,#120420 50%,#081220 100%);
  border:1px solid transparent;
  /* gradient border via box-shadow interne */
  box-shadow:
    inset 0 0 0 1px rgba(255,45,120,.5),
    0 6px 30px rgba(255,45,120,.3),
    0 2px 8px rgba(0,0,0,.6);
  transition:box-shadow .3s;
  overflow:hidden;position:relative}
.btn-login:hover .btn-body{
  box-shadow:
    inset 0 0 0 1px rgba(255,45,120,.9),
    0 0 28px rgba(255,45,120,.5),
    0 0 55px rgba(255,45,120,.2),
    0 2px 8px rgba(0,0,0,.6)}

/* Ligne lumineuse qui traverse le bouton au hover */
.btn-body::after{
  content:"";position:absolute;top:0;left:-100%;
  width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);
  transform:skewX(-20deg);
  transition:left .5s ease;pointer-events:none}
.btn-login:hover .btn-body::after{left:150%}

/* Icône gauche */
.btn-icon-wrap{
  width:34px;height:34px;border-radius:8px;flex-shrink:0;
  background:linear-gradient(135deg,var(--pink),var(--pink-dim));
  display:flex;align-items:center;justify-content:center;
  font-size:17px;line-height:1;
  box-shadow:0 0 12px rgba(255,45,120,.6);
  animation:btn-icon-pulse 2.5s ease-in-out infinite}
@keyframes btn-icon-pulse{
  0%,100%{box-shadow:0 0 12px rgba(255,45,120,.6)}
  50%{box-shadow:0 0 22px rgba(255,45,120,.9),0 0 40px rgba(255,45,120,.3)}}

/* Texte centré */
.btn-text{
  flex:1;text-align:center;
  font-family:"Bebas Neue",sans-serif;font-size:18px;letter-spacing:4px;
  color:#fff;text-shadow:0 0 14px rgba(255,255,255,.25)}

/* Flèche droite */
.btn-arrow{
  flex-shrink:0;
  font-family:"Rajdhani",sans-serif;font-size:22px;font-weight:700;
  color:var(--cyan);
  text-shadow:0 0 10px rgba(0,212,255,.7);
  transition:transform .25s}
.btn-login:hover .btn-arrow{transform:translateX(5px)}

/* Footer card */
.card-footer{margin-top:20px;text-align:center;font-size:12px;color:var(--muted)}
.card-footer strong{color:var(--pink);font-weight:700}

/* Footer page */
.page-footer{position:absolute;bottom:20px;left:0;right:0;text-align:center;font-size:11px;color:rgba(255,255,255,.12);letter-spacing:1px}

/* ── RESPONSIVE ── */
@media(max-width:860px){
  html,body{overflow-y:auto;height:auto;overflow-x:hidden}
  .wrapper{flex-direction:column;height:auto;min-height:100dvh}
  .left{min-height:45vw;max-height:40dvh;align-items:flex-end;justify-content:center}
  .mascot-img{
    height:40dvh;max-height:350px;
    object-position:bottom center;
    animation:mascot-fadein 1.6s cubic-bezier(.22,1,.36,1) both;
    filter:drop-shadow(0 0 45px rgba(255,45,120,.5)) drop-shadow(0 0 90px rgba(255,45,120,.2));
  }
  .left-text{display:none}
  .divider{display:none}
  .ground-ring.gr1{width:160px;height:30px}
  .ground-ring.gr2{width:260px;height:50px}
  .ground-ring.gr3{width:360px;height:70px}
  .ground-glow{width:280px;height:35px}
  .right{width:100%;padding:24px 16px 60px}
  .site-logo{width:140px}
  .tagline{font-size:9px;margin-bottom:20px}
}
@media(max-width:480px){
  .left{min-height:35vw;max-height:30dvh}
  .mascot-img{height:30dvh;max-height:250px;animation:mascot-fadein 1.6s cubic-bezier(.22,1,.36,1) both;}
  .ground-ring.gr1{width:120px;height:22px}
  .ground-ring.gr2{width:200px;height:38px}
  .ground-ring.gr3{width:280px;height:55px}
  .ground-glow{width:200px;height:28px}
  .card{padding:24px 16px 20px}
  .card-bar{margin:-24px -16px 24px}
  .card-title{font-size:24px}
  .card-sub{font-size:12px;margin-bottom:20px}
  .input{padding:12px 12px 12px 40px;font-size:14px}
  .btn-body{height:50px;padding:0 16px}
  .btn-text{font-size:16px;letter-spacing:3px}
  .right{padding:16px 12px 50px}
  .site-logo{width:120px}
}
@media(max-width:360px){
  .left{max-height:25dvh}
  .mascot-img{max-height:200px}
  .ground-ring.gr1{width:100px;height:18px}
  .ground-ring.gr2{width:160px;height:30px}
  .ground-ring.gr3{display:none}
  .ground-glow{width:160px;height:22px}
  .card{padding:20px 14px 18px}
  .card-bar{margin:-20px -14px 20px}
  .right{padding:12px 10px 40px}
}
</style>
</head>
<body>

<div class="bg"></div>
<div class="bottom-bar"></div>

<div class="wrapper">

  <!-- ══ GAUCHE ══ -->
  <div class="left">

    <div class="left-text">
      <div class="badge">⚗️ Mode Laboratoire</div>

      <div class="hero-title">Le grand<br>coup<br><em>d'envoi.</em></div>

      <p class="hero-sub">
        Nos équipes terminent les phases de tests et de finitions.<br>
        On ne vous propose pas juste des pronos —<br>
        <strong style="color:#fff">on vous prépare une stratégie.</strong>
      </p>

      <!-- TIMER COUNTDOWN -->
      <div class="countdown" id="countdown">
        <div class="cd-block">
          <span class="cd-num" id="cd-days">--</span>
          <span class="cd-label">Jours</span>
        </div>
        <div class="cd-sep">:</div>
        <div class="cd-block">
          <span class="cd-num" id="cd-hours">--</span>
          <span class="cd-label">Heures</span>
        </div>
        <div class="cd-sep">:</div>
        <div class="cd-block">
          <span class="cd-num" id="cd-mins">--</span>
          <span class="cd-label">Minutes</span>
        </div>
        <div class="cd-sep">:</div>
        <div class="cd-block">
          <span class="cd-num" id="cd-secs">--</span>
          <span class="cd-label">Secondes</span>
        </div>
      </div>

      <p class="launch-date">🚀 Lancement le <strong style="color:var(--pink)">14 Avril 2026</strong> — Restez connectés.</p>

      <div class="stats">
        <div class="stat"><div class="dot" style="background:var(--pink)"></div>Analyse IA + Expertise humaine</div>
        <div class="stat"><div class="dot" style="background:var(--cyan)"></div>Value Bets identifiés en temps réel</div>
        <div class="stat"><div class="dot" style="background:linear-gradient(var(--pink),var(--cyan))"></div>Football &middot; Tennis &middot; Basketball &middot; Hockey</div>
        <div class="stat"><div class="dot" style="background:#fff;box-shadow:0 0 6px rgba(255,255,255,.6)"></div><span style="color:#fff;font-weight:700;letter-spacing:1.5px">Safe &nbsp;<span style="color:var(--muted)">—</span>&nbsp; Live Bet &nbsp;<span style="color:var(--muted)">—</span>&nbsp; Fun</span></div>
      </div>
    </div>

    <!-- Particules — DANS .left, z-index:1 → passent DERRIÈRE mascotte (z:3) -->
    <div class="particles" id="particles"></div>

    <!-- Canvas Ki DBZ — z-index:2 -->
    <canvas id="ki-canvas"></canvas>

    <!-- Anneaux sol -->
    <div class="ground-ring gr1"></div>
    <div class="ground-ring gr2"></div>
    <div class="ground-ring gr3"></div>
    <div class="ground-glow"></div>

    <!-- Mascotte — z-index:3 (devant particules et ki) -->
    <img src="<?= htmlspecialchars($mascotte_url) ?>" alt="Mascotte StratEdge" class="mascot-img" onerror="this.style.display='none'">

    <div class="divider"></div>
  </div>

  <!-- ══ DROITE ══ -->
  <div class="right">

    <!-- Logo avec fond transparent via mix-blend-mode:screen -->
    <img src="data:image/png;base64,<?= $LOGO_B64 ?>" alt="StratEdge" class="site-logo">

    <div class="tagline">Espace membres exclusif</div>

    <div class="card">
      <div class="card-bar"></div>

      <div class="card-title">Connexion</div>
      <div class="card-sub">Entre tes identifiants pour accéder à tes pronos.</div>

      <?php if(!empty($error)): ?>
      <div class="error-msg">⚠&nbsp; <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="" id="se-form" autocomplete="on">
        <input type="hidden" name="gate_submit" value="1">
        <input type="hidden" name="gate_csrf"   value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="field">
          <label for="gate_email">Adresse e-mail</label>
          <div class="field-wrap">
            <span class="field-ico">✉️</span>
            <input type="email" id="gate_email" name="gate_email" class="input"
              placeholder="ton@email.com" autocomplete="email"
              value="<?= htmlspecialchars($_POST['gate_email'] ?? '') ?>"
              required autofocus>
          </div>
        </div>

        <div class="field">
          <label for="gate_password">Mot de passe</label>
          <div class="field-wrap">
            <span class="field-ico">🔒</span>
            <input type="password" id="gate_password" name="gate_password" class="input"
              placeholder="Ton mot de passe" autocomplete="current-password"
              style="padding-right:48px" required>
            <button type="button" class="eye-btn" onclick="togglePwd()" title="Afficher">👁</button>
          </div>
        </div>

        <div class="remember">
          <input type="checkbox" id="rem" name="remember" value="1">
          <label for="rem">Se souvenir de moi</label>
        </div>

        <button type="submit" class="btn-login" id="se-btn">
          <div class="btn-body">
            <div class="btn-icon-wrap">⚡</div>
            <span class="btn-text">Accéder à StratEdge</span>
            <span class="btn-arrow">›</span>
          </div>
        </button>
      </form>

      <div class="card-footer">
        Pas encore membre ?&nbsp;<strong>Contacte l'équipe StratEdge</strong>
      </div>
    </div>

    <div class="page-footer">© 2026 StratEdge &nbsp;·&nbsp; Tous droits réservés &nbsp;·&nbsp; 18+</div>
  </div>
</div>

<script>
/* ══════════════════════════════════════
   PARTICULES — dans .left, remontent de BAS en HAUT
   z-index:1 → passent DERRIÈRE la mascotte (z:3)
══════════════════════════════════════ */
(function(){
  var container = document.getElementById('particles');
  var colors = ['#ff2d78','#00d4ff','#ff6bb0','#55e8ff','#ff1a55'];
  for(var i=0;i<45;i++){
    var el=document.createElement('div');
    el.className='particle';
    var s=Math.random()*5+1;
    /* left aléatoire sur toute la largeur du panneau gauche */
    el.style.cssText=[
      'width:'+s+'px',
      'height:'+s+'px',
      'left:'+(Math.random()*100)+'%',
      'background:'+colors[Math.floor(Math.random()*colors.length)],
      'animation-duration:'+(8+Math.random()*14)+'s',
      'animation-delay:-('+Math.random()*20+'s)', /* négatif = déjà en cours, pas bloquées en bas */
      'opacity:.6'
    ].join(';');
    container.appendChild(el);
  }
})();

/* ══════════════════════════════════════
   KI DBZ — Canvas : pilier central + rayons + éclairs
══════════════════════════════════════ */
(function(){
  var canvas=document.getElementById('ki-canvas');
  if(!canvas)return;
  var ctx=canvas.getContext('2d');

  var W,H,CX,CY_BASE;
  window.addEventListener('resize', function(){
    var p=canvas.parentElement;
    if(p){ canvas.width=p.offsetWidth; canvas.height=p.offsetHeight; }
  });

  /* ── Pilier Ki central ── */
  function drawPillar(t){
    var flicker=0.6+Math.sin(t*8)*0.2+Math.random()*0.15;
    var pillarH=H*0.85;
    var grd=ctx.createLinearGradient(CX,CY_BASE,CX,CY_BASE-pillarH);
    grd.addColorStop(0  ,'rgba(255,45,120,'+(0.55*flicker)+')');
    grd.addColorStop(0.3,'rgba(255,80,160,'+(0.35*flicker)+')');
    grd.addColorStop(0.7,'rgba(200,50,120,'+(0.15*flicker)+')');
    grd.addColorStop(1  ,'rgba(255,45,120,0)');
    ctx.save();
    /* Pilier gauche */
    ctx.fillStyle=grd;
    ctx.beginPath();
    ctx.ellipse(CX-18,CY_BASE-pillarH/2,14*(0.8+Math.sin(t*6)*.2),pillarH/2,0,0,Math.PI*2);
    ctx.fill();
    /* Pilier droit */
    ctx.beginPath();
    ctx.ellipse(CX+18,CY_BASE-pillarH/2,14*(0.8+Math.sin(t*7+1)*.2),pillarH/2,0,0,Math.PI*2);
    ctx.fill();
    /* Pilier central (plus lumineux) */
    var grd2=ctx.createLinearGradient(CX,CY_BASE,CX,CY_BASE-pillarH*0.9);
    grd2.addColorStop(0,'rgba(255,255,255,'+(0.18*flicker)+')');
    grd2.addColorStop(.2,'rgba(255,100,180,'+(0.22*flicker)+')');
    grd2.addColorStop(.7,'rgba(255,45,120,'+(0.08*flicker)+')');
    grd2.addColorStop(1,'rgba(0,0,0,0)');
    ctx.fillStyle=grd2;
    ctx.beginPath();
    ctx.ellipse(CX,CY_BASE-pillarH*.45,28*(0.85+Math.sin(t*5)*.15),pillarH*.45,0,0,Math.PI*2);
    ctx.fill();
    ctx.restore();
  }

  /* ── Rayons d'énergie ── (spikes DBZ vers le haut) */
  var RAYS=[];
  function spawnRay(){
    /* Angles entre π*1.1 (légèrement à gauche-haut) et π*1.9 (légèrement à droite-haut)
       En canvas (y vers le bas) : sin négatif = vers le haut. π*1.5 = droit vers le haut. */
    var angle=Math.PI*1.05+Math.random()*Math.PI*0.9;
    var len=80+Math.random()*200;
    var thick=0.6+Math.random()*2.2;
    /* Point de départ : silhouette du corps (zone centrale, entre pieds et épaules) */
    var spawnY=CY_BASE-(H*.1+Math.random()*H*.55);
    var spawnX=CX+(Math.random()-.5)*80;
    RAYS.push({angle:angle,len:len,thick:thick,
      color:Math.random()>.7?'rgba(0,212,255,':'rgba(255,45,120,',
      life:0,maxLife:5+Math.floor(Math.random()*9),
      x:spawnX, y:spawnY});
  }
  function drawRays(){
    RAYS=RAYS.filter(function(r){
      var fade=1-(r.life/r.maxLife);
      ctx.save();
      ctx.shadowColor=r.color+'1)';ctx.shadowBlur=8;
      ctx.strokeStyle=r.color+(fade*.8).toFixed(2)+')';
      ctx.lineWidth=r.thick;ctx.lineCap='round';
      ctx.beginPath();
      ctx.moveTo(r.x,r.y);
      /* Zigzag sur la longueur */
      var steps=5,sx=r.x,sy=r.y;
      for(var i=1;i<=steps;i++){
        var f=i/steps;
        var jitter=(Math.random()-.5)*14*fade;
        var ex=r.x+Math.cos(r.angle)*r.len*f+jitter;
        var ey=r.y+Math.sin(r.angle)*r.len*f+jitter;
        ctx.lineTo(ex,ey);
      }
      ctx.stroke();
      ctx.restore();
      r.life++;
      return r.life<r.maxLife;
    });
  }

  /* ── Éclairs ── */
  var BOLTS=[];
  function spawnBolt(){
    var angle=Math.random()*Math.PI*2;
    var rx=100+Math.random()*80, ry=H*.3;
    var bx=CX+Math.cos(angle)*rx;
    var by=(CY_BASE-H*.35)+Math.sin(angle)*ry;
    var dir=Math.atan2(by-(CY_BASE-H*.4),bx-CX);
    var len=30+Math.random()*80;
    var pts=[{x:bx,y:by}];
    var cx2=bx,cy2=by;
    for(var i=0;i<8;i++){
      cx2+=Math.cos(dir)*len/8+(Math.random()-.5)*20;
      cy2+=Math.sin(dir)*len/8+(Math.random()-.5)*20;
      pts.push({x:cx2,y:cy2});
    }
    BOLTS.push({pts:pts,alpha:.5+Math.random()*.5,width:.5+Math.random()*1.2,
      color:Math.random()>.7?'rgba(0,212,255,':'rgba(255,45,120,',
      life:0,maxLife:3+Math.floor(Math.random()*7)});
  }
  function drawBolts(){
    BOLTS=BOLTS.filter(function(b){
      var fade=1-(b.life/b.maxLife);
      var a=(b.alpha*fade).toFixed(2);
      ctx.save();
      ctx.shadowColor=b.color+'1)';ctx.shadowBlur=10;
      ctx.strokeStyle=b.color+a+')';
      ctx.lineWidth=b.width;ctx.lineCap='round';
      ctx.beginPath();ctx.moveTo(b.pts[0].x,b.pts[0].y);
      for(var i=1;i<b.pts.length;i++)ctx.lineTo(b.pts[i].x,b.pts[i].y);
      ctx.stroke();
      /* Cœur blanc */
      ctx.strokeStyle='rgba(255,255,255,'+(fade*.5).toFixed(2)+')';
      ctx.lineWidth=b.width*.35;ctx.stroke();
      ctx.restore();
      b.life++;return b.life<b.maxLife;
    });
  }

  var frame=0,t=0;
  function loop(){
    /* Dimensions depuis le PARENT (plus fiable que canvas.offsetWidth) */
    var p=canvas.parentElement;
    var nw=p?p.offsetWidth:window.innerWidth*0.65;
    var nh=p?p.offsetHeight:window.innerHeight;
    if(nw>0&&nh>0&&(canvas.width!==nw||canvas.height!==nh)){
      canvas.width=nw; canvas.height=nh;
    }
    W=canvas.width||700; H=canvas.height||window.innerHeight;
    CX=W/2; CY_BASE=H*.92;
    ctx.clearRect(0,0,W,H);
    t+=0.016;

    drawPillar(t);

    if(frame%2===0) spawnRay();
    if(frame%3===0) spawnBolt();
    drawRays();
    drawBolts();

    frame++;
    requestAnimationFrame(loop);
  }
  /* Attendre que le layout soit rendu avant de démarrer */
  window.addEventListener('load', function(){ setTimeout(loop, 50); });
})();

/* ══════════════════════════════════════
   COUNTDOWN — 14 Avril 2026 00:00:00 Paris
══════════════════════════════════════ */
(function(){
  /* Date cible : 14 Avril 2026 à minuit heure de Paris */
  var target = new Date('2026-04-14T00:00:00+02:00').getTime();

  var els = {
    d: document.getElementById('cd-days'),
    h: document.getElementById('cd-hours'),
    m: document.getElementById('cd-mins'),
    s: document.getElementById('cd-secs')
  };

  function pad(n){ return n < 10 ? '0'+n : ''+n; }

  function flash(el){
    el.style.animation = 'none';
    void el.offsetWidth; /* reflow */
    el.style.animation = 'cd-tick .15s ease';
  }

  var prev = {d:'',h:'',m:'',s:''};

  function tick(){
    var now  = Date.now();
    var diff = target - now;

    if(diff <= 0){
      /* Lancement ! */
      els.d.textContent = els.h.textContent = els.m.textContent = els.s.textContent = '00';
      return;
    }

    var days  = Math.floor(diff / 86400000);
    var hours = Math.floor((diff % 86400000) / 3600000);
    var mins  = Math.floor((diff % 3600000)  / 60000);
    var secs  = Math.floor((diff % 60000)    / 1000);

    var vals = {d:pad(days),h:pad(hours),m:pad(mins),s:pad(secs)};

    /* Flash uniquement sur le chiffre qui change */
    if(vals.d !== prev.d){ els.d.textContent = vals.d; flash(els.d); }
    if(vals.h !== prev.h){ els.h.textContent = vals.h; flash(els.h); }
    if(vals.m !== prev.m){ els.m.textContent = vals.m; flash(els.m); }
    if(vals.s !== prev.s){ els.s.textContent = vals.s; flash(els.s); }

    prev = vals;
  }

  tick();
  setInterval(tick, 1000);
})();

/* ── Toggle mdp ── */
function togglePwd(){
  var i=document.getElementById('gate_password');
  i.type=i.type==='password'?'text':'password';
}

/* ── Bouton chargement ── */
document.getElementById('se-form').addEventListener('submit',function(){
  var btn=document.getElementById('se-btn');
  var txt=btn.querySelector('.btn-text');
  var ico=btn.querySelector('.btn-icon-wrap');
  var arr=btn.querySelector('.btn-arrow');
  btn.disabled=true;
  if(txt) txt.textContent='Connexion…';
  if(ico) ico.textContent='⏳';
  if(arr) arr.style.opacity='0';
});
</script>
</body>
</html>
<?php
}
