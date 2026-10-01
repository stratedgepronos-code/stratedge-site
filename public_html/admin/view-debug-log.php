<?php
// STRATEDGE — Visionneuse du debug-card.log (super admin uniquement)
// URL: /panel-x9k3m/view-debug-log.php   (le .log direct est bloqué en 403, normal)
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');
$f = __DIR__ . '/debug-card.log';
if (!is_file($f)) die("(aucun log — genere une card d'abord)");
$lines = file($f);
echo implode('', array_slice($lines, -40));
