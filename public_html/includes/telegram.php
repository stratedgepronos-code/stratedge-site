<?php
// ============================================================
// STRATEDGE — Module Telegram (envoi vers canal public)
// ============================================================
// Charge la config locale (token + chat_id) qui DOIT être présente
// dans /includes/telegram-config.local.php (non versionné, voir .example).
// ============================================================

$_telegramConfigLocal = __DIR__ . '/telegram-config.local.php';
if (is_file($_telegramConfigLocal)) {
    require_once $_telegramConfigLocal;
}

/**
 * Indique si le module est configuré (token + channel_id présents).
 */
function telegramIsConfigured(): bool {
    return defined('TELEGRAM_BOT_TOKEN')
        && defined('TELEGRAM_CHANNEL_ID')
        && TELEGRAM_BOT_TOKEN !== ''
        && TELEGRAM_BOT_TOKEN !== 'TON_TOKEN_ICI'
        && TELEGRAM_CHANNEL_ID !== '';
}

/**
 * Logue les évènements Telegram (succès/erreurs) dans /logs/telegram-log.txt
 */
function telegramLog(string $level, string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message . "\n";
    @file_put_contents(__DIR__ . '/../logs/telegram-log.txt', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Envoi bas-niveau d'un message texte au canal.
 *
 * @param string $text Le message (max 4096 chars, HTML supporté avec parse_mode)
 * @param array  $options ['parse_mode' => 'HTML'|'MarkdownV2'|null, 'disable_preview' => bool]
 * @return bool true si envoyé OK
 */
function telegramSend(string $text, array $options = []): bool {
    if (!telegramIsConfigured()) {
        telegramLog('WARN', 'telegramSend: not configured, skipping');
        return false;
    }
    if ($text === '') return false;
    if (mb_strlen($text) > 4096) $text = mb_substr($text, 0, 4093) . '...';

    $payload = [
        'chat_id'                   => TELEGRAM_CHANNEL_ID,
        'text'                      => $text,
        'parse_mode'                => $options['parse_mode'] ?? 'HTML',
        'disable_web_page_preview'  => !empty($options['disable_preview']),
    ];

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        telegramLog('OK', 'send ' . mb_strlen($text) . ' chars');
        return true;
    }
    telegramLog('ERR', 'send HTTP ' . $httpCode . ' — ' . substr((string)$response, 0, 300));
    return false;
}

/**
 * Envoi d'une photo avec caption optionnelle.
 *
 * @param string $photo Chemin local OU URL HTTPS de l'image
 * @param string $caption Texte sous l'image (max 1024 chars, HTML supporté)
 * @param array  $options
 * @return bool
 */
function telegramSendPhoto(string $photo, string $caption = '', array $options = []): bool {
    if (!telegramIsConfigured()) {
        telegramLog('WARN', 'telegramSendPhoto: not configured, skipping');
        return false;
    }
    if ($photo === '') return false;
    if (mb_strlen($caption) > 1024) $caption = mb_substr($caption, 0, 1021) . '...';

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendPhoto';

    $isUrl = filter_var($photo, FILTER_VALIDATE_URL) !== false;
    $isFile = !$isUrl && is_file($photo);

    if (!$isUrl && !$isFile) {
        telegramLog('ERR', 'sendPhoto: photo not found: ' . substr($photo, 0, 200));
        return false;
    }

    $payload = [
        'chat_id'    => TELEGRAM_CHANNEL_ID,
        'caption'    => $caption,
        'parse_mode' => $options['parse_mode'] ?? 'HTML',
    ];

    $ch = curl_init($url);
    if ($isUrl) {
        $payload['photo'] = $photo;
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
    } else {
        // Multipart upload pour fichier local
        $payload['photo'] = new CURLFile($photo);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
    }

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        telegramLog('OK', 'sendPhoto ' . ($isUrl ? 'URL' : 'FILE') . ' caption=' . mb_strlen($caption));
        return true;
    }
    telegramLog('ERR', 'sendPhoto HTTP ' . $httpCode . ' — ' . substr((string)$response, 0, 300));
    return false;
}

// ============================================================
// HELPERS HAUT-NIVEAU pour les annonces StratEdge
// ============================================================

