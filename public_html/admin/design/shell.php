<?php
require_once __DIR__.'/icons.php';
$sePageNames = ['index'=>'Vue d’ensemble','membres'=>'Membres','admins'=>'Équipe admin','tickets'=>'Tickets SAV','messages'=>'Messages','messagerie-interne'=>'Messagerie interne','creer-card'=>'Studio de création','valider-bets'=>'Validation des bets','poster-bet'=>'Publier un bet','historique'=>'Historique','idees'=>'Idées & bugs','code-promo'=>'Codes promo','giveaway'=>'GiveAway','broadcast'=>'Broadcast','twitter-post'=>'Publication sur X','live90'=>'Live Intelligence','slate'=>'Edge Finder','prono-commu-admin'=>'Prono de la communauté','montante-tennis'=>'Montante Tennis','montante-foot'=>'Montante Foot','ht-tracker'=>'Bet Mi-Temps','edit-bet-image'=>'Modifier une image','football-lab-import'=>'Importer PackBall','football-lab-analyses'=>'Analyses football','football-lab-history'=>'Suivi des résultats'];
$sePageTitle = $sePageNames[$pageActive ?? ''] ?? 'Espace de travail';
$seRole = function_exists('isSuperAdmin') && isSuperAdmin() ? 'Super admin' : 'Administrateur';
?>
<style data-se-design="2026-10"><?php readfile(__DIR__.'/system.css'); ?></style>
<script>document.documentElement.classList.add('se-admin-root');document.body.classList.add('se-admin');document.body.dataset.adminPage=<?= json_encode($pageActive ?? '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<a class="se-skip" href="#se-main-content">Aller au contenu</a>
<header class="se-topbar">
  <div class="se-breadcrumb"><span>Administration</span><span class="se-breadcrumb-slash">/</span><strong><?= htmlspecialchars($sePageTitle, ENT_QUOTES) ?></strong></div>
  <div class="se-top-actions"><button type="button" class="se-search-trigger" data-se-search aria-label="Rechercher une page dans l’administration"><?= se_admin_icon('search',17) ?><span>Rechercher une page</span><kbd>Ctrl K</kbd></button><span class="se-role"><?= htmlspecialchars($seRole, ENT_QUOTES) ?></span><span class="se-avatar" aria-hidden="true">SE</span></div>
</header>
<dialog class="se-command" id="se-command" aria-labelledby="se-command-title">
  <form method="dialog" class="se-command-head"><label for="se-command-input" id="se-command-title"><?= se_admin_icon('search') ?><span class="se-sr-only">Rechercher une page</span></label><input id="se-command-input" type="search" placeholder="Où veux-tu aller ?" autocomplete="off"><button value="cancel" aria-label="Fermer la recherche">Esc</button></form>
  <p class="se-command-hint">NAVIGATION RAPIDE</p><div id="se-command-results" class="se-command-results"></div><p class="se-command-empty" hidden>Aucune page ne correspond à cette recherche.</p><footer>↑ ↓ pour parcourir <span>Entrée pour ouvrir · Échap pour fermer</span></footer>
</dialog>
<script><?php readfile(__DIR__.'/interactions.js'); ?></script>
