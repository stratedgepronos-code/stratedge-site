<?php
// ============================================================
// test-fonts.php — Ouvrir UNE FOIS pour diagnostiquer les fonts
// https://stratedgepronos.fr/admin/test-fonts.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/live-card-template.php';

$css = getLocalFontsCss();
$size = strlen($css);
$hasBase64 = strpos($css, 'base64') !== false;
$hasFontFace = strpos($css, '@font-face') !== false;

// Extraire les familles déclarées
preg_match_all("/font-family:'([^']+)'/", $css, $families);
$familles = array_unique($families[1] ?? []);

// Vérifier le cache
$cacheDir = dirname(__DIR__) . '/assets/fonts/cache/';
$cacheFile = $cacheDir . 'fonts-b64.css';
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Test Fonts</title>
<style>body{font-family:monospace;background:#050810;color:#f0f4f8;padding:2rem;}
.ok{color:#00c864;} .err{color:#ff2d7a;} h2{color:#00d4ff;margin-top:1.5rem;}</style>
</head>
<body>
<h1>🔍 Diagnostic Fonts StratEdge</h1>

<h2>Résultat getLocalFontsCss()</h2>
<p>Taille CSS : <strong><?= number_format($size) ?> octets</strong> 
  <?= $size > 50000 ? '<span class="ok">✅ OK</span>' : '<span class="err">❌ Trop petit — le cache n\'a probablement pas été généré</span>' ?></p>
<p>Contient @font-face : <?= $hasFontFace ? '<span class="ok">✅ OUI</span>' : '<span class="err">❌ NON</span>' ?></p>
<p>Contient base64 : <?= $hasBase64 ? '<span class="ok">✅ OUI (fonts embarquées)</span>' : '<span class="err">❌ NON (fallback @import — fonts NE CHARGERONT PAS dans iframe)</span>' ?></p>
<p>Familles déclarées : <strong><?= implode(', ', $familles) ?: 'aucune' ?></strong></p>

<h2>Cache disque</h2>
<p>Dossier cache : <code><?= $cacheDir ?></code>
  <?= is_dir($cacheDir) ? '<span class="ok">✅ existe</span>' : '<span class="err">❌ absent</span>' ?></p>
<p>Fichier CSS cache : 
  <?= file_exists($cacheFile) ? '<span class="ok">✅ ' . number_format(filesize($cacheFile)) . ' octets</span>' : '<span class="err">❌ absent</span>' ?></p>

<?php
$woffs = ['orbitron-700','orbitron-900','bebas-neue-400','rajdhani-400','rajdhani-600','rajdhani-700'];
echo '<h2>Fichiers woff2</h2>';
foreach ($woffs as $w) {
    $f = $cacheDir . $w . '.woff2';
    if (file_exists($f)) echo "<p class='ok'>✅ $w.woff2 — " . number_format(filesize($f)) . " octets</p>";
    else echo "<p class='err'>❌ $w.woff2 — absent</p>";
}
?>

<h2>Test curl (serveur → Google)</h2>
<?php
$ch = curl_init('https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, 
    CURLOPT_USERAGENT=>'Mozilla/5.0 Chrome/120 Safari/537.36']);
$r = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
if ($err) echo "<p class='err'>❌ curl échoue : $err</p>";
elseif ($r && strlen($r) > 100) echo "<p class='ok'>✅ curl OK — réponse " . strlen($r) . " octets</p>";
else echo "<p class='err'>❌ curl réponse vide</p>";
?>

<h2>Forcer régénération</h2>
<?php if (isset($_GET['reset'])): 
    @unlink($cacheFile);
    array_map('unlink', glob($cacheDir . '*.woff2'));
    echo "<p class='ok'>✅ Cache supprimé — rechargez la page sans ?reset pour régénérer</p>";
else: ?>
<p><a href="?reset=1" style="color:#ff2d7a">🔄 Supprimer le cache et régénérer</a></p>
<?php endif; ?>

</body></html>
