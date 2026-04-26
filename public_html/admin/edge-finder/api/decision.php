<?php
/**
 * StratEdge Edge Finder — Endpoint AJAX pour valider/rejeter un candidat.
 *
 * Méthode : POST application/json
 * Auth    : session admin (cookie navigateur, pas de token API)
 * Body    : { "candidate_id": N, "decision": "validated|rejected|published|won|lost|void|pending", "note": "optional text" }
 *
 * Réponse :
 *   200 { success: true, candidate_id: N, decision: "..." }
 *   400 { error: "..." }
 *   401 { error: "Not authenticated" }
 *   404 { error: "Candidate not found" }
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Auth via session admin (super-admin uniquement)
if (!isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated (super-admin required)']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed (use POST)']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$candidate_id = (int)($payload['candidate_id'] ?? 0);
$decision = $payload['decision'] ?? '';
$note = $payload['note'] ?? null;

$valid_decisions = ['pending', 'validated', 'rejected', 'published', 'won', 'lost', 'void'];
if ($candidate_id <= 0 || !in_array($decision, $valid_decisions, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'candidate_id must be > 0 and decision must be one of: ' . implode(', ', $valid_decisions)]);
    exit;
}

$exists = SE_Db::queryOne(
    "SELECT candidate_id FROM pick_candidates WHERE candidate_id = ?",
    [$candidate_id]
);
if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'Candidate not found']);
    exit;
}

try {
    if ($decision === 'pending') {
        SE_Db::execute(
            "UPDATE pick_candidates
             SET user_decision = 'pending', decision_at = NULL, decision_note = NULL
             WHERE candidate_id = ?",
            [$candidate_id]
        );
    } else {
        SE_Db::execute(
            "UPDATE pick_candidates
             SET user_decision = ?, decision_at = NOW(), decision_note = ?
             WHERE candidate_id = ?",
            [$decision, $note, $candidate_id]
        );
    }

    echo json_encode([
        'success'      => true,
        'candidate_id' => $candidate_id,
        'decision'     => $decision,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'  => 'DB error',
        'detail' => SE_DEBUG ? $e->getMessage() : null,
    ]);
}
