<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
lab_start('Ta journée commence ici.', 'import');
if ($labError) { lab_end(); exit; }
?>
<section class="lab-panel lab-import-panel">
    <div class="lab-eyebrow">FootyStats</div><h2>Les vrais bilans domicile et extérieur</h2>
    <p>À chaque import, le Lab retrouve les matchs et complète PackBall avec le bilan à domicile de l’équipe qui reçoit et le bilan à l’extérieur de son adversaire.</p>
    <?php if (\StratEdgeLab\FootyStats::configured()->ready()): ?>
    <p class="lab-muted">Clé présente sur le serveur. Le test ci-dessous vérifie l’accès à ton compte ; la couverture de chaque match est contrôlée à l’import.</p>
    <form method="post" action="<?= lab_h($labBase) ?>action.php"><?= lab_token() ?><input type="hidden" name="action" value="footystats_check"><button class="lab-button lab-secondary">Tester la connexion FootyStats</button></form>
    <?php else: ?>
    <p class="lab-notice">La clé FootyStats n’est pas disponible pour le Lab. L’import reste accessible, mais les sélections attendront l’enrichissement.</p>
    <details><summary>Configurer l’accès FootyStats</summary><p>Renseigne la clé dans la configuration serveur existante <code>FOOTYSTATS_API_KEY</code>, ou dans <code>STRATEDGE_LAB_FOOTYSTATS_KEY</code>. Le Lab la réutilisera automatiquement.</p></details>
    <?php endif; ?>
</section>
<section class="lab-panel lab-import-panel">
    <div class="lab-section-head"><div><div class="lab-eyebrow">Import automatique</div><h2>Deux fichiers. Une seule analyse.</h2><p>Charge ensemble les exports GPT (33 colonnes) et GPT-2 (28 colonnes) pour la même journée.</p></div><span class="lab-section-icon"><?= lab_icon('import', 21) ?></span></div>
    <form action="<?= lab_h($labBase) ?>action.php" method="post" enctype="multipart/form-data" class="lab-form" id="lab-packball-upload">
        <?= lab_token() ?><input type="hidden" name="action" value="packball_pair"><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <label class="lab-dropzone" id="lab-dropzone" for="lab-packball-file">
            <span class="lab-empty-icon"><?= lab_icon('import', 28) ?></span>
            <strong>Dépose tes deux CSV PackBall ici</strong>
            <span>ou clique pour sélectionner GPT et GPT-2 ensemble</span>
            <input type="file" id="lab-packball-file" name="packball[]" multiple accept=".csv,text/csv" required aria-describedby="lab-upload-help lab-upload-status">
            <small>2 CSV · 2 Mo par fichier · 1 000 matchs maximum</small>
        </label>
        <p id="lab-upload-status" class="lab-upload-status" role="status" aria-live="polite" hidden></p>
        <p id="lab-upload-error" class="lab-alert" role="alert" hidden></p>
        <noscript><button class="lab-button">Importer et analyser</button></noscript>
    </form>
    <p class="lab-muted" id="lab-upload-help">L’ordre des deux fichiers est libre. Les colonnes et les matchs sont rapprochés automatiquement. L’enrichissement FootyStats se lance avec l’analyse et peut prendre jusqu’à 45 secondes.</p>
</section>
<section class="lab-panel lab-import-panel">
    <div class="lab-section-head"><div><div class="lab-eyebrow">Fin de journée</div><h2>Valider les scores automatiquement</h2><p>Pour les scores, un seul des deux CSV réexporté après les matchs suffit. Les lignes en statut FT seront rapprochées des analyses existantes par date, domicile et extérieur.</p></div><span class="lab-section-icon"><?= lab_icon('history', 21) ?></span></div>
    <form action="<?= lab_h($labBase) ?>action.php" method="post" enctype="multipart/form-data" class="lab-form" id="lab-results-upload">
        <?= lab_token() ?><input type="hidden" name="action" value="packball_results"><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <label class="lab-dropzone lab-dropzone-compact" for="lab-results-file"><span class="lab-empty-icon"><?= lab_icon('check', 25) ?></span><strong>Choisir le CSV terminé</strong><span>Les scores FT seront validés dans l’historique</span><input type="file" id="lab-results-file" name="packball" accept=".csv,text/csv" required><small>Le même format PackBall · aucun réglage</small></label>
        <noscript><button class="lab-button">Importer les scores</button></noscript>
    </form>
    <p class="lab-muted">Un score déjà enregistré n’est jamais écrasé. Une contradiction ou une ligne ambiguë est signalée pour validation manuelle.</p>
</section>
<div class="lab-steps lab-import-steps">
    <div class="lab-step"><b><?= lab_icon('check', 15) ?></b><div><strong>Statistiques croisées</strong><p>Buts, échantillons, fréquences et contrôles de cohérence.</p></div></div>
    <div class="lab-step"><b><?= lab_icon('check', 15) ?></b><div><strong>Cotes de 1,60 à 3,50</strong><p>Comparaison aux prix et résistance à un scénario défavorable.</p></div></div>
    <div class="lab-step"><b><?= lab_icon('check', 15) ?></b><div><strong>Résultats directement</strong><p>Pistes à examiner ou aucun pari si les critères ne sont pas réunis.</p></div></div>
</div>
<p class="lab-muted">Garde le même ordre de colonnes dans ton export PackBall. Les horaires sont lus en heure de Paris.</p>
<?php lab_end(); ?>
