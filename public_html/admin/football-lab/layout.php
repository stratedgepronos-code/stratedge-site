<?php
declare(strict_types=1);
function lab_icon(string $name, int $size = 18): string {
    $paths = [
        'import' => '<path d="M12 16V3m-5 5 5-5 5 5M4 15v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/>',
        'analyses' => '<path d="M4 20V10m8 10V4m8 16v-7M3 21h18"/>',
        'history' => '<path d="M3 11a9 9 0 1 1 2.6 7.4M3 4v7h7m2-4v5l3 2"/>',
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'lab' => '<path d="M9 3h6m-5 0v6L4 19a1.3 1.3 0 0 0 1 2h14a1.3 1.3 0 0 0 1-2L14 9V3M7 15h10"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['lab']) . '</svg>';
}
function lab_asset(string $file): string {
    // Nginx rewrites PHP panel URLs, but serves static files at their real /admin path.
    return '/admin/football-lab/assets/' . $file . '?v=' . substr(hash_file('sha256', __DIR__ . '/assets/' . $file), 0, 12);
}
function lab_start(string $title, string $active): void {
    global $db, $labBase, $labError;
    $pageActive = 'football-lab-' . $active;
    $descriptions = ['import' => 'Tes exports PackBall, le point de départ de chaque analyse.', 'analyses' => 'Compare les marchés. Examine le contexte. Construis ta sélection.', 'history' => 'Confronte les probabilités aux résultats, match après match.'];
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0a0a12"><title><?= lab_h($title) ?> · StratEdge Lab</title><meta name="robots" content="noindex,nofollow"><style>body{margin:0;background:#0a0a12;color:#f3f5fb;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}</style><link rel="stylesheet" href="<?= lab_h(lab_asset('lab.css')) ?>"></head><body class="lab-body">
    <?php require __DIR__ . '/../sidebar.php'; ?>
    <main class="main lab"><div class="lab-topline"><div class="lab-brand"><?= lab_icon('lab', 21) ?> StratEdge Lab <span>Football</span></div><span class="lab-badge">Modèle expérimental</span></div>
    <header class="lab-head"><div><div class="lab-eyebrow"><?= lab_h(['import' => '01 / Les données', 'analyses' => '02 / Les opportunités', 'history' => '03 / La performance'][$active] ?? 'Football') ?></div><h1><?= lab_h($title) ?></h1><p class="lab-description"><?= lab_h($descriptions[$active] ?? '') ?></p></div><?php if ($active !== 'import'): ?><a class="lab-button" href="<?= lab_h($labBase) ?>import.php"><?= lab_icon('import', 16) ?> Nouvel import</a><?php endif; ?></header>
    <nav class="lab-tabs" aria-label="StratEdge Lab"><?php foreach (['import' => ['import.php', 'Importer PackBall'], 'analyses' => ['index.php', 'Analyses'], 'history' => ['history.php', 'Suivi des résultats']] as $id => $item): ?><a <?= $active === $id ? 'aria-current="page"' : '' ?> href="<?= lab_h($labBase . $item[0]) ?>"><?= lab_icon($id) ?><?= lab_h($item[1]) ?></a><?php endforeach; ?></nav>
    <?php if ($labError): ?><p class="lab-alert" role="alert"><?= lab_h($labError) ?></p><?php endif; ?>
    <?php foreach (['lab_error' => 'lab-alert', 'lab_success' => 'lab-notice'] as $key => $class): if (isset($_SESSION[$key])): ?><p class="<?= $class ?>" role="status"><?= lab_h($_SESSION[$key]) ?></p><?php unset($_SESSION[$key]); endif; endforeach;
}
function lab_end(): void { ?><footer class="lab-footer"><strong>StratEdge Lab · Football</strong><span>Données PackBall / Analyses & suivi</span></footer></main><script src="<?= lab_h(lab_asset('lab.js')) ?>" defer></script></body></html><?php }
function lab_select(string $name, array $table, string $selected = ''): void {
    ?><select name="<?= lab_h($name) ?>"><option value="">Non fourni</option><?php foreach ($table['headers'] as $col => $heading):
        $sample = $table['rows'][0][$col] ?? '';
        $components = ['value' => ''];
        if (strpos($sample, '|') !== false) { $components += ['first' => ' · partie gauche', 'second' => ' · partie droite']; }
        foreach ($components as $component => $suffix): $value = $col . ':' . $component;
    ?><option value="<?= lab_h($value) ?>" <?= $selected === $value ? 'selected' : '' ?>><?= lab_h(($col + 1) . ' — ' . substr($heading, 0, 70) . $suffix . ' [' . substr($sample, 0, 75) . ']') ?></option><?php endforeach; endforeach; ?></select><?php
}
