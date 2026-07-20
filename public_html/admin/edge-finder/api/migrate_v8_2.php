<?php
/**
 * StratEdge Edge Finder — migration v8.2 « filtres de cohérence ».
 *
 * Ajoute les colonnes de persistance des verdicts du module
 * src/filters/coherence.py (pipeline) pour pouvoir répondre en une requête à
 * « pourquoi ce pick n'est pas sorti ? ».
 *
 * ⚠️ Les tables pick_candidates / pick_matches vivent dans la base du SITE,
 * pas dans celle du pipeline Python — d'où cette migration en PHP.
 *
 * Idempotent : vérifie l'existence avant chaque ALTER (le MySQL de prod ne
 * supporte pas `ADD COLUMN IF NOT EXISTS`, syntaxe MariaDB).
 *
 * Usage (CLI uniquement) :
 *   php /var/www/stratedgepronos.fr/public_html/admin/edge-finder/api/migrate_v8_2.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.');
}

require_once __DIR__ . '/../lib/db.php';

$plan = [
    'pick_candidates' => [
        'recommendable' => "TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'v8.2 : survit aux filtres P1'",
        'tracking_only' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'v8.2 P1.1 : Under suivi, jamais recommande'",
        'rejections'    => "TEXT NULL COMMENT 'v8.2 : [{market,reason}] raisons de rejet'",
    ],
    'pick_matches' => [
        'data_suspect'   => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'v8.2 P1.3 : potentials corrompus'",
        'quarantine'     => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'v8.2 P1.4 : desaccord DC/potentials'",
        'coherence_json' => "TEXT NULL COMMENT 'v8.2 : raisons match + best_signal_missed'",
    ],
];

$added = 0;
foreach ($plan as $table => $cols) {
    $existing = [];
    foreach (SE_Db::queryAll(
        "SELECT COLUMN_NAME AS c FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?", [$table]) as $r) {
        $existing[$r['c']] = true;
    }
    if (!$existing) {
        echo "  !! table $table introuvable\n";
        continue;
    }
    foreach ($cols as $name => $ddl) {
        if (isset($existing[$name])) {
            echo "  = $table.$name deja present\n";
            continue;
        }
        try {
            SE_Db::execute("ALTER TABLE `$table` ADD COLUMN `$name` $ddl");
            echo "  OK $table.$name\n";
            $added++;
        } catch (Throwable $e) {
            echo "  KO $table.$name : " . substr($e->getMessage(), 0, 80) . "\n";
        }
    }
}

echo "\nVerification :\n";
foreach ($plan as $table => $cols) {
    $have = [];
    foreach (SE_Db::queryAll(
        "SELECT COLUMN_NAME AS c FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?", [$table]) as $r) {
        if (isset($cols[$r['c']])) $have[] = $r['c'];
    }
    echo "  $table : " . (implode(', ', $have) ?: '(aucune)') . "\n";
}
echo "\n$added colonne(s) ajoutee(s).\n";
