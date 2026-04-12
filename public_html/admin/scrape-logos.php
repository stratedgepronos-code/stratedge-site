<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
set_time_limit(600);
$base = __DIR__ . '/../assets/logos';
@mkdir($base, 0755, true);

$TEAMS = [
    'football' => ['paris-saint-germain'=>160,'olympique-marseille'=>176,'olympique-lyonnais'=>170,'as-monaco'=>174,'lille-osc'=>166,'stade-rennais'=>213,'ogc-nice'=>2664,'fc-nantes'=>180,'rc-strasbourg'=>183,'rc-lens'=>164,'montpellier'=>178,'stade-reims'=>2656,'toulouse-fc'=>2649,'stade-brestois'=>2658,'angers-sco'=>2655,'aj-auxerre'=>162,'le-havre-ac'=>2657,'saint-etienne'=>181,'manchester-city'=>382,'manchester-united'=>360,'liverpool'=>364,'chelsea'=>363,'arsenal'=>359,'tottenham-hotspur'=>367,'newcastle-united'=>361,'aston-villa'=>362,'west-ham-united'=>371,'brighton'=>331,'brentford'=>337,'crystal-palace'=>384,'everton'=>368,'fulham'=>370,'wolverhampton'=>380,'nottingham-forest'=>393,'bournemouth'=>349,'leicester-city'=>375,'ipswich-town'=>373,'southampton'=>376,'sunderland'=>366,'leeds-united'=>357,'burnley'=>379,'sheffield-united'=>398,'sheffield-wednesday'=>397,'norwich-city'=>381,'west-bromwich-albion'=>383,'middlesbrough'=>369,'coventry-city'=>352,'preston-north-end'=>386,'cardiff-city'=>347,'swansea-city'=>318,'bristol-city'=>344,'hull-city'=>355,'watford'=>395,'blackburn-rovers'=>336,'queens-park-rangers'=>389,'millwall'=>378,'portsmouth'=>385,'derby-county'=>353,'luton-town'=>301,'real-madrid'=>86,'fc-barcelona'=>83,'atletico-madrid'=>1068,'athletic-bilbao'=>93,'real-sociedad'=>89,'villarreal'=>102,'real-betis'=>244,'sevilla'=>243,'valencia'=>94,'getafe'=>2922,'girona'=>9812,'celta-vigo'=>85,'osasuna'=>97,'rayo-vallecano'=>101,'mallorca'=>84,'alaves'=>96,'las-palmas'=>98,'leganes'=>17534,'espanyol'=>88,'inter-milan'=>110,'ac-milan'=>103,'juventus'=>111,'napoli'=>114,'as-roma'=>104,'lazio'=>105,'atalanta'=>108,'fiorentina'=>109,'bologna'=>107,'torino'=>586,'udinese'=>115,'genoa'=>2311,'empoli'=>117,'hellas-verona'=>598,'parma'=>130,'cagliari'=>2315,'lecce'=>2314,'monza'=>4001,'venezia'=>2727,'como'=>2316,'bayern-munich'=>132,'borussia-dortmund'=>124,'bayer-leverkusen'=>131,'rb-leipzig'=>11420,'stuttgart'=>134,'eintracht-frankfurt'=>125,'wolfsburg'=>138,'borussia-monchengladbach'=>123,'hoffenheim'=>7911,'freiburg'=>126,'werder-bremen'=>137,'augsburg'=>7912,'bochum'=>122,'heidenheim'=>14911,'st-pauli'=>133,'holstein-kiel'=>2719,'benfica'=>1929,'porto'=>2950,'sporting-cp'=>2930,'psv-eindhoven'=>148,'feyenoord'=>142,'celtic'=>256,'rangers'=>257,'shakhtar-donetsk'=>2317,'club-brugge'=>2292,'anderlecht'=>1329,'galatasaray'=>645,'fenerbahce'=>1466,'besiktas'=>1523,'olympiakos'=>1492,'paok'=>1509],
    'nba' => ['atlanta-hawks'=>'atl','boston-celtics'=>'bos','brooklyn-nets'=>'bkn','charlotte-hornets'=>'cha','chicago-bulls'=>'chi','cleveland-cavaliers'=>'cle','dallas-mavericks'=>'dal','denver-nuggets'=>'den','detroit-pistons'=>'det','golden-state-warriors'=>'gs','houston-rockets'=>'hou','indiana-pacers'=>'ind','la-clippers'=>'lac','los-angeles-lakers'=>'lal','memphis-grizzlies'=>'mem','miami-heat'=>'mia','milwaukee-bucks'=>'mil','minnesota-timberwolves'=>'min','new-orleans-pelicans'=>'no','new-york-knicks'=>'ny','oklahoma-city-thunder'=>'okc','orlando-magic'=>'orl','philadelphia-76ers'=>'phi','phoenix-suns'=>'phx','portland-trail-blazers'=>'por','sacramento-kings'=>'sac','san-antonio-spurs'=>'sa','toronto-raptors'=>'tor','utah-jazz'=>'utah','washington-wizards'=>'wsh'],
    'nhl' => ['anaheim-ducks'=>'ana','boston-bruins'=>'bos','buffalo-sabres'=>'buf','calgary-flames'=>'cgy','carolina-hurricanes'=>'car','chicago-blackhawks'=>'chi','colorado-avalanche'=>'col','columbus-blue-jackets'=>'cbj','dallas-stars'=>'dal','detroit-red-wings'=>'det','edmonton-oilers'=>'edm','florida-panthers'=>'fla','los-angeles-kings'=>'la','minnesota-wild'=>'min','montreal-canadiens'=>'mtl','nashville-predators'=>'nsh','new-jersey-devils'=>'nj','new-york-islanders'=>'nyi','new-york-rangers'=>'nyr','ottawa-senators'=>'ott','philadelphia-flyers'=>'phi','pittsburgh-penguins'=>'pit','san-jose-sharks'=>'sj','seattle-kraken'=>'sea','st-louis-blues'=>'stl','tampa-bay-lightning'=>'tb','toronto-maple-leafs'=>'tor','utah-hockey-club'=>'utah','vancouver-canucks'=>'van','vegas-golden-knights'=>'vgs','washington-capitals'=>'wsh','winnipeg-jets'=>'wpg'],
    'mlb' => ['arizona-diamondbacks'=>'ari','atlanta-braves'=>'atl','baltimore-orioles'=>'bal','boston-red-sox'=>'bos','chicago-cubs'=>'chc','chicago-white-sox'=>'chw','cincinnati-reds'=>'cin','cleveland-guardians'=>'cle','colorado-rockies'=>'col','detroit-tigers'=>'det','houston-astros'=>'hou','kansas-city-royals'=>'kc','los-angeles-angels'=>'laa','los-angeles-dodgers'=>'lad','miami-marlins'=>'mia','milwaukee-brewers'=>'mil','minnesota-twins'=>'min','new-york-mets'=>'nym','new-york-yankees'=>'nyy','oakland-athletics'=>'oak','philadelphia-phillies'=>'phi','pittsburgh-pirates'=>'pit','san-diego-padres'=>'sd','san-francisco-giants'=>'sf','seattle-mariners'=>'sea','st-louis-cardinals'=>'stl','tampa-bay-rays'=>'tb','texas-rangers'=>'tex','toronto-blue-jays'=>'tor','washington-nationals'=>'wsh'],
];
$LOCAL = ['football'=>'football','nba'=>'basket','nhl'=>'hockey','mlb'=>'baseball'];

