<?php
// Genuine venue sample, missing API and zero-sample European season are different failures.
$snapshot = $enriched28;
$export = \StratEdgeLab\DecisionEngine::diagnosticExport($enriched28['analysis']);
check($enriched28 === $snapshot && $export['matches'][0]['sample']['home_n'] === 12, 'Diagnostic export preserves archive and reports actual venue sample');
check(count($export['matches'][0]['markets']) === 8, 'Diagnostic includes every market, not just selected bets');
$missingExport = \StratEdgeLab\DecisionEngine::diagnosticExport($missingRun['analysis']);
check($missingExport['matches'][0]['sample']['home_n'] === null, 'PackBall overall sample never mislabeled home-only');
check($missingExport['matches'][0]['diagnostic']['category'] === 'api_unavailable', 'Unavailable API explicitly classified');
check(!$missingExport['matches'][0]['markets'][0]['usable_for_selection'], 'Fallback computation clearly unusable for pricing');
$earlyTransport = static function ($endpoint, $params) use ($fixtureFooty, $homeFooty, $awayFooty): array {
    if ($endpoint === 'todays-matches') { return ['success'=>true,'data'=>[$fixtureFooty],'pager'=>['max_page'=>1]]; }
    if ($endpoint !== 'league-teams') { return ['success'=>true,'data'=>[],'pager'=>['max_page'=>1]]; }
    $h=$homeFooty; $a=$awayFooty;
    $h['stats']['seasonMatchesPlayed_home']=0; $a['stats']['seasonMatchesPlayed_away']=2;
    return ['success'=>true,'data'=>[$h,$a],'pager'=>['max_page'=>1]];
};
$early = \StratEdgeLab\PackBall::prepare($csv28, 'early-europe.csv', null, new \StratEdgeLab\FootyStats('test', $earlyTransport));
$em = $early['analysis']['matches'][0];
check($em['pick'] === null && $em['footystats']['sample'] === ['home_n'=>0,'away_n'=>2], 'Early European competition preserves zero and partial venue counts');
check($em['footystats']['season_id'] === 8001 && $em['footystats']['failure_code'] === 'sample_insufficient', 'Early competition preserves source and exact failure');
$legacy = $em; $legacy['footystats'] = ['status'=>'unavailable','message'=>'Échantillon FootyStats vide ou totaux de buts incomplets.'];
check(\StratEdgeLab\DecisionEngine::diagnostic($legacy)['category'] === 'sample_or_data_incomplete', 'Legacy conflated error is not retroactively invented as exact zero');
$cheapDiagnostic = \StratEdgeLab\DecisionEngine::diagnostic($noBetData['analysis']['matches'][0]);
check($cheapDiagnostic['state'] === 'no_bet' && $cheapDiagnostic['category'] === 'criteria_not_met', 'Sufficient data and insufficient prices distinguished from unavailable analysis');
$earlyId = $fixtureStore->create('analysis', $early, 123);
[$body] = $request('index.php', ['get'=>['id'=>$earlyId]]);
check(strpos($body, 'Matchs non retenus (1)') !== false && strpos($body, 'Analyse impossible faute de données') !== false, 'Zero-selection import exposes collapsed exact diagnostics');
check(strpos($body, 'id="lab-rejected" open') === false && strpos($body, 'Télécharger le diagnostic JSON') !== false, 'Diagnostics collapsed by default and export available without a pick');
$savePreview('index-diagnostics', $body);
$cutoff = strtotime('2026-09-16T12:00:00Z');
$hFixture = ['id'=>1,'homeID'=>9001,'awayID'=>9002,'competition_id'=>8000,'status'=>'complete','date_unix'=>$cutoff-86400,'homeGoalCount'=>2,'awayGoalCount'=>1,'no_home_away'=>0];
$historyRows = [$hFixture, $hFixture];
$future = $hFixture; $future['id']=2; $future['date_unix']=$cutoff+86400; $historyRows[]=$future;
$neutral = $hFixture; $neutral['id']=3; $neutral['no_home_away']=1; $historyRows[]=$neutral;
$wrongVenue = $hFixture; $wrongVenue['id']=4; $wrongVenue['homeID']=9002; $wrongVenue['awayID']=9001; $historyRows[]=$wrongVenue;
$oldHistory = $hFixture; $oldHistory['id']=5; $oldHistory['date_unix']=$cutoff-366*86400; $historyRows[]=$oldHistory;
$history = \StratEdgeLab\HistoricalContext::summarize($historyRows,9001,'home',8000,$cutoff);
check($history['n'] === 1 && $history['gf'] === 2 && $history['ga'] === 1, 'Detailed history excludes future, old, neutral, wrong venue and duplicates');
check($history['scope'] === 'descriptive_only' && $history['period_start'] === gmdate('c',$cutoff-86400), 'Historical source explicitly dated and descriptive');
$contradictory=$hFixture; $contradictory['homeGoalCount']=5;
$runtimeReject(static function () use ($hFixture,$contradictory,$cutoff) { \StratEdgeLab\HistoricalContext::summarize([$hFixture,$contradictory],9001,'home',8000,$cutoff); }, 'Contradictory historical score blocks that source');
