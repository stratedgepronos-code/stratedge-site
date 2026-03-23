<?php
/**
 * Tracker bets mi-temps — données en localStorage par compte membre.
 * Le HTML de référence est tracker-mitemps.html (même dossier).
 */
require_once __DIR__ . '/includes/auth.php';

$membre = isLoggedIn() ? getMembre() : null;
if (!$membre) {
    header('Location: /login.php?redirect=' . rawurlencode('/tracker-mitemps.php'));
    exit;
}

$currentPage = 'mitemps';
$avatarUrl = getAvatarUrl($membre);
$htLsKeyJson = json_encode('ht_bets_u' . (int) $membre['id'], JSON_UNESCAPED_UNICODE);

$htmlPath = __DIR__ . '/tracker-mitemps.html';
if (!is_readable($htmlPath)) {
    http_response_code(500);
    echo 'Fichier tracker-mitemps.html introuvable.';
    exit;
}
$html = file_get_contents($htmlPath);

if (!preg_match('#<style>(.*?)</style>#s', $html, $sm)) {
    http_response_code(500);
    echo 'Structure HTML invalide (style).';
    exit;
}
if (!preg_match('#<div class="page">(.*)</div>\s*<input type="file"#s', $html, $pm)) {
    http_response_code(500);
    echo 'Structure HTML invalide (page).';
    exit;
}
if (!preg_match('#<input type="file"[^>]*>\s*<script>(.*?)</script>#s', $html, $jm)) {
    http_response_code(500);
    echo 'Structure HTML invalide (script).';
    exit;
}

$script = $jm[1];
$script = str_replace("localStorage.getItem('ht_bets')", 'localStorage.getItem(' . $htLsKeyJson . ')', $script);
$script = str_replace("localStorage.setItem('ht_bets',", 'localStorage.setItem(' . $htLsKeyJson . ',', $script);
$pageInner = trim($pm[1]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>⏱️ Tracker Mi-Temps — StratEdge</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" crossorigin="anonymous"></script>
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
/* Layout avec sidebar StratEdge */
.page-tracker-mitemps .app{max-height:none!important;overflow:visible!important;}
.page-tracker-mitemps .content{overflow-y:visible!important;min-height:0!important;}
.tracker-mi-outer{max-width:100%;padding:0 0 2rem;}
<?= $sm[1] ?>
</style>
</head>
<body class="page-tracker-mitemps">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="tracker-mi-outer">
  <div class="page">
<?= $pageInner ?>

  </div>

<input type="file" id="fileInput" accept=".json" style="display:none" onchange="handleImport(event)">

<script>
<?= $script ?>

</script>
</div>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</main></div>
</body>
</html>
