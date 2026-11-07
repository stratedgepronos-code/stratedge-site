<?php
// ── Sidebar partagée — inclure en haut de chaque page admin ──
// Usage : require_once __DIR__ . '/sidebar.php';
// Avant d'inclure, définir : $pageActive = 'index' | 'poster-bet' | 'giveaway' | 'membres' | 'messages' | 'tickets' | 'historique' | …

// Toutes les requêtes en try/catch pour éviter crash si colonne/table manquante
$nbTicketsOpen = 0; $nbMsgNonLus = 0; $nbBetsHistorique = 0; $nbChatNonLus = 0; $nbInboxNonLus = 0;
try { $nbTicketsOpen    = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE statut != 'resolu'")->fetchColumn(); } catch(Exception $e) {}
try { $nbMsgNonLus      = (int)$db->query("SELECT COUNT(*) FROM messages WHERE expediteur='membre' AND lu=0")->fetchColumn(); } catch(Exception $e) {}
try { $nbBetsHistorique = (int)$db->query("SELECT COUNT(*) FROM bets WHERE resultat IS NOT NULL AND resultat NOT IN ('en_cours','pending')")->fetchColumn(); } catch(Exception $e) {}
try { $nbChatNonLus     = (int)$db->query("SELECT COUNT(*) FROM chat_messages WHERE expediteur='membre' AND lu=0")->fetchColumn(); } catch(Exception $e) {}
try { if (function_exists('isSuperAdmin') && isSuperAdmin()) $nbInboxNonLus = (int)$db->query("SELECT COUNT(*) FROM admin_inbox WHERE lu=0")->fetchColumn(); } catch(Exception $e) {}
$seLiveAccess = function_exists('isAdmin') && isAdmin();
$seAdminHome = $seLiveAccess ? '/panel-x9k3m/slate/live90/' : '/panel-x9k3m/index.php';
$seLiveHistory = ($pageActive ?? '') === 'live90' && basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '') === 'historique.php';
?>
<?php require __DIR__ . '/design/shell.php'; ?>

<!-- TOPBAR MOBILE -->
<div class="mobile-topbar">
  <a href="<?= $seAdminHome ?>" class="mob-logo">StratEdge <small>ADMIN</small></a>
  <div class="se-mobile-tools"><button class="se-search-trigger" type="button" data-se-search aria-label="Rechercher une page"><?= se_admin_icon('search',18) ?></button><button type="button" class="hamburger" id="hamburger" onclick="toggleSidebar()" aria-label="Ouvrir le menu" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button></div>
</div>

