<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = $argv[1] ?? dirname(__DIR__);
$web = is_file($root . '/public_html/admin/football-lab/lib/Context.php') ? $root . '/public_html' : $root;
require_once $web . '/admin/football-lab/lib/Context.php';
require_once $web . '/admin/football-lab/lib/AutoContext.php';
require_once $web . '/admin/football-lab/lib/Store.php';
if (!\StratEdgeLab\Context::ready()) { echo "GPT_CONTEXT_NOT_CONFIGURED\n"; exit; }
if (in_array('--check', $argv, true)) { echo "GPT_CONTEXT_CONFIGURED\n"; exit; }
require_once $web . '/includes/db.php';
$db = getDB(); $store = new \StratEdgeLab\Store($db); $store->install();
// Only the newest analysis per owner; older imports remain immutable archives.
$rows = $db->query("SELECT r.id, r.owner_id FROM se_lab_runs r WHERE r.kind = 'analysis' AND NOT EXISTS (SELECT 1 FROM se_lab_runs n WHERE n.owner_id = r.owner_id AND n.kind = 'analysis' AND (n.created_at > r.created_at OR (n.created_at = r.created_at AND n.id > r.id)))")->fetchAll(\PDO::FETCH_ASSOC);
$started = time(); $processed = 0;
foreach ($rows as $row) {
    $owner = (int)$row['owner_id']; $run = $store->get($row['id'], $owner); $latest = [];
    foreach ($store->events($run['id'], $owner) as $event) {
        if ($event['kind'] === 'research') { $latest[$event['match_key']] = $event['data']; }
    }
    $matches = $run['data']['analysis']['matches'];
    usort($matches, static function ($a, $b) { return ($b['pick']['probability'] ?? 0) <=> ($a['pick']['probability'] ?? 0); });
    foreach ($matches as $match) {
        if ($processed >= 3 || time() - $started >= 180) { break 2; }
        if (!\StratEdgeLab\AutoContext::due($match, $latest[$match['key']] ?? null, time())) { continue; }
        try {
            $research = \StratEdgeLab\Context::request($match);
            if (strtotime($match['kickoff']) <= time()) { continue; }
            $research['automatic'] = true;
        } catch (\Throwable $e) {
            $research = ['decision'=>'pending', 'reason'=>'Recherche temporairement indisponible ; nouvelle tentative automatique.', 'text'=>'', 'sources'=>[], 'checked_at'=>gmdate('c'), 'status'=>'error', 'prompt_version'=>2, 'automatic'=>true];
        }
        $store->event($run['id'], $match['key'], 'research', $research, $owner);
        $processed++;
    }
}
echo 'GPT_CONTEXT_PROCESSED=' . $processed . PHP_EOL;
