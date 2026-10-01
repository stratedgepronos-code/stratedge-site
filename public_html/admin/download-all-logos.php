<?php
/**
 * STRATEDGE — Téléchargeur master (tous sports + flags)
 *
 * Télécharge en batch les assets suivants :
 *
 *  [A] NBA     30 logos  depuis ESPN CDN      → /assets/logos/basket/
 *  [B] NHL     32 logos  depuis ESPN CDN      → /assets/logos/hockey/
 *  [C] MLB     30 logos  depuis ESPN CDN      → /assets/logos/baseball/
 *  [D] Flags   250       depuis flagcdn.com   → /assets/flags/
 *
 * Note : le football est géré par /admin/download-football-logos.php
 *        (source différente : media.api-sports.io + TheSportsDB)
 *
 * À lancer UNE FOIS via navigateur : /admin/download-all-logos.php
 * Relançable à volonté (skip existant > 500 bytes).
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/nba-logos-db.php';
require_once __DIR__ . '/../includes/nhl-logos-db.php';
require_once __DIR__ . '/../includes/mlb-logos-db.php';
require_once __DIR__ . '/../includes/flags-db.php';

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain; charset=utf-8');
@ini_set('implicit_flush', '1');
@ob_implicit_flush(true);
while (@ob_end_flush());

echo "═══════════════════════════════════════════════\n";
echo "  STRATEDGE — Téléchargeur master\n";
echo "═══════════════════════════════════════════════\n";
echo "  NBA + NHL + MLB + Flags\n";
echo "═══════════════════════════════════════════════\n\n";

// ────────────────────────────────────────────────
// UTILS
// ────────────────────────────────────────────────
function http_dl(string $url, int $timeout = 10): ?string {
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

function download_logos_batch(string $label, string $dir, string $urlPattern, array $teams): array {
    @mkdir($dir, 0755, true);
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  [$label] → " . count($teams) . " équipes\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $ok = 0; $skip = 0; $ko = 0; $errs = [];
    $uniqueAbbrs = array_unique(array_values($teams));
    echo "  Logos uniques à tirer : " . count($uniqueAbbrs) . "\n\n";

    foreach ($uniqueAbbrs as $abbr) {
        $outFile = $dir . '/' . $abbr . '.png';
        if (is_file($outFile) && filesize($outFile) > 500) {
            $skip++;
            continue;
        }
        $url = str_replace('{abbr}', $abbr, $urlPattern);
        $body = http_dl($url);
        if ($body !== null && strlen($body) > 500) {
            if (@file_put_contents($outFile, $body) !== false) {
                $ok++;
                echo "  ✓ $abbr.png\n";
            } else {
                $ko++;
                $errs[] = "WRITE FAIL: $abbr";
            }
        } else {
            $ko++;
            $errs[] = "FETCH FAIL: $abbr ($url)";
        }
        usleep(100000);
    }

    echo "\n  [$label DONE] $ok ok · $skip skip · $ko ko\n";
    if (!empty($errs)) {
        foreach ($errs as $e) echo "    • $e\n";
    }
    echo "\n";
    return compact('ok', 'skip', 'ko');
}

// ────────────────────────────────────────────────
// [A] NBA
// ────────────────────────────────────────────────
$nbaTeams = [
    'atl','bos','bkn','cha','chi','cle','dal','den','det','gs',
    'hou','ind','lac','lal','mem','mia','mil','min','no','ny',
    'okc','orl','phi','phx','por','sac','sa','tor','utah','wsh',
];
$nbaRes = download_logos_batch(
    'NBA',
    __DIR__ . '/../assets/logos/basket',
    'https://a.espncdn.com/i/teamlogos/nba/500/{abbr}.png',
    array_combine($nbaTeams, $nbaTeams)
);

// ────────────────────────────────────────────────
// [B] NHL
// ────────────────────────────────────────────────
$nhlTeams = [
    'ana','bos','buf','cgy','car','chi','col','cbj','dal','det',
    'edm','fla','la','min','mtl','nsh','nj','nyi','nyr','ott',
    'phi','pit','sj','sea','stl','tb','tor','utah','van','vgk',
    'wsh','wpg',
];
$nhlRes = download_logos_batch(
    'NHL',
    __DIR__ . '/../assets/logos/hockey',
    'https://a.espncdn.com/i/teamlogos/nhl/500/{abbr}.png',
    array_combine($nhlTeams, $nhlTeams)
);

// ────────────────────────────────────────────────
// [C] MLB
// ────────────────────────────────────────────────
$mlbTeams = [
    'ari','atl','bal','bos','chc','chw','cin','cle','col','det',
    'hou','kc','laa','lad','mia','mil','min','nym','nyy','oak',
    'phi','pit','sd','sf','sea','stl','tb','tex','tor','wsh',
];
$mlbRes = download_logos_batch(
    'MLB',
    __DIR__ . '/../assets/logos/baseball',
    'https://a.espncdn.com/i/teamlogos/mlb/500/{abbr}.png',
    array_combine($mlbTeams, $mlbTeams)
);

// ────────────────────────────────────────────────
// [D] FLAGS (flagcdn.com free, public)
// ────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  [FLAGS] → flagcdn.com\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$flagsDir = __DIR__ . '/../assets/flags';
@mkdir($flagsDir, 0755, true);

// Récupère la liste officielle ISO2 via l'API flagcdn
echo "  Récupération liste des pays...\n";
$codesJson = http_dl('https://flagcdn.com/en/codes.json', 20);
if ($codesJson === null) {
    echo "  ⚠️  Impossible de récupérer la liste des pays. Fallback: liste statique.\n";
    // Fallback : liste hardcodée des 100 pays courants
    $codes = [
        'fr','de','es','it','pt','nl','be','ch','at','gb','ie','dk','no','se','fi',
        'is','pl','cz','sk','hu','ro','bg','hr','rs','si','ba','me','al','mk','gr',
        'cy','mt','tr','ru','ua','by','ee','lv','lt','lu','li','mc','sm','ad','md',
        'us','ca','mx','br','ar','cl','co','uy','py','bo','pe','ec','ve','cr','pa',
        'gt','hn','sv','ni','cu','jm','ht','do','pr',
        'jp','cn','kr','kp','in','pk','bd','vn','th','id','my','sg','ph','kh',
        'la','mm','tw','hk','mn','kz','uz','kg','ir','sa','ae','qa','bh','kw','om',
        'ma','dz','tn','eg','ly','sn','ci','ml','bf','ne','gn','gh','tg','bj','ng',
        'cm','ga','cg','cd','ao','mz','za','zw','zm','bw','na','mg','mu','ke','tz',
        'ug','rw','et','sd','cv',
        'au','nz','fj',
    ];
    $codes = array_fill_keys($codes, '');
} else {
    $codes = json_decode($codesJson, true);
    if (!is_array($codes)) {
        echo "  ⚠️  Parse JSON échoué.\n";
        $codes = [];
    }
    echo "  ✓ " . count($codes) . " pays détectés\n";
}
echo "\n";

$ok4 = 0; $skip4 = 0; $ko4 = 0;
$i = 0; $total = count($codes);
foreach ($codes as $iso => $countryName) {
    $i++;
    $outFile = $flagsDir . '/' . $iso . '.png';
    if (is_file($outFile) && filesize($outFile) > 200) {
        $skip4++;
        continue;
    }
    $url = "https://flagcdn.com/w320/{$iso}.png";
    $body = http_dl($url);
    if ($body !== null && strlen($body) > 200) {
        if (@file_put_contents($outFile, $body) !== false) {
            $ok4++;
        } else {
            $ko4++;
        }
    } else {
        $ko4++;
    }
    if ($ok4 > 0 && $ok4 % 30 === 0) {
        echo "  → $ok4/$total flags téléchargés...\n";
        flush();
    }
    usleep(50000); // 50ms (flagcdn est rapide)
}
echo "\n  [FLAGS DONE] $ok4 ok · $skip4 skip · $ko4 ko\n\n";

// ────────────────────────────────────────────────
// RÉCAPITULATIF
// ────────────────────────────────────────────────
$totalFiles = 0;
$totalSize = 0;
foreach (['logos/basket','logos/hockey','logos/baseball','flags'] as $d) {
    foreach (glob(__DIR__ . '/../assets/' . $d . '/*') as $f) {
        if (is_file($f)) {
            $totalFiles++;
            $totalSize += filesize($f);
        }
    }
}

echo "═══════════════════════════════════════════════\n";
echo "  RÉCAPITULATIF FINAL\n";
echo "═══════════════════════════════════════════════\n";
echo sprintf("  NBA    : %d ok · %d skip · %d ko\n", $nbaRes['ok'], $nbaRes['skip'], $nbaRes['ko']);
echo sprintf("  NHL    : %d ok · %d skip · %d ko\n", $nhlRes['ok'], $nhlRes['skip'], $nhlRes['ko']);
echo sprintf("  MLB    : %d ok · %d skip · %d ko\n", $mlbRes['ok'], $mlbRes['skip'], $mlbRes['ko']);
echo sprintf("  FLAGS  : %d ok · %d skip · %d ko\n", $ok4, $skip4, $ko4);
echo sprintf("  -------\n");
echo sprintf("  Total fichiers : %d\n", $totalFiles);
echo sprintf("  Taille totale  : %s Mo\n", number_format($totalSize / 1024 / 1024, 2));
echo "\n✅ TERMINÉ.\n\n";
echo "Pour les logos football, lance séparément :\n";
echo "  /admin/download-football-logos.php\n";
echo "\nPour les photos joueurs (lazy-load automatique à l'usage) :\n";
echo "  stratedge_player_photo(2544, 'nba')  → LeBron James (cache 30j)\n";
