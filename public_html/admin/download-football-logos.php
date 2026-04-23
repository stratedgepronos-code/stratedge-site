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
echo "Dossier cible : " . realpath($base) . "\n\n";

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
// PHASE 3 — ESPN CDN fallback (MLS/Liga MX complémentaire)
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PHASE 3 — ESPN CDN (MLS/Liga MX fallback)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// ESPN team IDs format: https://a.espncdn.com/i/teamlogos/soccer/500/{espn_id}.png
// MLS + Liga MX teams with manually verified ESPN IDs
$espnTeams = [
    // === MLS ===
    'real-salt-lake'        => ['espn_id' => 4771, 'aliases' => ['rsl', 'salt-lake']],
    'inter-miami-cf'        => ['espn_id' => 20232, 'aliases' => ['inter-miami', 'miami']],
    'cf-montreal'           => ['espn_id' => 9720, 'aliases' => ['montreal']],
    'la-galaxy'             => ['espn_id' => 187, 'aliases' => ['galaxy']],
    'los-angeles-fc'        => ['espn_id' => 18966, 'aliases' => ['lafc']],
    'new-york-city-fc'      => ['espn_id' => 17012, 'aliases' => ['nycfc']],
    'new-york-red-bulls'    => ['espn_id' => 190, 'aliases' => ['red-bulls', 'nyrb']],
    'atlanta-united-fc'     => ['espn_id' => 18418, 'aliases' => ['atlanta-united', 'atlanta-mls']],
    'seattle-sounders-fc'   => ['espn_id' => 9726, 'aliases' => ['seattle', 'sounders']],
    'portland-timbers'      => ['espn_id' => 9723, 'aliases' => ['portland', 'timbers']],
    'columbus-crew'         => ['espn_id' => 183, 'aliases' => ['columbus', 'crew']],
    'toronto-fc'            => ['espn_id' => 7318, 'aliases' => ['toronto-mls']],
    'san-jose-earthquakes'  => ['espn_id' => 191, 'aliases' => ['san-jose', 'earthquakes']],
    'vancouver-whitecaps-fc'=> ['espn_id' => 9727, 'aliases' => ['vancouver', 'whitecaps']],
    'dc-united'             => ['espn_id' => 193, 'aliases' => ['washington']],
    'philadelphia-union'    => ['espn_id' => 10739, 'aliases' => ['philadelphia']],
    'fc-dallas'             => ['espn_id' => 185, 'aliases' => ['dallas-mls']],
    'houston-dynamo-fc'     => ['espn_id' => 6077, 'aliases' => ['houston', 'dynamo']],
    'chicago-fire-fc'       => ['espn_id' => 182, 'aliases' => ['chicago-fire']],
    'sporting-kansas-city'  => ['espn_id' => 186, 'aliases' => ['sporting-kc']],
    'minnesota-united-fc'   => ['espn_id' => 17362, 'aliases' => ['minnesota', 'loons']],
    'orlando-city-sc'       => ['espn_id' => 12363, 'aliases' => ['orlando']],
    'nashville-sc'          => ['espn_id' => 18986, 'aliases' => ['nashville']],
    'austin-fc'             => ['espn_id' => 20906, 'aliases' => ['austin']],
    'st-louis-city-sc'      => ['espn_id' => 22713, 'aliases' => ['st-louis']],
    'charlotte-fc'          => ['espn_id' => 21300, 'aliases' => ['charlotte']],
    'new-england-revolution'=> ['espn_id' => 189, 'aliases' => ['revolution', 'new-england']],
    'fc-cincinnati'         => ['espn_id' => 18267, 'aliases' => ['cincinnati-mls']],
    'colorado-rapids'       => ['espn_id' => 184, 'aliases' => ['rapids']],

    // === Liga MX ===
    'club-america'          => ['espn_id' => 229, 'aliases' => ['america', 'club-america-mex']],
    'cd-guadalajara'        => ['espn_id' => 233, 'aliases' => ['chivas', 'guadalajara']],
    'cruz-azul'             => ['espn_id' => 228, 'aliases' => ['cruz-azul-mex']],
    'pumas-unam'            => ['espn_id' => 235, 'aliases' => ['pumas', 'unam']],
    'cf-monterrey'          => ['espn_id' => 231, 'aliases' => ['monterrey', 'rayados']],
    'tigres-uanl'           => ['espn_id' => 2296, 'aliases' => ['tigres']],
    'club-leon'             => ['espn_id' => 237, 'aliases' => ['leon']],
    'cf-pachuca'            => ['espn_id' => 241, 'aliases' => ['pachuca', 'tuzos']],
    'toluca'                => ['espn_id' => 240, 'aliases' => ['toluca-mex']],
    'atlas'                 => ['espn_id' => 230, 'aliases' => ['atlas-mex']],
    'club-necaxa'           => ['espn_id' => 236, 'aliases' => ['necaxa']],
    'club-tijuana'          => ['espn_id' => 2295, 'aliases' => ['tijuana', 'xolos']],
    'puebla'                => ['espn_id' => 239, 'aliases' => ['puebla-mex']],
    'santos-laguna'         => ['espn_id' => 238, 'aliases' => ['santos-laguna-mex']],
    'queretaro'             => ['espn_id' => 242, 'aliases' => ['queretaro-mex']],
    'mazatlan-fc'           => ['espn_id' => 20672, 'aliases' => ['mazatlan']],
    'atletico-san-luis'     => ['espn_id' => 9659, 'aliases' => ['san-luis']],
    'fc-juarez'             => ['espn_id' => 19320, 'aliases' => ['juarez']],
];

