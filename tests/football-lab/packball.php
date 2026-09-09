<?php
// Loaded by run.php: synthetic export in the exact supplied PackBall layout.
require_once __DIR__ . '/../../public_html/admin/football-lab/lib/PackBall.php';
use StratEdgeLab\PackBall;

$exportRow = ['Portugal', 'POR', 'Test League', (new DateTimeImmutable('+2 days', new DateTimeZone('Europe/Paris')))->format('d-m-Y') . ' 21:45', 'NS', 'Club Test D', '', '', 'Club Test E', '', '',
    '1.42', '2.74', '5.89', '1.11', '2.10', '1.72', '2.13', '1.65',
    '10', '10', '1.2', '3.7', '1.6', '0.8', '3.6', '2.7', '80', '20', '65',
    '30', '30', '20', '0', '13.8', '6.6', '7.8', '21.6', '3.3', '8.6', '3.8', '2.3', '44', '64', '1', '2.6'];
$makeExport = static function (array $rows, ?array $headers = null, string $separator = ';'): string {
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headers ?? PackBall::headers(), $separator, '"', '');
    foreach ($rows as $row) { fputcsv($stream, $row, $separator, '"', ''); }
    rewind($stream); $csv = stream_get_contents($stream); fclose($stream); return $csv;
};
$csv = $makeExport([$exportRow]);
$prepared = PackBall::prepare($csv, 'PackBall Custom GPT 01-01-2000(1).csv');
$match = $prepared['analysis']['matches'][0];
check(count($prepared['tables']) === 1 && count($prepared['tables']['stats']['headers']) === 46, 'Single 46-column PackBall file');
check(count($prepared['analysis']['matches']) === 1 && !$prepared['analysis']['errors'], 'Automatic PackBall analysis without posted settings');
near($match['lambdas']['ft']['home'], 1.1, 'Home aggregate shrunk toward league average');
near($match['lambdas']['ft']['away'], 31.9 / 14, 'Away aggregate shrunk toward league average');
check($match['stats']['home_n'] === 10 && $match['stats']['away_n'] === 10, 'Samples from export, no default prompt');
$byId = array_column($match['candidates'], null, 'id');
foreach (['ft_over_25' => 1.42, 'ft_under_25' => 2.74, 'ft_under_15' => 5.89, 'ft_over_15' => 1.11, 'ft_over_35' => 2.10, 'ft_under_35' => 1.72, 'ft_btts_yes' => 2.13, 'ft_btts_no' => 1.65] as $market => $odds) {
    near($byId[$market]['odds'], $odds, 'Correct repeated Odds column: ' . $market);
}
check(count($byId) === 8, 'Only the eight supplied markets are analyzed by V2');
foreach ($byId as $c) { check(!$c['eligible'] || ($c['odds'] >= 1.60 && $c['odds'] <= 3.50 && $c['ev'] >= 0.04 && $c['stress_ev'] >= 0), 'Selection requires useful price and robust model edge'); }
check($prepared['import']['timezone'] === 'Europe/Paris' && strtotime($match['kickoff']) > time(), 'Date read from row, not old export filename');
$winter = $exportRow; $winter[3] = '15-01-2030 21:45';
$summer = $exportRow; $summer[3] = '15-07-2030 21:45';
$fixedNow = new DateTimeImmutable('2029-01-01T00:00:00Z');
check(PackBall::prepare($makeExport([$winter]), 'test.csv', $fixedNow)['analysis']['matches'][0]['kickoff'] === '2030-01-15T20:45:00+00:00', 'Paris winter offset');
check(PackBall::prepare($makeExport([$summer]), 'test.csv', $fixedNow)['analysis']['matches'][0]['kickoff'] === '2030-07-15T19:45:00+00:00', 'Paris summer offset');
$finished = $exportRow; $finished[5] = 'Finished'; $finished[4] = 'FT';
$live = $exportRow; $live[5] = 'Live'; $live[4] = 'INPLAY_2ND_HALF';
$mixed = PackBall::prepare($makeExport([$finished, $exportRow, $live]), 'matches.csv');
check(count($mixed['analysis']['matches']) === 1 && count($mixed['analysis']['errors']) === 2, 'Live and completed rows skipped, upcoming rows retained');
check(count(PackBall::prepare($makeExport([$finished]), 'old.csv')['analysis']['matches']) === 0, 'Old exports give an empty report, no forced predictions');
$noOdds = $exportRow; for ($col = 11; $col <= 18; $col++) { $noOdds[$col] = ''; }
check(PackBall::prepare($makeExport([$noOdds]), 'no-odds.csv')['analysis']['matches'][0]['pick'] === null, 'No invented price or unpriced pick');
$bad = $exportRow; $bad[21] = 'not a number';
check(count(PackBall::prepare($makeExport([$bad, $live]), 'bad.csv')['analysis']['errors']) === 2, 'Bad data reported per row');
foreach ([',', "\t"] as $delimiter) { check(count(PackBall::prepare($makeExport([$exportRow], null, $delimiter), 'other.csv')['analysis']['matches']) === 1, 'Automatic CSV delimiter'); }
$headers = PackBall::headers(); $headers[11] = 'Unknown';
rejects(static function () use ($makeExport, $exportRow, $headers) { PackBall::prepare($makeExport([$exportRow], $headers), 'changed.csv'); }, 'Changed schema rejected instead of misread');
rejects(static function () use ($makeExport, $exportRow) { PackBall::prepare($makeExport([array_slice($exportRow, 0, 45)], array_slice(PackBall::headers(), 0, 45)), 'short.csv'); }, 'Missing columns rejected');

