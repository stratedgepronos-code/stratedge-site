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

// Job zombie : 'running' depuis trop longtemps (worker mort sans finir).
// On detecte sur 'running' (etape 1 ou 2) ET 'researched' (etape 1 finie
// mais etape 2 pas lancee correctement) si > 5 min.
if (($status === 'running' || $status === 'researched') && !empty($row['started_at'])) {
    if (time() - strtotime($row['started_at']) > 300) {
        echo json_encode([
            'status' => 'error',
            'error_msg' => 'Analyse expiree (worker interrompu) - relance l\'analyse',
        ]);
        exit;
    }
}

// status='researched' : etape 1 finie, on doit declencher l'etape 2.
// On le fait depuis ICI (l'endpoint status) pour avoir un point de
// declenchement automatique cote serveur sans dependre du frontend.
if ($status === 'researched') {
    // Heuristique anti-doublon : on stocke l'heure du dernier declenchement
    // dans error_msg (champ inutilise dans cet etat). Format : "writer_kicked@TIMESTAMP".
    $last_kick = 0;
    if (preg_match('/^writer_kicked@(\d+)$/', (string)($row['error_msg'] ?? ''), $mm)) {
        $last_kick = (int)$mm[1];
    }
    $now = time();
    // Relance l'etape 2 si jamais lancee, ou si dernier kick > 20s (probable echec, retry)
    if (($now - $last_kick > 20) && defined('SE_WORKER_TOKEN')) {
        SE_Db::execute(
            "UPDATE match_scorer_analysis SET error_msg = ? WHERE match_id = ?",
            ['writer_kicked@' . $now, $match_id]
        );

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'stratedgepronos.fr';
        $base = dirname($_SERVER['REQUEST_URI'] ?? '/panel-x9k3m/edge-finder/api/status');
        $writer_url = $scheme . '://' . $host . $base . '/analyze_scorers_worker_writer.php'
            . '?match_id=' . $match_id
            . '&worker_token=' . urlencode(SE_WORKER_TOKEN);
        $ch = curl_init($writer_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 800,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_FRESH_CONNECT => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    // Pour le navigateur : on continue d'afficher "en cours"
    echo json_encode(['status' => 'running']);
    exit;
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
