<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/auth.php';
requireSuperAdmin();
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['live90_csrf']??=bin2hex(random_bytes(32));
header('Cache-Control: no-store');
/* Assets intégrés : nginx ne sert pas les CSS/JS sous le panel. */
$read = static fn(string $f): string => (string)@file_get_contents(__DIR__.'/assets/'.$f);
$css  = str_ireplace('</style', '<\/style', $read('style.css')."\n".$read('history.css'));
$app  = str_ireplace('</script', '<\/script', $read('history.js'));
/* Sidebar du panel, comme sur les autres pages admin. Elle attend $db et
   $pageActive, et se place en position fixe à gauche : le contenu doit donc
   être enveloppé dans .main, qui porte la marge de 240 px. Son code est
   capturé à part : si elle échoue (base indisponible, fonction manquante),
   la console s'affiche quand même au lieu d'une page blanche, et la cause
   reste lisible dans le code source de la page. */
$pageActive = 'live90';
$sidebar = '';
ob_start();
try {
    $db = function_exists('getDB') ? getDB() : null;
    if (!is_object($db)) throw new RuntimeException('getDB indisponible');
    require dirname(__DIR__,2).'/sidebar.php';
    $sidebar = (string)ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    $sidebar = '<!-- sidebar non chargée : '.htmlspecialchars(get_class($e).' · '.$e->getMessage(),ENT_QUOTES).' -->';
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=htmlspecialchars($_SESSION['live90_csrf'],ENT_QUOTES)?>"><title>StratEdge · Historique live</title><style>
<?= $css ?>
</style></head><body>
<?= $sidebar ?>
<div class="main">
<?php readfile(__DIR__.'/assets/history.html'); ?>
</div><script>
<?= $app ?>
</script></body></html>

