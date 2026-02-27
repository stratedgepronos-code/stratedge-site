<?php
// ============================================================
// STRATEDGE — generate-card.php — Endpoint AJAX Claude API
// V8 — Routage Safe / Live / Fun (prompt Live fusionné)
// POST /admin/generate-card.php
// ============================================================

// Sécurité : admin seulement
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Config Claude (3 prompts)
if (!defined('ABSPATH')) { define('ABSPATH', true); }
require_once __DIR__ . '/../includes/claude-config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Lire les données POST ──────────────────────────────────
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides.']);
    exit;
}

$sport   = $data['sport']    ?? '';
$typeBet = $data['type_bet'] ?? 'Safe';

// ── Choisir le bon prompt selon le type ────────────────────
// Safe  → CLAUDE_CARD_PROMPT (analyse détaillée, 1080px)
// Live  → CLAUDE_LIVE_PROMPT (1 match, 720px, mascotte latérale)
// Fun   → CLAUDE_FUN_PROMPT  (combiné multi-matchs, 760px, mascotte latérale)
switch ($typeBet) {
    case 'Live':
        $systemPrompt = CLAUDE_LIVE_PROMPT;
        break;
    case 'Fun':
        $systemPrompt = CLAUDE_FUN_PROMPT;
        break;
    default: // Safe
        $systemPrompt = CLAUDE_CARD_PROMPT;
        break;
}

// ── Construire le message utilisateur ──────────────────────
$dateInfo = "Nous sommes le " . date('d/m/Y') . " (saison " . date('Y') . "/" . (date('Y')+1) . " en football, saison " . date('Y') . " en tennis). Heure = fuseau Europe/Paris.\n\n";

if ($typeBet === 'Safe') {
    // Mode Safe : champs structurés (match, prono, cote) + analyse détaillée
    $betData = json_encode([
        'sport'    => $sport,
        'match'    => $data['match']    ?? '',
        'type_bet' => 'Safe',
        'prono'    => $data['prono']    ?? '',
        'cote'     => $data['cote']     ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $userMessage = $dateInfo . "Utilise les stats de carrière ET les stats de la saison en cours. Ne mets pas de stats futures ou inventées.\n\nGénère une card de bet StratEdge avec ces données :\n\n" . $betData;

} elseif ($typeBet === 'Live') {
    // Mode Live : match + prono + cote → Claude trouve les infos du match
    $match = $data['match'] ?? '';
    $prono = $data['prono'] ?? '';
    $cote  = $data['cote']  ?? '';

    $userMessage = $dateInfo . "Sport : " . $sport . "\nType : LIVE BET\n\nMatch : " . $match . "\nPronostic : " . $prono . "\nCote : " . $cote . "\n\nTrouve les infos du match (compétition, drapeaux, date, heure Europe/Paris). Génère la card.";

} else {
    // Mode Fun : textarea brute (multi-matchs + pronos + cotes) → Claude trouve les infos
    $rawBet = $data['raw_bet'] ?? '';
    $userMessage = $dateInfo . "Sport : " . $sport . "\nType : FUN BET (combiné multi-matchs)\n\n" . $rawBet . "\n\nTrouve les infos de chaque match (compétition, drapeaux, dates, heures Europe/Paris). Calcule la cote totale. Génère la card.";
}

// ── Appel API Claude ───────────────────────────────────────
$apiPayload = [
    'model'      => CLAUDE_MODEL,
    'max_tokens' => 16000,
    'system'     => $systemPrompt,
    'messages'   => [
        ['role' => 'user', 'content' => $userMessage]
    ]
];

// Adaptive Thinking pour Sonnet 4.6
// Désactivé temporairement pour debug — décommenter une fois que la base fonctionne
// if (defined('CLAUDE_THINKING_ENABLED') && CLAUDE_THINKING_ENABLED) {
//     $apiPayload['thinking'] = ['type' => 'adaptive'];
// }

$payload = json_encode($apiPayload, JSON_UNESCAPED_UNICODE);

// Log pour debug (supprimer en prod)
error_log('STRATEDGE CARD — Model: ' . CLAUDE_MODEL . ' | Type: ' . $typeBet . ' | Payload size: ' . strlen($payload));

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ── Gestion erreurs réseau ─────────────────────────────────
if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur réseau : ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    $errBody = json_decode($response, true);
    $errMsg = $errBody['error']['message'] ?? '';
    $errType = $errBody['error']['type'] ?? '';
    echo json_encode([
        'error' => 'Erreur API Claude (' . $httpCode . ') ' . $errType . ' : ' . ($errMsg ?: substr($response, 0, 500))
    ]);
    exit;
}

// ── Parser la réponse Claude ───────────────────────────────
$claudeResponse = json_decode($response, true);

// Avec Extended Thinking, la réponse contient des blocs thinking + text
// On cherche le premier bloc de type "text"
$content = '';
if (isset($claudeResponse['content']) && is_array($claudeResponse['content'])) {
    foreach ($claudeResponse['content'] as $block) {
        if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
            $content = $block['text'];
            break;
        }
    }
}

// Fallback si pas de bloc text trouvé
if (empty($content) && isset($claudeResponse['content'][0]['text'])) {
    $content = $claudeResponse['content'][0]['text'];
}

// Nettoyer éventuels backticks markdown
$content = preg_replace('/^```(?:json)?\s*/m', '', $content);
$content = preg_replace('/\s*```$/m', '', $content);
$content = trim($content);

// FIX : supprimer les caractères de contrôle (cause json_error 3)
$content = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $content);

// Parser le JSON retourné par Claude
$cards = json_decode($content, true);

// FIX 2 : si json_decode échoue, tenter d'extraire le JSON manuellement
if (!$cards || !isset($cards['html_normal']) || !isset($cards['html_locked'])) {
    if (preg_match('/\{.*"html_normal"\s*:\s*".*"html_locked"\s*:\s*".*\}/s', $content, $match)) {
        $cards = json_decode($match[0], true);
    }
}

if (!$cards || !isset($cards['html_normal']) || !isset($cards['html_locked'])) {
    http_response_code(500);
    $jsonErr = json_last_error_msg();
    echo json_encode([
        'error' => 'Claude n\'a pas retourné le format attendu (JSON: ' . $jsonErr . '). Réponse brute :',
        'raw'   => substr($content, 0, 500)
    ]);
    exit;
}

// ── Succès ─────────────────────────────────────────────────
// On renvoie aussi le type et la largeur pour le frontend
$cardWidth = ($typeBet === 'Live') ? 720 : (($typeBet === 'Fun') ? 760 : 1080);

echo json_encode([
    'success'     => true,
    'html_normal' => $cards['html_normal'],
    'html_locked' => $cards['html_locked'],
    'type_bet'    => $typeBet,
    'card_width'  => $cardWidth,
]);
