<?php
/**
 * StratEdge Edge Finder — Authentification token
 *
 * Vérifie le header X-StratEdge-Token contre SE_IMPORT_TOKEN avec hash_equals
 * (comparaison timing-safe pour éviter les attaques par timing).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function se_get_token_from_request(): ?string {
    // Header HTTP standard
    if (isset($_SERVER['HTTP_X_STRATEDGE_TOKEN'])) {
        return trim($_SERVER['HTTP_X_STRATEDGE_TOKEN']);
    }
    // Apache mod_rewrite peut transformer le header
    if (isset($_SERVER['REDIRECT_HTTP_X_STRATEDGE_TOKEN'])) {
        return trim($_SERVER['REDIRECT_HTTP_X_STRATEDGE_TOKEN']);
    }
    // Fallback : query param (DEV uniquement, jamais en prod)
    if (SE_DEBUG && isset($_GET['token'])) {
        return trim($_GET['token']);
    }
    return null;
}

function se_require_valid_token(): void {
    $provided = se_get_token_from_request();
    if ($provided === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing X-StratEdge-Token header']);
        exit;
    }
    if (!hash_equals(SE_IMPORT_TOKEN, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
}
