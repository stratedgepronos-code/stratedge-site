<?php
// ============================================================
// STRATEDGE — live-card-template.php V14 (refonte éditorial 1080x1080)
// Design: cyberpunk editorial magazine style — mascotte + ghost text + crop marks
// Returns html_normal + html_locked for each bet type
// ============================================================

// Load asset system helpers (logos, flags, player photos)
$__inc = __DIR__ . '/../includes/';
foreach ([
    'football-logos-db.php','nba-logos-db.php','nhl-logos-db.php',
    'mlb-logos-db.php','flags-db.php','player-photos.php'
] as $f) {
    if (file_exists($__inc . $f)) { require_once $__inc . $f; }
}

if (!function_exists('stratedge_is_valid_logo')) {
    function stratedge_is_valid_logo($path) {
        $path = trim((string)$path);
        if ($path === '') return false;
        if (filter_var($path, FILTER_VALIDATE_URL)) return true;
        if ($path[0] === '/') {
            $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
            if (is_file($abs) && filesize($abs) > 500) return true;
        }
        return false;
    }
}

/** Resolve team logo URL via local asset helpers. */
function stratedge_resolve_team_logo($teamName, $sport) {
    $name = trim((string)$teamName);
    if ($name === '') return '';
    $s = strtolower(trim((string)$sport));

    $foot_keys = ['foot','football','soccer','ligue 1','ligue1','premier league','pl','liga','serie a','seriea','bundesliga','bl','champions league','c1','europa league','c3','eredivisie','mls','liga mx'];
    if (in_array($s, $foot_keys, true)) {
        return function_exists('stratedge_football_logo') ? stratedge_football_logo($name) : '';
    }
    if (in_array($s, ['basket','nba','basketball'], true)) {
        return function_exists('stratedge_nba_logo') ? stratedge_nba_logo($name) : '';
    }
    if (in_array($s, ['hockey','nhl'], true)) {
        return function_exists('stratedge_nhl_logo') ? stratedge_nhl_logo($name) : '';
    }
    if (in_array($s, ['baseball','mlb'], true)) {
        return function_exists('stratedge_mlb_logo') ? stratedge_mlb_logo($name) : '';
    }
    return '';
}

/** UTF-8 codepoint helper for emoji flag → ISO2 conversion */
function _stratedge_cp($ch) {
    $bytes = array_values(unpack('C*', $ch));
    $cnt = count($bytes);
    if ($cnt === 1) return $bytes[0];
    if ($cnt === 2) return (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F);
    if ($cnt === 3) return (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F);
    if ($cnt === 4) return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F);
    return 0;
}

/** Resolve flag: accepts URL / local path / emoji / country name / ISO2 */
function stratedge_flag_resolve($input) {
    $input = trim((string)$input);
    if ($input === '') return '';
    if (strpos($input, 'http') === 0 || $input[0] === '/') return $input;
    if (!function_exists('stratedge_flag')) return '';

    // Try emoji → ISO2
    if (function_exists('mb_strlen') && mb_strlen($input, 'UTF-8') <= 4) {
        $codes = [];
        $len = mb_strlen($input, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($input, $i, 1, 'UTF-8');
            $cp = _stratedge_cp($ch);
            if ($cp >= 0x1F1E6 && $cp <= 0x1F1FF) {
                $codes[] = chr($cp - 0x1F1E6 + ord('A'));
            }
        }
        if (count($codes) === 2) return stratedge_flag(implode('', $codes));
    }
    return stratedge_flag($input);
}

// ────────────────────────────────────────────────────────────
// THEMES
// ────────────────────────────────────────────────────────────
function stratedge_card_theme($tipster) {
    $t = strtolower(trim((string)$tipster));
    if ($t === 'tennis') {
        return [
            'accent' => '#39ff14', 'rgb' => '57,255,20',
            'accent_gradient' => 'linear-gradient(180deg,#c5ff5a 0%,#39ff14 70%,#1a8f08 100%)',
            'accent_gradient_2' => 'linear-gradient(180deg,#c5ff5a,#39ff14)',
            'bg' => 'linear-gradient(160deg, #0f1f0f 0%, #0a140a 40%, #050a06 100%)',
            'bg_dark' => '#050a06',
            'mascot' => '/assets/images/mascotte-tennis-nobg.png',
            'conf_bar' => 'linear-gradient(90deg,#39ff14,#00e5ff)',
            'edition' => 'TENNIS DIVISION',
            'div_short' => 'Tennis',
        ];
    }
    if ($t === 'fun') {
        return [
            'accent' => '#ff2d7a', 'rgb' => '255,45,122',
            'accent_gradient' => 'linear-gradient(180deg,#ff8cb8 0%,#ff2d7a 50%,#c850c0 100%)',
            'accent_gradient_2' => 'linear-gradient(180deg,#ff8cb8,#c850c0)',
            'bg' => 'linear-gradient(160deg, #1a0820 0%, #14081a 40%, #050208 100%)',
            'bg_dark' => '#050208',
            'mascot' => '/assets/images/mascotte-fun-hires.png',
            'conf_bar' => 'linear-gradient(90deg,#ff2d7a,#c850c0)',
            'edition' => 'FUN ZONE',
            'div_short' => 'Fun',
        ];
    }
    return [
        'accent' => '#ff2d78', 'rgb' => '255,45,120',
        'accent_gradient' => 'linear-gradient(180deg,#ff8cb8 0%,#ff2d78 70%,#b01e5c 100%)',
        'accent_gradient_2' => 'linear-gradient(180deg,#ff8cb8,#ff2d78)',
        'bg' => 'linear-gradient(160deg, #1a0a14 0%, #140a12 40%, #050206 100%)',
        'bg_dark' => '#050206',
        'mascot' => '/assets/images/mascotte-rose.png',
        'conf_bar' => 'linear-gradient(90deg,#ff2d78,#00e5ff)',
        'edition' => 'MULTISPORTS',
        'div_short' => 'Multi',
    ];
}

function stratedge_card_promo($tipster) {
    $t = strtolower(trim((string)$tipster));
    if ($t === 'tennis') return ['eyebrow'=>'Tennis Premium · Accès total','title'=>'Abonnement Semaine','price'=>'15€','url'=>'stratedgepronos.fr/offre-tennis'];
    if ($t === 'fun')    return ['eyebrow'=>"Fun Week-End · Délire grosses cotes",'title'=>'Abonnement Week-End','price'=>'10€','url'=>'stratedgepronos.fr/offre-fun'];
    return                 ['eyebrow'=>'StratEdge Multi · Packs crédits','title'=>'Pack Trio · 3 paris','price'=>'12€','url'=>'stratedgepronos.fr/packs-daily'];
}

