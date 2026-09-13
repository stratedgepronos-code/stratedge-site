<?php
// Included by the authenticated analysis list, after the owner check.
$scoreResult = $events[$m['key']]['result']['data'] ?? null;
$scorePeriod = $pick['period'] ?? 'ft';
$scoreReady = time() >= strtotime($m['kickoff']) + 90 * 60;
if ($scorePeriod === 'ft'):
?>
<form class="lab-card-score lab-form" method="post" action="<?= lab_h($labBase) ?>action.php">
    <?= lab_token() ?><input type="hidden" name="action" value="result"><input type="hidden" name="id" value="<?= lab_h($run['id']) ?>"><input type="hidden" name="match" value="<?= lab_h($m['key']) ?>"><input type="hidden" name="return_to" value="analyses"><input type="hidden" name="confirm_result" value="1">
    <div class="lab-card-score-heading"><strong>Score final</strong><?php if ($scoreResult): ?><span role="status"><?= lab_h(['won' => 'Gagné', 'lost' => 'Perdu', 'void' => 'Annulé', 'no_bet' => 'Score enregistré'][$scoreResult['outcome']] ?? 'Score enregistré') ?></span><?php endif; ?></div>
    <div class="lab-card-score-fields">
        <label>Domicile<input type="number" name="score_home" min="0" max="30" step="1" inputmode="numeric" required placeholder="—" aria-label="<?= lab_h('Score domicile : ' . $m['home']) ?>" value="<?= lab_h($scoreResult['home'] ?? '') ?>" <?= !$scoreReady ? 'disabled' : '' ?>></label>
        <label>Extérieur<input type="number" name="score_away" min="0" max="30" step="1" inputmode="numeric" required placeholder="—" aria-label="<?= lab_h('Score extérieur : ' . $m['away']) ?>" value="<?= lab_h($scoreResult['away'] ?? '') ?>" <?= !$scoreReady ? 'disabled' : '' ?>></label>
        <button class="lab-button lab-secondary" <?= !$scoreReady ? 'disabled' : '' ?>><?= $scoreResult ? 'Corriger le score final' : 'Enregistrer le score final' ?></button>
    </div>
    <small><?= $scoreReady ? 'À saisir une fois le match terminé · hors prolongations.' : 'La saisie sera disponible après le match.' ?></small>
</form>
<?php else: ?>
<p class="lab-muted">Pari par mi-temps : enregistre le score de la période dans la fiche du match.</p>
<?php endif; ?>
