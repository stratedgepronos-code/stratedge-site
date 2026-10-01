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

// Reconstructed CSV cells and original provider snapshot from the real archived import.
$realFiles = [];
foreach (['sept17-gpt-33.csv','sept17-gpt2-28.csv'] as $file) { $realFiles[] = ['name'=>$file,'csv'=>file_get_contents(__DIR__ . '/' . $file)]; }
$real = \StratEdgeLab\PackBall::preparePair($realFiles, new DateTimeImmutable('2026-09-16T20:52:11Z'));
$recorded = json_decode(file_get_contents(__DIR__ . '/sept17-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
$byKey = array_column($recorded['matches'], null, 'key');
foreach ($real['analysis']['matches'] as &$rm) {
    $rm['packball_stats'] = $rm['stats'];
    $rm['stats'] = $byKey[$rm['key']]['stats']; $rm['footystats'] = $byKey[$rm['key']]['footystats'];
}
unset($rm);
$real['analysis']['footystats'] = $recorded['footystats'];
$reviewed = \StratEdgeLab\DecisionEngine::review($real['analysis'], $real['tables']['stats']);
$counts = [];
foreach ($reviewed['matches'] as $rm) {
    $category = \StratEdgeLab\DecisionEngine::diagnostic($rm)['category'];
    $counts[$category] = ($counts[$category] ?? 0) + 1;
}
check(count($reviewed['matches']) === 19 && !$reviewed['errors'], 'Both real September 17 CSV tables import 19 matches without errors');
check($counts === ['sample_insufficient'=>5,'criteria_not_met'=>2,'api_match_missing'=>6,'sample_or_data_incomplete'=>6] ||
    ($counts['sample_insufficient'] === 5 && $counts['criteria_not_met'] === 2 && $counts['api_match_missing'] === 6 && $counts['sample_or_data_incomplete'] === 6), 'Real archived failures: 5 small samples, 2 price rejections, 6 missing mappings, 6 ambiguous incomplete samples');
foreach (['Real Sociedad'=>1.72,'Viktoria Plzeň'=>1.79] as $team=>$price) {
    $rm = array_values(array_filter($reviewed['matches'], static function ($m) use ($team) { return $m['home'] === $team; }))[0];
    $c = array_column($rm['candidates'],null,'id')['ft_over_25'];
    near($c['odds'], $price, 'Real proposed over-2.5 price remains unchanged');
    check($rm['pick'] === null && count($c['reasons']) === 1 && strpos($c['reasons'][0], 'FootyStats') !== false, 'Real qualitative lead blocked only by provider data, never forced');
}
check(!array_filter($reviewed['matches'], static function ($m) { return $m['pick'] !== null; }), 'Original provider snapshot still produces zero selections with unchanged thresholds');

$historyTransport = static function ($endpoint, $params) use ($earlyTransport, $hFixture): array {
    if ($endpoint === 'league-list') { return ['success'=>true,'data'=>[
        ['name'=>'Europe UEFA Europa League','country'=>'Europe','season'=>[['id'=>8001,'year'=>date('Y').(date('Y')+1)]]],
        // Production league-list omits league_name: full country-prefixed name is supported.
        ['name'=>'England Premier League','country'=>'England','season'=>[['id'=>8000,'year'=>(date('Y')-1).date('Y')]]]
    ]]; }
    if ($endpoint === 'team') { return ['success'=>true,'data'=>[['id'=>$params['team_id'],'country'=>'England','competition_id'=>8000]]]; }
    if ($endpoint === 'league-matches') {
        $rows=[];
        for($i=1;$i<=10;$i++) { $r=$hFixture; $r['id']=$i; $r['date_unix']=time()-86400*$i; $rows[]=$r; }
        return ['success'=>true,'data'=>$rows,'pager'=>['max_page'=>1]];
    }
    return $earlyTransport($endpoint,$params);
};
$historyRun=\StratEdgeLab\PackBall::prepare($csv28,'domestic-history.csv',null,new \StratEdgeLab\FootyStats('test',$historyTransport));
$hm=$historyRun['analysis']['matches'][0];
check($hm['historical_context']['status'] === 'descriptive_only' && $hm['historical_context']['sources']['home'][0]['n'] === 10, 'Domestic previous-season detailed history discovered and venue counts computed');
check($hm['historical_context']['sources']['away'][0]['n'] === 10 && $hm['pick'] === null, 'Sufficient alternative history never silently promotes a European bet');
check($hm['footystats']['sample']['home_n'] === 0, 'Historical descriptive sample does not overwrite primary competition evidence');

$aliasMatch=$base28; $aliasMatch['country']='Europe'; $aliasMatch['away']='NEC Nijmegen';
$aliasFixture=$fixtureFooty; $aliasFixture['away_name']='NEC'; $aliasFixture['awayID']=379;
check($client->findFixture($aliasMatch,[$aliasFixture])['awayID'] === 379, 'Verified September 17 NEC provider identity resolves in European scope');
$aliasMatch['country']='Brazil';
$runtimeReject(static function () use ($client,$aliasMatch,$aliasFixture) { $client->findFixture($aliasMatch,[$aliasFixture]); }, 'European verified alias cannot resolve an unrelated scope');
