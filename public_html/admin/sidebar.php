<?php
// ── Sidebar partagée — inclure en haut de chaque page admin ──
// Usage : require_once __DIR__ . '/sidebar.php';
// Avant d'inclure, définir : $pageActive = 'index' | 'poster-bet' | 'membres' | 'messages' | 'tickets' | 'historique'

// Toutes les requêtes en try/catch pour éviter crash si colonne/table manquante
$nbTicketsOpen = 0; $nbMsgNonLus = 0; $nbBetsHistorique = 0; $nbChatNonLus = 0; $nbInboxNonLus = 0;
try { $nbTicketsOpen    = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE statut != 'resolu'")->fetchColumn(); } catch(Exception $e) {}
try { $nbMsgNonLus      = (int)$db->query("SELECT COUNT(*) FROM messages WHERE expediteur='membre' AND lu=0")->fetchColumn(); } catch(Exception $e) {}
try { $nbBetsHistorique = (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat IS NOT NULL AND resultat NOT IN ('en_cours','pending')")->fetchColumn(); } catch(Exception $e) {}
try { $nbChatNonLus     = (int)$db->query("SELECT COUNT(*) FROM chat_messages WHERE expediteur='membre' AND lu=0")->fetchColumn(); } catch(Exception $e) {}
try { if (function_exists('isSuperAdmin') && isSuperAdmin()) $nbInboxNonLus = (int)$db->query("SELECT COUNT(*) FROM admin_inbox WHERE lu=0")->fetchColumn(); } catch(Exception $e) {}
?>
<!-- ════ STYLES SIDEBAR + RESPONSIVE ════ -->
<style>
  /* TOPBAR MOBILE */
  .mobile-topbar {
    display:none; position:fixed; top:0; left:0; right:0; z-index:200;
    height:58px; background:var(--bg-card); border-bottom:1px solid var(--border-subtle);
    align-items:center; justify-content:space-between; padding:0 1.2rem;
  }
  .mobile-topbar .mob-logo { font-family:'Orbitron',sans-serif; font-size:1rem; color:#ff2d78; font-weight:900; }
  .hamburger {
    width:40px; height:40px; display:flex; flex-direction:column; align-items:center;
    justify-content:center; gap:6px; cursor:pointer; background:rgba(255,45,120,0.08);
    border:1px solid rgba(255,45,120,0.2); border-radius:8px; flex-shrink:0;
  }
  .hamburger span { width:20px; height:2px; background:var(--neon-green); border-radius:2px; transition:all 0.3s; display:block; }
  .hamburger.open span:nth-child(1) { transform:translateY(8px) rotate(45deg); }
  .hamburger.open span:nth-child(2) { opacity:0; }
  .hamburger.open span:nth-child(3) { transform:translateY(-8px) rotate(-45deg); }

  /* OVERLAY */
  .sidebar-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6);
    z-index:149; backdrop-filter:blur(2px); pointer-events:none;
  }
  .sidebar-overlay.open { display:block; pointer-events:all; }

  /* SIDEBAR */
  .sidebar {
    width:240px; background:var(--bg-card); border-right:1px solid var(--border-subtle);
    height:100vh; position:fixed; top:0; left:0; display:flex; flex-direction:column;
    z-index:150; overflow-y:auto; transition:transform 0.3s ease;
  }
  .sidebar-logo { padding:1.5rem; border-bottom:1px solid var(--border-subtle); flex-shrink:0; }
  .sidebar-logo img { height:35px; }
  .sidebar-label {
    font-family:'Space Mono',monospace; font-size:0.6rem; letter-spacing:3px;
    text-transform:uppercase; color:var(--text-muted); padding:1.5rem 1.5rem 0.5rem;
    flex-shrink:0;
  }
  .sidebar nav a {
    display:flex; align-items:center; gap:0.8rem; padding:0.85rem 1.5rem;
    color:var(--text-secondary); text-decoration:none; font-size:0.92rem; font-weight:500;
    transition:all 0.2s; border-left:3px solid transparent;
  }
  .sidebar nav a:hover, .sidebar nav a.active {
    color:var(--text-primary); background:rgba(255,45,120,0.06); border-left-color:var(--neon-green);
  }
  .nav-group { margin-bottom:0.25rem; }
  .nav-group-toggle {
    display:flex; align-items:center; gap:0.8rem; width:100%;
    padding:0.85rem 1.5rem; border:none; background:transparent;
    color:var(--text-secondary); font-family:inherit; font-size:0.92rem; font-weight:500;
    text-align:left; cursor:pointer; transition:all 0.2s;
    border-left:3px solid transparent;
  }
  .nav-group-toggle:hover { color:var(--text-primary); background:rgba(255,45,120,0.04); }
  .nav-group-toggle .chevron { margin-left:auto; opacity:0.6; transition:transform 0.25s ease; }
  .nav-group.open .nav-group-toggle .chevron { transform:rotate(90deg); }
  .nav-group-inner { max-height:0; overflow:hidden; transition:max-height 0.3s ease; }
  .nav-group.open .nav-group-inner { max-height:400px; }
  .nav-group-inner a { padding-left:2.2rem; font-size:0.88rem; }
  .badge-count {
    background:var(--neon-green); color:white; font-size:0.62rem; font-weight:700;
    padding:0.15rem 0.45rem; border-radius:10px; margin-left:auto;
  }
  .sidebar-footer {
    margin-top:auto; padding:1.5rem; border-top:1px solid var(--border-subtle);
    display:flex; flex-direction:column; gap:0.75rem; flex-shrink:0;
  }
  .btn-site {
    display:flex; align-items:center; gap:0.6rem;
    background:linear-gradient(135deg,rgba(0,212,255,0.12),rgba(0,180,220,0.06));
    border:1px solid rgba(0,212,255,0.3);
    color:#00d4ff; text-decoration:none;
    padding:0.7rem 1rem; border-radius:10px; font-size:0.88rem; font-weight:600;
    transition:all 0.2s;
  }
  .btn-site:hover { background:rgba(0,212,255,0.2); border-color:rgba(0,212,255,0.6); transform:translateY(-1px); }
  .btn-logout {
    display:flex; align-items:center; justify-content:center; gap:0.5rem;
    background:rgba(255,45,120,0.08); border:1px solid rgba(255,45,120,0.25);
    color:var(--neon-green); padding:0.65rem 1rem; border-radius:8px;
    font-family:'Rajdhani',sans-serif; font-weight:700; font-size:0.9rem;
    text-decoration:none; transition:all 0.2s;
  }
  .btn-logout:hover { background:rgba(255,45,120,0.18); color:#fff; }

  /* MAIN */
  .main { margin-left:240px; flex:1; min-height:100vh; }

  /* ════ RESPONSIVE MOBILE ════ */
  @media (max-width:768px) {
    .mobile-topbar { display:flex; }
    .sidebar { transform:translateX(-100%); top:0; }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; padding-top:58px; }

    /* Tables scrollables */
    .table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table { min-width:500px; }

    /* Grilles */
    .two-cols, .stats-grid { grid-template-columns:1fr !important; }
    .chat-layout { grid-template-columns:1fr !important; height:auto !important; }

    /* Cards + padding */
    .card, .section-block { padding:1rem !important; }
    .page-header h1 { font-size:1.2rem !important; }
    .page-header p { font-size:0.82rem; }

    /* Poster bet */
    .previews-grid { grid-template-columns:repeat(2,1fr) !important; }
    .expire-box { flex-wrap:wrap; }

    /* Contacts panel historique */
    .contacts-panel { max-height:220px; }
  }
  @media (max-width:480px) {
    .main { padding:58px 0.75rem 1.5rem; }
    .previews-grid { grid-template-columns:1fr !important; }
    .stat-value { font-size:1.6rem !important; }
  }
</style>

<!-- TOPBAR MOBILE -->
<div class="mobile-topbar">
  <span class="mob-logo">STRATEDGE</span>
  <div class="hamburger" id="hamburger" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
  </div>
</div>

<!-- OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div style="font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900;color:#fff;letter-spacing:1px;">
      <span style="color:#ff2d78;">STRAT</span>EDGE
    </div>
    <div style="font-family:'Space Mono',monospace;font-size:0.6rem;color:var(--text-muted);letter-spacing:2px;margin-top:0.4rem;text-transform:uppercase;">Admin Panel</div>
  </div>

  <div class="sidebar-label">Navigation</div>
  <nav>
    <a href="index.php" <?= ($pageActive==='index') ?'class="active"':'' ?>>
      <span>📊</span> Tableau de bord
    </a>

    <?php $bettingOpen = in_array($pageActive, ['poster-bet','creer-card','edit-bet-image','historique']); ?>
    <div class="nav-group <?= $bettingOpen ? 'open' : '' ?>" data-group="betting">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <span>📌</span> Betting
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="poster-bet.php" <?= ($pageActive==='poster-bet') ?'class="active"':'' ?>><span>📸</span> Poster un bet</a>
        <a href="creer-card.php" <?= ($pageActive==='creer-card') ?'class="active"':'' ?>><span>🎨</span> Créer une Card</a>
        <a href="edit-bet-image.php" <?= ($pageActive==='edit-bet-image') ?'class="active"':'' ?>><span>🖼️</span> Modifier image bet</a>
        <a href="historique.php" <?= ($pageActive==='historique') ?'class="active"':'' ?>>
          <span>📂</span> Historique
          <?php if ($nbBetsHistorique > 0): ?><span class="badge-count"><?= $nbBetsHistorique ?></span><?php endif; ?>
        </a>
      </div>
    </div>

    <?php $usersOpen = in_array($pageActive, ['membres','admins']); ?>
    <div class="nav-group <?= $usersOpen ? 'open' : '' ?>" data-group="users">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <span>👥</span> Gestion utilisateurs
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="membres.php" <?= ($pageActive==='membres') ?'class="active"':'' ?>><span>👥</span> Membres</a>
        <a href="gestion-admins.php" <?= ($pageActive==='admins') ?'class="active"':'' ?>><span>👑</span> Admins</a>
      </div>
    </div>

    <a href="idees.php" <?= ($pageActive==='idees') ?'class="active"':'' ?>>
      <span>💡</span> Idées & Bugs
    </a>

    <?php if (isSuperAdmin()): ?>
    <a href="vault.php" <?= ($pageActive==='vault') ?'class="active"':'' ?> style="color:rgba(245,200,66,0.9);">
      <span>🔐</span> Coffre-Fort
    </a>
    <?php endif; ?>

    <?php $msgOpen = in_array($pageActive, ['messagerie-interne','messages']); ?>
    <div class="nav-group <?= $msgOpen ? 'open' : '' ?>" data-group="messagerie">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <span>💬</span> Messagerie
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
        <a href="messagerie-interne.php" <?= ($pageActive==='messagerie-interne') ?'class="active"':'' ?>>
          <span>📥</span> Messagerie interne
          <?php if (!empty($nbInboxNonLus)): ?><span class="badge-count"><?= $nbInboxNonLus ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="messages.php" <?= ($pageActive==='messages') ?'class="active"':'' ?>>
          <span>💬</span> Messages
          <?php if ($nbMsgNonLus > 0): ?><span class="badge-count"><?= $nbMsgNonLus ?></span><?php endif; ?>
        </a>
      </div>
    </div>

    <a href="tickets.php" <?= ($pageActive==='tickets') ?'class="active"':'' ?>>
      <span>🎫</span> Tickets SAV
      <?php if ($nbTicketsOpen > 0): ?><span class="badge-count"><?= $nbTicketsOpen ?></span><?php endif; ?>
    </a>

    <?php $pushOpen = in_array($pageActive, ['broadcast','twitter-post']); ?>
    <div class="nav-group <?= $pushOpen ? 'open' : '' ?>" data-group="push">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <span>📣</span> Push & réseaux sociaux
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="broadcast.php" <?= ($pageActive==='broadcast') ?'class="active"':'' ?>><span>📣</span> Broadcast</a>
        <a href="twitter-post.php" <?= ($pageActive==='twitter-post') ?'class="active"':'' ?>>
          <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.259 5.629L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg></span> Poster sur X
        </a>
      </div>
    </div>
  </nav>

  <div class="sidebar-footer">
    <a href="/" class="btn-site">
      <span style="font-size:1rem;">🌐</span>
      <span>Voir le site</span>
      <span style="margin-left:auto;font-size:0.7rem;opacity:0.6;">↗</span>
    </a>
    <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
  </div>
</div>

<script>
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const h = document.getElementById('hamburger');
  const o = document.getElementById('sidebarOverlay');
  s.classList.toggle('open');
  h.classList.toggle('open');
  o.classList.toggle('open');
}
function toggleNavGroup(btn) {
  var g = btn.closest('.nav-group');
  if (g) g.classList.toggle('open');
}
</script>
