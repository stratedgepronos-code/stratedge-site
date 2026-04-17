<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'index';
$db = getDB();

// Stats visiteurs (table visites en BDD — ne se remet plus à zéro au déploiement)
$visiteursAujourdhui = $visiteursSemaine = $visiteursMois = $visiteursAll = 0;
try {
    $todayStart = strtotime('today');
    $weekStart = strtotime('-7 days');
    $monthStart = strtotime('-30 days');
    $visiteursAll = (int)$db->query("SELECT COUNT(*) FROM visites")->fetchColumn();
    $visiteursMois = (int)$db->query("SELECT COUNT(*) FROM visites WHERE t >= $monthStart")->fetchColumn();
    $visiteursSemaine = (int)$db->query("SELECT COUNT(*) FROM visites WHERE t >= $weekStart")->fetchColumn();
    $visiteursAujourdhui = (int)$db->query("SELECT COUNT(*) FROM visites WHERE t >= $todayStart")->fetchColumn();
} catch (Throwable $e) {
    // Table visites peut ne pas exister
}

$nbMembres    = $db->query("SELECT COUNT(*) FROM membres WHERE email != 'stratedgepronos@gmail.com'")->fetchColumn();
$nbAboActifs  = $db->query("SELECT COUNT(*) FROM abonnements WHERE date_fin > NOW()")->fetchColumn();
// Nettoyage auto: désactiver les abonnements expirés (actif=1 mais date_fin passée)
try { $db->exec("UPDATE abonnements SET actif=0 WHERE actif=1 AND date_fin <= NOW()"); } catch(Throwable $e) {}
$nbBets       = $db->query("SELECT COUNT(*) FROM bets WHERE actif=1")->fetchColumn();
$nbTickets    = $db->query("SELECT COUNT(*) FROM tickets WHERE statut != 'resolu'")->fetchColumn();
$nbMessages   = $db->query("SELECT COUNT(*) FROM messages WHERE expediteur='membre' AND lu=0")->fetchColumn();

// === NOUVEAU MODELE 2026 ===
// MULTI = packs credits (table credits_paris)
// TENNIS = abo Semaine 15€ (table abonnements type='tennis')
// FUN = abo Semaine 10€ (table abonnements type='fun')
// VIP MAX = abo 30 jours (table abonnements type='vip_max')

// Revenus MULTI (packs credits)
try {
    $revenuMulti = (float)$db->query("SELECT COALESCE(SUM(prix_paye),0) FROM credits_paris")->fetchColumn();
    $nbAchatsMulti = (int)$db->query("SELECT COUNT(*) FROM credits_paris")->fetchColumn();
} catch (Throwable $e) { $revenuMulti = 0; $nbAchatsMulti = 0; }

// Revenus TENNIS (abo semaine 15€ ponctuel)
$revenuTennis = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='tennis'")->fetchColumn();
$nbAboTennis  = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='tennis'")->fetchColumn();

// Revenus FUN (abo semaine 10€ ponctuel)
$revenuFun = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='fun'")->fetchColumn();
$nbAboFun  = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='fun'")->fetchColumn();

// Revenus VIP MAX (abo 30 jours)
$revenuVip = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='vip_max'")->fetchColumn();
$nbAboVip  = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='vip_max'")->fetchColumn();
$nbVipActifs = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='vip_max' AND actif=1 AND date_fin>NOW()")->fetchColumn();

// Fondateurs VIP Max (places limitées à 10)
$fondateurPlaces = 0;
try { $fondateurPlaces = (int)$db->query("SELECT COUNT(*) FROM vip_max_fondateurs")->fetchColumn(); } catch(Throwable $e) {}
$fondateurRestant = max(0, 10 - $fondateurPlaces);

$revenuTotal = $revenuMulti + $revenuTennis + $revenuFun + $revenuVip;

