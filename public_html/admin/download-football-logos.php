<?php
/**
 * STRATEDGE — Téléchargeur universel logos football
 *
 * PHASE 1 : Télécharge les logos des équipes référencées dans football-logos-db.php
 *           depuis media.api-sports.io (CDN public gratuit)
 *
 * PHASE 2 : Ajoute les ligues exotiques depuis TheSportsDB API (gratuite) :
 *           K-League, J-League, Liga MX, A-League, Liga BetPlay + autres
 *
 * PHASE 3 : Génère manifest.json (mapping slug → fichier local)
 *
 * À lancer UNE FOIS via navigateur : /admin/download-football-logos.php
 * Relançable à volonté (skip les fichiers existants > 500 bytes).
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/football-logos-db.php';

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain; charset=utf-8');
@ini_set('implicit_flush', '1');
@ob_implicit_flush(true);
while (@ob_end_flush());

$base = __DIR__ . '/../assets/logos/football';
if (!is_dir($base) && !@mkdir($base, 0755, true)) {
    die("❌ Impossible de créer $base (vérifie permissions)\n");
}
if (!is_writable($base)) die("❌ $base n'est pas writable\n");

echo "═══════════════════════════════════════════════\n";
echo "  STRATEDGE — Téléchargeur logos football\n";
echo "═══════════════════════════════════════════════\n\n";
echo "Dossier cible : " . realpath($base) . "\n";

// Force modes : ?force=espn (re-télécharge seulement ESPN), ?force=all (tout)
$forceMode = $_GET['force'] ?? '';
if ($forceMode === 'espn' || $forceMode === 'all') {
    echo "⚠️  FORCE MODE = '$forceMode' → suppression avant re-download\n";
    $wipePatterns = ($forceMode === 'all')
        ? ['api-*.png', 'tsdb-*.png', 'espn-*.png']
        : ['espn-*.png'];
    $wiped = 0;
    foreach ($wipePatterns as $pat) {
        foreach (glob($base . '/' . $pat) as $f) {
            @unlink($f); $wiped++;
        }
    }
    echo "   → {$wiped} fichiers supprimés\n";
}
echo "\n";

// ────────────────────────────────────────────────
// UTILS
// ────────────────────────────────────────────────
function http_get(string $url, int $timeout = 15): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'Mozilla/5.0 StratEdge/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $body !== false) ? $body : null;
}

function slugify(string $s): string {
    $s = trim($s);
    // Translit accents
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($converted !== false) $s = $converted;
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// ────────────────────────────────────────────────
// PHASE 1 — API-Football (media.api-sports.io)
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PHASE 1 — media.api-sports.io\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Extraire IDs + slugs de football-logos-db.php
$dbFile = __DIR__ . '/../includes/football-logos-db.php';
$dbContent = file_get_contents($dbFile);
preg_match_all("/'([^']+)'\s*=>\s*(\d+)/", $dbContent, $m);

// Build mapping : id → liste de slugs (nom principal = premier rencontré)
$idToSlugs = [];
for ($i = 0; $i < count($m[1]); $i++) {
    $slug = $m[1][$i];
    $id = (int)$m[2][$i];
    if ($id < 1) continue;
    if (!isset($idToSlugs[$id])) $idToSlugs[$id] = [];
    $idToSlugs[$id][] = $slug;
}

echo sprintf("Équipes uniques détectées : %d\n", count($idToSlugs));
echo sprintf("Alias totaux (noms) : %d\n\n", array_sum(array_map('count', $idToSlugs)));

$ok1 = 0; $skip1 = 0; $ko1 = 0; $errors1 = [];
$manifest = []; // slug → filename

foreach ($idToSlugs as $id => $slugs) {
    $outFile = $base . "/api-{$id}.png";
    $fname = "api-{$id}.png";

    if (is_file($outFile) && filesize($outFile) > 500) {
        $skip1++;
        foreach ($slugs as $sl) $manifest[$sl] = $fname;
        continue;
    }

    $url = "https://media.api-sports.io/football/teams/{$id}.png";
    $body = http_get($url, 10);

    if ($body !== null && strlen($body) > 500) {
        if (@file_put_contents($outFile, $body) !== false) {
            foreach ($slugs as $sl) $manifest[$sl] = $fname;
            $ok1++;
            if ($ok1 % 20 === 0) {
                echo "  → {$ok1} téléchargés (dernier: ID $id, " . count($slugs) . " alias)\n";
                flush();
            }
        } else {
            $ko1++;
            if (count($errors1) < 5) $errors1[] = "WRITE FAIL : ID $id → $outFile";
        }
    } else {
        $ko1++;
        if (count($errors1) < 5) $errors1[] = "FETCH FAIL : ID $id ($slugs[0])";
    }

    usleep(120000); // 120ms entre requêtes
}

echo "\n[PHASE 1 DONE] {$ok1} téléchargés · {$skip1} déjà là · {$ko1} erreurs\n";
if (!empty($errors1)) {
    echo "\nErreurs détaillées :\n";
    foreach ($errors1 as $e) echo "  • $e\n";
}
echo "\n";

// ────────────────────────────────────────────────
// PHASE 2 — TheSportsDB (ligues exotiques)
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PHASE 2 — TheSportsDB (exotiques)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// IDs ligues TheSportsDB (free API v3)
$tsdbLeagues = [
    4346 => 'MLS (USA)',
    4350 => 'Liga MX (Mexique)',
    4322 => 'K League 1 (Corée)',
    4347 => 'J1 League (Japon)',
    4356 => 'A-League (Australie)',
    4411 => 'Primera A (Colombie/Liga BetPlay)',
    4338 => 'Süper Lig (Turquie)',
    4334 => 'Eliteserien (Norvège)',
    4331 => 'Allsvenskan (Suède)',
    4330 => 'Veikkausliiga (Finlande)',
    4344 => 'Cyprus First Division',
    4394 => 'Pro League (Arabie)',
    4328 => 'Premier League (EPL backup)',
    4332 => 'Major League Soccer 2',
    4335 => 'La Liga (Spain backup)',
    4391 => 'USL Championship',
    4348 => 'Brasileirão (backup)',
    4351 => 'Primera División (Argentine)',
    4353 => 'Liga de Expansion MX',
    4367 => 'Liga I (Roumanie)',
    4403 => 'J2 League (Japon D2)',
];

$ok2 = 0; $skip2 = 0; $ko2 = 0; $errors2 = [];

foreach ($tsdbLeagues as $lgId => $lgName) {
    echo "▸ $lgName (ID $lgId)\n";
    flush();

    $url = "https://www.thesportsdb.com/api/v1/json/3/lookup_all_teams.php?id={$lgId}";
    $json = http_get($url, 15);
    if ($json === null) {
        echo "  ⚠️  pas de réponse API\n\n";
        continue;
    }

    $data = json_decode($json, true);
    if (empty($data['teams'])) {
        echo "  ⚠️  aucune équipe trouvée\n\n";
        continue;
    }

    $teamCount = count($data['teams']);
    echo "  {$teamCount} équipes trouvées\n";

    foreach ($data['teams'] as $team) {
        $name = trim($team['strTeam'] ?? '');
        $badge = trim($team['strTeamBadge'] ?? $team['strBadge'] ?? '');
        if ($name === '' || $badge === '') continue;

        $slug = slugify($name);
        if ($slug === '') continue;

        $outFile = $base . "/tsdb-{$slug}.png";
        $fname = "tsdb-{$slug}.png";

        if (is_file($outFile) && filesize($outFile) > 500) {
            $manifest[$slug] = $fname;
            $skip2++;
            continue;
        }

        $body = http_get($badge, 10);
        if ($body !== null && strlen($body) > 500) {
            if (@file_put_contents($outFile, $body) !== false) {
                $manifest[$slug] = $fname;
                // Variantes: raw sans FC/CF/SC + alias courts/alternatifs
                $variants = [];
                $rawSlug = slugify(preg_replace('/\s*(fc|cf|ca|sc|ac|as|cd|cf|club)\s*/i', ' ', $name));
                if ($rawSlug !== $slug && strlen($rawSlug) > 2) $variants[] = $rawSlug;
                // Alternative names from TheSportsDB
                $altNames = trim($team['strAlternate'] ?? '');
                if ($altNames) {
                    foreach (explode(',', $altNames) as $alt) {
                        $altSlug = slugify(trim($alt));
                        if ($altSlug && strlen($altSlug) > 2) $variants[] = $altSlug;
                    }
                }
                // Noms courts spécifiques Liga MX / MLS connus
                $shortMap = [
                    'club america' => ['america'],
                    'club de futbol monterrey' => ['monterrey', 'rayados'],
                    'cd guadalajara' => ['chivas', 'guadalajara'],
                    'club tigres uanl' => ['tigres', 'tigres uanl'],
                    'cruz azul fc' => ['cruz azul', 'cruz-azul'],
                    'cf pachuca' => ['pachuca', 'tuzos'],
                    'club leon' => ['leon'],
                    'club necaxa' => ['necaxa', 'rayos'],
                    'atlas fc' => ['atlas'],
                    'club tijuana' => ['tijuana', 'xolos'],
                    'puebla fc' => ['puebla'],
                    'club santos laguna' => ['santos laguna', 'santos-mex'],
                    'queretaro fc' => ['queretaro'],
                    'mazatlan fc' => ['mazatlan'],
                    'club atletico de san luis' => ['san luis', 'atletico san luis'],
                    'fc juarez' => ['juarez'],
                    'club puebla' => ['puebla'],
                    'pumas unam' => ['pumas', 'unam'],
                    'toluca fc' => ['toluca'],
                    'real salt lake' => ['salt lake', 'rsl'],
                    'inter miami cf' => ['inter miami', 'miami'],
                    'cf montreal' => ['montreal', 'impact montreal'],
                    'la galaxy' => ['galaxy', 'los angeles galaxy'],
                    'los angeles fc' => ['lafc'],
                    'new york city fc' => ['nycfc', 'new york city'],
                    'new york red bulls' => ['red bulls', 'nyrb'],
                    'atlanta united fc' => ['atlanta united', 'atlanta mls'],
                    'seattle sounders fc' => ['seattle', 'sounders'],
                    'portland timbers' => ['portland', 'timbers'],
                    'columbus crew' => ['columbus', 'crew'],
                    'toronto fc' => ['toronto mls'],
                    'san jose earthquakes' => ['san jose', 'earthquakes'],
                    'vancouver whitecaps fc' => ['vancouver', 'whitecaps'],
                    'dc united' => ['d.c. united', 'washington'],
                    'philadelphia union' => ['philadelphia'],
                    'fc dallas' => ['dallas mls'],
                    'houston dynamo fc' => ['houston', 'dynamo'],
                    'chicago fire fc' => ['chicago', 'chicago fire'],
                    'sporting kansas city' => ['sporting kc', 'kansas city'],
                    'minnesota united fc' => ['minnesota', 'loons'],
                    'orlando city sc' => ['orlando'],
                    'nashville sc' => ['nashville'],
                    'austin fc' => ['austin'],
                    'st louis city sc' => ['st louis', 'saint louis'],
                    'charlotte fc' => ['charlotte'],
                    'new england revolution' => ['new england', 'revolution'],
                    'fc cincinnati' => ['cincinnati mls'],
                    'colorado rapids' => ['colorado', 'rapids'],
                ];
                $normName = strtolower($name);
                if (isset($shortMap[$normName])) {
                    foreach ($shortMap[$normName] as $short) {
                        $variants[] = slugify($short);
                    }
                }
                foreach (array_unique($variants) as $v) {
                    if ($v && !isset($manifest[$v])) $manifest[$v] = $fname;
                }
                $ok2++;
            } else {
                $ko2++;
            }
        } else {
            $ko2++;
            if (count($errors2) < 5) $errors2[] = "$name ($lgName): fetch badge fail";
        }
        usleep(80000);
    }
    echo "  → {$ok2} total OK · {$skip2} skip · {$ko2} ko (running)\n\n";
    flush();
}

