<?php
/**
 * Catégories bets (aligné sur historique.php) + cotes moyennes par univers.
 */
declare(strict_types=1);

/**
 * @return array<string, array{label:string, sections: list<string>}>
 */
function stratedge_bet_categories_config(): array {
    return [
        'multisport' => [
            'label'    => 'Multisports',
            'sections' => ['football_safe', 'football_live', 'hockey_safe', 'hockey_live', 'basket_safe', 'basket_live', 'baseball_safe', 'baseball_live'],
        ],
        'tennis' => [
            'label'    => 'Tennis',
            'sections' => ['tennis_safe_live', 'tennis_fun'],
        ],
        'fun' => [
            'label'    => 'Fun',
            'sections' => ['football_fun', 'hockey_fun', 'basket_fun', 'baseball_fun'],
        ],
    ];
}

function stratedge_bet_section_key(array $b): string {
    $sport = $b['sport'] ?? null;
    if ($sport === null || $sport === '') {
        $sport = (($b['categorie'] ?? 'multi') === 'tennis') ? 'tennis' : 'football';
    }
    $sport = strtolower(trim((string)$sport));
    if (!in_array($sport, ['tennis', 'football', 'basket', 'hockey', 'baseball'], true)) {
        $sport = 'football';
    }
    $type = (string)($b['type'] ?? 'safe');
    if (strpos($type, 'live') !== false) {
        $t = 'live';
    } elseif (strpos($type, 'fun') !== false) {
        $t = 'fun';
    } else {
        $t = 'safe';
    }
    return $sport . '_' . $t;
}

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
 * Cotes moyennes par catégorie, sur tous les bets avec résultat renseigné (comme la page historique).
 *
 * @return array{multisport: ?float, tennis: ?float, fun: ?float}
 */
function stratedge_cotes_moyennes_par_categorie(?PDO $db = null): array {
    if ($db === null) {
        require_once __DIR__ . '/db.php';
        $db = getDB();
    }

    $bets = $db->query("
        SELECT * FROM bets
        WHERE resultat != 'en_cours'
        ORDER BY date_resultat DESC, date_post DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $categoriesConfig = stratedge_bet_categories_config();
    $sectionsBets     = [];
    foreach ($bets as $b) {
        $key = stratedge_bet_section_key($b);
        if (!isset($sectionsBets[$key])) {
            $sectionsBets[$key] = [];
        }
        $sectionsBets[$key][] = $b;
    }
    $sectionsBets['tennis_safe_live'] = array_merge(
        $sectionsBets['tennis_safe'] ?? [],
        $sectionsBets['tennis_live'] ?? []
    );

    $out = ['multisport' => null, 'tennis' => null, 'fun' => null];
    foreach ($categoriesConfig as $catKey => $config) {
        $betsCat = [];
        foreach ($config['sections'] as $sk) {
            if (isset($sectionsBets[$sk])) {
                $betsCat = array_merge($betsCat, $sectionsBets[$sk]);
            }
        }
        $out[$catKey] = stratedge_avg_cote_for_bets($betsCat);
    }

    return $out;
}
