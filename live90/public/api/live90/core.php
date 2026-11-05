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
    $db->exec("CREATE TABLE IF NOT EXISTS context_jobs(id INTEGER PRIMARY KEY, kind TEXT NOT NULL, match_id TEXT NOT NULL, prematch_id INTEGER NOT NULL, stage TEXT NOT NULL, sample_id INTEGER, context_id INTEGER, state TEXT NOT NULL DEFAULT 'queued', created_at TEXT NOT NULL, started_at TEXT, finished_at TEXT, error TEXT, UNIQUE(kind,prematch_id,stage));
    CREATE TABLE IF NOT EXISTS context_reports(id INTEGER PRIMARY KEY, job_id INTEGER UNIQUE, match_id TEXT NOT NULL, prematch_id INTEGER NOT NULL, created_at TEXT NOT NULL, status TEXT NOT NULL, data TEXT NOT NULL, sources TEXT NOT NULL, research TEXT NOT NULL);
    CREATE INDEX IF NOT EXISTS context_match ON context_reports(match_id,id);
    CREATE TABLE IF NOT EXISTS live_reviews(id INTEGER PRIMARY KEY, job_id INTEGER UNIQUE, match_id TEXT NOT NULL, sample_id INTEGER NOT NULL, context_id INTEGER NOT NULL, created_at TEXT NOT NULL, assessment TEXT NOT NULL, data TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS ai_calls(id INTEGER PRIMARY KEY, job_id INTEGER NOT NULL, phase TEXT NOT NULL, created_at TEXT NOT NULL, state TEXT NOT NULL, usage TEXT);");
    $db->exec("CREATE TABLE IF NOT EXISTS playbooks(id INTEGER PRIMARY KEY, match_id TEXT NOT NULL, prematch_id INTEGER NOT NULL, imported_at TEXT NOT NULL, generated_at TEXT NOT NULL, data TEXT NOT NULL, digest TEXT NOT NULL);
    CREATE INDEX IF NOT EXISTS playbooks_match ON playbooks(match_id,id);
    CREATE TABLE IF NOT EXISTS playbook_imports(digest TEXT PRIMARY KEY, imported_at TEXT NOT NULL);");
    $db->exec("CREATE TABLE IF NOT EXISTS pending_playbooks(fixture TEXT PRIMARY KEY, accepted_at TEXT NOT NULL, generated_at TEXT NOT NULL, data TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'waiting', match_id TEXT);");
    $db->exec("CREATE TABLE IF NOT EXISTS collection_sources(client_id TEXT PRIMARY KEY,installation_id TEXT,version TEXT,last_seen_at TEXT,last_accepted_at TEXT,state TEXT,capabilities TEXT);
    CREATE TABLE IF NOT EXISTS collection_primary(id INTEGER PRIMARY KEY CHECK(id=1),client_id TEXT NOT NULL,version TEXT,last_good_at TEXT NOT NULL);");
    return $db;
}

function source90(PDO $db, array $x, string $now): array {
    // Called within the ingestion transaction. One active producer, no field merging.
    $version=is_string($x['collector_version']??null)?substr($x['collector_version'],0,40):'unknown';
    $client=is_string($x['client_id']??null)?substr($x['client_id'],0,100):'legacy-'.$version;
    $installation=is_string($x['installation_id']??null)?substr($x['installation_id'],0,100):null;
    $fields=[];$healthy=false;
    foreach($x['rows'] as $r){
        if(!is_array($r) || !preg_match('/^\d{1,20}$/',(string)($r['packball_id']??'')) || !empty($r['quality_errors']))continue;
        $stats=is_array($r['stats']??null)?$r['stats']:[];
        foreach($stats as $k=>$v){if(is_array($v) && number90($v['h']??null)!==null && number90($v['a']??null)!==null)$fields[$k]=true;}
        $clock=number90($r['minute']??null);$score=$r['score']??null;
        if(!in_array($r['state']??'', ['LIVE','HT'],true) || $clock===null || $clock<0 || $clock>90 || !is_array($score))continue;
        $valid=true;
        foreach(['h','a'] as $side){
            if(number90($score[$side]??null)===null || number90($stats['red_cards'][$side]??null)===null)$valid=false;
            if(number90($stats['fouls'][$side]??null)===null && (number90($stats['shots'][$side]??null)===null || number90($stats['sot'][$side]??null)===null))$valid=false;
        }
        if($valid)$healthy=true;
    }
    $primary=$db->query('SELECT * FROM collection_primary WHERE id=1')->fetch();
    $fresh=$primary && time()-strtotime($primary['last_good_at'])<=100;
    $higher=$primary && preg_match('/^\d+\.\d+\.\d+$/',$version) && preg_match('/^\d+\.\d+\.\d+$/',$primary['version']) && version_compare($version,$primary['version'],'>');
    $accepted=!$fresh || $primary['client_id']===$client || ($healthy && $higher);
    $s=$db->prepare('INSERT INTO collection_sources VALUES(?,?,?,?,?,?,?) ON CONFLICT(client_id) DO UPDATE SET installation_id=excluded.installation_id,version=excluded.version,last_seen_at=excluded.last_seen_at,last_accepted_at=COALESCE(excluded.last_accepted_at,collection_sources.last_accepted_at),state=excluded.state,capabilities=excluded.capabilities');
    $s->execute([$client,$installation,$version,$now,$accepted?$now:null,$accepted?'accepted':'standby',json_encode(array_keys($fields))]);
    if(!$accepted)return ['accepted'=>false,'code'=>'SOURCE_CONFLICT','primary_version'=>$primary['version'],'error'=>'Un autre collecteur actif ('.$primary['version'].') est prioritaire. Garde un seul onglet/script de collecte ; reprise automatique après 100 s sans relevé sain.'];
    if($healthy){
        $s=$db->prepare('INSERT OR REPLACE INTO collection_primary VALUES(1,?,?,?)');$s->execute([$client,$version,$now]);
    }
    return ['accepted'=>true,'healthy'=>$healthy,'version'=>$version];
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