$ok3 = 0; $skip3 = 0; $ko3 = 0;
foreach ($espnTeams as $slug => $info) {
    $espnId = $info['espn_id'];
    $aliases = $info['aliases'];
    $outFile = $base . "/espn-{$espnId}.png";
    $fname = "espn-{$espnId}.png";

    if (is_file($outFile) && filesize($outFile) > 500) {
        $skip3++;
        $manifest[$slug] = $fname;
        foreach ($aliases as $a) if (!isset($manifest[$a])) $manifest[$a] = $fname;
        continue;
    }

    $url = "https://a.espncdn.com/i/teamlogos/soccer/500/{$espnId}.png";
    $body = http_get($url, 10);
    if ($body !== null && strlen($body) > 500) {
        if (@file_put_contents($outFile, $body) !== false) {
            $manifest[$slug] = $fname;
            foreach ($aliases as $a) if (!isset($manifest[$a])) $manifest[$a] = $fname;
            $ok3++;
            if ($ok3 % 10 === 0) { echo "  → {$ok3} téléchargés\n"; flush(); }
        } else { $ko3++; }
    } else { $ko3++; }
    usleep(100000);
}

echo "\n[PHASE 3 DONE] {$ok3} téléchargés · {$skip3} déjà là · {$ko3} erreurs\n\n";

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
echo sprintf("  PHASE 1 (API-Football)  : %d ok, %d skip, %d ko\n", $ok1, $skip1, $ko1);
echo sprintf("  PHASE 2 (TheSportsDB)   : %d ok, %d skip, %d ko\n", $ok2, $skip2, $ko2);
echo sprintf("  PHASE 3 (ESPN CDN)      : %d ok, %d skip, %d ko\n", $ok3, $skip3, $ko3);
echo sprintf("  Total fichiers PNG      : %d\n", $totalFiles);
echo sprintf("  Taille totale           : %s\n", number_format($totalSize / 1024 / 1024, 2) . ' Mo');
echo sprintf("  Manifest entries        : %d slugs\n", count($manifest));
echo "\n✅ TERMINÉ.\n";
echo "\nProchaine étape :\n";
echo "  1. Vérifier /assets/logos/football/ contient bien les PNG\n";
echo "  2. stratedge_football_logo() va automatiquement utiliser ces fichiers\n";
echo "  3. Relancer ce script dans 1 mois pour MAJ nouvelles équipes\n";
