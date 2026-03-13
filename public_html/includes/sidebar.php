<?php
// ── Sidebar partagée pour toutes les pages membres ──
if (!isset($nbNonLus)) {
    $db2 = getDB();
    $stNl = $db2->prepare("SELECT COUNT(*) FROM messages WHERE membre_id = ? AND expediteur = 'admin' AND lu = 0");
    $stNl->execute([$membre['id']]);
    $nbNonLus = (int)$stNl->fetchColumn();
}
$nbBetsEnCours = 0;
$montanteEnCours = false;
if (isset($membre) && !empty($membre['id'])) {
    try {
        $dbSide = getDB();
        $nbBetsEnCours = (int) $dbSide->query("SELECT COUNT(*) FROM bets WHERE actif = 1")->fetchColumn();
        $stmtM = $dbSide->query("SELECT id FROM montante_config WHERE statut = 'active' LIMIT 1");
        $montanteEnCours = $stmtM && $stmtM->fetch();
    } catch (Throwable $e) {}
}
?>
<nav class="top-nav">
  <a href="/" class="nav-logo">
    <img src="/assets/images/logo site.png" alt="StratEdge" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
    <span class="nav-logo-fb" style="display:none;"><em>STRAT</em>EDGE</span>
  </a>
  <div class="nav-acts">
    <a href="/historique.php">Historique</a>
    <a href="/bets.php">📊 Les Bets<?php if ($nbBetsEnCours > 0): ?><span class="nav-badge badge-blink"><?= $nbBetsEnCours ?></span><?php endif; ?></a>
    <a href="/prono-commu.php">⚽ Prono commu</a>
    <a href="/#pricing" class="nav-btn">Souscrire</a>
    <?php if (isAdmin()): ?><a href="/panel-x9k3m/index.php" class="nav-admin">⚙️ Panel</a><?php endif; ?>
    <a href="/logout.php" style="color:var(--txt3);">Déconnexion</a>
  </div>
  <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <a href="/dashboard.php">📊 Dashboard</a>
  <a href="/historique.php">📋 Historique</a>
  <a href="/bets.php">🔥 Les Bets<?php if ($nbBetsEnCours > 0): ?><span class="nav-badge badge-blink"><?= $nbBetsEnCours ?></span><?php endif; ?></a>
    <a href="/prono-commu.php">⚽ Prono commu</a>
  <a href="/montante-tennis.php">🎾 Montante Tennis <span class="montante-status <?= $montanteEnCours ? 'montante-on' : 'montante-off' ?>"><?= $montanteEnCours ? 'En cours' : 'Off' ?></span></a>
  <a href="/#pricing">💳 Souscrire</a>
  <a href="/sav.php">🎫 SAV</a>
  <?php if (isAdmin()): ?><a href="/panel-x9k3m/index.php">⚙️ Panel</a><?php endif; ?>
  <a href="/logout.php">🚪 Déconnexion</a>
</div>
<div class="mob-tabs">
  <a class="s-link <?= $currentPage==='dashboard'?'active':'' ?>" href="/dashboard.php"><span class="ico">📊</span> Dashboard</a>
  
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
    <a class="s-link <?= $currentPage==='dashboard' && ($_GET['tab']??'')==='dashboard' || ($currentPage==='dashboard' && !in_array($_GET['tab']??'',['profil']))?'active':'' ?>" href="/dashboard.php"><span class="ico">📊</span> Dashboard</a>
    <a class="s-link <?= $currentPage==='dashboard' && ($_GET['tab']??'')==='profil'?'active':'' ?>" href="/dashboard.php?tab=profil"><span class="ico">👤</span> Mon Profil</a>
    <div class="side-sep"></div>
    <a class="s-link <?= $currentPage==='bets'?'active':'' ?>" href="/bets.php"><span class="ico">🔥</span> Les Bets<?php if ($nbBetsEnCours > 0): ?><span class="badge-n badge-blink"><?= $nbBetsEnCours ?></span><?php endif; ?></a>
    <a class="s-link <?= $currentPage==='pronocommu'?'active':'' ?>" href="/prono-commu.php"><span class="ico">⚽</span> Prono de la commu</a>
    <a class="s-link <?= $currentPage==='montante'?'active':'' ?>" href="/montante-tennis.php"><span class="ico">🎾</span> Montante Tennis <span class="montante-status <?= $montanteEnCours ? 'montante-on' : 'montante-off' ?>"><?= $montanteEnCours ? 'En cours' : 'Off' ?></span></a>
    <a class="s-link <?= $currentPage==='chat'?'active':'' ?>" href="/chat.php"><span class="ico">💬</span> Chat<?php if($nbNonLus>0):?><span class="badge-n"><?=$nbNonLus?></span><?php endif;?></a>
    <a class="s-link <?= $currentPage==='sav'?'active':'' ?>" href="/sav.php"><span class="ico">🎫</span> SAV</a>
    <div class="side-sep"></div>
    <a class="s-link" href="/#pricing"><span class="ico">💳</span> Souscrire</a>
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
