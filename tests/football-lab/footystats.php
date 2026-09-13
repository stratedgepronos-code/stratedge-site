<?php
use StratEdgeLab\FootyStats;

$row28 = ['Argentina', 'ARG', 'Synthetic League', (new DateTimeImmutable('+2 days', new DateTimeZone('Europe/Paris')))->format('d-m-Y') . ' 00:15', 'NS', 'Club Alpha', '', '', 'Club Beta',
    '1.54', '2.67', '5.35', '2.40', '1.43', '1.12', '10 | 10', '0.9 | 0.4', '1.0 | 0.7', '35', '90', '15', '10', '0', '50 | 20', '13.4 | 14.7', '4.2 | 4.6', '70', '20'];
$csv28 = $makeExport([$row28], \StratEdgeLab\PackBall::headers28());
$plain28 = \StratEdgeLab\PackBall::prepare($csv28, '28.csv');
$base28 = $plain28['analysis']['matches'][0];
check($plain28['import']['profile'] === \StratEdgeLab\PackBall::PROFILE_28, 'Current 28-column profile recognized');
check($base28['stats']['home_n'] === 10 && $base28['stats']['away_n'] === 10, 'Pair sample counts parsed');
near($base28['stats']['away_gf'], 0.4, 'Pair second value is visitor overall history');
$odds28 = array_column($base28['candidates'], 'odds', 'id');
foreach (['ft_over_15'=>1.54, 'ft_over_25'=>2.67, 'ft_over_35'=>5.35, 'ft_under_15'=>2.4, 'ft_under_25'=>1.43, 'ft_under_35'=>1.12] as $market=>$value) { near($odds28[$market], $value, 'Current price mapping ' . $market); }
check($base28['assessment']['features']['home_sot'] === null, 'Ambiguous screenshot column is never labeled SOT');
check($base28['pick'] === null, 'Missing league reference does not create a bet');
$fixtureFooty = ['id'=>7001, 'competition_id'=>8001, 'homeID'=>9001, 'awayID'=>9002, 'home_name'=>'Club Alpha FC', 'away_name'=>'Club Beta', 'date_unix'=>strtotime($base28['kickoff']), 'status'=>'incomplete'];
$homeFooty = ['id'=>9001, 'stats'=>['seasonMatchesPlayed_home'=>12, 'seasonGoalsTotal_home'=>24, 'seasonConcededNum_home'=>6,
    'seasonMatchesPlayed_away'=>99, 'seasonGoalsTotal_away'=>999, 'seasonConcededNum_away'=>999,
    'seasonMatchesPlayed_overall'=>20, 'seasonGoalsTotal_overall'=>60, 'seasonOver25Percentage_home'=>40, 'xg_for_avg_home'=>-1]];
$awayFooty = ['id'=>9002, 'stats'=>['seasonMatchesPlayed_away'=>10, 'seasonGoalsTotal_away'=>30, 'seasonConcededNum_away'=>18,
    'seasonMatchesPlayed_home'=>99, 'seasonGoalsTotal_home'=>999, 'seasonConcededNum_home'=>999,
    'seasonMatchesPlayed_overall'=>20, 'seasonGoalsTotal_overall'=>60, 'seasonOver25Percentage_away'=>60]];