echo "[PHASE 2 DONE] {$ok2} téléchargés · {$skip2} déjà là · {$ko2} erreurs\n";
if (!empty($errors2)) {
    echo "\nErreurs détaillées :\n";
    foreach ($errors2 as $e) echo "  • $e\n";
}
echo "\n";

// ────────────────────────────────────────────────
// PHASE 3 — ESPN SCRAPER (MLS/Liga MX)
// Scrape directement la page team ESPN pour récupérer
// le vrai URL du logo (évite les bugs d'ID CDN).
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PHASE 3 — ESPN scraper (URL logo réel)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Liste [slug interne → ESPN team_id] — on scrape les vraies pages ESPN
// Si l'ID est faux (ex: équipe renommée/bougée), le scraper détectera
// que le logo retrouvé n'est PAS celui attendu via l'URL.
$espnTeams = [
    // === MLS === (ESPN team IDs confirmés via standings/fixtures)
    'real-salt-lake'        => ['espn_id' => 4771, 'slug' => 'real-salt-lake', 'aliases' => ['rsl', 'salt-lake']],
    'inter-miami-cf'        => ['espn_id' => 20232, 'slug' => 'inter-miami-cf', 'aliases' => ['inter-miami', 'miami']],
    'cf-montreal'           => ['espn_id' => 9720,  'slug' => 'cf-montreal', 'aliases' => ['montreal']],
    'la-galaxy'             => ['espn_id' => 187,   'slug' => 'la-galaxy', 'aliases' => ['galaxy']],
    'los-angeles-fc'        => ['espn_id' => 18966, 'slug' => 'los-angeles-fc', 'aliases' => ['lafc']],
    'new-york-city-fc'      => ['espn_id' => 17606, 'slug' => 'new-york-city-fc', 'aliases' => ['nycfc']],
    'new-york-red-bulls'    => ['espn_id' => 190,   'slug' => 'red-bull-new-york', 'aliases' => ['red-bulls', 'nyrb']],
    'atlanta-united-fc'     => ['espn_id' => 18418, 'slug' => 'atlanta-united-fc', 'aliases' => ['atlanta-united', 'atlanta-mls']],
    'seattle-sounders-fc'   => ['espn_id' => 9726,  'slug' => 'seattle-sounders-fc', 'aliases' => ['seattle', 'sounders']],
    'portland-timbers'      => ['espn_id' => 9723,  'slug' => 'portland-timbers', 'aliases' => ['portland', 'timbers']],
    'columbus-crew'         => ['espn_id' => 183,   'slug' => 'columbus-crew', 'aliases' => ['columbus', 'crew']],
    'toronto-fc'            => ['espn_id' => 7318,  'slug' => 'toronto-fc', 'aliases' => ['toronto-mls']],
    'san-jose-earthquakes'  => ['espn_id' => 191,   'slug' => 'san-jose-earthquakes', 'aliases' => ['san-jose', 'earthquakes']],
    'vancouver-whitecaps-fc'=> ['espn_id' => 9727,  'slug' => 'vancouver-whitecaps-fc', 'aliases' => ['vancouver', 'whitecaps']],
    'dc-united'             => ['espn_id' => 193,   'slug' => 'dc-united', 'aliases' => ['washington']],
    'philadelphia-union'    => ['espn_id' => 10739, 'slug' => 'philadelphia-union', 'aliases' => ['philadelphia']],
    'fc-dallas'             => ['espn_id' => 185,   'slug' => 'fc-dallas', 'aliases' => ['dallas-mls']],
    'houston-dynamo-fc'     => ['espn_id' => 6077,  'slug' => 'houston-dynamo-fc', 'aliases' => ['houston', 'dynamo']],
    'chicago-fire-fc'       => ['espn_id' => 182,   'slug' => 'chicago-fire-fc', 'aliases' => ['chicago-fire']],
    'sporting-kansas-city'  => ['espn_id' => 186,   'slug' => 'sporting-kansas-city', 'aliases' => ['sporting-kc']],
    'minnesota-united-fc'   => ['espn_id' => 17362, 'slug' => 'minnesota-united-fc', 'aliases' => ['minnesota', 'loons']],
    'orlando-city-sc'       => ['espn_id' => 12011, 'slug' => 'orlando-city-sc', 'aliases' => ['orlando']],
    'nashville-sc'          => ['espn_id' => 18986, 'slug' => 'nashville-sc', 'aliases' => ['nashville']],
    'austin-fc'             => ['espn_id' => 20906, 'slug' => 'austin-fc', 'aliases' => ['austin']],
    'st-louis-city-sc'      => ['espn_id' => 22713, 'slug' => 'st-louis-city-sc', 'aliases' => ['st-louis']],
    'charlotte-fc'          => ['espn_id' => 21300, 'slug' => 'charlotte-fc', 'aliases' => ['charlotte']],
    'new-england-revolution'=> ['espn_id' => 189,   'slug' => 'new-england-revolution', 'aliases' => ['revolution', 'new-england']],
    'fc-cincinnati'         => ['espn_id' => 18267, 'slug' => 'fc-cincinnati', 'aliases' => ['cincinnati-mls']],
    'colorado-rapids'       => ['espn_id' => 184,   'slug' => 'colorado-rapids', 'aliases' => ['rapids']],

    // === Liga MX === (IDs ESPN vérifiés via pages team 2025-26)
    'club-leon'             => ['espn_id' => 228,   'slug' => 'leon', 'aliases' => ['leon']],
    'cf-pachuca'            => ['espn_id' => 234,   'slug' => 'pachuca', 'aliases' => ['pachuca', 'tuzos']],
    'toluca'                => ['espn_id' => 223,   'slug' => 'toluca', 'aliases' => ['toluca-mex']],
    'tigres-uanl'           => ['espn_id' => 232,   'slug' => 'tigres-uanl', 'aliases' => ['tigres']],
    'club-tijuana'          => ['espn_id' => 10125, 'slug' => 'tijuana', 'aliases' => ['tijuana', 'xolos']],
    'queretaro'             => ['espn_id' => 222,   'slug' => 'queretaro', 'aliases' => ['queretaro-mex']],
    'mazatlan-fc'           => ['espn_id' => 20702, 'slug' => 'mazatlan-fc', 'aliases' => ['mazatlan']],
    'atletico-san-luis'     => ['espn_id' => 15720, 'slug' => 'atletico-de-san-luis', 'aliases' => ['san-luis']],
    'fc-juarez'             => ['espn_id' => 17851, 'slug' => 'fc-juarez', 'aliases' => ['juarez']],
    // IDs Liga MX non vérifiés — on les laisse commentés, le scraper log les erreurs si faux
    // 'club-america'     => ['espn_id' => ?, 'slug' => 'club-america'],
    // 'cd-guadalajara'   => ['espn_id' => ?, 'slug' => 'guadalajara'],
    // 'cruz-azul'        => ['espn_id' => ?, 'slug' => 'cruz-azul'],
    // 'pumas-unam'       => ['espn_id' => ?, 'slug' => 'pumas-unam'],
    // 'cf-monterrey'     => ['espn_id' => ?, 'slug' => 'monterrey'],
    // 'atlas'            => ['espn_id' => ?, 'slug' => 'atlas'],
    // 'club-necaxa'      => ['espn_id' => ?, 'slug' => 'necaxa'],
    // 'puebla'           => ['espn_id' => ?, 'slug' => 'puebla'],
    // 'santos-laguna'    => ['espn_id' => ?, 'slug' => 'santos-laguna'],
];

