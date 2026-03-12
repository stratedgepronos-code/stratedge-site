<?php
/**
 * Diagnostic SMTP — affiche à l'écran chaque étape (connexion, EHLO, STARTTLS, AUTH).
 * Permet de voir pourquoi mail-tester ne reçoit rien sans consulter error_log.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Test SMTP</title>';
echo '<style>body{font-family:monospace;background:#111;color:#eee;padding:2rem;} .ok{color:#0c6;} .err{color:#c66;} pre{margin:0.5em 0;} h1{font-size:1.2rem;}</style></head><body>';
echo '<h1>🔌 Diagnostic SMTP (Brevo)</h1>';

if (!file_exists(__DIR__ . '/../includes/smtp-config.php')) {
    echo '<p class="err">smtp-config.php absent sur le serveur (includes/smtp-config.php).</p>';
    echo '</body></html>';
    exit;
}

require_once __DIR__ . '/../includes/smtp-config.php';

if (!defined('SMTP_HOST') || !SMTP_HOST || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
    echo '<p class="err">SMTP_HOST, SMTP_USER ou SMTP_PASS manquant dans smtp-config.php.</p>';
    echo '</body></html>';
    exit;
}

$host = SMTP_HOST;
$port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
$user = SMTP_USER;
$pass = SMTP_PASS;

$steps = [];
$sock = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 15);
if (!$sock) {
    echo '<p class="err">Connexion impossible ' . htmlspecialchars($host) . ':' . $port . '</p>';
    echo '<pre>' . htmlspecialchars($errstr) . ' (errno ' . $errno . ')</pre>';
    echo '<p>Vérifier : firewall, port 587 sortant autorisé, host/port corrects.</p>';
    echo '</body></html>';
    exit;
}
$steps[] = ['Connexion TCP', true, $host . ':' . $port];

$read = function () use ($sock) {
    $line = @fgets($sock, 8192);
    return $line !== false ? trim($line) : '';
};
$send = function ($cmd) use ($sock) { @fwrite($sock, $cmd . "\r\n"); };

$g = $read();
$steps[] = ['Banner', strpos($g, '220') === 0, $g];

$send('EHLO ' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost'));
$ehlo = [];
while (($l = $read()) !== '') {
    $ehlo[] = $l;
    if (strlen($l) >= 4 && $l[3] === ' ') break;
}
$steps[] = ['EHLO', !empty($ehlo) && strpos($ehlo[0], '250') === 0, implode("\n", $ehlo)];

$send('STARTTLS');
$r = $read();
$steps[] = ['STARTTLS', strpos($r, '220') === 0, $r];
if (strpos($r, '220') !== 0) {
    echo '<h2>Étapes</h2>';
    foreach ($steps as $s) {
        echo '<p class="' . ($s[1] ? 'ok' : 'err') . '">' . htmlspecialchars($s[0]) . ': ' . ($s[1] ? 'OK' : 'ÉCHEC') . '</p><pre>' . htmlspecialchars($s[2]) . '</pre>';
    }
    fclose($sock);
    echo '</body></html>';
    exit;
}

if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    echo '<p class="err">Échec chiffrement TLS</p>';
    fclose($sock);
    echo '</body></html>';
    exit;
}
$steps[] = ['TLS activé', true, 'OK'];

$send('EHLO ' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost'));
while (($l = $read()) !== '') { if (strlen($l) >= 4 && $l[3] === ' ') break; }

$send('AUTH LOGIN');
$read();
$send(base64_encode($user));
$read();
$send(base64_encode($pass));
$code = $read();
$authOk = strpos($code, '235') === 0;
$steps[] = ['AUTH LOGIN', $authOk, $code];

$send('QUIT');
fclose($sock);

echo '<h2>Résultat</h2>';
foreach ($steps as $s) {
    echo '<p class="' . ($s[1] ? 'ok' : 'err') . '">' . htmlspecialchars($s[0]) . ': ' . ($s[1] ? 'OK' : 'ÉCHEC') . '</p>';
    if (!empty($s[2])) echo '<pre>' . htmlspecialchars($s[2]) . '</pre>';
}

if ($authOk) {
    echo '<p class="ok"><strong>Connexion SMTP OK.</strong> Si mail-tester ne reçoit rien, le souci peut venir de l’envoi DATA (taille, contenu). Envoie un test depuis « Test des notifications » puis vérifie l’error_log pour [StratEdge SMTP] après DATA.</p>';
} else {
    echo '<p class="err">Auth échouée : vérifier SMTP_USER (email Brevo) et SMTP_PASS (clé SMTP, pas le mot de passe du compte). Créer une clé dans Brevo → SMTP & API → Clés SMTP.</p>';
}

echo '<p><a href="test-notifications.php" style="color:#8af;">← Retour Test des notifications</a></p>';
echo '</body></html>';