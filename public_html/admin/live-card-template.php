<?php
// ============================================================
// STRATEDGE — live-card-template.php V10
// V11 : résolution 1440px + CSS netteté (font-smoothing, text-rendering)
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
    $proxyHosts = ['upload.wikimedia.org', 'commons.wikimedia.org', 'en.wikipedia.org'];
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
// SPORT CONFIG — couleurs, mascotte, badge
// ────────────────────────────────────────────────────────────
function sportConfig($sport) {
    $sport = strtolower(trim($sport));
    if ($sport === 'tennis') {
        return [
            'emoji'        => '🎾',
            'label'        => 'Tennis',
            'pack'         => 'Tennis Pro',
            'mascotte_url'   => 'https://stratedgepronos.fr/assets/images/mascotte-tennis.png?v=1',
            // Tennis : mascotte-tennis.png bien visible en fond (watermark)
            'mascotte_style' => "opacity:0.58; background:none !important; height:100%; width:auto; max-width:85%; object-fit:contain; object-position:center center;",
            'mascotte_locked'=> "opacity:0.35; background:none !important; height:100%; width:auto; max-width:85%; object-fit:contain; object-position:center center;",
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
    return [
        'emoji'        => $s[0],
        'label'        => $s[1],
        'pack'         => $s[2],
        'mascotte_url'   => 'https://stratedgepronos.fr/assets/images/mascotte.png',
        // Autres sports : fond non-noir → opacity simple sans blend mode (évite le bave violet)
        'mascotte_style' => "opacity:0.12;",
        'mascotte_locked'=> "opacity:0.06;",
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
    $date   = htmlspecialchars($d['date_fr']    ?? '', ENT_QUOTES, 'UTF-8');
    $time   = htmlspecialchars($d['time_fr']    ?? '00:00', ENT_QUOTES, 'UTF-8');
    $p1     = htmlspecialchars($d['player1']    ?? 'Joueur 1', ENT_QUOTES, 'UTF-8');
    $p2     = htmlspecialchars($d['player2']    ?? 'Joueur 2', ENT_QUOTES, 'UTF-8');
    $comp   = htmlspecialchars($d['competition']?? '', ENT_QUOTES, 'UTF-8');
    $prono  = htmlspecialchars($d['prono']      ?? '', ENT_QUOTES, 'UTF-8');
    $cote   = htmlspecialchars($d['cote']       ?? '1.50', ENT_QUOTES, 'UTF-8');
    $conf   = intval($d['confidence'] ?? 70);
    $flag1  = flagImg($d['flag1'] ?? '');
    $flag2  = flagImg($d['flag2'] ?? '');
    $logo1_url = trim($d['team1_logo'] ?? '');
    $logo2_url = trim($d['team2_logo'] ?? '');
    $is_team_sport = in_array($sport, ['football', 'basket', 'hockey']);
    if ($is_team_sport) {
        if ($logo1_url === '' || !filter_var($logo1_url, FILTER_VALIDATE_URL)) {
            $logo1_url = function_exists('stratedge_fetch_team_logo_url') ? stratedge_fetch_team_logo_url($d['player1'] ?? '') : '';
        }
        if ($logo2_url === '' || !filter_var($logo2_url, FILTER_VALIDATE_URL)) {
            $logo2_url = function_exists('stratedge_fetch_team_logo_url') ? stratedge_fetch_team_logo_url($d['player2'] ?? '') : '';
        }
    }
    if ($is_team_sport && $logo1_url !== '' && filter_var($logo1_url, FILTER_VALIDATE_URL)) {
        $team1_display = '<img src="' . htmlspecialchars(logoProxyUrl($logo1_url), ENT_QUOTES, 'UTF-8') . '" class="team-logo" alt="">';
    } else {
        $team1_display = $flag1;
    }
    if ($is_team_sport && $logo2_url !== '' && filter_var($logo2_url, FILTER_VALIDATE_URL)) {
        $team2_display = '<img src="' . htmlspecialchars(logoProxyUrl($logo2_url), ENT_QUOTES, 'UTF-8') . '" class="team-logo" alt="">';
    } else {
        $team2_display = $flag2;
    }
    $logo   = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';
    $pronoJoueur = (int)($d['prono_joueur'] ?? 1);
    $p1_class = ($pronoJoueur === 1) ? 'player main' : 'player opponent';
    $p2_class = ($pronoJoueur === 2) ? 'player main' : 'player opponent';

    $embeddedFonts = getLocalFontsCss();
    $css = $embeddedFonts . <<<CSS

/* Netteté : rendu texte plus net (antialiasing, legibility) */
* { margin:0; padding:0; box-sizing:border-box; }
body {
  background:#0a0a0a; margin:0; padding:0; width:1440px; font-family:'Orbitron',sans-serif;
  -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
}

.card-wrapper { position:relative; width:1440px; }

/* Glow extérieur — z-index:-1 pour ne jamais créer de voile */
.border-glow {
  position:absolute; inset:-2px; border-radius:24px;
  background:{$sc['glow_gradient']};
  background-size:300% 300%; animation:gradientShift 4s ease infinite;
  z-index:-1; filter:blur(8px); opacity:0.8;
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.card {
  position:relative; z-index:1; width:1440px; background:#0d0d0f;
  border-radius:20px; overflow:hidden; display:flex; flex-direction:column;
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
.card-body { position:relative; z-index:2; padding:37px 48px 29px; display:flex; flex-direction:column; gap:19px; }

.card-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:50px; object-fit:contain; }
.sport-badge {
  display:flex; align-items:center; gap:8px;
  background:{$sc['badge_bg']}; border:1.5px solid {$sc['badge_border']};
  border-radius:24px; padding:5px 20px;
  font-family:'Orbitron',sans-serif; font-size:18px; font-weight:700;
  color:{$sc['badge_color']}; letter-spacing:1px; text-transform:uppercase;
  box-shadow:{$sc['badge_shadow']}; text-shadow:{$sc['badge_tshadow']};
}

.datetime-block { text-align:center; padding:4px 0; }
.datetime-day { font-family:'Orbitron',sans-serif; font-size:21px; font-weight:600; color:rgba(255,255,255,0.35); text-transform:uppercase; letter-spacing:3px; margin-bottom:4px; }
.datetime-time { font-family:'Orbitron',sans-serif; font-size:72px; font-weight:900; letter-spacing:4px; line-height:1; color:{$sc['time_color']}; text-shadow:{$sc['time_shadow']}; }

.match-block { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:18px; padding:19px 29px; position:relative; }
.match-left-bar { position:absolute; left:0; top:0; bottom:0; width:5px; background:linear-gradient(to bottom,#ff2d7a,#00e5ff); border-radius:5px 0 0 5px; }
.live-badge { display:flex; align-items:center; gap:8px; justify-content:center; margin-bottom:10px; font-family:'Orbitron',sans-serif; font-size:16px; font-weight:700; color:#ff2d7a; letter-spacing:2px; text-transform:uppercase; }
.live-dot { width:13px; height:13px; border-radius:50%; background:#ff2d7a; box-shadow:0 0 10px #ff2d7a; animation:blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }
.match-players { display:flex; align-items:center; justify-content:center; gap:27px; }
.player-info { display:flex; align-items:center; gap:11px; }
.player { font-family:'Bebas Neue',cursive; font-size:42px; letter-spacing:1px; line-height:1; }
.player.main { color:#fff; }
.player.opponent { color:rgba(255,255,255,0.5); }
.vs-badge { font-family:'Orbitron',sans-serif; font-size:21px; font-weight:900; color:#ff2d7a; }
.match-comp { font-family:'Orbitron',sans-serif; font-size:15px; color:rgba(255,255,255,0.3); text-align:center; margin-top:8px; letter-spacing:1px; }

.prono-block { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:18px; padding:19px 29px; display:flex; align-items:center; justify-content:space-between; gap:27px; }
.prono-left { flex:1; }
.prono-label { font-size:16px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:2px; margin-bottom:7px; font-weight:600; }
.prono-text {
  font-family:'Orbitron',sans-serif; font-weight:700; font-size:24px;
  color: #ff2d7a;
}
.cote-block { text-align:center; flex-shrink:0; }
.cote-label { font-size:16px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:2px; margin-bottom:8px; font-weight:600; }
.cote-pill { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#ff2d7a 0%,#c850c0 45%,#4158d0 100%); border-radius:18px; padding:16px 40px; min-width:160px; box-shadow:0 4px 20px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12); }
.cote-pill-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.13); border-radius:18px 18px 0 0; }
.cote-value { font-family:'Orbitron',sans-serif; font-size:45px; font-weight:900; color:#fff; position:relative; z-index:1; }

.confidence-section { display:flex; align-items:center; gap:19px; }
.confidence-label { font-size:17px; color:rgba(255,255,255,0.32); text-transform:uppercase; letter-spacing:1.5px; font-weight:600; white-space:nowrap; flex-shrink:0; }
.confidence-bar-bg { flex:1; height:10px; background:rgba(255,255,255,0.06); border-radius:12px; overflow:hidden; }
.confidence-bar-fill { height:100%; width:{$conf}%; background:{$sc['conf_gradient']}; border-radius:12px; animation:barPulse 2s ease-in-out infinite; }
@keyframes barPulse { 0%,100%{opacity:1} 50%{opacity:0.8} }
.confidence-score { font-family:'Orbitron',sans-serif; font-size:23px; font-weight:700; color:{$sc['conf_color']}; flex-shrink:0; text-shadow:{$sc['conf_shadow']}; }

.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18); border-radius:18px; padding:19px 24px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:19px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:5px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:5px 0 0 5px; }
.promo-text-block { flex:1; padding-left:13px; display:flex; flex-direction:column; gap:4px; }
.promo-eyebrow { font-size:15px; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; font-family:'Orbitron',sans-serif; }
.promo-main { font-family:'Bebas Neue',cursive; font-size:27px; letter-spacing:0.8px; color:#fff; }
.promo-main-hl { color:{$sc['promo_price_color']}; }
.promo-sub { font-size:13px; color:rgba(255,255,255,0.35); font-weight:500; font-family:'Orbitron',sans-serif; }
.promo-sub span { color:{$sc['promo_price_color']}; font-weight:700; }
.promo-cta { display:inline-flex; align-items:center; gap:7px; background:linear-gradient(135deg,#39ff14,#00c896); color:#000; font-family:'Orbitron',sans-serif; font-size:15px; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:13px 21px; border-radius:12px; white-space:nowrap; box-shadow:0 0 14px rgba(57,255,20,0.4); }

/* Locked */
.locked-zone { text-align:center; padding:19px 0; }
.locked-padlock { font-size:74px; line-height:1; }
.locked-reserved { font-family:'Orbitron',sans-serif; font-size:19px; color:#ff2d7a; opacity:0.7; letter-spacing:2px; margin:11px 0; }
.locked-cta-btn { background:linear-gradient(135deg,#FF2D78,#d6245f); color:white; font-family:'Orbitron',sans-serif; font-size:19px; font-weight:700; padding:13px 40px; border-radius:14px; display:inline-block; letter-spacing:1px; }
.locked-cote-center { text-align:center; margin:8px 0; }

.card-footer-gradient { height:5px; background:{$sc['footer_gradient']}; position:relative; z-index:2; }
CSS;
    // Overrides Tennis Live uniquement (réf. visuelle : fond #0A0A0A, cote rose→violet, offre #1A361A)
    if ($is_tennis) {
        $css .= <<<TENNIS

/* Tennis Live — couleurs et offre alignées sur la maquette — polices Orbitron, pas de vert sur prono */
.card-wrapper.tennis .border-glow { background:linear-gradient(135deg,#E7337B,#7D41E7,#E7337B) !important; }
.card-wrapper.tennis .card { background:#0d0d0f !important; border-color:rgba(255,255,255,0.08) !important; }
.card-wrapper.tennis .mascotte-watermark { background:transparent !important; z-index:0; pointer-events:none; display:flex !important; align-items:center; justify-content:center; }
.card-wrapper.tennis .mascotte-watermark img { background:none !important; box-shadow:none !important; max-height:100%; width:auto; max-width:85%; object-fit:contain; object-position:center center; }
.card-wrapper.tennis .match-left-bar { background:linear-gradient(to bottom,#E7337B,#7D41E7); }
.card-wrapper.tennis .match-block { background:transparent !important; border-color:rgba(255,255,255,0.06); }
.card-wrapper.tennis .live-badge { color:#ff2d7a !important; }
.card-wrapper.tennis .live-dot { background:#ff2d7a !important; box-shadow:0 0 6px #ff2d7a; }
.card-wrapper.tennis .vs-badge { background:none !important; background-image:none !important; box-shadow:none !important; border:none !important; padding:0 !important; margin:0 !important; }
.card-wrapper.tennis .vs-badge svg { display:block; }
.card-wrapper.tennis .player,
.card-wrapper.tennis .match-players .player { font-family:'Orbitron',sans-serif !important; font-weight:700 !important; font-size:38px !important; letter-spacing:1px !important; }
.card-wrapper.tennis .promo-main,
.card-wrapper.tennis .promo-text-block .promo-main { font-family:'Orbitron',sans-serif !important; font-weight:700 !important; font-size:26px !important; letter-spacing:0.8px !important; }
.card-wrapper.tennis .promo-sub,
.card-wrapper.tennis .promo-text-block .promo-sub { font-family:'Orbitron',sans-serif !important; font-size:14px !important; font-weight:500 !important; }
.card-wrapper.tennis .prono-block,
.prono-block-tennis-dark { background:#0d0d0f !important; background-color:#0d0d0f !important; background-image:none !important; background-blend-mode:normal !important; box-shadow:none !important; border:1px solid rgba(255,255,255,0.07) !important; }
.card-wrapper.tennis .prono-block::before,
.card-wrapper.tennis .prono-block::after,
.prono-block-tennis-dark::before,
.prono-block-tennis-dark::after { display:none !important; background:none !important; background-image:none !important; }
.card-wrapper.tennis .prono-block,
.prono-block-tennis-dark { padding:24px 32px !important; min-height:72px !important; }
.card-wrapper.tennis .prono-label { font-size:18px !important; color:rgba(255,255,255,0.5) !important; margin-bottom:10px !important; }
.card-wrapper.tennis .prono-text { color:#fff !important; font-size:34px !important; font-weight:800 !important; font-family:'Orbitron',sans-serif !important; line-height:1.25 !important; letter-spacing:0.5px !important; }
.card-wrapper.tennis .cote-pill { background:linear-gradient(135deg,#E7337B 0%,#7D41E7 100%); box-shadow:0 4px 16px rgba(231,51,123,0.35); }
.card-wrapper.tennis .cote-pill-shine { display:none !important; }
.card-wrapper.tennis .cote-value { background:transparent !important; box-shadow:none !important; }
.card-wrapper.tennis .promo-banner { background:#1A361A; border:1px solid rgba(57,255,20,0.35); }
.card-wrapper.tennis .promo-eyebrow { color:#39ff14; }
.card-wrapper.tennis .promo-cta { background:linear-gradient(135deg,#39ff14,#00c896); color:#000; box-shadow:0 0 14px rgba(57,255,20,0.5); }
.card-wrapper.tennis .locked-reserved { color:#39ff14; }
.card-wrapper.tennis .locked-cta-btn { background:linear-gradient(135deg,#39ff14,#00c896); color:#000 !important; font-family:'Orbitron',sans-serif !important; font-size:10px; font-weight:700; }
TENNIS;
    }
    if ($is_team_sport) {
        $css .= <<<TEAMSPORT

/* Team sport (foot, NBA, hockey) — logos + dégradé rose néon → bleu néon sur prono et VS */
.card-wrapper.team-sport .team-logo { height:34px; width:auto; max-width:54px; object-fit:contain; vertical-align:middle; }
.card-wrapper.team-sport .prono-text {
  background:linear-gradient(90deg,#E7337B 0%,#00e5ff 100%);
  -webkit-background-clip:text; background-clip:text;
  -webkit-text-fill-color:transparent; color:transparent;
  font-family:'Orbitron',sans-serif; font-weight:700; font-size:17px;
}
.card-wrapper.team-sport .vs-badge {
  background:linear-gradient(90deg,#E7337B 0%,#00e5ff 100%);
  -webkit-background-clip:text; background-clip:text;
  -webkit-text-fill-color:transparent; color:transparent;
  font-family:'Orbitron',sans-serif; font-size:16px; font-weight:900;
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
        ? "<span class='vs-badge' style='display:inline-block;width:78px;height:46px;background:none!important;border:none;padding:0;margin:0;line-height:0;vertical-align:middle;'><svg xmlns='http://www.w3.org/2000/svg' width='78' height='46' viewBox='0 0 42 26' style='display:block;'><defs><linearGradient id='vsg' x1='0%' y1='0%' x2='100%' y2='0%'><stop offset='0%' style='stop-color:#E7337B'/><stop offset='100%' style='stop-color:#00e5ff'/></linearGradient></defs><text x='21' y='21' text-anchor='middle' fill='url(#vsg)' font-family='Orbitron,sans-serif' font-size='20' font-weight='900'>VS</text></svg></span>"
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
      <div class='prono-block" . ($is_tennis ? " prono-block-tennis-dark' style='background:#0d0d0f !important; background-color:#0d0d0f !important; background-image:none !important;'" : "'") . ">
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
    $mascotteUrl = 'https://stratedgepronos.fr/assets/images/mascotte.png';
    $logo        = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';

    $date    = htmlspecialchars($d['date_fr']    ?? '', ENT_QUOTES, 'UTF-8');
    $time    = htmlspecialchars($d['time_fr']    ?? '00:00', ENT_QUOTES, 'UTF-8');
    $coteTot = htmlspecialchars($d['cote_totale']?? '1.00', ENT_QUOTES, 'UTF-8');
    $conf    = intval($d['confidence'] ?? 65);
    $bets    = $d['bets'] ?? [];
    $nbBets  = count($bets);

    // Couleurs alternées pour les barres gauche
    $barColors = [
        'linear-gradient(to bottom,#ff2d7a,#c850c0)',
        'linear-gradient(to bottom,#c850c0,#4158d0)',
        'linear-gradient(to bottom,#4158d0,#00e5ff)',
    ];

    $embeddedFonts = getLocalFontsCss();
    $css = $embeddedFonts . "\n/* Fallback Google Fonts si embarquées absentes (iframe/srcdoc) */\n@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Bebas+Neue&display=swap');\n" . <<<CSS

/* Netteté : rendu texte plus net */
* { margin:0; padding:0; box-sizing:border-box; }
body {
  background:#0a0a0a; margin:0; padding:0; width:1440px; font-family:'Orbitron',sans-serif;
  -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
}

.card-wrapper { position:relative; width:1440px; }
.border-glow {
  position:absolute; inset:-2px; border-radius:28px;
  background:linear-gradient(135deg,#ff2d7a,#c850c0,#ff2d7a);
  background-size:300% 300%; animation:gradientShift 4s ease infinite;
  z-index:-1; filter:blur(12px); opacity:0.75;
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.card {
  position:relative; z-index:1; width:1440px; background:#0e0b12;
  border-radius:24px; overflow:hidden; display:flex; flex-direction:column;
  border:1px solid rgba(255,45,122,0.1); isolation:isolate;
}

/* MASCOTTE WATERMARK */
.mascotte-watermark {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  z-index:0; pointer-events:none;
}
.mascotte-watermark img {
  height:100%; width:auto; object-fit:contain;
  opacity:0.12;
}

.card-body { position:relative; z-index:2; padding:35px 43px 29px; display:flex; flex-direction:column; gap:16px; }

/* HEADER */
.card-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:45px; object-fit:contain; }
.funbet-badge {
  display:flex; align-items:center; gap:10px;
  background:rgba(255,45,122,0.1); border:1.5px solid rgba(255,45,122,0.45);
  border-radius:26px; padding:7px 24px;
  font-family:'Orbitron',sans-serif; font-size:18px; font-weight:900;
  color:#ff2d7a; letter-spacing:2px; text-transform:uppercase;
  box-shadow:0 0 10px rgba(255,45,122,0.2); text-shadow:0 0 8px rgba(255,45,122,0.5);
}

/* DATE / HEURE */
.datetime-block { text-align:center; padding:4px 0; }
.datetime-day { font-family:'Orbitron',sans-serif; font-size:20px; font-weight:600; color:rgba(255,255,255,0.32); text-transform:uppercase; letter-spacing:3px; margin-bottom:3px; }
.datetime-time { font-family:'Orbitron',sans-serif; font-size:58px; font-weight:900; letter-spacing:3px; line-height:1; color:#ff2d7a; text-shadow:0 0 25px rgba(255,45,122,0.5); }
.datetime-sub { font-size:16px; color:rgba(255,255,255,0.2); font-family:'Orbitron',sans-serif; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; margin-top:3px; }

/* SECTION TITLE */
.section-title { display:flex; align-items:center; gap:10px; }
.section-title-text { font-family:'Orbitron',sans-serif; font-size:16px; font-weight:700; color:rgba(255,45,122,0.6); text-transform:uppercase; letter-spacing:2.5px; white-space:nowrap; }
.section-title-line { flex:1; height:1px; background:linear-gradient(to right,rgba(255,45,122,0.3),transparent); }

/* LIGNES DE PARIS */
.bets-container { display:flex; flex-direction:column; gap:10px; }
.bet-line { background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.055); border-radius:12px; padding:14px 18px 14px 24px; position:relative; display:flex; flex-direction:column; gap:5px; }
.bet-left-bar { position:absolute; left:0; top:0; bottom:0; width:5px; border-radius:5px 0 0 5px; }
.bet-top-row { display:flex; align-items:center; justify-content:space-between; }
.bet-num-match { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.bet-num { font-family:'Orbitron',sans-serif; font-size:17px; color:rgba(255,140,200,0.6); }
.bet-match { font-family:'Orbitron',sans-serif; font-size:18px; color:rgba(255,255,255,0.5); letter-spacing:0.5px; font-weight:600; }
.bet-heure { font-family:'Orbitron',sans-serif; font-size:16px; color:rgba(255,45,122,0.85); font-weight:700; margin-left:8px; white-space:nowrap; }
.bet-line .fun-team-logo { height:26px; width:auto; max-width:36px; object-fit:contain; vertical-align:middle; }
.bet-cote-pill { background:rgba(255,45,122,0.08); border:1px solid rgba(255,45,122,0.2); border-radius:10px; padding:5px 14px; font-family:'Orbitron',sans-serif; font-size:22px; font-weight:700; color:#ff8c6b; }
.bet-prono { font-family:'Orbitron',sans-serif; font-size:18px; font-weight:700; color:rgba(255,255,255,0.9); }

/* CONFIANCE */
.confidence-col { display:flex; flex-direction:column; gap:6px; }
.conf-header { display:flex; justify-content:space-between; align-items:center; }
.conf-label { font-family:'Orbitron',sans-serif; font-size:16px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.conf-score { font-family:'Orbitron',sans-serif; font-size:24px; font-weight:900; color:#ff2d7a; text-shadow:0 0 8px rgba(255,45,122,0.3); }
.conf-bar-bg { height:10px; background:rgba(255,255,255,0.05); border-radius:5px; overflow:hidden; }
.conf-bar-fill { height:100%; width:{$conf}%; background:linear-gradient(to right,#ff2d7a,#c850c0,#ff8c42); border-radius:5px; animation:barPulse 2s ease-in-out infinite; }
@keyframes barPulse { 0%,100%{opacity:0.85} 50%{opacity:1} }

/* COTE TOTALE */
.cote-totale-block { display:flex; align-items:center; gap:24px; background:rgba(255,45,122,0.06); border:1px solid rgba(255,45,122,0.14); border-radius:18px; padding:16px 24px; }
.cote-total-info { flex:1; }
.cote-eyebrow { font-family:'Orbitron',sans-serif; font-size:15px; color:rgba(255,255,255,0.25); text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:4px; }
.cote-desc { font-family:'Orbitron',sans-serif; font-size:16px; color:rgba(255,255,255,0.45); letter-spacing:1px; }
.total-pill { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#ff2d7a 0%,#c850c0 50%,#4158d0 100%); border-radius:18px; padding:16px 42px; flex-shrink:0; box-shadow:0 4px 22px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12); }
.total-pill-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.13); border-radius:18px 18px 0 0; }
.total-cote { font-family:'Orbitron',sans-serif; font-size:45px; font-weight:900; color:#fff; letter-spacing:2px; position:relative; z-index:1; }

/* PROMO — Option Sport Daily, Week-end, Weekly (fonts forcées pour lisibilité) */
.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18); border-radius:18px; padding:19px 24px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:19px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:5px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:5px 0 0 5px; }
.promo-text-block { flex:1; padding-left:13px; display:flex; flex-direction:column; gap:7px; }
.promo-eyebrow { font-family:'Orbitron',sans-serif !important; font-size:17px !important; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.promo-main { font-family:'Bebas Neue','Orbitron',sans-serif !important; font-size:29px !important; letter-spacing:0.8px; color:#fff; line-height:1.25; min-height:1.25em; }
.promo-main-hl { color:#ff2d7a; font-family:inherit !important; }
.promo-packs { display:flex; gap:8px; flex-wrap:wrap; }
.pack-tag { font-family:'Orbitron',sans-serif !important; font-size:17px !important; font-weight:700; padding:7px 15px; border-radius:6px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.6); text-transform:uppercase; }
.pack-tag-max { color:#39ff14; border-color:rgba(57,255,20,0.3); background:rgba(57,255,20,0.07); }
.promo-price { font-family:'Orbitron',sans-serif !important; font-size:17px !important; color:rgba(255,255,255,0.55); }
.promo-price span { font-family:'Orbitron',sans-serif !important; font-size:26px !important; font-weight:700; color:#00e5ff; }
.promo-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0; }
.promo-cta { display:inline-flex; align-items:center; gap:7px; background:linear-gradient(135deg,#39ff14,#00c896); color:#000; font-family:'Orbitron',sans-serif !important; font-size:17px !important; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:13px 24px; border-radius:12px; white-space:nowrap; box-shadow:0 0 14px rgba(57,255,20,0.4); }

/* Locked */
.locked-zone { text-align:center; margin:8px 0; }
.locked-padlock { font-size:72px; line-height:1; }
.locked-reserved { font-family:'Orbitron',sans-serif; font-size:19px; color:#ff2d7a; opacity:0.7; letter-spacing:2px; margin:11px 0; }
.locked-cta-btn { background:linear-gradient(135deg,#FF2D78,#d6245f); color:white; font-family:'Orbitron',sans-serif; font-size:19px; font-weight:700; padding:13px 40px; border-radius:14px; display:inline-block; letter-spacing:1px; }

.card-footer-gradient { height:5px; background:linear-gradient(to right,#ff2d7a,#c850c0,#4158d0); position:relative; z-index:2; }
CSS;

    // ── Générer les lignes de paris ──
    $betLinesNormal = '';
    $betLinesLocked = '';
    foreach ($bets as $i => $bet) {
        $num    = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        $match  = htmlspecialchars($bet['match'] ?? '', ENT_QUOTES, 'UTF-8');
        $prono  = htmlspecialchars($bet['prono'] ?? '', ENT_QUOTES, 'UTF-8');
        $bcote  = htmlspecialchars($bet['cote']  ?? '1.00', ENT_QUOTES, 'UTF-8');
        $heure  = htmlspecialchars($bet['heure'] ?? $bet['time'] ?? '', ENT_QUOTES, 'UTF-8');
        $heureSpan = $heure !== '' ? "<span class='bet-heure'>{$heure}</span>" : '';

        $logo1Url = trim($bet['team1_logo'] ?? '');
        $logo2Url = trim($bet['team2_logo'] ?? '');
        $matchRaw = $bet['match'] ?? '';
        $matchParts = preg_split('/\s+vs\.?\s+/i', $matchRaw, 2);
        $team1Name = trim($matchParts[0] ?? '');
        $team2Name = trim($matchParts[1] ?? '');
        if (($logo1Url === '' || !filter_var($logo1Url, FILTER_VALIDATE_URL)) && $team1Name !== '' && function_exists('stratedge_fetch_team_logo_url')) {
            $logo1Url = stratedge_fetch_team_logo_url($team1Name);
        }
        if (($logo2Url === '' || !filter_var($logo2Url, FILTER_VALIDATE_URL)) && $team2Name !== '' && function_exists('stratedge_fetch_team_logo_url')) {
            $logo2Url = stratedge_fetch_team_logo_url($team2Name);
        }
        if ($logo1Url !== '' && filter_var($logo1Url, FILTER_VALIDATE_URL)) {
            $ico1 = '<img src="' . htmlspecialchars(logoProxyUrl($logo1Url), ENT_QUOTES, 'UTF-8') . '" class="fun-team-logo" alt="">';
        } else {
            $ico1 = flagImg($bet['flag1'] ?? '');
        }
        if ($logo2Url !== '' && filter_var($logo2Url, FILTER_VALIDATE_URL)) {
            $ico2 = '<img src="' . htmlspecialchars(logoProxyUrl($logo2Url), ENT_QUOTES, 'UTF-8') . '" class="fun-team-logo" alt="">';
        } else {
            $ico2 = flagImg($bet['flag2'] ?? '');
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

    $promoBlock = <<<HTML
  <div class='promo-banner'>
    <div class='promo-left-bar'></div>
    <div class='promo-text-block'>
      <div class='promo-eyebrow'>🚀 Option Fun Bet — En supplément de vos packs</div>
      <div class='promo-main'>Option dans <span class='promo-main-hl'>Sport Daily, Week-end &amp; Weekly</span> · Inclus MAX</div>
      <div class='promo-packs'>
        <span class='pack-tag'>🌅 Sport Daily</span>
        <span class='pack-tag'>📅 Week-end</span>
        <span class='pack-tag'>📆 Weekly</span>
        <span class='pack-tag pack-tag-max'>✅ Inclus MAX</span>
      </div>
      <div class='promo-price'>En option dès <span>1.50€/jour</span></div>
    </div>
    <div class='promo-right'>
      <div class='promo-cta'>⚡ Je m'abonne</div>
    </div>
  </div>
HTML;

    // CARD NORMALE
    $html_normal = <<<HTML
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>

<style>{$css}</style>
</head>
<body>
<div class='card-wrapper'>
  <div class='border-glow'></div>
  <div class='card'>
    <div class='mascotte-watermark'>
      <img src='{$mascotteUrl}' alt=''>
    </div>
    <div class='card-body'>
      <div class='card-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div class='funbet-badge'>⚡ Fun Bet</div>
      </div>
      <div class='datetime-block'>
        <div class='datetime-day'>{$date}</div>
        <div class='datetime-time'>{$time}</div>
        <div class='datetime-sub'>heure du 1er match</div>
      </div>
      <div class='section-title'>
        <span class='section-title-text'>⚡ Sélection multi-paris</span>
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
<div class='card-wrapper'>
  <div class='border-glow'></div>
  <div class='card'>
    <div class='mascotte-watermark'>
      <img src='{$mascotteUrl}' alt='' style='opacity:0.06'>
    </div>
    <div class='card-body'>
      <div class='card-header'>
        <img src='{$logo}' class='logo-img' alt='StratEdge'>
        <div class='funbet-badge'>⚡ Fun Bet</div>
      </div>
      <div class='datetime-block'>
        <div class='datetime-day'>{$date}</div>
        <div class='datetime-time'>{$time}</div>
        <div class='datetime-sub'>heure du 1er match</div>
      </div>
      <div class='section-title'>
        <span class='section-title-text'>⚡ Sélection multi-paris</span>
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
