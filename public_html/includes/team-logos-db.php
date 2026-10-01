<?php
// STRATEDGE — Lookup logos locaux (/assets/logos/{sport}/{slug}.png)
if (!function_exists('stratedge_local_team_logo')) {

// Alias traductions FR → nom officiel local (pour améliorer le matching)
if (!function_exists('stratedge_normalize_team_alias')) {
function stratedge_normalize_team_alias($name) {
    $aliases = [
        // Allemand
        'fribourg' => 'freiburg', 'munich' => 'munchen',
        'cologne' => 'koln', 'mayence' => 'mainz',
        'francfort' => 'frankfurt', 'brême' => 'bremen', 'breme' => 'bremen',
        'hoffenheim' => 'hoffenheim', 'leverkusen' => 'leverkusen',
        'mönchengladbach' => 'monchengladbach', 'gladbach' => 'monchengladbach',
        'stuttgart' => 'stuttgart', 'bochum' => 'bochum',
        'augsbourg' => 'augsburg',
        // Italien
        'milan ac' => 'milan', 'milan inter' => 'inter',
        'naples' => 'napoli', 'venise' => 'venezia', 'rome' => 'roma',
        'turin' => 'torino', 'florence' => 'fiorentina',
        'bologne' => 'bologna', 'vérone' => 'verona', 'verone' => 'verona',
        // Espagnol
        'séville' => 'sevilla', 'seville' => 'sevilla',
        'majorque' => 'mallorca', 'baléares' => 'mallorca',
        'saragosse' => 'zaragoza',
        // Portugais
        'porto fc' => 'porto',
        // Anglais
        'wolverhampton' => 'wolverhampton', 'wolves' => 'wolverhampton',
        'spurs' => 'tottenham',
        // Français (variantes)
        'psg' => 'paris saint-germain', 'paris sg' => 'paris saint-germain',
        'om' => 'olympique marseille', 'marseille' => 'olympique marseille',
        'ol' => 'olympique lyonnais', 'lyon' => 'olympique lyonnais',
        'asse' => 'saint-etienne', 'sainté' => 'saint-etienne', 'saint-étienne' => 'saint-etienne',
        'lille' => 'lille osc', 'losc' => 'lille osc',
        'rennes' => 'stade rennais',
        'nantes' => 'fc nantes',
        'lens' => 'rc lens',
        'strasbourg' => 'rc strasbourg',
        'reims' => 'stade reims',
        'brest' => 'stade brestois',
        'nice' => 'ogc nice',
        'auxerre' => 'aj auxerre',
        'havre' => 'le havre ac', 'le havre' => 'le havre ac',
        'toulouse' => 'toulouse fc',
        'angers' => 'angers sco',
    ];
    $lower = mb_strtolower(trim($name), 'UTF-8');
    return $aliases[$lower] ?? $name;
}
}

function stratedge_local_team_logo($teamName, $sport = 'football') {
    $teamName = stratedge_normalize_team_alias($teamName);
    static $mapping = null;
    if ($mapping === null) {
        $f = __DIR__ . '/../assets/logos/mapping.json';
        $mapping = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
    }
    $sportKey = strtolower($sport);
    if (!isset($mapping[$sportKey])) return '';

    // Normaliser nom recherché
    $name = strtolower(trim(preg_replace('/[^\w\s\-]/u', ' ', (string)$teamName)));
    $name = preg_replace('/\s+/', ' ', $name);
    $stopwords = ['fc','sc','ac','as','cf','sco','fk','sk','aj','bk','cd','rc','ud','of',
                  'le','la','les','de','du','des','stade','racing','club','football','the'];
    $nameWords = array_values(array_filter(explode(' ', $name), fn($w) => $w !== '' && !in_array($w, $stopwords) && strlen($w) >= 2));
    if (empty($nameWords)) return '';

    $baseDir = '/assets/logos/' . $sportKey . '/';
    $best = null; $bestScore = 0;

    foreach (array_keys($mapping[$sportKey]) as $slug) {
        $slugWords = array_filter(explode('-', $slug), fn($w) => !in_array($w, $stopwords) && strlen($w) >= 3);
        if (empty($slugWords)) continue;

        // Score = proportion mots slug trouvés dans nom
        $found = 0;
        foreach ($slugWords as $sw) {
            foreach ($nameWords as $nw) {
                if ($nw === $sw || (strlen($sw) >= 5 && (str_contains($nw, $sw) || str_contains($sw, $nw)))) {
                    $found++; break;
                }
            }
        }
        $score = $found / count($slugWords);
        if ($score >= 0.7 && $score > $bestScore) {
            $bestScore = $score;
            $best = $baseDir . $slug . '.png';
        }
    }
    return $best ?: '';
}

}