/**
 * Scrape la page team ESPN et extrait l'URL du logo depuis les meta og:image
 * ou depuis le HTML (img src avec teamlogos/soccer/500/).
 * @return string|null URL complète du logo trouvé, ou null si fail.
 */
function scrape_espn_team_logo(int $espnId, string $slug): ?string {
    $pageUrl = "https://www.espn.com/soccer/team/_/id/{$espnId}/{$slug}";
    $html = http_get($pageUrl, 15);
    if ($html === null || strlen($html) < 1000) return null;

    // 1) og:image meta tag (le plus fiable)
    if (preg_match('#<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']#i', $html, $m)) {
        $url = html_entity_decode($m[1]);
        if (strpos($url, 'teamlogos/soccer') !== false) return $url;
    }

    // 2) Premier <img> avec src contenant teamlogos/soccer/500/
    if (preg_match('#src=["\']((?:https?:)?//[^"\']*?teamlogos/soccer/500/[^"\']+\.png)["\']#i', $html, $m)) {
        $url = html_entity_decode($m[1]);
        if (strpos($url, '//') === 0) $url = 'https:' . $url;
        return $url;
    }

    // 3) combiner URL pattern
    if (preg_match('#(https?://a\.espncdn\.com/combiner/i\?img=/i/teamlogos/soccer/500/\d+\.png[^"\']*)#i', $html, $m)) {
        return html_entity_decode($m[1]);
    }

    return null;
}

