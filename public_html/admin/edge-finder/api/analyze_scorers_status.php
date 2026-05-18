<?php
/**
 * STRATEDGE - Edge Finder - STATUS analyse buteurs (asynchrone)
 * ================================================================================
 *
 * GET ?match_id=N
 *
 * Endpoint LEGER interroge en polling par le frontend toutes les ~3s.
 *
 * Reponses JSON :
 *   {status: 'pending'}   -> job cree, worker pas encore demarre
 *   {status: 'running'}   -> worker en cours
 *   {status: 'error', error_msg: '...'}
 *   {status: 'done', ...resultat complet...}
 *   {status: 'none'}      -> aucune analyse pour ce match
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config-keys.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

if (!isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$match_id = (int)($_GET['match_id'] ?? 0);
if ($match_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'match_id required']);
    exit;
}

$row = SE_Db::queryOne(
    "SELECT * FROM match_scorer_analysis WHERE match_id = ?",
    [$match_id]
);

if (!$row) {
    echo json_encode(['status' => 'none']);
    exit;
}

$status = $row['status'] ?? 'done';

// Job zombie : 'running' depuis trop longtemps (worker mort sans finir)
if ($status === 'running' && !empty($row['started_at'])) {
    if (time() - strtotime($row['started_at']) > 300) {
        echo json_encode([
            'status' => 'error',
            'error_msg' => 'Analyse expiree (worker interrompu) - relance l\'analyse',
        ]);
        exit;
    }
}

if ($status === 'pending' || $status === 'running') {
    echo json_encode(['status' => $status]);
    exit;
}

if ($status === 'error') {
    echo json_encode([
        'status' => 'error',
        'error_msg' => $row['error_msg'] ?: 'Erreur inconnue',
    ]);
    exit;
}

// status === 'done' : renvoie le resultat complet
echo json_encode([
    'status' => 'done',
    'generated_at' => $row['generated_at'],
    'scorers' => json_decode($row['scorers_json'] ?? '[]', true),
    'warnings' => json_decode($row['warnings_json'] ?? '[]', true),
    'freshness_note' => $row['freshness_note'],
    'markdown_full' => $row['markdown_full'],
    'searches' => json_decode($row['searches_json'] ?? '[]', true),
    'model_used' => $row['model_used'],
    'tokens_input' => (int)$row['tokens_input'],
    'tokens_output' => (int)$row['tokens_output'],
    'web_searches_count' => (int)$row['web_searches_count'],
    'cost_usd' => (float)$row['cost_usd'],
    'duration_seconds' => (int)$row['duration_seconds'],
], JSON_UNESCAPED_UNICODE);
