<?php
// ============================================================
// TEST SMTP — À supprimer après diagnostic
// Upload dans public_html/ puis ouvre : stratedgepronos.fr/test-smtp.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
if (file_exists(__DIR__ . '/includes/smtp-config.php')) {
    require_once __DIR__ . '/includes/smtp-config.php';
}

echo "<pre style='background:#111;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== TEST SMTP STRATEDGE ===\n\n";

// 1. Vérifier que la config est chargée
echo "1. Config SMTP chargée ?\n";
if (defined('SMTP_HOST')) {
    echo "   ✅ SMTP_HOST = " . SMTP_HOST . "\n";
    echo "   ✅ SMTP_PORT = " . (defined('SMTP_PORT') ? SMTP_PORT : 'non défini') . "\n";
    echo "   ✅ SMTP_SECURE = " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'non défini') . "\n";
    echo "   ✅ SMTP_USER = " . SMTP_USER . "\n";
    echo "   ✅ SMTP_PASS = " . str_repeat('*', strlen(SMTP_PASS)) . " (" . strlen(SMTP_PASS) . " chars)\n";
} else {
    echo "   ❌ SMTP_HOST non défini — smtp-config.php pas trouvé ou pas chargé\n";
    echo "   Vérif: fichier exists = " . (file_exists(__DIR__ . '/includes/smtp-config.php') ? 'OUI' : 'NON') . "\n";
    echo "</pre>";
    exit;
}

echo "\n";

// 2. Test connexion port 465 SSL
echo "2. Test connexion SSL (port 465)...\n";
$errno = 0; $errstr = '';
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$sock465 = @stream_socket_client('ssl://smtp.hostinger.com:465', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if ($sock465) {
    $banner = @fgets($sock465, 1024);
    echo "   ✅ Port 465 SSL : CONNECTÉ — " . trim($banner) . "\n";
    fclose($sock465);
} else {
    echo "   ❌ Port 465 SSL : ÉCHEC — {$errstr} (errno {$errno})\n";
}

echo "\n";

// 3. Test connexion port 587 TLS
echo "3. Test connexion TLS (port 587)...\n";
$sock587 = @stream_socket_client('tcp://smtp.hostinger.com:587', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if ($sock587) {
    $banner = @fgets($sock587, 1024);
    echo "   ✅ Port 587 TCP : CONNECTÉ — " . trim($banner) . "\n";
    fclose($sock587);
} else {
    echo "   ❌ Port 587 TCP : ÉCHEC — {$errstr} (errno {$errno})\n";
}

echo "\n";

// 4. Test envoi complet via port 465 SSL
echo "4. Test envoi complet via SSL 465...\n";
$sock = @stream_socket_client('ssl://smtp.hostinger.com:465', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if (!$sock) {
    echo "   ❌ Connexion impossible : {$errstr}\n";
    echo "</pre>";
    exit;
}

$read = function() use ($sock) { $l = @fgets($sock, 8192); return $l !== false ? trim($l) : ''; };
$send = function($cmd) use ($sock) { @fwrite($sock, $cmd . "\r\n"); };
$readAll = function() use ($sock) {
    $result = '';
    while (($line = @fgets($sock, 8192)) !== false) {
        $line = trim($line);
        $result .= $line . "\n";
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return trim($result);
};

$banner = $read();
echo "   Banner: {$banner}\n";

$send('EHLO stratedgepronos.fr');
$ehlo = $readAll();
echo "   EHLO: " . str_replace("\n", " | ", $ehlo) . "\n";

$send('AUTH LOGIN');
$r = $read();
echo "   AUTH LOGIN: {$r}\n";

$send(base64_encode(SMTP_USER));
$r = $read();
echo "   USER: {$r}\n";

$send(base64_encode(SMTP_PASS));
$r = $read();
echo "   PASS: {$r}\n";

if (strpos($r, '235') === 0) {
    echo "   ✅ AUTHENTIFICATION RÉUSSIE !\n";
    
    // Envoyer un vrai mail test
    $testTo = SMTP_USER; // s'envoyer à soi-même
    $send('MAIL FROM:<noreply@stratedgepronos.fr>');
    echo "   MAIL FROM: " . $read() . "\n";
    
    $send('RCPT TO:<' . $testTo . '>');
    echo "   RCPT TO: " . $read() . "\n";
    
    $send('DATA');
    echo "   DATA: " . $read() . "\n";
    
    $msg  = "From: StratEdge Pronos <noreply@stratedgepronos.fr>\r\n";
    $msg .= "To: {$testTo}\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode("Test SMTP StratEdge") . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Message-ID: <test." . time() . "@stratedgepronos.fr>\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "\r\n";
    $msg .= "Ce mail a ete envoye via SMTP Hostinger (port 465 SSL). Si tu le recois, ca marche !";
    
    @fwrite($sock, $msg . "\r\n.\r\n");
    $r = $read();
    echo "   ENVOI: {$r}\n";
    
    if (strpos($r, '250') === 0) {
        echo "\n   🎉 MAIL ENVOYÉ AVEC SUCCÈS VIA SMTP !\n";
    } else {
        echo "\n   ⚠️ Réponse inattendue après envoi\n";
    }
} else {
    echo "   ❌ AUTHENTIFICATION ÉCHOUÉE — vérifie le mot de passe\n";
}

$send('QUIT');
fclose($sock);

echo "\n=== FIN DU TEST ===\n";
echo "</pre>";
