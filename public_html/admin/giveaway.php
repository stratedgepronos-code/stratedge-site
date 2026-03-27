<?php
// ============================================================
// STRATEDGE — GiveAway Admin
// public_html/admin/giveaway.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/giveaway-functions.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$pageActive = 'giveaway';
$db = getDB();

$tz = new DateTimeZone('Europe/Paris');
$moisActuel = (new DateTime('now', $tz))->format('Y-m');
$mois = $_GET['mois'] ?? $moisActuel;

// POST actions
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_cadeau') {
        $cadeau = trim($_POST['cadeau'] ?? '');
        if ($cadeau) {
            setGiveawayCadeau($mois, $cadeau);
            $success = 'Cadeau mis à jour !';
        }
    }

    if ($action === 'draw') {
        $config = getGiveawayConfig($mois);
        if ($config['statut'] === 'drawn') {
            $error = 'Ce mois a déjà été tiré au sort.';
        } else {
            $gagnant = tirerAuSort($mois);
            if ($gagnant) {
                $success = '🎉 Gagnant : ' . $gagnant['nom'] . ' (' . $gagnant['total_pts'] . ' pts)';
                // Email au gagnant
                try {
                    $stmtEmail = $db->prepare("SELECT email FROM membres WHERE id = ?");
                    $stmtEmail->execute([$gagnant['membre_id']]);
                    $emailGagnant = $stmtEmail->fetchColumn();
                    if ($emailGagnant) {
                        $cadeau = getGiveawayConfig($mois)['cadeau'] ?? 'un cadeau StratEdge';
                        envoyerEmail($emailGagnant, '🎁 Tu as gagné le GiveAway StratEdge !',
                            emailTemplate('🎁 Félicitations !',
                                '<p style="font-size:18px;font-weight:700;">Tu as été tiré au sort pour le GiveAway de <span style="color:#ff2d78;">' . moisFrancais($mois) . '</span> !</p>'
                                . '<p style="margin:1rem 0;">Avec <strong style="color:#00d4ff;">' . $gagnant['total_pts'] . ' points</strong>, tu remportes :</p>'
                                . '<div style="background:#111827;border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:1.2rem;margin:1rem 0;text-align:center;">'
                                . '<p style="font-size:20px;font-weight:700;color:#fff;">' . htmlspecialchars($cadeau) . '</p>'
                                . '</div>'
                                . '<p>Connecte-toi à ton espace pour plus d\'infos. L\'équipe StratEdge te contactera pour la remise.</p>'
                            )
                        );
                    }
                } catch (Exception $e) { /* silent */ }
            } else {
                $error = 'Aucun participant ce mois-ci.';
            }
        }
    }

    if ($action === 'reset') {
        $db->prepare("UPDATE giveaway_config SET statut = 'open', gagnant_id = NULL, gagnant_nom = NULL, drawn_at = NULL WHERE mois = ?")->execute([$mois]);
        $success = 'Tirage réinitialisé.';
    }
}

$config = getGiveawayConfig($mois);
$classement = getClassementMois($mois);
$totalPts = array_sum(array_column($classement, 'total_pts'));