$derniersMembres = $db->query("SELECT * FROM membres WHERE email != 'stratedgepronos@gmail.com' ORDER BY date_inscription DESC LIMIT 5")->fetchAll();
$derniersTickets = $db->query("SELECT t.*, m.nom FROM tickets t JOIN membres m ON t.membre_id=m.id WHERE t.statut!='resolu' ORDER BY t.date_creation DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --bg-dark:#050810; --bg-card:#0d1220; --neon-green:#ff2d78; --neon-green-dim:#d6245f; --neon-blue:#00d4ff; --neon-purple:#a855f7; --text-primary:#f0f4f8; --text-secondary:#b0bec9; --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.12); }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { overflow-x:hidden; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; display:flex; overflow-x:hidden; }
    .main { padding:2rem; }
    .dash-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; }
    .stats-bar-visiteurs { display:flex; flex-wrap:wrap; gap:1rem; align-items:center; padding:0.75rem 1.25rem; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:14px; max-width:100%; }
    .vis-label { font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); }
    .vis-sub { color:var(--text-muted); font-size:0.9rem; }
    .page-header { margin-bottom:2rem; }
    .page-header h1 { font-family:'Orbitron',sans-serif; font-size:1.6rem; font-weight:700; }
    .page-header p { color:var(--text-muted); margin-top:0.3rem; }
    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1rem; }
    .stat-card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:14px; padding:1.5rem; position:relative; overflow:hidden; transition:all 0.3s; }
    .stat-card:hover { border-color:rgba(255,45,120,0.25); }
    .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0.7; }
    .stat-card:nth-child(1)::before { background:var(--neon-green); }
    .stat-card:nth-child(2)::before { background:var(--neon-blue); }
    .stat-card:nth-child(3)::before { background:var(--neon-purple); }
    .stat-card:nth-child(4)::before { background:#ffc107; }
    .stat-card:nth-child(5)::before { background:#00c864; }
    .stat-card:nth-child(6)::before { background:var(--neon-green-dim); }
    .stat-label { font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.75rem; }
    .stat-value { font-family:'Orbitron',sans-serif; font-size:2rem; font-weight:900; }
    .stat-sub { font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; }
    .revenus-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:2rem; }
    .revenu-card { border-radius:14px; padding:1.5rem; position:relative; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; transition:all 0.3s; }
    .revenu-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:14px 14px 0 0; }
    .revenu-card.tennis { background:var(--bg-card); border:1px solid rgba(0,212,106,0.25); }
    .revenu-card.tennis::before { background:linear-gradient(90deg,#00d46a,#00a852); }
    .revenu-card.tennis:hover { border-color:rgba(0,212,106,0.5); box-shadow:0 0 20px rgba(0,212,106,0.08); }
    .revenu-card.multi { background:var(--bg-card); border:1px solid rgba(0,212,255,0.25); }
    .revenu-card.multi::before { background:linear-gradient(90deg,#00d4ff,#0099cc); }
    .revenu-card.multi:hover { border-color:rgba(0,212,255,0.5); box-shadow:0 0 20px rgba(0,212,255,0.08); }
    .revenu-left { display:flex; align-items:center; gap:1.2rem; }
    .revenu-emoji { font-size:2rem; line-height:1; }
    .revenu-label { font-family:'Space Mono',monospace; font-size:0.58rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.4rem; }
    .revenu-value { font-family:'Orbitron',sans-serif; font-size:1.9rem; font-weight:900; line-height:1; }
    .revenu-sub { font-size:0.78rem; color:var(--text-muted); margin-top:0.3rem; }
    .revenu-pct-label { font-family:'Space Mono',monospace; font-size:0.55rem; letter-spacing:2px; text-transform:uppercase; opacity:0.6; margin-bottom:0.25rem; }
    .revenu-pct-value { font-family:'Orbitron',sans-serif; font-size:1.5rem; font-weight:900; }
    .revenu-card.vip { background:linear-gradient(135deg,#111208,#0d1220); border:1px solid rgba(245,200,66,0.3); }
    .revenu-card.vip::before { background:linear-gradient(90deg,#c8960c,#f5c842,#fffbe6,#e8a020); background-size:200% 100%; animation:vipShim 2.5s linear infinite; }
    @keyframes vipShim { from{background-position:-100% 0} to{background-position:100% 0} }
    .revenu-card.vip:hover { border-color:rgba(245,200,66,0.6); box-shadow:0 0 20px rgba(245,200,66,0.08); }
    .vip-split { display:flex; gap:1.2rem; margin-top:1rem; flex-wrap:wrap; }
    .vip-split-item { flex:1; min-width:120px; background:rgba(245,200,66,0.05); border:1px solid rgba(245,200,66,0.15); border-radius:10px; padding:0.9rem 1rem; }
    .vip-split-pseudo { font-family:'Orbitron',sans-serif; font-size:0.62rem; letter-spacing:2px; text-transform:uppercase; color:rgba(245,200,66,0.55); margin-bottom:0.3rem; }
    .vip-split-pct { font-family:'Orbitron',sans-serif; font-size:0.85rem; font-weight:700; color:rgba(245,200,66,0.5); margin-bottom:0.2rem; }
    .vip-split-montant { font-family:'Orbitron',sans-serif; font-size:1.5rem; font-weight:900; background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:14px; padding:1.5rem; margin-bottom:1.5rem; }
    .card h3 { font-family:'Orbitron',sans-serif; font-size:0.9rem; font-weight:700; margin-bottom:1.5rem; display:flex; align-items:center; justify-content:space-between; }
    .card h3 a { color:var(--neon-green); font-size:0.75rem; text-decoration:none; font-family:'Rajdhani',sans-serif; font-weight:600; }
    table { width:100%; border-collapse:collapse; }
    th { text-align:left; font-family:'Space Mono',monospace; font-size:0.62rem; letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); padding:0.75rem; border-bottom:1px solid rgba(255,255,255,0.05); }
    td { padding:0.85rem 0.75rem; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--text-secondary); font-size:0.9rem; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .badge { padding:0.2rem 0.7rem; border-radius:6px; font-size:0.75rem; font-weight:700; }
    .two-cols { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
    @media (max-width:1100px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .revenus-row { grid-template-columns:1fr; } }
    @media (max-width:768px) {
      .main { margin-left:0 !important; padding:62px 0.7rem calc(1.5rem + 74px + env(safe-area-inset-bottom,0px)) 0.7rem !important; width:100% !important; max-width:100vw !important; min-width:0 !important; }
      .two-cols { grid-template-columns:1fr !important; gap:0.8rem; }
      .stats-grid { grid-template-columns:1fr 1fr; gap:0.6rem; }
      .stat-card { padding:1rem 0.8rem; border-radius:10px; }
      .stat-value { font-size:1.3rem !important; }
      .stat-label { font-size:0.58rem; letter-spacing:1px; }
      .stat-sub { font-size:0.72rem; }
      .dash-header { flex-direction:column; align-items:stretch; gap:0.6rem; margin-bottom:1rem; }
      .page-header h1 { font-size:1.15rem !important; }
      .page-header p { font-size:0.82rem; }
      .stats-bar-visiteurs { width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; white-space:nowrap; padding:0.6rem 0.8rem; border-radius:10px; gap:0.6rem; flex-wrap:nowrap; scrollbar-width:thin; }
      .stats-bar-visiteurs span { flex-shrink:0; font-size:0.8rem; }
      .revenus-row { grid-template-columns:1fr !important; gap:0.8rem; }
      .revenu-card { padding:1rem 0.8rem; border-radius:10px; flex-direction:column !important; align-items:flex-start !important; }
      .revenu-left { min-width:0; overflow:hidden; }
      .revenu-value { font-size:1.4rem !important; word-break:break-all; }
      .revenu-emoji { font-size:1.5rem; }
      .revenu-pct-value { font-size:1.1rem; }
      .revenu-label { font-size:0.52rem; }
      .revenu-sub { font-size:0.72rem; }
      .vip-split { gap:0.6rem; }
      .vip-split-item { min-width:0 !important; flex:1; padding:0.7rem 0.6rem; }
      .vip-split-montant { font-size:1.1rem; }
      .vip-split-pct { font-size:0.72rem; }
      .vip-split-pseudo { font-size:0.55rem; }
      .card { padding:1rem 0.8rem; border-radius:10px; margin-bottom:1rem; }
      .card h3 { font-size:0.82rem; margin-bottom:1rem; }
      .card h3 a { font-size:0.7rem; }
      table { display:block; overflow-x:auto; -webkit-overflow-scrolling:touch; white-space:nowrap; width:100% !important; max-width:100% !important; }
      th { font-size:0.55rem; padding:0.5rem 0.4rem; }
      td { font-size:0.8rem; padding:0.6rem 0.4rem; }
    }
    @media (max-width:380px) {
      .main { padding:58px 0.5rem calc(1rem + 68px + env(safe-area-inset-bottom,0px)) 0.5rem !important; }
      .stats-grid { grid-template-columns:1fr; }
      .stat-value { font-size:1.15rem !important; }
      .revenu-value { font-size:1.2rem !important; }
      .vip-split { flex-direction:column; }
      .vip-split-item { min-width:100% !important; }
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <div class="page-header dash-header">
    <div>
      <h1>📊 Tableau de bord</h1>
      <p>Bienvenue — <?= date('d/m/Y à H:i') ?></p>
    </div>
    <div class="stats-bar-visiteurs">
      <span class="vis-label">Visiteurs</span>
      <span><strong><?= number_format($visiteursAujourdhui, 0, ',', ' ') ?></strong> <span class="vis-sub">aujourd'hui</span></span>
      <span><strong><?= number_format($visiteursSemaine, 0, ',', ' ') ?></strong> <span class="vis-sub">7 jours</span></span>
      <span><strong><?= number_format($visiteursMois, 0, ',', ' ') ?></strong> <span class="vis-sub">30 jours</span></span>
      <span><strong><?= number_format($visiteursAll, 0, ',', ' ') ?></strong> <span class="vis-sub">all time</span></span>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">👥 Membres</div>
      <div class="stat-value" style="color:var(--neon-green)"><?= $nbMembres ?></div>
      <div class="stat-sub">comptes inscrits</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">⚡ Abonnements actifs</div>
      <div class="stat-value" style="color:var(--neon-blue)"><?= $nbAboActifs ?></div>
      <div class="stat-sub">en ce moment</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">📊 Bets en ligne</div>
      <div class="stat-value" style="color:var(--neon-purple)"><?= $nbBets ?></div>
      <div class="stat-sub">actifs actuellement</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">🎫 Tickets ouverts</div>
      <div class="stat-value" style="color:#ffc107"><?= $nbTickets ?></div>
      <div class="stat-sub">en attente</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">💬 Messages non lus</div>
      <div class="stat-value" style="color:#00c864"><?= $nbMessages ?></div>
      <div class="stat-sub">de membres</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">💰 Revenus total</div>
      <div class="stat-value" style="color:var(--neon-green-dim)"><?= number_format($revenuTotal, 2) ?>€</div>
      <div class="stat-sub">tous abonnements</div>
    </div>
  </div>

  <div class="revenus-row" style="grid-template-columns:1fr 1fr;">
    <div class="revenu-card tennis">
      <div class="revenu-left">
        <div class="revenu-emoji">🎾</div>
        <div>
          <div class="revenu-label">Revenus Tennis Semaine</div>
          <div class="revenu-value" style="color:#00d46a"><?= number_format($revenuTennis, 2) ?>€</div>
          <div class="revenu-sub"><?= $nbAboTennis ?> abonnement<?= $nbAboTennis>1?'s':'' ?> · 15€/sem</div>
        </div>
      </div>
      <div>
        <div class="revenu-pct-label" style="color:#00d46a">Part du total</div>
        <div class="revenu-pct-value" style="color:#00d46a"><?= $revenuTotal>0 ? number_format(($revenuTennis/$revenuTotal)*100,1) : '0.0' ?>%</div>
      </div>
    </div>
    <div class="revenu-card multi">
      <div class="revenu-left">
        <div class="revenu-emoji">⚽🏀🏒</div>
        <div>
          <div class="revenu-label">Revenus Multi Packs Crédits</div>
          <div class="revenu-value" style="color:#00d4ff"><?= number_format($revenuMulti, 2) ?>€</div>
          <div class="revenu-sub"><?= $nbAchatsMulti ?> pack<?= $nbAchatsMulti>1?'s':'' ?> vendu<?= $nbAchatsMulti>1?'s':'' ?> · 4,50€ à 30€</div>
        </div>
      </div>
      <div>
        <div class="revenu-pct-label" style="color:#00d4ff">Part du total</div>
        <div class="revenu-pct-value" style="color:#00d4ff"><?= $revenuTotal>0 ? number_format(($revenuMulti/$revenuTotal)*100,1) : '0.0' ?>%</div>
      </div>
    </div>

    <!-- FUN BETS -->
    <div class="revenu-card" style="background:var(--bg-card);border:1px solid rgba(168,85,247,0.25);flex-direction:column;align-items:stretch;gap:0.5rem;">
      <div style="position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0;background:linear-gradient(90deg,#a855f7,#7c3aed);"></div>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div class="revenu-left">
          <div class="revenu-emoji">🎯</div>
          <div>
            <div class="revenu-label" style="color:rgba(168,85,247,0.7);">Revenus Fun Semaine</div>
            <div class="revenu-value" style="color:#a855f7;"><?= number_format($revenuFun, 2) ?>€</div>
            <div class="revenu-sub"><?= $nbAboFun ?> abonnement<?= $nbAboFun>1?'s':'' ?> · 10€/sem</div>
          </div>
        </div>
        <div>
          <div class="revenu-pct-label" style="color:rgba(168,85,247,0.7);">Part du total</div>
          <div class="revenu-pct-value" style="color:#a855f7;"><?= $revenuTotal>0 ? number_format(($revenuFun/$revenuTotal)*100,1) : '0.0' ?>%</div>
        </div>
      </div>
    </div>

    <!-- VIP MAX -->
    <div class="revenu-card vip" style="flex-direction:column;align-items:stretch;gap:0.5rem;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div class="revenu-left">
          <div class="revenu-emoji">👑</div>
          <div>
            <div class="revenu-label" style="color:rgba(245,200,66,0.7);">Revenus VIP Max</div>
            <div class="revenu-value" style="color:#f5c842;"><?= number_format($revenuVip, 2) ?>€</div>
            <div class="revenu-sub"><?= $nbAboVip ?> abonnement<?= $nbAboVip>1?'s':'' ?> · <?= $nbVipActifs ?> actif<?= $nbVipActifs>1?'s':'' ?></div>
          </div>
        </div>
        <div>
          <div class="revenu-pct-label" style="color:rgba(245,200,66,0.7);">Part du total</div>
          <div class="revenu-pct-value" style="color:#f5c842;"><?= $revenuTotal>0 ? number_format(($revenuVip/$revenuTotal)*100,1) : '0.0' ?>%</div>
        </div>
      </div>
      <!-- Splits revenus VIP Max -->
      <?php
        $splitShaym   = $revenuVip * 0.50;
        $splitYaffa   = $revenuVip * 0.20;
        $splitShuriik = $revenuVip * 0.30;
      ?>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;margin-top:0.5rem;">
        <div style="background:rgba(245,200,66,0.06);border:1px solid rgba(245,200,66,0.15);border-radius:8px;padding:0.5rem 0.6rem;text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:0.6rem;color:rgba(245,200,66,0.5);letter-spacing:1px;text-transform:uppercase;">Shaym · 50%</div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#f5c842;margin-top:0.15rem;"><?= number_format($splitShaym, 2) ?>€</div>
        </div>
        <div style="background:rgba(168,85,247,0.06);border:1px solid rgba(168,85,247,0.15);border-radius:8px;padding:0.5rem 0.6rem;text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:0.6rem;color:rgba(168,85,247,0.5);letter-spacing:1px;text-transform:uppercase;">Yaffa · 20%</div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#a855f7;margin-top:0.15rem;"><?= number_format($splitYaffa, 2) ?>€</div>
        </div>
        <div style="background:rgba(57,255,20,0.06);border:1px solid rgba(57,255,20,0.15);border-radius:8px;padding:0.5rem 0.6rem;text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:0.6rem;color:rgba(57,255,20,0.5);letter-spacing:1px;text-transform:uppercase;">Shuriik · 30%</div>
          <div style="font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#39ff14;margin-top:0.15rem;"><?= number_format($splitShuriik, 2) ?>€</div>
        </div>
      </div>
      <div style="display:flex;gap:1rem;margin-top:0.3rem;font-size:0.75rem;color:rgba(245,200,66,0.5);">
        <span>🏆 Fondateurs : <?= $fondateurPlaces ?>/10</span>
        <span>📭 Places restantes : <?= $fondateurRestant ?></span>
      </div>
    </div>

  </div>

  <div class="two-cols">
    <div class="card">
      <h3>Derniers membres <a href="membres.php">Voir tous →</a></h3>
      <table>
        <thead><tr><th>Nom</th><th>Email</th><th>Inscrit le</th></tr></thead>
        <tbody>
          <?php foreach ($derniersMembres as $m): ?>
          <tr>
            <td><strong><?= clean($m['nom']) ?></strong></td>
            <td style="font-size:0.8rem;"><?= clean($m['email']) ?></td>
            <td style="font-size:0.8rem;"><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($derniersMembres)): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">Aucun membre</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <h3>Tickets en attente <a href="tickets.php">Voir tous →</a></h3>
      <table>
        <thead><tr><th>Membre</th><th>Sujet</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($derniersTickets as $t): ?>
          <tr>
            <td><?= clean($t['nom']) ?></td>
            <td style="font-size:0.85rem;"><?= clean(substr($t['sujet'],0,30)) ?>…</td>
            <td><span class="badge" style="background:rgba(255,200,0,0.1);color:#ffc107;border:1px solid rgba(255,200,0,0.2);"><?= $t['statut'] ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($derniersTickets)): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">✅ Aucun ticket en attente</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
