<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require_once __DIR__ . '/lib/Metrics.php';
$entries = [];
try {
    if (!$labError) {
        foreach ($labStore->recent($labOwner, 'analysis', 100) as $r) {
            $run = $labStore->get($r['id'], $labOwner); $events = lab_latest($labStore->events($r['id'], $labOwner));
            foreach ($run['data']['analysis']['matches'] as $m) {
                // Do not retain raw tables and every candidate while aggregating a hundred runs.
                $summary = array_intersect_key($m, array_flip(['key', 'home', 'away', 'kickoff', 'pick']));
                $entries[] = ['run_id' => $run['id'], 'created_at' => $run['created_at'], 'match' => $summary, 'result' => $events[$m['key']]['result']['data'] ?? null];
            }
        }
    }
} catch (Throwable $e) { $labError = 'Le suivi est temporairement indisponible.'; }
$metrics = \StratEdgeLab\Metrics::summarize($entries);
lab_start('Suivre la précision', 'history');
if ($labError) { lab_end(); exit; }
?>
<p class="lab-notice">Premier choix statistique de chaque match dans les 100 dernières analyses. Les réimports ne multiplient pas les paris. Tous les choix initiaux sont suivis, y compris ceux écartés ensuite. Les résultats sont saisis et vérifiés par toi.</p>
<div class="lab-stats"><div><strong><?= $metrics['n'] ?></strong><span>choix réglés hors annulations</span></div><div><strong><?= $metrics['n'] ? lab_p($metrics['wins'] / $metrics['n']) : '—' ?></strong><span>réussite observée</span></div><div><strong><?= $metrics['brier'] === null ? '—' : number_format($metrics['brier'], 4, ',', ' ') ?></strong><span>score de Brier · plus bas = meilleur</span></div></div>
<section class="lab-panel"><h2>Probabilités annoncées et résultats</h2><?php if (!$metrics['n']): ?><p>Les premières mesures apparaîtront après la saisie des résultats dans les fiches de match.</p><?php else: ?><div class="lab-table-scroll"><table><thead><tr><th>Tranche annoncée</th><th>Effectif</th><th>Moyenne prévue</th><th>Réussite observée</th></tr></thead><tbody><?php foreach ($metrics['bands'] as $band => $v): ?><tr><td><?= $band * 10 ?>–<?= ($band + 1) * 10 ?> %</td><td><?= $v['n'] ?></td><td><?= lab_p($v['p'] / $v['n']) ?></td><td><?= lab_p($v['wins'] / $v['n']) ?></td></tr><?php endforeach; ?></tbody></table></div><p class="lab-muted">Un faible effectif ne permet pas de conclure à la calibration du modèle. Log-loss : <?= number_format($metrics['logloss'], 4, ',', ' ') ?>.</p><?php endif; ?></section>
<section class="lab-panel"><h2>Suivi aux cotes importées</h2><p><?= $metrics['priced'] ?> choix réglés avec une cote · résultat fictif à mise constante : <strong><?= number_format($metrics['units'], 2, ',', ' ') ?> unités</strong> · ROI : <strong><?= $metrics['roi'] === null ? '—' : lab_p($metrics['roi']) ?></strong>.</p><p class="lab-muted">Les cotes exportées ne prouvent pas qu’un pari pouvait être pris à ce prix. Ce suivi ne représente pas un historique de mises réelles.</p></section>
<section class="lab-panel"><h2>Résultats des choix initiaux</h2><div class="lab-table-scroll"><table><thead><tr><th>Match</th><th>Pari</th><th>Probabilité</th><th>Résultat</th></tr></thead><tbody><?php foreach (array_reverse($metrics['rows']) as $entry): $m = $entry['match']; ?><tr><td><a href="<?= lab_h($labBase . 'match.php?id=' . $entry['run_id'] . '&match=' . $m['key']) ?>"><?= lab_h($m['home'] . ' — ' . $m['away']) ?></a></td><td><?= lab_h($m['pick']['label']) ?></td><td><?= lab_p($m['pick']['probability']) ?></td><td><?= lab_h(['won' => 'Gagné', 'lost' => 'Perdu', 'void' => 'Annulé'][$entry['result']['outcome'] ?? ''] ?? 'À renseigner') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php lab_end(); ?>
