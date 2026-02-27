<?php
// ============================================================
// STRATEDGE — Web Push Notifications (cURL pur, SANS Composer)
// Protocole : RFC 8291 (aes128gcm) + VAPID (RFC 8292)
// Nécessite : PHP 7.3+ avec extensions openssl + curl
// ============================================================

require_once __DIR__ . '/vapid-config.php';

// ── Vérification des prérequis ──────────────────────────────
$_pushReady = extension_loaded('openssl')
    && function_exists('openssl_pkey_derive')
    && function_exists('hash_hkdf')
    && function_exists('curl_init')
    && defined('VAPID_PUBLIC_KEY')
    && VAPID_PUBLIC_KEY !== 'VOTRE_CLE_PUBLIQUE_VAPID_ICI';

// ── Helpers base64url ───────────────────────────────────────
function _pushB64Encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function _pushB64Decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

// ── Convertir clé privée VAPID (32 octets b64url) en PEM ────
function _vapidPrivateKeyToPem(): string {
    $privBytes = _pushB64Decode(VAPID_PRIVATE_KEY);
    $pubBytes  = _pushB64Decode(VAPID_PUBLIC_KEY);

    // ASN.1 DER : ECPrivateKey avec courbe prime256v1
    $der = "\x30\x77"                                       // SEQUENCE (119 bytes)
        . "\x02\x01\x01"                                    // INTEGER 1 (version)
        . "\x04\x20" . $privBytes                           // OCTET STRING (32 bytes priv)
        . "\xa0\x0a"                                        // [0] EXPLICIT (10 bytes)
        .   "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"    // OID prime256v1
        . "\xa1\x44"                                        // [1] EXPLICIT (68 bytes)
        .   "\x03\x42\x00" . $pubBytes;                     // BIT STRING (65 bytes pub)

    return "-----BEGIN EC PRIVATE KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END EC PRIVATE KEY-----\n";
}

// ── Convertir clé publique raw (65 octets) en PEM ───────────
function _ecPubKeyToPem(string $rawPubKey): string {
    // ASN.1 DER : SubjectPublicKeyInfo pour EC prime256v1
    $der = "\x30\x59"                                       // SEQUENCE (89 bytes)
        . "\x30\x13"                                        // SEQUENCE (19 bytes)
        .   "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"        // OID ecPublicKey
        .   "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"    // OID prime256v1
        . "\x03\x42\x00" . $rawPubKey;                      // BIT STRING (65 bytes)

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

// ── Convertir signature DER (openssl) → raw r||s (JWT) ──────
function _derSigToRaw(string $der): string {
    $pos = 2; // skip SEQUENCE tag + length

    // Lire r
    $pos++; // skip 0x02 (INTEGER tag)
    $rLen = ord($der[$pos]); $pos++;
    $r = substr($der, $pos, $rLen); $pos += $rLen;

    // Lire s
    $pos++; // skip 0x02
    $sLen = ord($der[$pos]); $pos++;
    $s = substr($der, $pos, $sLen);

    // Normaliser à 32 octets chacun (supprimer padding 0x00 puis left-pad)
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

// ── Créer le JWT VAPID + header Authorization ───────────────
function _createVapidAuth(string $endpoint): ?string {
    $parsed  = parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];

    // Header JWT
    $header = _pushB64Encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));

    // Payload JWT
    $payload = _pushB64Encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12 heures
        'sub' => VAPID_SUBJECT,
    ]));

    $toSign = $header . '.' . $payload;

    // Charger la clé privée VAPID
    $pem = _vapidPrivateKeyToPem();
    $key = openssl_pkey_get_private($pem);
    if (!$key) {
        error_log('[Push] Impossible de charger la clé VAPID privée : ' . openssl_error_string());
        return null;
    }

    // Signer avec ES256 (ECDSA P-256 SHA-256)
    $derSig = '';
    if (!openssl_sign($toSign, $derSig, $key, OPENSSL_ALGO_SHA256)) {
        error_log('[Push] Échec signature VAPID : ' . openssl_error_string());
        return null;
    }

    $rawSig = _derSigToRaw($derSig);
    $jwt = $toSign . '.' . _pushB64Encode($rawSig);

    return 'vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY;
}

