<?php
// ============================================================
// STRATEDGE — GiveAway (page membre)
// public_html/giveaway.php
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/giveaway-functions.php';
requireLogin();

$membre = getMembre();
$currentPage = 'giveaway';
$avatarUrl = getAvatarUrl($membre);

$tz = new DateTimeZone('Europe/Paris');
$moisActuel = (new DateTime('now', $tz))->format('Y-m');

$config = getGiveawayConfig($moisActuel);
$mesPoints = getPointsMembre($membre['id'], $moisActuel);
$mesDetails = getPointsDetailMembre($membre['id'], $moisActuel);
$classement = getClassementMois($moisActuel);
$totalPts = array_sum(array_column($classement, 'total_pts'));
$nbParticipants = count($classement);
$maChance = ($totalPts > 0 && $mesPoints > 0) ? round($mesPoints / $totalPts * 100, 1) : 0;

// Données anonymisées pour la roue (on cache les noms sauf le sien)
$wheelData = [];
$monIndex = -1;
foreach ($classement as $i => $p) {
    $isMoi = ((int)$p['membre_id'] === (int)$membre['id']);
    if ($isMoi) $monIndex = $i;
    $wheelData[] = [
        'label' => $isMoi ? htmlspecialchars($membre['nom']) : ('Participant #' . ($i + 1)),
        'pts'   => (int)$p['total_pts'],
        'moi'   => $isMoi,
    ];
}

$cadeau = $config['cadeau'] ?? '';
$statut = $config['statut'] ?? 'open';
$gagnantNom = $config['gagnant_nom'] ?? '';

