<?php
/**
 * STRATEDGE — MLB team logos mapping
 * Source: a.espncdn.com/i/teamlogos/mlb/500/{abbr}.png
 * 30 équipes MLB · abréviations ESPN
 */

if (!function_exists('stratedge_mlb_logo')) {

function stratedge_mlb_logo(string $teamName): string {
    $name = mb_strtolower(trim($teamName), 'UTF-8');
    $name = preg_replace('/\s+/', ' ', $name);

    static $db = null;
    if ($db === null) {
        $db = [
            // AL East
            'baltimore orioles' => 'bal', 'orioles' => 'bal', 'os' => 'bal',
            'boston red sox' => 'bos', 'red sox' => 'bos',
            'new york yankees' => 'nyy', 'yankees' => 'nyy',
            'tampa bay rays' => 'tb', 'rays' => 'tb',
            'toronto blue jays' => 'tor', 'blue jays' => 'tor', 'jays' => 'tor',
            // AL Central
            'chicago white sox' => 'chw', 'white sox' => 'chw', 'whitesox' => 'chw',
            'cleveland guardians' => 'cle', 'guardians' => 'cle',
            'detroit tigers' => 'det', 'tigers' => 'det',
            'kansas city royals' => 'kc', 'royals' => 'kc',
            'minnesota twins' => 'min', 'twins' => 'min',
            // AL West
            'houston astros' => 'hou', 'astros' => 'hou',
            'los angeles angels' => 'laa', 'la angels' => 'laa', 'angels' => 'laa',
            'oakland athletics' => 'oak', 'athletics' => 'oak', 'oakland a s' => 'oak', 'a s' => 'oak',
            'seattle mariners' => 'sea', 'mariners' => 'sea',
            'texas rangers' => 'tex', 'rangers mlb' => 'tex', 'tex rangers' => 'tex',
            // NL East
            'atlanta braves' => 'atl', 'braves' => 'atl',
            'miami marlins' => 'mia', 'marlins' => 'mia',
            'new york mets' => 'nym', 'mets' => 'nym',
            'philadelphia phillies' => 'phi', 'phillies' => 'phi',
            'washington nationals' => 'wsh', 'nationals' => 'wsh', 'nats' => 'wsh',
            // NL Central
            'chicago cubs' => 'chc', 'cubs' => 'chc',
            'cincinnati reds' => 'cin', 'reds' => 'cin',
            'milwaukee brewers' => 'mil', 'brewers' => 'mil', 'brew crew' => 'mil',
            'pittsburgh pirates' => 'pit', 'pirates' => 'pit', 'buccos' => 'pit',
            'st louis cardinals' => 'stl', 'cardinals' => 'stl', 'cards' => 'stl', 'st. louis cardinals' => 'stl',
            // NL West
            'arizona diamondbacks' => 'ari', 'diamondbacks' => 'ari', 'd-backs' => 'ari',
            'colorado rockies' => 'col', 'rockies' => 'col',
            'los angeles dodgers' => 'lad', 'la dodgers' => 'lad', 'dodgers' => 'lad',
            'san diego padres' => 'sd', 'padres' => 'sd',
            'san francisco giants' => 'sf', 'giants' => 'sf', 'sfg' => 'sf',
        ];
    }

    if (isset($db[$name])) {
        $abbr = $db[$name];
        $localPath = __DIR__ . '/../assets/logos/baseball/' . $abbr . '.png';
        if (is_file($localPath) && filesize($localPath) > 500) {
            return '/assets/logos/baseball/' . $abbr . '.png';
        }
        return 'https://a.espncdn.com/i/teamlogos/mlb/500/' . $abbr . '.png';
    }

    return '';
}

}
