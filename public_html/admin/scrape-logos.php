<?php
// STRATEDGE — Scraper tous les logos d'équipes depuis TheSportsDB
// Accès admin uniquement. Télécharge en /assets/logos/{sport}/{slug}.png
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
set_time_limit(600);

$base = __DIR__ . '/../assets/logos';
@mkdir($base, 0755, true);

$TEAMS = [
    'football' => [
        'Paris Saint-Germain','Olympique de Marseille','Olympique Lyonnais','AS Monaco','Lille OSC',
        'Stade Rennais','OGC Nice','FC Nantes','RC Strasbourg','RC Lens','Montpellier HSC',
        'Stade de Reims','Toulouse FC','Stade Brestois 29','Angers SCO','AJ Auxerre','Le Havre AC',
        'Saint-Etienne','Manchester City','Manchester United','Liverpool','Chelsea','Arsenal',
        'Tottenham Hotspur','Newcastle United','Aston Villa','West Ham United','Brighton & Hove Albion',
        'Brentford','Crystal Palace','Everton','Fulham','Wolverhampton Wanderers','Nottingham Forest',
        'AFC Bournemouth','Leicester City','Ipswich Town','Southampton','Sunderland','Leeds United',
        'Burnley','Sheffield United','Sheffield Wednesday','Norwich City','West Bromwich Albion',
        'Middlesbrough','Coventry City','Preston North End','Cardiff City','Swansea City',
        'Bristol City','Hull City','Watford','Blackburn Rovers','Queens Park Rangers','Stoke City',
        'Millwall','Portsmouth','Derby County','Luton Town','Plymouth Argyle','Oxford United',
        'Real Madrid','FC Barcelona','Atletico Madrid','Athletic Bilbao','Real Sociedad','Villarreal CF',
        'Real Betis','Sevilla FC','Valencia CF','Getafe CF','Girona FC','Celta Vigo','CA Osasuna',
        'Rayo Vallecano','RCD Mallorca','Deportivo Alaves','UD Las Palmas','CD Leganes','RCD Espanyol',
        'Real Valladolid','Inter Milan','AC Milan','Juventus','Napoli','AS Roma','Lazio','Atalanta',
        'Fiorentina','Bologna','Torino','Udinese','Genoa','Empoli','Hellas Verona','Parma','Cagliari',
        'Lecce','Monza','Venezia','Como','Bayern Munich','Borussia Dortmund','Bayer Leverkusen',
        'RB Leipzig','VfB Stuttgart','Eintracht Frankfurt','VfL Wolfsburg','Borussia Monchengladbach',
        'TSG Hoffenheim','SC Freiburg','Mainz 05','Werder Bremen','FC Augsburg','Union Berlin',
        'VfL Bochum','Heidenheim','FC St. Pauli','Holstein Kiel','Benfica','FC Porto','Sporting CP',
        'Ajax','PSV Eindhoven','Feyenoord','Celtic','Rangers','Shakhtar Donetsk','Club Brugge',
        'Anderlecht','Galatasaray','Fenerbahce','Besiktas','Olympiakos','PAOK','Red Star Belgrade',
        'Dinamo Zagreb','KRC Genk',
    ],
    'basket' => ['Atlanta Hawks','Boston Celtics','Brooklyn Nets','Charlotte Hornets','Chicago Bulls','Cleveland Cavaliers','Dallas Mavericks','Denver Nuggets','Detroit Pistons','Golden State Warriors','Houston Rockets','Indiana Pacers','Los Angeles Clippers','Los Angeles Lakers','Memphis Grizzlies','Miami Heat','Milwaukee Bucks','Minnesota Timberwolves','New Orleans Pelicans','New York Knicks','Oklahoma City Thunder','Orlando Magic','Philadelphia 76ers','Phoenix Suns','Portland Trail Blazers','Sacramento Kings','San Antonio Spurs','Toronto Raptors','Utah Jazz','Washington Wizards'],
    'hockey' => ['Anaheim Ducks','Boston Bruins','Buffalo Sabres','Calgary Flames','Carolina Hurricanes','Chicago Blackhawks','Colorado Avalanche','Columbus Blue Jackets','Dallas Stars','Detroit Red Wings','Edmonton Oilers','Florida Panthers','Los Angeles Kings','Minnesota Wild','Montreal Canadiens','Nashville Predators','New Jersey Devils','New York Islanders','New York Rangers','Ottawa Senators','Philadelphia Flyers','Pittsburgh Penguins','San Jose Sharks','Seattle Kraken','St. Louis Blues','Tampa Bay Lightning','Toronto Maple Leafs','Utah Hockey Club','Vancouver Canucks','Vegas Golden Knights','Washington Capitals','Winnipeg Jets'],
    'baseball' => ['Arizona Diamondbacks','Atlanta Braves','Baltimore Orioles','Boston Red Sox','Chicago Cubs','Chicago White Sox','Cincinnati Reds','Cleveland Guardians','Colorado Rockies','Detroit Tigers','Houston Astros','Kansas City Royals','Los Angeles Angels','Los Angeles Dodgers','Miami Marlins','Milwaukee Brewers','Minnesota Twins','New York Mets','New York Yankees','Oakland Athletics','Philadelphia Phillies','Pittsburgh Pirates','San Diego Padres','San Francisco Giants','Seattle Mariners','St. Louis Cardinals','Tampa Bay Rays','Texas Rangers','Toronto Blue Jays','Washington Nationals'],
];
$filter = ['football'=>'Soccer','basket'=>'Basketball','hockey'=>'Ice Hockey','baseball'=>'Baseball'];

