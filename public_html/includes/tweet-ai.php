<?php
// ============================================================
// STRATEDGE — Génération automatique de tweets via Claude API
// Appelé quand l'admin valide un résultat (gagné/perdu/annulé)
// ============================================================

require_once __DIR__ . '/claude-config.php';

function genererTweetExplication(array $bet, string $resultat): string {
    $titre      = $bet['titre'] ?? '';
    $description = $bet['description'] ?? '';
    $analyse    = strip_tags($bet['analyse_html'] ?? '');
    $cote       = $bet['cote'] ?? '';
    $sport      = $bet['sport'] ?? 'football';
    $categorie  = $bet['categorie'] ?? 'multi';
    $type       = $bet['type'] ?? 'safe';

    $resultatLabel = ['gagne' => 'GAGNÉ', 'perdu' => 'PERDU', 'annule' => 'ANNULÉ'][$resultat] ?? $resultat;

    $prompt = <<<PROMPT
Tu es le community manager de StratEdge Pronos, un service de pronostics sportifs premium.

Le bet suivant vient d'être marqué comme {$resultatLabel}.

Infos du bet :
- Titre : {$titre}
- Sport : {$sport}
- Catégorie : {$categorie}
- Type : {$type}
- Cote : {$cote}
- Description : {$description}
- Analyse originale : {$analyse}

Génère UNIQUEMENT 2-3 phrases courtes et percutantes (max 180 caractères au total) qui expliquent POURQUOI le bet a été {$resultatLabel}.

Règles :
- Sois factuel et concis : cite le score, un joueur clé, un fait de match si possible
- Si GAGNÉ : ton positif mais pas arrogant, explique ce qui a fait la différence
- Si PERDU : ton honnête, explique ce qui n'a pas fonctionné (blessure, contre-performance, retournement...)
- Si ANNULÉ : explique brièvement la raison (match reporté, forfait, conditions météo...)
- Utilise le tutoiement, ton décontracté mais pro
- PAS d'emoji, PAS de hashtag, PAS de lien
- PAS de préambule, PAS de "Voici" — juste les phrases directement
- Écris en français

Réponds UNIQUEMENT avec les 2-3 phrases, rien d'autre.
PROMPT;

    try {
        $payload = json_encode([
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 200,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $data = json_decode($response, true);
            $text = $data['content'][0]['text'] ?? '';
            $text = trim($text);
            if ($text !== '') {
                error_log('[tweet-ai] Explication générée pour bet "' . $titre . '" (' . $resultat . ')');
                return $text;
            }
        }

        error_log('[tweet-ai] Échec API Claude (HTTP ' . $httpCode . ') pour bet "' . $titre . '"');
    } catch (\Throwable $e) {
        error_log('[tweet-ai] Erreur : ' . $e->getMessage());
    }

    return '';
}
