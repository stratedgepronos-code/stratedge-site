<?php
/**
 * Partial : card d'un candidat (pick).
 *
 * Variables attendues en contexte:
 *   $c       : tableau du candidat (pick_candidates row)
 *   $is_reco : bool, true si c'est le pick recommande du match
 *
 * Utilise par index.php pour rendre :
 *  - Le pick principal (visible toujours)
 *  - Les picks alternatifs (dans le bloc accordeon)
 */
?>
<div class="ef-cand ef-cand-<?= htmlspecialchars($c['status']) ?> ef-cand-decision-<?= htmlspecialchars($c['user_decision']) ?><?= $is_reco ? ' ef-cand-recommended' : '' ?>"
     data-candidate-id="<?= (int)$c['candidate_id'] ?>">
  <?php if ($is_reco): ?>
    <div class="ef-cand-reco-badge" title="Pick recommande pour ce match (anti-correlation : 1 seul pick/match)">⭐ PICK DU MATCH</div>
  <?php endif ?>
  <div class="ef-cand-status"><?= status_emoji($c['status']) ?></div>
  <div class="ef-cand-market">
    <div class="ef-cand-market-label"><?= htmlspecialchars($c['market']) ?></div>
    <div class="ef-cand-market-group"><?= htmlspecialchars($c['market_group']) ?></div>
  </div>
  <div class="ef-cand-odds">
    <div class="ef-cand-odds-label">COTE</div>
    <div class="ef-cand-odds-value"><?= number_format((float)$c['odds'], 2) ?></div>
  </div>
  <div class="ef-cand-ev">
    <div class="ef-cand-ev-label">EV</div>
    <div class="ef-cand-ev-value"><?= ((float)$c['ev'] >= 0 ? '+' : '') . number_format((float)$c['ev'] * 100, 1) ?>%</div>
  </div>
  <div class="ef-cand-probas">
    <div class="ef-cand-proba" title="Proba modèle">M <?= number_format((float)$c['model_proba'] * 100, 1) ?>%</div>
    <div class="ef-cand-proba" title="Proba marché dévigée">D <?= number_format((float)$c['devig_proba'] * 100, 1) ?>%</div>
  </div>
  <div class="ef-cand-conv">
    <div class="ef-cand-conv-label">CONV</div>
    <div class="ef-cand-conv-value">
      <span class="ef-cand-conv-num<?= (int)$c['conviction'] >= 100 ? ' ef-cand-conv-exceptional' : '' ?>"><?= (int)$c['conviction'] ?><?= (int)$c['conviction'] >= 100 ? ' 🔥' : '' ?></span>
      <span class="ef-cand-conv-bar"><span style="width:<?= min(100, (int)$c['conviction']) ?>%"></span></span>
    </div>
  </div>
  <div class="ef-cand-actions">
    <?php if ($c['user_decision'] === 'pending'): ?>
      <button class="ef-btn ef-btn-track" data-action="tracked" title="Suivre ce pick">📌 Suivre</button>
      <button class="ef-btn ef-btn-skip" data-action="skipped" title="Passer">✗</button>
    <?php else: ?>
      <?= decision_pill($c['user_decision']) ?>
      <?php if (in_array($c['user_decision'], ['tracked','skipped'], true)): ?>
        <button class="ef-btn ef-btn-undo" data-action="pending" title="Annuler">↩</button>
      <?php endif ?>
      <?php if ($c['user_decision'] === 'tracked'): ?>
        <button class="ef-btn ef-btn-won"  data-action="won"  title="Marquer gagné">🏆</button>
        <button class="ef-btn ef-btn-lost" data-action="lost" title="Marquer perdu">💀</button>
      <?php endif ?>
    <?php endif ?>
  </div>
</div>