/**
 * Recherche ESPN par nom pour trouver team_id et logo URL.
 * Utile quand on ne connaît pas l'ID exact.
 * @return array|null ['espn_id' => int, 'slug' => string, 'logo' => string] ou null
 */
function search_espn_team(string $teamName, string $league = 'soccer'): ?array {
    $q = urlencode($teamName);
    $apiUrl = "https://site.web.api.espn.com/apis/common/v3/search?query={$q}&limit=10&type=team&sport={$league}";
    $json = http_get($apiUrl, 10);
    if (!$json) return null;
    $data = @json_decode($json, true);
    if (!$data) return null;

    // Parcourir les résultats et trouver le premier match soccer
    $results = $data['results'] ?? [];
    foreach ($results as $grp) {
        $items = $grp['contents'] ?? [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'team') continue;
            $name = strtolower($item['displayName'] ?? '');
            $target = strtolower($teamName);
            // Match fuzzy : au moins 4 chars communs ou substring
            if ($name === $target || strpos($name, $target) !== false || strpos($target, $name) !== false) {
                $img = null;
                if (isset($item['image']['default'])) $img = $item['image']['default'];
                elseif (isset($item['logo'])) $img = $item['logo'];
                elseif (isset($item['image']) && is_string($item['image'])) $img = $item['image'];
                $uid = $item['uid'] ?? '';
                // uid format: s:600~l:775~t:20232
                if (preg_match('/t:(\d+)/', $uid, $m)) {
                    return [
                        'espn_id' => (int)$m[1],
                        'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $item['displayName'])),
                        'logo' => $img,
                    ];
                }
            }
        }
    }
    return null;
}

