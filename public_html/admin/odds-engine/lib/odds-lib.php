<?php
// ============================================================
// STRATEDGE ODDS ENGINE — librairie coeur
// Config : config.local.php (jamais versionne) a cote de ce fichier
// ============================================================
function oeDb(): PDO {
    require_once __DIR__ . '/../../../includes/db.php';
    return getDB();
}

function oeConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $f = __DIR__ . '/../config.local.php';
    $cfg = file_exists($f) ? (include $f) : [];
    if (!is_array($cfg)) $cfg = [];
    $cfg += [
        'provider'   => 'theoddsapi',
        'api_key'    => '',
        'regions'    => 'eu',
        'markets'    => 'h2h,totals',
        'sports'     => ['soccer_france_ligue_one', 'soccer_epl', 'tennis_atp', 'tennis_wta'],
        'ref_book'   => 'pinnacle',            // reference sharp pour le devig/CLV
        'fr_books'   => ['betclic', 'unibet_eu', 'winamax_fr'], // books "soft" surveilles
        'closing_window_min' => 10,            // fenetre avant kickoff pour figer la cloture
    ];
    return $cfg;
}

function oeLog(string $msg): void {
    @file_put_contents(__DIR__ . '/../odds-engine.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ── Devig ────────────────────────────────────────────────────
/** Devig proportionnel (2+ issues) : probas justes qui somment a 1. */
function oeDevigProportional(array $odds): array {
    $imp = array_map(fn($o) => $o > 1.0 ? 1.0 / $o : 0.0, $odds);
    $sum = array_sum($imp);
    if ($sum <= 0) return array_fill(0, count($odds), null);
    return array_map(fn($p) => $p / $sum, $imp);
}
/** Devig "power" (methode PROMPT_BETTING v7.5) : resout sum(imp^k)=1. */
function oeDevigPower(array $odds): array {
    $imp = array_map(fn($o) => $o > 1.0 ? 1.0 / $o : 0.000001, $odds);
    $lo = 0.5; $hi = 3.0;
    for ($i = 0; $i < 60; $i++) {
        $k = ($lo + $hi) / 2;
        $s = array_sum(array_map(fn($p) => pow($p, $k), $imp));
        if ($s > 1.0) $lo = $k; else $hi = $k;
    }
    $k = ($lo + $hi) / 2;
    return array_map(fn($p) => pow($p, $k), $imp);
}

// ── Driver The Odds API (v4) ─────────────────────────────────
function oeFetchTheOddsApi(string $sportKey): array {
    $c = oeConfig();
    $url = 'https://api.the-odds-api.com/v4/sports/' . rawurlencode($sportKey) . '/odds'
         . '?apiKey=' . rawurlencode($c['api_key'])
         . '&regions=' . rawurlencode($c['regions'])
         . '&markets=' . rawurlencode($c['markets'])
         . '&oddsFormat=decimal&dateFormat=iso';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) { oeLog("theoddsapi $sportKey HTTP $code"); return []; }
    $json = json_decode((string)$resp, true);
    if (!is_array($json)) return [];
    // Normalisation en structure interne
    $out = [];
    foreach ($json as $ev) {
        $rows = [];
        foreach (($ev['bookmakers'] ?? []) as $bm) {
            foreach (($bm['markets'] ?? []) as $mk) {
                foreach (($mk['outcomes'] ?? []) as $oc) {
                    $rows[] = [
                        'book'      => $bm['key'],
                        'market'    => $mk['key'],
                        'selection' => (string)$oc['name'],
                        'line'      => isset($oc['point']) ? (float)$oc['point'] : null,
                        'odds'      => (float)$oc['price'],
                    ];
                }
            }
        }
        $out[] = [
            'provider_event_id' => (string)$ev['id'],
            'sport_key'         => $sportKey,
            'home'              => (string)($ev['home_team'] ?? ''),
            'away'              => (string)($ev['away_team'] ?? ''),
            'commence_time'     => gmdate('Y-m-d H:i:s', strtotime($ev['commence_time'])),
            'odds'              => $rows,
        ];
    }
    return $out;
}

/** Point d'entree provider-agnostique (OddsPapi = futur driver ici). */
function oeFetchOdds(string $sportKey): array {
    $c = oeConfig();
    switch ($c['provider']) {
        case 'theoddsapi': return oeFetchTheOddsApi($sportKey);
        default: oeLog('provider inconnu: ' . $c['provider']); return [];
    }
}

// ── Persistance ──────────────────────────────────────────────
function oeUpsertEvent(PDO $db, array $ev): int {
    $c = oeConfig();
    $sel = $db->prepare("SELECT id FROM oe_events WHERE provider=? AND provider_event_id=?");
    $sel->execute([$c['provider'], $ev['provider_event_id']]);
    $id = $sel->fetchColumn();
    if ($id) return (int)$id;
    $ins = $db->prepare("INSERT INTO oe_events (provider, provider_event_id, sport_key, home, away, commence_time) VALUES (?,?,?,?,?,?)");
    $ins->execute([$c['provider'], $ev['provider_event_id'], $ev['sport_key'], $ev['home'], $ev['away'], $ev['commence_time']]);
    return (int)$db->lastInsertId();
}

/** Insere UNIQUEMENT si la cote a change vs le dernier snapshot (historique compact). */
function oeRecordOdds(PDO $db, int $eventId, array $row, string $now): bool {
    $sel = $db->prepare(
        "SELECT odds FROM oe_odds
         WHERE event_id=? AND book=? AND market=? AND selection=? AND (line <=> ?)
         ORDER BY captured_at DESC, id DESC LIMIT 1");
    $sel->execute([$eventId, $row['book'], $row['market'], $row['selection'], $row['line']]);
    $last = $sel->fetchColumn();
    if ($last !== false && abs((float)$last - $row['odds']) < 0.001) return false; // inchangee
    $ins = $db->prepare(
        "INSERT INTO oe_odds (event_id, book, market, selection, line, odds, captured_at, is_opening)
         VALUES (?,?,?,?,?,?,?,?)");
    $ins->execute([$eventId, $row['book'], $row['market'], $row['selection'], $row['line'],
                   $row['odds'], $now, $last === false ? 1 : 0]);
    return true;
}

/** Fige la cloture : marque le dernier snapshot de chaque (book,market,selection,line). */
function oeCaptureClosing(PDO $db, int $eventId): int {
    $n = $db->prepare(
        "UPDATE oe_odds o
         JOIN (SELECT MAX(id) mid FROM oe_odds WHERE event_id=? GROUP BY book, market, selection, line) t
           ON o.id = t.mid
         SET o.is_closing = 1");
    $n->execute([$eventId]);
    $db->prepare("UPDATE oe_events SET status='closing_captured' WHERE id=?")->execute([$eventId]);
    return $n->rowCount();
}