/** Generate the full CSS for the card. No heredoc PHP vars inside — pre-filled by sprintf-style. */
function stratedge_card_css($theme, $conf_pct) {
    $accent = $theme['accent']; $rgb = $theme['rgb']; $bg_dark = $theme['bg_dark'];
    $bg = $theme['bg']; $grad = $theme['accent_gradient']; $grad2 = $theme['accent_gradient_2'];
    $conf_bar = $theme['conf_bar'];
    $c = (int)$conf_pct;
    $css = "@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Instrument+Serif:ital@0;1&family=Inter:wght@400;700;800;900&family=Archivo+Narrow:wght@400;500;700&family=Share+Tech+Mono&display=swap');";
    $css .= "*{margin:0;padding:0;box-sizing:border-box}";
    $css .= "html,body{background:#050505;margin:0;padding:0}";
    $css .= ".card{position:relative;width:1080px;height:1080px;background:$bg;overflow:hidden;isolation:isolate;font-family:'Inter',sans-serif;color:#ede8e0;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}";
    $css .= ".noise{position:absolute;inset:0;pointer-events:none;mix-blend-mode:overlay;opacity:.35;z-index:8}";
    $css .= ".court-lines{position:absolute;inset:0;opacity:.14;z-index:1}";
    $css .= ".ghost{position:absolute;top:-40px;right:10px;font-family:'Bebas Neue',sans-serif;font-size:420px;line-height:.85;color:$accent;opacity:.055;transform:rotate(-8deg);letter-spacing:-10px;z-index:1;pointer-events:none;white-space:nowrap}";
    $css .= ".mascot{position:absolute;right:-180px;bottom:-160px;width:820px;height:auto;z-index:3;filter:drop-shadow(0 40px 60px rgba(0,0,0,.8));pointer-events:none}";
    $css .= ".mascot.fun-pose{width:auto;height:820px;right:-50px;bottom:-100px}";
    $css .= ".vignette{position:absolute;inset:0;background:radial-gradient(ellipse 80% 70% at 50% 50%, transparent 40%, rgba(0,0,0,.55) 100%);z-index:7;pointer-events:none}";
    $css .= ".crop{position:absolute;width:22px;height:22px;z-index:9;pointer-events:none}";
    $css .= ".crop.tl{top:32px;left:32px;border-top:1px solid rgba(237,232,224,.25);border-left:1px solid rgba(237,232,224,.25)}";
    $css .= ".crop.tr{top:32px;right:32px;border-top:1px solid rgba(237,232,224,.25);border-right:1px solid rgba(237,232,224,.25)}";
    $css .= ".crop.bl{bottom:32px;left:32px;border-bottom:1px solid rgba(237,232,224,.25);border-left:1px solid rgba(237,232,224,.25)}";
    $css .= ".crop.br{bottom:32px;right:32px;border-bottom:1px solid rgba(237,232,224,.25);border-right:1px solid rgba(237,232,224,.25)}";
    $css .= ".edition{position:absolute;left:24px;top:50%;transform:translateY(-50%) rotate(-90deg);transform-origin:left center;font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:5px;text-transform:uppercase;color:$accent;opacity:.22;white-space:nowrap;z-index:4;pointer-events:none}";
    $css .= ".inner{position:relative;padding:64px 64px 20px 64px}";
    $css .= ".top{position:relative;z-index:5;display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}";
    $css .= ".brand-logo{height:36px;width:auto;display:block;filter:drop-shadow(0 0 8px rgba(0,0,0,.7))}";
    $css .= ".brand-line{width:46px;height:1px;background:#ede8e0;opacity:.35;margin-top:10px}";
    $css .= ".top-right{text-align:right}";
    $css .= ".date-meta{font-family:'Archivo Narrow',sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.7;margin-bottom:14px;font-weight:700}";
    $css .= ".badge-sport{display:inline-flex;align-items:center;font-family:'Archivo Narrow',sans-serif;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:$accent;background:rgba($rgb,.1);border:1.5px solid $accent;padding:7px 14px;border-radius:2px;box-shadow:0 0 14px rgba($rgb,.5), inset 0 0 8px rgba($rgb,.1);text-shadow:0 0 8px rgba($rgb,.6);white-space:nowrap}";
    $css .= ".badge-sport::before{content:'';width:6px;height:6px;border-radius:50%;background:$accent;box-shadow:0 0 10px $accent;margin-right:10px;flex-shrink:0}";
    $css .= ".kicker-wrap{position:relative;z-index:5;margin-bottom:0;line-height:0}";
    $css .= ".kicker-svg{display:block;height:34px;width:auto;max-width:100%}";
    $css .= ".kicker{position:relative;z-index:5;font-family:'Instrument Serif',serif;font-style:italic;font-size:24px;color:#ede8e0;opacity:.6;margin-bottom:0;word-spacing:.1em;white-space:pre-wrap}";
    $css .= ".time-block{position:relative;z-index:5;display:flex;align-items:flex-end;margin-bottom:8px}";
    $css .= ".time{font-family:'Inter',sans-serif;font-weight:900;font-size:64px;line-height:.9;letter-spacing:-3px;color:#ede8e0;font-variant-numeric:tabular-nums}";
    $css .= ".time-svg-wrap{display:inline-block;height:74px;line-height:0}";
    $css .= ".time-svg{height:74px;width:auto;display:block;overflow:visible}";
    $css .= ".time-accent{color:$accent;text-shadow:0 0 18px rgba($rgb,.55), 0 0 6px rgba($rgb,.8), 0 2px 0 rgba(255,255,255,.15)}";
    $css .= ".time-label{font-family:'Archivo Narrow',sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.55;padding-bottom:14px;margin-left:14px;white-space:nowrap}";
    $css .= ".type-stamp{position:relative;z-index:5;display:inline-flex;align-items:center;gap:14px;padding:14px 22px;background:rgba($rgb,.12);border:2px solid $accent;border-radius:4px;margin:18px 0 22px 0;box-shadow:0 0 24px rgba($rgb,.35), inset 0 0 14px rgba($rgb,.08)}";
    $css .= ".type-stamp-icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;color:$accent;filter:drop-shadow(0 0 8px rgba($rgb,.6))}";
    $css .= ".type-stamp-text{display:flex;flex-direction:column;gap:2px}";
    $css .= ".type-stamp-label{font-family:'Bebas Neue',sans-serif;font-size:30px;letter-spacing:3px;line-height:1;color:$accent;text-shadow:0 0 12px rgba($rgb,.7)}";
    $css .= ".type-stamp-sub{font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.55}";
    $css .= ".type-stamp.is-live{border-color:#ff3838;background:rgba(255,56,56,.1);box-shadow:0 0 24px rgba(255,56,56,.5), inset 0 0 14px rgba(255,56,56,.1)}";
    $css .= ".type-stamp.is-live .type-stamp-label,.type-stamp.is-live .type-stamp-icon{color:#ff3838;text-shadow:0 0 12px rgba(255,56,56,.8)}";
    $css .= ".type-stamp.is-live .type-stamp-icon{filter:drop-shadow(0 0 10px rgba(255,56,56,.8))}";
    $css .= ".live-dot{width:14px;height:14px;border-radius:50%;background:#ff3838;box-shadow:0 0 16px #ff3838, 0 0 30px rgba(255,56,56,.6)}";
    $css .= ".type-stamp.is-fun{transform:rotate(-2deg);background:linear-gradient(135deg, rgba($rgb,.18), rgba($rgb,.08))}";
    $css .= ".type-stamp.is-fun .type-stamp-label::after{content:'';display:inline-block;width:8px;height:8px;background:$accent;margin-left:10px;transform:rotate(45deg);box-shadow:0 0 10px $accent;vertical-align:middle}";
    $css .= ".combi-count{font-family:'Inter',sans-serif;font-weight:900;font-size:44px;letter-spacing:-2px;line-height:.9;color:$accent;text-shadow:0 0 14px rgba($rgb,.7);margin-left:6px}";
    $css .= ".match{position:relative;z-index:2;margin-bottom:26px;max-width:540px}";
    $css .= ".team{font-family:'Bebas Neue',sans-serif;font-size:38px;line-height:1;letter-spacing:-.5px;color:#ede8e0;display:flex;align-items:center;gap:14px;margin:6px 0}";
    $css .= ".team-logo{width:44px;height:44px;object-fit:contain;filter:drop-shadow(0 0 8px rgba(0,0,0,.6));flex-shrink:0}";
    $css .= ".flag{width:38px;height:25px;object-fit:cover;display:inline-block;margin-right:10px;border-radius:2px;flex-shrink:0}";
    $css .= ".vs{font-family:'Instrument Serif',serif;font-style:italic;font-size:28px;color:$accent;margin:0 0 0 22px;opacity:.9}";
    $css .= ".comp{font-family:'Archivo Narrow',sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.5;margin-top:10px;padding-left:22px}";
    $css .= ".pick{position:relative;z-index:2;max-width:540px;border:1px solid rgba(237,232,224,.2);padding:16px 22px;margin-bottom:0}";
    $css .= ".pick.combi{max-width:620px;border:none;border-top:1px solid rgba(237,232,224,.25);border-bottom:1px solid rgba(237,232,224,.25);padding:16px 0}";
    $css .= ".pick-eyebrow{font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.45;margin-bottom:10px}";
    $css .= ".pick-main{font-family:'Bebas Neue',sans-serif;font-size:30px;line-height:1.1;letter-spacing:-.5px;color:#ede8e0;margin-bottom:6px;word-spacing:.15em;word-wrap:break-word;overflow-wrap:break-word}";
    $css .= ".pick-accent{font-family:'Instrument Serif',serif;font-style:italic;font-size:30px;color:$accent;text-shadow:0 0 10px rgba($rgb,.5)}";
    $css .= ".pick-market{font-family:'Archivo Narrow',sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.45;margin-top:8px}";
    $css .= ".combi-list{display:flex;flex-direction:column;gap:14px;margin-top:8px}";
    $css .= ".combi-row{display:flex;align-items:center;padding:10px 0;border-top:1px dashed rgba($rgb,.2);white-space:nowrap}";
    $css .= ".combi-row:first-child{border-top:none;padding-top:4px}";
    $css .= ".combi-teams{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:.5px;color:#ede8e0;line-height:1.2;display:flex;align-items:center;flex:1;min-width:0}";
    $css .= ".combi-teams .flag{width:26px;height:17px;margin-right:6px}";
    $css .= ".combi-teams .team-logo{width:26px;height:26px;margin-right:6px}";
    $css .= ".combi-sep{font-family:'Instrument Serif',serif;font-style:italic;font-size:16px;color:$accent;opacity:.7;padding:0 8px}";
    $css .= ".combi-pick{display:flex;align-items:center;margin-left:18px;flex-shrink:0;line-height:0}";
    $css .= ".combi-pick-svg{display:block;height:24px;width:auto;max-width:260px}";
    $css .= ".combi-cote{font-family:'Share Tech Mono',monospace;font-size:16px;color:$accent;background:rgba($rgb,.1);padding:4px 10px;border:1px solid rgba($rgb,.3);margin-left:18px;flex-shrink:0}";
    $css .= ".pick-locked{position:relative;z-index:2;max-width:540px;border:1.5px solid $accent;padding:18px 22px;margin-bottom:0;display:flex;align-items:center;box-shadow:0 0 20px rgba($rgb,.3);overflow:hidden}";
    $css .= ".pick-locked::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(-45deg, transparent 0 14px, rgba($rgb,.04) 14px 15px);pointer-events:none}";
    $css .= ".padlock{position:relative;width:48px;height:48px;flex-shrink:0;margin-right:28px;border:1.5px solid $accent;display:flex;align-items:center;justify-content:center;border-radius:2px;box-shadow:0 0 14px rgba($rgb,.5), inset 0 0 8px rgba($rgb,.15)}";
    $css .= ".padlock::before{content:'';position:absolute;inset:-5px;border:1px solid rgba($rgb,.15)}";
    $css .= ".locked-text{position:relative;z-index:1;flex:1}";
    $css .= ".locked-eyebrow{font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:3px;text-transform:uppercase;color:$accent;margin-bottom:6px}";
    $css .= ".locked-main{font-family:'Bebas Neue',sans-serif;font-size:28px;line-height:1.05;letter-spacing:-.5px;color:#ede8e0;word-spacing:.15em}";
    $css .= ".locked-accent{font-family:'Instrument Serif',serif;font-style:italic;color:$accent;text-shadow:0 0 10px rgba($rgb,.5)}";
    $css .= ".locked-sub{font-family:'Instrument Serif',serif;font-style:italic;font-size:15px;color:#ede8e0;opacity:.55;margin-top:6px}";
    $css .= ".data-row{position:relative;z-index:5;display:flex;align-items:center;margin-top:24px;margin-bottom:0;max-width:720px}";
    $css .= ".data-cote{display:flex;flex-direction:column;gap:4px}";
    $css .= ".data-label{font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:3px;text-transform:uppercase;color:#ede8e0;opacity:.45}";
    $css .= ".cote-val{font-family:'Inter',sans-serif;font-weight:900;font-size:58px;letter-spacing:-3px;line-height:1;color:$accent;font-variant-numeric:tabular-nums}";
    $css .= ".value-pill{display:inline-flex;align-items:center;gap:6px;font-family:'Share Tech Mono',monospace;font-size:11px;letter-spacing:1px;color:$accent;background:rgba($rgb,.08);border:1px solid rgba($rgb,.4);padding:5px 10px;border-radius:2px;margin-top:6px;width:fit-content}";
    $css .= ".conf{flex:1;max-width:340px;margin-left:90px}";
    $css .= ".conf-top{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px}";
    $css .= ".conf-num{font-family:'Share Tech Mono',monospace;font-size:14px;color:#ede8e0;opacity:.8}";
    $css .= ".conf-num strong{color:$accent;font-weight:400;font-size:20px}";
    $css .= ".conf-bar{position:relative;height:3px;background:rgba(237,232,224,.1)}";
    $css .= ".conf-fill{position:absolute;left:0;top:0;bottom:0;width:$c%;background:$conf_bar}";
    $css .= ".conf-dot{position:absolute;left:$c%;top:50%;transform:translate(-50%,-50%);width:10px;height:10px;border-radius:50%;background:$accent;box-shadow:0 0 14px $accent}";
    $css .= ".footer-abs{position:absolute;left:64px;right:64px;bottom:56px;z-index:10}";
    $css .= ".quote{font-family:'Instrument Serif',serif;font-style:italic;font-size:24px;line-height:1.25;color:#ede8e0;opacity:.75;max-width:620px;border-top:1px solid rgba(237,232,224,.15);padding-top:16px;margin-bottom:20px;word-spacing:.1em}";
    $css .= ".quote .hl{color:$accent;font-style:italic;text-shadow:0 0 8px rgba($rgb,.4)}";
    $css .= ".cta-unlock{max-width:620px;border-top:1px solid rgba(237,232,224,.15);padding-top:16px;margin-bottom:18px;display:flex;align-items:center;gap:20px}";
    $css .= ".cta-unlock-text{font-family:'Instrument Serif',serif;font-style:italic;font-size:22px;line-height:1.25;color:#ede8e0;opacity:.85;flex:1;word-spacing:.1em}";
    $css .= ".cta-unlock-text .hl{color:$accent;font-style:italic;text-shadow:0 0 8px rgba($rgb,.4)}";
    $css .= ".cta-arrow{width:56px;height:56px;border:1.5px solid $accent;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba($rgb,.08);box-shadow:0 0 18px rgba($rgb,.4), inset 0 0 10px rgba($rgb,.1)}";
    $css .= ".cta-arrow::after{content:'';width:18px;height:18px;border-right:1.5px solid $accent;border-top:1.5px solid $accent;transform:rotate(45deg) translate(-3px,3px)}";
    $css .= ".promo{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:linear-gradient(135deg, rgba($rgb,.18) 0%, rgba($rgb,.08) 100%);border:1.5px solid $accent;box-shadow:0 0 28px rgba($rgb,.4), inset 0 0 14px rgba($rgb,.1);position:relative}";
    $css .= ".promo-left{display:flex;flex-direction:column;gap:4px}";
    $css .= ".promo-eyebrow{font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:3px;text-transform:uppercase;color:$accent}";
    $css .= ".promo-title{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:1px;color:#ede8e0;word-spacing:.15em}";
    $css .= ".promo-title .price{color:$accent;text-shadow:0 0 8px rgba($rgb,.4)}";
    $css .= ".promo-url{font-family:'Share Tech Mono',monospace;font-size:10px;color:#ede8e0;opacity:.5;margin-top:2px}";
    $css .= ".promo-cta{font-family:'Archivo Narrow',sans-serif;font-weight:700;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:$bg_dark;background:$accent;padding:12px 20px;display:inline-flex;align-items:center;gap:10px;text-decoration:none;box-shadow:0 0 22px rgba($rgb,.6), 0 4px 16px rgba(0,0,0,.4)}";
    $css .= ".promo-cta .arrow{display:inline-block;width:24px;height:1px;background:$bg_dark;position:relative}";
    $css .= ".promo-cta .arrow::after{content:'';position:absolute;right:0;top:50%;width:8px;height:8px;border-right:1px solid $bg_dark;border-top:1px solid $bg_dark;transform:translateY(-50%) rotate(45deg)}";
    $css .= ".player-solo{position:absolute;right:-40px;bottom:30px;width:540px;z-index:3;filter:drop-shadow(0 30px 60px rgba(0,0,0,.85));pointer-events:none}";
    $css .= ".player-solo img.main{width:100%;height:auto;display:block}";
    $css .= ".player-solo-label{position:absolute;bottom:24px;left:24px;right:130px;padding:10px 14px;background:linear-gradient(90deg,rgba(0,0,0,.88),rgba(0,0,0,.5));border-left:4px solid $accent}";
    $css .= ".psolo-name{font-family:'Bebas Neue',sans-serif;font-size:54px;letter-spacing:-1px;line-height:.9;color:#ede8e0;text-shadow:0 2px 10px rgba(0,0,0,.95)}";
    $css .= ".psolo-stats{font-family:'Archivo Narrow',sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:$accent;margin-top:6px}";
    $css .= ".opp-badge{position:absolute;top:60px;right:30px;width:96px;text-align:center}";
    $css .= ".opp-badge img{width:90px;height:90px;filter:drop-shadow(0 0 12px rgba(0,0,0,.8))}";
    $css .= ".opp-label{font-family:'Archivo Narrow',sans-serif;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:$accent;margin-top:6px}";
    return $css;
}

