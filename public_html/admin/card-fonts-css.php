<?php
/**
 * Polices des cards en CSS embarqué — version fail-safe.
 * 1) Si getLocalFontsCss() existe (template legacy) → l'utiliser
 * 2) Sinon : servir le cache disque (fonts-embedded.css / fonts-b64.css)
 * 3) Sinon : fallback @import Google Fonts — ne renvoie JAMAIS de 500
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=86400');

// 1) Fonction du template legacy si disponible
@include_once __DIR__ . '/live-card-template.legacy.php';
if (function_exists('getLocalFontsCss')) {
    try { echo getLocalFontsCss(); exit; } catch (Throwable $e) { /* fallback */ }
}

// 2) Cache disque généré (font-cache.php)
foreach (['fonts-embedded.css', 'fonts-b64.css'] as $f) {
    $p = __DIR__ . '/../assets/fonts/cache/' . $f;
    if (is_file($p) && filesize($p) > 5000) { readfile($p); exit; }
}

// 3) Fallback ultime : import direct Google Fonts (jamais de 500)
echo "@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Orbitron:wght@700;900&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&family=Instrument+Serif:ital@0;1&family=Archivo+Narrow:wght@400;500;700&family=Inter:wght@400;700;800;900&display=swap');\n";