// Actual multipart upload through PHP's HTTP server: is_uploaded_file stays enforced.
$router = $fixture . '/router.php';
file_put_contents($router, '<?php session_start(); $_SESSION = ["membre_id"=>123,"membre_email"=>"lab-test@example.test","is_admin"=>true,"csrf_token"=>"fixture-token"]; if (parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) !== "/panel-x9k3m/football-lab/action.php") { http_response_code(404); exit; } require __DIR__."/public_html/admin/football-lab/action.php";');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$socket) { throw new RuntimeException($errstr); }
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $router], [1 => ['file', $fixture . '/http.log', 'a'], 2 => ['file', $fixture . '/http.log', 'a']], $pipes);
try {
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $errstr, 0.1);
        if ($probe) { fclose($probe); break; }
        usleep(50000);
    }
    $upload = static function (string $bytes, string $token = 'fixture-token', string $filename = 'PackBall.csv') use ($address): array {
        $boundary = 'LabFixtureBoundary8192634';
        $body = '';
        foreach (['action' => 'packball_upload', 'csrf' => $token] as $key => $value) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
        }
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"packball\"; filename=\"$filename\"\r\nContent-Type: text/csv\r\n\r\n" . $bytes . "\r\n--" . $boundary . "--\r\n";
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary, 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
        $response = file_get_contents('http://' . $address . '/panel-x9k3m/football-lab/action.php', false, $ctx);
        return [$response, $http_response_header];
    };
    $before = count($fixtureStore->recent(123));
    [, $headers] = $upload($csv);
    check(strpos($headers[0], '303') !== false, 'Multipart upload redirects');
    $location = implode("\n", $headers);
    check(strpos($location, '/football-lab/index.php?id=') !== false, 'Upload goes directly to analysis, not mapping');
    check(count($fixtureStore->recent(123)) === $before + 1, 'Multipart upload saves one analysis');
    preg_match('/index\.php\?id=([a-f0-9]{32})/', $location, $uploadedId);
    $newest = $fixtureStore->get($uploadedId[1], 123);
    check(($newest['data']['import']['profile'] ?? '') === PackBall::PROFILE, 'Imported profile persisted');
    [, $headers] = $upload($csv, 'wrong');
    check(strpos($headers[0], '403') !== false && count($fixtureStore->recent(123)) === $before + 1, 'Upload remains protected by CSRF');
    [, $headers] = $upload('wrong;schema');
    check(strpos(implode("\n", $headers), '/football-lab/import.php') !== false && count($fixtureStore->recent(123)) === $before + 1, 'Invalid upload does not create an analysis');
    [, $headers] = $upload($csv, 'fixture-token', 'file.txt');
    check(count($fixtureStore->recent(123)) === $before + 1, 'Non-CSV extension rejected');
} finally { proc_terminate($process); proc_close($process); }

require __DIR__ . '/decision-engine.php';