function stratedge_court_lines($theme, $tipster) {
    $accent = $theme['accent'];
    if (strtolower((string)$tipster) === 'tennis') {
        return "<svg class='court-lines' viewBox='0 0 1080 1080' preserveAspectRatio='none'>"
             . "<defs><linearGradient id='lf' x1='0' y1='0' x2='0' y2='1'>"
             . "<stop offset='0%' stop-color='$accent' stop-opacity='0'/>"
             . "<stop offset='60%' stop-color='$accent' stop-opacity='.6'/>"
             . "<stop offset='100%' stop-color='$accent' stop-opacity='0'/>"
             . "</linearGradient></defs>"
             . "<line x1='540' y1='1080' x2='120' y2='600' stroke='url(#lf)' stroke-width='1'/>"
             . "<line x1='540' y1='1080' x2='960' y2='600' stroke='url(#lf)' stroke-width='1'/>"
             . "<line x1='540' y1='1080' x2='300' y2='650' stroke='url(#lf)' stroke-width='.5'/>"
             . "<line x1='540' y1='1080' x2='780' y2='650' stroke='url(#lf)' stroke-width='.5'/>"
             . "<line x1='180' y1='900' x2='900' y2='900' stroke='$accent' stroke-opacity='.3' stroke-width='1'/>"
             . "</svg>";
    }
    return "<svg class='court-lines' viewBox='0 0 1080 1080' preserveAspectRatio='none'>"
         . "<defs><linearGradient id='lf' x1='0' y1='0' x2='1' y2='0'>"
         . "<stop offset='0%' stop-color='$accent' stop-opacity='0'/>"
         . "<stop offset='50%' stop-color='$accent' stop-opacity='.5'/>"
         . "<stop offset='100%' stop-color='$accent' stop-opacity='0'/>"
         . "</linearGradient></defs>"
         . "<line x1='0' y1='280' x2='1080' y2='280' stroke='url(#lf)' stroke-width='1'/>"
         . "<line x1='0' y1='540' x2='1080' y2='540' stroke='url(#lf)' stroke-width='.5'/>"
         . "<line x1='0' y1='820' x2='1080' y2='820' stroke='url(#lf)' stroke-width='.5'/>"
         . "</svg>";
}

