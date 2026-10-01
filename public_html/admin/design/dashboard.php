<?php
/* Presentation only. All counters are provided by admin/index.php. */
require_once __DIR__.'/icons.php';
$seEuro = static fn($value) => number_format((float)$value, 2, ',', ' ');
$seInteger = static fn($value) => number_format((int)$value, 0, ',', ' ');
$seChannels = [
    ['label'=>'Multi · packs crédits','value'=>$revenuMulti,'count'=>$nbAchatsMulti,'unit'=>'packs vendus','color'=>'#64d7ed'],
    ['label'=>'Tennis · semaine','value'=>$revenuTennis,'count'=>$nbAboTennis,'unit'=>'abonnements','color'=>'#82d7ac'],
    ['label'=>'Fun · semaine','value'=>$revenuFun,'count'=>$nbAboFun,'unit'=>'abonnements','color'=>'#c3a4ea'],
    ['label'=>'VIP Max','value'=>$revenuVip,'count'=>$nbAboVip,'unit'=>'abonnements','color'=>'#e8c678'],
];
?>
<div class="se-dashboard">
  <header class="se-page-intro"><div><div class="se-eyebrow">Ton espace de pilotage</div><h1>Vue d’ensemble<span style="color:var(--se-pink)">.</span></h1><p>L’activité de StratEdge, les priorités et la suite.</p></div><div class="se-page-date"><?= se_admin_icon('clock',14) ?><time datetime="<?= date('c') ?>"><?= date('d.m.Y') ?></time><span>·</span>Actualisé à <?= date('H:i') ?></div></header>
  <section class="se-overview" aria-label="Revenus et priorités">
    <article class="se-revenue"><div class="se-panel-kicker"><span>Revenus cumulés</span><span class="se-label-pill">Depuis le début</span></div><div class="se-revenue-value"><?= $seEuro($revenuTotal) ?><small>€</small></div><p class="se-revenue-note">Packs crédits et abonnements enregistrés</p><div class="se-revenue-foot"><strong>4 offres · une vue consolidée</strong><a href="#se-revenue-breakdown">Voir la répartition <?= se_admin_icon('arrow',15) ?></a></div></article>
    <article class="se-panel se-priorities"><div class="se-panel-title"><h2>À traiter</h2><span>Support & communauté</span></div>
      <a class="se-task-row" href="/panel-x9k3m/tickets.php"><span class="se-task-icon"><?= se_admin_icon('ticket',17) ?></span><div><strong>Tickets ouverts</strong><small>Demandes en attente de résolution</small></div><b class="se-task-count"><?= $seInteger($nbTickets) ?></b><?= se_admin_icon('arrow',15) ?></a>
      <a class="se-task-row" href="/panel-x9k3m/messages.php"><span class="se-task-icon"><?= se_admin_icon('message',17) ?></span><div><strong>Messages non lus</strong><small>Conversations avec les membres</small></div><b class="se-task-count"><?= $seInteger($nbMessages) ?></b><?= se_admin_icon('arrow',15) ?></a>
      <p class="se-task-note"><?= (int)$nbTickets+(int)$nbMessages === 0 ? 'Tout est à jour. Place à la prochaine analyse.' : 'Un accès direct aux demandes qui attendent ta réponse.' ?></p>
    </article>
  </section>
  <section class="se-metrics" aria-label="Activité de la plateforme">
    <a class="se-metric" href="/panel-x9k3m/membres.php"><span><?= se_admin_icon('users',15) ?>Membres inscrits</span><strong><?= $seInteger($nbMembres) ?></strong><small>Comptes de la communauté</small><?= se_admin_icon('arrow',15) ?></a>
    <a class="se-metric" href="/panel-x9k3m/membres.php"><span><?= se_admin_icon('crown',15) ?>Abonnements actifs</span><strong><?= $seInteger($nbAboActifs) ?></strong><small>À la date de consultation</small><?= se_admin_icon('arrow',15) ?></a>
    <a class="se-metric" href="/panel-x9k3m/valider-bets.php"><span><?= se_admin_icon('chart',15) ?>Bets en ligne</span><strong><?= $seInteger($nbBets) ?></strong><small>Publications actuellement actives</small><?= se_admin_icon('arrow',15) ?></a>
  </section>
  <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
  <div class="se-section-label">Tes espaces d’analyse</div>
  <section class="se-workspaces" aria-label="Accès aux outils football">
    <a class="se-workspace" href="/panel-x9k3m/football-lab/index.php"><div class="se-workspace-symbol"><?= se_admin_icon('lab',24) ?></div><div><small>Avant-match</small><h3>Football Lab</h3><p>Statistiques, analyses et suivi des sélections.</p></div><?= se_admin_icon('arrow',20) ?></a>
    <a class="se-workspace" href="/panel-x9k3m/slate/live90/"><div class="se-workspace-symbol"><?= se_admin_icon('live',24) ?></div><div><small>Pendant le match</small><h3>Live Intelligence</h3><p>Lecture du jeu, signaux et historique live.</p></div><?= se_admin_icon('arrow',20) ?></a>
  </section>
  <?php endif; ?>
  <div class="se-section-label">Comprendre l’activité</div>
  <section class="se-detail-grid" aria-label="Répartition et fréquentation">
    <article class="se-panel" id="se-revenue-breakdown"><div class="se-panel-title"><h2>Répartition des revenus</h2><span>Cumul historique</span></div>
      <div class="se-segment-bar" aria-hidden="true"><?php foreach($seChannels as $channel): $share=$revenuTotal>0?max(0,$channel['value']/$revenuTotal*100):0; ?><span style="--channel:<?= $channel['color'] ?>;--share:<?= number_format($share,3,'.','') ?>%"></span><?php endforeach; ?></div>
      <?php foreach($seChannels as $channel): $share=$revenuTotal>0?$channel['value']/$revenuTotal*100:0; ?>
      <div class="se-channel-row"><div class="se-channel-name"><i class="se-channel-dot" style="--channel:<?= $channel['color'] ?>"></i><div><?= $channel['label'] ?><small><?= $seInteger($channel['count']) ?> <?= $channel['unit'] ?></small></div></div><strong><?= $seEuro($channel['value']) ?> €</strong><span class="se-channel-share"><?= number_format($share,1,',',' ') ?> %</span></div>
      <?php endforeach; ?>
      <details class="se-repartition"><summary>Détail VIP Max · <?= $seInteger($nbVipActifs) ?> actifs</summary><div class="se-split"><span>Shaym · 50 %<strong><?= $seEuro($revenuVip*.5) ?> €</strong></span><span>Yaffa · 20 %<strong><?= $seEuro($revenuVip*.2) ?> €</strong></span><span>Shuriik · 30 %<strong><?= $seEuro($revenuVip*.3) ?> €</strong></span></div><p>Fondateurs : <?= $seInteger($fondateurPlaces) ?>/10 · <?= $seInteger($fondateurRestant) ?> places restantes</p></details>
    </article>
    <article class="se-panel"><div class="se-panel-title"><h2>Fréquentation</h2><?= se_admin_icon('globe',17) ?></div><div class="se-traffic-big"><?= $seInteger($visiteursAujourdhui) ?></div><div class="se-traffic-label">visites enregistrées aujourd’hui</div><div class="se-traffic-list"><div><span>7 derniers jours</span><b><?= $seInteger($visiteursSemaine) ?></b></div><div><span>30 derniers jours</span><b><?= $seInteger($visiteursMois) ?></b></div><div><span>Depuis le début</span><b><?= $seInteger($visiteursAll) ?></b></div></div><p class="se-task-note">Comptage des visites, pas des visiteurs uniques.</p></article>
  </section>
  <div class="se-section-label">La communauté</div>
  <section class="se-activity-grid" aria-label="Membres et demandes récentes">
    <article class="se-panel"><div class="se-panel-title"><h2>Derniers membres</h2><a href="/panel-x9k3m/membres.php">Tous les membres ↗</a></div><div class="table-wrap"><table><thead><tr><th>Membre</th><th>Inscription</th></tr></thead><tbody>
    <?php foreach($derniersMembres as $m): ?><tr><td><div class="se-member"><span class="se-member-avatar" aria-hidden="true"><?= clean(function_exists('mb_substr') ? mb_strtoupper(mb_substr($m['nom'],0,1)) : strtoupper(substr($m['nom'],0,1))) ?></span><div><b><?= clean($m['nom']) ?></b><small><?= clean($m['email']) ?></small></div></div></td><td style="color:var(--se-muted);white-space:nowrap;font-size:10px"><?= date('d.m.Y',strtotime($m['date_inscription'])) ?></td></tr><?php endforeach; ?>
    <?php if(empty($derniersMembres)): ?><tr><td colspan="2"><div class="se-empty">Les prochains membres apparaîtront ici.</div></td></tr><?php endif; ?>
    </tbody></table></div></article>
    <article class="se-panel"><div class="se-panel-title"><h2>Demandes en attente</h2><a href="/panel-x9k3m/tickets.php">Ouvrir le SAV ↗</a></div><div class="table-wrap"><table><thead><tr><th>Demande</th><th>Statut</th></tr></thead><tbody>
    <?php foreach($derniersTickets as $t): ?><tr><td><div class="se-member"><div><b><?= clean($t['nom']) ?></b><small><?= clean($t['sujet']) ?></small></div></div></td><td><span class="se-status"><?= clean($t['statut']) ?></span></td></tr><?php endforeach; ?>
    <?php if(empty($derniersTickets)): ?><tr><td colspan="2"><div class="se-empty"><?= se_admin_icon('check',24) ?><p>Aucun ticket en attente.</p></div></td></tr><?php endif; ?>
    </tbody></table></div></article>
  </section>
  <footer class="se-dashboard-footer"><strong>StratEdge · Administration</strong><span>Ta stratégie. Notre Edge.</span></footer>
</div>
