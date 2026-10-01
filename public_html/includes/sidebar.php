<?php
// ── Sidebar partagée pour toutes les pages membres ──
$_cp = $currentPage ?? '';

// ── Stats pour le drawer mobile (membres connectés uniquement) ──
$_mmStats = null;
$_mmBadge = null;
$_mmAboLabel = null;
$_mmNewBets = 0;
$_mmGiveawayActif = false;

if (!empty($membre)) {
    try {
        $_db = getDB();

        // ── Stats par tipster — MEME LOGIQUE que historique.php (all-time, par posted_by_role) ──
        $_tipsterStats = ['multi'=>['w'=>0,'l'=>0,'roi'=>0,'winrate'=>0], 'tennis'=>['w'=>0,'l'=>0,'roi'=>0,'winrate'=>0], 'fun'=>['w'=>0,'l'=>0,'roi'=>0,'winrate'=>0]];
        $_gains = ['multi'=>0.0,'tennis'=>0.0,'fun'=>0.0];

        $_allBets = $_db->query("SELECT posted_by_role, categorie, resultat, cote FROM bets WHERE resultat IN ('gagne','perdu')")->fetchAll();

        foreach ($_allBets as $_b) {
            // Déterminer le tipster (même logique que historique.php::_tipsterOf)
            $_role = $_b['posted_by_role'] ?? '';
            if ($_role === 'admin_tennis') $_tip = 'tennis';
            elseif ($_role === 'admin_fun') $_tip = 'fun';
            elseif ($_role === 'superadmin') $_tip = 'multi';
            elseif (($_b['categorie'] ?? '') === 'tennis') $_tip = 'tennis';
            else $_tip = 'multi';

            $_c = (float) str_replace(',', '.', $_b['cote'] ?? 0);
            if ($_b['resultat'] === 'gagne') {
                $_tipsterStats[$_tip]['w']++;
                if ($_c > 0) $_gains[$_tip] += ($_c - 1);
            } else {
                $_tipsterStats[$_tip]['l']++;
                $_gains[$_tip] -= 1;
            }
        }
        foreach ($_tipsterStats as $_k => $_s) {
            $_total = $_s['w'] + $_s['l'];
            $_tipsterStats[$_k]['winrate'] = $_total > 0 ? round($_s['w'] * 100 / $_total) : 0;
            $_tipsterStats[$_k]['roi']     = $_total > 0 ? round($_gains[$_k] * 100 / $_total) : 0;
        }

        // Bets live actifs
        $_liveStmt = $_db->query("SELECT COUNT(*) FROM bets WHERE actif=1 AND (resultat IS NULL OR resultat='' OR resultat NOT IN ('gagne','perdu','annule'))");
        $_liveCount = (int) $_liveStmt->fetchColumn();
        $_mmStats = ['live' => $_liveCount];

        // Nouveaux bets < 6h pour badge NEW
        $_newStmt = $_db->query("SELECT COUNT(*) FROM bets WHERE actif=1 AND date_post >= DATE_SUB(NOW(), INTERVAL 6 HOUR)");
        $_mmNewBets = (int) $_newStmt->fetchColumn();

        // Abonnement → badge + durée restante
        $_abo = getAbonnementActif($membre['id']);
        if ($_abo) {
            $_type = $_abo['type'] ?? '';
            if ($_type === 'vip_max') {
                $_mmBadge = 'vip';
                $_mmAboLabel = 'VIP MAX';
            } elseif (in_array($_type, ['tennis','weekly'])) {
                $_mmBadge = 'tennis';
                $_mmAboLabel = 'TENNIS';
            } elseif (in_array($_type, ['fun','weekend_fun'])) {
                $_mmBadge = 'fun';
                $_mmAboLabel = 'FUN';
            } elseif ($_type === 'weekend') {
                $_mmBadge = 'tennis';
                $_mmAboLabel = 'WEEK-END';
            } elseif (in_array($_type, ['daily','rasstoss'])) {
                $_mmBadge = 'tennis';
                $_mmAboLabel = 'DAILY';
            }
            if (!empty($_abo['date_fin']) && $_mmAboLabel) {
                $_fin = new DateTime($_abo['date_fin']);
                $_now = new DateTime('now');
                if ($_fin > $_now) {
                    $_diff = $_now->diff($_fin);
                    if ($_diff->days > 0) $_mmAboLabel .= ' · ' . $_diff->days . 'j';
                    elseif ($_diff->h > 0) $_mmAboLabel .= ' · ' . $_diff->h . 'h';
                }
            }
        }

        // Giveaway actif ?
        if (function_exists('getGiveawayConfig')) {
            $_gw = getGiveawayConfig();
            $_mmGiveawayActif = !empty($_gw);
        }
    } catch (Exception $_e) {
        // Silencieux
    }
}
?>
<nav class="top-nav">
  <a href="/" class="nav-logo">
    <img src="/assets/images/logo site.png" alt="StratEdge" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
    <span class="nav-logo-fb" style="display:none;"><em>STRAT</em>EDGE</span>
  </a>

  <div class="nav-center">
    <a href="/bets.php" class="nav-lnk <?= $_cp==='bets'?'active':'' ?>">🔥 Bets</a>
    <a href="/historique.php" class="nav-lnk <?= $_cp==='historique'?'active':'' ?>">📋 Historique</a>
    <a href="/giveaway.php" class="nav-lnk <?= $_cp==='giveaway'?'active':'' ?>">🎁 GiveAway</a>
    <a href="/prono-commu.php" class="nav-lnk <?= $_cp==='pronocommu'?'active':'' ?>">⚽ Prono commu</a>
    <a href="/montante.php" class="nav-lnk <?= $_cp==='montante'?'active':'' ?>">📈 Montante</a>
  </div>

  <div class="nav-acts">
    <a href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer" class="nav-x" aria-label="Suivre sur X">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    </a>
    <?php if (empty($membre)): ?>
      <a href="/souscrire.php" class="nav-btn">Souscrire</a>
    <?php endif; ?>
    <?php if (isAdmin()): ?><a href="/panel-x9k3m/index.php" class="nav-admin">⚙️ Panel</a><?php endif; ?>
    <?php if (!empty($membre)): ?>
      <a href="/dashboard.php" class="nav-user" title="Mon espace">
        <?php if (!empty($avatarUrl)): ?><img src="<?= $avatarUrl ?>?v=<?= time() ?>" alt=""><?php else: ?><span><?= strtoupper(substr($membre['nom'],0,1)) ?></span><?php endif; ?>
      </a>
    <?php else: ?>
      <a href="/login.php" class="nav-login">Connexion</a>
      <a href="/register.php" class="nav-btn nav-btn-pink">S'inscrire</a>
    <?php endif; ?>
  </div>
  <button class="hamburger" id="btnHam" onclick="toggleMenu()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- Overlay -->