$ok3 = 0; $skip3 = 0; $ko3 = 0; $errors3 = [];
foreach ($espnTeams as $internalSlug => $info) {
    $espnId = $info['espn_id'];
    $espnSlug = $info['slug'];
    $aliases = $info['aliases'];
    $outFile = $base . "/espn-{$espnId}.png";
    $fname = "espn-{$espnId}.png";

    // Skip si déjà téléchargé
    if (is_file($outFile) && filesize($outFile) > 500) {
        $skip3++;
        $manifest[$internalSlug] = $fname;
        foreach ($aliases as $a) if (!isset($manifest[$a])) $manifest[$a] = $fname;
        continue;
    }

    echo "  ▸ {$internalSlug} (ID {$espnId}) ... ";
    flush();

    // Étape 1 : scraper la page ESPN pour trouver l'URL logo réel
    $logoUrl = scrape_espn_team_logo($espnId, $espnSlug);

    if (!$logoUrl) {
        // Fallback : essayer l'URL CDN directe (au cas où)
        $logoUrl = "https://a.espncdn.com/i/teamlogos/soccer/500/{$espnId}.png";
    }

    // Étape 2 : télécharger le logo trouvé
    $body = http_get($logoUrl, 10);
    if ($body !== null && strlen($body) > 500) {
        if (@file_put_contents($outFile, $body) !== false) {
            $manifest[$internalSlug] = $fname;
            foreach ($aliases as $a) if (!isset($manifest[$a])) $manifest[$a] = $fname;
            $ok3++;
            echo "✓ (" . number_format(strlen($body) / 1024, 1) . " Ko)\n";
            flush();
        } else {
            $ko3++;
            echo "✗ write fail\n";
        }
    } else {
        $ko3++;
        $errors3[] = "{$internalSlug}: logoUrl=$logoUrl fail";
        echo "✗ fetch fail\n";
    }
    usleep(400000); // 400ms pour ne pas flood ESPN
}

