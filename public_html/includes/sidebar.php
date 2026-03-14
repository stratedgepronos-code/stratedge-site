<?php
// ── Sidebar partagée pour toutes les pages membres ──
if (!isset($nbNonLus)) {
    $db2 = getDB();
    $stNl = $db2->prepare("SELECT COUNT(*) FROM messages WHERE membre_id = ? AND expediteur = 'admin' AND lu = 0");
    $stNl->execute([$membre['id']]);
    $nbNonLus = (int)$stNl->fetchColumn();
}
?>
<nav class="top-nav">
  <a href="/" class="nav-logo">
    <img src="/assets/images/logo site.png" alt="StratEdge" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
    <span class="nav-logo-fb" style="display:none;"><em>STRAT</em>EDGE</span>
  </a>
  <div class="nav-acts">
    <a href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer" class="nav-x" aria-label="Suivre StratEdge sur X (Twitter)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    </a>
    <a href="/historique.php">Historique</a>
    <a href="/bets.php">📊 Les Bets</a>
    <a href="/souscrire.php" class="nav-btn">Souscrire</a>
    <?php if (isAdmin()): ?><a href="/panel-x9k3m/index.php" class="nav-admin">⚙️ Panel</a><?php endif; ?>
    <a href="/logout.php" style="color:var(--txt3);">Déconnexion</a>
  </div>
  <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <a href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer">𝕏 Suivre sur X</a>
  <a href="/dashboard.php">📊 Dashboard</a>
  <a href="/dashboard.php?tab=notifs">🔔 Notifications</a>
  <a href="/historique.php">📋 Historique</a>
  <a href="/bets.php">🔥 Les Bets</a>
  <a href="/souscrire.php">💳 Souscrire</a>
  <a href="/sav.php">🎫 SAV</a>
  <?php if (isAdmin()): ?><a href="/panel-x9k3m/index.php">⚙️ Panel</a><?php endif; ?>
  <a href="/logout.php">🚪 Déconnexion</a>
</div>
<div class="mob-tabs">
  <a class="s-link <?= $currentPage==='dashboard'?'active':'' ?>" href="/dashboard.php"><span class="ico">📊</span> Dashboard</a>
  <a class="s-link <?= $currentPage==='dashboard' && ($_GET['tab']??'')==='notifs'?'active':'' ?>" href="/dashboard.php?tab=notifs"><span class="ico">🔔</span> Notifs</a>
  <a class="s-link <?= $currentPage==='bets'?'active':'' ?>" href="/bets.php"><span class="ico">🔥</span> Bets</a>
  <a class="s-link <?= $currentPage==='pronocommu'?'active':'' ?>" href="/prono-commu.php"><span class="ico">⚽</span> Prono commu</a>
  <a class="s-link <?= $currentPage==='chat'?'active':'' ?>" href="/chat.php"><span class="ico">💬</span> Chat</a>
  <a class="s-link <?= $currentPage==='sav'?'active':'' ?>" href="/sav.php"><span class="ico">🎫</span> SAV</a>
</div>
<!-- Mascotte en position fixe, hors de tout conteneur -->
<div class="mascotte-bg"><img src="/assets/images/mascotte.png" alt=""></div>
<div class="app">
<aside class="side">
  <div class="side-user">
    <div class="side-av"><?php if ($avatarUrl): ?><img src="<?= $avatarUrl ?>?v=<?= time() ?>" alt=""><?php else: ?><?= strtoupper(substr($membre['nom'],0,1)) ?><?php endif; ?></div>
    <div><div class="side-name"><?= htmlspecialchars($membre['nom']) ?></div><div class="side-email"><?= htmlspecialchars($membre['email']) ?></div></div>
  </div>
  <div class="side-nav">
    <a class="s-link <?= $currentPage==='dashboard' && !in_array($_GET['tab']??'',['profil','notifs'])?'active':'' ?>" href="/dashboard.php"><span class="ico">📊</span> Dashboard</a>
    <a class="s-link <?= $currentPage==='dashboard' && ($_GET['tab']??'')==='profil'?'active':'' ?>" href="/dashboard.php?tab=profil"><span class="ico">👤</span> Mon Profil</a>
    <a class="s-link <?= $currentPage==='dashboard' && ($_GET['tab']??'')==='notifs'?'active':'' ?>" href="/dashboard.php?tab=notifs"><span class="ico">🔔</span> Notifications</a>
    <a class="s-link <?= $currentPage==='historique'?'active':'' ?>" href="/historique.php"><span class="ico">📋</span> Historique</a>
    <div class="side-sep"></div>
    <a class="s-link <?= $currentPage==='bets'?'active':'' ?>" href="/bets.php"><span class="ico">🔥</span> Les Bets</a>
    <a class="s-link <?= $currentPage==='pronocommu'?'active':'' ?>" href="/prono-commu.php"><span class="ico">⚽</span> Prono de la commu</a>
    <a class="s-link <?= $currentPage==='chat'?'active':'' ?>" href="/chat.php"><span class="ico">💬</span> Chat<?php if($nbNonLus>0):?><span class="badge-n"><?=$nbNonLus?></span><?php endif;?></a>
    <a class="s-link <?= $currentPage==='sav'?'active':'' ?>" href="/sav.php"><span class="ico">🎫</span> SAV</a>
    <div class="side-sep"></div>
    <a class="s-link <?= ($currentPage??'')==='souscrire'?'active':'' ?>" href="/souscrire.php"><span class="ico">💳</span> Souscrire</a>
    <a class="s-link" href="https://x.com/strat_edge_" target="_blank" rel="noopener noreferrer"><span class="ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></span> Suivre sur X</a>
  </div>
  <div class="side-foot"><a href="/logout.php">🚪 Déconnexion</a></div>
</aside>
<main class="content">
<script>
function toggleMenu(){
  var m = document.getElementById('mobileMenu');
  if (m) m.classList.toggle('open');
}
document.addEventListener('click', function(e){
  var m = document.getElementById('mobileMenu');
  var btn = document.querySelector('.hamburger');
  if (!m || !m.classList.contains('open')) return;
  if ((btn && btn.contains(e.target)) || m.contains(e.target)) return;
  m.classList.remove('open');
});
document.querySelectorAll('#mobileMenu a').forEach(function(a){
  a.addEventListener('click', function(){
    var m = document.getElementById('mobileMenu');
    if (m) m.classList.remove('open');
  });
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') {
    var m = document.getElementById('mobileMenu');
    if (m) m.classList.remove('open');
  }
});
</script>
