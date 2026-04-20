<?php
// ── Sidebar partagée pour toutes les pages membres ──
$_cp = $currentPage ?? '';
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
    <a href="/montante-tennis.php" class="nav-lnk <?= $_cp==='montante'?'active':'' ?>">🎾 Montante</a>
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

<!-- Drawer mobile -->
<div class="mobile-menu" id="mobileMenu">
  <div class="mm-head">
    <div class="mm-head-title">MENU</div>
    <button class="mm-close" onclick="closeMenu()" aria-label="Fermer">✕</button>
  </div>

  <?php if (!empty($membre)): ?>
  <div class="mm-user">
    <div class="mm-av"><?php if (!empty($avatarUrl)): ?><img src="<?= $avatarUrl ?>?v=<?= time() ?>" alt=""><?php else: ?><?= strtoupper(substr($membre['nom'],0,1)) ?><?php endif; ?></div>
    <div class="mm-info">
      <div class="mm-name"><?= htmlspecialchars($membre['nom']) ?></div>
      <div class="mm-email"><?= htmlspecialchars($membre['email']) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="mm-sect">Navigation</div>
  <a href="/" class="mm-lnk"><span class="mm-ico">🏠</span> Accueil</a>
  <a href="/bets.php" class="mm-lnk <?= $_cp==='bets'?'active':'' ?>"><span class="mm-ico">🔥</span> Les Bets</a>
  <a href="/historique.php" class="mm-lnk <?= $_cp==='historique'?'active':'' ?>"><span class="mm-ico">📋</span> Historique</a>
  <a href="/giveaway.php" class="mm-lnk <?= $_cp==='giveaway'?'active':'' ?>"><span class="mm-ico">🎁</span> GiveAway</a>
  <a href="/prono-commu.php" class="mm-lnk <?= $_cp==='pronocommu'?'active':'' ?>"><span class="mm-ico">⚽</span> Prono de la commu</a>
  <a href="/montante-tennis.php" class="mm-lnk <?= $_cp==='montante'?'active':'' ?>"><span class="mm-ico">🎾</span> Montante Tennis</a>

  <?php if (!empty($membre)): ?>
  <div class="mm-sect">Mon compte</div>
  <a href="/dashboard.php" class="mm-lnk <?= $_cp==='dashboard' && !in_array($_GET['tab']??'',['profil','bankroll'])?'active':'' ?>"><span class="mm-ico">📊</span> Dashboard</a>
  <a href="/dashboard.php?tab=profil" class="mm-lnk <?= $_cp==='dashboard' && ($_GET['tab']??'')==='profil'?'active':'' ?>"><span class="mm-ico">👤</span> Mon Profil</a>
  <a href="/dashboard.php?tab=bankroll" class="mm-lnk <?= $_cp==='dashboard' && ($_GET['tab']??'')==='bankroll'?'active':'' ?>"><span class="mm-ico">🏦</span> Bankroll</a>
  <a href="/sav.php" class="mm-lnk <?= $_cp==='sav'?'active':'' ?>"><span class="mm-ico">🎫</span> SAV</a>
  <?php endif; ?>

  <div class="mm-sect">Autres</div>
  <a href="/souscrire.php" class="mm-lnk <?= $_cp==='souscrire'?'active':'' ?>"><span class="mm-ico">💳</span> Souscrire</a>
  <a href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer" class="mm-lnk"><span class="mm-ico">𝕏</span> Suivre sur X</a>
  <?php if (isAdmin()): ?><a href="/panel-x9k3m/index.php" class="mm-lnk mm-admin"><span class="mm-ico">⚙️</span> Panel Admin</a><?php endif; ?>

  <div class="mm-foot">
  <?php if (!empty($membre)): ?>
    <a href="/logout.php" class="mm-cta mm-cta-out">🚪 Déconnexion</a>
  <?php else: ?>
    <a href="/login.php" class="mm-cta mm-cta-login">🔓 Connexion</a>
    <a href="/register.php" class="mm-cta mm-cta-register">✨ S'inscrire</a>
  <?php endif; ?>
  </div>
</div>

<!-- Mobile bottom tab bar -->
<div class="mob-tabs">
  <a class="s-link <?= $_cp==='bets'?'active':'' ?>" href="/bets.php"><span class="ico">🔥</span> Bets</a>
  <a class="s-link <?= $_cp==='pronocommu'?'active':'' ?>" href="/prono-commu.php"><span class="ico">⚽</span> Prono</a>
  <a class="s-link <?= $_cp==='giveaway'?'active':'' ?>" href="/giveaway.php"><span class="ico">🎁</span> GiveAway</a>
  <a class="s-link <?= $_cp==='montante'?'active':'' ?>" href="/montante-tennis.php"><span class="ico">🎾</span> Montante</a>
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
    <a class="s-link <?= $_cp==='montante'?'active':'' ?>" href="/montante-tennis.php"><span class="ico">🎾</span> Montante Tennis</a>

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

  window.toggleMenu = function(){
    var isOpen = menu.classList.contains('open');
    if (isOpen) closeMenu();
    else openMenu();
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