function stratedge_noise_svg() {
    return "<svg class='noise' xmlns='http://www.w3.org/2000/svg'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 .55 0'/></filter><rect width='100%' height='100%' filter='url(#n)'/></svg>";
}

function stratedge_type_stamp($type, $n_picks = 0) {
    $t = strtolower((string)$type);
    if ($t === 'safe' || $t === 'buteur' || $t === 'scorer') {
        $icon = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' width='28' height='28'><path d='M12 2L4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6l-8-4z'/><path d='m9 12 2 2 4-4'/></svg>";
        $label = ($t === 'buteur' || $t === 'scorer') ? 'SAFE BET · PROP' : 'SAFE BET';
        $sub = ($t === 'buteur' || $t === 'scorer') ? 'Prop joueur · pick confirmé' : 'Analyse validée · pick confirmé';
        $class = 'type-stamp';
    } elseif ($t === 'live') {
        $icon = "<div class='live-dot'></div>";
        $label = 'LIVE · EN DIRECT'; $sub = 'Bet en cours · tick live'; $class = 'type-stamp is-live';
    } elseif ($t === 'combi') {
        $icon = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' width='28' height='28'><path d='M10 13a5 5 0 007 0l4-4a5 5 0 00-7-7l-1 1'/><path d='M14 11a5 5 0 00-7 0l-4 4a5 5 0 007 7l1-1'/></svg>";
        $n = (int)$n_picks;
        $label = "COMBI<span class='combi-count'>×$n</span>";
        $sub = "$n sélections combinées"; $class = 'type-stamp';
    } else {
        $icon = "<svg viewBox='0 0 24 24' fill='currentColor' width='28' height='28'><path d='M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z'/></svg>";
        $label = 'FUN ZONE'; $sub = 'Longshot · cote bombe'; $class = 'type-stamp is-fun';
    }
    return "<div class='$class'><div class='type-stamp-icon'>$icon</div><div class='type-stamp-text'><div class='type-stamp-label'>$label</div><div class='type-stamp-sub'>$sub</div></div></div>";
}

