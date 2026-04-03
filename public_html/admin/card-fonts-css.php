<?php
/**
 * Polices des cards en CSS embarqué (même logique que live-card-template getLocalFontsCss).
 * Appel same-origin depuis creer-card.php → compatible avec une CSP connect-src 'self' stricte
 * (pas de fetch navigateur vers fonts.googleapis.com).
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/live-card-template.php';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=86400');

if (!function_exists('getLocalFontsCss')) {
    http_response_code(500);
    echo '/* getLocalFontsCss indisponible */';
    exit;
}

echo getLocalFontsCss();
