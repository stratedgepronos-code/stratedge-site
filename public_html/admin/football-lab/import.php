<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
$draft = null; $maps = []; $opts = []; $prefilled = false;
try {
    if (!$labError && isset($_GET['id'])) {
        $draft = $labStore->get((string)$_GET['id'], $labOwner);
        if ($draft['kind'] !== 'draft') { throw new InvalidArgumentException('Cet import est déjà une analyse.'); }
        if (($_SESSION['lab_form']['id'] ?? '') === $draft['id']) {
            $maps = $_SESSION['lab_form']['maps']; $opts = $_SESSION['lab_form']['options'];
        } else {
            $recent = $labStore->recent($labOwner, 'analysis', 1);
            if ($recent) {
                $old = $labStore->get($recent[0]['id'], $labOwner)['data'];
                foreach ($draft['data']['tables'] as $role => $table) {
                    if ($table['has_header'] && ($old['tables'][$role]['fingerprint'] ?? '') === $table['fingerprint']) {
                        $maps[$role] = $old['maps'][$role]; $prefilled = true;
                    }
                }
            }
        }
    }
} catch (Throwable $e) { $labError = 'Import introuvable ou inaccessible.'; $draft = null; }
lab_start('Importer les matchs', 'import');
if ($labError) { lab_end(); exit; }
if (!$draft): ?>
<section class="lab-panel"><h2>Nouvelle journée</h2><p>Ajoute ton export de statistiques. Les cotes peuvent être dans ce fichier ou dans un second export.</p>
<form action="<?= lab_h($labBase) ?>action.php" method="post" enctype="multipart/form-data" class="lab-form">
<?= lab_token() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
<div class="lab-grid"><?php foreach (['stats' => 'Statistiques PackBall', 'odds' => 'Cotes · facultatif'] as $role => $label): ?>
<fieldset><legend><?= lab_h($label) ?></legend><label>Fichier CSV UTF-8<input type="file" name="<?= $role ?>" accept=".csv,text/csv" <?= $role === 'stats' ? 'required' : '' ?>></label>
<label>Séparateur<select name="separator[<?= $role ?>]"><option value="auto">Détecter</option value=";">Point-virgule ;</option><option value=",">Virgule ,</option><option value="&#9;">Tabulation</option></select></label>
<label class="lab-check"><input type="checkbox" name="header[<?= $role ?>]" value="1" checked> La première ligne contient les noms des colonnes</label></fieldset><?php endforeach; ?></div>
<p class="lab-muted">2 Mo et 1 000 matchs maximum par fichier. Tu vérifieras les colonnes avant tout calcul.</p><button class="lab-button">Vérifier les fichiers</button></form></section>
<?php $drafts = $labStore->recent($labOwner, 'draft', 5); if ($drafts): ?><section class="lab-panel"><h2>Imports à reprendre</h2><ul class="lab-list"><?php foreach ($drafts as $r): ?><li><a href="<?= lab_h($labBase . 'import.php?id=' . $r['id']) ?>">Import du <?= lab_h($r['created_at']) ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php else: $tables = $draft['data']['tables']; ?>
<p class="lab-notice">Vérifie les exemples de cellules. « Partie gauche / droite » correspond à une valeur au format A | B. Les numéros affichés commencent à 1.</p>
<?php if ($prefilled): ?><p class="lab-muted">Correspondances préremplies depuis un export avec les mêmes en-têtes : vérifie-les avant de confirmer.</p><?php endif; ?>
<?php if (!empty($_SESSION['lab_import_errors'])): ?><section class="lab-panel"><h2>À corriger</h2><ul><?php foreach ($_SESSION['lab_import_errors'] as $e): ?><li><?= lab_h($e['file'] . ' · ligne de données ' . $e['row'] . ' : ' . $e['message']) ?></li><?php endforeach; ?></ul></section><?php unset($_SESSION['lab_import_errors']); endif; ?>
<form action="<?= lab_h($labBase) ?>action.php" method="post" class="lab-form">
<?= lab_token() ?><input type="hidden" name="action" value="analyze"><input type="hidden" name="id" value="<?= lab_h($draft['id']) ?>">
<?php foreach ($tables as $role => $table): ?><section class="lab-panel"><h2><?= $role === 'stats' ? 'Statistiques' : 'Cotes' ?> · <?= count($table['rows']) ?> lignes</h2><p class="lab-muted"><?= lab_h($table['name']) ?></p>
<details><summary>Aperçu des trois premières lignes</summary><div class="lab-table-scroll"><table><thead><tr><?php foreach ($table['headers'] as $i => $h): ?><th><?= lab_h(($i + 1) . ' · ' . $h) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach (array_slice($table['rows'], 0, 3) as $row): ?><tr><?php foreach ($row as $v): ?><td><?= lab_h($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></details>
<div class="lab-mapping"><?php $fields = $role === 'stats' ? \StratEdgeLab\Engine::fields() : array_intersect_key(\StratEdgeLab\Engine::fields(), array_flip(['home', 'away', 'kickoff', 'league'])); foreach ($fields as $key => $label): ?>
<label><?= lab_h($label) ?><?php lab_select('mapping[' . $role . '][' . $key . ']', $table, $maps[$role][$key] ?? ''); ?></label><?php endforeach; ?></div>
<details><summary>Associer les colonnes de cotes <?= $role === 'stats' && isset($tables['odds']) ? '(si présentes aussi dans les statistiques)' : '' ?></summary><div class="lab-mapping"><?php foreach (\StratEdgeLab\Engine::markets() as $key => $market): ?><label><?= lab_h($market['label']) ?><?php lab_select('mapping[' . $role . '][' . $key . ']', $table, $maps[$role][$key] ?? ''); ?></label><?php endforeach; ?></div></details></section><?php endforeach; ?>
<section class="lab-panel"><h2>Lecture des données</h2><div class="lab-grid">
<label>Format de date<select name="options[date_format]"><?php foreach (['d/m/Y H:i' => '08/09/2026 21:00', 'd/m/Y H:i:s' => '08/09/2026 21:00:00', 'd-m-Y H:i' => '08-09-2026 21:00', 'Y-m-d H:i' => '2026-09-08 21:00', 'Y-m-d H:i:s' => '2026-09-08 21:00:00', 'Y-m-d\\TH:i:sP' => '2026-09-08T21:00:00+02:00', 'H:i' => '21:00 (date ci-dessous)'] as $fmt => $example): ?><option value="<?= lab_h($fmt) ?>" <?= ($opts['date_format'] ?? '') === $fmt ? 'selected' : '' ?>><?= lab_h($example) ?></option><?php endforeach; ?></select></label>
<label>Fuseau des horaires<select name="options[timezone]"><?php foreach (['Europe/Paris', 'UTC', 'Europe/London', 'America/Guayaquil', 'America/Sao_Paulo', 'America/New_York'] as $tz): ?><option <?= ($opts['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= lab_h($tz) ?></option><?php endforeach; ?></select></label>
<label>Date si le fichier ne contient que l’heure<input type="date" name="options[match_date]" value="<?= lab_h($opts['match_date'] ?? date('Y-m-d')) ?>"></label>
<label>Nombre de matchs si absent des colonnes<input type="number" name="options[sample_default]" min="1" max="10000" step="1" placeholder="Ex. 10, à confirmer dans PackBall" value="<?= lab_h($opts['sample_default'] ?? '') ?>"></label>
<label>Cote minimale · facultative<input type="number" name="options[min_odds]" step="0.01" min="1.01" max="1000" placeholder="Aucune" value="<?= lab_h($opts['min_odds'] ?? '') ?>"></label>
<label>Source des cotes · facultative<input name="options[odds_source]" maxlength="180" placeholder="Bookmaker ou export PackBall" value="<?= lab_h($opts['odds_source'] ?? '') ?>"></label>
</div><p class="lab-muted">Sans cote minimale, le classement recherche strictement la probabilité la plus élevée, y compris sur les lignes faciles. Les cotes restent celles de ton export.</p>
<details open><summary>Marchés à comparer</summary><div class="lab-market-grid"><?php foreach (\StratEdgeLab\Engine::markets() as $id => $market): ?><label class="lab-check"><input type="checkbox" name="options[markets][]" value="<?= lab_h($id) ?>" <?= !isset($opts['markets']) || in_array($id, $opts['markets'], true) ? 'checked' : '' ?>><?= lab_h($market['label']) ?></label><?php endforeach; ?></div></details>
<label class="lab-check lab-confirm"><input type="checkbox" name="confirm_mapping" required> Je confirme les colonnes, les moyennes par match, les périodes et les échantillons domicile / extérieur utilisés.</label>
<button class="lab-button">Analyser les matchs</button></section></form>
<?php endif; lab_end(); ?>
