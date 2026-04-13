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
 *
 * @return array{multisport: ?float, tennis: ?float, fun: ?float}
 */
function stratedge_cotes_moyennes_par_categorie(?PDO $db = null): array {
    if ($db === null) {
        require_once __DIR__ . '/db.php';
        $db = getDB();
    }

    // Meme filtre que historique.php (bets avec resultat connu OU en attente, pas en_cours)
    $bets = $db->query("
        SELECT * FROM bets
        WHERE (resultat IS NULL OR resultat NOT IN ('en_cours','pending'))
        ORDER BY COALESCE(date_resultat, date_post) DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Helper routage tipster (identique a historique.php)
    $tipsterOf = static function (array $b): string {
        $role = $b['posted_by_role'] ?? '';
        if ($role === 'admin_tennis') return 'tennis';
        if ($role === 'admin_fun') return 'fun';
        if ($role === 'superadmin') return 'multisport';
        // Fallback bets pre-migration
        if (($b['categorie'] ?? '') === 'tennis') return 'tennis';
        return 'multisport';
    };

    $multi = [];
    $tennis = [];
    $fun = [];
    foreach ($bets as $b) {
        $t = $tipsterOf($b);
        if ($t === 'tennis')     $tennis[] = $b;
        elseif ($t === 'fun')    $fun[] = $b;
        else                     $multi[] = $b;
    }

    return [
        'multisport' => stratedge_avg_cote_for_bets($multi),
        'tennis'     => stratedge_avg_cote_for_bets($tennis),
        'fun'        => stratedge_avg_cote_for_bets($fun),
    ];
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
