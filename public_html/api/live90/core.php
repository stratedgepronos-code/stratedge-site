<?php
declare(strict_types=1);
function config90(): array {
    $path = getenv('SE90_ENV') ?: '/etc/stratedge/live.env';
    $env = is_readable($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
    return is_array($env) ? $env : [];
}
function db90(): PDO {
    $env = config90();
    $db = new PDO('sqlite:' . ($env['SE90_DB'] ?? '/var/lib/stratedge/live90.sqlite'), null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
    $db->exec("CREATE TABLE IF NOT EXISTS cycles(id TEXT PRIMARY KEY, collected_at TEXT NOT NULL, received_at TEXT NOT NULL, payload TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS samples(id INTEGER PRIMARY KEY, cycle_id TEXT NOT NULL, match_id TEXT NOT NULL, received_at TEXT NOT NULL, data TEXT NOT NULL, UNIQUE(cycle_id,match_id));
    CREATE INDEX IF NOT EXISTS samples_match ON samples(match_id,id);
    CREATE TABLE IF NOT EXISTS prematch(id INTEGER PRIMARY KEY, match_id TEXT NOT NULL, recorded_at TEXT NOT NULL, kickoff TEXT NOT NULL, data TEXT NOT NULL);
    CREATE INDEX IF NOT EXISTS prematch_match ON prematch(match_id,id);
    CREATE TABLE IF NOT EXISTS decisions(match_id TEXT PRIMARY KEY, sample_id INTEGER, updated_at TEXT, status TEXT, reasons TEXT, detail TEXT);
    CREATE TABLE IF NOT EXISTS signals(id INTEGER PRIMARY KEY, match_id TEXT NOT NULL, sample_id INTEGER NOT NULL, created_at TEXT NOT NULL, version TEXT NOT NULL, market TEXT NOT NULL, line REAL NOT NULL, odds REAL NOT NULL, context TEXT NOT NULL, delivery TEXT DEFAULT 'disabled', outcome TEXT DEFAULT 'pending', settled_at TEXT, UNIQUE(match_id,version));
    CREATE TABLE IF NOT EXISTS settlements(id INTEGER PRIMARY KEY, signal_id INTEGER NOT NULL, created_at TEXT NOT NULL, score_h INTEGER, score_a INTEGER, note TEXT NOT NULL);");
    return $db;
}
function reply90(array $data, int $code=200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function body90(): array {
    $raw = file_get_contents('php://input', false, null, 0, 2097153);
    if ($raw === false || strlen($raw)>2097152) reply90(['error'=>'Envoi trop volumineux'],413);
    try {$x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);} catch(Throwable $e){reply90(['error'=>'JSON invalide'],400);}
    if (!is_array($x)) reply90(['error'=>'Objet JSON attendu'],400);
    return $x;
}
function number90(mixed $v): ?float {return (is_int($v)||is_float($v)) && is_finite((float)$v) ? (float)$v : null;}
