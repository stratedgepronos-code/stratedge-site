<?php
// ============================================================
// STRATEDGE — generate-card.php V12
// LIVE = template PHP fixe + Claude enrichit (JSON) ← inchangé
// FUN  = template PHP fixe + Claude enrichit (JSON) ← NOUVEAU V12
// SAFE = Claude génère le HTML complet              ← inchangé
// ============================================================

@set_time_limit(300);
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if (!defined('ABSPATH')) { define('ABSPATH', true); }
require_once __DIR__ . '/../includes/claude-config.php';
require_once __DIR__ . '/../includes/logo-fallback.php';

header('Content-Type: application/json; charset=utf-8');

function debugLog($msg) {
    @file_put_contents(__DIR__ . '/debug-card.log', '[' . date('H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ── Appel Claude générique ──────────────────────────────────
function callClaude($systemPrompt, $userMsg, $maxTokens = 1000, $useThinking = false) {
    $body = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ];
    $thinkingActive = $useThinking && defined('CLAUDE_THINKING_ENABLED') && CLAUDE_THINKING_ENABLED && $maxTokens > 2048;
    if ($thinkingActive) {
        $body['thinking'] = ['type' => 'enabled', 'budget_tokens' => 4096];
    }
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    debugLog("API call — model:" . CLAUDE_MODEL . " max_tokens:$maxTokens thinking:" . ($thinkingActive ? 'ON(4096)' : 'OFF') . " payload_size:" . strlen($payload));

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
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    debugLog("API response — HTTP:$httpCode duration:{$elapsed}s response_size:" . strlen($response ?: ''));

    if ($curlError) return ['error' => 'Erreur réseau (' . $elapsed . 's) : ' . $curlError];
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        return ['error' => "Erreur API Claude ($httpCode, {$elapsed}s) : " . ($err['error']['message'] ?? substr($response, 0, 300))];
    }

    $resp = json_decode($response, true);
    $text = '';
    foreach (($resp['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    return ['text' => $text];
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

debugLog("Sport: $sport | Type: $typeBet | Model: " . CLAUDE_MODEL);

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

        // Date et heure fiables : serveur Europe/Paris ou champs envoyés par le formulaire
        $tz = new DateTimeZone('Europe/Paris');
        $now = new DateTime('now', $tz);
        if (!empty($data['date_fr']) && !empty($data['time_fr'])) {
            $date_fr = trim($data['date_fr']);
            $time_fr = preg_replace('/[^0-9:]/', '', trim($data['time_fr']));
            if (strlen($time_fr) < 4) $time_fr = $now->format('H:i');
        } else {
            if (class_exists('IntlDateFormatter')) {
                $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN);
                $date_fr = ucfirst($formatter->format($now));
            } else {
                $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                $mois = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
                $date_fr = $jours[(int)$now->format('w')] . ' ' . $now->format('d') . ' ' . $mois[(int)$now->format('n')] . ' ' . $now->format('Y');
            }
            $time_fr = $now->format('H:i');
        }

        $userMsg = "Sport : $sport\nMatch : $match\nPronostic : $prono\nCote : $cote\n\nDate et heure à utiliser telles quelles : date_fr = \"$date_fr\" , time_fr = \"$time_fr\" (fuseau Europe/Paris).";
        debugLog("LIVE — Enrichissement via Claude...");

        $result = callClaude(CLAUDE_LIVE_ENRICH_PROMPT, $userMsg, 1000);
        if (isset($result['error'])) {
            debugLog("LIVE ENRICH ERROR: " . $result['error']);
            http_response_code(500);
            echo json_encode(['error' => $result['error']]);
            exit;
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

        // Toujours utiliser nos date/heure (pas celles de Claude)
        $enriched['date_fr'] = $date_fr;
        $enriched['time_fr'] = $time_fr;

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
        echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Live', 'card_width' => 1440]);
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

    $userMsg = "Sport : $sport\nDate du jour : " . date('d/m/Y') . "\nHeure = fuseau Europe/Paris\n\nListe des paris combinés :\n" . $rawBet;
    debugLog("FUN — Enrichissement via Claude...");

    $result = callClaude(CLAUDE_FUN_ENRICH_PROMPT, $userMsg, 2000);
    if (isset($result['error'])) {
        debugLog("FUN ENRICH ERROR: " . $result['error']);
        http_response_code(500);
        echo json_encode(['error' => $result['error']]);
        exit;
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
    echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Fun', 'card_width' => 1080]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// MODE SAFE — Claude génère le HTML complet (inchangé)
// ═══════════════════════════════════════════════════════════
$systemPrompt = CLAUDE_CARD_PROMPT;
$dateInfo = "Nous sommes le " . date('d/m/Y') . " (saison " . date('Y') . "/" . (date('Y')+1) . " en football, saison " . date('Y') . " en tennis). Heure = fuseau Europe/Paris.\n\n";

$betData = json_encode([
    'sport'    => $sport,
    'match'    => $data['match'] ?? '',
    'type_bet' => 'Safe',
    'prono'    => $data['prono'] ?? '',
    'cote'     => $data['cote']  ?? '',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$userMessage = $dateInfo . "Utilise les stats de carrière ET les stats de la saison en cours. Ne mets pas de stats futures ou inventées.\n\nGénère une card de bet StratEdge avec ces données :\n\n" . $betData;

debugLog("SAFE — Appel Claude HTML...");
$result = callClaude($systemPrompt, $userMessage, 16000, true);

if (isset($result['error'])) {
    debugLog("SAFE ERROR: " . $result['error']);
    http_response_code(500);
    echo json_encode(['error' => $result['error']]);
    exit;
}

$content = $result['text'];
$content = preg_replace('/^```(?:json)?\s*/m', '', $content);
$content = preg_replace('/\s*```$/m', '', $content);
$content = trim($content);
$content = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $content);

$cards = json_decode($content, true);
if (!$cards || !isset($cards['html_normal'])) {
    $f = strpos($content, '{'); $l = strrpos($content, '}');
    if ($f !== false && $l > $f) $cards = json_decode(substr($content, $f, $l - $f + 1), true);
}

if (!$cards || !isset($cards['html_normal']) || !isset($cards['html_locked'])) {
    debugLog("SAFE ECHEC JSON: " . json_last_error_msg());
    http_response_code(500);
    echo json_encode(['error' => 'Format JSON invalide. Réessayez.', 'raw' => substr($content, 0, 500)]);
    exit;
}

debugLog("SAFE OK! 1440px");
echo json_encode(['success' => true, 'html_normal' => $cards['html_normal'], 'html_locked' => $cards['html_locked'], 'type_bet' => 'Safe', 'card_width' => 1440]);
