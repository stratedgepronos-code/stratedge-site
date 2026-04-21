<?php
/**
 * STRATEDGE — NHL team logos mapping
 * Source: a.espncdn.com/i/teamlogos/nhl/500/{abbr}.png
 * 32 équipes NHL · abréviations ESPN
 */

if (!function_exists('stratedge_nhl_logo')) {

function stratedge_nhl_logo(string $teamName): string {
    $name = mb_strtolower(trim($teamName), 'UTF-8');
    $name = preg_replace('/\s+/', ' ', $name);

    static $db = null;
    if ($db === null) {
        $db = [
            // Eastern — Atlantic
            'boston bruins' => 'bos', 'bruins' => 'bos',
            'buffalo sabres' => 'buf', 'sabres' => 'buf',
            'detroit red wings' => 'det', 'red wings' => 'det',
            'florida panthers' => 'fla', 'panthers' => 'fla',
            'montreal canadiens' => 'mtl', 'canadiens' => 'mtl', 'habs' => 'mtl',
            'ottawa senators' => 'ott', 'senators' => 'ott',
            'tampa bay lightning' => 'tb', 'lightning' => 'tb', 'tbl' => 'tb',
            'toronto maple leafs' => 'tor', 'maple leafs' => 'tor', 'leafs' => 'tor',
            // Eastern — Metropolitan
            'carolina hurricanes' => 'car', 'hurricanes' => 'car', 'canes' => 'car',
            'columbus blue jackets' => 'cbj', 'blue jackets' => 'cbj',
            'new jersey devils' => 'nj', 'devils' => 'nj', 'njd' => 'nj',
            'new york islanders' => 'nyi', 'islanders' => 'nyi',
            'new york rangers' => 'nyr', 'rangers' => 'nyr',
            'philadelphia flyers' => 'phi', 'flyers' => 'phi',
            'pittsburgh penguins' => 'pit', 'penguins' => 'pit', 'pens' => 'pit',
            'washington capitals' => 'wsh', 'capitals' => 'wsh', 'caps' => 'wsh',
            // Western — Central
            'chicago blackhawks' => 'chi', 'blackhawks' => 'chi', 'hawks nhl' => 'chi',
            'colorado avalanche' => 'col', 'avalanche' => 'col', 'avs' => 'col',
            'dallas stars' => 'dal', 'stars' => 'dal',
            'minnesota wild' => 'min', 'wild' => 'min',
            'nashville predators' => 'nsh', 'predators' => 'nsh', 'preds' => 'nsh',
            'st louis blues' => 'stl', 'blues' => 'stl', 'st. louis blues' => 'stl',
            'utah hockey club' => 'utah', 'utah mammoth' => 'utah', 'mammoth' => 'utah', 'uta hockey' => 'utah',
            'winnipeg jets' => 'wpg', 'jets' => 'wpg',
            // Western — Pacific
            'anaheim ducks' => 'ana', 'ducks' => 'ana',
            'calgary flames' => 'cgy', 'flames' => 'cgy',
            'edmonton oilers' => 'edm', 'oilers' => 'edm',
            'los angeles kings' => 'la', 'la kings' => 'la', 'kings nhl' => 'la', 'lak' => 'la',
            'san jose sharks' => 'sj', 'sharks' => 'sj', 'sjs' => 'sj',
            'seattle kraken' => 'sea', 'kraken' => 'sea',
            'vancouver canucks' => 'van', 'canucks' => 'van',
            'vegas golden knights' => 'vgk', 'golden knights' => 'vgk', 'vegas' => 'vgk',
        ];
    }

    if (isset($db[$name])) {
        $abbr = $db[$name];
        $localPath = __DIR__ . '/../assets/logos/hockey/' . $abbr . '.png';
        if (is_file($localPath) && filesize($localPath) > 500) {
            return '/assets/logos/hockey/' . $abbr . '.png';
        }
        return 'https://a.espncdn.com/i/teamlogos/nhl/500/' . $abbr . '.png';
    }

    return '';
}

}
