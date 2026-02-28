<?php
// ============================================================
// STRATEDGE — font-cache.php
// Télécharge les fonts Google une seule fois côté serveur,
// les cache en woff2 sur disque, retourne du CSS @font-face
// avec data URI base64 — aucune requête externe dans l'iframe.
//
// POURQUOI : les iframes srcdoc ont une origine "null".
// fonts.gstatic.com bloque toute requête CORS depuis null.
// Les fonts ne chargent jamais, peu importe le temps d'attente.
// Seule solution : embarquer les fonts en base64 dans le CSS.
// ============================================================

if (!defined('ABSPATH')) { define('ABSPATH', true); }

define('FONT_CACHE_DIR', __DIR__ . '/../assets/fonts/cache/');

/**
 * Retourne le CSS @font-face complet avec toutes les fonts en base64.
 * Télécharge et met en cache si nécessaire (1 seul téléchargement).
 */
function getEmbeddedFontsCss() {
    // Créer le dossier de cache si nécessaire
    if (!is_dir(FONT_CACHE_DIR)) {
        mkdir(FONT_CACHE_DIR, 0755, true);
    }

    $cacheFile = FONT_CACHE_DIR . 'fonts-embedded.css';

    // Si le cache existe et fait plus de 10KB, on le retourne directement
    if (file_exists($cacheFile) && filesize($cacheFile) > 10000) {
        return file_get_contents($cacheFile);
    }

    // Définir les fonts à télécharger
    // Format : [famille, poids, style, url directe woff2]
    // Les URLs sont stables — Google Fonts ne les change pas souvent
    $fonts = [
        // ── Bebas Neue 400 ──────────────────────────────
        [
            'family' => 'Bebas Neue',
            'weight' => 400,
            'style'  => 'normal',
            'api'    => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap',
        ],
        // ── Orbitron 700 & 900 ──────────────────────────
        [
            'family' => 'Orbitron',
            'weight' => 700,
            'style'  => 'normal',
            'api'    => 'https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap',
        ],
        [
            'family' => 'Orbitron',
            'weight' => 900,
            'style'  => 'normal',
            'api'    => 'https://fonts.googleapis.com/css2?family=Orbitron:wght@900&display=swap',
        ],
        // ── Rajdhani 400, 600, 700 ───────────────────────
        [
            'family' => 'Rajdhani',
            'weight' => 400,
            'style'  => 'normal',
            'api'    => 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@400&display=swap',
        ],
        [
            'family' => 'Rajdhani',
            'weight' => 600,
            'style'  => 'normal',
            'api'    => 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@600&display=swap',
        ],
        [
            'family' => 'Rajdhani',
            'weight' => 700,
            'style'  => 'normal',
            'api'    => 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@700&display=swap',
        ],
    ];

    $cssBlocks = [];
    $errors    = [];

    foreach ($fonts as $font) {
        $slug      = strtolower(str_replace([' ', ':'], ['-', ''], $font['family'])) . '-' . $font['weight'];
        $cacheWoff = FONT_CACHE_DIR . $slug . '.woff2';
        $woff2Data = null;

        // ── 1. Lire depuis le cache disque ──
        if (file_exists($cacheWoff) && filesize($cacheWoff) > 1000) {
            $woff2Data = file_get_contents($cacheWoff);
        }

        // ── 2. Sinon, télécharger ──
        if (!$woff2Data) {
            // Étape A : récupérer le CSS Google Fonts pour avoir l'URL woff2
            $css = curlGet($font['api'], [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'
            ]);

            if (!$css) {
                $errors[] = "Impossible de charger l'API Google Fonts pour {$font['family']} {$font['weight']}";
                continue;
            }

            // Extraire l'URL woff2
            if (!preg_match('/url\((https:\/\/fonts\.gstatic\.com[^)]+\.woff2)\)/', $css, $m)) {
                $errors[] = "URL woff2 introuvable pour {$font['family']} {$font['weight']}";
                continue;
            }
            $woff2Url = $m[1];

            // Étape B : télécharger le fichier woff2
            $woff2Data = curlGet($woff2Url);
            if (!$woff2Data || strlen($woff2Data) < 1000) {
                $errors[] = "Téléchargement woff2 échoué pour {$font['family']} {$font['weight']}";
                continue;
            }

            // Mettre en cache
            file_put_contents($cacheWoff, $woff2Data);
        }

        // ── 3. Encoder en base64 et générer le @font-face ──
        $b64 = base64_encode($woff2Data);
        $cssBlocks[] = "@font-face {\n"
            . "  font-family: '{$font['family']}';\n"
            . "  font-weight: {$font['weight']};\n"
            . "  font-style: {$font['style']};\n"
            . "  font-display: block;\n"
            . "  src: url('data:font/woff2;base64,{$b64}') format('woff2');\n"
            . "}\n";
    }

    if (empty($cssBlocks)) {
        // Aucune font chargée — logger et retourner vide
        @file_put_contents(FONT_CACHE_DIR . 'font-errors.log', implode("\n", $errors) . "\n", FILE_APPEND);
        return '/* FONT CACHE ERROR: ' . implode(' | ', $errors) . ' */';
    }

    $finalCss = implode("\n", $cssBlocks);

    // Sauvegarder le CSS assemblé (évite de ré-encoder à chaque requête)
    file_put_contents($cacheFile, $finalCss);

    return $finalCss;
}

/**
 * Helper curl minimal
 */
function curlGet($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($err) return null;
    return $result ?: null;
}
