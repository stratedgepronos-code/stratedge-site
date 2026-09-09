<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requireSuperAdmin();
require_once __DIR__ . '/lib/Engine.php';
require_once __DIR__ . '/lib/Store.php';
require_once __DIR__ . '/lib/Context.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
$db = getDB();
$labOwner = (int)($_SESSION['membre_id'] ?? 0);
if ($labOwner < 1) { http_response_code(403); exit('Accès refusé.'); }
$labStore = new \StratEdgeLab\Store($db);
$labError = null;
try { $labStore->install(); } catch (\Throwable $e) {
    error_log('StratEdge Lab: database initialization failed');
    $labError = 'Le stockage des analyses n’est pas disponible. Vérifiez les droits de création de tables du site.';
}
$labBase = '/panel-x9k3m/football-lab/';
function lab_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lab_p(float $value): string { return number_format($value * 100, 1, ',', ' ') . ' %'; }
function lab_url(string $url): bool { return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true); }
function lab_token(): string { return '<input type="hidden" name="csrf" value="' . lab_h(csrfToken()) . '">'; }
function lab_find_match(array $run, string $key): array {
    foreach ($run['data']['analysis']['matches'] ?? [] as $match) { if ($match['key'] === $key) { return $match; } }
    throw new \InvalidArgumentException('Match introuvable.');
}
function lab_latest(array $events): array {
    $out = [];
    foreach ($events as $event) { $out[$event['match_key']][$event['kind']] = $event; }
    return $out;
}
