<?php
// ============================================================
// STRATEDGE — Template card Live Bet (visuel référence garanti)
// Variables attendues : $date_fr, $time_fr, $player1, $player2, $flag1, $flag2,
// $competition, $prono, $cote, $confiance, $sport, $sport_emoji, $is_locked
// $mascotte_url, $logo_url, $badge_style (CSS inline pour le badge), $promo_sport_pro
// ============================================================
if (!isset($date_fr)) $date_fr = '';
if (!isset($time_fr)) $time_fr = '';
if (!isset($player1)) $player1 = '';
if (!isset($player2)) $player2 = '';
if (!isset($flag1)) $flag1 = '';
if (!isset($flag2)) $flag2 = '';
if (!isset($competition)) $competition = '';
if (!isset($prono)) $prono = '';
if (!isset($cote)) $cote = '';
if (!isset($confiance)) $confiance = 73;
if (!isset($sport)) $sport = 'Tennis';
if (!isset($sport_emoji)) $sport_emoji = '🎾';
if (!isset($is_locked)) $is_locked = false;
if (!isset($mascotte_url)) $mascotte_url = 'https://stratedgepronos.fr/assets/images/mascotte-tennis.jpg';
if (!isset($logo_url)) $logo_url = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';
if (!isset($badge_style)) $badge_style = 'background:rgba(57,255,20,0.12);border:1.5px solid rgba(57,255,20,0.6);color:#39ff14;';
if (!isset($promo_sport_pro)) $promo_sport_pro = 'Tennis Pro';
$confiance = (int) $confiance;
if ($confiance < 0) $confiance = 0;
if ($confiance > 100) $confiance = 100;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StratEdge Card Live</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #0a0a0a; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Rajdhani', sans-serif; }
  .card-wrapper { position: relative; width: 720px; }
  .card-wrapper::before { content: ''; position: absolute; inset: -2px; border-radius: 20px; background: linear-gradient(135deg, #ff2d7a, #00e5ff, #ff2d7a); background-size: 300% 300%; animation: gradientShift 4s ease infinite; z-index: 0; filter: blur(5px); opacity: 0.85; }
  @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
  .card { position: relative; z-index: 1; width: 720px; background: linear-gradient(145deg, #0d0d0f 0%, #111318 40%, #0d1117 100%); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; border: 1px solid rgba(255,255,255,0.05); }
  .card::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 75% 50%, rgba(0,229,255,0.04) 0%, transparent 60%), radial-gradient(ellipse at 20% 80%, rgba(255,45,122,0.05) 0%, transparent 50%); z-index: 0; pointer-events: none; }
  .card-body { position: relative; z-index: 2; display: flex; padding: 0; overflow: hidden; }
  .mascotte-section { width: 210px; flex-shrink: 0; position: relative; overflow: hidden; }
  .mascotte-section::after { content: ''; position: absolute; right: 0; top: 0; bottom: 0; width: 55px; background: linear-gradient(to right, transparent, #111318); z-index: 2; }
  .mascotte-img { width: 100%; height: 100%; object-fit: cover; object-position: center top; filter: drop-shadow(0 0 20px rgba(57,255,20,0.25)); }
  .content-section { flex: 1; padding: 16px 20px 14px 10px; display: flex; flex-direction: column; gap: 9px; }
  .card-header { display: flex; align-items: center; justify-content: space-between; }
  .logo-img { height: 26px; object-fit: contain; }
  .sport-badge { display: flex; align-items: center; gap: 5px; border-radius: 20px; padding: 3px 12px; font-family: 'Orbitron', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; box-shadow: 0 0 10px rgba(57,255,20,0.25), inset 0 0 8px rgba(57,255,20,0.05); text-shadow: 0 0 8px rgba(57,255,20,0.8); }
  .datetime-block { text-align: center; padding: 6px 0 2px; }
  .datetime-day { font-family: 'Rajdhani', sans-serif; font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 3px; margin-bottom: 2px; }
  .datetime-time { font-family: 'Orbitron', sans-serif; font-size: 34px; font-weight: 900; color: #ffffff; letter-spacing: 3px; line-height: 1; text-shadow: 0 0 30px rgba(0,229,255,0.5), 0 0 60px rgba(0,229,255,0.2); background: linear-gradient(90deg, #ffffff 0%, #00e5ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
  .match-block { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 9px 14px; position: relative; }
  .match-block::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: linear-gradient(to bottom, #ff2d7a, #00e5ff); border-radius: 3px 0 0 3px; }
  .match-top-row { display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
  .live-badge { display: flex; align-items: center; gap: 5px; font-family: 'Orbitron', sans-serif; font-size: 9px; font-weight: 700; color: #ff2d7a; letter-spacing: 2px; text-transform: uppercase; }
  .live-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff2d7a; box-shadow: 0 0 6px #ff2d7a; animation: blink 1.2s ease-in-out infinite; }
  @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
  .match-players { display: flex; align-items: center; justify-content: center; gap: 12px; }
  .player-info { display: flex; align-items: center; gap: 6px; }
  .player-flag { font-size: 18px; line-height: 1; }
  .player { font-family: 'Bebas Neue', cursive; font-size: 22px; letter-spacing: 1px; line-height: 1; }
  .player.main { color: #ffffff; text-shadow: 0 0 20px rgba(0,229,255,0.3); }
  .player.opponent { color: rgba(255,255,255,0.7); }
  .vs-badge { background: linear-gradient(135deg, #ff2d7a, #00e5ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-family: 'Orbitron', sans-serif; font-size: 12px; font-weight: 900; flex-shrink: 0; }
  .match-competition { font-family: 'Rajdhani', sans-serif; font-size: 14px; color: rgba(255,255,255,0.55); margin-top: 4px; text-align: center; }
  .prono-block { background: linear-gradient(135deg, rgba(255,45,122,0.08), rgba(0,229,255,0.06)); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 9px 14px; display: flex; align-items: center; justify-content: space-between; }
  .prono-left { flex: 1; }
  .prono-label { font-size: 12px; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 3px; font-weight: 600; }
  .prono-text { font-family: 'Rajdhani', sans-serif; font-weight: 700; font-size: 18px; color: #ffffff; }
  .prono-text em { font-style: normal; background: linear-gradient(90deg, #ff2d7a, #00e5ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
  .cote-block { text-align: center; flex-shrink: 0; margin-left: 14px; }
  .cote-label { font-size: 12px; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; font-weight: 600; }
  .cote-pill { display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #ff2d7a 0%, #c850c0 45%, #4158d0 100%); border-radius: 12px; padding: 8px 20px; min-width: 85px; box-shadow: 0 4px 20px rgba(255,45,122,0.5), inset 0 0 0 1px rgba(255,255,255,0.12); position: relative; overflow: hidden; }
  .cote-pill::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 50%; background: rgba(255,255,255,0.13); border-radius: 12px 12px 0 0; }
  .cote-value { font-family: 'Orbitron', sans-serif; font-size: 22px; font-weight: 900; color: #ffffff; letter-spacing: 1px; position: relative; z-index: 1; }
  .confidence-section { display: flex; align-items: center; gap: 10px; }
  .confidence-label { font-size: 12px; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
  .confidence-bar-bg { flex: 1; height: 6px; background: rgba(255,255,255,0.07); border-radius: 10px; overflow: hidden; }
  .confidence-bar-fill { height: 100%; background: linear-gradient(90deg, #ff2d7a, #ff6b35, #00e5ff); border-radius: 10px; animation: barPulse 2s ease-in-out infinite; }
  @keyframes barPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
  .confidence-score { font-family: 'Orbitron', sans-serif; font-size: 13px; font-weight: 700; color: #00e5ff; flex-shrink: 0; text-shadow: 0 0 10px rgba(0,229,255,0.5); }
  .promo-banner { background: linear-gradient(135deg, rgba(57,255,20,0.07) 0%, rgba(0,229,255,0.05) 100%); border: 1px solid rgba(57,255,20,0.25); border-radius: 10px; padding: 9px 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; position: relative; overflow: hidden; }
  .promo-banner::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: linear-gradient(to bottom, #39ff14, #00e5ff); border-radius: 3px 0 0 3px; }
  .promo-text-block { display: flex; flex-direction: column; gap: 1px; }
  .promo-eyebrow { font-size: 9px; color: #39ff14; text-transform: uppercase; letter-spacing: 2.5px; font-weight: 700; font-family: 'Orbitron', sans-serif; text-shadow: 0 0 8px rgba(57,255,20,0.6); }
  .promo-main { font-family: 'Bebas Neue', cursive; font-size: 17px; letter-spacing: 1px; color: #ffffff; line-height: 1.1; }
  .promo-main em { font-style: normal; background: linear-gradient(90deg, #ff2d7a, #c850c0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
  .promo-sub { font-size: 12px; color: rgba(255,255,255,0.55); font-weight: 500; margin-top: 1px; }
  .promo-sub span { color: rgba(0,229,255,0.85); font-weight: 700; }
  .promo-cta { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #39ff14, #00c896); color: #000000; font-family: 'Orbitron', sans-serif; font-size: 9px; font-weight: 900; letter-spacing: 0.8px; text-transform: uppercase; padding: 7px 13px; border-radius: 8px; white-space: nowrap; flex-shrink: 0; box-shadow: 0 0 16px rgba(57,255,20,0.45), 0 3px 10px rgba(0,0,0,0.3); animation: ctaPulse 2.5s ease-in-out infinite; }
  @keyframes ctaPulse { 0%, 100% { box-shadow: 0 0 16px rgba(57,255,20,0.4), 0 3px 10px rgba(0,0,0,0.3); } 50% { box-shadow: 0 0 26px rgba(57,255,20,0.75), 0 3px 10px rgba(0,0,0,0.3); } }
  .locked-block { text-align: center; padding: 8px 0; }
  .locked-block .lock-icon { font-size: 50px; line-height: 1; margin: 6px 0; }
  .locked-block .locked-text { font-family: 'Orbitron', sans-serif; font-size: 11px; color: #ff2d7a; opacity: 0.7; letter-spacing: 2px; margin: 6px 0; }
  .locked-cta { background: linear-gradient(135deg, #FF2D78, #d6245f); color: white; padding: 10px 28px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-size: 11px; font-weight: 700; display: inline-block; letter-spacing: 1px; margin-top: 6px; }
  .card-footer-gradient { height: 3px; background: linear-gradient(90deg, #ff2d7a 0%, #c850c0 50%, #00e5ff 100%); position: relative; z-index: 2; }
</style>
</head>
<body>
<div class="card-wrapper">
  <div class="card">
    <div class="card-body">
      <div class="mascotte-section">
        <img class="mascotte-img" src="<?php echo htmlspecialchars($mascotte_url); ?>" alt="">
      </div>
      <div class="content-section">
        <div class="card-header">
          <img class="logo-img" src="<?php echo htmlspecialchars($logo_url); ?>" alt="StratEdge">
          <div class="sport-badge" style="<?php echo $badge_style; ?>"><?php echo $sport_emoji; ?> <?php echo htmlspecialchars(strtoupper($sport)); ?></div>
        </div>
        <div class="datetime-block">
          <div class="datetime-day"><?php echo htmlspecialchars($date_fr); ?></div>
          <div class="datetime-time"><?php echo htmlspecialchars($time_fr); ?></div>
        </div>
        <div class="match-block">
          <div class="match-top-row">
            <div class="live-badge"><div class="live-dot"></div>Live Bet</div>
          </div>
          <div class="match-players">
            <div class="player-info"><span class="player-flag"><?php echo $flag1; ?></span><div class="player main"><?php echo htmlspecialchars($player1); ?></div></div>
            <div class="vs-badge">VS</div>
            <div class="player-info"><span class="player-flag"><?php echo $flag2; ?></span><div class="player opponent"><?php echo htmlspecialchars($player2); ?></div></div>
          </div>
          <?php if ($competition !== '') { ?><div class="match-competition"><?php echo htmlspecialchars($competition); ?></div><?php } ?>
        </div>
        <div class="prono-block">
          <?php if ($is_locked) { ?>
          <div class="prono-left">
            <div class="locked-block">
              <div class="lock-icon">🔒</div>
              <div class="locked-text">CONTENU RÉSERVÉ</div>
              <div class="locked-cta">🔓 Reçois le bet sur stratedgepronos.fr</div>
            </div>
          </div>
          <?php } else { ?>
          <div class="prono-left">
            <div class="prono-label">Pronostic</div>
            <div class="prono-text"><em><?php echo htmlspecialchars($prono); ?></em></div>
          </div>
          <?php } ?>
          <div class="cote-block">
            <div class="cote-label">Cote</div>
            <div class="cote-pill"><div class="cote-value"><?php echo htmlspecialchars($cote); ?></div></div>
          </div>
        </div>
        <div class="confidence-section">
          <div class="confidence-label">Confiance</div>
          <div class="confidence-bar-bg"><div class="confidence-bar-fill" style="width:<?php echo $confiance; ?>%"></div></div>
          <div class="confidence-score"><?php echo $confiance; ?>/100</div>
        </div>
        <div class="promo-banner">
          <div class="promo-text-block">
            <div class="promo-eyebrow"><?php echo $sport_emoji; ?> Offre exclusive</div>
            <div class="promo-main">Pack <em><?php echo htmlspecialchars($promo_sport_pro); ?></em> — Accès illimité</div>
            <div class="promo-sub">Pronostics experts · Analyses live · <span>Dès 9.99€/mois</span></div>
          </div>
          <div class="promo-cta">🚀 Je m'abonne</div>
        </div>
      </div>
    </div>
    <div class="card-footer-gradient"></div>
  </div>
</div>
</body>
</html>