$typeLabels = ['daily'=>'⚡ Daily','weekend'=>'📅 Week-End','weekly'=>'🏆 Weekly','vip_max'=>'👑 VIP Max'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>🎁 GiveAway — StratEdge Pronos</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
.gw-hero{text-align:center;margin-bottom:2rem;}
.gw-tag{display:inline-flex;align-items:center;gap:.5rem;font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:4px;text-transform:uppercase;color:#ff2d78;background:rgba(255,45,120,.08);border:1px solid rgba(255,45,120,.25);padding:.35rem 1rem;border-radius:30px;margin-bottom:.8rem;}
.gw-hero h1{font-family:'Orbitron',sans-serif;font-size:clamp(1.5rem,3vw,2.2rem);font-weight:900;margin-bottom:.4rem;}
.gw-hero h1 span{background:linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.gw-hero p{color:var(--txt3);font-size:.95rem;max-width:500px;margin:0 auto;}
.gw-month{font-family:'Orbitron',sans-serif;font-size:.85rem;color:#00d4ff;margin-top:.6rem;letter-spacing:2px;}

/* Layout */
.gw-layout{display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start;}

/* Wheel */
.gw-wheel-zone{display:flex;flex-direction:column;align-items:center;}
.gw-wheel-box{position:relative;width:420px;max-width:100%;}
.gw-wheel-box canvas{display:block;width:100%;height:auto;border-radius:50%;box-shadow:0 0 40px rgba(255,45,120,.1),0 0 60px rgba(0,212,255,.05);}
.gw-ptr{position:absolute;top:-10px;left:50%;transform:translateX(-50%);z-index:10;filter:drop-shadow(0 3px 12px rgba(255,45,120,.5));}
.gw-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:70px;height:70px;border-radius:50%;background:radial-gradient(circle,#111827,#0a0e17);border:2px solid rgba(255,45,120,.3);display:flex;align-items:center;justify-content:center;z-index:5;box-shadow:0 0 20px rgba(255,45,120,.2);}
.gw-center img{width:56px;height:auto;filter:drop-shadow(0 0 6px rgba(255,45,120,.3));}

/* Right panel */
.gw-panel{display:flex;flex-direction:column;gap:1rem;}

/* Prize card */
.gw-prize{background:var(--card,#111827);border:1px solid transparent;border-radius:14px;padding:1.2rem;position:relative;overflow:hidden;background-clip:padding-box;}
.gw-prize::after{content:'';position:absolute;inset:-1px;border-radius:15px;padding:1px;background:linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff);-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none;}
.gw-prize-title{font-family:'Orbitron',sans-serif;font-size:.65rem;letter-spacing:2px;color:var(--txt3,#8a9bb0);text-transform:uppercase;margin-bottom:.5rem;}
.gw-prize-text{font-family:'Rajdhani',sans-serif;font-size:1.3rem;font-weight:700;color:#fff;line-height:1.3;}
.gw-prize-empty{color:var(--txt3,#8a9bb0);font-style:italic;font-size:.9rem;}

/* My points */
.gw-mypts{background:var(--card,#111827);border:1px solid var(--border,rgba(255,45,120,.12));border-radius:14px;padding:1.2rem;text-align:center;}
.gw-mypts-val{font-family:'Orbitron',sans-serif;font-size:2.5rem;font-weight:900;background:linear-gradient(135deg,#00d4ff,#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.gw-mypts-label{font-size:.78rem;color:var(--txt3,#8a9bb0);text-transform:uppercase;letter-spacing:1px;margin-top:.2rem;}
.gw-mypts-chance{font-family:'Space Mono',monospace;font-size:.75rem;color:#ff2d78;margin-top:.5rem;}

/* Points history */
.gw-history{background:var(--card,#111827);border:1px solid var(--border,rgba(255,45,120,.12));border-radius:14px;padding:1rem;}
.gw-history-title{font-family:'Orbitron',sans-serif;font-size:.62rem;letter-spacing:2px;color:var(--txt3,#8a9bb0);text-transform:uppercase;margin-bottom:.6rem;}
.gw-hist-item{display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.03);font-size:.85rem;}
.gw-hist-type{color:var(--txt2,#b0bec9);}
.gw-hist-pts{font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;color:#00d4ff;}
.gw-hist-date{font-family:'Space Mono',monospace;font-size:.6rem;color:var(--txt3,#8a9bb0);}
.gw-hist-empty{color:var(--txt3,#8a9bb0);font-size:.82rem;font-style:italic;text-align:center;padding:1rem 0;}

/* How it works */
.gw-how{background:var(--card,#111827);border:1px solid var(--border,rgba(255,45,120,.12));border-radius:14px;padding:1rem;}
.gw-how-title{font-family:'Orbitron',sans-serif;font-size:.62rem;letter-spacing:2px;color:var(--txt3,#8a9bb0);text-transform:uppercase;margin-bottom:.6rem;}
.gw-how-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;}
.gw-how-item{display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--txt2,#b0bec9);}
.gw-how-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.gw-how-pts{font-family:'Orbitron',sans-serif;font-size:.68rem;font-weight:700;color:#00d4ff;}

/* Stats bar */
.gw-stats{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;}
.gw-stat{background:var(--card,#111827);border:1px solid var(--border,rgba(255,45,120,.12));border-radius:10px;padding:.7rem;text-align:center;}
.gw-stat-val{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900;color:#fff;}
.gw-stat-lbl{font-size:.65rem;color:var(--txt3,#8a9bb0);text-transform:uppercase;letter-spacing:1px;margin-top:.1rem;}

/* Winner banner */
.gw-winner-banner{background:linear-gradient(135deg,rgba(255,45,120,.08),rgba(168,85,247,.06));border:1px solid rgba(255,45,120,.25);border-radius:14px;padding:1.5rem;text-align:center;margin-bottom:1rem;}
.gw-winner-crown{font-size:2.5rem;}
.gw-winner-label{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:3px;color:#ff2d78;margin:.3rem 0;}
.gw-winner-name{font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:900;background:linear-gradient(135deg,#ff2d78,#f5c842,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}

@media(max-width:900px){
  .gw-layout{grid-template-columns:1fr;}
  .gw-wheel-box{width:320px;margin:0 auto;}
  .gw-how-grid{grid-template-columns:1fr;}
}

/* Voile « coming soon » sur tout le contenu sous le hero */
.gw-coming-soon-wrap{position:relative;min-height:320px;border-radius:16px;}
.gw-soon-overlay{
  position:absolute;inset:0;z-index:40;
  background:rgba(5,8,22,0.88);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  display:flex;align-items:center;justify-content:center;padding:2rem;
  border-radius:16px;pointer-events:auto;
}
.gw-soon-text{
  margin:0;text-align:center;font-family:'Orbitron',sans-serif;font-weight:900;
  font-size:clamp(1.35rem,4.5vw,2.35rem);line-height:1.25;color:#fff;
  text-shadow:0 0 40px rgba(255,45,120,0.45),0 0 80px rgba(0,212,255,0.2);
  letter-spacing:0.06em;text-transform:uppercase;max-width:22ch;
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="gw-hero">
  <div class="gw-tag">🎁 TIRAGE MENSUEL</div>
  <h1><span>GiveAway</span> StratEdge</h1>
  <p>Chaque abonnement te rapporte des points. Plus t'en as, plus tu as de chances de gagner !</p>
  <div class="gw-month"><?= strtoupper(moisFrancais($moisActuel)) ?></div>
</div>

<?php if ($statut === 'drawn' && $gagnantNom): ?>
<div class="gw-winner-banner">
  <div class="gw-winner-crown">👑</div>
  <div class="gw-winner-label">GAGNANT DU MOIS</div>
  <div class="gw-winner-name"><?= htmlspecialchars($gagnantNom) ?></div>
</div>
<?php endif; ?>

<div class="gw-coming-soon-wrap">
<div class="gw-layout">
  <!-- LEFT: WHEEL -->
  <div class="gw-wheel-zone">
    <div class="gw-wheel-box">
      <canvas id="cv" width="600" height="600"></canvas>
      <div class="gw-ptr">
        <svg viewBox="0 0 40 50" width="32" height="40"><defs><linearGradient id="pg" x1="20" y1="0" x2="20" y2="50" gradientUnits="userSpaceOnUse"><stop stop-color="#ff2d78"/><stop offset="1" stop-color="#c4185a"/></linearGradient></defs><path d="M20 50 L6 10 Q20 0 34 10 Z" fill="url(#pg)"/><circle cx="20" cy="14" r="4" fill="#fff" opacity=".9"/></svg>
      </div>
      <div class="gw-center">
        <img src="/assets/images/logo site.png" alt="StratEdge">
      </div>
    </div>

    <!-- Stats -->
    <div class="gw-stats" style="margin-top:1rem;width:100%;max-width:420px;">
      <div class="gw-stat">
        <div class="gw-stat-val"><?= $nbParticipants ?></div>
        <div class="gw-stat-lbl">Participants</div>
      </div>
      <div class="gw-stat">
        <div class="gw-stat-val"><?= $totalPts ?></div>
        <div class="gw-stat-lbl">Points total</div>
      </div>
    </div>
  </div>

  <!-- RIGHT: PANEL -->
  <div class="gw-panel">
    <!-- Prize -->
    <div class="gw-prize">
      <div class="gw-prize-title">🎁 Cadeau du mois</div>
      <?php if ($cadeau): ?>
        <div class="gw-prize-text"><?= nl2br(htmlspecialchars($cadeau)) ?></div>
      <?php else: ?>
        <div class="gw-prize-empty">Le cadeau sera annoncé prochainement…</div>
      <?php endif; ?>
    </div>

    <!-- My points -->
    <div class="gw-mypts">
      <div class="gw-mypts-val"><?= $mesPoints ?></div>
      <div class="gw-mypts-label">Mes points ce mois</div>
      <?php if ($maChance > 0): ?>
        <div class="gw-mypts-chance"><?= $maChance ?>% de chance de gagner</div>
      <?php elseif ($mesPoints === 0): ?>
        <div class="gw-mypts-chance" style="color:var(--txt3)">Souscris un pack pour participer !</div>
      <?php endif; ?>
    </div>

    <!-- History -->
    <div class="gw-history">
      <div class="gw-history-title">📋 Mes achats ce mois</div>
      <?php if (empty($mesDetails)): ?>
        <div class="gw-hist-empty">Aucun achat éligible ce mois</div>
      <?php else: ?>
        <?php foreach ($mesDetails as $d): ?>
        <div class="gw-hist-item">
          <span class="gw-hist-type"><?= $typeLabels[$d['type_abo']] ?? $d['type_abo'] ?></span>
          <span class="gw-hist-pts">+<?= $d['points'] ?> pt<?= $d['points']>1?'s':'' ?></span>
          <span class="gw-hist-date"><?= date('d/m', strtotime($d['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- How it works -->
    <div class="gw-how">
      <div class="gw-how-title">⚙️ Comment ça marche</div>
      <div class="gw-how-grid">
        <div class="gw-how-item"><div class="gw-how-dot" style="background:#ff2d78"></div>Daily<div class="gw-how-pts">1 pt</div></div>
        <div class="gw-how-item"><div class="gw-how-dot" style="background:#00d4ff"></div>Week-End<div class="gw-how-pts">3 pts</div></div>
        <div class="gw-how-item"><div class="gw-how-dot" style="background:#a855f7"></div>Weekly<div class="gw-how-pts">6 pts</div></div>
        <div class="gw-how-item"><div class="gw-how-dot" style="background:#f5c842"></div>VIP Max<div class="gw-how-pts">10 pts</div></div>
      </div>
      <p style="font-size:.75rem;color:var(--txt3);margin-top:.6rem;line-height:1.4;">Chaque achat entre le 1er et le dernier jour du mois te rapporte des points. Plus tu en as, plus tu as de chances de gagner le tirage au sort. Tirage en fin de mois par l'admin.</p>
    </div>
  </div>
</div>
<div class="gw-soon-overlay" role="presentation">
  <p class="gw-soon-text">Début des Giveaway Septembre</p>
</div>
</div>

<script>
var W=<?= json_encode($wheelData, JSON_UNESCAPED_UNICODE) ?>;
var TOT=0;for(var i=0;i<W.length;i++)TOT+=W[i].pts;
var COLS=['#ff2d78','#00d4ff','#a855f7','#f5c842','#00d46a','#ff6b2b','#e040fb','#00bcd4','#ff5252','#7c4dff','#64ffda','#ffd740','#ff4081','#448aff','#b388ff'];

var cv=document.getElementById('cv');
var ctx=cv.getContext('2d');
var CX=300,CY=300,R=270;

function draw(){
  ctx.clearRect(0,0,600,600);
  // Ring
  ctx.beginPath();ctx.arc(CX,CY,R+8,0,Math.PI*2);ctx.lineWidth=2;
  var rg=ctx.createLinearGradient(0,0,600,600);
  rg.addColorStop(0,'#ff2d78');rg.addColorStop(.5,'#00d4ff');rg.addColorStop(1,'#a855f7');
  ctx.strokeStyle=rg;ctx.stroke();

  if(TOT===0){
    ctx.beginPath();ctx.arc(CX,CY,R,0,Math.PI*2);ctx.fillStyle='#111827';ctx.fill();
    ctx.font='700 18px Orbitron,sans-serif';ctx.fillStyle='#8a9bb0';ctx.textAlign='center';
    ctx.fillText('Aucun participant',CX,CY);
    return;
  }

  var segS=-Math.PI/2;
  for(var i=0;i<W.length;i++){
    var sl=(W[i].pts/TOT)*Math.PI*2;
    var segE=segS+sl;
    ctx.beginPath();ctx.moveTo(CX,CY);ctx.arc(CX,CY,R,segS,segE);ctx.closePath();
    var col=COLS[i%COLS.length];
    if(W[i].moi) col='#ff2d78'; // Mon segment en rose
    var grd=ctx.createRadialGradient(CX,CY,40,CX,CY,R);
    grd.addColorStop(0,col+'55');grd.addColorStop(1,col+'cc');
    ctx.fillStyle=grd;ctx.fill();
    ctx.strokeStyle='rgba(0,0,0,.35)';ctx.lineWidth=1.5;ctx.stroke();

    if(sl>0.08){
      ctx.save();ctx.translate(CX,CY);ctx.rotate(segS+sl/2);
      ctx.textAlign='right';
      var fs=sl>.35?16:(sl>.18?13:10);
      ctx.font='700 '+fs+'px Orbitron,sans-serif';
      ctx.fillStyle=W[i].moi?'#fff':'rgba(255,255,255,.6)';
      ctx.shadowColor='rgba(0,0,0,.8)';ctx.shadowBlur=5;
      ctx.fillText(W[i].label,R-20,fs/3);
      ctx.shadowBlur=0;ctx.restore();
    }
    segS=segE;
  }
}
draw();
</script>
</main></div>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
