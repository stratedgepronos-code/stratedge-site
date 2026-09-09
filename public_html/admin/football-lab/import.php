<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
lab_start('Ta journée commence ici.', 'import');
if ($labError) { lab_end(); exit; }
?>
<section class="lab-panel lab-import-panel">
    <div class="lab-section-head"><div><div class="lab-eyebrow">Import automatique</div><h2>Un fichier. Toute ta journée.</h2><p>Charge ton export PackBall : les statistiques et les cotes sont déjà dedans.</p></div><span class="lab-section-icon"><?= lab_icon('import', 21) ?></span></div>
    <form action="<?= lab_h($labBase) ?>action.php" method="post" enctype="multipart/form-data" class="lab-form" id="lab-packball-upload">
        <?= lab_token() ?><input type="hidden" name="action" value="packball_upload"><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <label class="lab-dropzone" id="lab-dropzone" for="lab-packball-file">
            <span class="lab-empty-icon"><?= lab_icon('import', 28) ?></span>
            <strong>Dépose ton CSV PackBall ici</strong>
            <span>ou clique pour choisir le fichier</span>
            <input type="file" id="lab-packball-file" name="packball" accept=".csv,text/csv" required aria-describedby="lab-upload-help lab-upload-status">
            <small>CSV · 2 Mo maximum · 1 000 matchs maximum</small>
        </label>
        <p id="lab-upload-status" class="lab-upload-status" role="status" aria-live="polite" hidden></p>
        <p id="lab-upload-error" class="lab-alert" role="alert" hidden></p>
        <noscript><button class="lab-button">Importer et analyser</button></noscript>
    </form>
    <p class="lab-muted" id="lab-upload-help">Ton format PackBall est configuré. L’analyse se lance dès que tu choisis le fichier, sans réglage supplémentaire.</p>
</section>
<div class="lab-steps lab-import-steps">
    <div class="lab-step"><b><?= lab_icon('check', 15) ?></b><div><strong>Statistiques croisées</strong><p>Buts, échantillons, fréquences et contrôles de cohérence.</p></div></div>
    <div class="lab-step"><b><?= lab_icon('check', 15) ?></b><div><strong>Cotes de 1,60 à 3,50</strong><p>Comparaison aux prix et résistance à un scénario défavorable.</p></div></div>
    <div class="lab-step"><b><?= lab_icon('check', 15) ?></b><div><strong>Résultats directement</strong><p>Pistes à examiner ou aucun pari si les critères ne sont pas réunis.</p></div></div>
</div>
<p class="lab-muted">Garde le même ordre de colonnes dans ton export PackBall. Les horaires sont lus en heure de Paris.</p>
<?php lab_end(); ?>
