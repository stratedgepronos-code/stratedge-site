<?php
/**
 * Cotes moyennes par tipster pour la page d'accueil.
 * Aligne sur historique.php : utilise posted_by_role pour le routage
 * - MULTI  = posted_by_role='superadmin' (Alex, incl. Fun Bet 3.05 ID:277)
 * - TENNIS = posted_by_role='admin_tennis' (Shuriik) OU categorie='tennis' fallback
 * - FUN    = posted_by_role='admin_fun' (Morrayaffa)
 */
declare(strict_types=1);

function stratedge_avg_cote_for_bets(array $arr): ?float {
    $cotes = array_filter(array_map(static function ($b) {
        $c = (float)str_replace(',', '.', (string)($b['cote'] ?? '0'));
        return $c > 0 ? $c : null;
    }, $arr));
    if (count($cotes) === 0) {
        return null;
    }
    return round(array_sum($cotes) / count($cotes), 2);
}

/**
 * Cotes moyennes par tipster (meme logique que historique.php)
 * v2 : agregation faite EN SQL — l'ancienne version chargeait TOUTE la table
 * bets en memoire PHP (fetchAll) et a fini par exploser memory_limit (256M)
 * quand la table a grossi -> erreur 500 sur la home le 08/07/2026.
 *
 * @return array{multisport: ?float, tennis: ?float, fun: ?float}
 */
function stratedge_cotes_moyennes_par_categorie(?PDO $db = null): array {
    if ($db === null) {
        require_once __DIR__ . '/db.php';
        $db = getDB();
    }

    $out = ['multisport' => null, 'tennis' => null, 'fun' => null];

    try {
        // Meme filtre + meme routage tipster que historique.php, mais agrege
        // cote base : 3 lignes retournees au maximum, memoire constante.
        $rows = $db->query("
            SELECT
                CASE
                    WHEN posted_by_role = 'admin_tennis' THEN 'tennis'
                    WHEN posted_by_role = 'admin_fun'    THEN 'fun'
                    WHEN posted_by_role = 'superadmin'   THEN 'multisport'
                    WHEN categorie = 'tennis'            THEN 'tennis'
                    ELSE 'multisport'
                END AS tipster,
                AVG(CAST(REPLACE(cote, ',', '.') AS DECIMAL(10,4))) AS avg_cote
            FROM bets
            WHERE (resultat IS NULL OR resultat NOT IN ('en_cours','pending'))
              AND CAST(REPLACE(cote, ',', '.') AS DECIMAL(10,4)) > 0
            GROUP BY tipster
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $k = $r['tipster'];
            if (array_key_exists($k, $out) && $r['avg_cote'] !== null) {
                $out[$k] = round((float)$r['avg_cote'], 2);
            }
        }
    } catch (Throwable $e) {
        // La home ne doit JAMAIS tomber pour un widget de cotes moyennes.
        error_log('stratedge_cotes_moyennes_par_categorie: ' . $e->getMessage());
    }

    return $out;
}

// Legacy: ces fonctions etaient utilisees avant, on les garde pour backward-compat
// au cas ou d'autres fichiers les appellent encore.
function stratedge_bet_categories_config(): array {
    return [
        'multisport' => ['label' => 'Multisports', 'sections' => []],
        'tennis'     => ['label' => 'Tennis',     'sections' => []],
        'fun'        => ['label' => 'Fun',        'sections' => []],
    ];
}

function stratedge_bet_section_key(array $b): string {
    $sport = strtolower(trim((string)($b['sport'] ?? 'football')));
    if (!in_array($sport, ['tennis','football','basket','hockey','baseball'], true)) $sport = 'football';
    $type = (string)($b['type'] ?? 'safe');
    if (strpos($type, 'live') !== false) $t = 'live';
    elseif (strpos($type, 'fun') !== false) $t = 'fun';
    else $t = 'safe';
    return $sport . '_' . $t;
}
