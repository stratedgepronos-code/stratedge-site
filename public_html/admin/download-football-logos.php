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
    4322 => 'K League 1 (Corée)',
    4346 => 'J1 League (Japon)',
    4350 => 'Liga MX (Mexique)',
    4356 => 'A-League (Australie)',
    4411 => 'Primera A (Colombie/Liga BetPlay)',
    4338 => 'Süper Lig (Turquie)',
    4334 => 'Eliteserien (Norvège)',
    4331 => 'Allsvenskan (Suède)',
    4330 => 'Veikkausliiga (Finlande)',
    4344 => 'Cyprus First Division',
    4394 => 'Pro League (Arabie)',
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
                // Ajouter aussi version sans accents/chars spéciaux + version raw
                $rawSlug = slugify(preg_replace('/\s*(fc|cf|ca|sc|ac|as)\s*/i', ' ', $name));
                if ($rawSlug !== $slug && strlen($rawSlug) > 2) {
                    $manifest[$rawSlug] = $fname;
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
// PHASE 3 — Manifest JSON
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
echo sprintf("  Total fichiers PNG      : %d\n", $totalFiles);
echo sprintf("  Taille totale           : %s\n", number_format($totalSize / 1024 / 1024, 2) . ' Mo');
echo sprintf("  Manifest entries        : %d slugs\n", count($manifest));
echo "\n✅ TERMINÉ.\n";
echo "\nProchaine étape :\n";
echo "  1. Vérifier /assets/logos/football/ contient bien les PNG\n";
echo "  2. stratedge_football_logo_local() va automatiquement utiliser ces fichiers\n";
echo "  3. Relancer ce script dans 1 mois pour MAJ nouvelles équipes\n";