/**
 * Annonce un nouveau bet sur le canal Telegram.
 *
 * @param string $categorie 'multi' | 'tennis' | 'fun'
 * @param string $betType 'safe' | 'live' | 'fun' | 'safe,fun' etc.
 * @param string $betTitre Titre du match (ex: "PSG vs Bayern · 21h00")
 * @param string|null $imagePath URL ou chemin local de la card locked à joindre (optionnel)
 */
function telegramAnnonceNouveauBet(string $categorie, string $betType, string $betTitre, ?string $imagePath = null): bool {
    if (!telegramIsConfigured()) return false;

    // Emoji catégorie
    $catEmoji = [
        'multi'  => '⚽',
        'tennis' => '🎾',
        'fun'    => '🎲',
    ][$categorie] ?? '🎯';

    // Label type
    $typeLabels = [
        'safe'      => '🛡️ Safe',
        'live'      => '⚡ Live',
        'fun'       => '🎯 Fun',
        'safe,fun'  => '🛡️🎯 Safe + Fun',
        'safe,live' => '🛡️⚡ Safe + Live',
    ];
    $typeLabel = $typeLabels[$betType] ?? ucfirst($betType);

    // Header
    $catLabel = [
        'multi'  => 'MULTI',
        'tennis' => 'TENNIS',
        'fun'    => 'FUN',
    ][$categorie] ?? strtoupper($categorie);

    $titreSafe = htmlspecialchars($betTitre, ENT_QUOTES, 'UTF-8');

    // Message
    $msg  = "🔥 <b>NOUVEAU BET " . htmlspecialchars($catLabel) . " " . $typeLabel . "</b>\n\n";
    if ($betTitre !== '') {
        $msg .= $catEmoji . " <b>" . $titreSafe . "</b>\n\n";
    }
    $msg .= "📲 Analyse complète sur <a href=\"https://stratedgepronos.fr/bets.php\">stratedgepronos.fr</a>\n\n";
    $msg .= "#StratEdge #" . ucfirst($categorie) . " #" . ucfirst(str_replace(',', '', $betType));

    // Si on a une image (card locked), on envoie photo + caption
    if ($imagePath) {
        return telegramSendPhoto($imagePath, $msg);
    }
    return telegramSend($msg);
}

/**
 * Annonce le résultat d'un bet (gagne / perdu / annule).
 *
 * @param string $titrebet Titre du match
 * @param string $resultat 'gagne' | 'perdu' | 'annule' | 'nul' | 'win' | 'lose'
 * @param string $typeBet 'safe' | 'live' | 'fun' | 'tennis' etc.
 */
function telegramAnnonceResultatBet(string $titrebet, string $resultat, string $typeBet = ''): bool {
    if (!telegramIsConfigured()) return false;

    $r = strtolower($resultat);
    $emojiMap = [
        'gagne'  => '✅', 'win'   => '✅',
        'perdu'  => '❌', 'lose'  => '❌',
        'annule' => '↺', 'nul'   => '↺', 'void' => '↺',
    ];
    $labelMap = [
        'gagne' => 'BET GAGNÉ', 'win' => 'BET GAGNÉ',
        'perdu' => 'BET PERDU', 'lose' => 'BET PERDU',
        'annule' => 'BET ANNULÉ', 'nul' => 'BET NUL', 'void' => 'BET ANNULÉ',
    ];
    $emoji = $emojiMap[$r] ?? '📊';
    $label = $labelMap[$r] ?? 'RÉSULTAT';

    $titreSafe = htmlspecialchars($titrebet, ENT_QUOTES, 'UTF-8');
    $typeSafe  = htmlspecialchars($typeBet, ENT_QUOTES, 'UTF-8');

    $msg  = $emoji . " <b>" . $label . "</b>\n\n";
    if ($titrebet !== '') {
        $msg .= "🎯 " . $titreSafe . "\n";
    }
    if ($typeBet !== '') {
        $msg .= "📋 " . $typeSafe . "\n";
    }
    $msg .= "\n📲 <a href=\"https://stratedgepronos.fr/bets.php\">stratedgepronos.fr</a>\n\n";
    $msg .= "#StratEdge #Bilan";

    return telegramSend($msg);
}

