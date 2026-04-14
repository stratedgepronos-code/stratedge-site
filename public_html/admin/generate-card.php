<?php
// ============================================================
// STRATEDGE — generate-card.php V12
// LIVE = template PHP fixe + Claude enrichit (JSON) ← inchangé
// FUN  = template PHP fixe + Claude enrichit (JSON) ← NOUVEAU V12
// SAFE = Template PHP compact + Claude enrichit (JSON)  ← V2
// ============================================================
// Diagnostic : GET ?_ping=1 → réponse JSON sans auth (vérifier que le script est bien exécuté)
if (!empty($_GET['_ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'php' => PHP_VERSION, 'dir' => __DIR__]);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Éviter tout output avant notre JSON (warnings, etc.)
set_error_handler(function($severity, $message, $file, $line) {
    @file_put_contents(__DIR__ . '/debug-card.log',
        '[' . date('H:i:s') . '] PHP ' . $severity . ': ' . $message . ' in ' . $file . ':' . $line . "\n", FILE_APPEND);
    return true; // supprime l'affichage par défaut
});
ob_start();

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) { @ob_end_clean(); }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'error' => 'PHP Fatal: ' . $err['message'],
            'file'  => basename($err['file']),
            'line'  => $err['line'],
        ]);
        @file_put_contents(__DIR__ . '/debug-card.log',
            '[' . date('H:i:s') . '] FATAL SHUTDOWN: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] . "\n",
            FILE_APPEND);
    }
});

@set_time_limit(300);
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');

function sendJsonError($message, $code = 500, $extra = []) {
    if (ob_get_level()) ob_clean();
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(['error' => $message], $extra));
    exit;
}