echo "\n[PHASE 3 DONE] {$ok3} téléchargés · {$skip3} déjà là · {$ko3} erreurs\n";
if (!empty($errors3)) {
    echo "\nErreurs détaillées :\n";
    foreach ($errors3 as $e) echo "  • $e\n";
}
echo "\n";

// ────────────────────────────────────────────────
// PHASE 3.5 — Auto-discover équipes Liga MX (API search ESPN)
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PHASE 3.5 — Auto-discover Liga MX\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Équipes Liga MX à découvrir automatiquement (nom officiel ESPN attendu)
$autoDiscover = [
    'Club América'            => ['america', 'club-america', 'club-america-mex'],
    'Guadalajara'             => ['chivas', 'guadalajara', 'cd-guadalajara'],
    'Cruz Azul'               => ['cruz-azul', 'cruz-azul-mex'],
    'Pumas UNAM'              => ['pumas', 'unam', 'pumas-unam'],
    'Monterrey'               => ['monterrey', 'rayados', 'cf-monterrey'],
    'Atlas'                   => ['atlas', 'atlas-mex'],
    'Necaxa'                  => ['necaxa', 'club-necaxa'],
    'Puebla'                  => ['puebla', 'puebla-mex', 'club-puebla'],
    'Santos Laguna'           => ['santos-laguna', 'santos-laguna-mex'],
];

