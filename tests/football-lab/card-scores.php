<?php
// Exercise the real authenticated controller and saved result events.
$scoreData = $enriched28;
$scoreMatch = $scoreData['analysis']['matches'][0];
$scoreMatch['kickoff'] = (new DateTimeImmutable('-3 hours'))->format('c');
$scoreMatch['pick'] = array_column($scoreMatch['candidates'], null, 'id')['ft_over_25'];
$scoreData['analysis']['matches'] = [$scoreMatch];
$scoreId = $fixtureStore->create('analysis', $scoreData, 123);
[$body] = $request('index.php', ['get'=>['id'=>$scoreId]]);
check(substr_count($body, 'name="score_home"') === 1 && substr_count($body, 'name="score_away"') === 1 && strpos($body, 'Enregistrer le score final') !== false, 'Analysis card exposes both score fields');
$postScore = ['action'=>'result', 'id'=>$scoreId, 'match'=>$scoreMatch['key'], 'return_to'=>'analyses', 'confirm_result'=>'1', 'csrf'=>'fixture-token', 'score_home'=>'2', 'score_away'=>'1'];
[$body, $err] = $request('action.php', ['post'=>$postScore]);
$scoreEvents = $fixtureStore->events($scoreId,123);
check(strpos($err,'HTTP_STATUS=303') !== false && count($scoreEvents) === 1 && $scoreEvents[0]['data']['outcome'] === 'won', 'Card score settles the actual market');
$postScore['score_home'] = '0'; $postScore['score_away'] = '0';
$request('action.php', ['post'=>$postScore]);
$scoreEvents = $fixtureStore->events($scoreId,123);
check(count($scoreEvents) === 2 && $scoreEvents[1]['data']['outcome'] === 'lost' && $scoreEvents[0]['data']['outcome'] === 'won', 'Score correction is appended and previous result preserved');
[$body] = $request('index.php', ['get'=>['id'=>$scoreId]]);
check(preg_match('/name="score_home"[^>]*value="0"/', $body) === 1 && strpos($body, 'Perdu') !== false && strpos($body, 'Corriger le score final') !== false, 'Recorded zero score and outcome are visible on card');
$savePreview('index-card-scores', $body);
foreach ([['score_home'=>'-1'], ['score_away'=>''], ['score_home'=>'1.5'], ['csrf'=>'wrong']] as $change) {
    $request('action.php', ['post'=>array_replace($postScore,$change)]);
    check(count($fixtureStore->events($scoreId,123)) === 2, 'Invalid score or CSRF cannot add a result');
}
$request('action.php', ['post'=>$postScore, 'session'=>['membre_id'=>999,'membre_email'=>'lab-test@example.test','is_admin'=>true,'csrf_token'=>'fixture-token']]);
check(count($fixtureStore->events($scoreId,123)) === 2, 'Another owner cannot settle this analysis');
$futureData = $scoreData; $futureData['analysis']['matches'][0]['kickoff'] = (new DateTimeImmutable('+1 hour'))->format('c');
$futureId = $fixtureStore->create('analysis',$futureData,123);
$request('action.php',['post'=>array_replace($postScore,['id'=>$futureId])]);
check(!$fixtureStore->events($futureId,123), 'Premature result blocked by server');
$noPickData = $scoreData; $noPickData['analysis']['matches'][0]['pick'] = null;
$noPickId = $fixtureStore->create('analysis',$noPickData,123);
$request('action.php',['post'=>array_replace($postScore,['id'=>$noPickId])]);
check($fixtureStore->events($noPickId,123)[0]['data']['outcome'] === 'no_bet', 'Score can be stored without inventing a selected bet');
$halfData = $scoreData; $halfData['analysis']['matches'][0]['pick']['period'] = 'h1';
$halfId = $fixtureStore->create('analysis',$halfData,123);
[$body] = $request('index.php',['get'=>['id'=>$halfId]]);
check(strpos($body,'name="score_home"') === false, 'Halftime market does not expose final-score card inputs');
$request('action.php',['post'=>array_replace($postScore,['id'=>$halfId])]);
check(!$fixtureStore->events($halfId,123), 'Forged card request cannot settle halftime from FT scores');