try {
    $base = dirname(__DIR__);
    $authFile = $base . '/includes/auth.php';
    if (!is_readable($authFile)) {
        sendJsonError('Fichier auth introuvable.', 500, ['path' => $authFile, 'base' => $base]);
    }
    require_once $authFile;
    requireAdmin();
    if (!defined('ABSPATH')) { define('ABSPATH', true); }
    $claudeFile = $base . '/includes/claude-config.php';
    $logoFile   = $base . '/includes/logo-fallback.php';
    if (!is_readable($claudeFile)) {
        sendJsonError('Fichier claude-config introuvable.', 500, ['path' => $claudeFile]);
    }
    require_once $claudeFile;
    if (!defined('CLAUDE_API_KEY') || trim((string)CLAUDE_API_KEY) === '') {
        sendJsonError('Clé API Claude manquante ou vide. Vérifie includes/claude-config.php (CLAUDE_API_KEY).', 500);
    }
    if (!is_readable($logoFile)) {
        sendJsonError('Fichier logo-fallback introuvable.', 500, ['path' => $logoFile]);
    }
    require_once $logoFile;
} catch (Throwable $e) {
    sendJsonError('Chargement : ' . $e->getMessage(), 500, ['file' => basename($e->getFile()), 'line' => $e->getLine()]);
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

function debugLog($msg) {
    @file_put_contents(__DIR__ . '/debug-card.log', '[' . date('H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ── Tri des paris par heure (HH:MM) pour afficher le 1er match en tête + cohérence time_fr ──
function sortBetsByKickoffTime(array $bets): array {
    $indexed = [];
    foreach ($bets as $i => $bet) {
        if (!is_array($bet)) {
            continue;
        }
        $h = preg_replace('/[^0-9:]/', '', trim((string)($bet['heure'] ?? $bet['time'] ?? '')));
        if (strlen($h) >= 4) {
            $parts = explode(':', $h, 2);
            $hh = str_pad((string)(int)($parts[0] ?? 0), 2, '0', STR_PAD_LEFT);
            $mm = str_pad((string)(int)($parts[1] ?? 0), 2, '0', STR_PAD_LEFT);
            $sortKey = $hh . $mm;
        } else {
            $sortKey = '99:99';
        }
        $indexed[] = ['k' => $sortKey, 'i' => $i, 'b' => $bet];
    }
    usort($indexed, function ($a, $b) {
        $c = strcmp($a['k'], $b['k']);
        return $c !== 0 ? $c : ($a['i'] <=> $b['i']);
    });
    return array_map(function ($row) {
        return $row['b'];
    }, $indexed);
}

function syncGlobalTimeFromFirstBet(array $enriched): array {
    $bets = $enriched['bets'] ?? [];
    if (!is_array($bets) || $bets === []) {
        return $enriched;
    }
    $first = $bets[0];
    $h = preg_replace('/[^0-9:]/', '', trim((string)($first['heure'] ?? $first['time'] ?? '')));
    if (strlen($h) >= 4) {
        $enriched['time_fr'] = $h;
    }
    return $enriched;
}

// ── Appel Claude (une tentative) ────────────────────────────
function callClaudeOnce($systemPrompt, $userMsg, $maxTokens, $thinkingActive) {
    $body = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ];
    if ($thinkingActive) {
        $body['thinking'] = ['type' => 'enabled', 'budget_tokens' => 4096];
    }
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    $timeout = $thinkingActive ? 180 : 120;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $t0 = microtime(true);
    $response  = curl_exec($ch);
    $elapsed   = round(microtime(true) - $t0, 1);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    debugLog("API response — HTTP:$httpCode duration:{$elapsed}s response_size:" . strlen($response ?: ''));

    if ($curlError) {
        return ['error' => 'Erreur réseau (' . $elapsed . 's) : ' . $curlError, '_http' => 0];
    }
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $apiMsg = $err['error']['message'] ?? substr((string) $response, 0, 300);
        $apiType = $err['error']['type'] ?? '';
        return [
            'error'  => "Erreur API Claude ($httpCode, {$elapsed}s) : " . $apiMsg,
            '_http'  => $httpCode,
            '_atype' => $apiType,
        ];
    }

    $resp = json_decode($response, true);
    $text = '';
    foreach (($resp['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
    return ['text' => $text, '_http' => 200];
}

function claudeErrorIsRetryable(array $result): bool {
    $http = (int) ($result['_http'] ?? 0);
    if (in_array($http, [429, 503, 529], true)) {
        return true;
    }
    $atype = (string) ($result['_atype'] ?? '');
    if ($atype === 'overloaded_error' || stripos($atype, 'overloaded') !== false) {
        return true;
    }
    $msg = (string) ($result['error'] ?? '');
    return stripos($msg, 'Overloaded') !== false || stripos($msg, 'surcharg') !== false;
}

function emitClaudeFailureJson(array $result): void {
    $msg = (string) ($result['error'] ?? 'Erreur API Claude.');
    $http = (int) ($result['_http'] ?? 500);
    $overload = claudeErrorIsRetryable($result);
    if ($overload) {
        http_response_code(503);
        echo json_encode([
            'error'      => 'Claude (Anthropic) est momentanément saturé. Ce n’est pas un problème de clé API : réessaie dans 1 à 2 minutes.',
            'error_code' => 'claude_overloaded',
            'detail'     => $msg,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Appel Claude générique (retries si surcharge / rate limit) ─
function callClaude($systemPrompt, $userMsg, $maxTokens = 1000, $useThinking = false) {
    $thinkingActive = $useThinking && defined('CLAUDE_THINKING_ENABLED') && CLAUDE_THINKING_ENABLED && $maxTokens > 2048;
    $maxAttempts    = 4;
    $last           = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        debugLog("API call attempt $attempt/$maxAttempts — model:" . CLAUDE_MODEL . " max_tokens:$maxTokens thinking:" . ($thinkingActive ? 'ON(4096)' : 'OFF'));
        $last = callClaudeOnce($systemPrompt, $userMsg, $maxTokens, $thinkingActive);
        if (!isset($last['error'])) {
            unset($last['_http'], $last['_atype']);
            return $last;
        }
        if (!claudeErrorIsRetryable($last) || $attempt >= $maxAttempts) {
            break;
        }
        $sleep = min(20, (int) pow(2, $attempt));
        debugLog("API retry in {$sleep}s (HTTP " . ($last['_http'] ?? '?') . ')');
        sleep($sleep);
    }

    return [
        'error'  => $last['error'] ?? 'Erreur API Claude.',
        '_http'  => $last['_http'] ?? 500,
        '_atype' => $last['_atype'] ?? '',
    ];
}

// ── Parser JSON Claude (retire backticks, extrait {...}) ─────
function parseClaudeJson($text) {
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/\s*```$/m', '', $text);
    $text = trim($text);
    $data = json_decode($text, true);
    if (!$data) {
        $f = strpos($text, '{'); $l = strrpos($text, '}');
        if ($f !== false && $l > $f) $data = json_decode(substr($text, $f, $l - $f + 1), true);
    }
    return $data;
}

debugLog("=== NOUVELLE GENERATION ===");

// ── Lire POST ──────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides.']);
    exit;
}

$sport   = $data['sport']    ?? '';
$typeBet = $data['type_bet'] ?? 'Safe';

// Diagnostic POST : type_bet=_ping → 200 OK sans appeler Claude (vérifie auth + config)
if ($typeBet === '_ping') {
    debugLog("POST _ping OK");
    echo json_encode(['success' => true, 'message' => 'pong', 'php' => PHP_VERSION]);
    exit;
}

debugLog("Sport: $sport | Type: $typeBet | Model: " . CLAUDE_MODEL);

try {

// ═══════════════════════════════════════════════════════════
// MODE LIVE — Template PHP fixe + Claude enrichit les données
// ═══════════════════════════════════════════════════════════
if ($typeBet === 'Live') {
    try {
        $templatePath = __DIR__ . '/live-card-template.php';
        if (!is_readable($templatePath)) {
            throw new Exception('Template Live introuvable (admin/live-card-template.php).');
        }
        require_once $templatePath;

        if (!function_exists('generateLiveCards')) {
            throw new Exception('Fonction generateLiveCards manquante dans le template.');
        }
        if (!defined('CLAUDE_LIVE_ENRICH_PROMPT')) {
            throw new Exception('Constante CLAUDE_LIVE_ENRICH_PROMPT manquante (claude-config.php).');
        }

        $match = $data['match'] ?? '';
        $prono = $data['prono'] ?? '';
        $cote  = $data['cote']  ?? '1.50';

        // Heure par défaut (secours) : serveur Europe/Paris ou champs formulaire
        $tz = new DateTimeZone('Europe/Paris');
        $now = new DateTime('now', $tz);
        if (!empty($data['date_fr']) && !empty($data['time_fr'])) {
            $default_date_fr = trim($data['date_fr']);
            $default_time_fr = preg_replace('/[^0-9:]/', '', trim($data['time_fr']));
            if (strlen($default_time_fr) < 4) $default_time_fr = $now->format('H:i');
        } else {
            if (class_exists('IntlDateFormatter')) {
                $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN);
                $default_date_fr = ucfirst($formatter->format($now));
            } else {
                $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                $mois = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
                $default_date_fr = $jours[(int)$now->format('w')] . ' ' . $now->format('d') . ' ' . $mois[(int)$now->format('n')] . ' ' . $now->format('Y');
            }
            $default_time_fr = $now->format('H:i');
        }

        $userMsg = "Sport : $sport\nMatch : $match\nPronostic : $prono\nCote : $cote\n\nTu DOIS renvoyer date_fr et time_fr correspondant à l'heure RÉELLE du match (coup d'envoi), TOUJOURS convertie en fuseau Europe/Paris (heure française affichée aux abonnés).\n"
            . "Si le match se joue aux États-Unis (MLB, NBA, NFL, MLS, NCAA, NHL à domicile US, tennis à Indian Wells / US Open session US, etc.) : convertis l’heure locale US (souvent Eastern Time ET, parfois PT pour côte ouest) vers Paris. Ne jamais afficher l’heure « américaine » telle quelle.\n"
            . "Rappels utiles : MLB/NBA soir US → souvent nuit ou lendemain matin à Paris ; vérifier si le match passe minuit heure de Paris (date_fr = jour du coup d’envoi à Paris).\n"
            . "Si tu ne peux pas la déduire, utilise ces valeurs par défaut (secours) : date_fr = \"$default_date_fr\" , time_fr = \"$default_time_fr\".";
        debugLog("LIVE — Enrichissement via Claude...");

        $result = callClaude(CLAUDE_LIVE_ENRICH_PROMPT, $userMsg, 1000);
        if (isset($result['error'])) {
            debugLog("LIVE ENRICH ERROR: " . $result['error']);
            emitClaudeFailureJson($result);
        }

        debugLog("LIVE enrich text: " . $result['text']);
        $enriched = parseClaudeJson($result['text']);
        debugLog("Enriched: " . json_encode($enriched));

        if (!$enriched) {
            debugLog("WARN: enrichissement échoué, valeurs par défaut");
            $parts = preg_split('/\s+vs?\s+/i', $match);
            $enriched = [
                'player1'     => strtoupper(trim($parts[0] ?? 'Joueur 1')),
                'player2'     => strtoupper(trim($parts[1] ?? 'Joueur 2')),
                'flag1'       => '🏳️',
                'flag2'       => '🏳️',
                'competition' => '',
            ];
        }

        // Toujours utiliser l'heure renvoyée par Claude (l'IA s'en charge) ; secours uniquement si vide
        if (empty($enriched['date_fr']) || empty($enriched['time_fr'])) {
            $enriched['date_fr'] = $default_date_fr;
            $enriched['time_fr'] = $default_time_fr;
        } else {
            $enriched['date_fr'] = trim($enriched['date_fr']);
            $enriched['time_fr'] = preg_replace('/[^0-9:]/', '', trim($enriched['time_fr']));
            if (strlen($enriched['time_fr']) < 4) $enriched['time_fr'] = $default_time_fr;
        }

        $coteFloat  = floatval($cote);
        $confidence = ($coteFloat > 0) ? min(95, max(30, round(115 / $coteFloat))) : 60;

        $cards = generateLiveCards([
            'sport'       => $sport,
            'date_fr'     => $enriched['date_fr'],
            'time_fr'     => $enriched['time_fr'],
            'player1'     => $enriched['player1']    ?? 'JOUEUR 1',
            'player2'     => $enriched['player2']    ?? 'JOUEUR 2',
            'flag1'       => $enriched['flag1']      ?? '🏳️',
            'flag2'       => $enriched['flag2']      ?? '🏳️',
            'team1_logo'  => $enriched['team1_logo'] ?? '',
            'team2_logo'  => $enriched['team2_logo'] ?? '',
            'competition' => $enriched['competition']?? '',
            'prono'       => $prono,
            'prono_joueur'=> (int)($enriched['prono_joueur'] ?? 1),
            'cote'        => $cote,
            'confidence'  => $confidence,
        ]);

        debugLog("LIVE OK! normal=" . strlen($cards['html_normal']) . " locked=" . strlen($cards['html_locked']));
        echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Live', 'card_width' => 1080, 'cote' => $cote]);
    } catch (Throwable $e) {
        debugLog("LIVE EXCEPTION: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur génération Live : ' . $e->getMessage()]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════
// MODE FUN BET — Template PHP fixe + Claude enrichit les données
// Même principe que Live : Claude retourne JSON structuré,
// le PHP génère le HTML garanti via generateFunCards()
// ═══════════════════════════════════════════════════════════
if ($typeBet === 'Fun') {
    try {
        require_once __DIR__ . '/live-card-template.php';
    } catch (Throwable $e) {
        debugLog("FUN require ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Chargement template : ' . $e->getMessage()]);
        exit;
    }

    $rawBet = $data['raw_bet'] ?? '';

    if (empty($rawBet)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucun pari fourni pour le Fun Bet.']);
        exit;
    }

    $userMsg = "Sport : $sport\nDate du jour : " . date('d/m/Y') . "\nToutes les heures = Europe/Paris (heure française). Matchs aux USA : convertir ET/PT vers Paris, ne pas laisser d'heure US brute.\n\nListe des paris combinés :\n" . $rawBet;
    debugLog("FUN — Enrichissement via Claude...");

    $result = callClaude(CLAUDE_FUN_ENRICH_PROMPT, $userMsg, 2000);
    if (isset($result['error'])) {
        debugLog("FUN ENRICH ERROR: " . $result['error']);
        emitClaudeFailureJson($result);
    }

    debugLog("FUN enrich text: " . $result['text']);
    $enriched = parseClaudeJson($result['text']);
    debugLog("FUN Enriched: " . json_encode($enriched));

    if (!$enriched || empty($enriched['bets'])) {
        debugLog("FUN WARN: enrichissement échoué");
        http_response_code(500);
        echo json_encode(['error' => 'Claude n\'a pas pu analyser les paris. Vérifiez le format saisi.']);
        exit;
    }

    $enriched['bets'] = sortBetsByKickoffTime($enriched['bets']);
    $enriched = syncGlobalTimeFromFirstBet($enriched);

    // Recalcul cote totale côté PHP (sécurité si Claude se trompe)
    $coteTotale = 1.0;
    foreach ($enriched['bets'] as $bet) {
        $coteTotale *= floatval($bet['cote'] ?? 1.0);
    }
    $coteTotale = number_format($coteTotale, 2, '.', '');
    // Si Claude a fourni une cote_totale cohérente, on la garde
    if (isset($enriched['cote_totale']) && abs(floatval($enriched['cote_totale']) - floatval($coteTotale)) < 0.1) {
        $coteTotale = $enriched['cote_totale'];
    }

    try {
        $cards = generateFunCards([
            'sport'       => $sport,
            'date_fr'     => $enriched['date_fr']    ?? date('d/m/Y'),
            'time_fr'     => $enriched['time_fr']    ?? date('H:i'),
            'bets'        => $enriched['bets'],
            'cote_totale' => $coteTotale,
            'confidence'  => intval($enriched['confidence'] ?? 65),
        ]);
    } catch (Throwable $e) {
        debugLog("FUN generateFunCards ERROR: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur génération card : ' . $e->getMessage()]);
        exit;
    }

    debugLog("FUN OK! normal=" . strlen($cards['html_normal']) . " locked=" . strlen($cards['html_locked']));
    echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Fun', 'card_width' => 1080, 'cote' => $coteTotale]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// MODE SAFE COMBINÉ — Template PHP + Claude enrichit (comme Fun)
// ═══════════════════════════════════════════════════════════
if ($typeBet === 'Safe Combiné') {
    try {
        require_once __DIR__ . '/live-card-template.php';
    } catch (Throwable $e) {
        debugLog("SAFE_COMBI require ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Chargement template : ' . $e->getMessage()]);
        exit;
    }

    $rawBet = $data['raw_bet'] ?? '';

    if (empty($rawBet)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucun pari fourni pour le Safe Combiné.']);
        exit;
    }

    $userMsg = "Sport : $sport\nDate du jour : " . date('d/m/Y') . "\nToutes les heures = Europe/Paris. Matchs US : convertir vers Paris (ET/PT).\n\nListe des paris Safe à combiner :\n" . $rawBet;
    debugLog("SAFE_COMBI — Enrichissement via Claude...");

    $result = callClaude(CLAUDE_SAFE_COMBI_ENRICH_PROMPT, $userMsg, 2500);
    if (isset($result['error'])) {
        debugLog("SAFE_COMBI ENRICH ERROR: " . $result['error']);
        emitClaudeFailureJson($result);
    }

    debugLog("SAFE_COMBI enrich text: " . $result['text']);
    $enriched = parseClaudeJson($result['text']);
    debugLog("SAFE_COMBI Enriched: " . json_encode($enriched));

    if (!$enriched || empty($enriched['bets'])) {
        debugLog("SAFE_COMBI WARN: enrichissement échoué");
        http_response_code(500);
        echo json_encode(['error' => 'Claude n\'a pas pu analyser les paris. Vérifiez le format saisi.']);
        exit;
    }

    $enriched['bets'] = sortBetsByKickoffTime($enriched['bets']);
    $enriched = syncGlobalTimeFromFirstBet($enriched);

    $coteTotale = 1.0;
    foreach ($enriched['bets'] as $bet) {
        $coteTotale *= floatval($bet['cote'] ?? 1.0);
    }
    $coteTotale = number_format($coteTotale, 2, '.', '');
    if (isset($enriched['cote_totale']) && abs(floatval($enriched['cote_totale']) - floatval($coteTotale)) < 0.15) {
        $coteTotale = $enriched['cote_totale'];
    }

    $confGlobale = intval($enriched['confidence_globale'] ?? 65);

    try {
        $cards = generateSafeCombiCards([
            'sport'              => $sport,
            'date_fr'            => $enriched['date_fr']    ?? date('d/m/Y'),
            'time_fr'            => $enriched['time_fr']    ?? date('H:i'),
            'bets'               => $enriched['bets'],
            'cote_totale'        => $coteTotale,
            'confidence_globale' => $confGlobale,
        ]);
    } catch (Throwable $e) {
        debugLog("SAFE_COMBI generateSafeCombiCards ERROR: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur génération card : ' . $e->getMessage()]);
        exit;
    }

    debugLog("SAFE_COMBI OK! normal=" . strlen($cards['html_normal']) . " locked=" . strlen($cards['html_locked']));
    echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Safe Combiné', 'card_width' => 1440, 'cote' => $coteTotale]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// MODE SAFE — Template PHP + Claude enrichit (V2 compact)
// ═══════════════════════════════════════════════════════════
try {
    require_once __DIR__ . '/live-card-template.php';
} catch (Throwable $e) {
    debugLog("SAFE V2 require ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Chargement template : ' . $e->getMessage()]);
    exit;
}

$rawMatch = $data['match'] ?? '';
$rawProno = $data['prono'] ?? '';
$rawCote  = $data['cote']  ?? '';

if (empty($rawMatch) || empty($rawProno)) {
    http_response_code(400);
    echo json_encode(['error' => 'Match et pronostic requis.']);
    exit;
}

$tz_safe = new DateTimeZone('Europe/Paris');
$now_safe = new DateTime('now', $tz_safe);
$dateInfo = "Nous sommes le " . $now_safe->format('d/m/Y') . " à " . $now_safe->format('H:i') . " heure de Paris.\n"
    . "⚠️ Toutes les heures = Europe/Paris. Matchs US → convertir ET/PT vers Paris.\n\n";

$userMsg = $dateInfo . "Sport : $sport\nMatch : $rawMatch\nPronostic : $rawProno\nCote : $rawCote";

debugLog("SAFE V2 — Enrichissement via Claude...");
$result = callClaude(CLAUDE_SAFE_ENRICH_PROMPT, $userMsg, 2000, false);

if (isset($result['error'])) {
    debugLog("SAFE V2 ERROR: " . $result['error']);
    emitClaudeFailureJson($result);
}

debugLog("SAFE V2 enrich text: " . $result['text']);
$enriched = parseClaudeJson($result['text']);
debugLog("SAFE V2 Enriched: " . json_encode($enriched));

if (!$enriched || empty($enriched['match'])) {
    debugLog("SAFE V2 WARN: enrichissement échoué");
    http_response_code(500);
    echo json_encode(['error' => "Claude n'a pas pu analyser le match. Vérifiez le format."]);
    exit;
}

try {
    $cards = generateSafeCards([
        'sport'       => $sport,
        'date_fr'     => $enriched['date_fr']     ?? date('d/m/Y'),
        'time_fr'     => $enriched['time_fr']     ?? date('H:i'),
        'match'       => $enriched['match']       ?? $rawMatch,
        'heure'       => $enriched['heure']       ?? $enriched['time_fr'] ?? '',
        'competition' => $enriched['competition'] ?? '',
        'flag1'       => $enriched['flag1']       ?? '',
        'flag2'       => $enriched['flag2']       ?? '',
        'team1_logo'  => $enriched['team1_logo']  ?? '',
        'team2_logo'  => $enriched['team2_logo']  ?? '',
        'prono'       => $enriched['prono']       ?? $rawProno,
        'cote'        => $enriched['cote']        ?? $rawCote,
        'confidence'  => intval($enriched['confidence'] ?? 65),
        'value_pct'   => floatval($enriched['value_pct'] ?? 0),
        'analyse'     => $enriched['analyse']     ?? '',
    ]);
} catch (Throwable $e) {
    debugLog("SAFE V2 generateSafeCards ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur génération card : ' . $e->getMessage()]);
    exit;
}

debugLog("SAFE V2 OK! 1080px compact");
echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Safe', 'card_width' => 1080, 'cote' => $enriched['cote'] ?? $rawCote]);

} catch (Throwable $e) {
    debugLog("FATAL: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendJsonError('Erreur serveur : ' . $e->getMessage(), 500, ['file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
