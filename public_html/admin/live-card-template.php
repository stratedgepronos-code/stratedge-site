<?php
// ============================================================
// STRATEDGE — live-card-template.php V9
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
            // Tennis : mascotte-tennis.png, fond transparent (opacity seule)
            'mascotte_style' => "opacity:0.42; background:none !important; height:100%; width:auto; object-fit:contain;",
            'mascotte_locked'=> "opacity:0.22; background:none !important; height:100%; width:auto; object-fit:contain;",
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
    $logo   = 'https://stratedgepronos.fr/assets/images/logo_site_transparent.png';

    $embeddedFonts = getLocalFontsCss();
    $css = $embeddedFonts . <<<CSS

* { margin:0; padding:0; box-sizing:border-box; }
body { background:#0a0a0a; margin:0; padding:0; width:720px; font-family:'Orbitron',sans-serif; }

.card-wrapper { position:relative; width:720px; }

/* Glow extérieur — z-index:-1 pour ne jamais créer de voile */
.border-glow {
  position:absolute; inset:-2px; border-radius:20px;
  background:{$sc['glow_gradient']};
  background-size:300% 300%; animation:gradientShift 4s ease infinite;
  z-index:-1; filter:blur(6px); opacity:0.8;
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.card {
  position:relative; z-index:1; width:720px; background:#0d0d0f;
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
.card-body { position:relative; z-index:2; padding:20px 24px 16px; display:flex; flex-direction:column; gap:10px; }

.card-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:26px; object-fit:contain; }
.sport-badge {
  display:flex; align-items:center; gap:5px;
  background:{$sc['badge_bg']}; border:1.5px solid {$sc['badge_border']};
  border-radius:20px; padding:3px 12px;
  font-family:'Orbitron',sans-serif; font-size:10px; font-weight:700;
  color:{$sc['badge_color']}; letter-spacing:1px; text-transform:uppercase;
  box-shadow:{$sc['badge_shadow']}; text-shadow:{$sc['badge_tshadow']};
}

.datetime-block { text-align:center; padding:2px 0; }
.datetime-day { font-family:'Orbitron',sans-serif; font-size:12px; font-weight:600; color:rgba(255,255,255,0.35); text-transform:uppercase; letter-spacing:3px; margin-bottom:2px; }
.datetime-time { font-family:'Orbitron',sans-serif; font-size:38px; font-weight:900; letter-spacing:4px; line-height:1; color:{$sc['time_color']}; text-shadow:{$sc['time_shadow']}; }

.match-block { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:10px 16px; position:relative; }
.match-left-bar { position:absolute; left:0; top:0; bottom:0; width:3px; background:linear-gradient(to bottom,#ff2d7a,#00e5ff); border-radius:3px 0 0 3px; }
.live-badge { display:flex; align-items:center; gap:5px; justify-content:center; margin-bottom:6px; font-family:'Orbitron',sans-serif; font-size:9px; font-weight:700; color:#ff2d7a; letter-spacing:2px; text-transform:uppercase; }
.live-dot { width:7px; height:7px; border-radius:50%; background:#ff2d7a; box-shadow:0 0 6px #ff2d7a; animation:blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }
.match-players { display:flex; align-items:center; justify-content:center; gap:14px; }
.player-info { display:flex; align-items:center; gap:6px; }
.player { font-family:'Bebas Neue',cursive; font-size:23px; letter-spacing:1px; line-height:1; }
.player.main { color:#fff; }
.player.opponent { color:rgba(255,255,255,0.5); }
.vs-badge { font-family:'Orbitron',sans-serif; font-size:12px; font-weight:900; color:#ff2d7a; }
.match-comp { font-family:'Orbitron',sans-serif; font-size:8px; color:rgba(255,255,255,0.3); text-align:center; margin-top:5px; letter-spacing:1px; }

.prono-block { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; gap:14px; }
.prono-left { flex:1; }
.prono-label { font-size:9px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:2px; margin-bottom:4px; font-weight:600; }
.prono-text {
  font-family:'Orbitron',sans-serif; font-weight:700; font-size:13px;
  color: #ff2d7a;
}
.cote-block { text-align:center; flex-shrink:0; }
.cote-label { font-size:9px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:2px; margin-bottom:5px; font-weight:600; }
.cote-pill { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#ff2d7a 0%,#c850c0 45%,#4158d0 100%); border-radius:12px; padding:9px 22px; min-width:90px; box-shadow:0 4px 20px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12); }
.cote-pill-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.13); border-radius:12px 12px 0 0; }
.cote-value { font-family:'Orbitron',sans-serif; font-size:24px; font-weight:900; color:#fff; position:relative; z-index:1; }

.confidence-section { display:flex; align-items:center; gap:10px; }
.confidence-label { font-size:10px; color:rgba(255,255,255,0.32); text-transform:uppercase; letter-spacing:1.5px; font-weight:600; white-space:nowrap; flex-shrink:0; }
.confidence-bar-bg { flex:1; height:6px; background:rgba(255,255,255,0.06); border-radius:10px; overflow:hidden; }
.confidence-bar-fill { height:100%; width:{$conf}%; background:{$sc['conf_gradient']}; border-radius:10px; animation:barPulse 2s ease-in-out infinite; }
@keyframes barPulse { 0%,100%{opacity:1} 50%{opacity:0.8} }
.confidence-score { font-family:'Orbitron',sans-serif; font-size:13px; font-weight:700; color:{$sc['conf_color']}; flex-shrink:0; text-shadow:{$sc['conf_shadow']}; }

.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18); border-radius:10px; padding:9px 12px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:3px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:3px 0 0 3px; }
.promo-text-block { flex:1; padding-left:8px; display:flex; flex-direction:column; gap:2px; }
.promo-eyebrow { font-size:8px; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; font-family:'Orbitron',sans-serif; }
.promo-main { font-family:'Bebas Neue',cursive; font-size:14px; letter-spacing:0.8px; color:#fff; }
.promo-main-hl { color:{$sc['promo_price_color']}; }
.promo-sub { font-size:7px; color:rgba(255,255,255,0.35); font-weight:500; font-family:'Orbitron',sans-serif; }
.promo-sub span { color:{$sc['promo_price_color']}; font-weight:700; }
.promo-cta { display:inline-flex; align-items:center; gap:4px; background:linear-gradient(135deg,#39ff14,#00c896); color:#000; font-family:'Orbitron',sans-serif; font-size:8px; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:7px 12px; border-radius:8px; white-space:nowrap; box-shadow:0 0 14px rgba(57,255,20,0.4); }

/* Locked */
.locked-zone { text-align:center; padding:10px 0; }
.locked-padlock { font-size:42px; line-height:1; }
.locked-reserved { font-family:'Orbitron',sans-serif; font-size:10px; color:#ff2d7a; opacity:0.7; letter-spacing:2px; margin:6px 0; }
.locked-cta-btn { background:linear-gradient(135deg,#FF2D78,#d6245f); color:white; font-family:'Orbitron',sans-serif; font-size:10px; font-weight:700; padding:8px 24px; border-radius:10px; display:inline-block; letter-spacing:1px; }
.locked-cote-center { text-align:center; margin:4px 0; }

.card-footer-gradient { height:3px; background:{$sc['footer_gradient']}; position:relative; z-index:2; }
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
.card-wrapper.tennis .prono-block { background:linear-gradient(90deg,rgba(57,255,20,0.14),rgba(144,255,128,0.06)) !important; border-color:rgba(57,255,20,0.2); }
.card-wrapper.tennis .prono-text { color:#fff; font-size:16px; }
.card-wrapper.tennis .cote-pill { background:linear-gradient(135deg,#E7337B 0%,#7D41E7 100%); box-shadow:0 4px 16px rgba(231,51,123,0.35); }
.card-wrapper.tennis .cote-pill-shine { display:none !important; }
.card-wrapper.tennis .cote-value { background:transparent !important; box-shadow:none !important; }
.card-wrapper.tennis .promo-banner { background:#1A361A; border:1px solid rgba(57,255,20,0.35); }
.card-wrapper.tennis .promo-eyebrow { color:#39ff14; }
.card-wrapper.tennis .promo-cta { background:linear-gradient(135deg,#39ff14,#00c896); color:#000; box-shadow:0 0 14px rgba(57,255,20,0.5); }
.card-wrapper.tennis .locked-reserved { color:#39ff14; }
.card-wrapper.tennis .locked-cta-btn { background:linear-gradient(135deg,#39ff14,#00c896); color:#000; }
TENNIS;
    }

    $wrapper_class = $is_tennis ? 'card-wrapper tennis' : 'card-wrapper';
    $promo_eyebrow = $is_tennis ? 'OFFRE EXCLUSIVE' : $sc['emoji'] . ' Offre exclusive';
    $promo_main   = $is_tennis ? 'PACK TENNIS PRO - <span class="promo-main-hl">15€/semaine</span>' : "Pack <span class='promo-main-hl'>{$sc['pack']}</span> — Accès illimité";
    $promo_sub    = $is_tennis ? 'Pronostics experts - Analyses live - Accès illimité' : "Pronostics experts · Analyses live · <span>Dès 9.99€/mois</span>";
    $promo_extra  = $is_tennis ? '' : '';
    $promo_cta_text = $is_tennis ? "JE M'ABONNE →" : "🚀 Je m'abonne";
    $cote_pill_inner = $is_tennis
        ? "<div class='cote-value'>{$cote}</div>"
        : "<div class='cote-pill-shine'></div><div class='cote-value'>{$cote}</div>";

    // Tennis : VS = SVG uniquement (dégradé sur le texte, zéro background)
    $vs_html = $is_tennis
        ? "<span class='vs-badge' style='display:inline-block;width:42px;height:26px;background:none!important;border:none;padding:0;margin:0;line-height:0;vertical-align:middle;'><svg xmlns='http://www.w3.org/2000/svg' width='42' height='26' viewBox='0 0 42 26' style='display:block;'><defs><linearGradient id='vsg' x1='0%' y1='0%' x2='100%' y2='0%'><stop offset='0%' style='stop-color:#E7337B'/><stop offset='100%' style='stop-color:#00e5ff'/></linearGradient></defs><text x='21' y='21' text-anchor='middle' fill='url(#vsg)' font-family='Orbitron,sans-serif' font-size='20' font-weight='900'>VS</text></svg></span>"
        : "<div class='vs-badge'>VS</div>";

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
          <div class='player-info'>{$flag1}<div class='player main'>{$p1}</div></div>
          {$vs_html}
          <div class='player-info'>{$flag2}<div class='player opponent'>{$p2}</div></div>
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
          <div class='player-info'>{$flag1}<div class='player main'>{$p1}</div></div>
          {$vs_html}
          <div class='player-info'>{$flag2}<div class='player opponent'>{$p2}</div></div>
        </div>
        <div class='match-comp'>{$comp}</div>
      </div>
      <div class='locked-zone'>
        <div class='locked-padlock'>🔒</div>
        <div class='locked-reserved'>CONTENU RÉSERVÉ</div>
        <div class='locked-cta-btn'>🔓 Reçois le bet sur stratedgepronos.fr</div>
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
    $css = $embeddedFonts . <<<CSS

* { margin:0; padding:0; box-sizing:border-box; }
body { background:#0a0a0a; margin:0; padding:0; width:760px; font-family:'Orbitron',sans-serif; }

.card-wrapper { position:relative; width:760px; }
.border-glow {
  position:absolute; inset:-2px; border-radius:20px;
  background:linear-gradient(135deg,#ff2d7a,#c850c0,#ff2d7a);
  background-size:300% 300%; animation:gradientShift 4s ease infinite;
  z-index:-1; filter:blur(8px); opacity:0.75;
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }

.card {
  position:relative; z-index:1; width:760px; background:#0e0b12;
  border-radius:16px; overflow:hidden; display:flex; flex-direction:column;
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

.card-body { position:relative; z-index:2; padding:18px 22px 16px; display:flex; flex-direction:column; gap:9px; }

/* HEADER */
.card-header { display:flex; align-items:center; justify-content:space-between; }
.logo-img { height:24px; object-fit:contain; }
.funbet-badge {
  display:flex; align-items:center; gap:6px;
  background:rgba(255,45,122,0.1); border:1.5px solid rgba(255,45,122,0.45);
  border-radius:20px; padding:4px 14px;
  font-family:'Orbitron',sans-serif; font-size:10px; font-weight:900;
  color:#ff2d7a; letter-spacing:2px; text-transform:uppercase;
  box-shadow:0 0 10px rgba(255,45,122,0.2); text-shadow:0 0 8px rgba(255,45,122,0.5);
}

/* DATE / HEURE */
.datetime-block { text-align:center; padding:2px 0; }
.datetime-day { font-family:'Orbitron',sans-serif; font-size:11px; font-weight:600; color:rgba(255,255,255,0.32); text-transform:uppercase; letter-spacing:3px; margin-bottom:1px; }
.datetime-time { font-family:'Orbitron',sans-serif; font-size:32px; font-weight:900; letter-spacing:3px; line-height:1; color:#ff2d7a; text-shadow:0 0 25px rgba(255,45,122,0.5); }
.datetime-sub { font-size:9px; color:rgba(255,255,255,0.2); font-family:'Orbitron',sans-serif; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; margin-top:1px; }

/* SECTION TITLE */
.section-title { display:flex; align-items:center; gap:6px; }
.section-title-text { font-family:'Orbitron',sans-serif; font-size:9px; font-weight:700; color:rgba(255,45,122,0.6); text-transform:uppercase; letter-spacing:2.5px; white-space:nowrap; }
.section-title-line { flex:1; height:1px; background:linear-gradient(to right,rgba(255,45,122,0.3),transparent); }

/* LIGNES DE PARIS */
.bets-container { display:flex; flex-direction:column; gap:6px; }
.bet-line { background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.055); border-radius:8px; padding:8px 10px 8px 14px; position:relative; display:flex; flex-direction:column; gap:3px; }
.bet-left-bar { position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:3px 0 0 3px; }
.bet-top-row { display:flex; align-items:center; justify-content:space-between; }
.bet-num-match { display:flex; align-items:center; gap:4px; }
.bet-num { font-family:'Orbitron',sans-serif; font-size:10px; color:rgba(255,140,200,0.6); }
.bet-match { font-family:'Orbitron',sans-serif; font-size:10px; color:rgba(255,255,255,0.5); letter-spacing:0.5px; font-weight:600; }
.bet-cote-pill { background:rgba(255,45,122,0.08); border:1px solid rgba(255,45,122,0.2); border-radius:6px; padding:2px 8px; font-family:'Orbitron',sans-serif; font-size:13px; font-weight:700; color:#ff8c6b; }
.bet-prono { font-family:'Orbitron',sans-serif; font-size:10px; font-weight:700; color:rgba(255,255,255,0.9); }

/* CONFIANCE */
.confidence-col { display:flex; flex-direction:column; gap:4px; }
.conf-header { display:flex; justify-content:space-between; align-items:center; }
.conf-label { font-family:'Orbitron',sans-serif; font-size:9px; color:rgba(255,255,255,0.28); text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.conf-score { font-family:'Orbitron',sans-serif; font-size:14px; font-weight:900; color:#ff2d7a; text-shadow:0 0 8px rgba(255,45,122,0.3); }
.conf-bar-bg { height:6px; background:rgba(255,255,255,0.05); border-radius:3px; overflow:hidden; }
.conf-bar-fill { height:100%; width:{$conf}%; background:linear-gradient(to right,#ff2d7a,#c850c0,#ff8c42); border-radius:3px; animation:barPulse 2s ease-in-out infinite; }
@keyframes barPulse { 0%,100%{opacity:0.85} 50%{opacity:1} }

/* COTE TOTALE */
.cote-totale-block { display:flex; align-items:center; gap:14px; background:rgba(255,45,122,0.06); border:1px solid rgba(255,45,122,0.14); border-radius:10px; padding:9px 14px; }
.cote-total-info { flex:1; }
.cote-eyebrow { font-family:'Orbitron',sans-serif; font-size:8px; color:rgba(255,255,255,0.25); text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:2px; }
.cote-desc { font-family:'Orbitron',sans-serif; font-size:9px; color:rgba(255,255,255,0.45); letter-spacing:1px; }
.total-pill { position:relative; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#ff2d7a 0%,#c850c0 50%,#4158d0 100%); border-radius:12px; padding:9px 24px; flex-shrink:0; box-shadow:0 4px 22px rgba(255,45,122,0.5),inset 0 0 0 1px rgba(255,255,255,0.12); }
.total-pill-shine { position:absolute; top:0; left:0; right:0; height:50%; background:rgba(255,255,255,0.13); border-radius:12px 12px 0 0; }
.total-cote { font-family:'Orbitron',sans-serif; font-size:26px; font-weight:900; color:#fff; letter-spacing:2px; position:relative; z-index:1; }

/* PROMO */
.promo-banner { background:rgba(14,22,14,0.95); border:1px solid rgba(57,255,20,0.18); border-radius:10px; padding:9px 12px; position:relative; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.promo-left-bar { position:absolute; left:0; top:0; bottom:0; width:3px; background:linear-gradient(to bottom,#39ff14,#00e5ff); border-radius:3px 0 0 3px; }
.promo-text-block { flex:1; padding-left:8px; display:flex; flex-direction:column; gap:3px; }
.promo-eyebrow { font-family:'Orbitron',sans-serif; font-size:8px; color:#39ff14; text-transform:uppercase; letter-spacing:2px; font-weight:700; }
.promo-main { font-family:'Bebas Neue',cursive; font-size:14px; letter-spacing:0.8px; color:#fff; }
.promo-main-hl { color:#ff2d7a; }
.promo-packs { display:flex; gap:4px; flex-wrap:wrap; }
.pack-tag { font-family:'Orbitron',sans-serif; font-size:9px; font-weight:700; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.4); text-transform:uppercase; }
.pack-tag-max { color:#39ff14; border-color:rgba(57,255,20,0.3); background:rgba(57,255,20,0.07); }
.promo-price { font-family:'Orbitron',sans-serif; font-size:7px; color:rgba(255,255,255,0.35); }
.promo-price span { font-family:'Orbitron',sans-serif; font-size:13px; font-weight:700; color:#00e5ff; }
.promo-right { display:flex; flex-direction:column; align-items:flex-end; gap:5px; flex-shrink:0; }
.promo-cta { display:inline-flex; align-items:center; gap:4px; background:linear-gradient(135deg,#39ff14,#00c896); color:#000; font-family:'Orbitron',sans-serif; font-size:8px; font-weight:900; letter-spacing:0.8px; text-transform:uppercase; padding:7px 12px; border-radius:8px; white-space:nowrap; box-shadow:0 0 14px rgba(57,255,20,0.4); }

/* Locked */
.locked-zone { text-align:center; margin:4px 0; }
.locked-padlock { font-size:40px; line-height:1; }
.locked-reserved { font-family:'Orbitron',sans-serif; font-size:10px; color:#ff2d7a; opacity:0.7; letter-spacing:2px; margin:6px 0; }
.locked-cta-btn { background:linear-gradient(135deg,#FF2D78,#d6245f); color:white; font-family:'Orbitron',sans-serif; font-size:10px; font-weight:700; padding:8px 24px; border-radius:10px; display:inline-block; letter-spacing:1px; }

.card-footer-gradient { height:3px; background:linear-gradient(to right,#ff2d7a,#c850c0,#4158d0); position:relative; z-index:2; }
CSS;

    // ── Générer les lignes de paris ──
    $betLinesNormal = '';
    $betLinesLocked = '';
    foreach ($bets as $i => $bet) {
        $num    = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        $match  = htmlspecialchars($bet['match'] ?? '', ENT_QUOTES, 'UTF-8');
        $prono  = htmlspecialchars($bet['prono'] ?? '', ENT_QUOTES, 'UTF-8');
        $bcote  = htmlspecialchars($bet['cote']  ?? '1.00', ENT_QUOTES, 'UTF-8');
        $f1     = flagImg($bet['flag1'] ?? '');
        $f2     = flagImg($bet['flag2'] ?? '');
        $barColor = $barColors[$i % 3];

        $betLinesNormal .= <<<HTML
    <div class='bet-line'>
      <div class='bet-left-bar' style='background:{$barColor}'></div>
      <div class='bet-top-row'>
        <div class='bet-num-match'>
          <span class='bet-num'>{$num}</span>
          <span>{$f1}</span>
          <span class='bet-match'>{$match}</span>
          <span>{$f2}</span>
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
          <span>{$f1}</span>
          <span class='bet-match'>{$match}</span>
          <span>{$f2}</span>
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