<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<!-- Drawer mobile CYBERPUNK -->
<div class="mobile-menu" id="mobileMenu">
<div class="mm-inner">
  <!-- Header -->
  <div class="mm-head">
    <div class="mm-head-title">
      <div class="mm-head-bar"></div>
      <div class="mm-head-txt">NAVIGATION</div>
    </div>
    <button class="mm-close" onclick="closeMenu()" aria-label="Fermer">✕</button>
  </div>

  <?php if (!empty($membre)): ?>
  <!-- User card glassy -->
  <div class="mm-user">
    <div class="mm-user-row">
      <div class="mm-av">
        <?php if (!empty($avatarUrl)): ?><img src="<?= $avatarUrl ?>?v=<?= time() ?>" alt=""><?php else: ?><?= strtoupper(substr($membre['nom'],0,1)) ?><?php endif; ?>
      </div>
      <div class="mm-info">
        <div class="mm-name"><?= htmlspecialchars($membre['nom']) ?></div>
        <div class="mm-status"><?= $_mmAboLabel ? htmlspecialchars($_mmAboLabel) : 'Aucun abo actif' ?></div>
      </div>
      <?php if ($_mmBadge === 'vip'): ?>
      <div class="mm-badge-vip">👑 VIP</div>
      <?php elseif ($_mmBadge === 'tennis'): ?>
      <div class="mm-badge-tennis">🎾</div>
      <?php elseif ($_mmBadge === 'fun'): ?>
      <div class="mm-badge-fun">🎲</div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($_tipsterStats)): ?>
  <!-- Stats par tipster -->
  <div class="mm-tipsters">
    <div class="mm-tipster mm-t-multi">
      <div class="mm-t-head">
        <div class="mm-t-emoji">⚽</div>
        <div class="mm-t-name">MULTI</div>
      </div>
      <div class="mm-t-stats">
        <div class="mm-t-val"><?= $_tipsterStats['multi']['winrate'] ?>%</div>
        <div class="mm-t-roi"><?= $_tipsterStats['multi']['roi'] >= 0 ? '+' : '' ?><?= $_tipsterStats['multi']['roi'] ?>% ROI</div>
      </div>
    </div>
    <div class="mm-tipster mm-t-tennis">
      <div class="mm-t-head">
        <div class="mm-t-emoji">🎾</div>
        <div class="mm-t-name">TENNIS</div>
      </div>
      <div class="mm-t-stats">
        <div class="mm-t-val"><?= $_tipsterStats['tennis']['winrate'] ?>%</div>
        <div class="mm-t-roi"><?= $_tipsterStats['tennis']['roi'] >= 0 ? '+' : '' ?><?= $_tipsterStats['tennis']['roi'] ?>% ROI</div>
      </div>
    </div>
    <div class="mm-tipster mm-t-fun">
      <div class="mm-t-head">
        <div class="mm-t-emoji">🎲</div>
        <div class="mm-t-name">FUN</div>
      </div>
      <div class="mm-t-stats">
        <div class="mm-t-val"><?= $_tipsterStats['fun']['winrate'] ?>%</div>
        <div class="mm-t-roi"><?= $_tipsterStats['fun']['roi'] >= 0 ? '+' : '' ?><?= $_tipsterStats['fun']['roi'] ?>% ROI</div>
      </div>
    </div>
  </div>

  <!-- Bets en cours -->
  <a href="/bets.php" class="mm-live">
    <div class="mm-live-left">
      <span class="mm-live-pulse"></span>
      <div class="mm-live-txt">
        <div class="mm-live-lbl">BETS EN COURS</div>
        <div class="mm-live-sub"><?= $_mmStats['live'] ?> bet<?= $_mmStats['live'] > 1 ? 's' : '' ?> <?= $_mmStats['live'] > 1 ? 'actifs' : 'actif' ?></div>
      </div>
    </div>
    <div class="mm-live-count"><?= $_mmStats['live'] ?></div>
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Section PRINCIPAL -->
  <div class="mm-sect">PRINCIPAL</div>
  <div class="mm-lnks">
    <a href="/bets.php" class="mm-lnk <?= $_cp==='bets'?'active':'' ?>">
      <div class="mm-ico">🔥</div>
      <div class="mm-lnk-lbl">Les Bets</div>
      <?php if ($_mmNewBets > 0 && $_cp !== 'bets'): ?>
        <div class="mm-lnk-new">NEW</div>
      <?php else: ?>
        <div class="mm-lnk-chev">›</div>
      <?php endif; ?>
    </a>
    <a href="/historique.php" class="mm-lnk <?= $_cp==='historique'?'active':'' ?>">
      <div class="mm-ico">📋</div>
      <div class="mm-lnk-lbl">Historique</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="/giveaway.php" class="mm-lnk <?= $_cp==='giveaway'?'active':'' ?>">
      <div class="mm-ico">🎁<?php if ($_mmGiveawayActif && $_cp !== 'giveaway'): ?><span class="mm-ico-dot"></span><?php endif; ?></div>
      <div class="mm-lnk-lbl">GiveAway</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="/prono-commu.php" class="mm-lnk <?= $_cp==='pronocommu'?'active':'' ?>">
      <div class="mm-ico">⚽</div>
      <div class="mm-lnk-lbl">Prono de la commu</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="/montante.php" class="mm-lnk <?= $_cp==='montante'?'active':'' ?>">
      <div class="mm-ico">📈</div>
      <div class="mm-lnk-lbl">Montante</div>
      <div class="mm-lnk-chev">›</div>
    </a>
  </div>

  <?php if (!empty($membre)): ?>
  <!-- Section MON COMPTE -->
  <div class="mm-sect">MON COMPTE</div>
  <div class="mm-lnks">
    <a href="/dashboard.php" class="mm-lnk <?= $_cp==='dashboard' && !in_array($_GET['tab']??'',['profil','bankroll'])?'active':'' ?>">
      <div class="mm-ico">📊</div>
      <div class="mm-lnk-lbl">Dashboard</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="/dashboard.php?tab=profil" class="mm-lnk <?= $_cp==='dashboard' && ($_GET['tab']??'')==='profil'?'active':'' ?>">
      <div class="mm-ico">👤</div>
      <div class="mm-lnk-lbl">Mon Profil</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="/dashboard.php?tab=bankroll" class="mm-lnk <?= $_cp==='dashboard' && ($_GET['tab']??'')==='bankroll'?'active':'' ?>">
      <div class="mm-ico">🏦</div>
      <div class="mm-lnk-lbl">Bankroll</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="/sav.php" class="mm-lnk <?= $_cp==='sav'?'active':'' ?>">
      <div class="mm-ico">🎫</div>
      <div class="mm-lnk-lbl">SAV</div>
      <div class="mm-lnk-chev">›</div>
    </a>
  </div>
  <?php endif; ?>

  <!-- Section AUTRES -->
  <div class="mm-sect">AUTRES</div>
  <div class="mm-lnks">
    <a href="/" class="mm-lnk">
      <div class="mm-ico">🏠</div>
      <div class="mm-lnk-lbl">Accueil</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <a href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer" class="mm-lnk">
      <div class="mm-ico">𝕏</div>
      <div class="mm-lnk-lbl">Suivre sur X</div>
      <div class="mm-lnk-chev">↗</div>
    </a>
    <?php if (isAdmin()): ?>
    <a href="/panel-x9k3m/index.php" class="mm-lnk mm-admin">
      <div class="mm-ico">⚙️</div>
      <div class="mm-lnk-lbl">Panel Admin</div>
      <div class="mm-lnk-chev">›</div>
    </a>
    <?php endif; ?>
  </div>

  <!-- Footer CTAs -->
  <div class="mm-foot">
  <?php if (!empty($membre)): ?>
    <a href="/logout.php" class="mm-cta mm-cta-out">🚪 LOGOUT</a>
    <?php if (!$_mmBadge): ?>
    <a href="/souscrire.php" class="mm-cta mm-cta-main">💳 SOUSCRIRE</a>
    <?php else: ?>
    <a href="/dashboard.php?tab=profil" class="mm-cta mm-cta-main">⚙️ PROFIL</a>
    <?php endif; ?>
  <?php else: ?>
    <a href="/login.php" class="mm-cta mm-cta-login">🔓 CONNEXION</a>
    <a href="/register.php" class="mm-cta mm-cta-main">✨ S'INSCRIRE</a>
  <?php endif; ?>
  </div>