// Data pour la roue JS
$wheelData = [];
foreach ($classement as $p) {
    $wheelData[] = ['name' => $p['nom'], 'pts' => (int)$p['total_pts']];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>🎁 GiveAway Admin — StratEdge</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;
  --border-subtle:rgba(255,45,120,0.12);--card:#111827;--txt3:#8a9bb0;--txt2:#b0bec9;
}
*{box-sizing:border-box;}
html,body{overflow-x:hidden;margin:0;}
body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;}
.ga-layout{display:grid;grid-template-columns:1fr 380px;gap:2rem;align-items:start;margin-top:1rem;}
.ga-title{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:900;margin-bottom:.3rem;}
.ga-title span{background:linear-gradient(135deg,#ff2d78,#a855f7,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.ga-sub{color:var(--text-muted);font-size:.9rem;margin-bottom:1.5rem;}
.ga-main-inner{max-width:1200px;}
.ga-wheel-zone{display:flex;flex-direction:column;align-items:center;}
.ga-wbox{position:relative;width:460px;max-width:100%;}
.ga-wbox canvas{display:block;width:100%;height:auto;border-radius:50%;box-shadow:0 0 40px rgba(255,45,120,.1);}
.ga-ptr{position:absolute;top:-10px;left:50%;transform:translateX(-50%);z-index:10;filter:drop-shadow(0 3px 12px rgba(255,45,120,.5));transition:filter .05s;}
.ga-ptr.flash{filter:drop-shadow(0 4px 25px rgba(255,45,120,1)) drop-shadow(0 0 12px #fff);}
.ga-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80px;height:80px;border-radius:50%;background:radial-gradient(circle,#111827,#0a0e17);border:2px solid rgba(255,45,120,.3);display:flex;align-items:center;justify-content:center;z-index:5;box-shadow:0 0 20px rgba(255,45,120,.2);}
.ga-center img{width:62px;height:auto;filter:drop-shadow(0 0 6px rgba(255,45,120,.3));}
/* Roue vide : le logo central masquait le texte canvas */
.ga-wbox--empty .ga-center{display:none;}
.ga-wheel-placeholder{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:4;text-align:center;pointer-events:none;padding:0 1rem;}
.ga-wheel-placeholder p{margin:0;font-family:'Orbitron',sans-serif;font-size:1rem;color:var(--text-muted);max-width:220px;line-height:1.35;}
.ga-wheel-placeholder small{display:block;margin-top:.5rem;font-size:.75rem;color:var(--text-muted);opacity:.85;}
.spin-btn{margin-top:1.2rem;padding:.9rem 2.5rem;font-family:'Orbitron',sans-serif;font-size:.9rem;font-weight:900;letter-spacing:3px;color:#fff;background:linear-gradient(135deg,#ff2d78,#c4185a);border:none;border-radius:12px;cursor:pointer;transition:all .3s;box-shadow:0 6px 25px rgba(255,45,120,.3);}
.spin-btn:hover{transform:translateY(-2px);box-shadow:0 10px 35px rgba(255,45,120,.5);}
.spin-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;}
.reset-btn{margin-top:.5rem;padding:.5rem 1.5rem;font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;color:#ff5555;background:rgba(255,50,50,.08);border:1px solid rgba(255,50,50,.2);border-radius:20px;cursor:pointer;}

/* Panel */
.ga-panel{display:flex;flex-direction:column;gap:1rem;}
.ga-card{background:var(--card,#111827);border:1px solid rgba(255,45,120,.12);border-radius:14px;padding:1.1rem;}
.ga-card-title{font-family:'Orbitron',sans-serif;font-size:.62rem;letter-spacing:2px;color:var(--txt3);text-transform:uppercase;margin-bottom:.6rem;}
.ga-input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:.6rem .8rem;color:#fff;font-family:'Rajdhani',sans-serif;font-size:.95rem;resize:vertical;min-height:80px;}
.ga-input:focus{border-color:#ff2d78;outline:none;}
.ga-save{margin-top:.5rem;padding:.5rem 1.2rem;font-family:'Orbitron',sans-serif;font-size:.65rem;letter-spacing:1px;color:#fff;background:linear-gradient(135deg,#00d4ff,#0089ff);border:none;border-radius:8px;cursor:pointer;}
.ga-lb-item{display:grid;grid-template-columns:28px 1fr auto auto;align-items:center;gap:.6rem;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.03);font-size:.88rem;}
.ga-lb-rank{font-family:'Orbitron',sans-serif;font-size:.7rem;font-weight:900;color:var(--txt3);}
.ga-lb-name{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ga-lb-pts{font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;color:#00d4ff;}
.ga-lb-pct{font-family:'Space Mono',monospace;font-size:.6rem;color:var(--txt3);}
.ga-alert{padding:.8rem 1rem;border-radius:10px;font-size:.9rem;margin-bottom:1rem;}
.ga-alert.ok{background:rgba(0,200,100,.1);border:1px solid rgba(0,200,100,.3);color:#00c864;}
.ga-alert.err{background:rgba(255,50,50,.1);border:1px solid rgba(255,50,50,.3);color:#ff5555;}
.ga-drawn{background:rgba(255,45,120,.08);border:1px solid rgba(255,45,120,.25);border-radius:14px;padding:1.2rem;text-align:center;margin-bottom:1rem;}
.ga-drawn-name{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:900;color:#ff2d78;}

/* Winner overlay */
.win-ov{position:fixed;inset:0;z-index:1000;background:rgba(6,8,16,.92);backdrop-filter:blur(20px);display:none;align-items:center;justify-content:center;}
.win-ov.show{display:flex;}
.win-box{text-align:center;animation:wPop .6s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes wPop{0%{transform:scale(.3);opacity:0}100%{transform:scale(1);opacity:1}}
.win-crown{font-size:5rem;margin-bottom:.5rem;animation:crB 1s ease infinite;}
@keyframes crB{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.win-label{font-family:'Space Mono',monospace;font-size:.7rem;letter-spacing:4px;color:#ff2d78;}
.win-name{font-family:'Orbitron',sans-serif;font-size:clamp(2rem,5vw,3rem);font-weight:900;background:linear-gradient(135deg,#ff2d78,#f5c842,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:.3rem 0;}
.win-info{font-size:1.1rem;color:var(--text-secondary);}
.win-close{margin-top:1.5rem;padding:.6rem 2rem;font-family:'Orbitron',sans-serif;font-size:.65rem;letter-spacing:2px;color:var(--text-secondary);background:0 0;border:1px solid rgba(255,45,120,.2);border-radius:30px;cursor:pointer;}
.cfc-w{position:fixed;inset:0;z-index:999;pointer-events:none;overflow:hidden;display:none;}
.cfc-w.show{display:block;}.cfc-p{position:absolute;top:-3%;animation:cfF linear forwards;}
@keyframes cfF{0%{transform:translateY(0) rotate(0);opacity:1}100%{transform:translateY(110vh) rotate(720deg);opacity:0}}

@media(max-width:900px){.ga-layout{grid-template-columns:1fr;}.ga-wbox{width:340px;margin:0 auto;}}
</style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main" style="padding:2rem 1.5rem 2rem;">
<div class="ga-main-inner">
<?php if ($success): ?><div class="ga-alert ok"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="ga-alert err"><?= $error ?></div><?php endif; ?>

<h1 class="ga-title">🎁 <span>GiveAway</span> — <?= moisFrancais($mois) ?></h1>
<p class="ga-sub"><?= $totalPts ?> points · <?= count($classement) ?> participants</p>

<?php if ($config['statut'] === 'drawn'): ?>
<div class="ga-drawn">
  <div>👑 TIRAGE EFFECTUÉ</div>
  <div class="ga-drawn-name"><?= htmlspecialchars($config['gagnant_nom'] ?? '?') ?></div>
  <div style="font-size:.8rem;color:var(--text-muted);margin-top:.3rem;"><?= date('d/m/Y H:i', strtotime($config['drawn_at'])) ?></div>
  <form method="post" style="margin-top:.6rem;"><input type="hidden" name="action" value="reset"><button class="reset-btn" onclick="return confirm('Re-tirer au sort ce mois ?')">🔄 Réinitialiser</button></form>
</div>
<?php endif; ?>

<div class="ga-layout">
  <!-- WHEEL -->
  <div class="ga-wheel-zone">
    <div class="ga-wbox<?= empty($classement) ? ' ga-wbox--empty' : '' ?>">
      <canvas id="cv" width="600" height="600"></canvas>
      <?php if (empty($classement)): ?>
      <div class="ga-wheel-placeholder">
        <p>Aucun participant</p>
        <small>Les points s’ajoutent quand un membre achète un pack Daily, Week-End, Weekly ou VIP Max (hors tennis).</small>
      </div>
      <?php endif; ?>
      <div class="ga-ptr" id="ptr">
        <svg viewBox="0 0 40 50" width="36" height="45"><defs><linearGradient id="pg" x1="20" y1="0" x2="20" y2="50" gradientUnits="userSpaceOnUse"><stop stop-color="#ff2d78"/><stop offset="1" stop-color="#c4185a"/></linearGradient></defs><path d="M20 50 L6 10 Q20 0 34 10 Z" fill="url(#pg)"/><circle cx="20" cy="14" r="4" fill="#fff" opacity=".9"/></svg>
      </div>
      <div class="ga-center">
        <img src="/assets/images/logo site.png" alt="StratEdge">
      </div>
    </div>
    <?php if ($config['statut'] !== 'drawn' && !empty($classement)): ?>
    <button class="spin-btn" id="sBtn" onclick="spinWheel()">🎰 LANCER LE TIRAGE</button>
    <?php endif; ?>
  </div>

  <!-- PANEL -->
  <div class="ga-panel">
    <!-- Cadeau -->
    <div class="ga-card">
      <div class="ga-card-title">🎁 Cadeau à gagner</div>
      <form method="post">
        <input type="hidden" name="action" value="set_cadeau">
        <textarea name="cadeau" class="ga-input" placeholder="Ex: 1 mois VIP Max offert"><?= htmlspecialchars($config['cadeau'] ?? '') ?></textarea>
        <button class="ga-save">💾 Sauvegarder</button>
      </form>
    </div>

    <!-- Classement -->
    <div class="ga-card">
      <div class="ga-card-title">🏆 Classement (<?= count($classement) ?>)</div>
      <?php if (empty($classement)): ?>
        <p style="color:var(--text-muted);font-size:.85rem;font-style:italic;">Aucun participant ce mois.</p>
      <?php else: ?>
        <?php foreach ($classement as $i => $p):
          $pct = $totalPts > 0 ? round($p['total_pts']/$totalPts*100,1) : 0;
        ?>
        <div class="ga-lb-item">
          <div class="ga-lb-rank"><?= $i<3?['👑','🥈','🥉'][$i]:($i+1) ?></div>
          <div class="ga-lb-name"><?= htmlspecialchars($p['nom']) ?></div>
          <div class="ga-lb-pts"><?= $p['total_pts'] ?> pts</div>
          <div class="ga-lb-pct"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Winner overlay -->
<div class="win-ov" id="winOv"><div class="win-box"><div class="win-crown">👑</div><div class="win-label">GAGNANT DU GIVEAWAY</div><div class="win-name" id="winN"></div><div class="win-info" id="winI"></div><button class="win-close" onclick="document.getElementById('winOv').classList.remove('show')">FERMER</button></div></div>
<div class="cfc-w" id="cfcW"></div>

<!-- Hidden form for server-side draw -->
<form id="drawForm" method="post" style="display:none;"><input type="hidden" name="action" value="draw"></form>

<script>
var P=<?= json_encode($wheelData, JSON_UNESCAPED_UNICODE) ?>;
var TOT=0;for(var i=0;i<P.length;i++)TOT+=P[i].pts;
var COLS=['#ff2d78','#00d4ff','#a855f7','#f5c842','#00d46a','#ff6b2b','#e040fb','#00bcd4','#ff5252','#7c4dff','#64ffda','#ffd740','#ff4081','#448aff','#b388ff'];

var cv=document.getElementById('cv'),ctx=cv.getContext('2d');
var CX=300,CY=300,R=270,angle=0;

function draw(rot){
  ctx.clearRect(0,0,600,600);
  ctx.beginPath();ctx.arc(CX,CY,R+8,0,Math.PI*2);ctx.lineWidth=2;
  var rg=ctx.createLinearGradient(0,0,600,600);rg.addColorStop(0,'#ff2d78');rg.addColorStop(.5,'#00d4ff');rg.addColorStop(1,'#a855f7');
  ctx.strokeStyle=rg;ctx.stroke();

  if(TOT===0){ctx.beginPath();ctx.arc(CX,CY,R,0,Math.PI*2);ctx.fillStyle='#111827';ctx.fill();ctx.font='700 18px Orbitron';ctx.fillStyle='#8a9bb0';ctx.textAlign='center';ctx.fillText('Aucun participant',CX,CY);return;}

  var segS=rot-Math.PI/2;
  for(var i=0;i<P.length;i++){
    var sl=(P[i].pts/TOT)*Math.PI*2;var segE=segS+sl;
    ctx.beginPath();ctx.moveTo(CX,CY);ctx.arc(CX,CY,R,segS,segE);ctx.closePath();
    var col=COLS[i%COLS.length];var grd=ctx.createRadialGradient(CX,CY,40,CX,CY,R);
    grd.addColorStop(0,col+'55');grd.addColorStop(1,col+'cc');ctx.fillStyle=grd;ctx.fill();
    ctx.strokeStyle='rgba(0,0,0,.35)';ctx.lineWidth=1.5;ctx.stroke();
    if(sl>0.08){ctx.save();ctx.translate(CX,CY);ctx.rotate(segS+sl/2);ctx.textAlign='right';
      var fs=sl>.35?16:(sl>.18?13:10);ctx.font='700 '+fs+'px Orbitron,sans-serif';
      ctx.fillStyle='#fff';ctx.shadowColor='rgba(0,0,0,.8)';ctx.shadowBlur=5;
      ctx.fillText(P[i].name,R-20,fs/3);ctx.shadowBlur=0;ctx.restore();}
    segS=segE;
  }
}

var spinning=false;
function spinWheel(){
  if(spinning||TOT===0)return;spinning=true;
  document.getElementById('sBtn').disabled=true;

  var r=Math.random()*TOT,cum=0,winIdx=0;
  for(var i=0;i<P.length;i++){cum+=P[i].pts;if(r<=cum){winIdx=i;break;}}

  var cumDeg=0;for(var j=0;j<winIdx;j++)cumDeg+=(P[j].pts/TOT)*360;
  var sliceDeg=(P[winIdx].pts/TOT)*360;
  var target=cumDeg+sliceDeg*(.25+Math.random()*.5);
  var totalDeg=360*(8+Math.floor(Math.random()*5))+(360-target);
  var totalRad=totalDeg*Math.PI/180;
  var startA=angle,t0=null,dur=6500+Math.random()*2000;
  var ptr=document.getElementById('ptr'),lastSeg=-1;

  function anim(ts){
    if(!t0)t0=ts;var el=ts-t0;var prog=Math.min(el/dur,1);
    var ease=1-Math.pow(1-prog,4);var cur=startA+totalRad*ease;
    draw(cur);angle=cur;
    var dNow=((cur*180/Math.PI)%360+360)%360;
    var seg=Math.floor(dNow/(360/Math.max(P.length,1)));
    if(seg!==lastSeg){lastSeg=seg;ptr.classList.add('flash');setTimeout(function(){ptr.classList.remove('flash');},50);}
    if(prog<1){requestAnimationFrame(anim);}
    else{spinning=false;
      setTimeout(function(){
        document.getElementById('winN').textContent=P[winIdx].name;
        document.getElementById('winI').textContent=P[winIdx].pts+' points · '+(P[winIdx].pts/TOT*100).toFixed(1)+'% de chance';
        document.getElementById('winOv').classList.add('show');fireCfc();
        // Submit le tirage côté serveur aussi
        setTimeout(function(){document.getElementById('drawForm').submit();},3000);
      },500);
    }
  }
  requestAnimationFrame(anim);
}

function fireCfc(){var c=document.getElementById('cfcW');c.innerHTML='';c.classList.add('show');
  var cols=['#ff2d78','#00d4ff','#a855f7','#f5c842','#00d46a','#fff'];
  for(var i=0;i<120;i++){var p=document.createElement('div');p.className='cfc-p';var sz=Math.random()*10+4;
    p.style.cssText='left:'+Math.random()*100+'%;width:'+sz+'px;height:'+sz+'px;background:'+cols[Math.floor(Math.random()*cols.length)]+';border-radius:'+(Math.random()>.5?'50%':'2px')+';animation-duration:'+(Math.random()*3+2)+'s;animation-delay:'+(Math.random()*1)+'s;opacity:'+(Math.random()*.8+.2);
    c.appendChild(p);}
  setTimeout(function(){c.classList.remove('show');},6000);}

draw(0);
</script>
</div><!-- .ga-main-inner -->
</div><!-- .main -->
</body>
</html>
