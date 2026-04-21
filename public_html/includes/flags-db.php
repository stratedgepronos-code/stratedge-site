<?php
/**
 * STRATEDGE — Country flags resolver
 * Source: flagcdn.com (free, no auth, no rate limit)
 * Format: flagcdn.com/w320/{iso2}.png (320px wide)
 *
 * Usage:
 *   stratedge_flag('France')      → /assets/flags/fr.png OR https://flagcdn.com/w320/fr.png
 *   stratedge_flag('FR')          → idem
 *   stratedge_flag('Argentina')   → /assets/flags/ar.png
 *   stratedge_flag('États-Unis')  → /assets/flags/us.png
 */

if (!function_exists('stratedge_flag')) {

function stratedge_flag(string $country, int $width = 320): string {
    $name = mb_strtolower(trim($country), 'UTF-8');

    // Mapping pays → ISO2 (codes les plus courants en pronostics sportifs)
    static $db = null;
    if ($db === null) {
        $db = [
            // Europe
            'france' => 'fr', 'fr' => 'fr',
            'allemagne' => 'de', 'germany' => 'de', 'de' => 'de',
            'angleterre' => 'gb-eng', 'england' => 'gb-eng',
            'royaume-uni' => 'gb', 'united kingdom' => 'gb', 'uk' => 'gb', 'gb' => 'gb',
            'ecosse' => 'gb-sct', 'scotland' => 'gb-sct', 'écosse' => 'gb-sct',
            'pays de galles' => 'gb-wls', 'wales' => 'gb-wls',
            'irlande du nord' => 'gb-nir', 'northern ireland' => 'gb-nir',
            'irlande' => 'ie', 'ireland' => 'ie', 'ie' => 'ie',
            'espagne' => 'es', 'spain' => 'es', 'es' => 'es',
            'italie' => 'it', 'italy' => 'it', 'it' => 'it',
            'portugal' => 'pt', 'pt' => 'pt',
            'pays-bas' => 'nl', 'netherlands' => 'nl', 'hollande' => 'nl', 'nl' => 'nl',
            'belgique' => 'be', 'belgium' => 'be', 'be' => 'be',
            'suisse' => 'ch', 'switzerland' => 'ch', 'ch' => 'ch',
            'autriche' => 'at', 'austria' => 'at', 'at' => 'at',
            'danemark' => 'dk', 'denmark' => 'dk', 'dk' => 'dk',
            'norvege' => 'no', 'norway' => 'no', 'no' => 'no', 'norvège' => 'no',
            'suede' => 'se', 'sweden' => 'se', 'se' => 'se', 'suède' => 'se',
            'finlande' => 'fi', 'finland' => 'fi', 'fi' => 'fi',
            'islande' => 'is', 'iceland' => 'is', 'is' => 'is',
            'pologne' => 'pl', 'poland' => 'pl', 'pl' => 'pl',
            'republique tcheque' => 'cz', 'czech republic' => 'cz', 'cz' => 'cz', 'czechia' => 'cz',
            'slovaquie' => 'sk', 'slovakia' => 'sk', 'sk' => 'sk',
            'hongrie' => 'hu', 'hungary' => 'hu', 'hu' => 'hu',
            'roumanie' => 'ro', 'romania' => 'ro', 'ro' => 'ro',
            'bulgarie' => 'bg', 'bulgaria' => 'bg', 'bg' => 'bg',
            'croatie' => 'hr', 'croatia' => 'hr', 'hr' => 'hr',
            'serbie' => 'rs', 'serbia' => 'rs', 'rs' => 'rs',
            'slovenie' => 'si', 'slovenia' => 'si', 'si' => 'si', 'slovénie' => 'si',
            'bosnie' => 'ba', 'bosnia' => 'ba', 'ba' => 'ba', 'bosnia and herzegovina' => 'ba',
            'montenegro' => 'me', 'me' => 'me', 'monténégro' => 'me',
            'albanie' => 'al', 'albania' => 'al', 'al' => 'al',
            'macedoine' => 'mk', 'north macedonia' => 'mk', 'mk' => 'mk', 'macédoine' => 'mk',
            'grece' => 'gr', 'greece' => 'gr', 'gr' => 'gr', 'grèce' => 'gr',
            'chypre' => 'cy', 'cyprus' => 'cy', 'cy' => 'cy',
            'malte' => 'mt', 'malta' => 'mt', 'mt' => 'mt',
            'turquie' => 'tr', 'turkey' => 'tr', 'tr' => 'tr',
            'russie' => 'ru', 'russia' => 'ru', 'ru' => 'ru',
            'ukraine' => 'ua', 'ua' => 'ua',
            'bielorussie' => 'by', 'belarus' => 'by', 'by' => 'by', 'biélorussie' => 'by',
            'estonie' => 'ee', 'estonia' => 'ee', 'ee' => 'ee',
            'lettonie' => 'lv', 'latvia' => 'lv', 'lv' => 'lv',
            'lituanie' => 'lt', 'lithuania' => 'lt', 'lt' => 'lt',
            'luxembourg' => 'lu', 'lu' => 'lu',
            'liechtenstein' => 'li', 'li' => 'li',
            'monaco' => 'mc', 'mc' => 'mc',
            'saint-marin' => 'sm', 'san marino' => 'sm', 'sm' => 'sm',
            'andorre' => 'ad', 'andorra' => 'ad', 'ad' => 'ad',
            'kosovo' => 'xk', 'xk' => 'xk',
            'moldavie' => 'md', 'moldova' => 'md', 'md' => 'md',

            // Amériques
            'etats-unis' => 'us', 'united states' => 'us', 'usa' => 'us', 'us' => 'us', 'états-unis' => 'us',
            'canada' => 'ca', 'ca' => 'ca',
            'mexique' => 'mx', 'mexico' => 'mx', 'mx' => 'mx',
            'bresil' => 'br', 'brazil' => 'br', 'br' => 'br', 'brésil' => 'br',
            'argentine' => 'ar', 'argentina' => 'ar', 'ar' => 'ar',
            'chili' => 'cl', 'chile' => 'cl', 'cl' => 'cl',
            'colombie' => 'co', 'colombia' => 'co', 'co' => 'co',
            'uruguay' => 'uy', 'uy' => 'uy',
            'paraguay' => 'py', 'py' => 'py',
            'bolivie' => 'bo', 'bolivia' => 'bo', 'bo' => 'bo',
            'perou' => 'pe', 'peru' => 'pe', 'pe' => 'pe', 'pérou' => 'pe',
            'equateur' => 'ec', 'ecuador' => 'ec', 'ec' => 'ec', 'équateur' => 'ec',
            'venezuela' => 've', 've' => 've',
            'costa rica' => 'cr', 'cr' => 'cr',
            'panama' => 'pa', 'pa' => 'pa',
            'guatemala' => 'gt', 'gt' => 'gt',
            'honduras' => 'hn', 'hn' => 'hn',
            'salvador' => 'sv', 'el salvador' => 'sv', 'sv' => 'sv',
            'nicaragua' => 'ni', 'ni' => 'ni',
            'cuba' => 'cu', 'cu' => 'cu',
            'jamaique' => 'jm', 'jamaica' => 'jm', 'jm' => 'jm', 'jamaïque' => 'jm',
            'haiti' => 'ht', 'haïti' => 'ht', 'ht' => 'ht',
            'republique dominicaine' => 'do', 'dominican republic' => 'do', 'do' => 'do', 'république dominicaine' => 'do',
            'porto rico' => 'pr', 'puerto rico' => 'pr', 'pr' => 'pr',

            // Asie
            'japon' => 'jp', 'japan' => 'jp', 'jp' => 'jp',
            'chine' => 'cn', 'china' => 'cn', 'cn' => 'cn',
            'coree du sud' => 'kr', 'south korea' => 'kr', 'kr' => 'kr', 'corée du sud' => 'kr', 'corée' => 'kr',
            'coree du nord' => 'kp', 'north korea' => 'kp', 'kp' => 'kp', 'corée du nord' => 'kp',
            'inde' => 'in', 'india' => 'in', 'in' => 'in',
            'pakistan' => 'pk', 'pk' => 'pk',
            'bangladesh' => 'bd', 'bd' => 'bd',
            'sri lanka' => 'lk', 'lk' => 'lk',
            'vietnam' => 'vn', 'vn' => 'vn',
            'thailande' => 'th', 'thailand' => 'th', 'th' => 'th', 'thaïlande' => 'th',
            'indonesie' => 'id', 'indonesia' => 'id', 'id' => 'id', 'indonésie' => 'id',
            'malaisie' => 'my', 'malaysia' => 'my', 'my' => 'my',
            'singapour' => 'sg', 'singapore' => 'sg', 'sg' => 'sg',
            'philippines' => 'ph', 'ph' => 'ph',
            'cambodge' => 'kh', 'cambodia' => 'kh', 'kh' => 'kh',
            'laos' => 'la', 'la' => 'la',
            'myanmar' => 'mm', 'birmanie' => 'mm', 'mm' => 'mm',
            'taiwan' => 'tw', 'tw' => 'tw',
            'hong kong' => 'hk', 'hk' => 'hk',
            'mongolie' => 'mn', 'mongolia' => 'mn', 'mn' => 'mn',
            'kazakhstan' => 'kz', 'kz' => 'kz',
            'ouzbekistan' => 'uz', 'uzbekistan' => 'uz', 'uz' => 'uz', 'ouzbékistan' => 'uz',
            'kirghizistan' => 'kg', 'kyrgyzstan' => 'kg', 'kg' => 'kg',
            'tadjikistan' => 'tj', 'tajikistan' => 'tj', 'tj' => 'tj',
            'turkmenistan' => 'tm', 'turkmenistan' => 'tm', 'tm' => 'tm', 'turkménistan' => 'tm',
            'iran' => 'ir', 'ir' => 'ir',
            'irak' => 'iq', 'iraq' => 'iq', 'iq' => 'iq',
            'syrie' => 'sy', 'syria' => 'sy', 'sy' => 'sy',
            'liban' => 'lb', 'lebanon' => 'lb', 'lb' => 'lb',
            'jordanie' => 'jo', 'jordan' => 'jo', 'jo' => 'jo',
            'israel' => 'il', 'il' => 'il', 'israël' => 'il',
            'palestine' => 'ps', 'ps' => 'ps',
            'arabie saoudite' => 'sa', 'saudi arabia' => 'sa',
            'emirats arabes unis' => 'ae', 'uae' => 'ae', 'ae' => 'ae', 'émirats arabes unis' => 'ae',
            'qatar' => 'qa', 'qa' => 'qa',
            'bahrein' => 'bh', 'bahrain' => 'bh', 'bh' => 'bh', 'bahreïn' => 'bh',
            'koweit' => 'kw', 'kuwait' => 'kw', 'kw' => 'kw', 'koweït' => 'kw',
            'oman' => 'om', 'om' => 'om',
            'yemen' => 'ye', 'yémen' => 'ye', 'ye' => 'ye',
            'afghanistan' => 'af', 'af' => 'af',
            'nepal' => 'np', 'népal' => 'np', 'np' => 'np',
            'bhoutan' => 'bt', 'bhutan' => 'bt', 'bt' => 'bt',
            'maldives' => 'mv', 'mv' => 'mv',

            // Afrique
            'maroc' => 'ma', 'morocco' => 'ma', 'ma' => 'ma',
            'algerie' => 'dz', 'algeria' => 'dz', 'dz' => 'dz', 'algérie' => 'dz',
            'tunisie' => 'tn', 'tunisia' => 'tn', 'tn' => 'tn',
            'egypte' => 'eg', 'egypt' => 'eg', 'eg' => 'eg', 'égypte' => 'eg',
            'libye' => 'ly', 'libya' => 'ly', 'ly' => 'ly',
            'senegal' => 'sn', 'sénégal' => 'sn', 'sn' => 'sn',
            'cote d ivoire' => 'ci', 'ivory coast' => 'ci', 'ci' => 'ci', 'côte d ivoire' => 'ci',
            'mali' => 'ml', 'ml' => 'ml',
            'burkina faso' => 'bf', 'bf' => 'bf',
            'niger' => 'ne', 'ne' => 'ne',
            'guinee' => 'gn', 'guinea' => 'gn', 'gn' => 'gn', 'guinée' => 'gn',
            'ghana' => 'gh', 'gh' => 'gh',
            'togo' => 'tg', 'tg' => 'tg',
            'benin' => 'bj', 'bj' => 'bj', 'bénin' => 'bj',
            'nigeria' => 'ng', 'ng' => 'ng',
            'cameroun' => 'cm', 'cameroon' => 'cm', 'cm' => 'cm',
            'gabon' => 'ga', 'ga' => 'ga',
            'congo' => 'cg', 'cg' => 'cg', 'republique du congo' => 'cg',
            'rdc' => 'cd', 'dr congo' => 'cd', 'cd' => 'cd', 'congo kinshasa' => 'cd',
            'angola' => 'ao', 'ao' => 'ao',
            'mozambique' => 'mz', 'mz' => 'mz',
            'afrique du sud' => 'za', 'south africa' => 'za', 'za' => 'za',
            'zimbabwe' => 'zw', 'zw' => 'zw',
            'zambie' => 'zm', 'zambia' => 'zm', 'zm' => 'zm',
            'botswana' => 'bw', 'bw' => 'bw',
            'namibie' => 'na', 'namibia' => 'na', 'na' => 'na',
            'madagascar' => 'mg', 'mg' => 'mg',
            'maurice' => 'mu', 'mauritius' => 'mu', 'mu' => 'mu',
            'kenya' => 'ke', 'ke' => 'ke',
            'tanzanie' => 'tz', 'tanzania' => 'tz', 'tz' => 'tz',
            'uganda' => 'ug', 'ouganda' => 'ug', 'ug' => 'ug',
            'rwanda' => 'rw', 'rw' => 'rw',
            'ethiopie' => 'et', 'ethiopia' => 'et', 'et' => 'et', 'éthiopie' => 'et',
            'soudan' => 'sd', 'sudan' => 'sd', 'sd' => 'sd',
            'cap vert' => 'cv', 'cape verde' => 'cv', 'cv' => 'cv', 'cap-vert' => 'cv',

            // Océanie
            'australie' => 'au', 'australia' => 'au', 'au' => 'au',
            'nouvelle zelande' => 'nz', 'new zealand' => 'nz', 'nz' => 'nz', 'nouvelle-zélande' => 'nz',
            'fidji' => 'fj', 'fiji' => 'fj', 'fj' => 'fj',
        ];
    }

    // Normalize input for matching
    $normalized = preg_replace("/['\"]/", ' ', $name);
    $normalized = preg_replace('/\s+/', ' ', trim($normalized));

    // Direct match
    $iso2 = $db[$normalized] ?? null;

    // If not found, try without accents
    if (!$iso2 && function_exists('iconv')) {
        $noAccent = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($noAccent !== false) {
            $noAccent = strtolower(trim($noAccent));
            $iso2 = $db[$noAccent] ?? null;
        }
    }

    if (!$iso2) return '';

    // Priorité : fichier local
    $localPath = __DIR__ . '/../assets/flags/' . $iso2 . '.png';
    if (is_file($localPath) && filesize($localPath) > 200) {
        return '/assets/flags/' . $iso2 . '.png';
    }

    // Fallback : URL distante flagcdn
    return "https://flagcdn.com/w{$width}/{$iso2}.png";
}

}
