<?php
/**
 * STRATEDGE — Base locale de logos (scraped depuis TheSportsDB)
 * Logos hébergés sur /assets/logos/{sport}/{slug}.png
 * Matching: normalisation + stopwords + word boundaries
 * Mapping JSON généré par admin/scrape-logos.php
 */

if (!function_exists('stratedge_local_team_logo')) {

function stratedge_local_team_logo($teamName, $sport = 'football') {
    static $mapping = null;
    if ($mapping === null) {
        $f = __DIR__ . '/../assets/logos/mapping.json';
        $mapping = is_file($f) ? (json_decode(@file_get_contents($f), true) ?: []) : [];
    }
    $sportKey = strtolower($sport);
    if (!isset($mapping[$sportKey])) return '';

    $name = strtolower(trim(preg_replace('/[^\w\s\-]/u', ' ', (string)$teamName)));
    $name = preg_replace('/\s+/', ' ', $name);
    $stopwords = ['fc','sc','ac','as','cf','sco','fk','sk','aj','bk','cd','rc','ud','of',
                  'le','la','les','de','du','des','stade','racing','club','football','the','real'];
    $words = array_values(array_filter(explode(' ', $name), fn($w) => $w !== '' && !in_array($w, $stopwords)));
    $nameWordsSet = array_flip($words);
    $baseDir = '/assets/logos/' . $sportKey . '/';

    $best = null; $bestScore = 0;

    foreach ($mapping[$sportKey] as $slug => $originalName) {
        $canonical = strtolower($originalName);
        $canonical = preg_replace('/[^\w\s\-]/u', ' ', $canonical);
        $canonical = preg_replace('/\s+/', ' ', trim($canonical));

        // 1. Match exact
        if ($name === $canonical) return $baseDir . $slug . '.png';
        if (str_replace('-',' ',$slug) === $name) return $baseDir . $slug . '.png';

        // 2. Score mot par mot (combien de mots significatifs du slug présents dans name)
        $slugWords = array_filter(explode('-', $slug), fn($w) => !in_array($w, $stopwords) && strlen($w) >= 3);
        if (empty($slugWords)) continue;
        $found = 0;
        foreach ($slugWords as $sw) {
            if (isset($nameWordsSet[$sw])) $found++;
            elseif (strpos($name, $sw) !== false && strlen($sw) >= 5) $found += 0.5;
        }
        $score = $found / count($slugWords);
        if ($score >= 0.6 && $score > $bestScore) {
            $bestScore = $score;
            $best = $baseDir . $slug . '.png';
        }
    }
    return $best ?: '';
}

}
