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
lab_start('Le suivi des résultats', 'history');
if ($labError) { lab_end(); exit; }
?>
<div class="lab-stats"><div><small>Échantillon</small><strong><?= $metrics['n'] ?></strong><span>choix réglés hors annulations</span></div><div><small>Précision observée</small><strong><?= $metrics['n'] ? lab_p($metrics['wins'] / $metrics['n']) : '—' ?></strong><span>de choix gagnants</span></div><div><small>Calibration</small><strong><?= $metrics['brier'] === null ? '—' : number_format($metrics['brier'], 4, ',', ' ') ?></strong><span>score de Brier · plus bas = meilleur</span></div></div>
<div class="lab-two-column">
<section class="lab-panel"><div class="lab-section-head"><div><div class="lab-eyebrow">La réalité du terrain</div><h2>Prévisions & résultats</h2><p>Les probabilités annoncées face à la réussite observée.</p></div><span class="lab-section-icon"><?= lab_icon('analyses', 21) ?></span></div>
<?php if (!$metrics['n']): ?><div class="lab-empty-compact"><?= lab_icon('history', 32) ?><h3>Le premier résultat lance le suivi.</h3><p>Renseigne les scores dans tes fiches de match. La précision et la calibration se construiront ici, au fil des journées.</p><a class="lab-button lab-secondary" href="<?= lab_h($labBase) ?>index.php">Voir mes analyses <?= lab_icon('arrow', 16) ?></a></div>
<?php else: ?><div class="lab-table-scroll"><table><thead><tr><th>Tranche annoncée</th><th>Effectif</th><th>Moyenne prévue</th><th>Réussite observée</th></tr></thead><tbody><?php foreach ($metrics['bands'] as $band => $v): ?><tr><td><?= $band * 10 ?>–<?= ($band + 1) * 10 ?> %</td><td><?= $v['n'] ?></td><td><?= lab_p($v['p'] / $v['n']) ?></td><td><?= lab_p($v['wins'] / $v['n']) ?></td></tr><?php endforeach; ?></tbody></table></div><p class="lab-result-note">Un faible effectif ne permet pas de conclure à la calibration du modèle. Log-loss : <?= number_format($metrics['logloss'], 4, ',', ' ') ?>.</p><?php endif; ?></section>
<section class="lab-panel"><div class="lab-section-head"><div><div class="lab-eyebrow">Simulation à mise constante</div><h2>Aux cotes importées</h2><p><?= $metrics['priced'] ?> choix réglés avec une cote.</p></div></div><div class="lab-value-pair"><div><strong><?= $metrics['priced'] ? number_format($metrics['units'], 2, ',', ' ') : '—' ?></strong><span>résultat en unités</span></div><div><strong><?= $metrics['roi'] === null ? '—' : lab_p($metrics['roi']) ?></strong><span>rendement simulé</span></div></div><p class="lab-muted">Une unité fictive par choix, à la cote de l’export. Ce suivi ne représente pas des mises réelles.</p><p class="lab-result-note">La disponibilité de ces cotes au moment de parier n’est pas vérifiée.</p></section>
</div>
<section class="lab-panel"><div class="lab-section-head"><div><div class="lab-eyebrow">Journal des choix initiaux</div><h2>Chaque match laisse une trace.</h2></div><span class="lab-status"><?= count($metrics['rows']) ?> matchs</span></div>
<?php if (!$metrics['rows']): ?><div class="lab-howto"><div class="lab-step"><b>01</b><div><strong>Importe une journée</strong><p>Ajoute les statistiques et, si tu les as, les cotes PackBall.</p></div></div><div class="lab-step"><b>02</b><div><strong>Examine les choix proposés</strong><p>Chaque fiche conserve le premier choix statistique et tes contrôles.</p></div></div><div class="lab-step"><b>03</b><div><strong>Renseigne les résultats</strong><p>Le suivi se met à jour après l’enregistrement des scores.</p></div></div></div>
<?php else: ?><div class="lab-table-scroll"><table><thead><tr><th>Match</th><th>Pari initial</th><th>Probabilité</th><th>Résultat</th></tr></thead><tbody><?php foreach (array_reverse($metrics['rows']) as $entry): $m = $entry['match']; $outcome = $entry['result']['outcome'] ?? ''; ?><tr><td><a href="<?= lab_h($labBase . 'match.php?id=' . $entry['run_id'] . '&match=' . $m['key']) ?>"><?= lab_h($m['home'] . ' — ' . $m['away']) ?></a></td><td><?= lab_h($m['pick']['label']) ?></td><td><?= lab_p($m['pick']['probability']) ?></td><td><span class="lab-status <?= in_array($outcome, ['won', 'lost'], true) ? 'lab-status-' . $outcome : '' ?>"><?= lab_h(['won' => 'Gagné', 'lost' => 'Perdu', 'void' => 'Annulé'][$outcome] ?? 'À renseigner') ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<details class="lab-method"><summary>Comprendre ce suivi</summary><p>Premier choix statistique de chaque match dans les 100 dernières analyses. Les réimports ne multiplient pas les paris. Tous les choix initiaux sont suivis, y compris ceux écartés ensuite. Les résultats sont saisis et vérifiés par toi.</p></details>
<?php lab_end(); ?>
