<?php
// ============================================================
// STRATEDGE — download-fonts.php
// Lancer UNE SEULE FOIS depuis le navigateur :
// https://stratedgepronos.fr/admin/download-fonts.php
// Télécharge Orbitron, Bebas Neue, Rajdhani en woff2
// et les sauvegarde dans /assets/fonts/
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$fontDir = __DIR__ . '/../assets/fonts/';
if (!is_dir($fontDir)) mkdir($fontDir, 0755, true);

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

// Étape 1 : appeler l'API Google Fonts pour récupérer les vraies URLs woff2
function getWoff2Url($apiUrl, $ua) {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ["User-Agent: $ua"],
    ]);
    $css = curl_exec($ch);
    curl_close($ch);
    if (!$css) return null;
    // Extraire toutes les URLs woff2 du CSS
    preg_match_all('/url\((https:\/\/fonts\.gstatic\.com[^)]+\.woff2)\)/', $css, $matches);
    return $matches[1] ?? [];
}

// Étape 2 : télécharger un fichier woff2
function downloadWoff2($url, $dest) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    if (!$data || strlen($data) < 500) return false;
    file_put_contents($dest, $data);
    return true;
}

$fonts = [
    // [nom_fichier, api_url]
    ['orbitron-700',  'https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap'],
    ['orbitron-900',  'https://fonts.googleapis.com/css2?family=Orbitron:wght@900&display=swap'],
    ['bebas-neue-400','https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap'],
    ['rajdhani-400',  'https://fonts.googleapis.com/css2?family=Rajdhani:wght@400&display=swap'],
    ['rajdhani-600',  'https://fonts.googleapis.com/css2?family=Rajdhani:wght@600&display=swap'],
    ['rajdhani-700',  'https://fonts.googleapis.com/css2?family=Rajdhani:wght@700&display=swap'],
];

$results = [];

foreach ($fonts as [$name, $apiUrl]) {
    $destFile = $fontDir . $name . '.woff2';

    // Déjà téléchargé ?
    if (file_exists($destFile) && filesize($destFile) > 500) {
        $results[] = "✅ $name.woff2 — déjà en cache (" . round(filesize($destFile)/1024) . " KB)";
        continue;
    }

    $urls = getWoff2Url($apiUrl, $ua);
    if (empty($urls)) {
        $results[] = "❌ $name — impossible de récupérer l'URL depuis Google Fonts";
        continue;
    }

    // Prendre la première URL (latin)
    $ok = downloadWoff2($urls[0], $destFile);
    if ($ok) {
        $results[] = "✅ $name.woff2 — téléchargé (" . round(filesize($destFile)/1024) . " KB)";
    } else {
        $results[] = "❌ $name — téléchargement échoué";
    }
}

// Générer le CSS @font-face local
$cssFontFace = <<<CSS
/* StratEdge — Fonts locales */
/* Généré automatiquement par download-fonts.php */

@font-face {
  font-family: 'Orbitron';
  font-weight: 700;
  font-style: normal;
  font-display: block;
  src: url('/assets/fonts/orbitron-700.woff2') format('woff2');
}
@font-face {
  font-family: 'Orbitron';
  font-weight: 900;
  font-style: normal;
  font-display: block;
  src: url('/assets/fonts/orbitron-900.woff2') format('woff2');
}
@font-face {
  font-family: 'Bebas Neue';
  font-weight: 400;
  font-style: normal;
  font-display: block;
  src: url('/assets/fonts/bebas-neue-400.woff2') format('woff2');
}
@font-face {
  font-family: 'Rajdhani';
  font-weight: 400;
  font-style: normal;
  font-display: block;
  src: url('/assets/fonts/rajdhani-400.woff2') format('woff2');
}
@font-face {
  font-family: 'Rajdhani';
  font-weight: 600;
  font-style: normal;
  font-display: block;
  src: url('/assets/fonts/rajdhani-600.woff2') format('woff2');
}
@font-face {
  font-family: 'Rajdhani';
  font-weight: 700;
  font-style: normal;
  font-display: block;
  src: url('/assets/fonts/rajdhani-700.woff2') format('woff2');
}
CSS;

file_put_contents($fontDir . 'stratedge-fonts.css', $cssFontFace);

?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Download Fonts — StratEdge</title>
  <style>
    body { font-family: monospace; background: #050810; color: #f0f4f8; padding: 2rem; }
    h1 { color: #ff2d7a; margin-bottom: 1.5rem; }
    .result { padding: 0.4rem 0; font-size: 1rem; }
    .done { color: #00c864; margin-top: 1.5rem; font-size: 1.1rem; font-weight: bold; }
    a { color: #00d4ff; }
  </style>
</head>
<body>
  <h1>📦 Téléchargement des fonts StratEdge</h1>
  <?php foreach ($results as $r): ?>
    <div class="result"><?= htmlspecialchars($r) ?></div>
  <?php endforeach; ?>
  <div class="done">
    ✅ Fonts sauvegardées dans <code>/assets/fonts/</code><br>
    CSS généré : <code>/assets/fonts/stratedge-fonts.css</code><br><br>
    Tu peux supprimer ce fichier maintenant.
  </div>
</body>
</html>
