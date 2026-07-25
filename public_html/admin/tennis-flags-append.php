<?php
/**
 * Ajoute le décorateur de drapeaux uniquement sur Edge Finder Tennis.
 * Chargé par public_html/admin/.user.ini via auto_append_file.
 */
if (PHP_SAPI === 'cli') {
    return;
}

$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
if (strpos($requestUri, '/edge-finder-tennis/') === false) {
    return;
}

echo "\n<script defer src=\"/panel-x9k3m/assets/tennis-country-flags.js?v=20260725-1\"></script>\n";
