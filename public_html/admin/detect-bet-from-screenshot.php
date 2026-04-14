<?php
// ============================================================
// STRATEDGE — Detection auto bet depuis screenshot bookmaker
// admin/detect-bet-from-screenshot.php
//
// Workflow:
// 1. Admin uploade une capture d'ecran de bookmaker (POST multipart)
// 2. On envoie l'image en base64 a Claude vision
// 3. Claude renvoie un JSON structure: matchs[], cote_totale, type
// 4. Frontend remplit le formulaire avec les donnees
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

// --- Recuperation cle Claude ---
$configFile = __DIR__ . '/../config-keys.php';
if (file_exists($configFile)) { require_once $configFile; }
$ANTHROPIC_KEY = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : null;
if (!$ANTHROPIC_KEY) {
    echo json_encode(['success' => false, 'error' => 'Cle Claude non configuree.']);
    exit;
}

// --- CSRF ---
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide.']);
    exit;
}

// --- Validation upload ---
if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Aucune image uploadee.']);
    exit;
}
$tmpFile = $_FILES['screenshot']['tmp_name'];
$mimeType = mime_content_type($tmpFile);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['success' => false, 'error' => 'Format non supporte (JPEG/PNG/WebP/GIF).']);
    exit;
}
$sizeMb = filesize($tmpFile) / 1024 / 1024;
if ($sizeMb > 5) {
    echo json_encode(['success' => false, 'error' => 'Image trop lourde (max 5MB).']);
    exit;
}

// --- Encodage base64 pour Claude API ---
$imageData = base64_encode(file_get_contents($tmpFile));

// --- Construction du prompt vision ---
$systemPrompt = <<<TXT
Tu es un assistant specialise dans l'analyse de captures d'ecran de paris sportifs (Winamax, Betclic, Unibet, Stake, PMU, Parions Sport, etc.).

Ta mission: extraire de l'image les informations du/des paris affiches et renvoyer un JSON strict.

REGLES:
1. Reponds UNIQUEMENT en JSON valide, sans aucun texte avant ou apres
2. Pas de balises markdown (pas de ```json)
3. Si plusieurs paris sur l'image = combine -> liste tous les matchs dans "matchs"
4. Cote totale = produit des cotes individuelles (calcule-la si non visible)
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

Si l'image n'est PAS une capture de pari, renvoie:
{"success": false, "error": "Pas une capture de pari sportif"}

Champs optionnels: "competition", "date", "bookmaker_detecte" (mets null si invisible).
TXT;

$userPrompt = "Voici une capture d'ecran. Analyse-la et renvoie le JSON structure du/des paris detectes.";

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
                    'text' => $userPrompt,
                ]
            ]
        ]
    ],
];

// --- Appel API ---
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
    echo json_encode(['success' => false, 'error' => 'Erreur reseau: ' . $curlErr]);
    exit;
}
if ($httpCode !== 200) {
    error_log('[detect-bet] HTTP ' . $httpCode . ' response: ' . substr($response, 0, 500));
    echo json_encode(['success' => false, 'error' => 'Claude API HTTP ' . $httpCode]);
    exit;
}

$apiData = json_decode($response, true);
$claudeText = $apiData['content'][0]['text'] ?? '';
if (!$claudeText) {
    echo json_encode(['success' => false, 'error' => 'Reponse Claude vide.']);
    exit;
}

// Nettoyer la reponse Claude (parfois entoure de markdown malgre l'instruction)
$claudeText = preg_replace('/^```json\s*|\s*```$/m', '', trim($claudeText));
$claudeText = preg_replace('/^```\s*|\s*```$/m', '', trim($claudeText));

$detected = json_decode($claudeText, true);
if (!is_array($detected)) {
    error_log('[detect-bet] JSON invalide de Claude: ' . substr($claudeText, 0, 500));
    echo json_encode(['success' => false, 'error' => 'Detection echouee, reessaie avec une image plus claire.']);
    exit;
}

// On retourne directement la reponse de Claude (success/error inclus)
echo json_encode($detected);
