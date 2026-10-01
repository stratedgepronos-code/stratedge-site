<?php
// ============================================================
// STRATEDGE — Scanner image pour extraction auto de bet
// admin/scanner-img.php
//
// Workflow:
// 1. Admin uploade une image (POST multipart)
// 2. On envoie l'image en base64 a Claude vision
// 3. Claude renvoie un JSON structure: matchs[], cote_totale, type
// 4. Frontend remplit le formulaire avec les donnees
// ============================================================

ob_start();

header('Content-Type: application/json; charset=utf-8');

// Helper pour quitter proprement avec JSON (toujours)
function jsonExit(array $data, int $http = 200): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Capturer toute erreur PHP et la transformer en JSON
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    error_log("[scanner-img] PHP error: $message in $file:$line");
    jsonExit(['success' => false, 'error' => "PHP error: $message"], 500);
});
set_exception_handler(function ($e) {
    error_log('[scanner-img] Exception: ' . $e->getMessage());
    jsonExit(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], 500);
});

try {
    require_once __DIR__ . '/../includes/auth.php';
} catch (Throwable $e) {
    jsonExit(['success' => false, 'error' => 'Auth load failed: ' . $e->getMessage()], 500);
}

// Verif admin SANS redirect
if (!function_exists('isAdmin') || !isAdmin()) {
    jsonExit(['success' => false, 'error' => 'Acces refuse: admin requis. Reconnecte-toi.'], 403);
}

// Verif methode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(['success' => false, 'error' => 'POST requis.'], 405);
}

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!$csrf || (function_exists('verifyCsrf') && !verifyCsrf($csrf))) {
    jsonExit(['success' => false, 'error' => 'CSRF invalide. Recharge la page.'], 403);
}

// Cle Claude
$configFile = __DIR__ . '/../config-keys.php';
if (file_exists($configFile)) { require_once $configFile; }
$ANTHROPIC_KEY = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : null;
if (!$ANTHROPIC_KEY) {
    jsonExit(['success' => false, 'error' => 'Cle Claude non configuree.'], 500);
}

// Validation upload
if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    jsonExit(['success' => false, 'error' => 'Aucune image uploadee.'], 400);
}
$tmpFile = $_FILES['screenshot']['tmp_name'];
$mimeType = mime_content_type($tmpFile);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedMimes)) {
    jsonExit(['success' => false, 'error' => 'Format non supporte (JPEG/PNG/WebP/GIF).'], 400);
}
$sizeMb = filesize($tmpFile) / 1024 / 1024;
if ($sizeMb > 5) {
    jsonExit(['success' => false, 'error' => 'Image trop lourde (max 5MB).'], 400);
}

// Base64
$imageData = base64_encode(file_get_contents($tmpFile));

// Prompt vision
$systemPrompt = <<<TXT
Tu es un assistant specialise dans l'analyse de captures d'ecran de paris sportifs (Winamax, Betclic, Unibet, Stake, PMU, Parions Sport, etc.).

Ta mission: extraire de l'image les informations du/des paris affiches et renvoyer un JSON strict.

REGLES:
1. Reponds UNIQUEMENT en JSON valide, sans aucun texte avant ou apres
2. Pas de balises markdown (pas de \`\`\`json)
3. Si plusieurs paris sur l'image = combine -> liste tous les matchs dans "matchs"
4. Cote totale = produit des cotes individuelles
5. Sport: 'football', 'tennis', 'basket', 'hockey', 'baseball' uniquement
6. Type suggere: 'safe' (cote totale <2.5), 'live' (si tag "Live" visible), 'fun' (cote totale >5), 'safecombi' (combine de safe)

FORMAT JSON ATTENDU:
{
  "success": true,
  "matchs": [
    {
      "equipes": "PSG vs Marseille",
      "marche": "Vainqueur PSG",
      "cote": 1.85,
      "sport": "football",
      "competition": "Ligue 1",
      "date": "15/04/2026 21:00"
    }
  ],
  "cote_totale": 1.85,
  "type_suggere": "safe",
  "is_combine": false,
  "bookmaker_detecte": "Winamax"
}

Si l'image n'est PAS une capture de pari sportif, renvoie:
{"success": false, "error": "Pas une capture de pari sportif"}

Champs optionnels: "competition", "date", "bookmaker_detecte" (mets null si invisible).
TXT;

$payload = [
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1500,
    'system' => $systemPrompt,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mimeType,
                        'data' => $imageData,
                    ]
                ],
                [
                    'type' => 'text',
                    'text' => "Analyse cette capture d'ecran et renvoie le JSON structure du/des paris.",
                ]
            ]
        ]
    ],
];

// Appel API
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $ANTHROPIC_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 60,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    jsonExit(['success' => false, 'error' => 'Erreur reseau Claude: ' . $curlErr], 500);
}
if ($httpCode !== 200) {
    error_log('[scanner-img] Claude HTTP ' . $httpCode . ': ' . substr($response, 0, 500));
    jsonExit(['success' => false, 'error' => 'Claude API HTTP ' . $httpCode], 500);
}

$apiData = json_decode($response, true);
$claudeText = $apiData['content'][0]['text'] ?? '';
if (!$claudeText) {
    jsonExit(['success' => false, 'error' => 'Reponse Claude vide.'], 500);
}

// Nettoyer la reponse (parfois entoure de markdown)
$claudeText = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($claudeText));

$detected = json_decode($claudeText, true);
if (!is_array($detected)) {
    error_log('[scanner-img] JSON invalide: ' . substr($claudeText, 0, 500));
    jsonExit(['success' => false, 'error' => 'Detection echouee, reessaie avec une image plus claire.'], 500);
}

jsonExit($detected);
