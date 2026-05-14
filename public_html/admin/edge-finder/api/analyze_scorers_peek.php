<?php
/**
 * STRATEDGE - Edge Finder - Peek cache analyse buteurs
 * =====================================================
 * GET /panel-x9k3m/edge-finder/api/analyze_scorers_peek.php?match_id=N
 *
 * Endpoint leger : retourne uniquement le cache d'analyse buteur si existant.
 * Ne declenche PAS Claude. Utilise par le frontend au chargement de page
 * pour afficher direct l'analyse si on a deja paye.
 *
 * Reponse :
 *   200 OK + JSON {cached: true, scorers: [...], ...}  si cache
 *   404 + JSON {cached: false}                          si pas de cache
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

requireSuperAdmin();

header('Content-Type: application/json; charset=utf-8');

$match_id = (int)($_GET['match_id'] ?? 0);
if ($match_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'match_id required']);
    exit;
}

$cached = SE_Db::queryOne(
    "SELECT * FROM match_scorer_analysis WHERE match_id = ?",
    [$match_id]
);

if (!$cached) {
    http_response_code(404);
    echo json_encode(['cached' => false]);
    exit;
}

echo json_encode([
    'cached' => true,
    'generated_at' => $cached['generated_at'],
    'scorers' => json_decode($cached['scorers_json'], true),
    'warnings' => json_decode($cached['warnings_json'] ?? '[]', true),
    'freshness_note' => $cached['freshness_note'],
    'markdown_full' => $cached['markdown_full'],
    'searches' => json_decode($cached['searches_json'] ?? '[]', true),
    'model_used' => $cached['model_used'],
    'tokens_input' => (int)$cached['tokens_input'],
    'tokens_output' => (int)$cached['tokens_output'],
    'cost_usd' => (float)$cached['cost_usd'],
    'web_searches_count' => (int)$cached['web_searches_count'],
], JSON_UNESCAPED_UNICODE);