/**
 * Annonce le démarrage d'une nouvelle montante.
 *
 * @param array $config Config montante (nom, bankroll_initial, etc.)
 * @param string $context 'tennis' | 'multi-sport'
 */
function telegramAnnonceMontanteDemarrage(array $config, string $context = 'tennis'): bool {
    if (!telegramIsConfigured()) return false;

    $isTennis = ($context === 'tennis');
    $emoji = $isTennis ? '🎾' : '⚽';
    $label = $isTennis ? 'MONTANTE TENNIS' : 'MONTANTE MULTI-SPORT';
    $url   = $isTennis
        ? 'https://stratedgepronos.fr/montante-tennis.php'
        : 'https://stratedgepronos.fr/montante-foot.php';

    $bankroll = number_format((float)($config['bankroll_initial'] ?? 100), 2, ',', ' ');
    $nom      = htmlspecialchars((string)($config['nom'] ?? ''), ENT_QUOTES, 'UTF-8');

    $msg  = "🚀 <b>NOUVELLE " . $label . "</b>\n\n";
    if ($nom !== '') {
        $msg .= $emoji . " " . $nom . "\n";
    }
    $msg .= "💰 Bankroll initial : <b>" . $bankroll . " €</b>\n\n";
    $msg .= "Étape 1 arrive bientôt — reste connecté.\n\n";
    $msg .= "📲 <a href=\"" . $url . "\">Suivre la montante</a>\n\n";
    $msg .= "#StratEdge #Montante" . ($isTennis ? 'Tennis' : 'MultiSport');

    return telegramSend($msg);
}

/**
 * Annonce une nouvelle étape de montante.
 */
function telegramAnnonceMontanteNouvelleEtape(array $step, array $config, string $context = 'tennis'): bool {
    if (!telegramIsConfigured()) return false;

    $isTennis = ($context === 'tennis');
    $emoji = $isTennis ? '🎾' : '⚽';
    $label = $isTennis ? 'Montante Tennis' : 'Montante Multi-sport';
    $url   = $isTennis
        ? 'https://stratedgepronos.fr/montante-tennis.php'
        : 'https://stratedgepronos.fr/montante-foot.php';

    $match   = htmlspecialchars((string)($step['match_desc'] ?? 'Nouveau prono'), ENT_QUOTES, 'UTF-8');
    $stepNum = (int)($step['step_number'] ?? 1);

    $msg  = "⚡ <b>STEP " . $stepNum . " — " . $label . "</b>\n\n";
    $msg .= $emoji . " " . $match . "\n\n";
    $msg .= "📲 <a href=\"" . $url . "\">Voir le détail du pick</a>\n\n";
    $msg .= "#StratEdge #Montante" . ($isTennis ? 'Tennis' : 'MultiSport');

    return telegramSend($msg);
}

/**
 * Annonce le résultat d'une étape de montante.
 */
function telegramAnnonceMontanteResultat(array $step, array $config, string $resultat, string $context = 'tennis'): bool {
    if (!telegramIsConfigured()) return false;

    $isTennis = ($context === 'tennis');
    $label = $isTennis ? 'Montante Tennis' : 'Montante Multi-sport';
    $url   = $isTennis
        ? 'https://stratedgepronos.fr/montante-tennis.php'
        : 'https://stratedgepronos.fr/montante-foot.php';

    $icons  = ['gagne' => '✅', 'perdu' => '❌', 'annule' => '↺'];
    $labels = ['gagne' => 'GAGNÉ', 'perdu' => 'PERDU', 'annule' => 'ANNULÉ'];
    $icon   = $icons[$resultat]  ?? '📊';
    $rlabel = $labels[$resultat] ?? strtoupper($resultat);

    $match   = htmlspecialchars((string)($step['match_desc'] ?? 'Prono'), ENT_QUOTES, 'UTF-8');
    $stepNum = (int)($step['step_number'] ?? 0);

    $msg  = $icon . " <b>STEP " . $stepNum . " — " . $rlabel . "</b>\n\n";
    $msg .= "🎯 " . $match . "\n";
    $msg .= "📋 " . $label . "\n\n";
    $msg .= "📲 <a href=\"" . $url . "\">Voir la montante</a>\n\n";
    $msg .= "#StratEdge #Bilan";

    return telegramSend($msg);
}
