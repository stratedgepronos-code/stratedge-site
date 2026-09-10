<?php
declare(strict_types=1);
require __DIR__ . '/../../public_html/admin/football-lab/lib/Engine.php';
require __DIR__ . '/../../public_html/admin/football-lab/lib/Store.php';
require __DIR__ . '/../../public_html/admin/football-lab/lib/Metrics.php';
require __DIR__ . '/../../public_html/admin/football-lab/lib/Context.php';
require __DIR__ . '/../../public_html/admin/football-lab/lib/PackBall.php';
require __DIR__ . '/../../public_html/admin/football-lab/lib/ResultsImport.php';
use StratEdgeLab\Engine;
use StratEdgeLab\Store;
use StratEdgeLab\Metrics;
use StratEdgeLab\Context;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
function near(float $a, float $b, string $message): void { check(abs($a - $b) < 1e-10, $message); }
function rejects(callable $f, string $message): void {
    try { $f(); } catch (InvalidArgumentException $e) { check(true, $message); return; }
    throw new RuntimeException('Expected rejection: ' . $message);
}
$csv = "home;away;date;league;status;n;gf;ga;agf;aga;over\nClub A;Club B;2030-09-08 21:00;Test;NS;10 | 10;2,1;1,2;1,8;1,6;2,14\n";
$table = Engine::csv($csv, 'auto', true);
check(count($table['rows']) === 1 && count($table['headers']) === 11, 'CSV width and row count');
$maps = ['stats' => ['home' => '0:value', 'away' => '1:value', 'kickoff' => '2:value', 'league' => '3:value', 'status' => '4:value', 'home_n' => '5:first', 'away_n' => '5:second', 'home_gf' => '6:value', 'home_ga' => '7:value', 'away_gf' => '8:value', 'away_ga' => '9:value', 'ft_over_25' => '10:value']];
$opts = ['date_format' => 'Y-m-d H:i', 'timezone' => 'Europe/Paris', 'markets' => ['ft_over_25', 'ft_under_25']];
$now = new DateTimeImmutable('2030-09-08T12:00:00Z');
$run = Engine::analyze(['stats' => $table], $maps, $opts, $now);
check(count($run['matches']) === 1 && !$run['errors'], 'End-to-end import');
$m = $run['matches'][0];
check($m['kickoff'] === '2030-09-08T19:00:00+00:00', 'Paris summer timezone');
near($m['lambdas']['ft']['home'], 1.85, 'Crossed home attack');
near($m['lambdas']['ft']['away'], 1.5, 'Crossed away attack');
check($m['stats']['home_n'] === 10, 'Split count');
check($m['pick']['id'] === 'ft_over_25', 'Highest probability chosen');
near($m['pick']['probability'], 1 - exp(-3.35) * (1 + 3.35 + 3.35 ** 2 / 2), 'Total formula');
near($m['pick']['ev'], $m['pick']['probability'] * 2.14 - 1, 'EV separate from rank');
check(count($m['candidates']) === 16, 'No invented halftime markets');
$p = Engine::probabilities(1, 1, 'ft');
near($p['ft_btts_yes']['probability'], (1 - exp(-1)) ** 2, 'BTTS analytic probability');
near($p['ft_btts_yes']['probability'] + $p['ft_btts_no']['probability'], 1, 'Complementary BTTS');
foreach ([0.0, 0.1, 0.9, 1.8, 4.0, 15.0] as $lambda) {
    $probs = Engine::probabilities($lambda / 2, $lambda / 2, 'ft');
    near($probs['ft_over_25']['probability'] + $probs['ft_under_25']['probability'], 1, 'Complementary totals');
    check($probs['ft_over_15']['probability'] >= $probs['ft_over_25']['probability'] && $probs['ft_over_25']['probability'] >= $probs['ft_over_35']['probability'], 'Monotone totals');
    foreach ($probs as $v) { check($v['probability'] >= 0 && $v['probability'] <= 1, 'Bounded probabilities'); }
}
near(Engine::number('0', 'zero'), 0, 'Zero is not missing');
check(Engine::number('', 'missing') === null, 'Missing is not zero');
rejects(static function () { Engine::number('2,1,2', 'bad'); }, 'Malformed decimal');
rejects(static function () { Engine::number('90%', 'bad'); }, 'Percent is not a goals mean');
rejects(static function () { Engine::number('-1', 'bad'); }, 'Negative mean');
rejects(static function () { Engine::cell(['2'], ['x' => '0:second'], 'x'); }, 'Missing split');
rejects(static function () { Engine::cell(['2'], ['x' => '999:value'], 'x'); }, 'Column bounds');
rejects(static function () { Engine::csv("a;b\n1;2;3", ';', true); }, 'Ragged rows');
rejects(static function () { Engine::csv("\xff", ';', false); }, 'Invalid UTF8');
rejects(static function () { Engine::csv('a;b', ';', true); }, 'Header without data');
$quoted = Engine::csv("\xEF\xBB\xBFhome;away\n\"Club; A\";Club B", ';', true);
check($quoted['rows'][0][0] === 'Club; A', 'Quoted delimiter and BOM');
rejects(static function () use ($opts) { Engine::kickoff('2030-02-31 21:00', $opts); }, 'Invalid calendar date');
rejects(static function () use ($opts) { Engine::kickoff('08/09/2030 21:00', $opts); }, 'Explicit date format');
$hours = $opts; $hours['date_format'] = 'H:i'; $hours['match_date'] = '2030-09-08';
check(Engine::kickoff('21:00', $hours)->format('H:i') === '19:00', 'Time-only date');
$bad = $table; $bad['rows'][0][4] = 'FT';
check(!Engine::analyze(['stats' => $bad], $maps, $opts, $now)['matches'], 'Finished status excluded');
check(!Engine::analyze(['stats' => $table], $maps, $opts, new DateTimeImmutable('2030-09-09'))['matches'], 'Past match excluded');
$bad = $table; $bad['rows'][] = $bad['rows'][0];
check(!Engine::analyze(['stats' => $bad], $maps, $opts, $now)['matches'], 'Both duplicated fixtures excluded');
$bad = $table; $bad['rows'][0][6] = '';
check(!Engine::analyze(['stats' => $bad], $maps, $opts, $now)['matches'], 'Missing goals mean excluded');
$bad = $table; $bad['rows'][0][5] = '0 | 10';
check(!Engine::analyze(['stats' => $bad], $maps, $opts, $now)['matches'], 'Empty sample excluded');
$filter = $opts; $filter['min_odds'] = '3.00';
check(Engine::analyze(['stats' => $table], $maps, $filter, $now)['matches'][0]['pick'] === null, 'Odds filter excludes all without forcing a bet');
$oddsTable = Engine::csv("home;away;date;league;under\nClub A;Club B;2030-09-08 21:00;Test;2.5", ';', true);
$joined = $maps; $joined['odds'] = ['home' => '0:value', 'away' => '1:value', 'kickoff' => '2:value', 'league' => '3:value', 'ft_under_25' => '4:value'];
$joinResult = Engine::analyze(['stats' => $table, 'odds' => $oddsTable], $joined, $opts, $now);
$cands = array_column($joinResult['matches'][0]['candidates'], null, 'id');
near($cands['ft_under_25']['odds'], 2.5, 'Exact odds join');
$oddsTable['rows'][0][1] = 'Other club';
$r = Engine::analyze(['stats' => $table, 'odds' => $oddsTable], $joined, $opts, $now);
check(count($r['matches'][0]['warnings']) > 0, 'Unmatched odds reported');
$htTable = $table; $htTable['rows'][0] = array_merge($htTable['rows'][0], ['1.2', '0.7', '1.0', '0.7']);
$htMap = $maps;
foreach (['home_gf', 'home_ga', 'away_gf', 'away_ga'] as $i => $field) { $htMap['stats']['h1_' . $field] = (11 + $i) . ':value'; }
$htOpts = $opts; $htOpts['markets'] = ['h1_over_15'];
$r = Engine::analyze(['stats' => $htTable], $htMap, $htOpts, $now);
near($r['matches'][0]['pick']['probability'], 1 - exp(-1.8) * 2.8, 'First half uses its own four means');
$htTable['rows'][0][11] = '9';
check(!Engine::analyze(['stats' => $htTable], $htMap, $htOpts, $now)['matches'], 'Incoherent halftime mapping blocked');
check(Engine::settle($p['ft_btts_yes'], 1, 1), 'Settle BTTS yes');
check(!Engine::settle($p['ft_over_25'], 1, 1), 'Settle under threshold');
check(Engine::settle($p['ft_home_over_05'], 1, 0), 'Settle home goals');