$footyCalls = [];
$fakeFooty = static function ($endpoint, $params) use (&$footyCalls, $fixtureFooty, $homeFooty, $awayFooty): array {
    $footyCalls[] = [$endpoint, $params];
    if ($endpoint === 'todays-matches') { return ['success'=>true, 'data'=>[$fixtureFooty], 'pager'=>['max_page'=>1]]; }
    if ($endpoint === 'league-list') { return ['success'=>true, 'data'=>[['id'=>8001]], 'pager'=>['max_page'=>1]]; }
    check($endpoint === 'league-teams' && $params['season_id'] === 8001 && $params['include'] === 'stats', 'Correct season-specific stats endpoint');
    check($params['max_time'] <= time() && $params['max_time'] < $fixtureFooty['date_unix'], 'Provider request has pre-kickoff cutoff');
    return ['success'=>true, 'data'=>[$params['page'] === 1 ? $homeFooty : $awayFooty], 'pager'=>['max_page'=>2]];
};
$client = new FootyStats('synthetic-secret-not-a-real-key', $fakeFooty, $store);
$enriched28 = \StratEdgeLab\PackBall::prepare($csv28, '28.csv', null, $client);
$fm = $enriched28['analysis']['matches'][0];
check($enriched28['analysis']['footystats']['enriched'] === 1, 'Full pipeline enriches current CSV');
check($fm['country'] === 'Argentina', 'Country retained for scoped team aliases');
check($enriched28['analysis']['version'] === \StratEdgeLab\DecisionEngine::FOOTY_VERSION, 'Venue estimates get their own tracking version');
check($fm['stats']['home_n'] === 12 && $fm['stats']['away_n'] === 10, 'Actual venue sample sizes replace general samples');
near($fm['stats']['home_gf'], 1.5, 'GoalsTotal minus conceded, divided by home sample');
near($fm['stats']['away_gf'], 1.2, 'Away-only scored average');
near($fm['stats']['away_ga'], 1.8, 'Away-only conceded average');
near($fm['packball_stats']['home_gf'], 0.9, 'Original PackBall stats remain archived');
near($fm['assessment']['features']['league_avg'], 3, 'League baseline uses complete paginated overall totals');
near($fm['assessment']['features']['over25'], 1080/22, 'Frequency control uses same venue samples');
check($fm['footystats']['home']['xg_for'] === null, 'Negative missing xG sentinel remains unknown');
check(array_column($fm['candidates'], 'odds', 'id') === $odds28, 'FootyStats never replaces imported odds');
check($footyCalls[0][1]['date'] === gmdate('Y-m-d', strtotime($base28['kickoff'])) && $footyCalls[0][1]['timezone'] === 'Etc/UTC', 'Paris midnight requests the correct preceding UTC date');
check(strpos(json_encode($enriched28), 'synthetic-secret') === false, 'No credential in archived analysis');
$beforeCalls = count($footyCalls);
\StratEdgeLab\PackBall::prepare($csv28, 'again.csv', null, new FootyStats('synthetic-secret-not-a-real-key', $fakeFooty, $store));
check(count($footyCalls) === $beforeCalls, 'Shared cache avoids repeated quota consumption');
$store->cachePut('expired', ['test'=>1], time()-1);
check($store->cacheGet('expired') === null, 'Expired provider cache not used');
new FootyStats('another-test-key', $fakeFooty, $store);
$runtimeReject = static function (callable $fn, string $label): void {
    try { $fn(); } catch (RuntimeException $e) { check(true, $label); return; }
    throw new RuntimeException('Expected provider rejection: ' . $label);
};
$duplicateFixture = $fixtureFooty; $duplicateFixture['id'] = 7002;
$runtimeReject(static function () use ($client, $base28, $fixtureFooty, $duplicateFixture) { $client->findFixture($base28, [$fixtureFooty, $duplicateFixture]); }, 'Ambiguous fixture rejected');
$reversed = $fixtureFooty; $reversed['home_name'] = 'Club Beta'; $reversed['away_name'] = 'Club Alpha';
$runtimeReject(static function () use ($client, $base28, $reversed) { $client->findFixture($base28, [$reversed]); }, 'Reversed home/away rejected');
$postponed = $fixtureFooty; $postponed['status'] = 'suspended';
$runtimeReject(static function () use ($client, $base28, $postponed) { $client->findFixture($base28, [$postponed]); }, 'Suspended fixture rejected');
$emptySplit = $homeFooty; $emptySplit['stats']['seasonMatchesPlayed_home'] = 0;
$runtimeReject(static function () use ($emptySplit) { FootyStats::split($emptySplit, 'home'); }, 'Zero venue sample rejected');
$missing = new FootyStats('', static function () { throw new RuntimeException('Must not request without a key'); });
$missingRun = \StratEdgeLab\PackBall::prepare($csv28, 'missing.csv', null, $missing);
check($missingRun['analysis']['footystats']['unavailable'] === 1 && $missingRun['analysis']['matches'][0]['pick'] === null, 'Missing key gives explicit abstention and saved report');
$badApi = new FootyStats('test', static function () { return ['success'=>false, 'message'=>'sensitive-provider-error', 'data'=>[]]; });
$badRun = \StratEdgeLab\PackBall::prepare($csv28, 'bad.csv', null, $badApi);
check(strpos(json_encode($badRun), 'sensitive-provider-error') === false && $badRun['analysis']['footystats']['unavailable'] === 1, 'Provider errors sanitized');
$emptyApi = new FootyStats('test', static function () { return ['success'=>true, 'data'=>[], 'pager'=>['max_page'=>1]]; });
check(\StratEdgeLab\PackBall::prepare($csv28, 'none.csv', null, $emptyApi)['analysis']['matches'][0]['pick'] === null, 'No fixture does not trigger fuzzy association');
$idFooty = $fixtureStore->create('analysis', $enriched28, 123);
[$body] = $request('match.php', ['get'=>['id'=>$idFooty, 'match'=>$fm['key']]]);
check(strpos($body, 'Bilans séparés par lieu') !== false && strpos($body, '7001') !== false, 'Actual enriched detail view renders provider provenance');
$savePreview('match-footystats', $body);
[$body, $err] = $request('action.php', ['post'=>['action'=>'footystats_check', 'csrf'=>'wrong']]);
check(strpos($err, 'HTTP_STATUS=403') !== false, 'Connection check requires CSRF');
$completed28 = $row28; $completed28[4] = 'FT'; $completed28[6] = '1'; $completed28[7] = '0';
$report28 = \StratEdgeLab\ResultsImport::apply($fixtureStore, 123, $makeExport([$completed28], \StratEdgeLab\PackBall::headers28()), 'scores28.csv', new DateTimeImmutable('+4 days'));
check(is_array($report28['messages']), 'Results importer accepts new layout without overwriting forecasts');