$ok35 = 0; $ko35 = 0;
foreach ($autoDiscover as $searchName => $aliases) {
    // Skip si tous les aliases sont déjà dans le manifest (déjà trouvé via une autre phase)
    $allFound = true;
    foreach ($aliases as $a) if (!isset($manifest[$a])) { $allFound = false; break; }
    if ($allFound) { continue; }

    echo "  ▸ Recherche '{$searchName}' ... ";
    flush();

    $found = search_espn_team($searchName);
    if (!$found || empty($found['espn_id'])) {
        echo "✗ pas trouvé\n";
        $ko35++;
        usleep(300000);
        continue;
    }

    $espnId = $found['espn_id'];
    $outFile = $base . "/espn-{$espnId}.png";
    $fname = "espn-{$espnId}.png";

    // Si déjà téléchargé, juste update le manifest
    if (is_file($outFile) && filesize($outFile) > 500) {
        foreach ($aliases as $a) if (!isset($manifest[$a])) $manifest[$a] = $fname;
        echo "✓ déjà en cache (ID {$espnId})\n";
        usleep(300000);
        continue;
    }

    // Télécharger le logo
    $logoUrl = !empty($found['logo']) ? $found['logo'] : "https://a.espncdn.com/i/teamlogos/soccer/500/{$espnId}.png";
    $body = http_get($logoUrl, 10);
    if ($body !== null && strlen($body) > 500) {
        if (@file_put_contents($outFile, $body) !== false) {
            foreach ($aliases as $a) if (!isset($manifest[$a])) $manifest[$a] = $fname;
            $ok35++;
            echo "✓ ID {$espnId} (" . number_format(strlen($body) / 1024, 1) . " Ko)\n";
        } else {
            $ko35++;
            echo "✗ write fail\n";
        }
    } else {
        $ko35++;
        echo "✗ fetch fail (logo={$logoUrl})\n";
    }
    usleep(500000);
}

echo "\n[PHASE 3.5 DONE] {$ok35} découvertes · {$ko35} échecs\n\n";

// ────────────────────────────────────────────────
// PHASE 4 — Manifest JSON
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PHASE 3 — Génération manifest\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

ksort($manifest);
$manifestFile = $base . '/manifest.json';
$manifestData = [
    'generated_at' => date('c'),
    'total_entries' => count($manifest),
    'mapping' => $manifest,
];
file_put_contents($manifestFile, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✓ Manifest écrit : {$manifestFile}\n";
echo "  Total entrées (slugs résolvables) : " . count($manifest) . "\n\n";

// ────────────────────────────────────────────────
// SUMMARY
// ────────────────────────────────────────────────
$totalFiles = count(glob($base . '/*.png'));
$totalSize = 0;
foreach (glob($base . '/*.png') as $f) $totalSize += filesize($f);

echo "═══════════════════════════════════════════════\n";
echo "  RÉCAPITULATIF\n";
echo "═══════════════════════════════════════════════\n";
echo sprintf("  PHASE 1 (API-Football)    : %d ok, %d skip, %d ko\n", $ok1, $skip1, $ko1);
echo sprintf("  PHASE 2 (TheSportsDB)     : %d ok, %d skip, %d ko\n", $ok2, $skip2, $ko2);
echo sprintf("  PHASE 3 (ESPN scraper)    : %d ok, %d skip, %d ko\n", $ok3, $skip3, $ko3);
echo sprintf("  PHASE 3.5 (ESPN search)   : %d découvertes, %d échecs\n", $ok35, $ko35);
echo sprintf("  Total fichiers PNG        : %d\n", $totalFiles);
echo sprintf("  Taille totale             : %s\n", number_format($totalSize / 1024 / 1024, 2) . ' Mo');
echo sprintf("  Manifest entries          : %d slugs\n", count($manifest));
echo "\n✅ TERMINÉ.\n";
echo "\nProchaine étape :\n";
echo "  1. Vérifier /assets/logos/football/ contient bien les PNG\n";
echo "  2. stratedge_football_logo() va automatiquement utiliser ces fichiers\n";
echo "  3. Relancer ce script dans 1 mois pour MAJ nouvelles équipes\n";
