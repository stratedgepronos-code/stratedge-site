<?php
/**
 * StratEdge — Base locale de logos d'équipes (foot + NBA + NHL + MLB).
 * Mappings nom → URL ESPN CDN vérifiée.
 * Utilisé en PRIORITÉ par stratedge_fetch_team_logo_url() avant APIs externes.
 */

if (!function_exists('stratedge_local_team_logo')) {

function stratedge_local_team_logo($teamName, $sport = 'football') {
    $name = strtolower(trim(preg_replace('/[^\w\s]/u', ' ', (string)$teamName)));
    $name = preg_replace('/\s+/', ' ', $name);

    $db = stratedge_team_logos_db();
    $sportKey = strtolower($sport);
    $map = $db[$sportKey] ?? [];

    // Match exact d'abord
    foreach ($map as $keys => $url) {
        $aliases = explode('|', $keys);
        foreach ($aliases as $alias) {
            if ($name === trim($alias)) return $url;
        }
    }

    // Match partiel (contient)
    foreach ($map as $keys => $url) {
        $aliases = explode('|', $keys);
        foreach ($aliases as $alias) {
            $alias = trim($alias);
            if ($alias === '') continue;
            if (strpos($name, $alias) !== false || strpos($alias, $name) !== false) return $url;
        }
    }
    return '';
}

function stratedge_team_logos_db() {
    // ESPN CDN pattern: https://a.espncdn.com/i/teamlogos/soccer/500/{id}.png (foot)
    // ESPN CDN pattern: https://a.espncdn.com/i/teamlogos/{sport}/500/{abbrev}.png (NBA/NHL/MLB)

    return [
        // ═════════════════════════════════════════════════════════════
        // FOOTBALL — Top 5 leagues + Champions League
        // ═════════════════════════════════════════════════════════════
        'football' => [
            // ── LIGUE 1 (France) ──
            'psg|paris saint germain|paris sg|paris'                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/160.png',
            'marseille|om|olympique marseille'                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/176.png',
            'lyon|ol|olympique lyonnais'                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/170.png',
            'monaco|as monaco'                                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/174.png',
            'lille|losc'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/166.png',
            'rennes|stade rennais'                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/213.png',
            'nice|ogc nice'                                           => 'https://a.espncdn.com/i/teamlogos/soccer/500/2664.png',
            'nantes|fc nantes'                                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/180.png',
            'strasbourg|rc strasbourg'                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/183.png',
            'lens|rc lens'                                            => 'https://a.espncdn.com/i/teamlogos/soccer/500/164.png',
            'montpellier'                                             => 'https://a.espncdn.com/i/teamlogos/soccer/500/178.png',
            'reims|stade reims'                                       => 'https://a.espncdn.com/i/teamlogos/soccer/500/2656.png',
            'toulouse|tfc'                                            => 'https://a.espncdn.com/i/teamlogos/soccer/500/2649.png',
            'brest|stade brestois'                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/2658.png',
            'angers|sco'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/2655.png',
            'auxerre|aj auxerre'                                      => 'https://a.espncdn.com/i/teamlogos/soccer/500/162.png',
            'le havre'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/2657.png',
            'saint etienne|asse'                                      => 'https://a.espncdn.com/i/teamlogos/soccer/500/181.png',

            // ── PREMIER LEAGUE (Angleterre) ──
            'manchester city|man city'                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/382.png',
            'manchester united|man united|man utd|united'             => 'https://a.espncdn.com/i/teamlogos/soccer/500/360.png',
            'liverpool|lfc'                                           => 'https://a.espncdn.com/i/teamlogos/soccer/500/364.png',
            'chelsea'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/363.png',
            'arsenal'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/359.png',
            'tottenham|spurs'                                         => 'https://a.espncdn.com/i/teamlogos/soccer/500/367.png',
            'newcastle'                                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/361.png',
            'aston villa|villa'                                       => 'https://a.espncdn.com/i/teamlogos/soccer/500/362.png',
            'west ham'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/371.png',
            'brighton'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/331.png',
            'brentford'                                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/337.png',
            'crystal palace|palace'                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/384.png',
            'everton'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/368.png',
            'fulham'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/370.png',
            'wolves|wolverhampton'                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/380.png',
            'nottingham forest|forest'                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/393.png',
            'bournemouth'                                             => 'https://a.espncdn.com/i/teamlogos/soccer/500/349.png',
            'leicester'                                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/375.png',
            'ipswich'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/373.png',
            'southampton'                                             => 'https://a.espncdn.com/i/teamlogos/soccer/500/376.png',

            // ── LA LIGA (Espagne) ──
            'real madrid|real'                                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/86.png',
            'barcelona|barca|fc barcelona'                            => 'https://a.espncdn.com/i/teamlogos/soccer/500/83.png',
            'atletico madrid|atletico|atleti'                         => 'https://a.espncdn.com/i/teamlogos/soccer/500/1068.png',
            'athletic bilbao|athletic club|bilbao'                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/93.png',
            'real sociedad|sociedad'                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/89.png',
            'villarreal'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/102.png',
            'real betis|betis'                                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/244.png',
            'sevilla'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/243.png',
            'valencia'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/94.png',
            'getafe'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/2922.png',
            'girona'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/9812.png',
            'celta vigo|celta'                                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/85.png',
            'osasuna'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/97.png',
            'rayo vallecano|rayo'                                     => 'https://a.espncdn.com/i/teamlogos/soccer/500/101.png',
            'mallorca'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/84.png',
            'alaves'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/96.png',
            'las palmas'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/98.png',
            'leganes'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/17534.png',
            'espanyol'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/88.png',
            'valladolid'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/95.png',

            // ── SERIE A (Italie) ──
            'inter|inter milan|internazionale'                        => 'https://a.espncdn.com/i/teamlogos/soccer/500/110.png',
            'ac milan|milan'                                          => 'https://a.espncdn.com/i/teamlogos/soccer/500/103.png',
            'juventus|juve'                                           => 'https://a.espncdn.com/i/teamlogos/soccer/500/111.png',
            'napoli'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/114.png',
            'roma|as roma'                                            => 'https://a.espncdn.com/i/teamlogos/soccer/500/104.png',
            'lazio'                                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/105.png',
            'atalanta'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/108.png',
            'fiorentina'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/109.png',
            'bologna'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/107.png',
            'torino'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/586.png',
            'udinese'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/115.png',
            'genoa'                                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/2311.png',
            'empoli'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/117.png',
            'hellas verona|verona'                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/598.png',
            'parma'                                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/130.png',
            'cagliari'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/2315.png',
            'lecce'                                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/2314.png',
            'monza'                                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/4001.png',
            'venezia'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/2727.png',
            'como'                                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/2316.png',

            // ── BUNDESLIGA (Allemagne) ──
            'bayern munich|bayern|fc bayern'                          => 'https://a.espncdn.com/i/teamlogos/soccer/500/132.png',
            'borussia dortmund|bvb|dortmund'                          => 'https://a.espncdn.com/i/teamlogos/soccer/500/124.png',
            'bayer leverkusen|leverkusen'                             => 'https://a.espncdn.com/i/teamlogos/soccer/500/131.png',
            'rb leipzig|leipzig'                                      => 'https://a.espncdn.com/i/teamlogos/soccer/500/11420.png',
            'stuttgart'                                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/134.png',
            'frankfurt|eintracht frankfurt'                           => 'https://a.espncdn.com/i/teamlogos/soccer/500/125.png',
            'wolfsburg'                                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/138.png',
            'borussia monchengladbach|gladbach'                       => 'https://a.espncdn.com/i/teamlogos/soccer/500/123.png',
            'hoffenheim'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/7911.png',
            'freiburg'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/126.png',
            'mainz'                                                   => 'https://a.espncdn.com/i/teamlogos/soccer/500/7910.png',
            'werder bremen|bremen'                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/137.png',
            'augsburg'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/7912.png',
            'union berlin'                                            => 'https://a.espncdn.com/i/teamlogos/soccer/500/598.png',
            'bochum'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/122.png',
            'heidenheim'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/14911.png',
            'st pauli'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/133.png',
            'holstein kiel|kiel'                                      => 'https://a.espncdn.com/i/teamlogos/soccer/500/2719.png',

            // ── AUTRES TOP CLUBS EUROPÉENS (C1) ──
            'benfica'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/1929.png',
            'porto|fc porto'                                          => 'https://a.espncdn.com/i/teamlogos/soccer/500/2950.png',
            'sporting|sporting cp|sporting lisbon'                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/2930.png',
            'ajax'                                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/109.png',
            'psv|psv eindhoven'                                       => 'https://a.espncdn.com/i/teamlogos/soccer/500/148.png',
            'feyenoord'                                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/142.png',
            'celtic'                                                  => 'https://a.espncdn.com/i/teamlogos/soccer/500/256.png',
            'rangers'                                                 => 'https://a.espncdn.com/i/teamlogos/soccer/500/257.png',
            'shakhtar donetsk|shakhtar'                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/2317.png',
            'club brugge|brugge'                                      => 'https://a.espncdn.com/i/teamlogos/soccer/500/2292.png',
            'anderlecht'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/1329.png',
            'galatasaray'                                             => 'https://a.espncdn.com/i/teamlogos/soccer/500/645.png',
            'fenerbahce'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/1466.png',
            'besiktas'                                                => 'https://a.espncdn.com/i/teamlogos/soccer/500/1523.png',
            'olympiakos'                                              => 'https://a.espncdn.com/i/teamlogos/soccer/500/1492.png',
            'paok'                                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/1509.png',
            'red star belgrade|crvena zvezda'                         => 'https://a.espncdn.com/i/teamlogos/soccer/500/1485.png',
            'dinamo zagreb|zagreb'                                    => 'https://a.espncdn.com/i/teamlogos/soccer/500/1502.png',
            'genk|rdc genk|racing genk'                               => 'https://a.espncdn.com/i/teamlogos/soccer/500/1334.png',
        ],

        // ═════════════════════════════════════════════════════════════
        // NBA — 30 équipes
        // ═════════════════════════════════════════════════════════════
        'basket' => [
            'atlanta|hawks'                                           => 'https://a.espncdn.com/i/teamlogos/nba/500/atl.png',
            'boston|celtics'                                          => 'https://a.espncdn.com/i/teamlogos/nba/500/bos.png',
            'brooklyn|nets'                                           => 'https://a.espncdn.com/i/teamlogos/nba/500/bkn.png',
            'charlotte|hornets'                                       => 'https://a.espncdn.com/i/teamlogos/nba/500/cha.png',
            'chicago|bulls'                                           => 'https://a.espncdn.com/i/teamlogos/nba/500/chi.png',
            'cleveland|cavaliers|cavs'                                => 'https://a.espncdn.com/i/teamlogos/nba/500/cle.png',
            'dallas|mavericks|mavs'                                   => 'https://a.espncdn.com/i/teamlogos/nba/500/dal.png',
            'denver|nuggets'                                          => 'https://a.espncdn.com/i/teamlogos/nba/500/den.png',
            'detroit|pistons'                                         => 'https://a.espncdn.com/i/teamlogos/nba/500/det.png',
            'golden state|warriors|gsw'                               => 'https://a.espncdn.com/i/teamlogos/nba/500/gsw.png',
            'houston|rockets'                                         => 'https://a.espncdn.com/i/teamlogos/nba/500/hou.png',
            'indiana|pacers'                                          => 'https://a.espncdn.com/i/teamlogos/nba/500/ind.png',
            'la clippers|clippers'                                    => 'https://a.espncdn.com/i/teamlogos/nba/500/lac.png',
            'la lakers|lakers|los angeles lakers'                     => 'https://a.espncdn.com/i/teamlogos/nba/500/lal.png',
            'memphis|grizzlies'                                       => 'https://a.espncdn.com/i/teamlogos/nba/500/mem.png',
            'miami|heat'                                              => 'https://a.espncdn.com/i/teamlogos/nba/500/mia.png',
            'milwaukee|bucks'                                         => 'https://a.espncdn.com/i/teamlogos/nba/500/mil.png',
            'minnesota|timberwolves|wolves'                           => 'https://a.espncdn.com/i/teamlogos/nba/500/min.png',
            'new orleans|pelicans'                                    => 'https://a.espncdn.com/i/teamlogos/nba/500/no.png',
            'new york|knicks'                                         => 'https://a.espncdn.com/i/teamlogos/nba/500/ny.png',
            'oklahoma city|thunder|okc'                               => 'https://a.espncdn.com/i/teamlogos/nba/500/okc.png',
            'orlando|magic'                                           => 'https://a.espncdn.com/i/teamlogos/nba/500/orl.png',
            'philadelphia|76ers|sixers'                               => 'https://a.espncdn.com/i/teamlogos/nba/500/phi.png',
            'phoenix|suns'                                            => 'https://a.espncdn.com/i/teamlogos/nba/500/phx.png',
            'portland|trail blazers|blazers'                          => 'https://a.espncdn.com/i/teamlogos/nba/500/por.png',
            'sacramento|kings'                                        => 'https://a.espncdn.com/i/teamlogos/nba/500/sac.png',
            'san antonio|spurs'                                       => 'https://a.espncdn.com/i/teamlogos/nba/500/sa.png',
            'toronto|raptors'                                         => 'https://a.espncdn.com/i/teamlogos/nba/500/tor.png',
            'utah|jazz'                                               => 'https://a.espncdn.com/i/teamlogos/nba/500/utah.png',
            'washington|wizards'                                      => 'https://a.espncdn.com/i/teamlogos/nba/500/wsh.png',
        ],

        // ═════════════════════════════════════════════════════════════
        // NHL — 32 équipes (ESPN scoreboard CDN)
        // ═════════════════════════════════════════════════════════════
        'hockey' => [
            'anaheim|ducks'                                           => 'https://a.espncdn.com/i/teamlogos/nhl/500/ana.png',
            'boston|bruins'                                           => 'https://a.espncdn.com/i/teamlogos/nhl/500/bos.png',
            'buffalo|sabres'                                          => 'https://a.espncdn.com/i/teamlogos/nhl/500/buf.png',
            'calgary|flames'                                          => 'https://a.espncdn.com/i/teamlogos/nhl/500/cgy.png',
            'carolina|hurricanes|canes'                               => 'https://a.espncdn.com/i/teamlogos/nhl/500/car.png',
            'chicago|blackhawks'                                      => 'https://a.espncdn.com/i/teamlogos/nhl/500/chi.png',
            'colorado|avalanche|avs'                                  => 'https://a.espncdn.com/i/teamlogos/nhl/500/col.png',
            'columbus|blue jackets|jackets'                           => 'https://a.espncdn.com/i/teamlogos/nhl/500/cbj.png',
            'dallas|stars'                                            => 'https://a.espncdn.com/i/teamlogos/nhl/500/dal.png',
            'detroit|red wings|wings'                                 => 'https://a.espncdn.com/i/teamlogos/nhl/500/det.png',
            'edmonton|oilers'                                         => 'https://a.espncdn.com/i/teamlogos/nhl/500/edm.png',
            'florida|panthers'                                        => 'https://a.espncdn.com/i/teamlogos/nhl/500/fla.png',
            'los angeles|kings|la kings'                              => 'https://a.espncdn.com/i/teamlogos/nhl/500/la.png',
            'minnesota|wild'                                          => 'https://a.espncdn.com/i/teamlogos/nhl/500/min.png',
            'montreal|canadiens|habs'                                 => 'https://a.espncdn.com/i/teamlogos/nhl/500/mtl.png',
            'nashville|predators|preds'                               => 'https://a.espncdn.com/i/teamlogos/nhl/500/nsh.png',
            'new jersey|devils'                                       => 'https://a.espncdn.com/i/teamlogos/nhl/500/nj.png',
            'new york islanders|islanders|isles'                      => 'https://a.espncdn.com/i/teamlogos/nhl/500/nyi.png',
            'new york rangers|rangers|ny rangers'                     => 'https://a.espncdn.com/i/teamlogos/nhl/500/nyr.png',
            'ottawa|senators|sens'                                    => 'https://a.espncdn.com/i/teamlogos/nhl/500/ott.png',
            'philadelphia|flyers'                                     => 'https://a.espncdn.com/i/teamlogos/nhl/500/phi.png',
            'pittsburgh|penguins|pens'                                => 'https://a.espncdn.com/i/teamlogos/nhl/500/pit.png',
            'san jose|sharks'                                         => 'https://a.espncdn.com/i/teamlogos/nhl/500/sj.png',
            'seattle|kraken'                                          => 'https://a.espncdn.com/i/teamlogos/nhl/500/sea.png',
            'st louis|blues|saint louis'                              => 'https://a.espncdn.com/i/teamlogos/nhl/500/stl.png',
            'tampa bay|lightning|bolts'                               => 'https://a.espncdn.com/i/teamlogos/nhl/500/tb.png',
            'toronto|maple leafs|leafs'                               => 'https://a.espncdn.com/i/teamlogos/nhl/500/tor.png',
            'utah|utah hockey'                                        => 'https://a.espncdn.com/i/teamlogos/nhl/500/utah.png',
            'vancouver|canucks'                                       => 'https://a.espncdn.com/i/teamlogos/nhl/500/van.png',
            'vegas|golden knights|knights|vgk'                        => 'https://a.espncdn.com/i/teamlogos/nhl/500/vgs.png',
            'washington|capitals|caps'                                => 'https://a.espncdn.com/i/teamlogos/nhl/500/wsh.png',
            'winnipeg|jets'                                           => 'https://a.espncdn.com/i/teamlogos/nhl/500/wpg.png',
        ],

        // ═════════════════════════════════════════════════════════════
        // MLB — 30 équipes
        // ═════════════════════════════════════════════════════════════
        'baseball' => [
            'arizona|diamondbacks|dbacks'                             => 'https://a.espncdn.com/i/teamlogos/mlb/500/ari.png',
            'atlanta|braves'                                          => 'https://a.espncdn.com/i/teamlogos/mlb/500/atl.png',
            'baltimore|orioles'                                       => 'https://a.espncdn.com/i/teamlogos/mlb/500/bal.png',
            'boston|red sox'                                          => 'https://a.espncdn.com/i/teamlogos/mlb/500/bos.png',
            'chicago cubs|cubs'                                       => 'https://a.espncdn.com/i/teamlogos/mlb/500/chc.png',
            'chicago white sox|white sox'                             => 'https://a.espncdn.com/i/teamlogos/mlb/500/chw.png',
            'cincinnati|reds'                                         => 'https://a.espncdn.com/i/teamlogos/mlb/500/cin.png',
            'cleveland|guardians'                                     => 'https://a.espncdn.com/i/teamlogos/mlb/500/cle.png',
            'colorado|rockies'                                        => 'https://a.espncdn.com/i/teamlogos/mlb/500/col.png',
            'detroit|tigers'                                          => 'https://a.espncdn.com/i/teamlogos/mlb/500/det.png',
            'houston|astros'                                          => 'https://a.espncdn.com/i/teamlogos/mlb/500/hou.png',
            'kansas city|royals|kc royals'                            => 'https://a.espncdn.com/i/teamlogos/mlb/500/kc.png',
            'los angeles angels|angels|la angels'                     => 'https://a.espncdn.com/i/teamlogos/mlb/500/laa.png',
            'los angeles dodgers|dodgers|la dodgers'                  => 'https://a.espncdn.com/i/teamlogos/mlb/500/lad.png',
            'miami|marlins'                                           => 'https://a.espncdn.com/i/teamlogos/mlb/500/mia.png',
            'milwaukee|brewers'                                       => 'https://a.espncdn.com/i/teamlogos/mlb/500/mil.png',
            'minnesota|twins'                                         => 'https://a.espncdn.com/i/teamlogos/mlb/500/min.png',
            'new york mets|mets|ny mets'                              => 'https://a.espncdn.com/i/teamlogos/mlb/500/nym.png',
            'new york yankees|yankees|ny yankees'                     => 'https://a.espncdn.com/i/teamlogos/mlb/500/nyy.png',
            'oakland|athletics|as'                                    => 'https://a.espncdn.com/i/teamlogos/mlb/500/oak.png',
            'philadelphia|phillies'                                   => 'https://a.espncdn.com/i/teamlogos/mlb/500/phi.png',
            'pittsburgh|pirates'                                      => 'https://a.espncdn.com/i/teamlogos/mlb/500/pit.png',
            'san diego|padres'                                        => 'https://a.espncdn.com/i/teamlogos/mlb/500/sd.png',
            'san francisco|giants|sf giants'                          => 'https://a.espncdn.com/i/teamlogos/mlb/500/sf.png',
            'seattle|mariners'                                        => 'https://a.espncdn.com/i/teamlogos/mlb/500/sea.png',
            'st louis|cardinals|cards'                                => 'https://a.espncdn.com/i/teamlogos/mlb/500/stl.png',
            'tampa bay|rays'                                          => 'https://a.espncdn.com/i/teamlogos/mlb/500/tb.png',
            'texas|rangers'                                           => 'https://a.espncdn.com/i/teamlogos/mlb/500/tex.png',
            'toronto|blue jays|jays'                                  => 'https://a.espncdn.com/i/teamlogos/mlb/500/tor.png',
            'washington|nationals|nats'                               => 'https://a.espncdn.com/i/teamlogos/mlb/500/wsh.png',
        ],
    ];
}

}