function stratedge_crop_marks() {
    return "<div class='crop tl'></div><div class='crop tr'></div><div class='crop bl'></div><div class='crop br'></div>";
}

function stratedge_brand_header($date_fr, $badge_text) {
    $d = htmlspecialchars($date_fr, ENT_QUOTES, 'UTF-8');
    $b = htmlspecialchars($badge_text, ENT_QUOTES, 'UTF-8');
    return "<div class='top'>"
         . "<div><img class='brand-logo' src='/assets/images/logo_site_transparent.png' alt='STRATEDGE'><div class='brand-line'></div></div>"
         . "<div class='top-right'><div class='date-meta'>$d</div><div class='badge-sport'>$b</div></div>"
         . "</div>";
}

function stratedge_team_line($team_name, $flag, $sport, $explicit_logo = '') {
    $team_safe = htmlspecialchars((string)$team_name, ENT_QUOTES, 'UTF-8');
    $is_tennis = strtolower((string)$sport) === 'tennis';
    if ($is_tennis) {
        $flag_url = stratedge_flag_resolve($flag);
        if ($flag_url) return "<img class='flag' src='" . htmlspecialchars($flag_url, ENT_QUOTES, 'UTF-8') . "' alt=''>$team_safe";
        return $team_safe;
    }
    $logo_url = '';
    if ($explicit_logo && stratedge_is_valid_logo($explicit_logo)) {
        $logo_url = $explicit_logo;
    } else {
        $logo_url = stratedge_resolve_team_logo($team_name, $sport);
    }
    if ($logo_url) return "<img class='team-logo' src='" . htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') . "' alt=''>$team_safe";
    $flag_url = stratedge_flag_resolve($flag);
    if ($flag_url) return "<img class='flag' src='" . htmlspecialchars($flag_url, ENT_QUOTES, 'UTF-8') . "' alt=''>$team_safe";
    return $team_safe;
}

