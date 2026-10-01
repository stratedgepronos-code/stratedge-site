<?php
$notSelected = array_values(array_filter($analysis['matches'] ?? [], static function ($m) { return empty($m['pick']); }));
$diagnosticExport = \StratEdgeLab\DecisionEngine::diagnosticExport($analysis);
$diagnosticJson = json_encode($diagnosticExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
?>
<?php if ($notSelected): ?>
<details class="lab-panel" id="lab-rejected"><summary>Matchs non retenus (<?= count($notSelected) ?>)</summary>
<p>Les données manquantes empêchent une décision. Un rejet après analyse signifie que les données sont exploitables, mais que les critères ne sont pas atteints.</p>
<?php foreach ($notSelected as $m): $d = \StratEdgeLab\DecisionEngine::diagnostic($m); $fs = $m['footystats'] ?? []; ?>
<article class="lab-panel" data-lab-search="<?= lab_h($m['home'] . ' ' . $m['away'] . ' ' . $m['league']) ?>">
<h3><a href="<?= lab_h($labBase . 'match.php?id=' . $run['id'] . '&match=' . $m['key']) ?>"><?= lab_h($m['home'] . ' — ' . $m['away']) ?></a></h3>
<p class="lab-meta"><?= lab_h($m['league'] . ' · ' . $m['kickoff']) ?></p>
<p><strong><?= lab_h($d['title']) ?></strong></p><p><?= lab_h($d['message']) ?></p>
<p class="lab-muted">Échantillons FootyStats : recevant à domicile <?= lab_h((string)($fs['home']['n'] ?? $fs['sample']['home_n'] ?? 'non archivé')) ?> · visiteur à l’extérieur <?= lab_h((string)($fs['away']['n'] ?? $fs['sample']['away_n'] ?? 'non archivé')) ?> · minimum 8 chacun.
Source : <?= lab_h($fs['source']['competition'] ?? (isset($fs['season_id']) ? $m['league'] : 'non archivée')) ?><?php if (isset($fs['season_id'])): ?> · saison ID <?= (int)$fs['season_id'] ?><?php endif; ?>.</p>
<?php if ($d['state'] === 'data_missing'): ?><p class="lab-notice">Données descriptives uniquement. Les statistiques globales PackBall ne sont pas des bilans domicile/extérieur ; leurs estimations éventuelles ne valident aucun pari.</p><?php endif; ?>
<?php require __DIR__ . '/history-context.php'; ?>
<details><summary>Motifs par marché</summary><ul><?php foreach ($m['candidates'] ?? [] as $c): ?><li><strong><?= lab_h($c['label']) ?></strong> · cote <?= isset($c['odds']) ? number_format($c['odds'], 2, ',', ' ') : 'absente' ?> : <?= lab_h(implode(' ', $c['reasons'] ?? [])) ?></li><?php endforeach; ?></ul></details>
</article>
<?php endforeach; ?></details>
<?php endif; ?>
<section class="lab-panel lab-form"><h2>Export diagnostic</h2><p>Tous les matchs, motifs, sources, échantillons et marchés calculés. Version archivée : <?= lab_h($analysis['version'] ?? 'inconnue') ?>. Cet export ne recalcule pas les archives.</p>
<button type="button" class="lab-button lab-secondary" id="lab-copy-diagnostic">Copier le diagnostic</button>
<button type="button" class="lab-button lab-secondary" id="lab-download-diagnostic">Télécharger le diagnostic JSON</button><span id="lab-diagnostic-status" role="status"></span>
<details><summary>Voir le diagnostic à exporter</summary><textarea id="lab-diagnostic-export" rows="12" readonly aria-label="Diagnostic complet de l’import"><?= lab_h($diagnosticJson) ?></textarea></details></section>
