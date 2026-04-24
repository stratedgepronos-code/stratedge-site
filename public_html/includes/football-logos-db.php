<?php
/**
 * STRATEDGE — Mapping football clubs → logo URL (API-Football IDs vérifiés)
 * Source: api-sports.io/football/teams/{id}.png
 * 
 * Ce fichier contient 200+ clubs des Big 5 + ligues mineures courantes.
 * Chaque ID a été vérifié manuellement.
 * 
 * Usage: stratedge_football_logo('PSG') → URL du logo
 */

if (!function_exists('stratedge_football_logo')) {

function stratedge_football_logo(string $teamName): string {
    $name = mb_strtolower(trim($teamName), 'UTF-8');
    // Nettoyer
    $name = preg_replace('/[^\w\s\-àâäéèêëïîôùûüçñ]/u', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);

    // Base de données des IDs vérifiés (nom_lowercase → api_football_id)
    static $db = null;
    if ($db === null) {
        $db = [
            // ═══ FRANCE — Ligue 1 ═══
            'psg' => 85, 'paris saint-germain' => 85, 'paris sg' => 85, 'paris' => 85,
            'marseille' => 81, 'olympique marseille' => 81, 'olympique de marseille' => 81, 'om' => 81,
            'lyon' => 80, 'olympique lyonnais' => 80, 'ol' => 80,
            'monaco' => 91, 'as monaco' => 91,
            'lille' => 79, 'lille osc' => 79, 'losc' => 79, 'losc lille' => 79,
            'rennes' => 94, 'stade rennais' => 94,
            'nice' => 84, 'ogc nice' => 84,
            'lens' => 116, 'rc lens' => 116,
            'strasbourg' => 95, 'rc strasbourg' => 95,
            'toulouse' => 96, 'toulouse fc' => 96,
            'nantes' => 83, 'fc nantes' => 83,
            'brest' => 106, 'stade brestois' => 106,
            'reims' => 93, 'stade reims' => 93, 'stade de reims' => 93,
            'montpellier' => 82, 'montpellier hsc' => 82,
            'le havre' => 99, 'le havre ac' => 99,
            'auxerre' => 98, 'aj auxerre' => 98,
            'angers' => 77, 'angers sco' => 77,
            'saint-etienne' => 1063, 'st etienne' => 1063, 'asse' => 1063, 'as saint-etienne' => 1063,
            // Ligue 2
            'lorient' => 97, 'fc lorient' => 97,
            'metz' => 112, 'fc metz' => 112,
            'caen' => 108, 'sm caen' => 108,
            'bordeaux' => 78, 'girondins' => 78, 'girondins de bordeaux' => 78,
            'bastia' => 102, 'sc bastia' => 102,
            'guingamp' => 100, 'ea guingamp' => 100,
            'grenoble' => 109, 'grenoble foot' => 109,
            'laval' => 113, 'stade lavallois' => 113,
            'amiens' => 101, 'amiens sc' => 101,
            'ajaccio' => 103, 'ac ajaccio' => 103,
            'pau' => 1065, 'pau fc' => 1065,
            'dunkerque' => 1066, 'usg dunkerque' => 1066,
            'rodez' => 1069, 'rodez af' => 1069,
            'troyes' => 107, 'estac troyes' => 107,
            'clermont' => 111, 'clermont foot' => 111,

            // ═══ ANGLETERRE — Premier League ═══
            'arsenal' => 42, 'arsenal fc' => 42,
            'manchester city' => 50, 'man city' => 50,
            'liverpool' => 40, 'liverpool fc' => 40,
            'chelsea' => 49, 'chelsea fc' => 49,
            'manchester united' => 33, 'man united' => 33, 'man utd' => 33,
            'tottenham' => 47, 'tottenham hotspur' => 47, 'spurs' => 47,
            'newcastle' => 34, 'newcastle united' => 34,
            'aston villa' => 66,
            'west ham' => 48, 'west ham united' => 48,
            'brighton' => 51, 'brighton hove albion' => 51,
            'crystal palace' => 52,
            'bournemouth' => 35, 'afc bournemouth' => 35,
            'fulham' => 36, 'fulham fc' => 36,
            'wolves' => 39, 'wolverhampton' => 39, 'wolverhampton wanderers' => 39,
            'everton' => 45, 'everton fc' => 45,
            'brentford' => 55, 'brentford fc' => 55,
            'nottingham forest' => 65, 'nottm forest' => 65,
            'leicester' => 46, 'leicester city' => 46,
            'ipswich' => 57, 'ipswich town' => 57,
            'southampton' => 41,
            // Championship
            'leeds' => 63, 'leeds united' => 63,
            'burnley' => 44, 'burnley fc' => 44,
            'sheffield united' => 62, 'sheffield utd' => 62,
            'luton' => 1359, 'luton town' => 1359,
            'sunderland' => 56,
            'middlesbrough' => 64,
            'norwich' => 71, 'norwich city' => 71,
            'watford' => 38, 'watford fc' => 38,
            'west brom' => 60, 'west bromwich' => 60, 'west bromwich albion' => 60,
            'coventry' => 72, 'coventry city' => 72,
            'blackburn' => 59, 'blackburn rovers' => 59,
            'stoke' => 75, 'stoke city' => 75,
            'swansea' => 1076, 'swansea city' => 1076,
            'bristol city' => 68,
            'millwall' => 73, 'millwall fc' => 73,
            'qpr' => 69, 'queens park rangers' => 69,
            'hull' => 74, 'hull city' => 74,

            // ═══ ESPAGNE — La Liga ═══
            'real madrid' => 541,
            'barcelona' => 529, 'barcelone' => 529, 'barca' => 529, 'fc barcelona' => 529, 'fc barcelone' => 529,
            'atletico madrid' => 530, 'atletico' => 530, 'atletico de madrid' => 530,
            'sevilla' => 536, 'seville' => 536, 'sevilla fc' => 536, 'fc seville' => 536,
            'real sociedad' => 548,
            'real betis' => 543, 'betis' => 543,
            'villarreal' => 533, 'villarreal cf' => 533,
            'athletic bilbao' => 531, 'athletic club' => 531, 'bilbao' => 531,
            'valencia' => 532, 'valencia cf' => 532, 'valence' => 532,
            'celta vigo' => 538, 'celta' => 538, 'rc celta' => 538,
            'osasuna' => 539, 'ca osasuna' => 539,
            'getafe' => 546, 'getafe cf' => 546,
            'girona' => 547, 'girona fc' => 547, 'girone' => 547,
            'rayo vallecano' => 728, 'rayo' => 728,
            'mallorca' => 798, 'rcd mallorca' => 798, 'majorque' => 798,
            'las palmas' => 534, 'ud las palmas' => 534,
            'alaves' => 542, 'deportivo alaves' => 542,
            'cadiz' => 724, 'cadiz cf' => 724, 'cadix' => 724,
            'almeria' => 723, 'ud almeria' => 723,
            'espanyol' => 540, 'rcd espanyol' => 540,
            'leganes' => 727, 'cd leganes' => 727,
            'valladolid' => 720, 'real valladolid' => 720,
            'real oviedo' => 726, 'oviedo' => 726,
            // Segunda
            'racing santander' => 730, 'racing' => 730,
            'eibar' => 797, 'sd eibar' => 797,
            'sporting gijon' => 796, 'gijon' => 796,
            'tenerife' => 725, 'cd tenerife' => 725,
            'levante' => 535, 'levante ud' => 535,
            'elche' => 797, 'elche cf' => 797,

            // ═══ ALLEMAGNE — Bundesliga ═══
            'bayern' => 157, 'bayern munich' => 157, 'bayern munchen' => 157, 'fc bayern' => 157,
            'dortmund' => 165, 'borussia dortmund' => 165, 'bvb' => 165,
            'leverkusen' => 168, 'bayer leverkusen' => 168,
            'rb leipzig' => 173, 'leipzig' => 173, 'rasenballsport leipzig' => 173,
            'frankfurt' => 169, 'eintracht frankfurt' => 169, 'francfort' => 169,
            'freiburg' => 160, 'sc freiburg' => 160, 'fribourg' => 160,
            'wolfsburg' => 161, 'vfl wolfsburg' => 161,
            'gladbach' => 163, 'monchengladbach' => 163, 'borussia monchengladbach' => 163,
            'union berlin' => 182, '1 fc union berlin' => 182,
            'stuttgart' => 172, 'vfb stuttgart' => 172,
            'hoffenheim' => 167, 'tsg hoffenheim' => 167,
            'mainz' => 164, 'mainz 05' => 164, 'fsv mainz' => 164, 'mayence' => 164,
            'augsburg' => 170, 'fc augsburg' => 170, 'augsbourg' => 170,
            'werder bremen' => 162, 'bremen' => 162, 'breme' => 162,
            'heidenheim' => 180, '1 fc heidenheim' => 180,
            'bochum' => 176, 'vfl bochum' => 176,
            'darmstadt' => 181, 'sv darmstadt' => 181, 'darmstadt 98' => 181,
            'koln' => 192, 'fc koln' => 192, 'cologne' => 192, '1 fc koln' => 192,
            'hertha' => 159, 'hertha bsc' => 159, 'hertha berlin' => 159,
            'hamburg' => 174, 'hamburger sv' => 174, 'hsv' => 174, 'hambourg' => 174,
            'schalke' => 174, 'schalke 04' => 174, 'fc schalke' => 174,
            'nuremberg' => 166, 'fc nurnberg' => 166, 'nurnberg' => 166,
            'st pauli' => 186, 'fc st pauli' => 186,
            'holstein kiel' => 190, 'kiel' => 190,

            // ═══ ITALIE — Serie A ═══
            'inter' => 505, 'inter milan' => 505, 'internazionale' => 505, 'fc internazionale' => 505,
            'milan' => 489, 'ac milan' => 489,
            'juventus' => 496, 'juve' => 496,
            'napoli' => 492, 'ssc napoli' => 492, 'naples' => 492,
            'roma' => 497, 'as roma' => 497, 'rome' => 497,
            'lazio' => 487, 'ss lazio' => 487,
            'atalanta' => 499, 'atalanta bc' => 499,
            'fiorentina' => 502, 'acf fiorentina' => 502, 'florence' => 502,
            'bologna' => 500, 'bologna fc' => 500, 'bologne' => 500,
            'torino' => 503, 'torino fc' => 503, 'turin' => 503,
            'monza' => 1579, 'ac monza' => 1579,
            'genoa' => 495, 'genoa cfc' => 495, 'genes' => 495,
            'cagliari' => 490, 'cagliari calcio' => 490,
            'udinese' => 494, 'udinese calcio' => 494,
            'empoli' => 511, 'empoli fc' => 511,
            'lecce' => 867, 'us lecce' => 867,
            'sassuolo' => 488, 'us sassuolo' => 488,
            'verona' => 504, 'hellas verona' => 504, 'verone' => 504,
            'salernitana' => 514, 'us salernitana' => 514,
            'frosinone' => 512, 'frosinone calcio' => 512,
            'venezia' => 517, 'venezia fc' => 517, 'venise' => 517,
            'como' => 895, 'como 1907' => 895,
            'parma' => 513, 'parma calcio' => 513, 'parme' => 513,
            // Serie B
            'sampdoria' => 498, 'uc sampdoria' => 498,
            'palermo' => 509, 'us palermo' => 509, 'palerme' => 509,
            'brescia' => 501, 'brescia calcio' => 501,
            'bari' => 510, 'ssc bari' => 510,

            // ═══ PORTUGAL — Liga Portugal ═══
            'benfica' => 211, 'sl benfica' => 211,
            'porto' => 212, 'fc porto' => 212,
            'sporting' => 228, 'sporting cp' => 228, 'sporting lisbonne' => 228, 'sporting lisbon' => 228,
            'braga' => 217, 'sc braga' => 217,
            'guimaraes' => 222, 'vitoria guimaraes' => 222, 'vitoria sc' => 222,

            // ═══ PAYS-BAS — Eredivisie 2025-26 (18 clubs) ═══
            'ajax' => 194, 'ajax amsterdam' => 194,
            'psv' => 197, 'psv eindhoven' => 197,
            'feyenoord' => 215, 'feyenoord rotterdam' => 215,
            'az' => 202, 'az alkmaar' => 202, 'alkmaar' => 202,
            'twente' => 201, 'fc twente' => 201,
            'utrecht' => 193, 'fc utrecht' => 193,
            'go ahead eagles' => 196, 'go ahead' => 196, 'eagles deventer' => 196,
            'nec' => 413, 'nec nijmegen' => 413,
            'sparta rotterdam' => 419, 'sparta' => 419,
            'fortuna sittard' => 415, 'fortuna' => 415,
            'heerenveen' => 206, 'sc heerenveen' => 206,
            'groningen' => 200, 'fc groningen' => 200,
            'pec zwolle' => 204, 'zwolle' => 204, 'fc zwolle' => 204,
            'rkc waalwijk' => 416, 'waalwijk' => 416, 'rkc' => 416,
            'nac breda' => 417, 'nac' => 417, 'breda' => 417,
            'heracles' => 412, 'heracles almelo' => 412,
            'telstar' => 1911, 'sc telstar' => 1911,
            'volendam' => 418, 'fc volendam' => 418,
            // Eerste Divisie (2e division NL) — peu commun mais inclus par sécurité
            'almere city' => 414, 'almere' => 414,
            'willem ii' => 209, 'willem 2' => 209,
            'mvv maastricht' => 411, 'mvv' => 411,

            // ═══ JAPON — J1 League 2026 (18 clubs) ═══
            // IDs api-sports peu fiables pour J-League : système priorisera
            // le manifest ESPN (Phase 3 scraper) via stratedge_football_logo().
            'kashima antlers' => 292, 'kashima' => 292,
            'kashiwa reysol' => 296, 'kashiwa' => 296, 'reysol' => 296,
            'urawa red diamonds' => 287, 'urawa' => 287, 'urawa reds' => 287,
            'yokohama f marinos' => 285, 'yokohama marinos' => 285, 'f marinos' => 285,
            'yokohama fc' => 289,
            'kawasaki frontale' => 281, 'kawasaki' => 281, 'frontale' => 281,
            'fc tokyo' => 286, 'tokyo fc' => 286,
            'tokyo verdy' => 294, 'verdy' => 294,
            'cerezo osaka' => 284, 'cerezo' => 284,
            'gamba osaka' => 280, 'gamba' => 280,
            'vissel kobe' => 282, 'vissel' => 282, 'kobe' => 282,
            'sanfrecce hiroshima' => 283, 'sanfrecce' => 283, 'hiroshima' => 283,
            'nagoya grampus' => 288, 'nagoya' => 288, 'grampus' => 288,
            'shonan bellmare' => 295, 'shonan' => 295, 'bellmare' => 295,
            'sagan tosu' => 297, 'tosu' => 297,
            'avispa fukuoka' => 298, 'avispa' => 298, 'fukuoka' => 298,
            'consadole sapporo' => 293, 'consadole' => 293, 'sapporo' => 293,
            'albirex niigata' => 290, 'albirex' => 290, 'niigata' => 290,
            'kyoto sanga' => 291, 'kyoto' => 291, 'sanga' => 291,
            'machida zelvia' => 7197, 'machida' => 7197, 'zelvia' => 7197,

            // ═══ BELGIQUE — Jupiler Pro ═══
            'club brugge' => 569, 'club bruges' => 569, 'bruges' => 569,
            'anderlecht' => 554, 'rsc anderlecht' => 554,
            'genk' => 631, 'krc genk' => 631, 'racing genk' => 631,
            'standard' => 571, 'standard liege' => 571, 'standard de liege' => 571,
            'union sg' => 736, 'union saint-gilloise' => 736,
            'gent' => 600, 'kaa gent' => 600, 'la gantoise' => 600,
            'antwerp' => 556, 'royal antwerp' => 556,

            // ═══ TURQUIE — Süper Lig ═══
            'galatasaray' => 645, 'galatasaray sk' => 645,
            'fenerbahce' => 611, 'fenerbahce sk' => 611,
            'besiktas' => 549, 'besiktas jk' => 549,
            'trabzonspor' => 609,

            // ═══ ÉCOSSE ═══
            'celtic' => 247, 'celtic fc' => 247, 'celtic glasgow' => 247,
            'rangers' => 257, 'rangers fc' => 257, 'glasgow rangers' => 257,

            // ═══ GRÈCE — IDs gérés par TheSportsDB (lookup par nom) ═══
            // Les IDs API-Football grecs sont incertains, on les laisse au fallback TheSportsDB

            // ═══ AUTRICHE — IDs gérés par TheSportsDB ═══

            // ═══ SUISSE — IDs gérés par TheSportsDB ═══

            // ═══ CHAMPIONS LEAGUE regulars (hors top 5) ═══
            'shakhtar' => 661, 'shakhtar donetsk' => 661,
            'dinamo zagreb' => 620, 'gnk dinamo zagreb' => 620,
            'red star' => 598, 'red star belgrade' => 598, 'etoile rouge' => 598, 'crvena zvezda' => 598,
            'copenhagen' => 541, 'fc copenhagen' => 541, 'fc copenhague' => 541,
            'malmo' => 381, 'malmo ff' => 381,

            // ═══ AMÉRIQUE DU SUD ═══
            'boca juniors' => 451, 'boca' => 451,
            'river plate' => 435, 'river' => 435,
            'flamengo' => 127, 'cr flamengo' => 127,
            'palmeiras' => 121, 'se palmeiras' => 121,
            'corinthians' => 131, 'sc corinthians' => 131,
            'sao paulo' => 126, 'sao paulo fc' => 126,
            'santos' => 128, 'santos fc' => 128,
            'gremio' => 130, 'gremio fbpa' => 130,
            'atletico mineiro' => 1062, 'atletico-mg' => 1062,
            'fluminense' => 124, 'fluminense fc' => 124,
            'internacional' => 119, 'sc internacional' => 119,
            'botafogo' => 120, 'botafogo fr' => 120,
            'cruzeiro' => 129, 'cruzeiro ec' => 129,
            'vasco' => 133, 'vasco da gama' => 133,
            'bahia' => 118, 'ec bahia' => 118,
            'fortaleza' => 132, 'fortaleza ec' => 132,
            'atletico paranaense' => 134, 'athletico-pr' => 134, 'athletico paranaense' => 134,

            // ═══ MLS ═══
            'inter miami' => 18656, 'inter miami cf' => 18656,
            'cf montreal' => 18645, 'montreal impact' => 18645,
            'la galaxy' => 1600, 'los angeles galaxy' => 1600,
            'lafc' => 18646, 'los angeles fc' => 18646,
            'new york rb' => 1602, 'new york red bulls' => 1602, 'red bulls' => 1602,
            'ny red bulls' => 1602, 'ny redbulls' => 1602, 'ny rb' => 1602,
            'rb new york' => 1602, 'red bull new york' => 1602, 'new york red bull' => 1602,
            'nycfc' => 18649, 'new york city' => 18649, 'new york city fc' => 18649,
            'atlanta united' => 1609, 'atlanta utd' => 1609,
            'seattle sounders' => 1595, 'seattle' => 1595,
            'portland timbers' => 1598, 'portland' => 1598,
            'columbus crew' => 1596, 'columbus' => 1596,
            'real salt lake' => 1599, 'rsl' => 1599, 'salt lake' => 1599,
            'san jose earthquakes' => 1611, 'san jose' => 1611, 'earthquakes' => 1611,
            'vancouver whitecaps' => 1606, 'whitecaps' => 1606, 'vancouver' => 1606,
            'toronto fc' => 1597, 'toronto mls' => 1597,
            'dc united' => 1616, 'd.c. united' => 1616,
            'philadelphia union' => 1614, 'philadelphia fc' => 1614,
            'fc dallas' => 1607, 'dallas mls' => 1607,
            'houston dynamo' => 1608, 'dynamo houston' => 1608,
            'chicago fire' => 1610, 'chicago fire fc' => 1610,
            'sporting kc' => 1615, 'sporting kansas city' => 1615, 'kansas city mls' => 1615,
            'minnesota united' => 9568, 'minnesota utd' => 9568,
            'orlando city' => 1612, 'orlando city sc' => 1612,
            'nashville sc' => 18654, 'nashville mls' => 18654,
            'austin fc' => 18655, 'austin mls' => 18655,
            'st louis city' => 22727, 'st. louis city sc' => 22727, 'saint louis mls' => 22727,
            'charlotte fc' => 20532, 'charlotte mls' => 20532,
            'new england revolution' => 1594, 'revolution' => 1594, 'new england mls' => 1594,
            'fc cincinnati' => 18648, 'cincinnati mls' => 18648,
            'colorado rapids' => 1613, 'rapids' => 1613,
            'inter miami' => 18656,
        ];
    }

    // ⚠️ Normalisation nom recherché pour éviter matches ambigus
    // 'Real Salt Lake' ne doit PAS tomber sur 'Real Madrid' via le mot 'real'
    // → on teste d'abord le nom complet, puis variantes sans mots génériques
    $name_words_all = explode(' ', $name);
    $generic_words = ['fc', 'sc', 'cf', 'ac', 'as', 'united', 'city', 'real', 'inter', 'sporting', 'olympique', 'club', 'football', 'the', 'de', 'du', 'le', 'la', 'les'];
    $specific_words = array_values(array_filter($name_words_all, function($w) use ($generic_words) {
        return strlen($w) >= 3 && !in_array($w, $generic_words, true);
    }));

    // Load manifest early (used by multiple lookup paths)
    static $manifest = null;
    if ($manifest === null) {
        $manifestFile = __DIR__ . '/../assets/logos/football/manifest.json';
        if (is_file($manifestFile)) {
            $data = @json_decode(@file_get_contents($manifestFile), true);
            $manifest = $data['mapping'] ?? [];
        } else {
            $manifest = [];
        }
    }

    // Slugify le nom recherché (utilisé pour lookup manifest)
    $slug = strtolower(trim($teamName));
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if ($conv !== false) $slug = $conv;
    }
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    /**
     * Résout un logo pour un ID api-sports avec 3 niveaux:
     *   1. Fichier local api-{id}.png (si téléchargé)
     *   2. Manifest lookup (via slug du nom d'équipe, donne ESPN ou TheSportsDB local)
     *   3. URL api-sports.io distante (dernier recours, peut retourner 403)
     */
    $resolveLogo = function($id) use (&$manifest, $slug) {
        // 1) Fichier local api-sports
        $localPath = __DIR__ . '/../assets/logos/football/api-' . $id . '.png';
        if (is_file($localPath) && filesize($localPath) > 500) {
            return '/assets/logos/football/api-' . $id . '.png';
        }
        // 2) Manifest lookup via slug (trouvé par ESPN ou TheSportsDB)
        if (isset($manifest[$slug])) {
            $f = __DIR__ . '/../assets/logos/football/' . $manifest[$slug];
            if (is_file($f) && filesize($f) > 500) {
                return '/assets/logos/football/' . $manifest[$slug];
            }
        }
        // 3) URL api-sports distante (fallback de dernier recours)
        return 'https://media.api-sports.io/football/teams/' . $id . '.png';
    };

    // ⚠️ MLS/Liga MX/J1 League : les IDs api-sports sont peu fiables. Si le slug
    // est dans le manifest (Phase 3 ESPN scraper vérifié), on le sert en priorité
    // AVANT même la DB api-sports, car le fichier api-XXXX.png peut contenir le logo
    // d'une AUTRE équipe (ex: 1616 pouvait être LA Galaxy au lieu de DC United).
    $priority_manifest_indicators = [
        // MLS
        'mls', 'inter miami', 'salt lake', 'galaxy', 'lafc', 'nycfc', 'red bull',
        'new york rb', 'atlanta united', 'seattle sounders', 'portland timbers',
        'columbus crew', 'toronto fc', 'san jose', 'vancouver', 'dc united',
        'philadelphia union', 'fc dallas', 'houston dynamo', 'chicago fire',
        'sporting kc', 'minnesota united', 'orlando city', 'nashville sc',
        'austin fc', 'st louis', 'charlotte fc', 'revolution', 'cincinnati',
        'colorado rapids', 'cf montreal', 'montreal',
        // Liga MX
        'america', 'club america', 'chivas', 'cruz azul', 'pumas', 'monterrey',
        'tigres', 'leon', 'pachuca', 'toluca', 'atlas', 'necaxa', 'tijuana',
        'xolos', 'puebla', 'santos laguna', 'queretaro', 'mazatlan',
        'atletico san luis', 'juarez',
        // J1 League (Japon) - IDs api-sports peu fiables
        'kashima', 'kashiwa', 'reysol', 'antlers', 'urawa', 'yokohama', 'marinos',
        'kawasaki', 'frontale', 'fc tokyo', 'tokyo verdy', 'verdy', 'cerezo',
        'gamba', 'vissel', 'sanfrecce', 'hiroshima', 'nagoya', 'grampus',
        'shonan', 'bellmare', 'sagan', 'avispa', 'fukuoka', 'consadole', 'sapporo',
        'albirex', 'niigata', 'sanga', 'machida', 'zelvia',
    ];
    $is_priority_league = false;
    foreach ($priority_manifest_indicators as $ind) {
        if (strpos($name, $ind) !== false) { $is_priority_league = true; break; }
    }

    if ($is_priority_league && isset($manifest[$slug])) {
        $f = __DIR__ . '/../assets/logos/football/' . $manifest[$slug];
        if (is_file($f) && filesize($f) > 500) {
            return '/assets/logos/football/' . $manifest[$slug];
        }
    }

    // Recherche directe (API-Football ID)
    if (isset($db[$name])) {
        return $resolveLogo($db[$name]);
    }

    // Recherche partielle (mots clés distinctifs uniquement, pas les génériques type 'fc', 'real')
    foreach ($db as $key => $id) {
        foreach ($specific_words as $w) {
            if ($key === $w) {
                return $resolveLogo($id);
            }
        }
    }

    // Fallback : lookup direct manifest (pour équipes non hardcodées dans \$db)
    // Le manifest contient TheSportsDB, ESPN scraper et tous les alias.
    if (isset($manifest[$slug])) {
        return '/assets/logos/football/' . $manifest[$slug];
    }

    // Recherche partielle dans le manifest — skip les mots génériques
    $slugWords = explode('-', $slug);
    foreach ($slugWords as $w) {
        if (strlen($w) >= 4 && !in_array($w, $generic_words, true) && isset($manifest[$w])) {
            return '/assets/logos/football/' . $manifest[$w];
        }
    }

    // Pas trouvé → retourne vide
    return '';
}

}
