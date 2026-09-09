<?php
use StratEdgeLab\DecisionEngine;
// Exact known NB probabilities: Gamma(2, rate=1) predicts p0=.25, p1=.25, p2=.1875.
$pmf = DecisionEngine::pmf(2, 1, 200);
near($pmf[0], 0.25, 'Gamma-Poisson zero probability');
near($pmf[1], 0.25, 'Gamma-Poisson one probability');
near($pmf[2], 0.1875, 'Gamma-Poisson two probability');
near(array_sum($pmf), 1.0, 'Predictive mass normalization');
$mean = 0; $second = 0;
foreach ($pmf as $k => $p) { $mean += $k * $p; $second += $k * $k * $p; }
near($mean, 2, 'Predictive mean'); near($second - $mean * $mean, 4, 'Predictive variance includes parameter uncertainty');
$ps = DecisionEngine::probabilities(['home' => ['shape' => 2, 'rate' => 1], 'away' => ['shape' => 2, 'rate' => 1]]);
near($ps['ft_under_15'], 0.1875, 'Total goals convolution');
near($ps['ft_btts_yes'], 0.5625, 'Both scoring under predictive distribution');
foreach (['15','25','35'] as $line) { near($ps['ft_under_' . $line] + $ps['ft_over_' . $line], 1, 'Complementary total markets'); }
check($ps['ft_over_15'] > $ps['ft_over_25'] && $ps['ft_over_25'] > $ps['ft_over_35'], 'Nested lines consistent');
rejects(static function () { DecisionEngine::pmf(0, 1); }, 'Invalid predictive shape rejected');
$preparedV2 = PackBall::prepare($makeExport([$exportRow]), 'v2.csv');
$v2match = $preparedV2['analysis']['matches'][0];
check($preparedV2['analysis']['version'] === DecisionEngine::VERSION, 'Distinct V2 version for independent tracking');
foreach ($v2match['candidates'] as $candidate) {
    check($candidate['stress_probability'] <= $candidate['probability'], 'Pessimistic scenario never exceeds base prediction');
    check($candidate['eligible'] === !$candidate['reasons'], 'Every rejection has explicit reasons');
    near($candidate['ev'], $candidate['probability'] * $candidate['odds'] - 1, 'Model EV calculation');
}
check($v2match['pick'] !== null && $v2match['pick']['odds'] >= 1.6, 'Controlled positive opportunity can pass, not always abstain');
$cheap = $exportRow; for ($i = 11; $i <= 18; $i++) { $cheap[$i] = '1.10'; }
check(PackBall::prepare($makeExport([$cheap]), 'cheap.csv')['analysis']['matches'][0]['pick'] === null, 'No low-odds selections');
$lowSample = $exportRow; $lowSample[19] = '4';
check(PackBall::prepare($makeExport([$lowSample]), 'few.csv')['analysis']['matches'][0]['pick'] === null, 'Insufficient samples block selection');
$missingLeague = $exportRow; $missingLeague[26] = '';
check(PackBall::prepare($makeExport([$missingLeague]), 'missing.csv')['analysis']['matches'][0]['pick'] === null, 'Missing league baseline blocks selection');
$badShots = $exportRow; $badShots[38] = '100';
check(PackBall::prepare($makeExport([$badShots]), 'shots.csv')['analysis']['matches'][0]['pick'] === null, 'Impossible shot data blocks selection');
$v2id = $fixtureStore->create('analysis', $preparedV2, 123);
[$body] = $request('match.php', ['get' => ['id' => $v2id, 'match' => $v2match['key']]]);
check(strpos($body, 'Diagnostic du moteur V2') !== false && strpos($body, 'Tirs cadrés produits') !== false, 'Actual V2 diagnostic view renders');
$savePreview('match-v2', $body);
[$body] = $request('index.php', ['get' => ['id' => $v2id]]);
check(strpos($body, 'Cotes de 1,60 à 3,50') !== false, 'Selection policy visible in actual list');
$savePreview('index-v2', $body);
[$body] = $request('history.php');
check(strpos($body, DecisionEngine::VERSION) !== false && strpos($body, 'Le premier résultat lance le suivi.') !== false, 'V2 tracking does not borrow the earlier settled V1 results');
$noBetData = PackBall::prepare($makeExport([$cheap]), 'cheap.csv');
$noBetId = $fixtureStore->create('analysis', $noBetData, 123);
[$body] = $request('match.php', ['get' => ['id' => $noBetId, 'match' => $noBetData['analysis']['matches'][0]['key']]]);
check(strpos($body, 'Pourquoi le moteur s’abstient') !== false, 'No-bet diagnostic renders without a selected candidate');