// ── Chiffrer le payload (RFC 8291 aes128gcm) ────────────────
function _encryptPushPayload(string $payload, string $userPubKeyB64, string $userAuthB64): ?array {
    $userPubKey  = _pushB64Decode($userPubKeyB64);  // 65 octets (point non compressé)
    $userAuth    = _pushB64Decode($userAuthB64);     // 16 octets

    // 1. Générer paire ECDH locale
    $localKey = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$localKey) {
        error_log('[Push] Échec génération clé ECDH : ' . openssl_error_string());
        return null;
    }

    $localDetails   = openssl_pkey_get_details($localKey);
    $localPubBytes  = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];

    // 2. Calculer le secret partagé ECDH
    $peerPem = _ecPubKeyToPem($userPubKey);
    $peerKey = openssl_pkey_get_public($peerPem);
    if (!$peerKey) {
        error_log('[Push] Clé publique subscriber invalide : ' . openssl_error_string());
        return null;
    }

    $sharedSecret = openssl_pkey_derive($peerKey, $localKey, 32);
    if ($sharedSecret === false) {
        error_log('[Push] Échec ECDH derive : ' . openssl_error_string());
        return null;
    }

    // 3. Dériver IKM via HKDF (auth_secret = salt, shared_secret = IKM)
    $info = "WebPush: info\x00" . $userPubKey . $localPubBytes;
    $ikm  = hash_hkdf('sha256', $sharedSecret, 32, $info, $userAuth);

    // 4. Générer un salt aléatoire (16 octets)
    $salt = random_bytes(16);

    // 5. Dériver CEK (16 octets) et nonce (12 octets)
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00",     $salt);

    // 6. Chiffrer avec AES-128-GCM
    $padded = $payload . "\x02"; // RFC 8291 : delimiter padding
    $tag = '';
    $encrypted = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($encrypted === false) {
        error_log('[Push] Échec AES-128-GCM : ' . openssl_error_string());
        return null;
    }

    // 7. Construire le body (header aes128gcm + ciphertext + tag)
    $body = $salt                                  // 16 octets : salt
        . pack('N', 4096)                          // 4 octets  : record size
        . chr(strlen($localPubBytes))              // 1 octet   : key ID length (65)
        . $localPubBytes                           // 65 octets : clé publique locale
        . $encrypted . $tag;                       // ciphertext + GCM tag (16 octets)

    return ['body' => $body, 'length' => strlen($body)];
}

// ── Envoyer une notification push via cURL ──────────────────
// Retourne : 'ok', 'expired' (à supprimer de la BDD), ou 'error'
function _sendSinglePush(string $endpoint, string $p256dh, string $auth, string $jsonPayload): string {
    // Chiffrer le payload
    $encrypted = _encryptPushPayload($jsonPayload, $p256dh, $auth);
    if (!$encrypted) return 'error';

    // Générer l'auth VAPID
    $vapidAuth = _createVapidAuth($endpoint);
    if (!$vapidAuth) return 'error';

    // Envoyer via cURL
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $encrypted['body'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . $encrypted['length'],
            'Authorization: ' . $vapidAuth,
            'TTL: 86400',
            'Urgency: high',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return 'ok';
    }

    // 404 ou 410 = subscription expirée → à supprimer
    if ($httpCode === 404 || $httpCode === 410) {
        return 'expired';
    }

    error_log("[Push] HTTP {$httpCode} pour " . substr($endpoint, 0, 80) . " — " . ($curlErr ?: substr($response, 0, 200)));
    return 'error';
}

// ═════════════════════════════════════════════════════════════
// FONCTION PRINCIPALE (même signature qu'avant)
// ═════════════════════════════════════════════════════════════

/**
 * Envoie une notification push à un membre (ou broadcast si null)
 */
