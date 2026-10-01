<?php if ($selectedMatches):
$chatMatches = [];
foreach ($selectedMatches as $item) {
    $chatMatches[] = array_intersect_key($item, array_flip(['home', 'away', 'league', 'kickoff', 'pick', 'stats', 'footystats', 'packball']));
}
$chatExport = json_encode(['exported_at' => gmdate('c'), 'matches' => $chatMatches], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
?>
<section class="lab-panel lab-form"><h2>Exporter les matchs sélectionnés</h2><p>Copie les <?= count($chatMatches) ?> sélections avec leurs statistiques et cotes, puis colle-les dans ta conversation.</p><button type="button" class="lab-button" id="lab-copy-chat">Copier les matchs et leurs statistiques</button><span id="lab-copy-status" role="status"></span><details><summary>Voir le texte à copier</summary><textarea id="lab-chat-export" rows="12" readonly aria-label="Matchs, statistiques et cotes"><?= lab_h($chatExport) ?></textarea></details></section>
<?php endif; ?>
