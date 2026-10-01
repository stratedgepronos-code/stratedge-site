<?php
declare(strict_types=1);
require __DIR__ . '/../../public_html/admin/football-lab/lib/FootyStats.php';

// The web worker has 128 MiB. Full historical API payloads must not accumulate
// across seasons even though the selection engine consumes only scores and IDs.
$client = new \StratEdgeLab\FootyStats('synthetic', static function ($endpoint, $params) {
    $rows = [];
    for ($i = 1; $i <= 300; $i++) {
        $row = ['id'=>$i,'competition_id'=>$params['season_id'],'homeID'=>1,'awayID'=>2,
            'homeGoalCount'=>2,'awayGoalCount'=>1,'status'=>'complete','date_unix'=>1700000000,'no_home_away'=>0];
        for ($j = 0; $j < 120; $j++) { $row['unused_stat_' . $j] = str_repeat((string)(($i + $j) % 10), 160); }
        $rows[] = $row;
    }
    return ['success'=>true,'data'=>$rows,'pager'=>['max_page'=>1]];
});
$request = new ReflectionMethod($client, 'request'); $request->setAccessible(true);
for ($season = 1; $season <= 20; $season++) {
    $result = $request->invoke($client, 'league-matches', ['season_id'=>$season]);
    if (count($result['data']) !== 300 || $result['data'][0]['homeGoalCount'] !== 2 || isset($result['data'][0]['unused_stat_0'])) {
        throw new RuntimeException('Fixture compaction changed scores or retained unused statistics.');
    }
}
echo 'OK — memory regression: ' . round(memory_get_peak_usage(true) / 1048576, 1) . " MiB\n";
