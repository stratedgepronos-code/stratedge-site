<?php
// STRATEDGE — Lookup logos locaux (/assets/logos/{sport}/{slug}.png)
if (!function_exists('stratedge_local_team_logo')) {

function stratedge_local_team_logo($teamName, $sport = 'football') {
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