</div><!-- /mm-inner -->
</div>

<!-- Mobile bottom tab bar -->
<div class="mob-tabs">
  <a class="s-link <?= $_cp==='bets'?'active':'' ?>" href="/bets.php"><span class="ico">🔥</span> Bets</a>
  <a class="s-link <?= $_cp==='pronocommu'?'active':'' ?>" href="/prono-commu.php"><span class="ico">⚽</span> Prono</a>
  <a class="s-link <?= $_cp==='giveaway'?'active':'' ?>" href="/giveaway.php"><span class="ico">🎁</span> GiveAway</a>
  <a class="s-link <?= $_cp==='montante'?'active':'' ?>" href="/montante.php"><span class="ico">📈</span> Montante</a>
  <?php if (!empty($membre)): ?>
  <a class="s-link <?= $_cp==='dashboard' && !in_array($_GET['tab']??'',['profil','bankroll'])?'active':'' ?>" href="/dashboard.php"><span class="ico">📊</span> Compte</a>
  <?php else: ?>
  <a class="s-link" href="/login.php"><span class="ico">🔐</span> Connexion</a>
  <?php endif; ?>
</div>

<!-- Mascotte -->
<div class="mascotte-bg"><img src="/assets/images/mascotte.png" alt=""></div>

<div class="app">
<aside class="side">
  <?php if (!empty($membre)): ?>
  <div class="side-user">
    <div class="side-av"><?php if (!empty($avatarUrl)): ?><img src="<?= $avatarUrl ?>?v=<?= time() ?>" alt=""><?php else: ?><?= strtoupper(substr($membre['nom'],0,1)) ?><?php endif; ?></div>
    <div><div class="side-name"><?= htmlspecialchars($membre['nom']) ?></div><div class="side-email"><?= htmlspecialchars($membre['email']) ?></div></div>
  </div>
  <?php else: ?>
  <div class="side-user side-user-guest">
    <div class="side-av side-av-guest"></div>
    <div class="side-guest-actions">
      <a href="/login.php" class="side-guest-btn side-guest-btn-login">Se connecter</a>
      <a href="/register.php" class="side-guest-btn side-guest-btn-register">S'inscrire</a>
    </div>
  </div>
  <?php endif; ?>
  <div class="side-nav">
    <?php if (!empty($membre)): ?>
    <div class="side-sect">ESPACE</div>
    <a class="s-link <?= $_cp==='dashboard' && !in_array($_GET['tab']??'',['profil','bankroll'])?'active':'' ?>" href="/dashboard.php"><span class="ico">📊</span> Dashboard</a>
    <a class="s-link <?= $_cp==='dashboard' && ($_GET['tab']??'')==='profil'?'active':'' ?>" href="/dashboard.php?tab=profil"><span class="ico">👤</span> Mon Profil</a>
    <a class="s-link <?= $_cp==='dashboard' && ($_GET['tab']??'')==='bankroll'?'active':'' ?>" href="/dashboard.php?tab=bankroll"><span class="ico">🏦</span> Bankroll</a>
    <?php endif; ?>

    <div class="side-sect">PARIS & ANALYSES</div>
    <a class="s-link <?= $_cp==='bets'?'active':'' ?>" href="/bets.php"><span class="ico">🔥</span> Les Bets</a>
    <a class="s-link <?= $_cp==='historique'?'active':'' ?>" href="/historique.php"><span class="ico">📋</span> Historique</a>

    <div class="side-sect">COMMUNAUTÉ</div>
    <a class="s-link <?= $_cp==='giveaway'?'active':'' ?>" href="/giveaway.php"><span class="ico">🎁</span> GiveAway</a>
    <a class="s-link <?= $_cp==='pronocommu'?'active':'' ?>" href="/prono-commu.php"><span class="ico">⚽</span> Prono de la commu</a>
    <a class="s-link <?= $_cp==='montante'?'active':'' ?>" href="/montante.php"><span class="ico">📈</span> Montante</a>

    <div class="side-sect">AUTRES</div>
    <?php if (!empty($membre)): ?>
    <a class="s-link <?= $_cp==='sav'?'active':'' ?>" href="/sav.php"><span class="ico">🎫</span> SAV</a>
    <?php endif; ?>
    <a class="s-link <?= $_cp==='souscrire'?'active':'' ?>" href="/souscrire.php"><span class="ico">💳</span> Souscrire</a>
    <a class="s-link" href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer"><span class="ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></span> Suivre sur X</a>
  </div>
  <?php if (!empty($membre)): ?>
  <div class="side-foot"><a href="/logout.php">🚪 Déconnexion</a></div>
  <?php endif; ?>
</aside>
<main class="content">
<script>
(function(){
  var menu = document.getElementById('mobileMenu');
  var overlay = document.getElementById('menuOverlay');
  var btn = document.getElementById('btnHam');
  if (!menu || !overlay || !btn) return;

  window.toggleMenu = function(){
    menu.classList.contains('open') ? closeMenu() : openMenu();
  };

  window.openMenu = function(){
    menu.classList.add('open');
    overlay.classList.add('show');
    btn.classList.add('open');
    document.body.classList.add('menu-open');
  };

  window.closeMenu = function(){
    menu.classList.remove('open');
    overlay.classList.remove('show');
    btn.classList.remove('open');
    document.body.classList.remove('menu-open');
  };

  // Fermer au clic sur un lien
  menu.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click', function(){ closeMenu(); });
  });

  // Fermer avec Escape
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && menu.classList.contains('open')) closeMenu();
  });
})();
</script>
