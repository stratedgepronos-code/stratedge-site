<?php
require_once __DIR__ . '/includes/auth.php';
$db = getDB();
$membre = isLoggedIn() ? getMembre() : null;
$abonnement = $membre ? getAbonnementActif($membre['id']) : null;
$hasAcces = $abonnement !== null;
$currentPage = 'bets';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;

// Filtrage des bets selon le type d'abonnement
$typeAbo = $abonnement['type'] ?? '';
if ($typeAbo === 'rasstoss') {
    // Rass-Toss = accès TOTAL (multi + tennis)
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 ORDER BY date_post DESC");
} elseif ($typeAbo === 'tennis') {
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 AND categorie = 'tennis' ORDER BY date_post DESC");
} else {
    $stmt = $db->query("SELECT * FROM bets WHERE actif = 1 AND categorie = 'multi' ORDER BY date_post DESC");
}
$bets = $stmt->fetchAll();
$typeLabels = ['safe'=>'🛡️ Safe','fun'=>'🎯 Fun','live'=>'⚡ Live'];
$typeColors = ['safe'=>'#00d4ff','fun'=>'#a855f7','live'=>'#ff2d78'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Les Bets – StratEdge Pronos</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<meta name="apple-mobile-web-app-capable" content="yes"><link rel="apple-touch-icon" href="/assets/images/mascotte.png">
<?php if ($membre): ?>
  <?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<?php else: ?>
<style>
:root{--bg:#050810;--card:#111827;--pink:#ff2d78;--pink-dim:#d6245f;--blue:#00d4ff;--purple:#a855f7;--txt:#f0f4f8;--txt2:#b0bec9;--txt3:#8a9bb0;--border:rgba(255,45,120,0.15);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;}
nav{background:rgba(5,8,16,0.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 2rem;position:sticky;top:0;z-index:100;}
.nav-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:65px;}
.logo img{height:35px;}
.nav-right{display:flex;align-items:center;gap:1.5rem;}
.nav-right a{color:var(--txt2);text-decoration:none;font-size:0.95rem;font-weight:600;transition:color .3s;}
.nav-right a:hover{color:var(--pink);}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:5px;background:none;border:none;}
.hamburger span{display:block;width:24px;height:2px;background:var(--txt);border-radius:2px;}
.mobile-menu{display:none;position:fixed;inset:0;top:65px;background:rgba(5,8,16,0.98);backdrop-filter:blur(20px);z-index:99;padding:2rem;flex-direction:column;}
.mobile-menu.open{display:flex;}
.mobile-menu a{color:var(--txt2);text-decoration:none;font-size:1.05rem;font-weight:600;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}
@media(max-width:700px){.nav-right{display:none;}.hamburger{display:flex;}}
</style>
<?php endif; ?>
<style>
/* ═══ BETS PAGE ═══ */
.bets-hero{position:relative;text-align:center;overflow:hidden;background:linear-gradient(180deg,rgba(255,45,120,0.07) 0%,transparent 100%);border-bottom:1px solid var(--border,rgba(255,45,120,0.15));margin-left:calc(-3rem - var(--sidebar-w,270px));margin-right:-3rem;margin-top:-2.5rem;padding:3.5rem 2rem 3rem calc(3rem + var(--sidebar-w,270px));}
.bets-hero::before{content:'';position:absolute;width:600px;height:400px;background:radial-gradient(circle,rgba(255,45,120,0.1) 0%,transparent 70%);top:-200px;left:50%;transform:translateX(-50%);pointer-events:none;}
.bets-hero::after{content:'';position:absolute;width:400px;height:300px;background:radial-gradient(circle,rgba(0,212,255,0.06) 0%,transparent 70%);bottom:-150px;right:-100px;pointer-events:none;}
/* Hero sans sidebar (visiteur) */
body:not(.app-body) .bets-hero{margin-left:-2rem;margin-right:-2rem;padding:3rem 2rem;}
.bets-tag{font-family:'Space Mono',monospace;font-size:0.75rem;letter-spacing:4px;text-transform:uppercase;color:var(--pink,#ff2d78);margin-bottom:0.7rem;}
.bets-title{font-family:'Orbitron',sans-serif;font-size:2.4rem;font-weight:900;margin-bottom:0.6rem;position:relative;}
.bets-title span{background:linear-gradient(135deg,#ff2d78,#ff6b9d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.bets-sub{color:var(--txt2,#b0bec9);font-size:1.05rem;max-width:500px;margin:0 auto;}

.bets-wrap{max-width:920px;margin:0 auto;padding:2rem 0.5rem;}

/* Banner abo */
.abo-b{border-radius:14px;padding:1.4rem 1.8rem;margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
.abo-b.ok{background:linear-gradient(135deg,rgba(0,212,106,0.08),rgba(0,212,255,0.04));border:1px solid rgba(0,212,106,0.25);}
.abo-b.no{background:linear-gradient(135deg,rgba(255,45,120,0.06),rgba(255,107,43,0.04));border:1px solid var(--border,rgba(255,45,120,0.15));}
.abo-b h3{font-family:'Orbitron',sans-serif;font-size:1rem;margin-bottom:0.25rem;}
.abo-b p{color:var(--txt3,#8a9bb0);font-size:0.92rem;}
.btn-sub{background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.75rem 1.6rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;display:inline-flex;align-items:center;gap:0.4rem;}
.btn-sub:hover{box-shadow:0 0 30px rgba(255,45,120,0.35);transform:translateY(-2px);}

/* Bets grid */
.bets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(360px,100%),1fr));gap:1.5rem;}
.bet-card{background:var(--card,#111827);border:1px solid var(--border,rgba(255,45,120,0.15));border-radius:18px;overflow:hidden;transition:all .3s;position:relative;}
.bet-card:hover{transform:translateY(-5px);box-shadow:0 25px 70px rgba(0,0,0,0.5),0 0 20px rgba(255,45,120,0.1);border-color:rgba(255,45,120,0.35);}
.bet-top{padding:1.1rem 1.4rem 0.7rem;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;}
.bet-badges{display:flex;gap:0.5rem;flex-wrap:wrap;}
.bet-badge{padding:0.3rem 0.85rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:0.68rem;font-weight:700;letter-spacing:1px;display:flex;align-items:center;gap:0.3rem;}
.bet-date{font-size:0.85rem;color:var(--txt3,#8a9bb0);font-family:'Space Mono',monospace;font-size:0.72rem;}
.bet-titre{font-family:'Orbitron',sans-serif;font-size:0.95rem;padding:0 1.4rem 0.9rem;color:var(--txt2,#b0bec9);font-weight:600;}
.bet-img-wrap{position:relative;overflow:hidden;}
.bet-img{width:100%;display:block;transition:transform .3s;}
.bet-img.blur{filter:blur(14px);transform:scale(1.08);}
.bet-img-wrap.zoomable{cursor:zoom-in;}
.bet-img-wrap.zoomable:hover .bet-img{transform:scale(1.02);}
.zoom-tip{position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,0.7);color:#fff;font-size:0.75rem;padding:0.35rem 0.7rem;border-radius:8px;backdrop-filter:blur(4px);pointer-events:none;opacity:0;transition:opacity .2s;}
.bet-img-wrap.zoomable:hover .zoom-tip{opacity:1;}
.lock-ov{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(5,8,16,0.75);backdrop-filter:blur(6px);padding:2rem;text-align:center;gap:0.9rem;}
.lock-ov .lock-i{font-size:2.8rem;filter:drop-shadow(0 0 10px rgba(255,45,120,0.3));}
.lock-ov .lock-t{font-family:'Orbitron',sans-serif;font-size:1.05rem;font-weight:700;}
.lock-ov .lock-s{color:var(--txt3,#8a9bb0);font-size:0.9rem;max-width:240px;line-height:1.4;}
.lock-ov .lock-b{background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.7rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.95rem;text-transform:uppercase;letter-spacing:1px;transition:all .3s;}
.lock-ov .lock-b:hover{box-shadow:0 0 25px rgba(255,45,120,0.4);transform:translateY(-1px);}
.bet-no-img{width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,rgba(255,45,120,0.04),rgba(0,212,255,0.02));display:flex;align-items:center;justify-content:center;font-size:3.5rem;color:var(--txt3,#8a9bb0);}
.no-bets{text-align:center;padding:5rem 2rem;color:var(--txt3,#8a9bb0);}
.no-bets .big{font-size:4rem;margin-bottom:1rem;}
.no-bets h3{font-family:'Orbitron',sans-serif;font-size:1.3rem;margin-bottom:0.5rem;color:var(--txt2,#b0bec9);}
.no-bets p{font-size:1rem;}

/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(5,8,16,0.96);backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:2rem;}
.lightbox.open{display:flex;}
.lightbox-inner{position:relative;max-width:95vw;max-height:92vh;}
.lightbox-img{max-width:100%;max-height:88vh;border-radius:14px;box-shadow:0 0 80px rgba(255,45,120,0.15);display:block;}
.lightbox-close{position:fixed;top:1.2rem;right:1.5rem;background:rgba(255,45,120,0.15);border:1px solid rgba(255,45,120,0.3);color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.3rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.lightbox-close:hover{background:#ff2d78;}
.lightbox-caption{text-align:center;margin-top:0.8rem;color:var(--txt3,#8a9bb0);font-size:0.88rem;}

@media(max-width:768px){
  .bets-grid{grid-template-columns:1fr;gap:1rem;max-width:100%;}
  .abo-b{padding:1rem;flex-direction:column;align-items:flex-start;border-radius:10px;}
  .abo-b h3{font-size:0.9rem;}
  .abo-b p{font-size:0.85rem;}
  .btn-sub{width:100%;justify-content:center;padding:0.65rem 1.2rem;font-size:0.9rem;}
  .bets-hero{margin-left:-0.8rem !important;margin-right:-0.8rem !important;margin-top:-1rem;padding:1.5rem 0.8rem !important;}
  .bets-hero::before,.bets-hero::after{display:none;}
  .bets-title{font-size:1.5rem;}
  .bets-sub{font-size:0.88rem;max-width:none;}
  .bets-wrap{padding:1rem 0;}
  .bet-card{border-radius:14px;}
  .bet-card:hover{transform:none;box-shadow:none;}
  .lock-ov{padding:1.5rem;}
  .lock-ov .lock-i{font-size:2.2rem;}
  .lock-ov .lock-t{font-size:0.88rem;}
  .lock-ov .lock-s{font-size:0.8rem;max-width:none;}
  .lock-ov .lock-b{font-size:0.82rem;padding:0.55rem 1rem;min-height:44px;display:inline-flex;align-items:center;}
  .no-bets .big{font-size:2.8rem;}
  .no-bets h3{font-size:1rem;}
  .no-bets p{font-size:0.88rem;}
  .lightbox-img{border-radius:8px;max-height:80dvh;}
}
@media(max-width:480px){
  .bets-title{font-size:1.3rem;}
  .bets-tag{font-size:0.6rem;letter-spacing:2px;}
  .bet-top{padding:0.8rem 0.9rem 0.4rem;}
  .bet-titre{padding:0 0.9rem 0.6rem;font-size:0.82rem;}
  .bet-badge{font-size:0.58rem;padding:0.2rem 0.55rem;}
  .bet-date{font-size:0.62rem;}
  .bet-card{border-radius:12px;}
  .lock-ov .lock-i{font-size:1.8rem;}
}
@media(max-width:360px){
  .bets-title{font-size:1.15rem;}
  .bet-top{padding:0.7rem 0.7rem 0.3rem;}
  .bet-titre{padding:0 0.7rem 0.5rem;font-size:0.78rem;}
}
</style>
</head>
<body>
<?php if ($membre): ?>
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php else: ?>
  <nav><div class="nav-inner">
    <a href="/" class="logo"><img src="assets/images/logo site.png" alt="StratEdge"></a>
    <div class="nav-right">
      <a href="/#pricing">Tarifs</a>
      <a href="login.php">Connexion</a>
      <a href="register.php" style="background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff;padding:0.45rem 1.1rem;border-radius:8px;font-weight:700;">S'inscrire</a>
    </div>
    <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
  </div></nav>
  <div class="mobile-menu" id="mobileMenu"><a href="/#pricing">Tarifs</a><a href="login.php">Connexion</a><a href="register.php">S'inscrire</a></div>
<?php endif; ?>

<div class="bets-hero">
  <div class="bets-tag">⚡ Pronos en cours</div>
  <h1 class="bets-title">Les <span>Bets</span></h1>
  <p class="bets-sub">Nos analyses et cards de bets. Abonnement requis pour le contenu complet.</p>
</div>

<div class="bets-wrap">
  <?php if ($hasAcces): ?>
  <div class="abo-b ok"><div><h3>✅ Accès complet débloqué</h3>
    <p><?php if ($abonnement['type']==='rasstoss'): ?><span style="color:#ffd700;font-weight:700;">👑 Rass-Toss — Life Time ♾️</span>
    <?php elseif ($abonnement['type']==='daily'): ?>Daily — expire au prochain bet
    <?php elseif ($abonnement['type']==='weekend'): ?>Week-End — expire le <?= date('d/m/Y à H:i',strtotime($abonnement['date_fin'])) ?>
    <?php else: ?>Weekly 7j — expire le <?= date('d/m/Y à H:i',strtotime($abonnement['date_fin'])) ?><?php endif; ?></p>
  </div><span style="font-size:1.6rem;">🔓</span></div>
  <?php else: ?>
  <div class="abo-b no"><div><h3>🔒 Contenu verrouillé</h3><p>Souscris pour accéder aux analyses complètes des bets.</p></div>
    <?php if (!isLoggedIn()): ?><a href="login.php" class="btn-sub">Se connecter →</a>
    <?php else: ?><a href="/#pricing" class="btn-sub">Voir les formules →</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($bets)): ?>
  <div class="no-bets"><div class="big">🎯</div><h3>Aucun bet disponible</h3><p>Les nouvelles analyses arrivent bientôt, reste connecté !</p></div>
  <?php else: ?>
  <div class="bets-grid">
    <?php foreach ($bets as $bet):
      $types  = explode(',', $bet['type']);
      // Afficher l'image dès que image_path est en BDD (ne pas dépendre de file_exists, souvent faux en prod)
      $imgSrc = !empty($bet['image_path']) ? (defined('SITE_URL') ? rtrim(SITE_URL,'/').'/'.ltrim($bet['image_path'],'/') : clean($bet['image_path'])) : '';
    ?>
    <div class="bet-card">
      <div class="bet-top">
        <div class="bet-badges">
          <?php foreach ($types as $t): $t=trim($t); $c=$typeColors[$t]??'#ff2d78'; ?>
          <span class="bet-badge" style="background:<?=$c?>18;color:<?=$c?>;border:1px solid <?=$c?>40;"><?= $typeLabels[$t]??$t ?></span>
          <?php endforeach; ?>
        </div>
        <span class="bet-date"><?= date('d/m/Y',strtotime($bet['date_post'])) ?></span>
      </div>
      <?php if ($bet['titre']): ?><div class="bet-titre"><?= clean($bet['titre']) ?></div><?php endif; ?>
      <div class="bet-img-wrap <?= ($hasAcces && $imgSrc)?'zoomable':'' ?>"
           <?= ($hasAcces && $imgSrc)?'data-src="'.clean($imgSrc).'" data-caption="'.htmlspecialchars($bet['titre']?:'Bet StratEdge',ENT_QUOTES).'"':'' ?>>
        <?php if ($imgSrc): ?>
        <img src="<?= clean($imgSrc) ?>" alt="Bet" class="bet-img <?= !$hasAcces?'blur':'' ?>" onerror="this.onerror=null;this.style.display='none';var w=this.closest('.bet-img-wrap');var p=w&&w.querySelector('.bet-no-img');if(p)p.style.display='flex';">
        <div class="bet-no-img <?= !$hasAcces?'blur':'' ?>" style="display:none" aria-hidden="true">📊</div>
        <?php if ($hasAcces): ?><div class="zoom-tip">🔍 Cliquer pour agrandir</div><?php endif; ?>
        <?php else: ?><div class="bet-no-img <?= !$hasAcces?'blur':'' ?>">📊</div><?php endif; ?>
        <?php if (!$hasAcces): ?>
        <div class="lock-ov"><div class="lock-i">🔒</div><div class="lock-t">Contenu verrouillé</div><div class="lock-s">Souscris pour accéder à l'analyse complète.</div>
          <a href="<?= isLoggedIn()?'/#pricing':'login.php' ?>" class="lock-b"><?= isLoggedIn()?'Voir les formules':'Se connecter' ?> →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($membre): ?></main></div><?php endif; ?>

<div class="lightbox" id="lightbox">
  <button class="lightbox-close" onclick="closeLB()">✕</button>
  <div class="lightbox-inner" onclick="event.stopPropagation()">
    <img src="" alt="" class="lightbox-img" id="lbImg">
    <div class="lightbox-caption" id="lbCap"></div>
  </div>
</div>

<script>
document.querySelectorAll('.bet-img-wrap.zoomable').forEach(function(el){
  el.addEventListener('click',function(){var s=el.dataset.src;if(!s)return;
    document.getElementById('lbImg').src=s;document.getElementById('lbCap').textContent=el.dataset.caption||'';
    document.getElementById('lightbox').classList.add('open');document.body.style.overflow='hidden';});
});
function closeLB(){document.getElementById('lightbox').classList.remove('open');document.getElementById('lbImg').src='';document.body.style.overflow='';}
document.getElementById('lightbox').addEventListener('click',function(e){if(e.target===this)closeLB();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeLB();});
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('open');}
</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
