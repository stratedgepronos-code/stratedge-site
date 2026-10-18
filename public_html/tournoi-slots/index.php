<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$organiser = isset($_GET['organiser']) && $_GET['organiser'] === '1';
if ($organiser && !isAdmin()) {
    if (!isLoggedIn()) { header('Location: /login.php?redirect=' . rawurlencode('/tournoi-slots/?organiser=1')); exit; }
    http_response_code(403); echo 'Cet accès est réservé à un administrateur StratEdge.'; exit;
}
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; media-src 'self' blob:; object-src 'none'; base-uri 'none'; frame-ancestors 'self'");
$arenaBoot = ['admin'=>$organiser, 'csrf'=>$organiser ? csrfToken() : null];
session_write_close();
define('SLOT_ARENA_VIEW', true);
require __DIR__ . '/app.php';
