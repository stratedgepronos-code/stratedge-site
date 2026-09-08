<?php
declare(strict_types=1);
function lab_start(string $title, string $active): void {
    global $db, $labBase, $labError;
    $pageActive = 'football-lab-' . $active;
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= lab_h($title) ?> · StratEdge Lab</title><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= lab_h($labBase) ?>assets/lab.css?v=1"></head><body>
    <?php require __DIR__ . '/../sidebar.php'; ?>
    <main class="main lab"><header class="lab-head"><div><div class="lab-eyebrow">STRATEDGE / FOOTBALL</div><h1><?= lab_h($title) ?></h1></div><span class="lab-badge">Lab · expérimental</span></header>
    <nav class="lab-tabs" aria-label="StratEdge Lab"><?php foreach (['import' => ['import.php', 'Importer'], 'analyses' => ['index.php', 'Analyses'], 'history' => ['history.php', 'Suivi']] as $id => $item): ?><a <?= $active === $id ? 'aria-current="page"' : '' ?> href="<?= lab_h($labBase . $item[0]) ?>"><?= lab_h($item[1]) ?></a><?php endforeach; ?></nav>
    <?php if ($labError): ?><p class="lab-alert" role="alert"><?= lab_h($labError) ?></p><?php endif; ?>
    <?php foreach (['lab_error' => 'lab-alert', 'lab_success' => 'lab-notice'] as $key => $class): if (isset($_SESSION[$key])): ?><p class="<?= $class ?>" role="status"><?= lab_h($_SESSION[$key]) ?></p><?php unset($_SESSION[$key]); endif; endforeach;
}
function lab_end(): void { ?></main><script src="<?= lab_h($GLOBALS['labBase']) ?>assets/lab.js?v=1" defer></script></body></html><?php }
function lab_select(string $name, array $table, string $selected = ''): void {
    ?><select name="<?= lab_h($name) ?>"><option value="">Non fourni</option><?php foreach ($table['headers'] as $col => $heading):
        $sample = $table['rows'][0][$col] ?? '';
        $components = ['value' => ''];
        if (strpos($sample, '|') !== false) { $components += ['first' => ' · partie gauche', 'second' => ' · partie droite']; }
        foreach ($components as $component => $suffix): $value = $col . ':' . $component;
    ?><option value="<?= lab_h($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= lab_h(($col + 1) . ' — ' . substr($heading, 0, 70) . $suffix . ' [' . substr($sample, 0, 75) . ']') ?></option><?php endforeach; endforeach; ?></select><?php
}