<!-- OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar" aria-label="Menu administrateur">
  <div class="sidebar-logo"><a class="se-brand" href="<?= $seAdminHome ?>" aria-label="StratEdge — accueil admin"><span class="se-brand-symbol" aria-hidden="true">S</span><span class="se-brand-word">StratEdge<small>Administration</small></span></a></div>

  <div class="sidebar-label">Espace de travail</div>
  <nav aria-label="Navigation principale">
    <?php if ($seLiveAccess): ?>
    <a href="/panel-x9k3m/slate/live90/" class="nav-item <?= ($pageActive==='live90' && !$seLiveHistory) ? 'active' : '' ?>">
      <?= se_admin_icon('live') ?> Live
    </a>
    <a href="/panel-x9k3m/slate/live90/historique.php" class="nav-item <?= $seLiveHistory ? 'active' : '' ?>">
      <?= se_admin_icon('history') ?> Historique du live
    </a>
    <?php endif; ?>
    <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
    <div class="nav-group <?= strpos((string)$pageActive, 'football-lab-') === 0 ? 'open' : '' ?>" data-group="football-lab">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)" style="color:#00d4ff;">
        <?= se_admin_icon('lab') ?> StratEdge Lab
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="/panel-x9k3m/football-lab/import.php" <?= ($pageActive==='football-lab-import') ? 'class="active"' : '' ?>>Importer PackBall</a>
        <a href="/panel-x9k3m/football-lab/index.php" <?= ($pageActive==='football-lab-analyses') ? 'class="active"' : '' ?>>Analyses football</a>
        <a href="/panel-x9k3m/football-lab/history.php" <?= ($pageActive==='football-lab-history') ? 'class="active"' : '' ?>>Suivi des résultats</a>
      </div>
    </div>
    <?php endif; ?>
    <a href="/panel-x9k3m/index.php?vue=overview" <?= ($pageActive==='index') ?'class="active"':'' ?>>
      <?= se_admin_icon('grid') ?> Vue d’ensemble
    </a>

    <?php
    $bettingOpenPages = ['valider-bets','creer-card','edit-bet-image','prono-commu-admin'];
    if (function_exists('isSuperAdmin') && isSuperAdmin()) {
        $bettingOpenPages[] = 'historique';
    }
    $bettingOpen = in_array($pageActive, $bettingOpenPages, true);
    ?>
    <div class="nav-group <?= $bettingOpen ? 'open' : '' ?>" data-group="betting">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <?= se_admin_icon('target') ?> Betting
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="/panel-x9k3m/valider-bets.php" <?= ($pageActive==='valider-bets') ?'class="active"':'' ?>><?= se_admin_icon('check') ?> Valider les bets</a>
        <a href="/panel-x9k3m/creer-card.php" <?= ($pageActive==='creer-card') ?'class="active"':'' ?>><?= se_admin_icon('image') ?> Créer une Card</a>
        <a href="/panel-x9k3m/prono-commu-admin.php" <?= ($pageActive==='prono-commu-admin') ?'class="active"':'' ?>><?= se_admin_icon('target') ?> Prono de la commu</a>
        <a href="/panel-x9k3m/edit-bet-image.php" <?= ($pageActive==='edit-bet-image') ?'class="active"':'' ?>><?= se_admin_icon('image') ?> Modifier image bet</a>
        <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
        <a href="/panel-x9k3m/historique.php" <?= ($pageActive==='historique') ?'class="active"':'' ?>>
          <?= se_admin_icon('history') ?> Historique
          <?php if ($nbBetsHistorique > 0): ?><span class="badge-count"><?= $nbBetsHistorique ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php $usersOpen = in_array($pageActive, ['membres','admins']); ?>
    <div class="nav-group <?= $usersOpen ? 'open' : '' ?>" data-group="users">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <?= se_admin_icon('users') ?> Membres & équipe
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="/panel-x9k3m/membres.php" <?= ($pageActive==='membres') ?'class="active"':'' ?>><?= se_admin_icon('users') ?> Membres</a>
        <a href="/panel-x9k3m/gestion-admins.php" <?= ($pageActive==='admins') ?'class="active"':'' ?>><?= se_admin_icon('crown') ?> Admins</a>
      </div>
    </div>

    <a href="/panel-x9k3m/idees.php" <?= ($pageActive==='idees') ?'class="active"':'' ?>>
      <?= se_admin_icon('bulb') ?> Idées & Bugs
    </a>

    <a href="/panel-x9k3m/code-promo.php" <?= ($pageActive==='code-promo') ?'class="active"':'' ?>>
      <?= se_admin_icon('ticket') ?> Code promo
    </a>

    <a href="/panel-x9k3m/giveaway.php" <?= ($pageActive==='giveaway') ?'class="active"':'' ?> style="<?= ($pageActive==='giveaway') ? '' : 'color:rgba(255,45,120,0.92);' ?>">
      <?= se_admin_icon('gift') ?> GiveAway
    </a>

    <?php $msgOpen = in_array($pageActive, ['messagerie-interne','messages']); ?>
    <div class="nav-group <?= $msgOpen ? 'open' : '' ?>" data-group="messagerie">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <?= se_admin_icon('message') ?> Messagerie
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
        <a href="/panel-x9k3m/messagerie-interne.php" <?= ($pageActive==='messagerie-interne') ?'class="active"':'' ?>>
          <?= se_admin_icon('message') ?> Messagerie interne
          <?php if (!empty($nbInboxNonLus)): ?><span class="badge-count"><?= $nbInboxNonLus ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="/panel-x9k3m/messages.php" <?= ($pageActive==='messages') ?'class="active"':'' ?>>
          <?= se_admin_icon('message') ?> Messages
          <?php if ($nbMsgNonLus > 0): ?><span class="badge-count"><?= $nbMsgNonLus ?></span><?php endif; ?>
        </a>
      </div>
    </div>

    <a href="/panel-x9k3m/tickets.php" <?= ($pageActive==='tickets') ?'class="active"':'' ?>>
      <?= se_admin_icon('ticket') ?> Tickets SAV
      <?php if ($nbTicketsOpen > 0): ?><span class="badge-count"><?= $nbTicketsOpen ?></span><?php endif; ?>
    </a>

    <?php $pushOpen = in_array($pageActive, ['broadcast','twitter-post']); ?>
    <div class="nav-group <?= $pushOpen ? 'open' : '' ?>" data-group="push">
      <button type="button" class="nav-group-toggle" onclick="toggleNavGroup(this)">
        <?= se_admin_icon('broadcast') ?> Diffusion & réseaux
        <span class="chevron">›</span>
      </button>
      <div class="nav-group-inner">
        <a href="/panel-x9k3m/broadcast.php" <?= ($pageActive==='broadcast') ?'class="active"':'' ?>><?= se_admin_icon('broadcast') ?> Broadcast</a>
        <a href="/panel-x9k3m/twitter-post.php" <?= ($pageActive==='twitter-post') ?'class="active"':'' ?>>
          <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.259 5.629L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg></span> Poster sur X
        </a>
      </div>
    </div>

    <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
    <a href="/panel-x9k3m/journal.php" <?= ($pageActive==='journal') ?'class="active"':'' ?>><?= se_admin_icon('image') ?> Journal public</a>
    <?php endif; ?>

  </nav>

  <div class="sidebar-footer">
    <a href="/" class="btn-site">
      <?= se_admin_icon('globe',17) ?>
      <span>Voir le site</span>
      <span style="margin-left:auto;font-size:0.7rem;opacity:0.6;">↗</span>
    </a>
    <a href="/logout.php" class="btn-logout"><?= se_admin_icon('logout',16) ?> Déconnexion</a>
  </div>