// Regression: live /league-teams omits competition_id. The request still defines the season;
// an explicitly conflicting season or a duplicated team must remain rejected.
$wrongSeason = new FootyStats('wrong-season-test', static function ($endpoint, $params) use ($fakeFooty): array {
    $response = $fakeFooty($endpoint, $params);
    if ($endpoint === 'league-teams') { $response['data'][0]['competition_id'] = 99999; }
    return $response;
});
$wrongRun = \StratEdgeLab\PackBall::prepare($csv28, 'wrong-season.csv', null, $wrongSeason);
check($wrongRun['analysis']['footystats']['unavailable'] === 1, 'Explicit conflicting season still rejected');
$duplicateTeam = new FootyStats('duplicate-team-test', static function ($endpoint, $params) use ($fakeFooty): array {
    $response = $fakeFooty($endpoint, $params);
    if ($endpoint === 'league-teams') { $response['data'][] = $response['data'][0]; }
    return $response;
});
check(\StratEdgeLab\PackBall::prepare($csv28, 'duplicate.csv', null, $duplicateTeam)['analysis']['footystats']['unavailable'] === 1, 'Duplicated provider team still rejected');
$interMatch = $base28; $interMatch['home'] = 'Inter'; $interMatch['country'] = 'Italy';
$interFixture = $fixtureFooty; $interFixture['home_name'] = 'Inter Milan'; $interFixture['homeID'] = 470;
check($client->findFixture($interMatch, [$interFixture])['homeID'] === 470, 'Verified country-scoped alias resolves provider ID');
$interMatch['country'] = 'Brazil';
$runtimeReject(static function () use ($client, $interMatch, $interFixture) { $client->findFixture($interMatch, [$interFixture]); }, 'Alias cannot match another country');
$savedCopy = $plain28;
$replayed = \StratEdgeLab\PackBall::replay($plain28, new FootyStats('replay-test', $fakeFooty));
check($plain28 === $savedCopy && $replayed['analysis']['footystats']['enriched'] === 1, 'Replay enriches stored CSV without mutating source analysis');
check(\StratEdgeLab\DecisionEngine::diagnostic($missingRun['analysis']['matches'][0])['state'] === 'data_missing', 'Missing enrichment distinguished from rejected prices');
$noValue = $fm; $noValue['pick'] = null; $noValue['assessment']['issues'] = [];
check(strpos(\StratEdgeLab\DecisionEngine::diagnostic($noValue)['message'], '6 marchés cotés') !== false, 'Diagnostics count six real prices, not eight markets');
$missingId = $fixtureStore->create('analysis', $missingRun, 123);
[$body] = $request('index.php', ['get'=>['id'=>$missingId]]);
check(strpos($body, 'Statistiques FootyStats indisponibles') !== false && strpos($body, 'Clé FootyStats absente') !== false && strpos($body, 'Relancer avec FootyStats') !== false, 'Saved missing-data card shows actionable cause and replay button');
check(strpos($body, 'Aucun des huit marchés') === false && strpos($body, '<span>Contexte à examiner</span>') === false, 'Unanalyzed match is not shown as reviewed or context-ready');
$savePreview('index-footystats-missing', $body);
[$body, $err] = $request('action.php', ['post'=>['action'=>'packball_retry', 'id'=>$missingId, 'csrf'=>'wrong']]);
check(strpos($err, 'HTTP_STATUS=403') !== false, 'Replay requires CSRF');
[$body, $err] = $request('action.php', ['post'=>['action'=>'packball_retry', 'id'=>$missingId, 'csrf'=>'fixture-token']]);
check(strpos($err, 'HTTP_STATUS=303') !== false && $fixtureStore->get($missingId,123)['data'] === $missingRun, 'Replay controller preserves original saved report');
