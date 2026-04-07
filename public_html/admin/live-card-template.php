<?php
// ============================================================
// STRATEDGE — live-card-template.php V13
// V13 : Fun card — promo 10€/sem + grosses cotes, lisibilité paris/confiance, mascotte tennis à jour
// V12 : ajout generateSafeCombiCards() — Safe Combiné (multi-bets Safe)
// V11 : Fun card tennis — mascotte-tennis, thème vert néon quand sport=tennis
// V10 : résolution cartes 1080px (Live + Fun)
// V9 : marqueur version pour vérif FTP + mascotte tennis / VS dégradé
// V8 : fonts embarquées en base64 (fix CORS srcdoc null origin)
// V7 : nouveau design watermark (sans colonne mascotte)
//      mascotte centrée transparente (mix-blend-mode:screen)
//      + generateFunCards() ajouté (même approche template)
// ============================================================

// ────────────────────────────────────────────────────────────
// FONTS EN BASE64 — téléchargées côté serveur PHP (zéro CORS)
// Le serveur PHP peut appeler fonts.gstatic.com sans restriction.
// Cache dans /assets/fonts/cache/ — téléchargement unique.
// ────────────────────────────────────────────────────────────
function getLocalFontsCss() {
    $fallback = "@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Bebas+Neue&family=Rajdhani:wght@400;600;700&display=swap');\n";
    try {
        $cacheDir  = dirname(__DIR__) . '/assets/fonts/cache/';
        $cacheFile = $cacheDir . 'fonts-b64.css';

        if (file_exists($cacheFile) && @filesize($cacheFile) > 50000) {
            $c = @file_get_contents($cacheFile);
            if ($c !== false) return $c;
        }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        // Même si le cache n'est pas inscriptible, on construit les @font-face en mémoire (évite fallback @import en iframe)

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';
        $fonts = [
            ['Orbitron',   700, 'https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap'],
            ['Orbitron',   900, 'https://fonts.googleapis.com/css2?family=Orbitron:wght@900&display=swap'],
            ['Bebas Neue', 400, 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap'],
            ['Rajdhani',   400, 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@400&display=swap'],
            ['Rajdhani',   600, 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@600&display=swap'],
            ['Rajdhani',   700, 'https://fonts.googleapis.com/css2?family=Rajdhani:wght@700&display=swap'],
        ];

        $blocks = [];
        foreach ($fonts as $font) {
            [$family, $weight, $apiUrl] = $font;
            $slug      = strtolower(str_replace(' ', '-', $family)) . '-' . $weight;
            $cacheWoff = $cacheDir . $slug . '.woff2';

            $woff2 = null;
            if (file_exists($cacheWoff) && @filesize($cacheWoff) > 500) {
                $woff2 = @file_get_contents($cacheWoff);
            }
            if (!$woff2 || strlen($woff2) < 500) {
                $css = _curlFetch($apiUrl, $ua);
                if (!$css) continue;
                preg_match_all('/url\((https:\/\/fonts\.gstatic\.com[^)]+\.woff2)\)/', $css, $m);
                if (empty($m[1])) continue;
                $woff2Url = end($m[1]);
                $woff2 = _curlFetch($woff2Url);
                if (!$woff2 || strlen($woff2) < 500) continue;
                @file_put_contents($cacheWoff, $woff2);
            }

            $b64 = base64_encode($woff2);
            $blocks[] = "@font-face{"
                . "font-family:'{$family}';font-weight:{$weight};font-style:normal;font-display:block;"
                . "src:url('data:font/woff2;base64,{$b64}') format('woff2');}";
        }

        if (empty($blocks)) return $fallback;

        $css = implode("\n", $blocks) . "\n";
        if (is_writable($cacheDir)) @file_put_contents($cacheFile, $css);
        return $css;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function _curlFetch($url, $ua = '') {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($ua) $opts[CURLOPT_USERAGENT] = $ua;
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: null;
}


// ────────────────────────────────────────────────────────────
// UTILITAIRE : URL logo → proxy même origine (affichage garanti)
// ────────────────────────────────────────────────────────────
function logoProxyUrl($url) {
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '';
    $host = parse_url($url, PHP_URL_HOST);
    $proxyHosts = ['upload.wikimedia.org', 'commons.wikimedia.org', 'en.wikipedia.org', 'a.espncdn.com', 'media.api-sports.io', 'cdn.nba.com'];
    if (in_array($host, $proxyHosts, true)) {
        $base = 'https://stratedgepronos.fr';
        $u = str_replace(['+', '/'], ['-', '_'], base64_encode($url));
        return $base . '/assets/logo-proxy.php?u=' . $u;
    }
    return $url;
}

// ────────────────────────────────────────────────────────────
// UTILITAIRE : emoji drapeau → img flagcdn.com
// ────────────────────────────────────────────────────────────
function flagImg($emoji) {
    $map = [
        '🇫🇷'=>'fr','🇪🇸'=>'es','🇮🇹'=>'it','🇩🇪'=>'de','🇬🇧'=>'gb','🇺🇸'=>'us',
        '🇧🇷'=>'br','🇦🇷'=>'ar','🇨🇱'=>'cl','🇵🇹'=>'pt','🇳🇱'=>'nl','🇧🇪'=>'be',
        '🇨🇭'=>'ch','🇦🇹'=>'at','🇷🇺'=>'ru','🇺🇦'=>'ua','🇵🇱'=>'pl','🇨🇿'=>'cz',
        '🇷🇸'=>'rs','🇭🇷'=>'hr','🇬🇷'=>'gr','🇹🇷'=>'tr','🇯🇵'=>'jp','🇨🇳'=>'cn',
        '🇰🇷'=>'kr','🇦🇺'=>'au','🇨🇦'=>'ca','🇲🇽'=>'mx','🇨🇴'=>'co','🇪🇨'=>'ec',
        '🇵🇪'=>'pe','🇺🇾'=>'uy','🇵🇾'=>'py','🇻🇪'=>'ve','🇧🇴'=>'bo',
        '🇳🇴'=>'no','🇸🇪'=>'se','🇩🇰'=>'dk','🇫🇮'=>'fi','🇮🇪'=>'ie',
        '🇷🇴'=>'ro','🇧🇬'=>'bg','🇭🇺'=>'hu','🇸🇰'=>'sk','🇸🇮'=>'si',
        '🇱🇹'=>'lt','🇱🇻'=>'lv','🇪🇪'=>'ee','🇬🇪'=>'ge','🇰🇿'=>'kz',
        '🇮🇳'=>'in','🇹🇭'=>'th','🇮🇩'=>'id','🇿🇦'=>'za','🇪🇬'=>'eg',
        '🇲🇦'=>'ma','🇹🇳'=>'tn','🇩🇿'=>'dz','🇳🇬'=>'ng','🇸🇳'=>'sn',
        '🇮🇱'=>'il','🇸🇦'=>'sa','🇦🇪'=>'ae','🇶🇦'=>'qa',
        '🇳🇿'=>'nz','🇸🇬'=>'sg','🇹🇼'=>'tw','🇭🇰'=>'hk',
    ];
    $code = $map[$emoji] ?? '';
    if (!$code) return "<span style='font-size:16px'>{$emoji}</span>";
    return "<img src='https://flagcdn.com/w40/{$code}.png' style='height:14px;border-radius:2px;vertical-align:middle;' alt=''>";
}

// ────────────────────────────────────────────────────────────
// NHL : nom d'équipe → URL logo ESPN (scoreboard 500px)
// ────────────────────────────────────────────────────────────
function nhlLogoUrl($teamName) {
    $name = strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $teamName)));
    $map = [
        'anaheim' => 'ana', 'ducks' => 'ana',
        'boston' => 'bos', 'bruins' => 'bos',
        'buffalo' => 'buf', 'sabres' => 'buf',
        'carolina' => 'car', 'hurricanes' => 'car',
        'columbus' => 'cbj', 'blue jackets' => 'cbj',
        'calgary' => 'cgy', 'flames' => 'cgy',
        'chicago' => 'chi', 'blackhawks' => 'chi',
        'colorado' => 'col', 'avalanche' => 'col',
        'dallas' => 'dal', 'stars' => 'dal',
        'detroit' => 'det', 'red wings' => 'det',
        'edmonton' => 'edm', 'oilers' => 'edm',
        'florida' => 'fla', 'panthers' => 'fla',
        'los angeles' => 'la', 'kings' => 'la', 'la ' => 'la',
        'minnesota' => 'min', 'wild' => 'min',
        'montreal' => 'mtl', 'canadiens' => 'mtl', 'habitans' => 'mtl',
        'new jersey' => 'nj', 'devils' => 'nj',
        'nashville' => 'nsh', 'predators' => 'nsh',
        'new york islanders' => 'nyi', 'islanders' => 'nyi',
        'new york rangers' => 'nyr', 'rangers' => 'nyr',
        'ottawa' => 'ott', 'senators' => 'ott',
        'philadelphia' => 'phi', 'flyers' => 'phi',
        'pittsburgh' => 'pit', 'penguins' => 'pit',
        'seattle' => 'sea', 'kraken' => 'sea',
        'san jose' => 'sjs', 'sharks' => 'sjs',
        'st louis' => 'stl', 'blues' => 'stl',
        'tampa bay' => 'tb', 'lightning' => 'tb',
        'toronto' => 'tor', 'maple leafs' => 'tor', 'leafs' => 'tor',
        'utah' => 'utah', 'utah hockey' => 'utah',
        'vegas' => 'vgk', 'golden knights' => 'vgk',
        'washington' => 'wsh', 'capitals' => 'wsh', 'caps' => 'wsh',
        'winnipeg' => 'wpg', 'jets' => 'wpg',
    ];
    foreach ($map as $key => $abbrev) {
        if (strpos($name, $key) !== false) return 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/nhl/500/scoreboard/' . $abbrev . '.png';
    }
    return '';
}

// ────────────────────────────────────────────────────────────
// MLB : nom d'équipe → URL logo ESPN (scoreboard 500px)
// ────────────────────────────────────────────────────────────
function mlbLogoUrl($teamName) {
    $name = strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $teamName)));
    $map = [
        'arizona' => 'ari', 'diamondbacks' => 'ari', 'dbacks' => 'ari',
        'atlanta' => 'atl', 'braves' => 'atl',
        'baltimore' => 'bal', 'orioles' => 'bal',
        'boston' => 'bos', 'red sox' => 'bos',
        'chicago cubs' => 'chc', 'cubs' => 'chc',
        'chicago white sox' => 'chw', 'white sox' => 'chw',
        'cincinnati' => 'cin', 'reds' => 'cin',
        'cleveland' => 'cle', 'guardians' => 'cle',
        'colorado' => 'col', 'rockies' => 'col',
        'detroit' => 'det', 'tigers' => 'det',
        'houston' => 'hou', 'astros' => 'hou',
        'kansas city' => 'kc', 'royals' => 'kc',
        'los angeles angels' => 'laa', 'angels' => 'laa',
        'los angeles dodgers' => 'lad', 'dodgers' => 'lad',
        'miami' => 'mia', 'marlins' => 'mia',
        'milwaukee' => 'mil', 'brewers' => 'mil',
        'minnesota' => 'min', 'twins' => 'min',
        'new york mets' => 'nym', 'mets' => 'nym',
        'new york yankees' => 'nyy', 'yankees' => 'nyy',
        'oakland' => 'oak', 'athletics' => 'oak',
        'philadelphia' => 'phi', 'phillies' => 'phi',
        'pittsburgh' => 'pit', 'pirates' => 'pit',
        'san diego' => 'sd', 'padres' => 'sd',
        'san francisco' => 'sf', 'giants' => 'sf',
        'seattle' => 'sea', 'mariners' => 'sea',
        'st louis' => 'stl', 'cardinals' => 'stl',
        'tampa bay' => 'tb', 'rays' => 'tb',
        'texas' => 'tex', 'rangers' => 'tex',
        'toronto' => 'tor', 'blue jays' => 'tor',
        'washington' => 'wsh', 'nationals' => 'wsh',
    ];
    foreach ($map as $key => $abbrev) {
        if (strpos($name, $key) !== false) return 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/mlb/500/scoreboard/' . $abbrev . '.png';
    }
    return '';
}

