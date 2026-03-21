<?php
// ============================================================
// SLOT BRACKET — Sync API
// public_html/slot-sync.php
// Sauvegarde et charge l'état du bracket en temps réel
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$file = __DIR__ . '/slot-state.json';

// GET = lire l'état actuel
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file)) {
        // Renvoyer le fichier brut (déjà du JSON)
        echo file_get_contents($file);
    } else {
        echo json_encode(['ts' => 0, 'inputs' => [], 'players' => []]);
    }
    exit;
}

// POST = sauvegarder le nouvel état
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || !isset($body['inputs'])) {
        echo json_encode(['error' => 'Données invalides']);
        exit;
    }

    // Ajouter timestamp serveur (fait foi)
    $body['ts'] = round(microtime(true) * 1000);

    file_put_contents($file, json_encode($body, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['success' => true, 'ts' => $body['ts']]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non supportée']);
