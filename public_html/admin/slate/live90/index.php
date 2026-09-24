<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/auth.php';
requireSuperAdmin();
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['live90_csrf']??=bin2hex(random_bytes(32));
header('Cache-Control: no-store');
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=htmlspecialchars($_SESSION['live90_csrf'],ENT_QUOTES)?>"><title>SEUIL 90 · Live intelligence</title><link rel="stylesheet" href="assets/style.css"></head><body>
<?php readfile(__DIR__.'/assets/shell.html'); ?><script src="assets/app.js" defer></script></body></html>