// ────────────────────────────────────────────────────────────
// SPORT CONFIG — couleurs, mascotte, badge
// ────────────────────────────────────────────────────────────
function sportConfig($sport) {
    $sport = strtolower(trim($sport));
    if ($sport === 'tennis') {
        return [
            'emoji'        => '🎾',
            'label'        => 'Tennis',
            'pack'         => 'Tennis Pro',
            'mascotte_url'   => 'https://stratedgepronos.fr/assets/images/mascotte-tennis.png',
            // Tennis : mascotte-tennis.png, fond transparent (opacity seule)
            'mascotte_style' => "opacity:0.55; background:none !important; height:100%; width:auto; object-fit:contain;",
            'mascotte_locked'=> "opacity:0.30; background:none !important; height:100%; width:auto; object-fit:contain;",
            // Badge vert neon
            'badge_bg'     => 'rgba(57,255,20,0.12)',
            'badge_border' => 'rgba(57,255,20,0.6)',
            'badge_color'  => '#39ff14',
            'badge_shadow' => '0 0 10px rgba(57,255,20,0.3)',
            'badge_tshadow'=> '0 0 8px rgba(57,255,20,0.9)',
            // Heure
            'time_color'   => '#39ff14',
            'time_shadow'  => '0 0 30px rgba(57,255,20,0.5)',
            // Glow extérieur de la card
            'glow_gradient'=> 'linear-gradient(135deg,#39ff14,#00e5ff,#39ff14)',
            // Barre confiance (réf. visuelle : cyan → bleu)
            'conf_gradient'=> 'linear-gradient(90deg,#00FFFF,#007BFF)',
            'conf_color'   => '#39ff14',
            'conf_shadow'  => '0 0 10px rgba(57,255,20,0.6)',
            // Promo
            'promo_price_color' => '#39ff14',
            // Footer
            'footer_gradient'   => 'linear-gradient(90deg,#39ff14 0%,#00e5ff 100%)',
        ];
    }
    // Tous les autres sports → rose/magenta
    $map = [
        'football' => ['⚽','Football','Football Pro'],
        'basket'   => ['🏀','Basket','Basket Pro'],
        'hockey'   => ['🏒','Hockey','Hockey Pro'],
        'rugby'    => ['🏉','Rugby','Rugby Pro'],
        'baseball' => ['⚾','Baseball','Baseball Pro'],
    ];
    $s = $map[$sport] ?? ['⚽','Football','Football Pro'];
    $mascotteFile = ($sport === 'baseball') ? 'mascotte-mlb.png' : 'mascotte.png';
    return [
        'emoji'        => $s[0],
        'label'        => $s[1],
        'pack'         => $s[2],
        'mascotte_url'   => 'https://stratedgepronos.fr/assets/images/' . $mascotteFile,
        // Autres sports : même rendu que tennis — pleine hauteur, transparent derrière le texte
        'mascotte_style' => "opacity:0.45; background:none !important; height:100%; width:auto; object-fit:contain;",
        'mascotte_locked'=> "opacity:0.25; background:none !important; height:100%; width:auto; object-fit:contain;",
        'badge_bg'     => 'rgba(255,45,122,0.12)',
        'badge_border' => 'rgba(255,45,122,0.6)',
        'badge_color'  => '#ff2d7a',
        'badge_shadow' => '0 0 10px rgba(255,45,122,0.3)',
        'badge_tshadow'=> '0 0 8px rgba(255,45,122,0.8)',
        'time_color'   => '#ff2d7a',
        'time_shadow'  => '0 0 30px rgba(255,45,122,0.5)',
        'glow_gradient'=> 'linear-gradient(135deg,#ff2d7a,#c850c0,#ff2d7a)',
        'conf_gradient'=> 'linear-gradient(90deg,#ff2d7a,#c850c0,#ff8c42)',
        'conf_color'   => '#ff2d7a',
        'conf_shadow'  => '0 0 10px rgba(255,45,122,0.4)',
        'promo_price_color' => '#00e5ff',
        'footer_gradient'   => 'linear-gradient(90deg,#ff2d7a 0%,#c850c0 50%,#00e5ff 100%)',
    ];
}