</div>
<nav class="admin-mob-tabs" aria-label="Navigation mobile">
  <?php if ($seLiveAccess): ?>
  <a href="/panel-x9k3m/slate/live90/" class="<?= ($pageActive==='live90') ? 'active' : '' ?>"><span class="ico"><?= se_admin_icon('live') ?></span><span>Live</span></a>
  <?php else: ?>
  <a href="/panel-x9k3m/index.php" class="<?= ($pageActive==='index') ? 'active' : '' ?>"><span class="ico"><?= se_admin_icon('grid') ?></span><span>Accueil</span></a>
  <?php endif; ?>
  <a href="/panel-x9k3m/valider-bets.php" class="<?= ($pageActive==='valider-bets') ? 'active' : '' ?>"><span class="ico"><?= se_admin_icon('check') ?></span><span>Valider</span></a>
  <a href="/panel-x9k3m/creer-card.php" class="<?= ($pageActive==='creer-card') ? 'active' : '' ?>"><span class="ico"><?= se_admin_icon('image') ?></span><span>Créer</span></a>
  <a href="/panel-x9k3m/messages.php" class="<?= ($pageActive==='messages') ? 'active' : '' ?>"><span class="ico"><?= se_admin_icon('message') ?></span><span>Messages</span></a>
  <a href="/panel-x9k3m/tickets.php" class="<?= ($pageActive==='tickets') ? 'active' : '' ?>"><span class="ico"><?= se_admin_icon('ticket') ?></span><span>SAV</span></a>
</nav>



<!-- STRATEDGE_TENNIS_UI_BOOTSTRAP_START -->
<?php if (($pageActive ?? '') === 'edge-finder-tennis'): ?>
<script defer src="/panel-x9k3m/assets/tennis-ui-bootstrap.js?v=20260725-2"></script>
<?php endif; ?>
<!-- STRATEDGE_TENNIS_UI_BOOTSTRAP_END -->


