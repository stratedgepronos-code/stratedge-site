<?php
/**
 * STRATEDGE — NBA team logos mapping
 * Source: a.espncdn.com/i/teamlogos/nba/500/{abbr}.png
 * 30 équipes NBA · abréviations ESPN
 */

if (!function_exists('stratedge_nba_logo')) {

function stratedge_nba_logo(string $teamName): string {
    $name = mb_strtolower(trim($teamName), 'UTF-8');
    $name = preg_replace('/\s+/', ' ', $name);

    static $db = null;
    if ($db === null) {
        $db = [
            // Eastern — Atlantic
            'boston celtics' => 'bos', 'celtics' => 'bos',
            'brooklyn nets' => 'bkn', 'nets' => 'bkn',
            'new york knicks' => 'ny', 'knicks' => 'ny', 'nyk' => 'ny',
            'philadelphia 76ers' => 'phi', '76ers' => 'phi', 'sixers' => 'phi',
            'toronto raptors' => 'tor', 'raptors' => 'tor',
            // Eastern — Central
            'chicago bulls' => 'chi', 'bulls' => 'chi',
            'cleveland cavaliers' => 'cle', 'cavaliers' => 'cle', 'cavs' => 'cle',
            'detroit pistons' => 'det', 'pistons' => 'det',
            'indiana pacers' => 'ind', 'pacers' => 'ind',
            'milwaukee bucks' => 'mil', 'bucks' => 'mil',
            // Eastern — Southeast
            'atlanta hawks' => 'atl', 'hawks' => 'atl',
            'charlotte hornets' => 'cha', 'hornets' => 'cha',
            'miami heat' => 'mia', 'heat' => 'mia',
            'orlando magic' => 'orl', 'magic' => 'orl',
            'washington wizards' => 'wsh', 'wizards' => 'wsh', 'was' => 'wsh',
            // Western — Northwest
            'denver nuggets' => 'den', 'nuggets' => 'den',
            'minnesota timberwolves' => 'min', 'timberwolves' => 'min', 'wolves' => 'min',
            'oklahoma city thunder' => 'okc', 'thunder' => 'okc',
            'portland trail blazers' => 'por', 'trail blazers' => 'por', 'blazers' => 'por',
            'utah jazz' => 'utah', 'jazz' => 'utah', 'uta' => 'utah',
            // Western — Pacific
            'golden state warriors' => 'gs', 'warriors' => 'gs', 'gsw' => 'gs',
            'los angeles clippers' => 'lac', 'la clippers' => 'lac', 'clippers' => 'lac',
            'los angeles lakers' => 'lal', 'la lakers' => 'lal', 'lakers' => 'lal',
            'phoenix suns' => 'phx', 'suns' => 'phx',
            'sacramento kings' => 'sac', 'kings' => 'sac',
            // Western — Southwest
            'dallas mavericks' => 'dal', 'mavericks' => 'dal', 'mavs' => 'dal',
            'houston rockets' => 'hou', 'rockets' => 'hou',
            'memphis grizzlies' => 'mem', 'grizzlies' => 'mem',
            'new orleans pelicans' => 'no', 'pelicans' => 'no', 'nop' => 'no',
            'san antonio spurs' => 'sa', 'spurs' => 'sa',
        ];
    }

    if (isset($db[$name])) {
        $abbr = $db[$name];
        $localPath = __DIR__ . '/../assets/logos/basket/' . $abbr . '.png';
        if (is_file($localPath) && filesize($localPath) > 500) {
            return '/assets/logos/basket/' . $abbr . '.png';
        }
        return 'https://a.espncdn.com/i/teamlogos/nba/500/' . $abbr . '.png';
    }

    return '';
}

}
