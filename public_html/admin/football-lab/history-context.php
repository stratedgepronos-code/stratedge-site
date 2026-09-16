<?php if (!empty($m['historical_context'])): $hc = $m['historical_context']; ?>
<details class="lab-panel"><summary>Historiques complémentaires · descriptifs</summary>
<p><?= lab_h($hc['message']) ?></p>
<?php foreach ($hc['sources'] as $venue => $sources): foreach ($sources as $source): ?>
<p><strong><?= lab_h($venue === 'home' ? $m['home'] . ' à domicile' : $m['away'] . ' à l’extérieur') ?></strong> · <?= lab_h($source['competition'] . ' · ' . $source['season']) ?> · <?= (int)$source['n'] ?> matchs.
Période : <?= lab_h(($source['period_start'] ?? 'aucun match') . ' → ' . ($source['period_end'] ?? 'aucun match')) ?>.
<?php if ($source['n']): ?>Buts marqués / encaissés : <?= number_format($source['gf'], 2, ',', ' ') ?> / <?= number_format($source['ga'], 2, ',', ' ') ?>. Fréquence +2,5 : <?= number_format($source['over25_percent'], 1, ',', ' ') ?> %.<?php endif; ?></p>
<?php endforeach; endforeach; ?>
<?php if ($hc['issues']): ?><ul><?php foreach ($hc['issues'] as $issue): ?><li><?= lab_h($issue) ?></li><?php endforeach; ?></ul><?php endif; ?>
<p class="lab-muted">Fréquences observées, pas des probabilités de ce match. Sources FootyStats détaillées dans l’export diagnostic.</p></details>
<?php endif; ?>