function fetch_url($u) {
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_USERAGENT=>'Mozilla/5.0']);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($code === 200 && $r && strlen($r) > 500) ? $r : null;
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== STRATEDGE Logo Scraper (ESPN CDN) ===\n\n";
$ok = 0; $ko = 0; $skipped = 0;
$mapping = ['football'=>[], 'basket'=>[], 'hockey'=>[], 'baseball'=>[]];

foreach ($TEAMS as $sport => $teams) {
    $localSport = $LOCAL[$sport];
    $dir = $base . '/' . $localSport;
    @mkdir($dir, 0755, true);
    echo "\n--- $sport (" . count($teams) . " equipes) ---\n";
    foreach ($teams as $slug => $id) {
        $outFile = $dir . '/' . $slug . '.png';
        if (is_file($outFile) && filesize($outFile) > 500) {
            $mapping[$localSport][$slug] = $slug;
            $skipped++;
            continue;
        }
        $url = ($sport === 'football')
            ? "https://a.espncdn.com/i/teamlogos/soccer/500/{$id}.png"
            : "https://a.espncdn.com/i/teamlogos/{$sport}/500/{$id}.png";
        $img = fetch_url($url);
        if ($img) {
            file_put_contents($outFile, $img);
            $mapping[$localSport][$slug] = $slug;
            echo "OK $slug.png\n";
            $ok++;
        } else {
            echo "FAIL $slug (id=$id)\n";
            $ko++;
        }
        usleep(100000);
    }
}
file_put_contents($base . '/mapping.json', json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n=== DONE: $ok telecharges, $skipped deja la, $ko echecs ===\n";