function slugify($n){$s=strtolower(trim($n));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
function fetch_url($u){$ch=curl_init($u);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'StratEdgeBot/1.0']);$r=curl_exec($ch);curl_close($ch);return $r;}

header('Content-Type: text/plain; charset=utf-8');
echo "=== STRATEDGE Logo Scraper ===\n\n";
$ok=0; $ko=0; $mapping=['football'=>[],'basket'=>[],'hockey'=>[],'baseball'=>[]];

// Limite de téléchargements par passage (pour éviter rate-limit TheSportsDB)
$batchLimit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 15;
$startOffset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$globalCount = 0;
$downloaded = 0;
$skipped = 0;

// Charger mapping existant pour skip intelligent
$existingMapping = [];
$mapFile = $base . '/mapping.json';
if (is_file($mapFile)) {
    $existingMapping = json_decode(@file_get_contents($mapFile), true) ?: [];
}
$mapping = array_merge(['football'=>[],'basket'=>[],'hockey'=>[],'baseball'=>[]], $existingMapping);

foreach ($TEAMS as $sport => $teams) {
    $dir = $base.'/'.$sport;
    @mkdir($dir, 0755, true);
    $expected = $filter[$sport];
    echo "\n--- $sport (".count($teams)." équipes) ---\n";
    foreach ($teams as $team) {
        $globalCount++;
        if ($globalCount <= $startOffset) continue;
        if ($downloaded >= $batchLimit) break 2; // stop tout

        $slug = slugify($team);
        $existingFile = $dir.'/'.$slug.'.png';
        if (is_file($existingFile) && filesize($existingFile) > 500) {
            $mapping[$sport][$slug] = $team;
            $skipped++;
            continue;
        }

        $url = 'https://www.thesportsdb.com/api/v1/json/123/searchteams.php?t='.urlencode($team);
        $json = fetch_url($url);
        if (!$json) { echo "✗ $team (no response)\n"; $ko++; continue; }
        $data = json_decode($json, true);
        if (empty($data['teams'])) { echo "✗ $team (not found)\n"; $ko++; continue; }
        $match = null;
        foreach ($data['teams'] as $t) { if (($t['strSport']??'')===$expected) { $match=$t; break; } }
        if (!$match) $match = $data['teams'][0];
        $badge = $match['strBadge'] ?? $match['strTeamBadge'] ?? '';
        if (!$badge) { echo "✗ $team (no badge)\n"; $ko++; continue; }
        $img = fetch_url($badge);
        if (!$img || strlen($img)<500) { echo "✗ $team (bad image)\n"; $ko++; continue; }
        $slug = slugify($team);
        file_put_contents($dir.'/'.$slug.'.png', $img);
        $mapping[$sport][$slug] = $team;
        $downloaded++;
        echo "✓ $slug.png ($team)\n";
        usleep(1500000); // 1.5s anti rate-limit
    }
}

file_put_contents($base.'/mapping.json', json_encode($mapping, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "\n=== DONE: $ok OK, $ko échecs ===\n";
echo "Mapping: /assets/logos/mapping.json\n";
