<?php
// ============================================================
// STRATEDGE — Montante Hub
// Page de choix entre Montante Foot et Montante Tennis
// ============================================================
require_once __DIR__ . '/includes/auth.php';
$db = getDB();

$membre = isLoggedIn() ? getMembre() : null;
$currentPage = 'montante';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;

// ── Stats rapides pour chaque montante (pour aperçu) ──
function fetchMontanteQuickStats(PDO $db, string $configTable, string $stepsTable): array {
    $res = ['active' => false, 'current' => 0, 'target' => 0, 'progress' => 0, 'wins' => 0, 'losses' => 0, 'nom' => ''];
    try {
        $cfg = $db->query("SELECT * FROM {$configTable} WHERE statut='active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$cfg) {
            return $res;
        }
        $res['active'] = true;
        $res['nom'] = $cfg['nom'] ?? '';
        $initial = (float)$cfg['bankroll_initial'];
        $target = (float)($cfg['bankroll_target'] ?? 500);

        $stmt = $db->prepare("SELECT * FROM {$stepsTable} WHERE montante_id = ? ORDER BY step_number ASC");
        $stmt->execute([$cfg['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $br = $initial;
        $w = 0; $l = 0;
        foreach ($rows as $r) {
            if ($r['resultat'] === 'gagne') { $w++; if ($r['bankroll_apres'] !== null) $br = (float)$r['bankroll_apres']; }
            elseif ($r['resultat'] === 'perdu') { $l++; if ($r['bankroll_apres'] !== null) $br = (float)$r['bankroll_apres']; }
        }
        $res['current'] = $br;
        $res['target'] = $target > 0 ? $target : 500;
        $res['progress'] = $res['target'] > 0 ? round(($br / $res['target']) * 100) : 0;
        $res['wins'] = $w;
        $res['losses'] = $l;
    } catch (Throwable $e) { /* tables not yet created */ }
    return $res;
}

$statsTennis = fetchMontanteQuickStats($db, 'montante_config', 'montante_steps');
$statsFoot   = fetchMontanteQuickStats($db, 'montante_foot_config', 'montante_foot_steps');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Montante — StratEdge</title>
<link rel="icon" type="image/png" href="/assets/images/mascotte.png">
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#050810">
<meta name="apple-mobile-web-app-capable" content="yes"><link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Bebas+Neue&family=Instrument+Serif:ital@0;1&family=Rajdhani:wght@400;500;600;700&family=Archivo+Narrow:wght@400;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
/* ── Hub page styles ── */
.hub-wrap{max-width:1200px;margin:0 auto;padding:2rem 0 3rem;position:relative;z-index:2;}

.hub-hero{text-align:center;margin-bottom:3.5rem;position:relative;}
.hub-kicker{
  font-family:'Archivo Narrow',sans-serif;font-size:13px;font-weight:700;
  letter-spacing:4px;text-transform:uppercase;
  color:rgba(255,45,120,0.8);
  margin-bottom:14px;
}
.hub-hero h1{
  font-family:'Bebas Neue',sans-serif;font-weight:400;
  font-size:clamp(3rem,8vw,5.5rem);line-height:0.9;letter-spacing:-1px;
  color:#fff;margin-bottom:14px;
}
.hub-hero h1 .italic{
  font-family:'Instrument Serif',serif;font-style:italic;
  background:linear-gradient(135deg,#ff2d78,#00d4ff);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
  filter:drop-shadow(0 0 20px rgba(255,45,120,0.3));
}
.hub-hero p{
  max-width:560px;margin:0 auto;
  color:var(--txt2);font-size:1rem;line-height:1.6;
}

/* Cards grid */
.hub-cards{
  display:grid;grid-template-columns:1fr 1fr;gap:1.8rem;
}
@media(max-width:820px){.hub-cards{grid-template-columns:1fr;}}

.hub-card{
  position:relative;overflow:hidden;
  border-radius:24px;
  min-height:480px;
  padding:2rem;
  display:flex;flex-direction:column;
  text-decoration:none;color:inherit;
  border:1px solid rgba(255,255,255,0.08);
  transition:all .4s cubic-bezier(0.16,1,0.3,1);
  cursor:pointer;
}
.hub-card:hover{
  transform:translateY(-4px);
  border-color:rgba(255,255,255,0.2);
}

/* Foot card (rose + cyan multi) */
.hub-card.foot{
  background:
    radial-gradient(ellipse 400px 300px at 100% 0%, rgba(255,45,120,0.25), transparent 60%),
    radial-gradient(ellipse 500px 400px at 0% 100%, rgba(0,212,255,0.15), transparent 60%),
    linear-gradient(180deg, #14080f 0%, #0a0610 100%);
}
.hub-card.foot::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,transparent 60%,rgba(255,45,120,0.08));
  pointer-events:none;
}
.hub-card.foot:hover{box-shadow:0 0 60px rgba(255,45,120,0.3),0 20px 60px rgba(0,0,0,0.5);}

/* Tennis card (vert néon) */
.hub-card.tennis{
  background:
    radial-gradient(ellipse 400px 300px at 100% 0%, rgba(57,255,20,0.22), transparent 60%),
    radial-gradient(ellipse 500px 400px at 0% 100%, rgba(0,212,255,0.12), transparent 60%),
    linear-gradient(180deg, #0a1a0f 0%, #050a06 100%);
}
.hub-card.tennis::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,transparent 60%,rgba(57,255,20,0.08));
  pointer-events:none;
}
.hub-card.tennis:hover{box-shadow:0 0 60px rgba(57,255,20,0.3),0 20px 60px rgba(0,0,0,0.5);}

/* Shimmer border animé au hover */
.hub-card::after{
  content:'';position:absolute;inset:0;border-radius:24px;
  padding:1.5px;
  background:linear-gradient(90deg,transparent,currentColor,transparent);
  background-size:200% 100%;
  -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
  -webkit-mask-composite:xor;mask-composite:exclude;
  opacity:0;
  animation:hubShimmer 3s linear infinite;
  pointer-events:none;
  transition:opacity .3s;
}
.hub-card:hover::after{opacity:0.7;}
.hub-card.foot::after{color:#ff2d78;}
.hub-card.tennis::after{color:#39ff14;}
@keyframes hubShimmer{
  0%{background-position:-200% 0;}
  100%{background-position:200% 0;}
}

/* Ghost big text en arrière */
.hub-ghost{
  position:absolute;
  font-family:'Bebas Neue',sans-serif;
  font-size:400px;line-height:0.8;letter-spacing:-20px;
  top:-40px;right:-60px;
  opacity:0.05;
  z-index:0;pointer-events:none;
  transform:rotate(-8deg);
}
.hub-card.foot .hub-ghost{color:#ff2d78;}
.hub-card.tennis .hub-ghost{color:#39ff14;}

/* Card content */
.hub-card-inner{position:relative;z-index:2;display:flex;flex-direction:column;height:100%;}

.hub-badge{
  align-self:flex-start;
  display:inline-flex;align-items:center;gap:8px;
  padding:6px 14px;border-radius:40px;
  font-family:'Archivo Narrow',sans-serif;font-size:11px;font-weight:700;
  letter-spacing:2.5px;text-transform:uppercase;
  margin-bottom:20px;
}
.hub-card.foot .hub-badge{
  background:rgba(255,45,120,0.12);border:1px solid rgba(255,45,120,0.4);color:#ff6ba1;
}
.hub-card.tennis .hub-badge{
  background:rgba(57,255,20,0.1);border:1px solid rgba(57,255,20,0.4);color:#39ff14;
}
.hub-badge .hub-badge-dot{
  width:7px;height:7px;border-radius:50%;
  animation:hubDotPulse 1.5s ease-in-out infinite;
}
.hub-card.foot .hub-badge .hub-badge-dot{background:#ff2d78;box-shadow:0 0 8px #ff2d78;}
.hub-card.tennis .hub-badge .hub-badge-dot{background:#39ff14;box-shadow:0 0 8px #39ff14;}
@keyframes hubDotPulse{0%,100%{opacity:1;}50%{opacity:0.4;}}

.hub-title{
  font-family:'Bebas Neue',sans-serif;font-weight:400;
  font-size:4rem;line-height:0.92;letter-spacing:-1px;
  color:#fff;margin-bottom:6px;
}
.hub-subtitle{
  font-family:'Instrument Serif',serif;font-style:italic;
  font-size:1.25rem;
  margin-bottom:24px;
}
.hub-card.foot .hub-subtitle{color:#ff6ba1;}
.hub-card.tennis .hub-subtitle{color:#39ff14;}

.hub-desc{
  color:var(--txt2);font-size:0.95rem;line-height:1.55;
  margin-bottom:24px;
  max-width:280px;
}

/* Mascotte section */
.hub-mascot{
  position:absolute;
  right:-50px;bottom:-30px;
  width:340px;height:340px;
  z-index:1;pointer-events:none;
  transition:transform .4s cubic-bezier(0.16,1,0.3,1);
}
.hub-mascot img{
  width:100%;height:100%;object-fit:contain;object-position:center bottom;
}
.hub-card.foot .hub-mascot{filter:drop-shadow(0 0 40px rgba(255,45,120,0.4));}
.hub-card.tennis .hub-mascot{filter:drop-shadow(0 0 40px rgba(57,255,20,0.4));}
.hub-card:hover .hub-mascot{transform:scale(1.05) translateY(-6px);}

/* Stats */
.hub-stats{
  display:flex;gap:1.5rem;margin-top:auto;margin-bottom:1.5rem;
  position:relative;z-index:3;
  padding:14px 16px;border-radius:12px;
  background:rgba(0,0,0,0.35);
  border:1px solid rgba(255,255,255,0.05);
  backdrop-filter:blur(10px);
  max-width:fit-content;
}
.hub-stat{display:flex;flex-direction:column;gap:2px;}
.hub-stat-lbl{
  font-family:'Archivo Narrow',sans-serif;font-size:9px;
  letter-spacing:2px;text-transform:uppercase;
  color:rgba(255,255,255,0.4);
}
.hub-stat-val{
  font-family:'Orbitron',sans-serif;font-weight:900;
  font-size:18px;line-height:1;
}
.hub-card.foot .hub-stat-val{color:#ff6ba1;}
.hub-card.tennis .hub-stat-val{color:#39ff14;}

/* Progress bar */
.hub-progress{
  margin-bottom:16px;position:relative;z-index:3;
  max-width:260px;
}
.hub-progress-label{
  display:flex;justify-content:space-between;
  font-family:'Share Tech Mono',monospace;font-size:12px;
  color:rgba(255,255,255,0.6);margin-bottom:6px;
  letter-spacing:0.5px;
}
.hub-progress-bar{
  height:6px;border-radius:3px;overflow:hidden;
  background:rgba(255,255,255,0.06);
  position:relative;
}
.hub-progress-fill{
  height:100%;border-radius:3px;
  transition:width 1s ease;
  position:relative;
}
.hub-card.foot .hub-progress-fill{
  background:linear-gradient(90deg,#ff2d78,#c850c0);
  box-shadow:0 0 12px rgba(255,45,120,0.5);
}
.hub-card.tennis .hub-progress-fill{
  background:linear-gradient(90deg,#39ff14,#00d46a);
  box-shadow:0 0 12px rgba(57,255,20,0.5);
}

/* CTA arrow */
.hub-cta{
  position:relative;z-index:3;margin-top:auto;
  display:inline-flex;align-items:center;gap:10px;
  font-family:'Orbitron',sans-serif;font-weight:700;
  font-size:13px;letter-spacing:2px;text-transform:uppercase;
}
.hub-card.foot .hub-cta{color:#ff6ba1;}
.hub-card.tennis .hub-cta{color:#39ff14;}
.hub-cta .arrow{
  display:inline-block;width:28px;height:1.5px;
  position:relative;
  transition:width .3s;
}
.hub-card.foot .hub-cta .arrow{background:#ff2d78;}
.hub-card.tennis .hub-cta .arrow{background:#39ff14;}
.hub-cta .arrow::after{
  content:'';position:absolute;right:-1px;top:-4px;
  width:8px;height:8px;border-top:1.5px solid currentColor;border-right:1.5px solid currentColor;
  transform:rotate(45deg);
  color:inherit;
}
.hub-card.foot .hub-cta .arrow::after{border-color:#ff2d78;}
.hub-card.tennis .hub-cta .arrow::after{border-color:#39ff14;}
.hub-card:hover .hub-cta .arrow{width:40px;}

.hub-inactive{
  color:rgba(255,255,255,0.4);
  font-family:'Share Tech Mono',monospace;
  font-size:12px;letter-spacing:1px;
  padding:10px 14px;border-radius:8px;
  background:rgba(255,255,255,0.03);
  border:1px dashed rgba(255,255,255,0.1);
  display:inline-block;max-width:fit-content;
}

/* Responsive */
@media(max-width:820px){
  .hub-mascot{width:240px;height:240px;right:-30px;bottom:-20px;}
  .hub-ghost{font-size:260px;right:-30px;}
  .hub-title{font-size:3rem;}
  .hub-card{min-height:420px;padding:1.5rem;}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="hub-wrap">

  <div class="hub-hero">
    <div class="hub-kicker">StratEdge · Montante Hub</div>
    <h1>L'art de la <span class="italic">progression.</span></h1>
    <p>Deux disciplines. Deux méthodes. Chaque pari défendu, chaque palier franchi — suis la montante qui te parle.</p>
  </div>

  <div class="hub-cards">

    <!-- MONTANTE FOOT -->
    <a href="/montante-foot.php" class="hub-card foot">
      <div class="hub-ghost">FOOT</div>
      <div class="hub-mascot"><img src="/assets/images/mascotte-rose.png" alt="Multi Mascot" onerror="this.src='/assets/images/mascotte.png'"></div>
      <div class="hub-card-inner">
        <div class="hub-badge">
          <span class="hub-badge-dot"></span>
          <?= $statsFoot['active'] ? 'Campagne live' : 'Prochainement' ?>
        </div>
        <h2 class="hub-title">MONTANTE<br>FOOT</h2>
        <div class="hub-subtitle">Multi · Strategy</div>
        <p class="hub-desc">Paris combinés et 1X2, travaillés avec la data Big 5 et Champions League. Palier par palier.</p>

        <?php if ($statsFoot['active']): ?>
        <div class="hub-progress">
          <div class="hub-progress-label">
            <span><?= number_format($statsFoot['current'], 0) ?>€</span>
            <span><?= $statsFoot['progress'] ?>%</span>
            <span><?= number_format($statsFoot['target'], 0) ?>€</span>
          </div>
          <div class="hub-progress-bar">
            <div class="hub-progress-fill" style="width:<?= min(100, max(3, $statsFoot['progress'])) ?>%;"></div>
          </div>
        </div>
        <div class="hub-stats">
          <div class="hub-stat">
            <div class="hub-stat-lbl">Wins</div>
            <div class="hub-stat-val"><?= $statsFoot['wins'] ?></div>
          </div>
          <div class="hub-stat">
            <div class="hub-stat-lbl">Losses</div>
            <div class="hub-stat-val"><?= $statsFoot['losses'] ?></div>
          </div>
          <div class="hub-stat">
            <div class="hub-stat-lbl">Objectif</div>
            <div class="hub-stat-val"><?= number_format($statsFoot['target'], 0) ?>€</div>
          </div>
        </div>
        <?php else: ?>
        <div class="hub-inactive" style="margin-top:auto;margin-bottom:1rem;">Aucune campagne active pour le moment</div>
        <?php endif; ?>

        <div class="hub-cta">
          Suivre la montante
          <span class="arrow"></span>
        </div>
      </div>
    </a>

    <!-- MONTANTE TENNIS -->
    <a href="/montante-tennis.php" class="hub-card tennis">
      <div class="hub-ghost">TENNIS</div>
      <div class="hub-mascot"><img src="/assets/images/mascotte-tennis-nobg.png" alt="Tennis Ace"></div>
      <div class="hub-card-inner">
        <div class="hub-badge">
          <span class="hub-badge-dot"></span>
          <?= $statsTennis['active'] ? 'Campagne live' : 'Prochainement' ?>
        </div>
        <h2 class="hub-title">MONTANTE<br>TENNIS</h2>
        <div class="hub-subtitle">Baseline · Syndicate</div>
        <p class="hub-desc">ATP, WTA, surfaces — chaque match défendu avec la méthode Baseline Syndicate v1.5.</p>

        <?php if ($statsTennis['active']): ?>
        <div class="hub-progress">
          <div class="hub-progress-label">
            <span><?= number_format($statsTennis['current'], 0) ?>€</span>
            <span><?= $statsTennis['progress'] ?>%</span>
            <span><?= number_format($statsTennis['target'], 0) ?>€</span>
          </div>
          <div class="hub-progress-bar">
            <div class="hub-progress-fill" style="width:<?= min(100, max(3, $statsTennis['progress'])) ?>%;"></div>
          </div>
        </div>
        <div class="hub-stats">
          <div class="hub-stat">
            <div class="hub-stat-lbl">Wins</div>
            <div class="hub-stat-val"><?= $statsTennis['wins'] ?></div>
          </div>
          <div class="hub-stat">
            <div class="hub-stat-lbl">Losses</div>
            <div class="hub-stat-val"><?= $statsTennis['losses'] ?></div>
          </div>
          <div class="hub-stat">
            <div class="hub-stat-lbl">Objectif</div>
            <div class="hub-stat-val"><?= number_format($statsTennis['target'], 0) ?>€</div>
          </div>
        </div>
        <?php else: ?>
        <div class="hub-inactive" style="margin-top:auto;margin-bottom:1rem;">Aucune campagne active pour le moment</div>
        <?php endif; ?>

        <div class="hub-cta">
          Suivre la montante
          <span class="arrow"></span>
        </div>
      </div>
    </a>

  </div>

</div>

</main></div>
<?php require_once __DIR__ . '/includes/footer-legal.php'; ?>
</body>
</html>