// ════════════════════════════════════════════════════════════
//  LIVE BET — Template PHP
//  Même design que les cartes finales : sans colonne mascotte,
//  mascotte en watermark centré transparent
// ════════════════════════════════════════════════════════════
function generateLiveCards($d) {
    $sport  = strtolower(trim($d['sport'] ?? 'football'));
    $sc     = sportConfig($sport);
    $is_tennis = ($sport === 'tennis');
    $date   = htmlspecialchars($d['date_fr']    ?? '', ENT_QUOTES, 'UTF-8', false);
    $time   = htmlspecialchars($d['time_fr']    ?? '00:00', ENT_QUOTES, 'UTF-8', false);
    $p1     = htmlspecialchars($d['player1']    ?? 'Joueur 1', ENT_QUOTES, 'UTF-8', false);
    $p2     = htmlspecialchars($d['player2']    ?? 'Joueur 2', ENT_QUOTES, 'UTF-8', false);
    $comp   = htmlspecialchars($d['competition']?? '', ENT_QUOTES, 'UTF-8', false);
    $prono  = htmlspecialchars($d['prono']      ?? '', ENT_QUOTES, 'UTF-8', false);
    $cote   = htmlspecialchars($d['cote']       ?? '1.50', ENT_QUOTES, 'UTF-8', false);
    $conf   = intval($d['confidence'] ?? 70);
    $flag1  = flagImg($d['flag1'] ?? '');
    $flag2  = flagImg($d['flag2'] ?? '');
    $logo1_url = trim($d['team1_logo'] ?? '');
    $logo2_url = trim($d['team2_logo'] ?? '');
    $is_team_sport = in_array($sport, ['football', 'basket', 'hockey', 'baseball']);
    if ($is_team_sport) {
        if ($logo1_url === '' || !filter_var($logo1_url, FILTER_VALIDATE_URL)) {
            $logo1_url = function_exists('stratedge_fetch_team_logo_url') ? stratedge_fetch_team_logo_url($d['player1'] ?? '') : '';
        }
        if ($logo2_url === '' || !filter_var($logo2_url, FILTER_VALIDATE_URL)) {
            $logo2_url = function_exists('stratedge_fetch_team_logo_url') ? stratedge_fetch_team_logo_url($d['player2'] ?? '') : '';
        }
        if ($sport === 'hockey') {
            if ($logo1_url === '' || !filter_var($logo1_url, FILTER_VALIDATE_URL)) $logo1_url = nhlLogoUrl($d['player1'] ?? '');
            if ($logo2_url === '' || !filter_var($logo2_url, FILTER_VALIDATE_URL)) $logo2_url = nhlLogoUrl($d['player2'] ?? '');
        }
        if ($sport === 'baseball') {
            if ($logo1_url === '' || !filter_var($logo1_url, FILTER_VALIDATE_URL)) $logo1_url = mlbLogoUrl($d['player1'] ?? '');
            if ($logo2_url === '' || !filter_var($logo2_url, FILTER_VALIDATE_URL)) $logo2_url = mlbLogoUrl($d['player2'] ?? '');
        }
    }
    if ($is_team_sport && $logo1_url !== '' && filter_var($logo1_url, FILTER_VALIDATE_URL)) {
        $team1_display = '<img src="' . htmlspecialchars(logoProxyUrl($logo1_url), ENT_QUOTES, 'UTF-8', false) . '" class="team-logo" alt="">';
    } else {
        $team1_display = $flag1;
    }
    if ($is_team_sport && $logo2_url !== '' && filter_var($logo2_url, FILTER_VALIDATE_URL)) {
        $team2_display = '<img src="' . htmlspecialchars(logoProxyUrl($logo2_url), ENT_QUOTES, 'UTF-8', false) . '" class="team-logo" alt="">';
    } else {
        $team2_display = $flag2;
    }
    $logo   = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';
    $pronoJoueur = (int)($d['prono_joueur'] ?? 1);
    $p1_class = ($pronoJoueur === 1) ? 'player main' : 'player opponent';
    $p2_class = ($pronoJoueur === 2) ? 'player main' : 'player opponent';

    $embeddedFonts = getLocalFontsCss();
    $css = $embeddedFonts . <<<CSS

* { margin:0; padding:0; box-sizing:border-box; }
html { background:#0a0a0a !important; width:1080px; min-width:1080px; max-width:1080px; overflow-x:hidden; }
body { background:#0a0a0a !important; margin:0; padding:0; width:1080px; min-width:1080px; max-width:1080px; font-family:'Orbitron',sans-serif; }

.card-wrapper { position:relative; width:1080px; min-width:1080px; max-width:1080px; background:#0a0a0a; }

/* Glow extérieur — z-index:-1 pour ne jamais créer de voile */
.border-glow {
  position:absolute; inset:-2px; border-radius:20px;
  background:{$sc['glow_gradient']};
  background-size:300% 300%; animation:gradientShift 4s ease infinite;
  z-index:-1; filter:blur(6px); opacity:0.8;
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.card {
  position:relative; z-index:1; width:1080px; background:#0d0d0f;
  border-radius:16px; overflow:hidden; display:flex; flex-direction:column;
  border:1px solid rgba(255,255,255,0.05); isolation:isolate;
}

/* MASCOTTE WATERMARK — centrée, derrière le contenu */
.mascotte-watermark {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index:0; pointer-events:none;
}
.mascotte-watermark img {
  height:100%; width:auto; object-fit:contain;
  {$sc['mascotte_style']}
}

/* Contenu par-dessus le watermark */
.card-body { position:relative; z-index:2; padding:28px 36px 22px; display:flex; flex-direction:column; gap:14px; }

.card-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:44px; object-fit:contain; }
.sport-badge {
  display:flex; align-items:center; gap:6px;
  background:{$sc['badge_bg']}; border:1.5px solid {$sc['badge_border']};
  border-radius:20px; padding:4px 16px;
  font-family:'Orbitron',sans-serif; font-size:17px; font-weight:700;
  color:{$sc['badge_color']}; letter-spacing:1px; text-transform:uppercase;
  box-shadow:{$sc['badge_shadow']}; text-shadow:{$sc['badge_tshadow']};
}

.datetime-block { text-align:center; padding:3px 0; }
.datetime-day { font-family:'Orbitron',sans-serif; font-size:21px; font-weight:600; color:rgba(255,255,255,0.55); text-transform:uppercase; letter-spacing:3px; margin-bottom:3px; }
.datetime-time { font-family:'Orbitron',sans-serif; font-size:54px; font-weight:900; letter-spacing:4px; line-height:1; color:{$sc['time_color']}; text-shadow:{$sc['time_shadow']}; }

.match-block { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:14px; padding:14px 22px; position:relative; }
.match-left-bar { position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(to bottom,#ff2d7a,#00e5ff); border-radius:4px 0 0 4px; }
.live-badge { display:flex; align-items:center; gap:6px; justify-content:center; margin-bottom:8px; font-family:'Orbitron',sans-serif; font-size:14px; font-weight:700; color:#ff2d7a; letter-spacing:2px; text-transform:uppercase; }
.live-dot { width:10px; height:10px; border-radius:50%; background:#ff2d7a; box-shadow:0 0 8px #ff2d7a; animation:blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }
.match-players { display:flex; align-items:center; justify-content:center; gap:20px; }
.player-info { display:flex; align-items:center; gap:8px; }
.player { font-family:'Bebas Neue',cursive; font-size:38px; letter-spacing:1px; line-height:1; }
.player.main { color:#fff; }
.player.opponent { color:rgba(255,255,255,0.7); }
.vs-badge { font-family:'Orbitron',sans-serif; font-size:19px; font-weight:900; color:#ff2d7a; }
.match-comp { font-family:'Orbitron',sans-serif; font-size:16px; color:rgba(255,255,255,0.5); text-align:center; margin-top:6px; letter-spacing:1px; }

.prono-block { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:14px; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
.prono-left { flex:1; }
.prono-label { font-size:16px; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:2px; margin-bottom:5px; font-weight:600; }
.prono-text {
  font-family:'Orbitron',sans-serif; font-weight:700; font-size:21px;
  color: #ff2d7a;
}
.cote-block { text-align:center; flex-shrink:0; }
.cote-label { font-size:16px; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:2px; margin-bottom:6px; font-weight:600; }
.cote-pill { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:#FF2D78; border-radius:14px; padding:12px 30px; min-width:120px; box-shadow:0 4px 20px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12); }
.cote-pill-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.13); border-radius:14px 14px 0 0; }
.cote-value { font-family:'Orbitron',sans-serif; font-size:40px; font-weight:900; color:#fff; position:relative; z-index:1; }

.confidence-section { display:flex; align-items:center; gap:14px; }
.confidence-label { font-size:17px; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:1.5px; font-weight:600; white-space:nowrap; flex-shrink:0; }
.confidence-bar-bg { flex:1; height:10px; background:rgba(255,255,255,0.06); border-radius:10px; overflow:hidden; }
.confidence-bar-fill { height:100%; width:{$conf}%; background:{$sc['conf_gradient']}; border-radius:10px; animation:barPulse 2s ease-in-out infinite; }
@keyframes barPulse { 0%,100%{opacity:1} 50%{opacity:0.8} }
.confidence-score { font-family:'Orbitron',sans-serif; font-size:20px; font-weight:700; color:{$sc['conf_color']}; flex-shrink:0; text-shadow:{$sc['conf_shadow']}; }

.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18); border-radius:14px; padding:14px 18px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:4px 0 0 4px; }
.promo-text-block { flex:1; padding-left:10px; display:flex; flex-direction:column; gap:3px; }
.promo-eyebrow { font-size:11px; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; font-family:'Orbitron',sans-serif; }
.promo-main { font-family:'Bebas Neue',cursive; font-size:20px; letter-spacing:0.8px; color:#fff; }
.promo-main-hl { color:{$sc['promo_price_color']}; }
.promo-sub { font-size:14px; color:rgba(255,255,255,0.55); font-weight:500; font-family:'Orbitron',sans-serif; }
.promo-sub span { color:{$sc['promo_price_color']}; font-weight:700; }
.promo-cta { display:inline-flex; align-items:center; gap:5px; background:linear-gradient(135deg,#39ff14,#00c896); color:#000; font-family:'Orbitron',sans-serif; font-size:14px; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:10px 16px; border-radius:10px; white-space:nowrap; box-shadow:0 0 14px rgba(57,255,20,0.4); }

/* Locked */
.locked-zone { text-align:center; padding:14px 0; }
.locked-padlock { font-size:62px; line-height:1; }
.locked-reserved { font-family:'Orbitron',sans-serif; font-size:16px; color:#ff2d7a; opacity:0.7; letter-spacing:2px; margin:8px 0; }
.locked-cta-btn { background:linear-gradient(135deg,#FF2D78,#d6245f); color:white; font-family:'Orbitron',sans-serif; font-size:16px; font-weight:700; padding:12px 32px; border-radius:12px; display:inline-block; letter-spacing:1px; }
.locked-cote-center { text-align:center; margin:6px 0; }

.card-footer-gradient { height:4px; background:{$sc['footer_gradient']}; position:relative; z-index:2; }
CSS;
    // Overrides Tennis Live uniquement (réf. visuelle : fond #0A0A0A, cote rose→violet, offre #1A361A)
    if ($is_tennis) {
        $css .= <<<TENNIS

/* Tennis Live — couleurs et offre alignées sur la maquette */
.card-wrapper.tennis .card { background:#0d0d0f; border-color:rgba(57,255,20,0.2); }
.card-wrapper.tennis .mascotte-watermark { background:transparent !important; z-index:0; pointer-events:none; display:flex !important; align-items:center; justify-content:center; }
.card-wrapper.tennis .mascotte-watermark img { background:none !important; box-shadow:none !important; max-height:100%; width:auto; object-fit:contain; }
.card-wrapper.tennis .match-left-bar { background:linear-gradient(to bottom,#E7337B,#7D41E7); }
.card-wrapper.tennis .match-block { background:transparent !important; border-color:rgba(255,255,255,0.06); }
.card-wrapper.tennis .live-badge { color:#ff2d7a !important; }
.card-wrapper.tennis .live-dot { background:#ff2d7a !important; box-shadow:0 0 6px #ff2d7a; }
.card-wrapper.tennis .vs-badge { background:none !important; background-image:none !important; box-shadow:none !important; border:none !important; padding:0 !important; margin:0 !important; }
.card-wrapper.tennis .vs-badge svg { display:block; }
.card-wrapper.tennis .player { font-family:'Orbitron',sans-serif !important; font-weight:700; font-size:36px; letter-spacing:1px; }
.card-wrapper.tennis .player.opponent { color:rgba(255,255,255,0.7); }
.card-wrapper.tennis .datetime-day { font-size:21px; color:rgba(255,255,255,0.55); }
.card-wrapper.tennis .datetime-time { font-size:58px; }
.card-wrapper.tennis .match-comp { font-size:16px; color:rgba(255,255,255,0.5); }
.card-wrapper.tennis .prono-label { font-size:16px; color:rgba(255,255,255,0.5); }
.card-wrapper.tennis .prono-text { color:#fff; font-size:24px; }
.card-wrapper.tennis .cote-label { font-size:16px; color:rgba(255,255,255,0.5); }
.card-wrapper.tennis .cote-value { font-size:44px; background:transparent !important; box-shadow:none !important; }
.card-wrapper.tennis .confidence-label { font-size:17px; color:rgba(255,255,255,0.5); }
.card-wrapper.tennis .confidence-score { font-size:24px; }
.card-wrapper.tennis .promo-main { font-family:'Orbitron',sans-serif !important; font-weight:700; font-size:24px; letter-spacing:0.8px; }
.card-wrapper.tennis .promo-sub { font-family:'Orbitron',sans-serif !important; font-size:14px; font-weight:500; color:rgba(255,255,255,0.55); }
.card-wrapper.tennis .promo-eyebrow { color:#39ff14; font-size:13px; }
.card-wrapper.tennis .prono-block { background:linear-gradient(90deg,rgba(57,255,20,0.14),rgba(144,255,128,0.06)) !important; border-color:rgba(57,255,20,0.2); }
.card-wrapper.tennis .cote-pill { background:#FF2D78; box-shadow:0 4px 16px rgba(255,45,122,0.5); }
.card-wrapper.tennis .cote-pill-shine { display:none !important; }
.card-wrapper.tennis .promo-banner { background:#1A361A; border:1px solid rgba(57,255,20,0.35); }
.card-wrapper.tennis .promo-cta { background:linear-gradient(135deg,#39ff14,#00c896); color:#000; box-shadow:0 0 14px rgba(57,255,20,0.5); font-size:16px; }
.card-wrapper.tennis .locked-reserved { color:#39ff14; font-size:18px; }
.card-wrapper.tennis .locked-cta-btn { background:linear-gradient(135deg,#39ff14,#00c896); color:#000 !important; font-family:'Orbitron',sans-serif !important; font-size:16px; font-weight:700; }
TENNIS;
    }
    if ($is_team_sport) {
        $css .= <<<TEAMSPORT

/* Team sport (foot, NBA, hockey) — logos + couleurs solides */
.card-wrapper.team-sport .team-logo { height:36px; width:auto; max-width:50px; object-fit:contain; vertical-align:middle; margin-right:4px; }
.card-wrapper.team-sport .prono-block { background:linear-gradient(135deg,rgba(255,45,122,0.12),rgba(0,229,255,0.06)) !important; border:1px solid rgba(255,45,122,0.25) !important; }
.card-wrapper.team-sport .prono-text {
  color:#ffffff;
  font-family:'Orbitron',sans-serif; font-weight:900; font-size:22px;
  text-shadow:0 0 20px rgba(255,45,122,0.6),0 2px 4px rgba(0,0,0,0.5);
  letter-spacing:0.5px;
}
.card-wrapper.team-sport .vs-badge {
  color:#ff2d7a;
  font-family:'Orbitron',sans-serif; font-size:18px; font-weight:900;
  text-shadow:0 0 12px rgba(255,45,122,0.5);
}
TEAMSPORT;
    }

    $wrapper_class = $is_tennis ? 'card-wrapper tennis' : 'card-wrapper';
    if ($is_team_sport) {
        $wrapper_class .= ' team-sport';
    }
    if ($is_tennis) {
        $promo_eyebrow = 'OFFRE EXCLUSIVE';
        $promo_main   = 'PACK TENNIS PRO - <span class="promo-main-hl">15€/semaine</span>';
        $promo_sub    = 'Pronostics experts - Analyses live - Accès illimité';
        $promo_cta_text = "JE M'ABONNE →";
    } elseif ($is_team_sport) {
        $promo_eyebrow = 'PACK DAILY';
        $promo_main   = 'Souscris par SMS à <span class="promo-main-hl">4,50€</span>';
        $promo_sub    = 'Pronostics chaque jour par SMS';
        $promo_cta_text = "Je m'abonne";
    } else {
        $promo_eyebrow = $sc['emoji'] . ' Offre exclusive';
        $promo_main   = "Pack <span class='promo-main-hl'>{$sc['pack']}</span> — Accès illimité";
        $promo_sub    = "Pronostics experts · Analyses live · <span>Dès 9.99€/mois</span>";
        $promo_cta_text = "🚀 Je m'abonne";
    }
    $promo_extra  = $is_tennis ? '' : '';
    $cote_pill_inner = $is_tennis
        ? "<div class='cote-value'>{$cote}</div>"
        : "<div class='cote-pill-shine'></div><div class='cote-value'>{$cote}</div>";

    // Tennis : VS = SVG uniquement (dégradé sur le texte, zéro background)
    $vs_html = $is_tennis
        ? "<span class='vs-badge' style='display:inline-block;width:60px;height:36px;background:none!important;border:none;padding:0;margin:0;line-height:0;vertical-align:middle;'><svg xmlns='http://www.w3.org/2000/svg' width='60' height='36' viewBox='0 0 42 26' style='display:block;'><defs><linearGradient id='vsg' x1='0%' y1='0%' x2='100%' y2='0%'><stop offset='0%' style='stop-color:#E7337B'/><stop offset='100%' style='stop-color:#00e5ff'/></linearGradient></defs><text x='21' y='21' text-anchor='middle' fill='url(#vsg)' font-family='Orbitron,sans-serif' font-size='20' font-weight='900'>VS</text></svg></span>"
        : "<div class='vs-badge'>VS</div>";

    $locked_cta_text = $is_tennis ? '🔓 Souscris au pack Tennis pour recevoir le bet' : '🔓 Reçois le bet sur stratedgepronos.fr';
    $font_link = "<link href=\"https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Bebas+Neue&family=Rajdhani:wght@400;600;700&display=swap\" rel=\"stylesheet\">";

    // CARD NORMALE
    $html_normal = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>
{$font_link}
<style>{$css}</style>
</head>
<body>
<!-- StratEdge card template v11 2026-03 (fonts+VS sans fond+Live Bet rouge) -->
<div class='{$wrapper_class}'>
  <div class='border-glow'></div>
  <div class='card'>
    <div class='mascotte-watermark'>
      <img src='{$sc['mascotte_url']}' alt='' style='{$sc['mascotte_style']}'>
    </div>
    <div class='card-body'>
      <div class='card-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div class='sport-badge'>{$sc['emoji']} {$sc['label']}</div>
      </div>
      <div class='datetime-block'>
        <div class='datetime-day'>{$date}</div>
        <div class='datetime-time'>{$time}</div>
      </div>
      <div class='match-block'>
        <div class='match-left-bar'></div>
        <div class='live-badge'><div class='live-dot'></div> Live Bet</div>
        <div class='match-players'>
          <div class='player-info'>{$team1_display}<div class='player main'>{$p1}</div></div>
          {$vs_html}
          <div class='player-info'>{$team2_display}<div class='player opponent'>{$p2}</div></div>
        </div>
        <div class='match-comp'>{$comp}</div>
      </div>
      <div class='prono-block'>
        <div class='prono-left'>
          <div class='prono-label'>Pronostic</div>
          <div class='prono-text'>{$prono}</div>
        </div>
        <div class='cote-block'>
          <div class='cote-label'>Cote</div>
          <div class='cote-pill'>{$cote_pill_inner}</div>
        </div>
      </div>
      <div class='confidence-section'>
        <div class='confidence-label'>Confiance</div>
        <div class='confidence-bar-bg'><div class='confidence-bar-fill'></div></div>
        <div class='confidence-score'>{$conf}/100</div>
      </div>
      <div class='promo-banner'>
        <div class='promo-left-bar'></div>
        <div class='promo-text-block'>
          <div class='promo-eyebrow'>{$promo_eyebrow}</div>
          <div class='promo-main'>{$promo_main}</div>
          <div class='promo-sub'>{$promo_sub}</div>
          {$promo_extra}
        </div>
        <div class='promo-cta'>{$promo_cta_text}</div>
      </div>
    </div>
    <div class='card-footer-gradient'></div>
  </div>
</div>
</body></html>
HTML;

    // CARD LOCKED
    $html_locked = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>
{$font_link}
<style>{$css}</style>
</head>
<body>
<div class='{$wrapper_class}'>
  <div class='border-glow'></div>
  <div class='card'>
    <div class='mascotte-watermark'>
      <img src='{$sc['mascotte_url']}' alt='' style='{$sc['mascotte_locked']}'>
    </div>
    <div class='card-body'>
      <div class='card-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div class='sport-badge'>{$sc['emoji']} {$sc['label']}</div>
      </div>
      <div class='datetime-block'>
        <div class='datetime-day'>{$date}</div>
        <div class='datetime-time'>{$time}</div>
      </div>
      <div class='match-block'>
        <div class='match-left-bar'></div>
        <div class='live-badge'><div class='live-dot'></div> Live Bet</div>
        <div class='match-players'>
          <div class='player-info'>{$team1_display}<div class='{$p1_class}'>{$p1}</div></div>
          {$vs_html}
          <div class='player-info'>{$team2_display}<div class='{$p2_class}'>{$p2}</div></div>
        </div>
        <div class='match-comp'>{$comp}</div>
      </div>
      <div class='locked-zone'>
        <div class='locked-padlock'>🔒</div>
        <div class='locked-reserved'>CONTENU RÉSERVÉ</div>
        <div class='locked-cta-btn'>{$locked_cta_text}</div>
      </div>
      <div class='locked-cote-center'>
        <div class='cote-label'>Cote</div>
        <div class='cote-pill'>{$cote_pill_inner}</div>
      </div>
      <div class='confidence-section'>
        <div class='confidence-label'>Confiance</div>
        <div class='confidence-bar-bg'><div class='confidence-bar-fill'></div></div>
        <div class='confidence-score'>{$conf}/100</div>
      </div>
      <div class='promo-banner'>
        <div class='promo-left-bar'></div>
        <div class='promo-text-block'>
          <div class='promo-eyebrow'>{$promo_eyebrow}</div>
          <div class='promo-main'>{$promo_main}</div>
          <div class='promo-sub'>{$promo_sub}</div>
          {$promo_extra}
        </div>
        <div class='promo-cta'>{$promo_cta_text}</div>
      </div>
    </div>
    <div class='card-footer-gradient'></div>
  </div>
</div>
</body></html>
HTML;

    return ['html_normal' => $html_normal, 'html_locked' => $html_locked];
}


// ════════════════════════════════════════════════════════════
//  FUN BET — Template PHP
//  $d = [
//    'sport'       => 'football',
//    'date_fr'     => 'Mercredi 26 Février 2026',
//    'time_fr'     => '20:45',          ← heure du 1er match
//    'bets'        => [                 ← tableau de paris
//      ['match'=>'...','flag1'=>'🇧🇪','flag2'=>'🇭🇷','prono'=>'...','cote'=>'2.95'],
//      ...
//    ],
//    'cote_totale' => '7.35',
//    'confidence'  => 68,
//  ]
// ════════════════════════════════════════════════════════════
function generateFunCards($d) {
    $sport       = strtolower(trim($d['sport'] ?? 'football'));
    $isTennis    = ($sport === 'tennis');
    $isBaseball  = ($sport === 'baseball');
    if ($isTennis) {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-tennis.png';
    } elseif ($isBaseball) {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-mlb.png';
    } else {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-fun.png';
    }
    $logo        = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';

    $date    = htmlspecialchars($d['date_fr']    ?? '', ENT_QUOTES, 'UTF-8', false);
    $time    = htmlspecialchars($d['time_fr']    ?? '00:00', ENT_QUOTES, 'UTF-8', false);
    $coteTot = htmlspecialchars($d['cote_totale']?? '1.00', ENT_QUOTES, 'UTF-8', false);
    $conf    = intval($d['confidence'] ?? 65);
    $bets    = $d['bets'] ?? [];
    $nbBets  = count($bets);

    // Couleurs : tennis = vert néon, sinon rose/bleu
    if ($isTennis) {
        $barColors = [
            'linear-gradient(to bottom,#39ff14,#00d46a)',
            'linear-gradient(to bottom,#00d46a,#00c896)',
            'linear-gradient(to bottom,#00c896,#00e5ff)',
        ];
    } else {
        $barColors = [
            'linear-gradient(to bottom,#ff2d7a,#c850c0)',
            'linear-gradient(to bottom,#c850c0,#4158d0)',
            'linear-gradient(to bottom,#4158d0,#00e5ff)',
        ];
    }

    $embeddedFonts = getLocalFontsCss();
    $css = $embeddedFonts . "\n/* Fallback Google Fonts si embarquées absentes (iframe/srcdoc) */\n@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Bebas+Neue&display=swap');\n" . <<<CSS

* { margin:0; padding:0; box-sizing:border-box; }
html { background:#0a0a0a !important; width:1080px; min-width:1080px; max-width:1080px; overflow-x:hidden; }
body { background:#0a0a0a !important; margin:0; padding:0; width:1080px; min-width:1080px; max-width:1080px; font-family:'Orbitron',sans-serif; }

.card-wrapper { position:relative; width:1080px; max-width:1080px; min-width:1080px; background:#0a0a0a; }
.border-glow {
  position:absolute; inset:-2px; border-radius:24px;
  background:linear-gradient(135deg,#ff2d7a,#c850c0,#ff2d7a);
  background-size:300% 300%; animation:gradientShift 4s ease infinite;
  z-index:-1; filter:blur(10px); opacity:0.75;
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.card {
  position:relative; z-index:1; width:1080px; max-width:1080px; background:#0e0b12;
  border-radius:20px; overflow:hidden; display:flex; flex-direction:column;
  border:1px solid rgba(255,45,122,0.1); isolation:isolate;
}

/* MASCOTTE WATERMARK */
.mascotte-watermark {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index:1; pointer-events:none;
}
.mascotte-watermark img {
  height:100%; width:auto; max-height:100%; object-fit:contain;
  opacity:0.12;
  background:none !important;
}
.card-wrapper.fun-sport-baseball .mascotte-watermark img {
  opacity:0.26;
  filter:brightness(1.1);
}

.card-body { position:relative; z-index:2; padding:26px 32px 22px; display:flex; flex-direction:column; gap:12px; }

/* HEADER */
.card-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:40px; object-fit:contain; }
.funbet-badge {
  display:flex; align-items:center; gap:8px;
  background:rgba(255,45,122,0.1); border:1.5px solid rgba(255,45,122,0.45);
  border-radius:22px; padding:6px 20px;
  font-family:'Orbitron',sans-serif; font-size:17px; font-weight:900;
  color:#ff2d7a; letter-spacing:2px; text-transform:uppercase;
  box-shadow:0 0 10px rgba(255,45,122,0.2); text-shadow:0 0 8px rgba(255,45,122,0.5);
}

/* DATE / HEURE */
.datetime-block { text-align:center; padding:3px 0; }
.datetime-day { font-family:'Orbitron',sans-serif; font-size:18px; font-weight:600; color:rgba(255,255,255,0.32); text-transform:uppercase; letter-spacing:3px; margin-bottom:2px; }
.datetime-time { font-family:'Orbitron',sans-serif; font-size:52px; font-weight:900; letter-spacing:3px; line-height:1; color:#ff2d7a; text-shadow:0 0 25px rgba(255,45,122,0.5); }
.datetime-sub { font-size:14px; color:rgba(255,255,255,0.2); font-family:'Orbitron',sans-serif; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; margin-top:2px; }

/* SECTION TITLE */
.section-title { display:flex; align-items:center; gap:8px; }
.section-title-text { font-family:'Orbitron',sans-serif; font-size:12px; font-weight:700; color:rgba(255,45,122,0.6); text-transform:uppercase; letter-spacing:2.5px; white-space:nowrap; }
.section-title-line { flex:1; height:1px; background:linear-gradient(to right,rgba(255,45,122,0.3),transparent); }

/* LIGNES DE PARIS — lisibilité : contrastes + air */
.bets-container { display:flex; flex-direction:column; gap:14px; }
.bet-line { background:rgba(255,255,255,0.035); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:14px 16px 14px 20px; position:relative; display:flex; flex-direction:column; gap:8px; }
.bet-left-bar { position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; }
.bet-top-row { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
.bet-num-match { display:flex; align-items:center; gap:10px; flex-wrap:wrap; line-height:1.4; }
.bet-num { font-family:'Orbitron',sans-serif; font-size:15px; color:rgba(255,140,200,0.75); flex-shrink:0; }
.bet-match { font-family:'Orbitron',sans-serif; font-size:16px; color:rgba(255,255,255,0.9); letter-spacing:0.3px; font-weight:600; line-height:1.35; }
.bet-heure { font-family:'Orbitron',sans-serif; font-size:13px; color:rgba(255,45,122,0.95); font-weight:700; margin-left:4px; white-space:nowrap; flex-shrink:0; }
.bet-line .fun-team-logo { height:24px; width:auto; max-width:32px; object-fit:contain; vertical-align:middle; }
.bet-cote-pill { background:#FF2D78; border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:8px 14px; font-family:'Orbitron',sans-serif; font-size:19px; font-weight:700; color:#fff; flex-shrink:0; }
.bet-prono { font-family:'Orbitron',sans-serif; font-size:16px; font-weight:700; color:rgba(255,255,255,0.96); line-height:1.5; padding-top:2px; letter-spacing:0.2px; }

/* CONFIANCE — décollée du bloc paris */
.confidence-col { display:flex; flex-direction:column; gap:10px; margin-top:10px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.06); }
.conf-header { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:2px; }
.conf-label { font-family:'Orbitron',sans-serif; font-size:12px; color:rgba(255,255,255,0.5); text-transform:uppercase; letter-spacing:1.8px; font-weight:700; line-height:1.3; }
.conf-score { font-family:'Orbitron',sans-serif; font-size:22px; font-weight:900; color:#ff2d7a; text-shadow:0 0 8px rgba(255,45,122,0.3); flex-shrink:0; }
.conf-bar-bg { height:12px; background:rgba(255,255,255,0.08); border-radius:6px; overflow:hidden; margin-top:4px; }
.conf-bar-fill { height:100%; width:{$conf}%; background:linear-gradient(to right,#ff2d7a,#c850c0,#ff8c42); border-radius:6px; animation:barPulse 2s ease-in-out infinite; }
@keyframes barPulse { 0%,100%{opacity:0.85} 50%{opacity:1} }

/* COTE TOTALE */
.cote-totale-block { display:flex; align-items:center; gap:18px; background:rgba(255,45,122,0.06); border:1px solid rgba(255,45,122,0.14); border-radius:14px; padding:12px 18px; }
.cote-total-info { flex:1; }
.cote-eyebrow { font-family:'Orbitron',sans-serif; font-size:13px; color:rgba(255,255,255,0.25); text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:3px; }
.cote-desc { font-family:'Orbitron',sans-serif; font-size:14px; color:rgba(255,255,255,0.45); letter-spacing:1px; }
.total-pill { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:#FF2D78; border-radius:14px; padding:14px 36px; flex-shrink:0; box-shadow:0 4px 22px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12); }
.total-pill-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.13); border-radius:14px 14px 0 0; }
.total-cote { font-family:'Orbitron',sans-serif; font-size:40px; font-weight:900; color:#fff; letter-spacing:2px; position:relative; z-index:1; }

/* PROMO — Option Sport Daily, Week-end, Weekly (fonts forcées pour lisibilité) */
.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18); border-radius:14px; padding:14px 18px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:4px 0 0 4px; }
.promo-text-block { flex:1; padding-left:10px; display:flex; flex-direction:column; gap:8px; }
.promo-eyebrow { font-family:'Orbitron',sans-serif !important; font-size:13px !important; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.promo-main { font-family:'Orbitron',sans-serif !important; font-size:22px !important; font-weight:700; letter-spacing:0.8px; color:#fff; line-height:1.25; min-height:1.25em; }
.promo-main-hl { color:#ff2d7a; font-family:inherit !important; }
.promo-packs { display:flex; gap:6px; flex-wrap:wrap; }
.pack-tag { font-family:'Orbitron',sans-serif !important; font-size:13px !important; font-weight:700; padding:5px 11px; border-radius:5px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.6); text-transform:uppercase; }
.pack-tag-max { color:#39ff14; border-color:rgba(57,255,20,0.3); background:rgba(57,255,20,0.07); }
.promo-price { font-family:'Orbitron',sans-serif !important; font-size:13px !important; color:rgba(255,255,255,0.55); }
.promo-price span { font-family:'Orbitron',sans-serif !important; font-size:20px !important; font-weight:700; color:#00e5ff; }
.promo-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.promo-cta { display:inline-flex; align-items:center; gap:5px; background:linear-gradient(135deg,#39ff14,#00c896); color:#000; font-family:'Orbitron',sans-serif !important; font-size:13px !important; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:10px 18px; border-radius:10px; white-space:nowrap; box-shadow:0 0 14px rgba(57,255,20,0.4); }

/* Locked */
.locked-zone { text-align:center; margin:6px 0; }
.locked-padlock { font-size:54px; line-height:1; }
.locked-reserved { font-family:'Orbitron',sans-serif; font-size:14px; color:#ff2d7a; opacity:0.7; letter-spacing:2px; margin:8px 0; }
.locked-cta-btn { background:linear-gradient(135deg,#FF2D78,#d6245f); color:white; font-family:'Orbitron',sans-serif; font-size:14px; font-weight:700; padding:10px 30px; border-radius:12px; display:inline-block; letter-spacing:1px; }

.card-footer-gradient { height:4px; background:linear-gradient(to right,#ff2d7a,#c850c0,#4158d0); position:relative; z-index:2; }

/* Tennis Fun — vert néon (mascotte tennis, couleurs vertes) */
.card-wrapper.tennis-fun .border-glow { display:none; }
.card-wrapper.tennis-fun .card { border:none; background:#0a0e0a; }
.card-wrapper.tennis-fun .funbet-badge { background:rgba(57,255,20,0.12); border-color:rgba(57,255,20,0.5); color:#39ff14; box-shadow:0 0 10px rgba(57,255,20,0.25); text-shadow:0 0 8px rgba(57,255,20,0.4); }
.card-wrapper.tennis-fun .datetime-time { color:#39ff14; text-shadow:0 0 25px rgba(57,255,20,0.5); }
.card-wrapper.tennis-fun .section-title-text { color:rgba(57,255,20,0.8); }
.card-wrapper.tennis-fun .section-title-line { background:linear-gradient(to right,rgba(57,255,20,0.35),transparent); }
.card-wrapper.tennis-fun .bet-num { color:rgba(144,255,128,0.85); }
.card-wrapper.tennis-fun .bet-match { color:rgba(255,255,255,0.92); }
.card-wrapper.tennis-fun .bet-heure { color:rgba(57,255,20,0.95); }
.card-wrapper.tennis-fun .bet-prono { color:rgba(255,255,255,0.97); }
.card-wrapper.tennis-fun .conf-label { color:rgba(255,255,255,0.52); }
.card-wrapper.tennis-fun .bet-cote-pill { background:#FF2D78; border-color:rgba(255,255,255,0.2); color:#fff; }
.card-wrapper.tennis-fun .conf-score { color:#39ff14; text-shadow:0 0 8px rgba(57,255,20,0.35); }
.card-wrapper.tennis-fun .conf-bar-fill { background:linear-gradient(to right,#39ff14,#00d46a,#00c896); }
.card-wrapper.tennis-fun .cote-totale-block { background:rgba(57,255,20,0.06); border-color:rgba(57,255,20,0.18); }
.card-wrapper.tennis-fun .total-pill { background:#FF2D78; box-shadow:0 4px 22px rgba(255,45,122,0.5); }
.card-wrapper.tennis-fun .card-footer-gradient { background:linear-gradient(to right,#39ff14,#00d46a,#00c896); }
.card-wrapper.tennis-fun .promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.35); }
.card-wrapper.tennis-fun .promo-left-bar { background:linear-gradient(to bottom,#39ff14,#00e5ff); }
.card-wrapper.tennis-fun .promo-eyebrow { color:#39ff14; }
.card-wrapper.tennis-fun .promo-main { font-size:22px; letter-spacing:0.3px; line-height:1.4; }
.card-wrapper.tennis-fun .promo-main-hl { color:#39ff14; }
.card-wrapper.tennis-fun .promo-cta { background:linear-gradient(135deg,#ff2d78,#d6245f); color:#fff; box-shadow:0 0 14px rgba(255,45,120,0.5); }
.card-wrapper.tennis-fun .promo-price span { color:#39ff14; }
.card-wrapper.tennis-fun .pack-tag-max { color:#39ff14; border-color:rgba(57,255,20,0.35); background:rgba(57,255,20,0.08); }
.card-wrapper.tennis-fun .locked-reserved { color:#39ff14; }
.card-wrapper.tennis-fun .locked-cta-btn { background:linear-gradient(135deg,#39ff14,#00c896); color:#000; }
CSS;

    // ── Générer les lignes de paris ──
    $betLinesNormal = '';
    $betLinesLocked = '';
    foreach ($bets as $i => $bet) {
        if (!is_array($bet)) continue;
        $num    = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        $match  = htmlspecialchars($bet['match'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $prono  = htmlspecialchars($bet['prono'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $bcote  = htmlspecialchars($bet['cote']  ?? '1.00', ENT_QUOTES, 'UTF-8', false);
        $heure  = htmlspecialchars($bet['heure'] ?? $bet['time'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $heureSpan = $heure !== '' ? "<span class='bet-heure'>{$heure}</span>" : '';

        $logo1Url = trim((string)($bet['team1_logo'] ?? ''));
        $logo2Url = trim((string)($bet['team2_logo'] ?? ''));
        $matchRaw = (string)($bet['match'] ?? '');
        $matchParts = preg_split('/\s+vs\.?\s+/i', $matchRaw, 2);
        $team1Name = trim($matchParts[0] ?? '');
        $team2Name = trim($matchParts[1] ?? '');
        if (($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) && $team1Name !== '' && function_exists('stratedge_fetch_team_logo_url')) {
            $logo1Url = stratedge_fetch_team_logo_url($team1Name);
        }
        if (($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) && $team2Name !== '' && function_exists('stratedge_fetch_team_logo_url')) {
            $logo2Url = stratedge_fetch_team_logo_url($team2Name);
        }
        $isHockey = (strtolower($d['sport'] ?? '') === 'hockey');
        if ($isHockey) {
            if ($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) {
                $logo1Url = nhlLogoUrl($team1Name);
            }
            if ($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) {
                $logo2Url = nhlLogoUrl($team2Name);
            }
        }
        $isBaseball = (strtolower($d['sport'] ?? '') === 'baseball');
        if ($isBaseball) {
            if ($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) {
                $logo1Url = mlbLogoUrl($team1Name);
            }
            if ($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) {
                $logo2Url = mlbLogoUrl($team2Name);
            }
        }
        if ($logo1Url !== '' && filter_var($logo1Url, FILTER_VALIDATE_URL)) {
            $ico1 = '<img src="' . htmlspecialchars(logoProxyUrl($logo1Url), ENT_QUOTES, 'UTF-8', false) . '" class="fun-team-logo" alt="">';
        } else {
            $ico1 = flagImg(is_string($bet['flag1'] ?? null) ? $bet['flag1'] : '');
        }
        if ($logo2Url !== '' && filter_var($logo2Url, FILTER_VALIDATE_URL)) {
            $ico2 = '<img src="' . htmlspecialchars(logoProxyUrl($logo2Url), ENT_QUOTES, 'UTF-8', false) . '" class="fun-team-logo" alt="">';
        } else {
            $ico2 = flagImg(is_string($bet['flag2'] ?? null) ? $bet['flag2'] : '');
        }

        $barColor = $barColors[$i % 3];

        $betLinesNormal .= <<<HTML
    <div class='bet-line'>
      <div class='bet-left-bar' style='background:{$barColor}'></div>
      <div class='bet-top-row'>
        <div class='bet-num-match'>
          <span class='bet-num'>{$num}</span>
          <span>{$ico1}</span>
          <span class='bet-match'>{$match}</span>
          {$heureSpan}
          <span>{$ico2}</span>
        </div>
        <div class='bet-cote-pill'>{$bcote}</div>
      </div>
      <div class='bet-prono'>{$prono}</div>
    </div>
HTML;

        $betLinesLocked .= <<<HTML
    <div class='bet-line'>
      <div class='bet-left-bar' style='background:{$barColor}'></div>
      <div class='bet-top-row'>
        <div class='bet-num-match'>
          <span class='bet-num'>{$num}</span>
          <span>{$ico1}</span>
          <span class='bet-match'>{$match}</span>
          {$heureSpan}
          <span>{$ico2}</span>
        </div>
        <span style='font-size:18px'>🔒</span>
      </div>
      <div style='height:10px;background:rgba(255,255,255,0.08);border-radius:3px;width:80%;margin:3px 0'></div>
      <div style='height:10px;background:rgba(255,255,255,0.05);border-radius:3px;width:55%;margin:2px 0'></div>
    </div>
HTML;
    }

    if ($isTennis) {
        $promoBlock = <<<HTML
  <div class='promo-banner'>
    <div class='promo-left-bar'></div>
    <div class='promo-text-block'>
      <div class='promo-eyebrow'>🎾 FUN BET TENNIS — GROSSES COTES</div>
      <div class='promo-main'><span class='promo-main-hl'>10€ / semaine</span> — combinés ATP / WTA</div>
      <div class='promo-main' style='font-size:16px;margin-top:6px;line-height:1.5;font-weight:600;'>Uniquement des <span class='promo-main-hl'>grosses cotes</span> · Fun réservé aux gros potentiels</div>
      <div class='promo-packs'>
        <span class='pack-tag pack-tag-max'>🎾 Fun Tennis — 10€/sem</span>
      </div>
      <div class='promo-price'>S’abonner sur <span>stratedgepronos.fr</span></div>
    </div>
    <div class='promo-right'>
      <div class='promo-cta'>🎾 Je m'abonne</div>
    </div>
  </div>
HTML;
    } else {
        $promoBlock = <<<HTML
  <div class='promo-banner'>
    <div class='promo-left-bar'></div>
    <div class='promo-text-block'>
      <div class='promo-eyebrow'>⚡ FUN BET — 100 % GROSSES COTES</div>
      <div class='promo-main'><span class='promo-main-hl'>10€ / semaine</span> — comme nos autres offres</div>
      <div class='promo-main' style='font-size:16px;margin-top:6px;line-height:1.5;font-weight:600;'>Pas du safe : que des <span class='promo-main-hl'>grosses cotes</span> · combinés à fort potentiel</div>
      <div class='promo-packs'>
        <span class='pack-tag pack-tag-max'>⚡ Fun Bet — 10€/sem</span>
      </div>
      <div class='promo-price'>S’abonner sur <span>stratedgepronos.fr</span></div>
    </div>
    <div class='promo-right'>
      <div class='promo-cta'>⚡ Je m'abonne</div>
    </div>
  </div>
HTML;
    }

    $wrapperClass = $isTennis ? 'card-wrapper tennis-fun' : 'card-wrapper';
    if ($isBaseball) {
        $wrapperClass .= ' fun-sport-baseball';
    }
    $funMascotteLockedOp = $isBaseball ? '0.14' : '0.06';
    $funBadgeText = $isTennis ? '🎾 Fun Bet Tennis' : '⚡ Fun Bet';
    $sectionTitle = $isTennis ? '🎾 Sélection multi-paris Tennis' : '⚡ Sélection multi-paris';

    // CARD NORMALE
    $html_normal = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>

<style>{$css}</style>
</head>
<body>
<div class='{$wrapperClass}'>
  <div class='border-glow'></div>
  <div class='card'>
    <div class='mascotte-watermark'>
      <img src='{$mascotteUrl}' alt=''>
    </div>
    <div class='card-body'>
      <div class='card-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div class='funbet-badge'>{$funBadgeText}</div>
      </div>
      <div class='datetime-block'>
        <div class='datetime-day'>{$date}</div>
        <div class='datetime-time'>{$time}</div>
        <div class='datetime-sub'>heure du 1er match</div>
      </div>
      <div class='section-title'>
        <span class='section-title-text'>{$sectionTitle}</span>
        <div class='section-title-line'></div>
      </div>
      <div class='bets-container'>
        {$betLinesNormal}
      </div>
      <div class='confidence-col'>
        <div class='conf-header'>
          <span class='conf-label'>🎲 Confiance globale</span>
          <span class='conf-score'>{$conf}/100</span>
        </div>
        <div class='conf-bar-bg'><div class='conf-bar-fill'></div></div>
      </div>
      <div class='cote-totale-block'>
        <div class='cote-total-info'>
          <div class='cote-eyebrow'>Cote totale combinée</div>
          <div class='cote-desc'>{$nbBets} sélections · Mise à votre risque</div>
        </div>
        <div class='total-pill'><div class='total-pill-shine'></div><div class='total-cote'>{$coteTot}</div></div>
      </div>
      {$promoBlock}
    </div>
    <div class='card-footer-gradient'></div>
  </div>
</div>
</body></html>
HTML;

    // CARD LOCKED
    $html_locked = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>

<style>{$css}</style>
</head>
<body>
<div class='{$wrapperClass}'>
  <div class='border-glow'></div>
  <div class='card'>
    <div class='mascotte-watermark'>
      <img src='{$mascotteUrl}' alt='' style='opacity:{$funMascotteLockedOp}'>
    </div>
    <div class='card-body'>
      <div class='card-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div class='funbet-badge'>{$funBadgeText}</div>
      </div>
      <div class='datetime-block'>
        <div class='datetime-day'>{$date}</div>
        <div class='datetime-time'>{$time}</div>
        <div class='datetime-sub'>heure du 1er match</div>
      </div>
      <div class='section-title'>
        <span class='section-title-text'>{$sectionTitle}</span>
        <div class='section-title-line'></div>
      </div>
      <div class='bets-container'>
        {$betLinesLocked}
      </div>
      <div class='locked-zone'>
        <div class='locked-padlock'>🔒</div>
        <div class='locked-reserved'>CONTENU RÉSERVÉ</div>
        <div class='locked-cta-btn'>🔓 Reçois le bet sur stratedgepronos.fr</div>
      </div>
      <div class='confidence-col'>
        <div class='conf-header'>
          <span class='conf-label'>🎲 Confiance globale</span>
          <span class='conf-score'>{$conf}/100</span>
        </div>
        <div class='conf-bar-bg'><div class='conf-bar-fill'></div></div>
      </div>
      <div class='cote-totale-block'>
        <div class='cote-total-info'>
          <div class='cote-eyebrow'>Cote totale combinée</div>
          <div class='cote-desc'>{$nbBets} sélections · Mise à votre risque</div>
        </div>
        <div class='total-pill'><div class='total-pill-shine'></div><div class='total-cote'>{$coteTot}</div></div>
      </div>
      {$promoBlock}
    </div>
    <div class='card-footer-gradient'></div>
  </div>
</div>
</body></html>
HTML;

    return ['html_normal' => $html_normal, 'html_locked' => $html_locked];
}


// ════════════════════════════════════════════════════════════
//  SAFE COMBINÉ — Template PHP (1440px)
//  $d = [
//    'sport'              => 'football',
//    'date_fr'            => 'Mercredi 26 Février 2026',
//    'time_fr'            => '20:45',
//    'bets'               => [
//      ['match'=>'...','heure'=>'20:45','flag1'=>'🇫🇷','flag2'=>'🇫🇷',
//       'team1_logo'=>'','team2_logo'=>'',
//       'prono'=>'Victoire PSG','cote'=>'1.65','confidence'=>78,
//       'value_pct'=>8.2,'analyse'=>'PSG invaincu à domicile...'],
//    ],
//    'cote_totale'        => '3.42',
//    'confidence_globale' => 68,
//  ]
// ════════════════════════════════════════════════════════════
function generateSafeCombiCards($d) {
    $sport       = strtolower(trim($d['sport'] ?? 'football'));
    $isTennis    = ($sport === 'tennis');
    $isBaseball  = ($sport === 'baseball');
    if ($isTennis) {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-tennis.png';
    } elseif ($isBaseball) {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-mlb.png';
    } else {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte.png';
    }
    $logo        = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';

    $date    = htmlspecialchars($d['date_fr']    ?? '', ENT_QUOTES, 'UTF-8', false);
    $time    = htmlspecialchars($d['time_fr']    ?? '00:00', ENT_QUOTES, 'UTF-8', false);
    $coteTot = htmlspecialchars($d['cote_totale']?? '1.00', ENT_QUOTES, 'UTF-8', false);
    $confGlob = intval($d['confidence_globale'] ?? 65);
    $bets    = $d['bets'] ?? [];
    $nbBets  = count($bets);

    $embeddedFonts = getLocalFontsCss();

    if ($isTennis) {
        $accentMain  = '#39ff14';
        $accentSec   = '#00d46a';
        $confBarGrad = 'linear-gradient(to right,#39ff14,#00d46a,#00c896)';
        $pillBg      = '#FF2D78';
        $badgeBg     = 'rgba(57,255,20,0.12)';
        $badgeBorder = 'rgba(57,255,20,0.5)';
        $badgeColor  = '#39ff14';
        $footerGrad  = 'linear-gradient(to right,#39ff14,#00d46a,#00c896)';
        $wrapperExtra = 'tennis-safe-combi';
    } else {
        $accentMain  = '#ff2d7a';
        $accentSec   = '#c850c0';
        $confBarGrad = 'linear-gradient(to right,#ff2d7a,#c850c0,#ff8c42)';
        $pillBg      = '#FF2D78';
        $badgeBg     = 'rgba(255,45,122,0.1)';
        $badgeBorder = 'rgba(255,45,122,0.45)';
        $badgeColor  = '#ff2d7a';
        $footerGrad  = 'linear-gradient(to right,#ff2d7a,#c850c0,#4158d0)';
        $wrapperExtra = '';
    }

    $barColors = $isTennis
        ? ['linear-gradient(to bottom,#39ff14,#00d46a)','linear-gradient(to bottom,#00d46a,#00c896)','linear-gradient(to bottom,#00c896,#00e5ff)']
        : ['linear-gradient(to bottom,#ff2d7a,#c850c0)','linear-gradient(to bottom,#c850c0,#4158d0)','linear-gradient(to bottom,#4158d0,#00e5ff)'];

    $css = $embeddedFonts . "\n@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Bebas+Neue&family=Rajdhani:wght@400;600;700&display=swap');\n" . <<<CSS

* { margin:0; padding:0; box-sizing:border-box; }
html { background:#0a0a0a !important; width:1440px; min-width:1440px; max-width:1440px; overflow-x:hidden; }
body { background:#0a0a0a !important; margin:0; padding:0; width:1440px; min-width:1440px; max-width:1440px; font-family:'Orbitron',sans-serif; }

.sc-wrapper { position:relative; width:1440px; max-width:1440px; min-width:1440px; background:#0a0a0a; }
.sc-border-glow {
  position:absolute; inset:-2px; border-radius:24px;
  background:linear-gradient(135deg,{$accentMain},{$accentSec},{$accentMain});
  background-size:300% 300%; animation:scGradShift 4s ease infinite;
  z-index:-1; filter:blur(12px); opacity:0.6;
}
@keyframes scGradShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.sc-card {
  position:relative; z-index:1; width:1440px; max-width:1440px; background:#0e0b12;
  border-radius:20px; overflow:hidden; display:flex; flex-direction:column;
  border:1px solid rgba(255,45,122,0.08); isolation:isolate;
}

.sc-mascotte {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index:1; pointer-events:none;
}
/* Watermark lisible sur fond sombre (Fun combi ~0.12 ; MLB souvent plus sombre → renfort ci-dessous) */
.sc-mascotte img {
  height:100%; width:auto; max-height:100%; object-fit:contain;
  opacity:0.18;
  background:none !important;
}
.sc-wrapper.sc-sport-baseball .sc-mascotte img {
  opacity:0.32;
  filter:brightness(1.12);
}
.sc-wrapper.sc-variant-locked .sc-mascotte img {
  opacity:0.10;
}
.sc-wrapper.sc-sport-baseball.sc-variant-locked .sc-mascotte img {
  opacity:0.18;
  filter:brightness(1.08);
}

.sc-body { position:relative; z-index:2; padding:32px 40px 28px; display:flex; flex-direction:column; gap:14px; }

.sc-header { display:flex; align-items:center; justify-content:space-between; }
.sc-logo { height:44px; object-fit:contain; }
.sc-badge {
  display:flex; align-items:center; gap:10px;
  background:{$badgeBg}; border:1.5px solid {$badgeBorder};
  border-radius:24px; padding:8px 24px;
  font-family:'Orbitron',sans-serif; font-size:18px; font-weight:900;
  color:{$badgeColor}; letter-spacing:2.5px; text-transform:uppercase;
  box-shadow:0 0 12px rgba(255,45,122,0.2); text-shadow:0 0 8px rgba(255,45,122,0.4);
}

.sc-datetime { text-align:center; padding:4px 0; }
.sc-date-day { font-family:'Orbitron',sans-serif; font-size:18px; font-weight:600; color:rgba(255,255,255,0.3); text-transform:uppercase; letter-spacing:3px; margin-bottom:3px; }
.sc-date-time { font-family:'Orbitron',sans-serif; font-size:54px; font-weight:900; letter-spacing:3px; line-height:1; color:{$accentMain}; text-shadow:0 0 28px rgba(255,45,122,0.5); }
.sc-date-sub { font-size:14px; color:rgba(255,255,255,0.18); font-family:'Orbitron',sans-serif; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin-top:3px; }

.sc-section-title { display:flex; align-items:center; gap:8px; }
.sc-section-text { font-family:'Orbitron',sans-serif; font-size:13px; font-weight:700; color:rgba(255,45,122,0.55); text-transform:uppercase; letter-spacing:2.5px; white-space:nowrap; }
.sc-section-line { flex:1; height:1px; background:linear-gradient(to right,rgba(255,45,122,0.25),transparent); }

.sc-bets { display:flex; flex-direction:column; gap:10px; }
.sc-bet {
  background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05);
  border-radius:12px; padding:14px 18px 14px 22px; position:relative;
  display:flex; flex-direction:column; gap:6px;
}
.sc-bet-bar { position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; }
.sc-bet-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.sc-bet-left { display:flex; align-items:center; gap:8px; flex:1; flex-wrap:wrap; }
.sc-bet-num { font-family:'Orbitron',sans-serif; font-size:16px; color:rgba(255,140,200,0.55); font-weight:700; }
.sc-bet-match { font-family:'Orbitron',sans-serif; font-size:18px; color:rgba(255,255,255,0.5); letter-spacing:0.5px; font-weight:600; }
.sc-bet-heure { font-family:'Orbitron',sans-serif; font-size:14px; color:{$accentMain}; font-weight:700; opacity:0.85; white-space:nowrap; }
.sc-bet .sc-team-logo { height:26px; width:auto; max-width:34px; object-fit:contain; vertical-align:middle; }
.sc-bet-right { display:flex; align-items:center; gap:12px; flex-shrink:0; }
.sc-cote-pill {
  background:{$pillBg}; border:1px solid rgba(255,255,255,0.2); border-radius:8px;
  padding:7px 15px; font-family:'Orbitron',sans-serif; font-size:21px; font-weight:700; color:#fff;
  box-shadow:0 2px 10px rgba(255,45,122,0.25);
}
.sc-value-tag {
  background:rgba(57,255,20,0.1); border:1px solid rgba(57,255,20,0.3); border-radius:6px;
  padding:4px 10px; font-family:'Orbitron',sans-serif; font-size:12px; font-weight:700;
  color:#39ff14; letter-spacing:0.5px; white-space:nowrap;
}
.sc-bet-prono { font-family:'Orbitron',sans-serif; font-size:17px; font-weight:700; color:rgba(255,255,255,0.88); }
.sc-bet-analyse { font-family:'Rajdhani',sans-serif; font-size:15px; color:rgba(255,255,255,0.35); line-height:1.4; font-weight:400; }

.sc-bet-conf { display:flex; align-items:center; gap:10px; margin-top:2px; }
.sc-bet-conf-label { font-family:'Orbitron',sans-serif; font-size:11px; color:rgba(255,255,255,0.2); text-transform:uppercase; letter-spacing:1.5px; font-weight:700; white-space:nowrap; }
.sc-bet-conf-bar { flex:1; height:5px; background:rgba(255,255,255,0.04); border-radius:3px; overflow:hidden; }
.sc-bet-conf-fill { height:100%; border-radius:3px; background:{$confBarGrad}; }
.sc-bet-conf-score { font-family:'Orbitron',sans-serif; font-size:13px; font-weight:900; color:{$accentMain}; text-shadow:0 0 6px rgba(255,45,122,0.2); min-width:40px; text-align:right; }

.sc-conf-global { display:flex; flex-direction:column; gap:6px; }
.sc-conf-header { display:flex; justify-content:space-between; align-items:center; }
.sc-conf-label { font-family:'Orbitron',sans-serif; font-size:15px; color:rgba(255,255,255,0.25); text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.sc-conf-score { font-family:'Orbitron',sans-serif; font-size:24px; font-weight:900; color:{$accentMain}; text-shadow:0 0 10px rgba(255,45,122,0.3); }
.sc-conf-bar-bg { height:9px; background:rgba(255,255,255,0.04); border-radius:5px; overflow:hidden; }
.sc-conf-bar-fill { height:100%; width:{$confGlob}%; background:{$confBarGrad}; border-radius:5px; animation:scBarPulse 2s ease-in-out infinite; }
@keyframes scBarPulse { 0%,100%{opacity:0.85} 50%{opacity:1} }

.sc-cote-block {
  display:flex; align-items:center; gap:20px;
  background:rgba(255,45,122,0.05); border:1px solid rgba(255,45,122,0.12);
  border-radius:16px; padding:14px 22px;
}
.sc-cote-info { flex:1; }
.sc-cote-eyebrow { font-family:'Orbitron',sans-serif; font-size:13px; color:rgba(255,255,255,0.22); text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:3px; }
.sc-cote-desc { font-family:'Orbitron',sans-serif; font-size:14px; color:rgba(255,255,255,0.4); letter-spacing:1px; }
.sc-total-pill {
  position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center;
  background:{$pillBg}; border-radius:16px; padding:16px 40px; flex-shrink:0;
  box-shadow:0 4px 26px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12);
}
.sc-total-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.12); border-radius:16px 16px 0 0; }
.sc-total-cote { font-family:'Orbitron',sans-serif; font-size:44px; font-weight:900; color:#fff; letter-spacing:2px; position:relative; z-index:1; }

.sc-promo {
  background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18);
  border-radius:14px; padding:14px 18px; position:relative;
  display:flex; align-items:center; justify-content:space-between; gap:14px;
}
.sc-promo-bar { position:absolute; left:0; top:0; bottom:0; width:4px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:4px 0 0 4px; }
.sc-promo-text { flex:1; padding-left:10px; display:flex; flex-direction:column; gap:5px; }
.sc-promo-eyebrow { font-family:'Orbitron',sans-serif !important; font-size:13px !important; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.sc-promo-main { font-family:'Orbitron',sans-serif !important; font-size:22px !important; font-weight:700; letter-spacing:0.8px; color:#fff; line-height:1.25; }
.sc-promo-hl { color:{$accentMain}; font-family:inherit !important; }
.sc-promo-packs { display:flex; gap:6px; flex-wrap:wrap; }
.sc-pack-tag { font-family:'Orbitron',sans-serif !important; font-size:13px !important; font-weight:700; padding:5px 11px; border-radius:5px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.6); text-transform:uppercase; }
.sc-pack-max { color:#39ff14; border-color:rgba(57,255,20,0.3); background:rgba(57,255,20,0.07); }
.sc-promo-cta {
  display:inline-flex; align-items:center; gap:5px;
  background:linear-gradient(135deg,#39ff14,#00c896); color:#000;
  font-family:'Orbitron',sans-serif !important; font-size:13px !important;
  font-weight:900; letter-spacing:0.8px; text-transform:uppercase;
  padding:10px 18px; border-radius:10px; white-space:nowrap;
  box-shadow:0 0 14px rgba(57,255,20,0.4);
}

.sc-locked { text-align:center; margin:8px 0; }
.sc-locked-padlock { font-size:58px; line-height:1; }
.sc-locked-text { font-family:'Orbitron',sans-serif; font-size:14px; color:{$accentMain}; opacity:0.7; letter-spacing:2px; margin:10px 0; }
.sc-locked-btn { background:linear-gradient(135deg,{$accentMain},{$accentSec}); color:white; font-family:'Orbitron',sans-serif; font-size:14px; font-weight:700; padding:11px 34px; border-radius:12px; display:inline-block; letter-spacing:1px; }

.sc-footer-grad { height:4px; background:{$footerGrad}; position:relative; z-index:2; }
CSS;

    // ── Bet lines HTML ──
    $betLinesNormal = '';
    $betLinesLocked = '';
    foreach ($bets as $i => $bet) {
        if (!is_array($bet)) continue;
        $num    = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        $match  = htmlspecialchars($bet['match'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $prono  = htmlspecialchars($bet['prono'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $bcote  = htmlspecialchars($bet['cote']  ?? '1.00', ENT_QUOTES, 'UTF-8', false);
        $heure  = htmlspecialchars($bet['heure'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $bconf  = intval($bet['confidence'] ?? 65);
        $valuePct = floatval($bet['value_pct'] ?? 0);
        $analyse  = htmlspecialchars($bet['analyse'] ?? '', ENT_QUOTES, 'UTF-8', false);
        $heureSpan = $heure !== '' ? "<span class='sc-bet-heure'>{$heure}</span>" : '';
        $valueTag  = ($valuePct > 0) ? "<span class='sc-value-tag'>VALUE +{$valuePct}%</span>" : '';

        $logo1Url = trim((string)($bet['team1_logo'] ?? ''));
        $logo2Url = trim((string)($bet['team2_logo'] ?? ''));
        $matchRaw = (string)($bet['match'] ?? '');
        $matchParts = preg_split('/\s+vs\.?\s+/i', $matchRaw, 2);
        $team1Name = trim($matchParts[0] ?? '');
        $team2Name = trim($matchParts[1] ?? '');
        if (($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) && $team1Name !== '' && function_exists('stratedge_fetch_team_logo_url')) {
            $logo1Url = stratedge_fetch_team_logo_url($team1Name);
        }
        if (($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) && $team2Name !== '' && function_exists('stratedge_fetch_team_logo_url')) {
            $logo2Url = stratedge_fetch_team_logo_url($team2Name);
        }
        $isHockey = (strtolower($d['sport'] ?? '') === 'hockey');
        if ($isHockey) {
            if ($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) $logo1Url = nhlLogoUrl($team1Name);
            if ($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) $logo2Url = nhlLogoUrl($team2Name);
        }
        $isBaseball = (strtolower($d['sport'] ?? '') === 'baseball');
        if ($isBaseball) {
            if ($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) $logo1Url = mlbLogoUrl($team1Name);
            if ($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) $logo2Url = mlbLogoUrl($team2Name);
        }
        if ($logo1Url !== '' && filter_var($logo1Url, FILTER_VALIDATE_URL)) {
            $ico1 = '<img src="' . htmlspecialchars(logoProxyUrl($logo1Url), ENT_QUOTES, 'UTF-8', false) . '" class="sc-team-logo" alt="">';
        } else {
            $ico1 = flagImg(is_string($bet['flag1'] ?? null) ? $bet['flag1'] : '');
        }
        if ($logo2Url !== '' && filter_var($logo2Url, FILTER_VALIDATE_URL)) {
            $ico2 = '<img src="' . htmlspecialchars(logoProxyUrl($logo2Url), ENT_QUOTES, 'UTF-8', false) . '" class="sc-team-logo" alt="">';
        } else {
            $ico2 = flagImg(is_string($bet['flag2'] ?? null) ? $bet['flag2'] : '');
        }

        $barColor = $barColors[$i % 3];

        $betLinesNormal .= <<<HTML
    <div class='sc-bet'>
      <div class='sc-bet-bar' style='background:{$barColor}'></div>
      <div class='sc-bet-top'>
        <div class='sc-bet-left'>
          <span class='sc-bet-num'>{$num}</span>
          <span>{$ico1}</span>
          <span class='sc-bet-match'>{$match}</span>
          {$heureSpan}
          <span>{$ico2}</span>
        </div>
        <div class='sc-bet-right'>
          {$valueTag}
          <div class='sc-cote-pill'>{$bcote}</div>
        </div>
      </div>
      <div class='sc-bet-prono'>🛡️ {$prono}</div>
      <div class='sc-bet-analyse'>{$analyse}</div>
      <div class='sc-bet-conf'>
        <span class='sc-bet-conf-label'>Confiance</span>
        <div class='sc-bet-conf-bar'><div class='sc-bet-conf-fill' style='width:{$bconf}%'></div></div>
        <span class='sc-bet-conf-score'>{$bconf}/100</span>
      </div>
    </div>
HTML;

        $betLinesLocked .= <<<HTML
    <div class='sc-bet'>
      <div class='sc-bet-bar' style='background:{$barColor}'></div>
      <div class='sc-bet-top'>
        <div class='sc-bet-left'>
          <span class='sc-bet-num'>{$num}</span>
          <span>{$ico1}</span>
          <span class='sc-bet-match'>{$match}</span>
          {$heureSpan}
          <span>{$ico2}</span>
        </div>
        <span style='font-size:20px'>🔒</span>
      </div>
      <div style='height:10px;background:rgba(255,255,255,0.08);border-radius:3px;width:85%;margin:3px 0'></div>
      <div style='height:10px;background:rgba(255,255,255,0.05);border-radius:3px;width:60%;margin:2px 0'></div>
      <div class='sc-bet-conf'>
        <span class='sc-bet-conf-label'>Confiance</span>
        <div class='sc-bet-conf-bar'><div class='sc-bet-conf-fill' style='width:{$bconf}%'></div></div>
        <span class='sc-bet-conf-score'>{$bconf}/100</span>
      </div>
    </div>
HTML;
    }

    if ($isTennis) {
        $promoBlock = <<<HTML
  <div class='sc-promo'>
    <div class='sc-promo-bar'></div>
    <div class='sc-promo-text'>
      <div class='sc-promo-eyebrow'>🎾 SAFE COMBINÉ TENNIS — SÉLECTION PREMIUM</div>
      <div class='sc-promo-main'>Inclus dans le <span class='sc-promo-hl'>Pack Tennis Pro</span></div>
      <div class='sc-promo-packs'>
        <span class='sc-pack-tag sc-pack-max'>🎾 Tennis Weekly — 15€/sem</span>
      </div>
    </div>
    <div class='sc-promo-cta'>🎾 Je m'abonne</div>
  </div>
HTML;
    } else {
        $promoBlock = <<<HTML
  <div class='sc-promo'>
    <div class='sc-promo-bar'></div>
    <div class='sc-promo-text'>
      <div class='sc-promo-eyebrow'>🛡️ Safe Combiné — Sélection Premium</div>
      <div class='sc-promo-main'>En option dans l'offre <span class='sc-promo-hl'>Week-end (+10€)</span></div>
      <div class='sc-promo-main' style='font-size:17px;margin-top:2px;'>Inclus dans l'offre <span class='sc-promo-hl'>VIP MAX</span></div>
      <div class='sc-promo-packs'>
        <span class='sc-pack-tag'>📅 Week-end +10€</span>
        <span class='sc-pack-tag sc-pack-max'>👑 Inclus MAX</span>
      </div>
    </div>
    <div class='sc-promo-cta'>⚡ Je m'abonne</div>
  </div>
HTML;
    }

    $sportClass   = $isBaseball ? ' sc-sport-baseball' : '';
    $wrapperClass = 'sc-wrapper sc-variant-normal' . ($wrapperExtra ? " $wrapperExtra" : '') . $sportClass;
    $wrapperClassLocked = 'sc-wrapper sc-variant-locked' . ($wrapperExtra ? " $wrapperExtra" : '') . $sportClass;
    $badgeText    = $isTennis ? '🎾🛡️ Safe Combiné Tennis' : '🛡️ Safe Combiné';
    $sectionTitle = $isTennis ? '🎾 Sélection multi-bets Safe Tennis' : '🛡️ Sélection multi-bets Safe';

    $html_normal = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>
<style>{$css}</style>
</head>
<body>
<div class='{$wrapperClass}'>
  <div class='sc-border-glow'></div>
  <div class='sc-card'>
    <div class='sc-mascotte'>
      <img src='{$mascotteUrl}' alt=''>
    </div>
    <div class='sc-body'>
      <div class='sc-header'>
        <img src='{$logo}' class='sc-logo' alt='StratEdge'>
        <div class='sc-badge'>{$badgeText}</div>
      </div>
      <div class='sc-datetime'>
        <div class='sc-date-day'>{$date}</div>
        <div class='sc-date-time'>{$time}</div>
        <div class='sc-date-sub'>heure du 1er match</div>
      </div>
      <div class='sc-section-title'>
        <span class='sc-section-text'>{$sectionTitle}</span>
        <div class='sc-section-line'></div>
      </div>
      <div class='sc-bets'>
        {$betLinesNormal}
      </div>
      <div class='sc-conf-global'>
        <div class='sc-conf-header'>
          <span class='sc-conf-label'>🛡️ Confiance globale combiné</span>
          <span class='sc-conf-score'>{$confGlob}/100</span>
        </div>
        <div class='sc-conf-bar-bg'><div class='sc-conf-bar-fill'></div></div>
      </div>
      <div class='sc-cote-block'>
        <div class='sc-cote-info'>
          <div class='sc-cote-eyebrow'>Cote totale combinée</div>
          <div class='sc-cote-desc'>{$nbBets} sélections Safe · Mise à votre risque</div>
        </div>
        <div class='sc-total-pill'><div class='sc-total-shine'></div><div class='sc-total-cote'>{$coteTot}</div></div>
      </div>
      {$promoBlock}
    </div>
    <div class='sc-footer-grad'></div>
  </div>
</div>
</body></html>
HTML;

    $html_locked = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>
<style>{$css}</style>
</head>
<body>
<div class='{$wrapperClassLocked}'>
  <div class='sc-border-glow'></div>
  <div class='sc-card'>
    <div class='sc-mascotte'>
      <img src='{$mascotteUrl}' alt=''>
    </div>
    <div class='sc-body'>
      <div class='sc-header'>
        <img src='{$logo}' class='sc-logo' alt='StratEdge'>
        <div class='sc-badge'>{$badgeText}</div>
      </div>
      <div class='sc-datetime'>
        <div class='sc-date-day'>{$date}</div>
        <div class='sc-date-time'>{$time}</div>
        <div class='sc-date-sub'>heure du 1er match</div>
      </div>
      <div class='sc-section-title'>
        <span class='sc-section-text'>{$sectionTitle}</span>
        <div class='sc-section-line'></div>
      </div>
      <div class='sc-bets'>
        {$betLinesLocked}
      </div>
      <div class='sc-locked'>
        <div class='sc-locked-padlock'>🔒</div>
        <div class='sc-locked-text'>CONTENU RÉSERVÉ</div>
        <div class='sc-locked-btn'>🔓 Reçois le bet sur stratedgepronos.fr</div>
      </div>
      <div class='sc-conf-global'>
        <div class='sc-conf-header'>
          <span class='sc-conf-label'>🛡️ Confiance globale combiné</span>
          <span class='sc-conf-score'>{$confGlob}/100</span>
        </div>
        <div class='sc-conf-bar-bg'><div class='sc-conf-bar-fill'></div></div>
      </div>
      <div class='sc-cote-block'>
        <div class='sc-cote-info'>
          <div class='sc-cote-eyebrow'>Cote totale combinée</div>
          <div class='sc-cote-desc'>{$nbBets} sélections Safe · Mise à votre risque</div>
        </div>
        <div class='sc-total-pill'><div class='sc-total-shine'></div><div class='sc-total-cote'>{$coteTot}</div></div>
      </div>
      {$promoBlock}
    </div>
    <div class='sc-footer-grad'></div>
  </div>
</div>
</body></html>
HTML;

    return ['html_normal' => $html_normal, 'html_locked' => $html_locked];
}

// ============================================================
// 🛡️ SAFE SINGLE — Card compacte format "carte bancaire"
// V1 : même style que Fun, adapté pour un seul bet Safe
// ============================================================
function generateSafeCards($d) {
    $sport       = strtolower(trim($d['sport'] ?? 'football'));
    $isTennis    = ($sport === 'tennis');
    $isBaseball  = ($sport === 'baseball');
    $isFun       = false; // Safe = pas fun

    if ($isTennis) {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-tennis.png';
    } elseif ($isBaseball) {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte-mlb.png';
    } else {
        $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte.png';
    }
    $logo = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';

    $date        = htmlspecialchars($d['date_fr']     ?? '', ENT_QUOTES, 'UTF-8', false);
    $time        = htmlspecialchars($d['time_fr']     ?? '00:00', ENT_QUOTES, 'UTF-8', false);
    $match       = htmlspecialchars($d['match']       ?? '', ENT_QUOTES, 'UTF-8', false);
    $heure       = htmlspecialchars($d['heure']       ?? $d['time_fr'] ?? '', ENT_QUOTES, 'UTF-8', false);
    $competition = htmlspecialchars($d['competition'] ?? '', ENT_QUOTES, 'UTF-8', false);
    $flag1       = $d['flag1'] ?? '';
    $flag2       = $d['flag2'] ?? '';
    $logo1       = htmlspecialchars($d['team1_logo']  ?? '', ENT_QUOTES, 'UTF-8', false);
    $logo2       = htmlspecialchars($d['team2_logo']  ?? '', ENT_QUOTES, 'UTF-8', false);
    $prono       = htmlspecialchars($d['prono']       ?? '', ENT_QUOTES, 'UTF-8', false);
    $cote        = htmlspecialchars($d['cote']        ?? '1.00', ENT_QUOTES, 'UTF-8', false);
    $conf        = intval($d['confidence'] ?? 65);
    $valuePct    = floatval($d['value_pct'] ?? 0);
    $analyse     = htmlspecialchars($d['analyse']     ?? '', ENT_QUOTES, 'UTF-8', false);

    // Sport badge
    $sportEmojis = ['football'=>'⚽','tennis'=>'🎾','basket'=>'🏀','hockey'=>'🏒','baseball'=>'⚾'];
    $sportLabels = ['football'=>'FOOTBALL','tennis'=>'TENNIS','basket'=>'BASKET','hockey'=>'HOCKEY','baseball'=>'BASEBALL'];
    $sportEmoji  = $sportEmojis[$sport] ?? '⚽';
    $sportLabel  = $sportLabels[$sport] ?? strtoupper($sport);

    // Couleurs
    if ($isTennis) {
        $accentMain  = '#39ff14';
        $accentSec   = '#00d46a';
        $confBarGrad = 'linear-gradient(to right,#39ff14,#00d46a,#00c896)';
        $badgeBg     = 'rgba(57,255,20,0.12)';
        $badgeBorder = 'rgba(57,255,20,0.5)';
        $badgeColor  = '#39ff14';
        $footerGrad  = 'linear-gradient(to right,#39ff14,#00d46a,#00c896)';
        $promoClass  = 'promo-tennis';
    } else {
        $accentMain  = '#ff2d7a';
        $accentSec   = '#c850c0';
        $confBarGrad = 'linear-gradient(to right,#ff2d7a,#c850c0,#ff8c42)';
        $badgeBg     = 'rgba(255,45,122,0.1)';
        $badgeBorder = 'rgba(255,45,122,0.45)';
        $badgeColor  = '#ff2d7a';
        $footerGrad  = 'linear-gradient(to right,#ff2d7a,#c850c0,#4158d0)';
        $promoClass  = 'promo-multi';
    }

    // Team logos HTML — use flagImg() for proper flag rendering
    $flag1Img = flagImg($flag1);
    $flag2Img = flagImg($flag2);
    $logo1Html = ($logo1 && filter_var($logo1, FILTER_VALIDATE_URL)) ? "<img src='{$logo1}' class='team-logo' onerror=\"this.style.display='none'\">" : "<div class='flag-wrap'>{$flag1Img}</div>";
    $logo2Html = ($logo2 && filter_var($logo2, FILTER_VALIDATE_URL)) ? "<img src='{$logo2}' class='team-logo' onerror=\"this.style.display='none'\">" : "<div class='flag-wrap'>{$flag2Img}</div>";

    // Teams split
    $teams = preg_split('/\s+vs\.?\s+/i', $match, 2);
    $team1Name = htmlspecialchars(trim($teams[0] ?? ''), ENT_QUOTES, 'UTF-8', false);
    $team2Name = htmlspecialchars(trim($teams[1] ?? ''), ENT_QUOTES, 'UTF-8', false);

    // Value badge
    $valueHtml = ($valuePct > 0) ? "<span class='value-badge'>VALUE +{$valuePct}%</span>" : '';

    // Promo banner (same as SafeCombi)
    if ($isTennis) {
        $promoBlock = "<div class='promo-banner promo-tennis'><div class='promo-inner'><span class='promo-icon'>🎾</span><div class='promo-text'><span class='promo-title'>TENNIS PRO — PACK ATP / WTA</span><span class='promo-sub'>15€/sem · Analyses exclusives</span></div><span class='promo-btn'>Je m'abonne</span></div></div>";
    } else {
        $promoBlock = "<div class='promo-banner promo-multi'><div class='promo-inner'><span class='promo-icon'>🏆</span><div class='promo-text'><span class='promo-title'>STRATEDGE PRONOS</span><span class='promo-sub'>Daily 4,50€ · Week-End 10€ · Weekly 20€ · VIP MAX 50€/mois</span></div><span class='promo-btn'>Je m'abonne</span></div></div>";
    }

    $embeddedFonts = getLocalFontsCss();

    $css = $embeddedFonts . "\n@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Bebas+Neue&family=Rajdhani:wght@400;600;700&display=swap');\n" . <<<CSS

* { margin:0; padding:0; box-sizing:border-box; }
html { background:#0a0a0a !important; width:1080px; min-width:1080px; max-width:1080px; overflow-x:hidden; }
body { background:#0a0a0a !important; margin:0; padding:0; width:1080px; min-width:1080px; max-width:1080px; font-family:'Orbitron',sans-serif; }

.safe-wrapper { position:relative; width:1080px; max-width:1080px; min-width:1080px; background:#0a0a0a; }
.safe-glow {
  position:absolute; inset:-2px; border-radius:24px;
  background:linear-gradient(135deg,{$accentMain},{$accentSec},{$accentMain});
  background-size:300% 300%; animation:safeGlow 4s ease infinite;
  z-index:-1; filter:blur(10px); opacity:0.6;
}
@keyframes safeGlow { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.safe-card {
  position:relative; z-index:1; width:1080px; background:#0a0a0a;
  border-radius:20px; overflow:hidden; display:flex; flex-direction:column;
  border:1px solid rgba(255,45,122,0.08); isolation:isolate;
}

.safe-mascotte {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index:1; pointer-events:none;
}
.safe-mascotte img {
  height:100%; width:auto; max-height:100%; object-fit:contain;
  opacity:0.12; background:none !important;
}

.safe-body { position:relative; z-index:2; padding:28px 36px 24px; display:flex; flex-direction:column; gap:14px; }

/* HEADER */
.safe-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:40px; object-fit:contain; }
.safe-badge {
  display:flex; align-items:center; gap:8px;
  background:{$badgeBg}; border:1.5px solid {$badgeBorder};
  border-radius:22px; padding:6px 20px;
  font-family:'Orbitron',sans-serif; font-size:17px; font-weight:900;
  color:{$badgeColor}; letter-spacing:2px; text-transform:uppercase;
  box-shadow:0 0 10px {$badgeBg}; text-shadow:0 0 8px {$badgeBg};
}
.sport-chip {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
  border-radius:16px; padding:4px 14px;
  font-family:'Rajdhani',sans-serif; font-size:13px; font-weight:700;
  color:#fff; letter-spacing:1.5px;
}

/* DATE / HEURE */
.safe-datetime { display:flex; align-items:center; gap:16px; }
.safe-time {
  font-family:'Orbitron',sans-serif; font-size:52px; font-weight:900;
  color:#fff; line-height:1; letter-spacing:2px;
}
.safe-date-col { display:flex; flex-direction:column; }
.safe-date {
  font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:600;
  color:rgba(255,255,255,0.5); letter-spacing:1px;
}
.safe-competition {
  font-family:'Rajdhani',sans-serif; font-size:15px; font-weight:600;
  color:{$accentMain}; letter-spacing:1px; text-transform:uppercase;
}

/* MATCH */
.safe-match {
  display:flex; align-items:center; justify-content:center; gap:32px;
  padding:24px 0; margin:4px 0;
  background:transparent; border-radius:14px;
  border:1px solid rgba(255,255,255,0.06);
}
.team-col { display:flex; flex-direction:column; align-items:center; gap:8px; min-width:180px; }
.team-logo { height:72px; width:72px; object-fit:contain; filter:drop-shadow(0 0 8px rgba(255,255,255,0.15)); }
.team-flag { font-size:48px; }
.flag-wrap { display:flex; align-items:center; justify-content:center; }
.flag-wrap img { height:48px !important; width:auto !important; border-radius:4px !important; filter:drop-shadow(0 0 6px rgba(255,255,255,0.2)); }
.team-name {
  font-family:'Orbitron',sans-serif; font-size:24px; font-weight:900;
  color:#fff; text-align:center; letter-spacing:1px; max-width:280px;
  text-shadow:0 0 12px rgba(255,255,255,0.1);
}
.vs-text {
  font-family:'Bebas Neue',sans-serif; font-size:36px; font-weight:400;
  color:rgba(255,255,255,0.15); letter-spacing:5px;
}

/* PRONO + COTE */
.safe-prono-row {
  display:flex; align-items:center; justify-content:space-between; gap:16px;
  padding:16px 20px; background:rgba(255,255,255,0.03);
  border-radius:12px; border:1px solid rgba(255,255,255,0.06);
}
.prono-text {
  font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700;
  color:#fff; letter-spacing:0.5px; flex:1;
}
.cote-box {
  display:flex; flex-direction:column; align-items:center;
  background:rgba(255,255,255,0.06); border:1.5px solid {$accentMain};
  border-radius:12px; padding:10px 24px; min-width:100px;
}
.cote-label {
  font-family:'Rajdhani',sans-serif; font-size:11px; font-weight:600;
  color:rgba(255,255,255,0.4); letter-spacing:2px; text-transform:uppercase;
}
.cote-val {
  font-family:'Orbitron',sans-serif; font-size:32px; font-weight:900;
  color:{$accentMain}; line-height:1;
}
.value-badge {
  display:inline-block; margin-left:10px;
  background:rgba(57,255,20,0.12); border:1px solid rgba(57,255,20,0.4);
  border-radius:6px; padding:3px 10px;
  font-family:'Orbitron',sans-serif; font-size:11px; font-weight:700;
  color:#39ff14; letter-spacing:1px;
}

/* ANALYSE */
.safe-analyse {
  font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:500;
  color:rgba(255,255,255,0.55); line-height:1.5; padding:4px 4px;
  font-style:italic;
}

/* CONFIDENCE */
.safe-conf-row { display:flex; align-items:center; gap:16px; }
.conf-label {
  font-family:'Orbitron',sans-serif; font-size:11px; font-weight:700;
  color:rgba(255,255,255,0.4); letter-spacing:2px; text-transform:uppercase;
  white-space:nowrap;
}
.conf-bar-bg {
  flex:1; height:14px; background:rgba(255,255,255,0.06);
  border-radius:7px; overflow:hidden; position:relative;
}
.conf-bar-fill {
  height:100%; border-radius:7px;
  background:{$confBarGrad};
  transition:width 0.6s;
}
.conf-val {
  font-family:'Orbitron',sans-serif; font-size:18px; font-weight:900;
  color:{$accentMain}; min-width:55px; text-align:right;
}

/* PROMO */
.promo-banner {
  margin-top:4px; padding:12px 20px; border-radius:12px;
  display:flex; align-items:center;
}
.promo-tennis { background:linear-gradient(135deg,rgba(57,255,20,0.08),rgba(0,212,106,0.05)); border:1px solid rgba(57,255,20,0.2); }
.promo-multi { background:linear-gradient(135deg,rgba(255,45,120,0.08),rgba(200,80,192,0.05)); border:1px solid rgba(255,45,120,0.2); }
.promo-inner { display:flex; align-items:center; gap:14px; width:100%; }
.promo-icon { font-size:24px; }
.promo-text { flex:1; display:flex; flex-direction:column; }
.promo-title { font-family:'Orbitron',sans-serif; font-size:12px; font-weight:900; color:#fff; letter-spacing:2px; }
.promo-sub { font-family:'Rajdhani',sans-serif; font-size:13px; color:rgba(255,255,255,0.45); margin-top:2px; }
.promo-btn {
  font-family:'Orbitron',sans-serif; font-size:11px; font-weight:900;
  color:#fff; background:{$accentMain}; border-radius:8px;
  padding:8px 18px; letter-spacing:1.5px; text-transform:uppercase;
  white-space:nowrap;
}

/* FOOTER */
.safe-footer-grad { height:5px; background:{$footerGrad}; }

/* LOCKED */
.locked-overlay {
  position:absolute; inset:0; z-index:10;
  background:rgba(5,4,8,0.65); backdrop-filter:blur(6px);
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px;
}
.lock-icon { font-size:48px; }
.lock-text {
  font-family:'Orbitron',sans-serif; font-size:14px; font-weight:900;
  color:{$accentMain}; letter-spacing:3px; text-transform:uppercase;
  text-shadow:0 0 12px {$accentMain};
}
.lock-sub {
  font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:600;
  color:rgba(255,255,255,0.5);
}
CSS;

    // ── HTML NORMAL ──────────────────────────────────────────
    $html_normal = <<<HTML
<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=1080'>
<style>{$css}</style></head><body>
<div class='safe-wrapper'>
  <div class='safe-glow'></div>
  <div class='safe-card'>
    <div class='safe-mascotte'><img src='{$mascotteUrl}' alt=''></div>
    <div class='safe-body'>
      <div class='safe-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div style='display:flex;gap:10px;align-items:center;'>
          <span class='sport-chip'>{$sportEmoji} {$sportLabel}</span>
          <span class='safe-badge'>🛡️ SAFE BET</span>
        </div>
      </div>
      <div class='safe-datetime'>
        <span class='safe-time'>{$time}</span>
        <div class='safe-date-col'>
          <span class='safe-date'>{$date}</span>
          <span class='safe-competition'>{$competition}</span>
        </div>
      </div>
      <div class='safe-match'>
        <div class='team-col'>{$logo1Html}<span class='team-name'>{$team1Name}</span></div>
        <span class='vs-text'>VS</span>
        <div class='team-col'>{$logo2Html}<span class='team-name'>{$team2Name}</span></div>
      </div>
      <div class='safe-prono-row'>
        <span class='prono-text'>{$prono}{$valueHtml}</span>
        <div class='cote-box'>
          <span class='cote-label'>COTE</span>
          <span class='cote-val'>{$cote}</span>
        </div>
      </div>
      <div class='safe-analyse'>{$analyse}</div>
      <div class='safe-conf-row'>
        <span class='conf-label'>Confiance</span>
        <div class='conf-bar-bg'><div class='conf-bar-fill' style='width:{$conf}%'></div></div>
        <span class='conf-val'>{$conf}%</span>
      </div>
      {$promoBlock}
    </div>
    <div class='safe-footer-grad'></div>
  </div>
</div>
</body></html>
HTML;

    // ── HTML LOCKED ──────────────────────────────────────────
    $html_locked = <<<HTML
<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><meta name='viewport' content='width=1080'>
<style>{$css}</style></head><body>
<div class='safe-wrapper'>
  <div class='safe-glow'></div>
  <div class='safe-card'>
    <div class='safe-mascotte'><img src='{$mascotteUrl}' alt='' style='opacity:0.08'></div>
    <div class='safe-body'>
      <div class='safe-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div style='display:flex;gap:10px;align-items:center;'>
          <span class='sport-chip'>{$sportEmoji} {$sportLabel}</span>
          <span class='safe-badge'>🛡️ SAFE BET</span>
        </div>
      </div>
      <div class='safe-datetime'>
        <span class='safe-time'>{$time}</span>
        <div class='safe-date-col'>
          <span class='safe-date'>{$date}</span>
          <span class='safe-competition'>{$competition}</span>
        </div>
      </div>
      <div class='safe-match'>
        <div class='team-col'>{$logo1Html}<span class='team-name'>{$team1Name}</span></div>
        <span class='vs-text'>VS</span>
        <div class='team-col'>{$logo2Html}<span class='team-name'>{$team2Name}</span></div>
      </div>
      <div class='safe-prono-row'>
        <span class='prono-text'>🔒</span>
        <div class='cote-box'>
          <span class='cote-label'>COTE</span>
          <span class='cote-val'>{$cote}</span>
        </div>
      </div>
      <div class='safe-conf-row'>
        <span class='conf-label'>Confiance</span>
        <div class='conf-bar-bg'><div class='conf-bar-fill' style='width:{$conf}%'></div></div>
        <span class='conf-val'>{$conf}%</span>
      </div>
      <div class='locked-overlay'>
        <span class='lock-icon'>🔒</span>
        <span class='lock-text'>Prono verrouillé</span>
        <span class='lock-sub'>Abonne-toi pour débloquer</span>
      </div>
      {$promoBlock}
    </div>
    <div class='safe-footer-grad'></div>
  </div>
</div>
</body></html>
HTML;

    return ['html_normal' => $html_normal, 'html_locked' => $html_locked];
}
