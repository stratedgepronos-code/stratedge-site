<?php
// ============================================================
// STRATEDGE — Page Merci (après achat)
// Couleur adaptée au pack · Disclaimer · Guide · Social
// ============================================================
require_once __DIR__ . '/includes/auth.php';
// Si pas connecté (session perdue au retour de Stripe), afficher quand même la page
// L'abonnement a été activé par le webhook Stripe côté serveur
$membre = isLoggedIn() ? getMembre() : null;

$type = $_GET['type'] ?? 'multi_pack';
$packs = [
  // === NOUVEAU MODELE ===
  'multi_pack'  => ['titre'=>'Pack Multi','emoji'=>'⚡','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'unique'      => ['titre'=>'Pack Unique','emoji'=>'🎯','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'duo'         => ['titre'=>'Pack Duo','emoji'=>'2️⃣','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'trio'        => ['titre'=>'Pack Trio','emoji'=>'🔥','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'quinte'      => ['titre'=>'Quinté','emoji'=>'💎','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'semaine'     => ['titre'=>'Pack Semaine','emoji'=>'📅','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'pack10'      => ['titre'=>'Pack 10','emoji'=>'🏆','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'tennis'      => ['titre'=>'Tennis Semaine','emoji'=>'🎾','color'=>'#00d46a','glow'=>'rgba(0,212,106,0.18)','grad'=>'linear-gradient(135deg,#00d46a,#00a852)','video'=>'assets/images/mascotte_tennis.mp4'],
  'fun'         => ['titre'=>'Fun Semaine','emoji'=>'🎲','color'=>'#a855f7','glow'=>'rgba(168,85,247,0.18)','grad'=>'linear-gradient(135deg,#a855f7,#ec4899)','video'=>'assets/images/mascotte-fun.mp4'],
  // Backward-compat pour anciens liens/webhooks qui pourraient encore arriver
  'daily'       => ['titre'=>'Daily','emoji'=>'⚡','color'=>'#ff2d78','glow'=>'rgba(255,45,120,0.18)','grad'=>'linear-gradient(135deg,#ff2d78,#c4185a)','video'=>'assets/images/DOIGT.mp4'],
  'weekend'     => ['titre'=>'Week-End','emoji'=>'📅','color'=>'#00d4ff','glow'=>'rgba(0,212,255,0.18)','grad'=>'linear-gradient(135deg,#00d4ff,#0099cc)','video'=>'assets/images/air.mp4'],
  'weekly'      => ['titre'=>'Weekly','emoji'=>'🏆','color'=>'#a855f7','glow'=>'rgba(168,85,247,0.18)','grad'=>'linear-gradient(135deg,#a855f7,#7c3aed)','video'=>'assets/images/SAM.mp4'],
];
if (!isset($packs[$type])) $type = 'multi_pack';
$p = $packs[$type];
$isDaily  = ($type === 'daily');
$isVipMax = false; // VIP MAX n'existe plus
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Merci ! — StratEdge Pronos</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#060810;--bg2:#0c1018;--bg3:#111827;
  --color:<?= $p['color'] ?>;--glow:<?= $p['glow'] ?>;--grad:<?= $p['grad'] ?>;
  --txt:#f0f4f8;--txt2:#e8ecf0;--txt3:#8a9bb0;
  --border:rgba(255,255,255,0.07);
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;overflow-x:hidden;line-height:1.6}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(255,255,255,0.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.015) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;z-index:0}

/* Orbes */
.bg-orbs{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}
.orb{position:absolute;border-radius:50%;filter:blur(100px);opacity:0;animation:orbF 10s ease-in-out infinite}
.o1{width:600px;height:600px;background:var(--glow);top:-150px;right:-100px}
.o2{width:450px;height:450px;background:rgba(0,212,255,0.05);bottom:-100px;left:5%;animation-delay:4s}
.o3{width:350px;height:350px;background:var(--glow);top:50%;left:30%;animation-delay:7s}
@keyframes orbF{0%{opacity:0;transform:scale(.85) translateY(30px)}35%{opacity:1}65%{opacity:1}100%{opacity:0;transform:scale(1.15) translateY(-30px)}}

/* Nav */
nav{position:sticky;top:0;z-index:100;background:rgba(6,8,16,0.88);backdrop-filter:blur(24px);border-bottom:1px solid var(--border);padding:0 2rem}
.nav-inner{max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:60px}
.nav-logo img{height:38px}
.nav-back{color:var(--txt3);text-decoration:none;font-size:0.85rem;letter-spacing:0.5px;transition:color .2s}
.nav-back:hover{color:var(--txt)}

/* Page */
.page{max-width:800px;margin:0 auto;padding:3rem 2rem 5rem;position:relative;z-index:1}

/* ── Hero Merci ── */
.hero-merci{text-align:center;margin-bottom:3rem;animation:fU .6s ease both}
.hero-check{width:90px;height:90px;border-radius:50%;background:color-mix(in srgb,var(--color) 12%,transparent);border:2px solid color-mix(in srgb,var(--color) 40%,transparent);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;animation:checkPop .5s ease .3s both;box-shadow:0 0 40px var(--glow)}
.hero-check svg{width:44px;height:44px}
@keyframes checkPop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
.hero-merci h1{font-family:'Orbitron',sans-serif;font-size:clamp(1.6rem,4vw,2.4rem);font-weight:900;margin-bottom:.6rem}
.hero-merci h1 span{background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero-pack{display:inline-flex;align-items:center;gap:.5rem;font-family:'Orbitron',sans-serif;font-size:.72rem;letter-spacing:2px;text-transform:uppercase;background:color-mix(in srgb,var(--color) 10%,transparent);border:1px solid color-mix(in srgb,var(--color) 30%,transparent);color:var(--color);padding:.4rem 1rem;border-radius:30px;margin-bottom:1.2rem}
.hero-merci p{color:var(--txt2);font-size:1.05rem;max-width:550px;margin:0 auto;line-height:1.7}
.hero-merci p strong{color:var(--txt)}

/* Mascotte */
.mascot-ring{width:100px;height:100px;border-radius:50%;overflow:hidden;margin:1.5rem auto 0;border:2px solid color-mix(in srgb,var(--color) 45%,transparent);box-shadow:0 0 30px var(--glow);animation:fU .7s ease .2s both}
.mascot-ring video{width:100%;height:100%;object-fit:cover}

/* ── Section card ── */
.info-card{background:var(--bg2);border:1px solid var(--border);border-radius:20px;padding:2rem 2rem;margin-bottom:1.5rem;position:relative;overflow:hidden;animation:fU .7s ease both}
.info-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);opacity:.7}
.info-card.warn::before{background:linear-gradient(90deg,#ff6b2b,#ffc107)}
.info-card.green::before{background:linear-gradient(90deg,#00d46a,#00d4ff)}
.ic-icon{font-size:1.8rem;margin-bottom:.8rem}
.ic-title{font-family:'Orbitron',sans-serif;font-size:.95rem;font-weight:700;letter-spacing:.5px;margin-bottom:.8rem;color:var(--txt)}
.ic-text{color:var(--txt2);font-size:.92rem;line-height:1.7}
.ic-text strong{color:var(--txt)}
.ic-text em{color:var(--color);font-style:normal;font-weight:700}

/* Liste steps */
.steps-list{list-style:none;padding:0;margin:.8rem 0 0;counter-reset:step}
.steps-list li{counter-increment:step;display:flex;align-items:flex-start;gap:.8rem;padding:.6rem 0;color:var(--txt2);font-size:.9rem;line-height:1.5}
.steps-list li::before{content:counter(step);font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:900;color:var(--color);background:color-mix(in srgb,var(--color) 12%,transparent);border:1px solid color-mix(in srgb,var(--color) 30%,transparent);border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}

/* Highlight box */
.highlight-box{background:color-mix(in srgb,var(--color) 6%,transparent);border:1px solid color-mix(in srgb,var(--color) 20%,transparent);border-radius:12px;padding:1rem 1.2rem;margin-top:1rem}
.highlight-box p{color:var(--txt2);font-size:.88rem;line-height:1.6;margin:0}
.highlight-box strong{color:var(--color)}

/* Daily special box */
.daily-box{background:rgba(255,107,43,0.06);border:1px solid rgba(255,107,43,0.2);border-radius:12px;padding:1rem 1.2rem;margin-top:1rem}
.daily-box p{color:var(--txt2);font-size:.88rem;line-height:1.6;margin:0}
.daily-box strong{color:#ff6b2b}

/* ── Boutons sociaux ── */
.social-row{display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem;animation:fU .8s ease .4s both}
.social-btn{display:inline-flex;align-items:center;gap:.6rem;padding:.85rem 1.5rem;border-radius:12px;font-family:'Rajdhani',sans-serif;font-size:.95rem;font-weight:700;text-decoration:none;transition:all .3s;letter-spacing:.5px}
.social-btn:hover{transform:translateY(-2px)}
.btn-x{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:var(--txt)}
.btn-x:hover{background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);box-shadow:0 8px 25px rgba(0,0,0,0.3)}
.btn-x svg{width:18px;height:18px;fill:currentColor}
.btn-telegram{background:linear-gradient(135deg,#0088cc,#0077b5);border:none;color:#fff;box-shadow:0 4px 15px rgba(0,136,204,0.25)}
.btn-telegram:hover{box-shadow:0 8px 30px rgba(0,136,204,0.4)}
.btn-telegram svg{width:18px;height:18px;fill:currentColor}

/* ── CTA dashboard ── */
.cta-section{text-align:center;margin-top:2.5rem;animation:fU .8s ease .5s both}
.cta-btn{display:inline-flex;align-items:center;gap:.6rem;padding:1rem 2.5rem;background:var(--grad);color:#fff;border-radius:12px;font-family:'Orbitron',sans-serif;font-size:.82rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;text-decoration:none;box-shadow:0 4px 25px var(--glow);transition:all .3s;position:relative;overflow:hidden}
.cta-btn::before{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,0.12) 0%,transparent 50%);pointer-events:none}
.cta-btn:hover{transform:translateY(-3px);box-shadow:0 8px 35px var(--glow);filter:brightness(1.1)}
.cta-sub{color:var(--txt3);font-size:.8rem;margin-top:.6rem}

/* Disclaimer bottom */
.disclaimer-bottom{text-align:center;margin-top:3rem;padding-top:2rem;border-top:1px solid var(--border);animation:fU .9s ease .6s both}
.disclaimer-bottom p{color:var(--txt3);font-size:.72rem;line-height:1.6;max-width:600px;margin:0 auto;opacity:.7}

@keyframes fU{from{opacity:0;transform:translateY(25px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:600px){
  .page{padding:2rem 1rem 4rem}
  .info-card{padding:1.4rem 1.2rem}
  .social-row{flex-direction:column}
  .social-btn{width:100%;justify-content:center}
}
</style>
</head>
<body>
<div class="bg-orbs"><div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div></div>

<nav><div class="nav-inner">
  <a href="/" class="nav-logo"><img src="assets/images/logo site.png" alt="StratEdge"></a>
  <a href="dashboard.php" class="nav-back">← Mon espace</a>
</div></nav>

<div class="page">

  <!-- ═══ HERO ═══ -->
  <div class="hero-merci">
    <div class="hero-check">
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--color)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="hero-pack"><?= $p['emoji'] ?> <?= $p['titre'] ?> · ACTIVÉ</div>
    <h1>Merci <span><?= $membre ? clean($membre['nom']) : 'à toi' ?></span> !</h1>
    <p>Ton accès <strong><?= $p['titre'] ?></strong> est maintenant actif. Bienvenue dans l'Edge — on va faire du beau travail ensemble.</p>
    <div class="mascot-ring">
      <video autoplay loop muted playsinline><source src="<?= $p['video'] ?>" type="video/mp4"></video>
    </div>
    <div style="margin-top:1.5rem;">
      <a href="bets.php" class="cta-btn" style="font-size:.75rem;padding:.8rem 2rem;">Accéder aux Bets →</a>
    </div>
  </div>

  <div style="text-align:center;font-family:'Orbitron',sans-serif;font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);margin-bottom:2rem;opacity:.5;">↓ Lis ces infos importantes avant de commencer ↓</div>

  <!-- ═══ DISCLAIMER RESPONSABLE ═══ -->
  <div class="info-card warn" style="animation-delay:.15s">
    <div class="ic-icon">⚠️</div>
    <div class="ic-title">Le pari reste un jeu — sois maître de toi-même</div>
    <div class="ic-text">
      Chez StratEdge, on analyse, on croise les stats, on cherche les meilleures opportunités. Mais <strong>aucun pronostic n'est garanti</strong> — un match de sport reste imprévisible, et c'est d'ailleurs ce qui en fait tout l'intérêt.<br><br>
      <em>Nous ne sommes pas responsables des résultats en fin de match.</em> Le pari sportif doit rester un <strong>plaisir</strong> et jamais une source de revenu espérée. Ne mise jamais de l'argent que tu ne peux pas te permettre de perdre.<br><br>
      <strong>C'est à toi de gérer ta bankroll.</strong> Nous te donnons nos analyses et nos conseils, mais la décision finale t'appartient toujours. Sois discipliné, fixe-toi des limites, et respecte-les.
    </div>
  </div>

  <!-- ═══ DAILY SPÉCIAL ═══ -->
  <?php if ($isDaily): ?>
  <div class="info-card" style="animation-delay:.25s">
    <div class="ic-icon">⚡</div>
    <div class="ic-title">Comment fonctionne ton Daily</div>
    <div class="ic-text">
      Ton Daily te donne accès au <strong>prochain bet publié</strong> (Safe ou Live). Ce bet peut arriver <em>aujourd'hui, demain, ou dans 2 jours</em> — ça dépend du calendrier des compétitions et de nos analyses.<br><br>
      On ne publie pas pour publier : <strong>on joue uniquement les matchs qu'on a analysés en profondeur</strong> et qui présentent les meilleures stats. Pas de remplissage, que de la qualité.
    </div>
    <div class="daily-box">
      <p><strong>Important :</strong> Si le bet est perdant, ton Daily <strong>n'est pas annulé</strong> et continue.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══ OÙ TROUVER LES PARIS ═══ -->
  <div class="info-card green" style="animation-delay:.3s">
    <div class="ic-icon">📊</div>
    <div class="ic-title">Où retrouver tes paris</div>
    <div class="ic-text">
      Tous les bets sont publiés directement sur ton <strong>espace membre</strong>, dans la section <em>"Les Bets"</em>. Chaque bet est affiché sous forme de card avec l'analyse complète, la cote, le type de pari et notre indice de confiance.
    </div>
    <ol class="steps-list">
      <li>Connecte-toi sur <strong>stratedgepronos.fr</strong></li>
      <li>Va dans <strong>"Les Bets"</strong> depuis le menu ou la sidebar</li>
      <li>Les bets actifs sont en haut — clique pour voir l'analyse complète</li>
      <li>Les résultats passés sont dans <strong>"Historique"</strong></li>
    </ol>
  </div>

  <!-- ═══ NOTIFICATIONS ═══ -->
  <div class="info-card" style="animation-delay:.35s">
    <div class="ic-icon">🔔</div>
    <div class="ic-title">Ne rate aucun bet — active tes notifications</div>
    <div class="ic-text">
      Tu reçois chaque bet par <strong>email</strong> automatiquement. Mais le plus rapide, c'est les <strong>notifications Push</strong> — tu es alerté instantanément, même quand tu n'es pas sur le site. C'est essentiel pour les <em>bets LIVE</em> qui ont un timing serré.
    </div>
    <div class="highlight-box">
      <p><strong>Comment activer les Push :</strong><br>
      Depuis ton <strong>Dashboard</strong>, clique sur le bouton <strong>"Activer les notifications"</strong>. Ton navigateur te demandera l'autorisation — accepte et c'est fait.</p>
    </div>
  </div>

  <!-- ═══ INSTALLER L'APPLI SUR TON TÉLÉPHONE ═══ -->
  <div class="info-card green" style="animation-delay:.38s">
    <div class="ic-icon">📲</div>
    <div class="ic-title">Installe StratEdge comme une appli sur ton téléphone</div>
    <div class="ic-text">
      Tu peux accéder à StratEdge directement depuis ton écran d'accueil, <strong>comme une vraie application</strong> — sans passer par le navigateur. C'est plus rapide, plus pratique, et tu recevras les <strong>notifications push</strong> même quand l'appli est fermée.
    </div>

    <div class="highlight-box" style="margin-top:1rem;">
      <p style="margin-bottom:.5rem;"><strong>📱 Sur iPhone (Safari) :</strong></p>
      <ol class="steps-list" style="margin-top:.3rem;">
        <li>Ouvre <strong>stratedgepronos.fr</strong> dans <strong>Safari</strong> (obligatoire, pas Chrome)</li>
        <li>Appuie sur le bouton <strong>Partager</strong> (carré avec flèche ↑ en bas de l'écran)</li>
        <li>Fais défiler et appuie sur <strong>"Sur l'écran d'accueil"</strong></li>
        <li>Confirme en appuyant sur <strong>"Ajouter"</strong> — c'est fait !</li>
      </ol>
    </div>

    <div class="highlight-box" style="margin-top:.8rem;">
      <p style="margin-bottom:.5rem;"><strong>🤖 Sur Android (Chrome) :</strong></p>
      <ol class="steps-list" style="margin-top:.3rem;">
        <li>Ouvre <strong>stratedgepronos.fr</strong> dans <strong>Chrome</strong></li>
        <li>Appuie sur les <strong>3 points ⋮</strong> en haut à droite</li>
        <li>Appuie sur <strong>"Installer l'application"</strong> ou <strong>"Ajouter à l'écran d'accueil"</strong></li>
        <li>Confirme — l'icône StratEdge apparaît sur ton écran !</li>
      </ol>
    </div>

    <div class="highlight-box" style="margin-top:.8rem;border-color:rgba(0,212,255,0.2);background:rgba(0,212,255,0.04);">
      <p><strong style="color:#00d4ff;">💡 Pourquoi installer l'appli ?</strong><br>
      Accès <strong>instantané</strong> en 1 tap depuis l'écran d'accueil · <strong>Notifications push</strong> même appli fermée · Mode <strong>plein écran</strong> sans barre du navigateur · Chargement <strong>ultra rapide</strong></p>
    </div>
  </div>

  <!-- ═══ NOS BETS : QUALITÉ > QUANTITÉ ═══ -->
  <div class="info-card" style="animation-delay:.4s">
    <div class="ic-icon">🎯</div>
    <div class="ic-title">On ne mise pas sur n'importe quoi</div>
    <div class="ic-text">
      Nos pronos sont basés sur les <strong>matchs du moment</strong> — les vrais événements sportifs en cours. On ne poste pas un bet pour poster un bet. Chaque prono est le résultat d'une <strong>analyse approfondie</strong> du match : statistiques, forme récente des équipes, confrontations directes, absences, cotes des bookmakers, modèles xG et Poisson.<br><br>
      Le nombre de bets publiés <strong>varie selon le calendrier sportif</strong>. Certaines semaines il y a beaucoup de matchs intéressants (Ligue des Champions, gros week-ends de championnat), d'autres moins. C'est <strong>normal et c'est voulu</strong>.<br><br>
      On joue <em>uniquement les matchs qui présentent une vraie value</em> — quand notre analyse estime que la probabilité réelle est supérieure à ce que la cote reflète. Pas de remplissage, pas de pari "au feeling".
    </div>
    <div class="highlight-box">
      <p><strong>La philosophie StratEdge :</strong> des bets ciblés, analysés, justifiés. Si tu veux du volume à tout prix, on n'est pas pour toi. Si tu veux de la <strong>qualité et de la rigueur</strong>, tu es au bon endroit.</p>
    </div>
  </div>

  <!-- ═══ RÉSEAUX SOCIAUX ═══ -->
  <div class="info-card" style="animation-delay:.45s">
    <div class="ic-icon">📱</div>
    <div class="ic-title">Reste connecté</div>
    <div class="ic-text">
      Suis-nous sur <strong>X (Twitter)</strong> pour les annonces, les résultats en direct et les coulisses de l'analyse. C'est aussi là qu'on communique en cas de maintenance ou de nouveauté.
      <?php if ($isVipMax): ?><br><br>En tant que membre <em>VIP MAX</em>, tu as accès à notre <strong>groupe Telegram privé</strong> — échanges directs, alertes prioritaires et communauté exclusive.<?php endif; ?>
    </div>
    <div class="social-row">
      <a href="https://x.com/StratEdgePronos" target="_blank" rel="noopener" class="social-btn btn-x">
        <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        Suivre @StratEdgePronos
      </a>
      <?php if ($isVipMax): ?>
      <a href="https://t.me/StratEdge_Detecor_Bot" target="_blank" rel="noopener" class="social-btn btn-telegram">
        <svg viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
        Rejoindre le Telegram VIP
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ CTA DASHBOARD ═══ -->
  <div class="cta-section">
    <a href="bets.php" class="cta-btn">Voir les Bets →</a>
    <?php if (!$membre): ?>
    <p style="margin-top:1rem;font-size:0.85rem;color:rgba(255,255,255,0.5);">
      Session expirée ? <a href="/login.php?redirect=/bets.php" style="color:#00d4ff;">Reconnecte-toi</a> — ton abonnement est déjà activé.
    </p>
    <?php endif; ?>
    <div class="cta-sub">Ton espace membre t'attend</div>
  </div>

  <!-- ═══ DISCLAIMER BOTTOM ═══ -->
  <div class="disclaimer-bottom">
    <p>Les paris sportifs comportent des risques de perte. StratEdge Pronos est un service d'analyse et de conseil — nous ne garantissons aucun résultat. Joue de manière responsable. Interdit aux mineurs (article L320-7 du Code de la sécurité intérieure). En cas de problème avec le jeu, appelle le 09 74 75 13 13 (appel non surtaxé).</p>
  </div>

</div>
</body>
</html>
