<?php
// ============================================================
// SLOT BRACKET — API Historique
// Racine public_html : slot-history.php + slot-bracket.html
// Stocke le palmarès dans un fichier JSON sur le serveur
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');

$file = __DIR__ . '/slot-history.json';

// Lire
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo '[]';
    }
    exit;
}

// Ajouter un champion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['name'])) {
        echo json_encode(['error' => 'Données manquantes']);
        exit;
    }

    $history = [];
    if (file_exists($file)) {
        $history = json_decode(file_get_contents($file), true) ?: [];
    }

    // Ajouter en premier
    array_unshift($history, [
        'name'   => substr($body['name'], 0, 100),
        'multi'  => substr($body['multi'] ?? '0', 0, 20),
        'caller' => substr($body['caller'] ?? '?', 0, 30),
        'date'   => date('d/m/Y H:i'),
    ]);

    // Max 50 entrées
    $history = array_slice($history, 0, 50);

    file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'total' => count($history)]);
    exit;
}

// Effacer tout
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    file_put_contents($file, '[]');
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non supportée']);