function envoyerPush(?int $membreId, string $title, string $body, string $url = '/dashboard.php', string $tag = 'general'): void {
    global $_pushReady;
    if (!$_pushReady) {
        error_log('[Push] Prérequis manquants (openssl/curl/hkdf/vapid)');
        return;
    }

    $db = getDB();

    // Récupérer les souscriptions ciblées
    if ($membreId !== null) {
        $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE membre_id = ?");
        $stmt->execute([$membreId]);
    } else {
        $stmt = $db->query("
            SELECT ps.* FROM push_subscriptions ps
            INNER JOIN abonnements a ON a.membre_id = ps.membre_id
            WHERE a.actif = 1
              AND (a.type IN ('daily','rasstoss') OR a.date_fin > NOW())
        ");
    }
    $subscriptions = $stmt->fetchAll();
    if (empty($subscriptions)) {
        error_log('[Push] Aucune souscription trouvée' . ($membreId ? " pour membre #$membreId" : ' (broadcast)'));
        return;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
        'icon'  => '/assets/images/mascotte.png',
        'badge' => '/assets/images/mascotte.png',
        'tag'   => $tag,
    ], JSON_UNESCAPED_UNICODE);

    $sent    = 0;
    $failed  = 0;
    $expired = 0;

    foreach ($subscriptions as $sub) {
        if (empty($sub['endpoint']) || empty($sub['p256dh']) || empty($sub['auth'])) {
            $failed++;
            continue;
        }

        $result = _sendSinglePush($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);

        if ($result === 'ok') {
            $sent++;
        } elseif ($result === 'expired') {
            // Souscription expirée → nettoyer la BDD
            $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
               ->execute([$sub['endpoint']]);
            $expired++;
        } else {
            $failed++;
        }
    }

    error_log("[Push] Envoyé: {$sent}, Expirées/supprimées: {$expired}, Échecs: {$failed}");
}


// ═════════════════════════════════════════════════════════════
// FONCTIONS HELPER PAR ÉVÉNEMENT (identiques à l'original)
// ═════════════════════════════════════════════════════════════

/**
 * Push broadcast : nouveau bet posté
 */
function pushNouveauBet(string $typeBet, string $titre = ''): void {
    $labels = [
        'safe'     => '🛡️ Safe',
        'fun'      => '🎯 Fun',
        'live'     => '⚡ Live',
        'safe,fun' => '🛡️+🎯 Safe+Fun',
        'safe,live'=> '🛡️+⚡ Safe+Live',
        'daily'    => '⚡ Daily',
        'weekend'  => '📅 Week-End',
        'weekly'   => '🏆 Weekly',
        'rasstoss' => '👑 Rass Toss',
    ];
    $label = $labels[$typeBet] ?? $typeBet;
    envoyerPush(
        null,
        '🔥 Nouveau bet disponible !',
        $label . ($titre ? ' — ' . $titre : '') . ' vient d\'être posté',
        '/bets.php',
        'nouveau-bet'
    );
}

/**
 * Push ciblé : résultat d'un bet
 */
function pushResultatBet(int $membreId, string $typeBet, string $titre, string $resultat): void {
    $icons = ['win' => '✅', 'lose' => '❌', 'void' => '↩️'];
    $icon  = $icons[$resultat] ?? '📊';
    envoyerPush(
        $membreId,
        $icon . ' Résultat du bet',
        $titre . ' : ' . strtoupper($resultat),
        '/bets.php',
        'resultat-bet'
    );
}

/**
 * Push ciblé : nouveau message chat
 */
function pushNouveauMessage(int $membreId): void {
    envoyerPush(
        $membreId,
        '💬 Nouveau message',
        'Tu as reçu un message de StratEdge',
        '/chat.php',
        'chat-message'
    );
}

/**
 * Push ciblé : réponse à un ticket SAV
 */
function pushReponseTicket(int $membreId, string $sujet): void {
    envoyerPush(
        $membreId,
        '🎫 Réponse à ton ticket',
        'Ton ticket "' . mb_substr($sujet, 0, 50) . '" a une réponse',
        '/sav.php',
        'ticket-reponse'
    );
}

/**
 * Push ciblé : abonnement expiré
 */
function pushAbonnementExpire(int $membreId, string $type): void {
    $labels = [
        'daily'   => '⚡ Daily',
        'weekend' => '📅 Week-End',
        'weekly'  => '🏆 Weekly',
        'tennis'  => '🎾 Tennis',
    ];
    $label = $labels[$type] ?? $type;
    envoyerPush(
        $membreId,
        '⏰ Abonnement terminé',
        'Ton accès ' . $label . ' a expiré. Renouvelle pour continuer à recevoir les bets !',
        '/#pricing',
        'abo-expire'
    );
}
