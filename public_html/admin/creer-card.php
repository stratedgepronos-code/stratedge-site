<?php
// ============================================================
// STRATEDGE — creer-card.php — Générateur de cards via Claude
// V8 — Support Safe + Live + Fun avec formulaire dynamique
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'creer-card';
$db = getDB();
$adminRole = getAdminRole();
$isAdminFunSport = isAdminFunSport();
$isAdminTennis = isAdminTennis();
/**
 * Préfixe URL pour fetch() : doit correspondre à l’URL vue par le navigateur (ex. /panel-x9k3m).
 * Si on utilise SCRIPT_NAME seul, Apache peut exposer /admin/… → fetch vers /admin/*.php = 403 (admin/.htaccess).
 */
$__uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$__uriPath = str_replace('\\', '/', $__uriPath);
$seAdminFetchPrefix = rtrim(dirname($__uriPath !== '' ? $__uriPath : (str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
if ($seAdminFetchPrefix === '/admin' || preg_match('#/admin$#', $seAdminFetchPrefix)) {
    $seAdminFetchPrefix = preg_replace('#/admin$#', '/panel-x9k3m', $seAdminFetchPrefix);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer une Card — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Bebas+Neue&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    :root {
      --bg-dark:#050810; --bg-card:#0d1220; --neon-green:#ff2d78; --neon-green-dim:#d6245f;
      --neon-blue:#00d4ff; --text-primary:#f0f4f8; --text-secondary:#b0bec9;
      --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.12);
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; display:flex; }
    .main { margin-left:240px; flex:1; padding:2rem; min-height:100vh; }
    .page-header { margin-bottom:2rem; }
    .page-header h1 { font-family:'Orbitron',sans-serif; font-size:1.5rem; font-weight:700; display:flex; align-items:center; gap:0.7rem; }
    .page-header p { color:var(--text-muted); margin-top:0.3rem; font-size:0.9rem; }
    .creator-layout { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }
    .form-card {
      background:var(--bg-card); border:1px solid var(--border-subtle);
      border-radius:16px; padding:1.8rem; position:sticky; top:2rem;
    }
    .form-section-title {
      font-family:'Space Mono',monospace; font-size:0.62rem; letter-spacing:3px;
      text-transform:uppercase; color:var(--text-muted); margin:1.5rem 0 0.75rem;
      padding-bottom:0.4rem; border-bottom:1px solid rgba(255,255,255,0.05);
    }
    .form-section-title:first-child { margin-top:0; }
    .field { margin-bottom:1rem; }
    .field label {
      display:block; font-family:'Space Mono',monospace; font-size:0.62rem;
      letter-spacing:2px; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.4rem;
    }
    .field input, .field select, .field textarea {
      width:100%; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1);
      border-radius:8px; padding:0.7rem 0.9rem; color:var(--text-primary);
      font-family:'Rajdhani',sans-serif; font-size:0.95rem; outline:none; transition:border 0.2s;
    }
    .field input:focus, .field select:focus, .field textarea:focus { border-color:var(--neon-green); }
    .field select option { background:#0d1220; }
    .field textarea { resize:vertical; min-height:120px; line-height:1.5; }

    /* ── Type selector pills ── */
    .type-selector { display:flex; gap:0.5rem; margin-bottom:1rem; }
    .type-pill {
      flex:1; padding:0.7rem 0.5rem; border-radius:10px; border:1px solid rgba(255,255,255,0.1);
      background:rgba(255,255,255,0.03); cursor:pointer; text-align:center;
      font-family:'Orbitron',sans-serif; font-size:0.7rem; font-weight:700;
      letter-spacing:1px; text-transform:uppercase; color:var(--text-muted);
      transition:all 0.3s; user-select:none;
    }
    .type-pill:hover { border-color:rgba(255,255,255,0.25); color:var(--text-secondary); }
    .type-pill.active-safe {
      background:linear-gradient(135deg, rgba(0,212,255,0.15), rgba(0,255,136,0.1));
      border-color:rgba(0,212,255,0.5); color:#00d4ff;
      box-shadow:0 0 15px rgba(0,212,255,0.15);
    }
    .type-pill.active-live {
      background:linear-gradient(135deg, rgba(255,45,120,0.15), rgba(255,100,50,0.1));
      border-color:rgba(255,45,120,0.5); color:#ff2d78;
      box-shadow:0 0 15px rgba(255,45,120,0.15);
    }
    .type-pill.active-fun {
      background:linear-gradient(135deg, rgba(168,85,247,0.15), rgba(255,45,120,0.1));
      border-color:rgba(168,85,247,0.5); color:#a855f7;
      box-shadow:0 0 15px rgba(168,85,247,0.15);
    }
    .type-pill.active-safecombi {
      background:linear-gradient(135deg, rgba(0,212,255,0.12), rgba(255,45,122,0.12));
      border-color:rgba(0,229,255,0.5); color:#00e5ff;
      box-shadow:0 0 15px rgba(0,229,255,0.2);
    }

    /* ── Help box ── */
    .help-box {
      background:rgba(0,212,255,0.04); border:1px solid rgba(0,212,255,0.12);
      border-radius:8px; padding:0.8rem; margin-bottom:1rem; font-size:0.8rem;
      color:var(--text-muted); line-height:1.5;
    }
    .help-box strong { color:var(--text-secondary); }
    .help-box code {
      background:rgba(255,255,255,0.06); padding:0.15rem 0.4rem; border-radius:4px;
      font-family:'Space Mono',monospace; font-size:0.72rem; color:#00d4ff;
    }

    .btn-generate {
      width:100%; padding:1rem; margin-top:1.5rem;
      background:linear-gradient(135deg, var(--neon-green), var(--neon-green-dim));
      color:white; border:none; border-radius:10px;
      font-family:'Orbitron',sans-serif; font-size:0.85rem; font-weight:700;
      letter-spacing:1.5px; text-transform:uppercase; cursor:pointer;
      transition:all 0.3s; display:flex; align-items:center; justify-content:center; gap:0.6rem;
    }
    .btn-generate:hover:not(:disabled) { box-shadow:0 0 30px rgba(255,45,120,0.4); transform:translateY(-2px); }
    .btn-generate:disabled { opacity:0.5; cursor:not-allowed; transform:none; }
    .result-area {
      background:var(--bg-card); border:1px solid var(--border-subtle);
      border-radius:16px; padding:1.8rem; min-height:300px;
    }
    .result-title {
      font-family:'Orbitron',sans-serif; font-size:0.9rem; font-weight:700;
      margin-bottom:1.5rem; display:flex; align-items:center; gap:0.6rem;
    }
    .result-empty {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      min-height:250px; gap:1rem; color:var(--text-muted);
    }
    .result-empty .big-icon { font-size:3rem; opacity:0.3; }
    .result-empty p { font-size:0.9rem; text-align:center; }
    .loading-state {
      display:none; flex-direction:column; align-items:center; justify-content:center;
      min-height:250px; gap:1.5rem;
    }
    .spinner {
      width:50px; height:50px; border-radius:50%;
      border:3px solid rgba(255,45,120,0.15);
      border-top-color:var(--neon-green);
      animation:spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform:rotate(360deg); } }
    .loading-steps { text-align:center; }
    .loading-step { font-size:0.85rem; color:var(--text-muted); margin-bottom:0.3rem; transition:color 0.3s; }
    .loading-step.active { color:var(--neon-green); font-weight:600; }
    .loading-step.done { color:#00c864; }
    .loading-step.done::before { content:'✓ '; }
    .cards-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem; align-items:start; }
    .card-preview-wrap { display:flex; flex-direction:column; }
    .card-preview-label {
      font-family:'Space Mono',monospace; font-size:0.6rem; letter-spacing:2px;
      text-transform:uppercase; color:var(--text-muted); margin-bottom:0.75rem;
      display:flex; align-items:center; gap:0.5rem;
    }
    .card-preview-label .dot { width:7px; height:7px; border-radius:50%; }
    .card-img-preview {
      width:100%; border-radius:12px; border:1px solid rgba(255,255,255,0.08);
      cursor:pointer; transition:transform 0.2s;
      max-height:700px; object-fit:contain; object-position:top;
      background:#080A12;
    }
    .card-img-preview:hover { transform:scale(1.02); }
    .dl-buttons { display:flex; gap:1rem; flex-wrap:wrap; }
    .btn-dl {
      flex:1; min-width:160px; padding:0.85rem 1.2rem;
      border:none; border-radius:10px; cursor:pointer;
      font-family:'Orbitron',sans-serif; font-size:0.72rem; font-weight:700;
      letter-spacing:1px; text-transform:uppercase;
      display:flex; align-items:center; justify-content:center; gap:0.5rem;
      transition:all 0.3s; text-decoration:none;
    }
    .btn-dl-normal { background:linear-gradient(135deg, var(--neon-green), var(--neon-green-dim)); color:white; }
    .btn-dl-normal:hover { box-shadow:0 0 20px rgba(255,45,120,0.4); transform:translateY(-2px); }
    .btn-dl-locked { background:linear-gradient(135deg, #f5c842, #c8960c); color:#050810; }
    .btn-dl-locked:hover { box-shadow:0 0 20px rgba(245,200,66,0.4); transform:translateY(-2px); }
    .btn-dl-both { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:var(--text-secondary); }
    .btn-dl-both:hover { background:rgba(255,255,255,0.12); transform:translateY(-2px); }
    .error-box {
      display:none; background:rgba(255,45,120,0.08); border:1px solid rgba(255,45,120,0.3);
      border-radius:10px; padding:1rem 1.2rem; color:#ff6b9d; font-size:0.9rem; margin-top:1rem;
    }
    .btn-regen {
      background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12);
      color:var(--text-secondary); padding:0.6rem 1.2rem; border-radius:8px;
      font-family:'Rajdhani',sans-serif; font-size:0.9rem; cursor:pointer; transition:all 0.2s;
    }
    .btn-regen:hover { background:rgba(255,255,255,0.1); color:white; }

    /* ── Render zone (largeur dynamique via JS) ── */
    .render-zone {
      position:fixed; top:-99999px; left:-99999px;
      overflow:visible; pointer-events:none; opacity:0;
    }
    .render-zone iframe { border:none; display:block; }

    @media (max-width:1100px) { .creator-layout { grid-template-columns:1fr; } .form-card { position:static; } }
    @media (max-width:768px) { .main { margin-left:0; padding-top:68px; } .cards-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <input type="hidden" id="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
  <div class="page-header">
    <h1>🎨 Créer une Card</h1>
    <p>Choisissez le type de bet — Claude génère la card normale + locked en JPEG.</p>
  </div>

  <div class="creator-layout">
    <div class="form-card">

      <!-- ══ DETECTION AUTO via screenshot ══ -->
      <div class="form-section-title">📸 Import depuis capture d'écran</div>
      <div class="help-box" style="background:rgba(255,45,120,0.08);border-color:rgba(255,45,120,0.25);">
        <strong>🤖 Claude détecte automatiquement le pari depuis ta capture</strong><br>
        Upload une capture d'écran de bookmaker (Winamax, Betclic, Stake, Unibet, etc.)<br>
        Claude lit les équipes, marchés, cotes et remplit le formulaire en 5 sec.
      </div>
      <div class="field" style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
        <input type="file" id="f-screenshot" accept="image/jpeg,image/png,image/webp,image/gif" style="flex:1;min-width:0;background:rgba(255,255,255,0.04);padding:0.6rem;border-radius:8px;border:1px dashed rgba(255,45,120,0.4);color:var(--text-muted);cursor:pointer;">
        <button type="button" id="btn-detect" onclick="detectFromScreenshot()" style="background:linear-gradient(135deg,#ff2d78,#c4185a);color:#fff;border:none;padding:0.7rem 1.2rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;white-space:nowrap;">📸 Détecter</button>
      </div>
      <div id="detect-status" style="margin-top:0.5rem;font-size:0.85rem;color:var(--text-muted);min-height:20px;"></div>

      <!-- ══ SPORT ══ -->
      <div class="form-section-title">🎾 Sport</div>
      <div class="field">
        <label>Sport</label>
        <select id="f-sport">
          <?php if ($isAdminFunSport): ?>
          <option value="football">⚽ Foot</option>
          <option value="basket">🏀 NBA</option>
          <option value="hockey">🏒 NHL</option>
          <option value="baseball">⚾ MLB</option>
          <?php elseif ($isAdminTennis): ?>
          <option value="tennis" selected>🎾 Tennis</option>
          <?php else: ?>
          <option value="tennis">🎾 Tennis</option>
          <option value="football">⚽ Football</option>
          <option value="basket">🏀 Basket</option>
          <option value="hockey">🏒 Hockey</option>
          <option value="baseball">⚾ Baseball (MLB)</option>
          <?php endif; ?>
        </select>
      </div>

      <!-- ══ TYPE DE BET (pills) ══ -->
      <?php if (!$isAdminFunSport): ?>
      <div class="form-section-title">⚡ Type de Bet</div>
      <div class="type-selector">
        <div class="type-pill <?= $isAdminTennis ? '' : 'active-safe' ?>" data-type="Safe" onclick="selectType('Safe')">🛡️ Safe</div>
        <div class="type-pill" data-type="Live" onclick="selectType('Live')">🔴 Live</div>
        <div class="type-pill <?= $isAdminTennis ? '' : '' ?>" data-type="Fun" onclick="selectType('Fun')">🎲 Fun</div>
        <div class="type-pill" data-type="SafeCombi" onclick="selectType('SafeCombi')">🛡️⚡ Combi</div>
      </div>
      <?php else: ?>
      <input type="hidden" id="force-fun-type" value="1">
      <div class="form-section-title">⚡ Type</div>
      <p style="color:var(--text-muted);font-size:0.9rem;">🎯 Fun — Foot / NBA / NHL uniquement</p>
      <?php endif; ?>

      <!-- ══ FORMULAIRE SAFE (champs structurés) ══ -->
      <div id="form-safe">
        <div class="form-section-title">📊 Prono & Cote</div>
        <div class="field">
          <label>Match (Joueur A vs Joueur B)</label>
          <input type="text" id="f-match" placeholder="Ex: Djokovic vs Alcaraz">
        </div>
        <div class="field">
          <label>Prono (pari conseillé)</label>
          <input type="text" id="f-prono" placeholder="Ex: Djokovic gagne le match">
        </div>
        <div class="field">
          <label>Cote</label>
          <input type="number" id="f-cote" step="0.01" min="1" placeholder="Ex: 1.85">
        </div>
      </div>

      <!-- ══ FORMULAIRE LIVE (champs structurés : match + prono + cote) ══ -->
      <div id="form-live" style="display:none">
        <div class="form-section-title">🔴 Match Live</div>
        <div class="field">
          <label>Match</label>
          <input type="text" id="f-live-match" placeholder="Ex: Garin vs Baez">
        </div>
        <div class="field">
          <label>Pronostic</label>
          <input type="text" id="f-live-prono" placeholder="Ex: Garin gagne 1 set">
        </div>
        <div class="field">
          <label>Cote</label>
          <input type="number" id="f-live-cote" step="0.01" min="1" placeholder="Ex: 1.58">
        </div>
        <div class="help-box">
          💡 Claude trouvera automatiquement le sport, la compétition, les drapeaux, la date et l'heure du match.
        </div>
      </div>

      <!-- ══ FORMULAIRE FUN (textarea multi-matchs avec cotes) ══ -->
      <div id="form-fun" style="display:none">
        <div class="form-section-title">🎲 Combiné Fun Bet</div>
        <div class="help-box">
          <strong>Entrez chaque match avec son prono et sa cote :</strong><br>
          <code>Match 1 : Genk vs Zagreb</code><br>
          <code>Prono 1 : Les 2 marquent + corners</code><br>
          <code>Cote 1 : 2.95</code><br><br>
          <code>Match 2 : Celta vs PAOK</code><br>
          <code>Prono 2 : +0.5 Celta 2ème MT</code><br>
          <code>Cote 2 : 2.49</code><br><br>
          💡 Claude trouvera les dates, heures, drapeaux et compétitions automatiquement.
        </div>
        <div class="field">
          <label>Matchs + Pronos + Cotes</label>
          <textarea id="f-raw-fun" rows="14" placeholder="Match 1 : ...&#10;Prono 1 : ...&#10;Cote 1 : ...&#10;&#10;Match 2 : ...&#10;Prono 2 : ...&#10;Cote 2 : ..."></textarea>
        </div>
      </div>

      <!-- ══ FORMULAIRE SAFE COMBINÉ (multi-bets Safe) ══ -->
      <div id="form-safecombi" style="display:none">
        <div class="form-section-title">🛡️ Safe Combiné</div>
        <div class="help-box">
          <strong>Combinez 2 à 5 bets Safe sur une seule card :</strong><br>
          <code>Match 1 : PSG vs Marseille</code><br>
          <code>Prono 1 : Victoire PSG</code><br>
          <code>Cote 1 : 1.65</code><br><br>
          <code>Match 2 : Djokovic vs Alcaraz</code><br>
          <code>Prono 2 : Djokovic gagne le match</code><br>
          <code>Cote 2 : 1.85</code><br><br>
          🛡️ Claude analysera chaque bet individuellement (confiance, value, analyse) puis calculera la confiance globale du combiné.
        </div>
        <div class="field">
          <label>Matchs + Pronos + Cotes (Safe)</label>
          <textarea id="f-raw-safecombi" rows="14" placeholder="Match 1 : ...&#10;Prono 1 : ...&#10;Cote 1 : ...&#10;&#10;Match 2 : ...&#10;Prono 2 : ...&#10;Cote 2 : ...&#10;&#10;Match 3 : ...&#10;Prono 3 : ...&#10;Cote 3 : ..."></textarea>
        </div>
      </div>

      <!-- ══ Analyse HTML (optionnel) ══ -->
      <div class="form-section-title">📄 Analyse HTML (optionnel)</div>
      <div class="help-box">
        Colle ici l'analyse HTML complète. Elle sera affichée sur la page du bet en dessous de la card.
      </div>
      <div class="field">
        <textarea id="f-analyse-html" rows="5" placeholder="Contenu HTML affiché sur la page du bet (analyse détaillée, stats, xG…). Optionnel."></textarea>
      </div>

      <button class="btn-generate" id="btn-generate" onclick="generateCard()">
        <span id="btn-icon">✨</span>
        <span id="btn-text">Générer les Cards</span>
      </button>
    </div>

    <!-- ══ RÉSULTAT ══ -->
    <div class="result-area">
      <div class="result-empty" id="state-empty">
        <div class="big-icon">🎨</div>
        <p>Remplissez le formulaire et cliquez sur <strong>Générer</strong>.<br>Claude créera la card normale + locked en JPEG.</p>
      </div>

      <div class="loading-state" id="state-loading">
        <div class="spinner"></div>
        <div class="loading-steps">
          <div class="loading-step" id="step1">Envoi des données à Claude...</div>
          <div class="loading-step" id="step2">Analyse du match & statistiques...</div>
          <div class="loading-step" id="step3">Génération de la card normale...</div>
          <div class="loading-step" id="step4">Génération de la card locked...</div>
          <div class="loading-step" id="step5">Conversion HTML → JPEG...</div>
          <div class="loading-step" id="step6">Finalisation ✓</div>
        </div>
      </div>

      <div id="state-result" style="display:none">
        <div class="result-title">
          ✅ Cards générées !
          <button class="btn-regen" onclick="generateCard()" style="margin-left:auto">🔄 Régénérer</button>
        </div>
        <div class="cards-grid">
          <div class="card-preview-wrap">
            <div class="card-preview-label">
              <span class="dot" style="background:#00c864"></span>
              Card normale
            </div>
            <img id="img-normal" class="card-img-preview" alt="Card normale">
          </div>
          <div class="card-preview-wrap">
            <div class="card-preview-label">
              <span class="dot" style="background:#f5c842"></span>
              Card locked
            </div>
            <img id="img-locked" class="card-img-preview" alt="Card locked">
          </div>
        </div>
        <div class="dl-buttons">
          <a class="btn-dl btn-dl-normal" id="dl-normal" download>⬇️ Normal (.jpg)</a>
          <a class="btn-dl btn-dl-locked" id="dl-locked" download>⬇️ Locked (.jpg)</a>
          <button class="btn-dl btn-dl-both" onclick="downloadBoth()">⬇️ Les Deux</button>
          <button class="btn-dl btn-dl-both" type="button" id="btn-copy-html" onclick="copyHtmlToClipboard()" title="Copie le HTML pour le coller dans Poster bet (analyse page bet)">📋 Copier le HTML</button>
          <button class="btn-dl btn-dl-normal" type="button" id="btn-poster-bet" onclick="posterBetFromCard()" title="Enregistre le bet avec cette card + analyse HTML (tout automatique)">🚀 Poster le bet</button>
        </div>
        <p id="poster-bet-status" style="display:none; margin-top:0.8rem; font-size:0.9rem; color:var(--text-muted);"></p>
      </div>
      <div class="error-box" id="state-error"></div>
    </div>
  </div>
</div>

<!-- Iframes cachées pour rendu HTML → JPEG -->
<div class="render-zone" id="render-zone">
  <iframe id="render-normal"></iframe>
  <iframe id="render-locked"></iframe>
</div>

<script>
// Préfixe dossier courant (évite fetch vers la mauvaise URL si chemin atypique)
const SE_ADMIN_FETCH_PREFIX = <?php echo json_encode($seAdminFetchPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function adminFetchUrl(name) {
  const rel = String(name || '').replace(/^\//, '');
  const base = (typeof SE_ADMIN_FETCH_PREFIX === 'string') ? SE_ADMIN_FETCH_PREFIX.replace(/\/$/, '') : '';
  if (base && base !== '.') return base + '/' + rel;
  return rel;
}

// ── Polices embarquées (same-origin) : évite CSP connect-src sans fonts.googleapis.com ──
let _fontsBase64Css;

/**
 * Récupère le CSS @font-face en base64 via le serveur (card-fonts-css.php).
 * Ne dépend pas d'un fetch vers Google depuis le navigateur.
 */
async function loadFontsAsBase64() {
  if (_fontsBase64Css !== undefined) return _fontsBase64Css;
  try {
    const resp = await fetch(adminFetchUrl('card-fonts-css.php'), { credentials: 'same-origin' });
    if (!resp.ok) {
      console.warn('card-fonts-css.php HTTP', resp.status);
      _fontsBase64Css = '';
      return _fontsBase64Css;
    }
    const text = await resp.text();
    _fontsBase64Css = (text && text.trim()) ? text : '';
    if (_fontsBase64Css.length) {
      console.log('Polices card (serveur):', _fontsBase64Css.length, 'car.');
    }
    return _fontsBase64Css;
  } catch (e) {
    console.warn('Polices card indisponibles:', e);
    _fontsBase64Css = '';
    return _fontsBase64Css;
  }
}

/** data: / blob: → Blob sans fetch() (contourne connect-src sans data:) */
function dataUrlToBlob(dataUrl) {
  if (typeof dataUrl !== 'string') throw new Error('URL image invalide');
  const i = dataUrl.indexOf(',');
  if (i === -1) throw new Error('URL image invalide');
  const meta = dataUrl.substring(0, i);
  const b64 = dataUrl.substring(i + 1).replace(/\s/g, '');
  const mimeMatch = meta.match(/data:([^;]+)/);
  const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
  const binary = atob(b64);
  const len = binary.length;
  const bytes = new Uint8Array(len);
  for (let j = 0; j < len; j++) bytes[j] = binary.charCodeAt(j);
  return new Blob([bytes], { type: mime });
}

async function jpegUrlToBlob(url) {
  if (typeof url === 'string' && url.indexOf('data:') === 0) {
    return dataUrlToBlob(url);
  }
  const r = await fetch(url);
  return r.blob();
}

// ── Variables globales ──────────────────────────────────────
let jpegNormalUrl = '';
let jpegLockedUrl = '';
let currentMatchName = '';  // for file downloads (underscored)
let currentMatchTitle = ''; // for bet title (clean, with spaces)
let currentType = 'Safe';
let lastGeneratedHtml = ''; // HTML de la card (pour page bet / poster-bet)
let lastGeneratedCote = ''; // Cote retournée par generate-card (pour poster-bet)

// Largeurs par type de card
const CARD_WIDTHS = { Safe: 1080, Live: 720, Fun: 1080, SafeCombi: 1440 };

// ── Sélection du type de bet ────────────────────────────────
function selectType(type) {
  currentType = type;
  document.querySelectorAll('.type-pill').forEach(p => { p.className = 'type-pill'; });
  const pill = document.querySelector(`.type-pill[data-type="${type}"]`);
  if (pill) pill.classList.add('active-' + type.toLowerCase());
  document.getElementById('form-safe').style.display      = (type === 'Safe') ? 'block' : 'none';
  document.getElementById('form-live').style.display      = (type === 'Live') ? 'block' : 'none';
  document.getElementById('form-fun').style.display       = (type === 'Fun')  ? 'block' : 'none';
  const fsb = document.getElementById('form-safecombi');
  if (fsb) fsb.style.display = (type === 'SafeCombi') ? 'block' : 'none';
}

// Admin Fun Sport : forcer type Fun au chargement
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('force-fun-type')) {
    currentType = 'Fun';
    document.getElementById('form-fun').style.display = 'block';
    document.getElementById('form-safe').style.display = 'none';
    document.getElementById('form-live').style.display = 'none';
    if (document.getElementById('form-safecombi')) document.getElementById('form-safecombi').style.display = 'none';
  }
});

// ──────────────────────────────────────────────────────────────
// 📸 DETECTION AUTO depuis screenshot bookmaker (Claude vision)
// ──────────────────────────────────────────────────────────────
async function detectFromScreenshot() {
  const fileInput = document.getElementById('f-screenshot');
  const statusEl = document.getElementById('detect-status');
  const btn = document.getElementById('btn-detect');

  if (!fileInput.files || fileInput.files.length === 0) {
    statusEl.style.color = '#ff2d78';
    statusEl.textContent = '⚠️ Sélectionne une capture d\'écran d\'abord.';
    return;
  }

  const file = fileInput.files[0];
  if (file.size > 5 * 1024 * 1024) {
    statusEl.style.color = '#ff2d78';
    statusEl.textContent = '⚠️ Image trop lourde (max 5MB). Compresse-la d\'abord.';
    return;
  }

  // UI loading
  btn.disabled = true;
  btn.textContent = '🔍 Analyse en cours...';
  statusEl.style.color = 'var(--text-muted)';
  statusEl.textContent = '🤖 Claude analyse la capture...';

  const csrf = document.getElementById('csrf_token').value;
  const fd = new FormData();
  fd.append('csrf_token', csrf);
  fd.append('screenshot', file);

  try {
    const r = await fetch('/admin/scanner-img.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    });
    const data = await r.json();

    if (!data.success) {
      statusEl.style.color = '#ff2d78';
      statusEl.textContent = '❌ ' + (data.error || 'Détection échouée');
      btn.disabled = false;
      btn.textContent = '📸 Détecter';
      return;
    }

    if (!data.matchs || data.matchs.length === 0) {
      statusEl.style.color = '#ff2d78';
      statusEl.textContent = '❌ Aucun match détecté dans cette image.';
      btn.disabled = false;
      btn.textContent = '📸 Détecter';
      return;
    }

    // Remplir le formulaire selon le nb de paris
    fillFormFromDetection(data);

    // Status final
    const nb = data.matchs.length;
    const cote = data.cote_totale || data.matchs[0].cote;
    const bookmaker = data.bookmaker_detecte ? ` (${data.bookmaker_detecte})` : '';
    statusEl.style.color = '#39ff14';
    statusEl.textContent = `✅ ${nb} pari${nb>1?'s':''} détecté${nb>1?'s':''}${bookmaker} · Cote totale ${cote} · Vérifie/modifie puis génère.`;
  } catch (e) {
    statusEl.style.color = '#ff2d78';
    statusEl.textContent = '❌ Erreur réseau : ' + e.message;
  } finally {
    btn.disabled = false;
    btn.textContent = '📸 Détecter';
  }
}

function fillFormFromDetection(data) {
  const matchs = data.matchs;
  const isCombine = matchs.length > 1;
  const typeSuggere = (data.type_suggere || 'safe').toLowerCase();

  // 1. Sport: prendre celui du 1er match, MAIS respecter les restrictions admin
  // Admin Fun Sport: jamais tennis (force football si tennis detecte)
  // Admin Tennis: toujours tennis
  let sport = matchs[0].sport || 'football';
  const isAdminFunSport = !!document.getElementById('force-fun-type');
  const sportSel = document.getElementById('f-sport');
  if (sportSel) {
    // Verifier si le sport detecte est disponible dans le select
    let sportAvailable = false;
    for (const opt of sportSel.options) {
      if (opt.value === sport) { sportAvailable = true; break; }
    }
    // Si pas dispo (ex: admin Fun + tennis detecte), prendre le 1er option du select
    if (!sportAvailable) {
      sport = sportSel.options[0].value;
      console.warn('Sport detecte (' + (matchs[0].sport || '?') + ') non autorise pour cet admin, fallback sur ' + sport);
    }
    sportSel.value = sport;
  }

  // 2. Type: si combine de safes -> SafeCombi, si fun (cote >5) -> Fun, sinon Safe ou Live
  let typeUI = 'Safe';
  if (typeSuggere === 'live') typeUI = 'Live';
  else if (typeSuggere === 'fun') typeUI = 'Fun';
  else if (isCombine) typeUI = 'SafeCombi';
  else typeUI = 'Safe';

  // Si admin Fun Sport, forcer Fun (il ne peut pas faire autre chose)
  if (isAdminFunSport) typeUI = 'Fun';

  // Activer le bon type (pill) et afficher le bon formulaire
  if (typeof selectType === 'function' && document.querySelector(`.type-pill[data-type="${typeUI}"]`)) {
    selectType(typeUI);
  }

  // 3. Remplir les champs selon le type
  if (typeUI === 'Safe' && !isCombine) {
    const m = matchs[0];
    const $ = id => document.getElementById(id);
    if ($('f-match'))  $('f-match').value  = m.equipes || '';
    if ($('f-prono'))  $('f-prono').value  = m.marche || '';
    if ($('f-cote'))   $('f-cote').value   = m.cote || '';
  } else if (typeUI === 'Live' && !isCombine) {
    const m = matchs[0];
    const $ = id => document.getElementById(id);
    if ($('f-live-match')) $('f-live-match').value = m.equipes || '';
    if ($('f-live-prono')) $('f-live-prono').value = m.marche || '';
    if ($('f-live-cote'))  $('f-live-cote').value  = m.cote || '';
  } else {
    // Combiné (Fun ou SafeCombi) : remplir le textarea raw
    const lines = [];
    matchs.forEach((m, i) => {
      const idx = i + 1;
      lines.push(`Match ${idx} : ${m.equipes || ''}`);
      lines.push(`Prono ${idx} : ${m.marche || ''}`);
      lines.push(`Cote ${idx} : ${m.cote || ''}`);
      lines.push('');
    });
    const txt = lines.join('\n').trim();
    if (typeUI === 'Fun') {
      const ta = document.getElementById('f-raw-fun');
      if (ta) ta.value = txt;
    } else if (typeUI === 'SafeCombi') {
      const ta = document.getElementById('f-raw-safecombi');
      if (ta) ta.value = txt;
    }
  }
}

// ──────────────────────────────────────────────────────────────

// ── Génération de la card ───────────────────────────────────
async function generateCard() {
  let payload = {
    sport:    document.getElementById('f-sport').value,
    type_bet: currentType,
  };

  // Valider et construire le payload selon le type
  if (currentType === 'Safe') {
    const match = document.getElementById('f-match').value.trim();
    const prono = document.getElementById('f-prono').value.trim();
    const cote  = document.getElementById('f-cote').value.trim();
    if (!match || !prono || !cote) {
      showError('⚠️ Remplissez au minimum : Match, Prono et Cote.');
      return;
    }
    payload.match = match;
    payload.prono = prono;
    payload.cote  = cote;
    currentMatchName = match.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '');
    currentMatchTitle = match;

  } else if (currentType === 'Live') {
    const match = document.getElementById('f-live-match').value.trim();
    const prono = document.getElementById('f-live-prono').value.trim();
    const cote  = document.getElementById('f-live-cote').value.trim();
    if (!match || !prono || !cote) {
      showError('⚠️ Remplissez : Match, Prono et Cote.');
      return;
    }
    payload.match = match;
    payload.prono = prono;
    payload.cote  = cote;
    currentMatchName = match.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '') + '_Live';
    currentMatchTitle = match + ' (Live)';

  } else if (currentType === 'Fun') {
    const raw = document.getElementById('f-raw-fun').value.trim();
    if (!raw) {
      showError('⚠️ Collez les infos du combiné Fun Bet.');
      return;
    }
    payload.raw_bet = raw;
    currentMatchName = 'FunBet_Combine';
    currentMatchTitle = 'Fun Bet Combiné';

  } else if (currentType === 'SafeCombi') {
    const raw = document.getElementById('f-raw-safecombi').value.trim();
    if (!raw) {
      showError('⚠️ Collez les infos du Safe Combiné (matchs + pronos + cotes).');
      return;
    }
    payload.type_bet = 'Safe Combiné';
    payload.raw_bet = raw;
    currentMatchName = 'SafeCombi';
    currentMatchTitle = 'Safe Combiné';
  }

  // Nettoyer les styles de card précédents injectés dans le head
  document.querySelectorAll('[data-card-style]').forEach(e => e.remove());

  setState('loading');
  setGenerateBtn(true);
  startLoadingAnimation();

  // Mettre la bonne largeur dans la zone de rendu
  const cardW = CARD_WIDTHS[currentType];
  const renderZone = document.getElementById('render-zone');
  renderZone.style.width = cardW + 'px';
  document.getElementById('render-normal').style.width = cardW + 'px';
  document.getElementById('render-locked').style.width = cardW + 'px';

  try {
    const controller = new AbortController();
    const fetchTimeout = setTimeout(() => controller.abort(), 240000);
    const resp = await fetch(adminFetchUrl('generate-card.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller.signal,
      credentials: 'same-origin'
    });
    clearTimeout(fetchTimeout);

    let data;
    const text = await resp.text();
    console.log('[generate-card] HTTP', resp.status, '| Réponse (' + text.length + ' car.):', text.substring(0, 500));
    try {
      data = JSON.parse(text);
    } catch (_) {
      showError('❌ Le serveur a renvoyé une erreur (HTTP ' + resp.status + '). Réponse non-JSON : ' + escHtml(text.substring(0, 300)) + (text.length > 300 ? '…' : ''));
      return;
    }

    if (!resp.ok || data.error) {
      let msg = '❌ ' + (data.error || 'Erreur inconnue.');
      if (data.error_code === 'claude_overloaded' || resp.status === 529 || /overloaded|529/i.test(String(data.detail || ''))) {
        msg = '⏳ <strong>Claude est saturé</strong> (erreur côté Anthropic, pas un souci de clé API manquante). Réessaie dans 1 à 2 minutes.';
        if (data.detail) msg += '<br><small style="opacity:0.65">' + escHtml(String(data.detail).substring(0, 200)) + '</small>';
      }
      if (data.file || data.line) msg += '<br><small style="opacity:0.7">' + (data.file || '') + (data.line ? ':' + data.line : '') + '</small>';
      if (data.raw) msg += '<br><small style="opacity:0.6">' + escHtml(data.raw) + '</small>';
      if (data.path) msg += '<br><small style="opacity:0.6">path: ' + escHtml(String(data.path)) + '</small>';
      showError(msg);
      return;
    }

    lastGeneratedHtml = data.html_normal || '';
    lastGeneratedCote = data.cote || '';
    // Utiliser la largeur retournée par le backend (au cas où)
    const w = data.card_width || cardW;

    // Conversion HTML → JPEG
    markStep(5);

    await injectAndWait('render-normal', data.html_normal);
    jpegNormalUrl = await captureIframeToJpeg('render-normal', w);

    await injectAndWait('render-locked', data.html_locked);
    jpegLockedUrl = await captureIframeToJpeg('render-locked', w);

    markStep(6);

    document.getElementById('img-normal').src = jpegNormalUrl;
    document.getElementById('img-locked').src = jpegLockedUrl;

    const dlN = document.getElementById('dl-normal');
    dlN.href = jpegNormalUrl;
    dlN.download = currentMatchName + '_normal.jpg';

    const dlL = document.getElementById('dl-locked');
    dlL.href = jpegLockedUrl;
    dlL.download = currentMatchName + '_locked.jpg';

    // Titre et cote déduits automatiquement des champs card (plus de champs doublons)

    setState('result');

  } catch(e) {
    showError('❌ Erreur : ' + e.message);
  } finally {
    setGenerateBtn(false);
    stopLoadingAnimation();
  }
}

// ── Injection HTML dans iframe ──────────────────────────────
// ── Injection HTML dans un DIV de la page parent ───────────
// Pas d'iframe = pas de problème d'origine CORS.
// Les fonts (Orbitron, Bebas Neue, Rajdhani) sont déjà chargées
// par le <link> dans le <head> de cette page.
async function injectAndWait(iframeId, html) {
  // ── Injecter les fonts base64 dans le HTML de la card ──────
  // Le navigateur parent télécharge les fonts, les encode en base64,
  // puis on les injecte directement dans le <style> de l'iframe.
  // Aucune requête réseau depuis l'iframe → zéro CORS.
  const fontsCss = await loadFontsAsBase64();
  if (fontsCss) {
    if (html.indexOf('</style>') !== -1) {
      html = html.replace('</style>', fontsCss + '\n</style>');
    } else if (html.indexOf('</head>') !== -1) {
      html = html.replace('</head>', '<style type="text/css">' + fontsCss + '</style>\n</head>');
    }
    html = html.replace(/<link[^>]*href=["']?https?:\/\/fonts\.googleapis\.com[^>]*>/gi, '');
  }

  return new Promise((resolve) => {
    const iframe = document.getElementById(iframeId);
    iframe.onload = () => {
      const iDoc = iframe.contentDocument || iframe.contentWindow.document;
      // Attendre les images (logo, mascotte)
      const imgs = iDoc.querySelectorAll('img');
      const waitImages = () => {
        if (imgs.length === 0) return Promise.resolve();
        return Promise.all(Array.from(imgs).map(img => {
          if (img.complete) return Promise.resolve();
          return new Promise(r => { img.onload = r; img.onerror = r; });
        }));
      };
      const waitFonts = () => {
        if (iDoc.fonts && iDoc.fonts.ready) return iDoc.fonts.ready;
        return Promise.resolve();
      };
      waitImages().then(() => waitFonts()).then(() => {
        // Délai supplémentaire pour que le rendu applique bien les fonts avant capture
        setTimeout(resolve, 400);
      });
      setTimeout(resolve, 5000); // timeout sécurité max
    };
    iframe.srcdoc = html;
  });
}


async function captureIframeToJpeg(iframeId, cardWidth) {
  const iframe = document.getElementById(iframeId);
  const iDoc   = iframe.contentDocument || iframe.contentWindow.document;
  const body   = iDoc.body;

  body.style.margin   = '0';
  body.style.padding  = '0';
  body.style.overflow = 'hidden';
  body.style.width    = cardWidth + 'px';

  let cardEl = body.querySelector('.card-wrapper')
            || body.querySelector('.card')
            || body.querySelector('[class*="wrapper"]')
            || body.querySelector('body > div')
            || body;

  // Attendre que les fonts soient appliquées avant html2canvas
  if (iDoc.fonts && iDoc.fonts.ready) await iDoc.fonts.ready;
  await new Promise(r => setTimeout(r, 300));

  const realHeight = Math.max(cardEl.scrollHeight, cardEl.offsetHeight, 300);

  iframe.style.width  = cardWidth + 'px';
  iframe.style.height = realHeight + 'px';

  await new Promise(r => setTimeout(r, 200));

  const canvas = await html2canvas(cardEl, {
    width:          cardWidth,
    height:         realHeight,
    backgroundColor: '#0a0a0a',
    scale:           1,
    useCORS:         true,
    allowTaint:      true,
    logging:         false,
    imageTimeout:    8000,
  });

  // Crop intelligent (enlever bandes noires)
  const ctx       = canvas.getContext('2d');
  const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const pixels    = imageData.data;

  let topCrop = 0;
  for (let y = 0; y < canvas.height; y++) {
    let hasContent = false;
    for (let x = 0; x < canvas.width; x += 4) {
      const i = (y * canvas.width + x) * 4;
      if (pixels[i] > 15 || pixels[i+1] > 15 || pixels[i+2] > 15) { hasContent = true; break; }
    }
    if (hasContent) { topCrop = y; break; }
  }

  let bottomCrop = canvas.height;
  for (let y = canvas.height - 1; y >= topCrop; y--) {
    let hasContent = false;
    for (let x = 0; x < canvas.width; x += 4) {
      const i = (y * canvas.width + x) * 4;
      if (pixels[i] > 15 || pixels[i+1] > 15 || pixels[i+2] > 15) { hasContent = true; break; }
    }
    if (hasContent) { bottomCrop = y + 1; break; }
  }
  bottomCrop = Math.min(bottomCrop + 2, canvas.height);
  const croppedHeight = bottomCrop - topCrop;

  let leftCrop = 0;
  for (let x = 0; x < canvas.width; x++) {
    let hasContent = false;
    for (let y = topCrop; y < bottomCrop; y++) {
      const i = (y * canvas.width + x) * 4;
      if (pixels[i] > 15 || pixels[i+1] > 15 || pixels[i+2] > 15) { hasContent = true; break; }
    }
    if (hasContent) { leftCrop = x; break; }
  }

  let rightCrop = canvas.width;
  for (let x = canvas.width - 1; x >= leftCrop; x--) {
    let hasContent = false;
    for (let y = topCrop; y < bottomCrop; y++) {
      const i = (y * canvas.width + x) * 4;
      if (pixels[i] > 15 || pixels[i+1] > 15 || pixels[i+2] > 15) { hasContent = true; break; }
    }
    if (hasContent) { rightCrop = x + 1; break; }
  }
  const croppedWidth = rightCrop - leftCrop;

  if (croppedHeight > 100 && croppedWidth > 100 && (topCrop > 5 || (canvas.height - bottomCrop) > 5 || leftCrop > 5 || (canvas.width - rightCrop) > 5)) {
    const cropped    = document.createElement('canvas');
    cropped.width    = croppedWidth;
    cropped.height   = croppedHeight;
    const croppedCtx = cropped.getContext('2d');
    croppedCtx.fillStyle = '#0a0a0a';
    croppedCtx.fillRect(0, 0, croppedWidth, croppedHeight);
    croppedCtx.drawImage(canvas, leftCrop, topCrop, croppedWidth, croppedHeight, 0, 0, croppedWidth, croppedHeight);
    return cropped.toDataURL('image/jpeg', 0.92);
  }
  return canvas.toDataURL('image/jpeg', 0.92);
}


// ── Télécharger les deux ────────────────────────────────────
function downloadBoth() {
  document.getElementById('dl-normal').click();
  setTimeout(() => document.getElementById('dl-locked').click(), 500);
}

// ── Copier le HTML (pour coller dans Poster bet → analyse page bet) ──
function copyHtmlToClipboard() {
  if (!lastGeneratedHtml) {
    alert('Génère d\'abord une card pour avoir le HTML à copier.');
    return;
  }
  navigator.clipboard.writeText(lastGeneratedHtml).then(function() {
    var btn = document.getElementById('btn-copy-html');
    if (btn) { btn.textContent = '✓ Copié !'; setTimeout(function() { btn.textContent = '📋 Copier le HTML'; }, 2000); }
  }).catch(function() { alert('Copie échouée.'); });
}

// ── Poster le bet automatiquement (image + HTML + infos) ──
async function posterBetFromCard() {
  if (!jpegNormalUrl || !jpegLockedUrl) {
    alert('Génère d\'abord une card avant de poster le bet.');
    return;
  }
  const btn = document.getElementById('btn-poster-bet');
  const statusEl = document.getElementById('poster-bet-status');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Envoi…'; }
  if (statusEl) { statusEl.style.display = 'block'; statusEl.textContent = 'Envoi du bet en cours…'; statusEl.style.color = 'var(--text-muted)'; }

  // Titre = match name clean (sans underscores)
  const titre = currentMatchTitle || 'Bet StratEdge';
  const typeMap = { Safe: 'safe', Live: 'live', Fun: 'fun', SafeCombi: 'safe' };
  let type = typeMap[currentType] || 'safe';
  let sport = document.getElementById('f-sport') ? document.getElementById('f-sport').value : 'tennis';
  let categorie = sport === 'tennis' ? 'tennis' : 'multi';
  if (document.getElementById('force-fun-type')) { type = 'fun'; categorie = 'multi'; sport = document.getElementById('f-sport') ? document.getElementById('f-sport').value : 'football'; }
  const analyseHtml = (document.getElementById('f-analyse-html') && document.getElementById('f-analyse-html').value.trim()) || '';
  // Cote = champ card si dispo, sinon cote retournée par generate-card
  let cote = '';
  if (currentType === 'Safe') cote = (document.getElementById('f-cote') && document.getElementById('f-cote').value) || '';
  else if (currentType === 'Live') cote = (document.getElementById('f-live-cote') && document.getElementById('f-live-cote').value) || '';
  if (!cote && lastGeneratedCote) cote = lastGeneratedCote;
  if (cote !== '' && !isNaN(parseFloat(cote))) cote = parseFloat(cote).toFixed(2);
  else cote = '';
  const csrf = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

  try {
    const blobNormal = await jpegUrlToBlob(jpegNormalUrl);
    const blobLocked = await jpegUrlToBlob(jpegLockedUrl);
    const form = new FormData();
    form.append('csrf_token', csrf);
    form.append('action', 'post_from_card');
    form.append('image', blobNormal, 'normal.jpg');
    form.append('locked_image', blobLocked, 'locked.jpg');
    form.append('titre', titre);
    form.append('type', type);
    form.append('description', '');
    form.append('categorie', categorie);
    form.append('sport', sport);
    form.append('analyse_html', analyseHtml);
    form.append('cote', cote);

    const resp = await fetch(adminFetchUrl('poster-bet-from-card.php'), { method: 'POST', body: form, credentials: 'same-origin' });
    const text = await resp.text();
    let data = {};
    try { data = JSON.parse(text); } catch (_) {}

    if (resp.ok && data.success) {
      if (statusEl) { statusEl.textContent = 'Bet posté avec succès ! Redirection…'; statusEl.style.color = '#00c864'; }
      setTimeout(function() { window.location.href = 'poster-bet.php?posted_from_card=1'; }, 1200);
    } else {
      if (btn) { btn.disabled = false; btn.textContent = '🚀 Poster le bet'; }
      if (statusEl) { statusEl.textContent = data.error || 'Erreur lors de l\'envoi.'; statusEl.style.color = '#ff6b9d'; }
    }
  } catch (e) {
    if (btn) { btn.disabled = false; btn.textContent = '🚀 Poster le bet'; }
    if (statusEl) { statusEl.textContent = 'Erreur : ' + e.message; statusEl.style.color = '#ff6b9d'; }
  }
}

// ── Gestion des états d'affichage ───────────────────────────
function setState(state) {
  document.getElementById('state-empty').style.display   = state === 'empty'   ? 'flex'  : 'none';
  document.getElementById('state-loading').style.display = state === 'loading' ? 'flex'  : 'none';
  document.getElementById('state-result').style.display  = state === 'result'  ? 'block' : 'none';
  if (state !== 'error') document.getElementById('state-error').style.display = 'none';
}

function showError(msg) {
  const el = document.getElementById('state-error');
  el.innerHTML = msg;
  el.style.display = 'block';
  setState('empty');
  document.getElementById('state-empty').style.display = 'none';
}

function setGenerateBtn(loading) {
  const btn = document.getElementById('btn-generate');
  btn.disabled = loading;
  document.getElementById('btn-icon').textContent = loading ? '⏳' : '✨';
  document.getElementById('btn-text').textContent = loading ? 'Génération en cours (~2-3 min)...' : 'Générer les Cards';
}

// ── Animation des étapes de chargement ──────────────────────
let loadingInterval;
const stepIds = ['step1','step2','step3','step4','step5','step6'];
let currentStep = 0;

function startLoadingAnimation() {
  currentStep = 0;
  stepIds.forEach(s => { document.getElementById(s).className = 'loading-step'; });
  document.getElementById(stepIds[0]).classList.add('active');
  loadingInterval = setInterval(() => {
    if (currentStep < 4) {
      document.getElementById(stepIds[currentStep]).className = 'loading-step done';
      currentStep++;
      document.getElementById(stepIds[currentStep]).classList.add('active');
    }
  }, 6000);
}

function markStep(stepNum) {
  for (let i = 0; i < stepNum - 1; i++) {
    document.getElementById(stepIds[i]).className = 'loading-step done';
  }
  if (stepNum - 1 < stepIds.length) {
    document.getElementById(stepIds[stepNum - 1]).className = 'loading-step active';
  }
}

function stopLoadingAnimation() { clearInterval(loadingInterval); }

function escHtml(str) { return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>