function stratedge_player_prop_solo($player_id, $sport, $player_name, $stats_hint, $opp_team_name, $opp_team_sport) {
    if (!function_exists('stratedge_player_photo')) return '';
    $photo = stratedge_player_photo($player_id, $sport);
    if (!$photo) return '';
    $name_safe = htmlspecialchars((string)$player_name, ENT_QUOTES, 'UTF-8');
    $stats_safe = htmlspecialchars((string)$stats_hint, ENT_QUOTES, 'UTF-8');
    $html  = "<div class='player-solo'>";
    $html .= "<img class='main' src='" . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') . "' alt=''>";
    $html .= "<div class='player-solo-label'><div class='psolo-name'>$name_safe</div>";
    if ($stats_safe) $html .= "<div class='psolo-stats'>$stats_safe</div>";
    $html .= "</div>";
    $opp_logo = stratedge_resolve_team_logo($opp_team_name, $opp_team_sport);
    if ($opp_logo) {
        $opp_short = htmlspecialchars(mb_substr((string)$opp_team_name, 0, 3, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $html .= "<div class='opp-badge'><img src='" . htmlspecialchars($opp_logo, ENT_QUOTES, 'UTF-8') . "' alt=''><div class='opp-label'>vs $opp_short</div></div>";
    }
    $html .= "</div>";
    return $html;
}

/** Version light (+40% vers blanc) d'une couleur hex pour gradient */
function _stratedge_gradient_light($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return '#ffffff';
    $r = min(255, hexdec(substr($hex,0,2)) + 80);
    $g = min(255, hexdec(substr($hex,2,2)) + 80);
    $b = min(255, hexdec(substr($hex,4,2)) + 80);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
/** Version dark (-40% vers noir) */
function _stratedge_gradient_dark($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return '#000000';
    $r = max(0, hexdec(substr($hex,0,2)) - 80);
    $g = max(0, hexdec(substr($hex,2,2)) - 80);
    $b = max(0, hexdec(substr($hex,4,2)) - 80);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/** htmlspecialchars + remplace espaces normaux par U+00A0 (non-breaking space littéral)
    → html2canvas préserve U+00A0 alors qu'il peut collapse &nbsp; entity */
function stratedge_nbsp_esc($text) {
    $safe = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    // Remplacer espaces simples par U+00A0 LITTÉRAL (pas l'entité HTML)
    return str_replace(' ', "\xC2\xA0", $safe);
}

function stratedge_normalize_data($d, $type) {
    $sport = $d['sport'] ?? '';
    $sport_lower = strtolower((string)$sport);
    $tipster = $d['tipster'] ?? null;
    if (!$tipster) {
        if ($sport_lower === 'tennis') $tipster = 'tennis';
        else if ($type === 'fun') $tipster = 'fun';
        else $tipster = 'multi';
    }

    $matches = [];
    if (!empty($d['matches']) && is_array($d['matches'])) {
        $matches = $d['matches'];
    } elseif (!empty($d['bets']) && is_array($d['bets'])) {
        // Format legacy Fun/Combi : bets[]
        foreach ($d['bets'] as $bet) {
            $matches[] = [
                't1' => $bet['player1'] ?? $bet['team1'] ?? $bet['t1'] ?? '',
                't2' => $bet['player2'] ?? $bet['team2'] ?? $bet['t2'] ?? '',
                'flag1' => $bet['flag1'] ?? '',
                'flag2' => $bet['flag2'] ?? '',
                'logo1' => $bet['team1_logo'] ?? $bet['logo1'] ?? '',
                'logo2' => $bet['team2_logo'] ?? $bet['logo2'] ?? '',
                'pick' => $bet['prono'] ?? $bet['pick'] ?? '',
                'cote' => $bet['cote'] ?? '',
                'comp' => $bet['competition'] ?? $bet['comp'] ?? '',
            ];
        }
        // Type auto: si plusieurs bets, c'est un combi
        if (count($matches) > 1 && $type === 'fun') {
            // garder type = fun mais afficher les picks en liste
        }
    } elseif ($type === 'combi' && !empty($d['selections']) && is_array($d['selections'])) {
        foreach ($d['selections'] as $sel) {
            $matches[] = [
                't1' => $sel['team1'] ?? $sel['t1'] ?? '',
                't2' => $sel['team2'] ?? $sel['t2'] ?? '',
                'flag1' => $sel['flag1'] ?? '',
                'flag2' => $sel['flag2'] ?? '',
                'logo1' => $sel['team1_logo'] ?? $sel['logo1'] ?? '',
                'logo2' => $sel['team2_logo'] ?? $sel['logo2'] ?? '',
                'pick' => $sel['prono'] ?? $sel['pick'] ?? '',
                'cote' => $sel['cote'] ?? '',
            ];
        }
    } else {
        $t1 = $d['player1'] ?? $d['team1'] ?? $d['t1'] ?? '';
        $t2 = $d['player2'] ?? $d['team2'] ?? $d['t2'] ?? '';
        // Fallback: parse $d['match'] = "A vs B"
        if ((!$t1 || !$t2) && !empty($d['match'])) {
            $parts = preg_split('/\s+vs?\s+/i', (string)$d['match']);
            if (count($parts) === 2) {
                if (!$t1) $t1 = trim($parts[0]);
                if (!$t2) $t2 = trim($parts[1]);
            }
        }
        if ($t1 || $t2) {
            $matches = [[
                't1' => $t1,
                't2' => $t2,
                'flag1' => $d['flag1'] ?? '',
                'flag2' => $d['flag2'] ?? '',
                'logo1' => $d['team1_logo'] ?? $d['logo1'] ?? '',
                'logo2' => $d['team2_logo'] ?? $d['logo2'] ?? '',
                'comp' => $d['competition'] ?? $d['comp'] ?? '',
            ]];
        }
    }

    $badge_text = $d['badge_text'] ?? '';
    if (!$badge_text) {
        $map = ['tennis'=>'Tennis · ATP','foot'=>'Foot','football'=>'Foot','basket'=>'Basket · NBA','nba'=>'Basket · NBA','hockey'=>'Hockey · NHL','nhl'=>'Hockey · NHL','mlb'=>'Baseball · MLB','baseball'=>'Baseball · MLB'];
        $badge_text = $map[$sport_lower] ?? ucfirst((string)$sport);
        if ($type === 'combi') $badge_text .= ' · Combi';
        elseif ($type === 'fun') $badge_text .= ' · Fun';
    }

    return [
        'tipster' => $tipster,
        'type' => $type,
        'sport' => $sport,
        'badge_text' => $badge_text,
        'n_edition' => $d['n_edition'] ?? date('Ymd'),
        'ghost' => $d['ghost'] ?? strtoupper(substr((string)$sport, 0, 3)),
        'kicker' => $d['kicker'] ?? 'Dossier du jour.',
        'date_fr' => $d['date_fr'] ?? date('l j F · Y'),
        'time_fr' => $d['time_fr'] ?? $d['heure'] ?? '20:00',
        'matches' => $matches,
        'pick_main' => $d['pick_main'] ?? $d['prono'] ?? '',
        'pick_accent' => $d['pick_accent'] ?? '',
        'pick_market' => $d['pick_market'] ?? $d['market'] ?? 'Marché · Pick',
        'cote' => $d['cote'] ?? '1.50',
        'value_pct' => $d['value_pct'] ?? 0,
        'confidence' => $d['confidence'] ?? 60,
        'quote_main' => $d['quote_main'] ?? 'La data parle.',
        'quote_accent' => $d['quote_accent'] ?? 'Le pick répond.',
        'is_player_prop' => !empty($d['is_player_prop']),
        'player_id' => $d['player_id'] ?? null,
        'player_name' => $d['player_name'] ?? '',
        'player_stats_hint' => $d['player_stats_hint'] ?? '',
        'player_prop_sport' => $d['player_prop_sport'] ?? $sport,
        'opp_team' => $d['opp_team'] ?? '',
    ];
}

function stratedge_build_card($d, $locked = false) {
    $data = $d;
    $tipster = $data['tipster'] ?? 'multi';
    $theme = stratedge_card_theme($tipster);
    $promo = stratedge_card_promo($tipster);
    $type = strtolower($data['type'] ?? 'safe');

    $conf = max(0, min(100, (int)($data['confidence'] ?? 60)));
    $css = stratedge_card_css($theme, $conf);

    $sport = $data['sport'] ?? '';
    $matches = $data['matches'] ?? [];
    $is_combi = ($type === 'combi') || (count($matches) > 1 && $type === 'fun');

    // Mascotte OR player-prop photo
    $mascot_html = '';
    if (!empty($data['is_player_prop']) && !empty($data['player_id'])) {
        $opp = $data['opp_team'];
        if (!$opp && !empty($matches[0])) { $opp = $matches[0]['t2'] ?? ''; }
        $prop = stratedge_player_prop_solo(
            $data['player_id'],
            $data['player_prop_sport'] ?? $sport,
            $data['player_name'] ?? '',
            $data['player_stats_hint'] ?? '',
            $opp,
            $sport
        );
        if ($prop) $mascot_html = $prop;
    }
    if (!$mascot_html) {
        $mc = 'mascot';
        if (strtolower((string)$tipster) === 'fun') $mc .= ' fun-pose';
        $mascot_html = "<img class='$mc' src='" . htmlspecialchars($theme['mascot'], ENT_QUOTES, 'UTF-8') . "' alt=''>";
    }

    // Match / combi block
    $match_section = '';
    $pick_html = '';
    if ($is_combi) {
        $rows = '';
        foreach ($matches as $m) {
            $t1 = stratedge_team_line($m['t1'] ?? '', $m['flag1'] ?? '', $sport, $m['logo1'] ?? '');
            $t2 = stratedge_team_line($m['t2'] ?? '', $m['flag2'] ?? '', $sport, $m['logo2'] ?? '');
            $pick_raw = htmlspecialchars((string)($m['pick'] ?? ''), ENT_QUOTES, 'UTF-8');
            // Combi pick en SVG (même fix que kicker - préserve espaces)
            $pick_chars = mb_strlen($m['pick'] ?? '', 'UTF-8');
            $pick_vb_w = max(220, $pick_chars * 10);
            $pick_svg = "<svg class='combi-pick-svg' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $pick_vb_w 26' preserveAspectRatio='xMinYMid meet'>"
                      . "<text x='0' y='19' font-family=\"'Instrument Serif', Georgia, serif\" font-style='italic' font-size='18' fill='#ede8e0' opacity='.85' xml:space='preserve'>$pick_raw</text>"
                      . "</svg>";
            $cote_txt = htmlspecialchars((string)($m['cote'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rows .= "<div class='combi-row'><div class='combi-teams'>$t1<span class='combi-sep'>vs</span>$t2</div><div class='combi-pick'>$pick_svg</div><div class='combi-cote'>$cote_txt</div></div>";
        }
        $pick_block_full = "<div class='pick combi'><div class='pick-eyebrow'>Sélections combinées · " . count($matches) . " picks</div><div class='combi-list'>$rows</div></div>";
    } else {
        if (!empty($matches[0])) {
            $m = $matches[0];
            $t1 = stratedge_team_line($m['t1'] ?? '', $m['flag1'] ?? '', $sport, $m['logo1'] ?? '');
            $t2 = stratedge_team_line($m['t2'] ?? '', $m['flag2'] ?? '', $sport, $m['logo2'] ?? '');
            $comp = htmlspecialchars((string)($m['comp'] ?? ''), ENT_QUOTES, 'UTF-8');
            $match_section = "<div class='match'><div class='team'>$t1</div><div class='vs'>versus</div><div class='team'>$t2</div><div class='comp'>$comp</div></div>";
        }
        $pm = stratedge_nbsp_esc($data['pick_main'] ?? '');
        $pa = stratedge_nbsp_esc($data['pick_accent'] ?? '');
        $pmkt = stratedge_nbsp_esc($data['pick_market'] ?? '');
        $pick_block_full = "<div class='pick'><div class='pick-eyebrow'>Le Pick</div><div class='pick-main'>{$pm} <span class='pick-accent'>$pa</span></div><div class='pick-market'>$pmkt</div></div>";
    }

    if ($locked) {
        $locked_label = $is_combi ? (count($matches) . " picks combinés — réservé abonnés") : "Pick réservé aux abonnés";
        $div_short = htmlspecialchars($theme['div_short'], ENT_QUOTES, 'UTF-8');
        $accent = $theme['accent'];
        $pick_html = "<div class='pick-locked'>"
                   . "<div class='padlock'><svg viewBox='0 0 24 24' fill='none' stroke='$accent' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' width='20' height='20'><rect x='4' y='10' width='16' height='12' rx='1'/><path d='M8 10V6a4 4 0 0 1 8 0v4'/></svg></div>"
                   . "<div class='locked-text'><div class='locked-eyebrow'>Le Pick · Contenu réservé</div><div class='locked-main'>Souscris au pack <span class='locked-accent'>$div_short</span></div><div class='locked-sub'>$locked_label</div></div>"
                   . "</div>";
        // CTA unlock en SVG pour préserver les espaces
        $cta_svg = "<svg class='footer-svg' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 36' preserveAspectRatio='xMinYMid meet'>"
                 . "<text x='0' y='26' font-family=\"'Instrument Serif', Georgia, serif\" font-style='italic' font-size='22' fill='#ede8e0' opacity='.85' xml:space='preserve'>"
                 . "<tspan>« Déverrouille l’analyse. </tspan>"
                 . "<tspan fill='$accent' opacity='1'>Le pick t’attend.</tspan>"
                 . "<tspan> »</tspan>"
                 . "</text></svg>";
        $footer_top = "<div class='cta-unlock'><div class='cta-unlock-text'>$cta_svg</div><div class='cta-arrow'></div></div>";
    } else {
        $pick_html = $pick_block_full;
        // Quote en SVG pour préserver les espaces (html2canvas + Instrument Serif italic = bug espaces)
        $qm_raw = htmlspecialchars((string)($data['quote_main'] ?? 'Analyse validée.'), ENT_QUOTES, 'UTF-8');
        $qa_raw = htmlspecialchars((string)($data['quote_accent'] ?? 'Le pick tient.'), ENT_QUOTES, 'UTF-8');
        $accent_hex = $theme['accent'];
        $quote_svg = "<svg class='footer-svg' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 700 40' preserveAspectRatio='xMinYMid meet'>"
                   . "<text x='0' y='28' font-family=\"'Instrument Serif', Georgia, serif\" font-style='italic' font-size='24' fill='#ede8e0' opacity='.75' xml:space='preserve'>"
                   . "<tspan>« $qm_raw </tspan>"
                   . "<tspan fill='$accent_hex' opacity='1'>$qa_raw</tspan>"
                   . "<tspan> »</tspan>"
                   . "</text></svg>";
        $footer_top = "<div class='quote'>$quote_svg</div>";
    }

    $n_edition_safe = htmlspecialchars((string)$data['n_edition'], ENT_QUOTES, 'UTF-8');
    $ghost_safe = htmlspecialchars((string)$data['ghost'], ENT_QUOTES, 'UTF-8');
    // Kicker en SVG pour préserver les espaces (html2canvas/Instrument Serif italic collapse parfois les espaces)
    $kicker_raw = htmlspecialchars((string)$data['kicker'], ENT_QUOTES, 'UTF-8');
    // Calcule une largeur approximative pour le viewBox (pour ne pas tronquer le texte long)
    $kicker_chars = mb_strlen($data['kicker'], 'UTF-8');
    $kicker_vb_width = max(280, $kicker_chars * 13); // ~13px par caractère en italique 24px
    $kicker_svg = "<svg class='kicker-svg' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $kicker_vb_width 36' preserveAspectRatio='xMinYMin meet'>"
                . "<text x='0' y='26' font-family=\"'Instrument Serif', Georgia, serif\" font-style='italic' font-size='26' fill='#ede8e0' opacity='.6' xml:space='preserve'>$kicker_raw</text>"
                . "</svg>";
    $kicker_safe = stratedge_nbsp_esc($data['kicker']); // fallback si SVG échoue
    $time_safe = htmlspecialchars((string)$data['time_fr'], ENT_QUOTES, 'UTF-8');
    $cote_safe = htmlspecialchars((string)$data['cote'], ENT_QUOTES, 'UTF-8');
    $value = (float)$data['value_pct'];
    $value_sign = ($value >= 0) ? '+' : '';
    $value_txt = htmlspecialchars(number_format($value, 1, '.', ''), ENT_QUOTES, 'UTF-8');

    // SVG pour l'heure avec vrai gradient (html2canvas rasterise le SVG correctement)
    $time_svg = "<svg class='time-svg' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 260 80' preserveAspectRatio='xMinYMax meet'>"
              . "<defs><linearGradient id='timegrad' x1='0%' y1='0%' x2='0%' y2='100%'>"
              . "<stop offset='0%' stop-color='" . _stratedge_gradient_light($theme['accent']) . "'/>"
              . "<stop offset='70%' stop-color='" . $theme['accent'] . "'/>"
              . "<stop offset='100%' stop-color='" . _stratedge_gradient_dark($theme['accent']) . "'/>"
              . "</linearGradient>"
              . "<filter id='timeglow' x='-20%' y='-20%' width='140%' height='140%'><feGaussianBlur stdDeviation='3'/><feMerge><feMergeNode/><feMergeNode/><feMergeNode in='SourceGraphic'/></feMerge></filter>"
              . "</defs>"
              . "<text x='0' y='68' font-family=\"Inter, 'Helvetica Neue', sans-serif\" font-weight='900' font-size='82' letter-spacing='-3' fill='url(#timegrad)' filter='url(#timeglow)'>$time_safe</text>"
              . "</svg>";

    $n_picks = $is_combi ? count($matches) : 0;
    $type_stamp = stratedge_type_stamp($type, $n_picks);
    $cote_label = $is_combi ? 'Cote totale' : 'Cote retenue';

    $court_lines = stratedge_court_lines($theme, $tipster);
    $noise = stratedge_noise_svg();
    $crop = stratedge_crop_marks();
    $header = stratedge_brand_header($data['date_fr'], $data['badge_text']);
    $edition_label = htmlspecialchars($theme['edition'], ENT_QUOTES, 'UTF-8');
    $promo_eye = htmlspecialchars($promo['eyebrow'], ENT_QUOTES, 'UTF-8');
    $promo_title = htmlspecialchars($promo['title'], ENT_QUOTES, 'UTF-8');
    $promo_price = htmlspecialchars($promo['price'], ENT_QUOTES, 'UTF-8');
    $promo_url = htmlspecialchars($promo['url'], ENT_QUOTES, 'UTF-8');

    return "<!DOCTYPE html>
<html lang='fr'>
<head><meta charset='UTF-8'><title>StratEdge · $n_edition_safe</title>
<style>$css</style></head>
<body>
<div class='card'>
  <div class='ghost'>$ghost_safe</div>
  $court_lines
  $mascot_html
  <div class='vignette'></div>
  $noise
  $crop
  <div class='edition'>Nº $n_edition_safe · $edition_label</div>
  <div class='inner'>
    $header
    <div class='kicker-wrap'>$kicker_svg</div>
    <div class='time-block'><div class='time-svg-wrap'>$time_svg</div><div class='time-label'>heure de Paris</div></div>
    $type_stamp
    $match_section$pick_html
    <div class='data-row'>
      <div class='data-cote'>
        <div class='data-label'>$cote_label</div>
        <div class='cote-val'>$cote_safe</div>
        <div class='value-pill'>◆ VALUE $value_sign$value_txt%</div>
      </div>
      <div class='conf'>
        <div class='conf-top'><div class='data-label'>Confiance</div><div class='conf-num'><strong>$conf</strong>/100</div></div>
        <div class='conf-bar'><div class='conf-fill'></div><div class='conf-dot'></div></div>
      </div>
    </div>
  </div>
  <div class='footer-abs'>
    $footer_top
    <div class='promo'>
      <div class='promo-left'>
        <div class='promo-eyebrow'>$promo_eye</div>
        <div class='promo-title'>{$promo_title} <span class='price'>$promo_price</span></div>
        <div class='promo-url'>$promo_url</div>
      </div>
      <a class='promo-cta' href='#'>S&apos;abonner <span class='arrow'></span></a>
    </div>
  </div>
</div>
</body></html>";
}

// ════════════════════════════════════════════════════════════
// 4 public entry points (called by generate-card.php)
// ════════════════════════════════════════════════════════════
function generateSafeCards($d) {
    $data = stratedge_normalize_data($d, 'safe');
    return ['html_normal' => stratedge_build_card($data, false),
            'html_locked' => stratedge_build_card($data, true)];
}
function generateLiveCards($d) {
    $data = stratedge_normalize_data($d, 'live');
    return ['html_normal' => stratedge_build_card($data, false),
            'html_locked' => stratedge_build_card($data, true)];
}
function generateFunCards($d) {
    $data = stratedge_normalize_data($d, 'fun');
    return ['html_normal' => stratedge_build_card($data, false),
            'html_locked' => stratedge_build_card($data, true)];
}
function generateSafeCombiCards($d) {
    $data = stratedge_normalize_data($d, 'combi');
    return ['html_normal' => stratedge_build_card($data, false),
            'html_locked' => stratedge_build_card($data, true)];
}
