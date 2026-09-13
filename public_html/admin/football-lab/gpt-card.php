<?php
$gpt = $events[$m['key']]['research']['data'] ?? null;
$gptDecision = $gpt['decision'] ?? 'pending';
?>
<div class="lab-notice"><strong>GPT · <?= lab_h(['retained'=>'Retenu après analyse', 'excluded'=>'Écarté après analyse', 'pending'=>'À attendre'][$gptDecision] ?? 'À attendre') ?></strong>
<p><?= lab_h($gpt['reason'] ?? ($past ? 'Pas de verdict GPT prématch enregistré.' : 'Analyse automatique en attente de traitement.')) ?></p>
<?php if ($gpt): ?><small>Recherche du <?= lab_h($gpt['checked_at']) ?> · <a href="<?= lab_h($labBase . 'match.php?id=' . $run['id'] . '&match=' . $m['key'] . '#contexte') ?>">Raisons et sources</a></small><?php endif; ?></div>