$db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$store = new Store($db); $store->install(); $store->install();
$id = $store->create('analysis', ['analysis' => $run], 123);
check($store->get($id, 123)['data']['analysis']['matches'][0]['home'] === 'Club A', 'Stored analysis');
rejects(static function () use ($store, $id) { $store->get($id, 124); }, 'Run ownership');
rejects(static function () use ($store) { $store->get('../config.php', 123); }, 'Run ID validation');
$store->event($id, $m['key'], 'context', ['decision' => 'retained', 'note' => 'test'], 123);
$store->event($id, $m['key'], 'result', ['outcome' => 'won'], 123);
$store->event($id, $m['key'], 'result', ['outcome' => 'lost'], 123);
check(count($store->events($id, 123)) === 3, 'Append-only corrections');
check(!$store->events($id, 124), 'Event ownership');
rejects(static function () use ($store, $id) { $store->event($id, str_repeat('0', 64), 'context', [], 123); }, 'Event fixture validation');
near($store->get($id, 123)['data']['analysis']['matches'][0]['pick']['probability'], $m['pick']['probability'], 'Original probability unchanged');
$entry = ['run_id' => 'a', 'created_at' => '2030-09-08T12:00:00Z', 'match' => $m, 'result' => ['outcome' => 'won']];
$duplicate = $entry; $duplicate['run_id'] = 'b'; $duplicate['created_at'] = '2030-09-08T13:00:00Z'; $duplicate['result']['outcome'] = 'lost';
$metrics = Metrics::summarize([$duplicate, $entry]);
check($metrics['n'] === 1 && $metrics['wins'] === 1, 'Repeated imports not counted twice');
near($metrics['units'], 1.14, 'Actual per-bet odds used');
$entry['match']['pick']['odds'] = null;
$metrics = Metrics::summarize([$entry]);
check($metrics['n'] === 1 && $metrics['priced'] === 0 && $metrics['roi'] === null, 'Unpriced predictions excluded from ROI only');
$entry['result']['outcome'] = 'void';
check(Metrics::summarize([$entry])['n'] === 0, 'Voids excluded from accuracy');
$parsed = Context::parse(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Texte sourcé', 'annotations' => [['type' => 'url_citation', 'url' => 'https://example.com/club', 'title' => 'Club'], ['type' => 'url_citation', 'url' => 'javascript:alert(1)', 'title' => 'Unsafe']]]]]]], 'test-model');
check(count($parsed['sources']) === 1 && $parsed['status'] === 'review_required', 'Source URLs filtered, no automatic validation');
try { Context::parse(['status' => 'completed', 'output' => []], 'test'); throw new Exception('Should reject'); } catch (RuntimeException $e) { check(true, 'No fabricated context on empty response'); }
// Render actual templates and execute the action controller in isolated PHP requests.
$fixture = sys_get_temp_dir() . '/stratedge-lab-test-' . bin2hex(random_bytes(8));
mkdir($fixture . '/public_html/includes', 0700, true);
mkdir($fixture . '/public_html/admin/football-lab', 0700, true);
$source = __DIR__ . '/../../public_html/admin/football-lab';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $file) {
    $target = $fixture . '/public_html/admin/football-lab/' . substr($file->getPathname(), strlen($source) + 1);
    if ($file->isDir()) { mkdir($target, 0700, true); }
    elseif ($file->getFilename() !== 'config.local.php') { copy($file->getPathname(), $target); }
}
copy(__DIR__ . '/../../public_html/includes/auth.php', $fixture . '/public_html/includes/auth.php');
copy(__DIR__ . '/../../public_html/admin/sidebar.php', $fixture . '/public_html/admin/sidebar.php');
file_put_contents($fixture . '/public_html/includes/db.php', '<?php define("ADMIN_EMAIL", "lab-test@example.test"); function getDB(): PDO { static $db; if (!$db) { $db = new PDO("sqlite:" . __DIR__ . "/fixture.sqlite"); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } return $db; }');
$fixtureDb = new PDO('sqlite:' . $fixture . '/public_html/includes/fixture.sqlite');
$fixtureDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fixtureStore = new Store($fixtureDb); $fixtureStore->install();
$draftId = $fixtureStore->create('draft', ['tables' => ['stats' => $table + ['name' => 'synthetic.csv']]], 123);
$fixtureId = $fixtureStore->create('analysis', ['analysis' => $run, 'maps' => $maps, 'tables' => ['stats' => $table]], 123);
$request = static function (string $page, array $data = []) use ($fixture): array {
    $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . escapeshellarg(__DIR__ . '/render-child.php') . ' ' . escapeshellarg($fixture) . ' ' . escapeshellarg($page) . ' ' . escapeshellarg(Store::encode($data));
    $pipes = []; $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start PHP request'); }
    $body = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    check($exit === 0 && strpos($body . $err, 'Fatal error') === false, 'Request executes: ' . $page . ' ' . $err);
    return [$body, $err];
};
// Optional synthetic previews for visual review; never load a production database.
$renderDir = getenv('LAB_RENDER_DIR') ?: '';
$savePreview = static function (string $name, string $html) use ($renderDir): void {
    if (!$renderDir) { return; }
    if (!is_dir($renderDir)) { mkdir($renderDir, 0700, true); }
    $html = preg_replace('~(/panel-x9k3m/football-lab/)([a-z]+)\.php(?:\?[^" ]*)?~', '$2.html', $html);
    file_put_contents($renderDir . '/' . $name . '.html', $html);
};
foreach (['import.php', 'index.php', 'history.php'] as $page) {
    [$body] = $request($page);
    $savePreview(basename($page, '.php'), $body);
    check(strpos($body, '/admin/football-lab/assets/lab.css?v=') !== false, 'Real CSS path in ' . $page);
    check(strpos($body, '/panel-x9k3m/football-lab/assets/') === false, 'No broken rewritten asset path');
    check(strpos($body, 'StratEdge Lab') !== false && strpos($body, '</html>') !== false, 'Complete rendered page: ' . $page);
}
[$body] = $request('import.php', ['get' => ['id' => $draftId]]);
$savePreview('mapping', $body);
check(substr_count($body, 'type="file"') === 2 && substr_count($body, 'name="packball"') === 2 && strpos($body, 'confirm_mapping') === false && strpos($body, '<select') === false, 'Analysis and result imports have no mapping/settings');
[$body] = $request('match.php', ['get' => ['id' => $fixtureId, 'match' => $m['key']]]);
$savePreview('match', $body);
check(strpos($body, 'Marchés comparés') !== false && strpos($body, 'Recherche web non configurée') !== false, 'Match detail and honest API state');
[$body, $err] = $request('index.php', ['session' => []]);
check($body === '' && strpos($err, 'HTTP_STATUS=302') !== false, 'Unauthenticated user redirected by actual auth');
[$body, $err] = $request('index.php', ['session' => ['membre_id' => 124, 'membre_email' => 'other@example.test', 'is_admin' => true]]);
check($body === '' && strpos($err, 'HTTP_STATUS=302') !== false, 'Ordinary admin denied by actual superadmin guard');
[$body, $err] = $request('action.php', ['post' => ['action' => 'context', 'csrf' => 'wrong']]);
check(strpos($err, 'HTTP_STATUS=403') !== false && strpos($body, 'Session expirée') !== false, 'CSRF failure blocks action');
[$body, $err] = $request('action.php', ['post' => ['action' => 'context', 'csrf' => 'fixture-token', 'id' => $fixtureId, 'match' => $m['key'], 'decision' => 'retained', 'note' => '<script>alert(1)</script>']]);
check(strpos($err, 'HTTP_STATUS=303') !== false && count($fixtureStore->events($fixtureId, 123)) === 1, 'Valid context POST persists and redirects');
[$body] = $request('match.php', ['get' => ['id' => $fixtureId, 'match' => $m['key']]]);
check(strpos($body, '<script>alert(1)</script>') === false && strpos($body, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Stored note escaped in template');
[$body, $err] = $request('action.php', ['post' => ['action' => 'analyze', 'csrf' => 'fixture-token', 'id' => $draftId, 'confirm_mapping' => '1', 'mapping' => $maps, 'options' => $opts]]);
check(strpos($err, 'HTTP_STATUS=303') !== false && count($fixtureStore->recent(123)) === 2, 'Mapping-to-analysis controller persists new run');
// Empty states must not imply measured performance before results exist.
$emptyOwner = ['session' => ['membre_id' => 999, 'membre_email' => 'lab-test@example.test', 'is_admin' => true, 'csrf_token' => 'fixture-token']];
[$body] = $request('history.php', $emptyOwner);
check(strpos($body, 'Le premier résultat lance le suivi.') !== false && strpos($body, '0,00') === false, 'No simulated profit shown without settled prices');
$savePreview('history-empty', $body);
[$body] = $request('index.php', $emptyOwner);
$savePreview('analyses-empty', $body);
check(strpos($body, 'Chaque sélection commence') !== false, 'Useful empty analysis state');
$fixtureStore->event($fixtureId, $m['key'], 'result', ['outcome' => 'won'], 123);
[$body] = $request('history.php'); $savePreview('history-settled', $body);
check((bool)preg_match('/lab-status-(won|lost)/', $body), 'Settled outcome badge renders');
check(!preg_match('/^(<{7}|={7}|>{7})(?: |$)/m', file_get_contents(__DIR__ . '/../../public_html/admin/sidebar.php')), 'Sidebar has no merge markers');
require __DIR__ . '/packball.php';
$packPrepared = \StratEdgeLab\PackBall::prepare($makeExport([$exportRow]), 'synthetic.csv', new DateTimeImmutable('+1 day'));
$packRunId = $fixtureStore->create('analysis', $packPrepared, 123);
$resultRow = $exportRow; $resultRow[4] = 'FT'; $resultRow[6] = '2'; $resultRow[7] = '1';
$resultReport = \StratEdgeLab\ResultsImport::apply($fixtureStore, 123, $makeExport([$resultRow]), 'results.csv', new DateTimeImmutable('+4 days'));
check($resultReport['recorded'] === 1 && $resultReport['existing'] === 0, 'Finished PackBall import settles the matching analysis');
$resultReportAgain = \StratEdgeLab\ResultsImport::apply($fixtureStore, 123, $makeExport([$resultRow]), 'results.csv', new DateTimeImmutable('+4 days'));
check($resultReportAgain['existing'] === 1 && $resultReportAgain['conflict'] === 0, 'Repeated result import is idempotent');
$conflictRow = $resultRow; $conflictRow[6] = '0'; $conflictRow[7] = '0';
$conflictReport = \StratEdgeLab\ResultsImport::apply($fixtureStore, 123, $makeExport([$conflictRow]), 'conflict.csv', new DateTimeImmutable('+4 days'));
check($conflictReport['conflict'] === 1, 'Contradictory score is never silently overwritten');
echo "OK — $checks checks\n";
