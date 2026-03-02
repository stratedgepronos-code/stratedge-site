<?php
// ============================================================
// STRATEDGE — creer-card.php — Générateur de cards via Claude
// V8 — Support Safe + Live + Fun avec formulaire dynamique
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'creer-card';
$db = getDB();
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
  <div class="page-header">
    <h1>🎨 Créer une Card</h1>
    <p>Choisissez le type de bet — Claude génère la card normale + locked en JPEG.</p>
  </div>

  <div class="creator-layout">
    <div class="form-card">

      <!-- ══ SPORT ══ -->
      <div class="form-section-title">🎾 Sport</div>
      <div class="field">
        <label>Sport</label>
        <select id="f-sport">
          <option value="tennis">🎾 Tennis</option>
          <option value="football">⚽ Football</option>
          <option value="basket">🏀 Basket</option>
          <option value="hockey">🏒 Hockey</option>
        </select>
      </div>

      <!-- ══ TYPE DE BET (pills) ══ -->
      <div class="form-section-title">⚡ Type de Bet</div>
      <div class="type-selector">
        <div class="type-pill active-safe" data-type="Safe" onclick="selectType('Safe')">
          🛡️ Safe
        </div>
        <div class="type-pill" data-type="Live" onclick="selectType('Live')">
          🔴 Live
        </div>
        <div class="type-pill" data-type="Fun" onclick="selectType('Fun')">
          🎲 Fun
        </div>
      </div>

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
        </div>
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
// ── Cache des fonts en base64 (chargées une fois) ────────────
let _fontsBase64Css = null;

// Télécharge les fonts Google côté navigateur → base64
// Le navigateur parent peut accéder à fonts.gstatic.com sans problème CORS
// (Access-Control-Allow-Origin: * sur les fichiers woff2)
async function loadFontsAsBase64() {
  if (_fontsBase64Css) return _fontsBase64Css; // cache

  const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

  // Étape 1 : récupérer le CSS Google Fonts pour avoir les URLs woff2
  // Inclure tous les poids utilisés dans les cards (Bebas Neue, Rajdhani 400-700, Orbitron 700/900)
  const apiUrl = 'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Orbitron:wght@700;900&family=Rajdhani:wght@400;500;600;700&display=swap';
  let googleCss = '';
  try {
    const resp = await fetch(apiUrl, {
      headers: { 'User-Agent': UA }
    });
    googleCss = await resp.text();
  } catch(e) {
    console.warn('Impossible de charger Google Fonts CSS:', e);
    return '';
  }

  // Étape 2 : extraire toutes les URLs woff2
  const woff2Regex = /url\((https:\/\/fonts\.gstatic\.com[^)]+\.woff2)\)/g;
  const urls = [];
  let m;
  while ((m = woff2Regex.exec(googleCss)) !== null) {
    urls.push(m[1]);
  }

  // Étape 3 : extraire les @font-face complets du CSS Google Fonts
  // et remplacer chaque url(https://...) par une data URI base64
  let enrichedCss = googleCss;

  await Promise.all(urls.map(async (url) => {
    try {
      const resp = await fetch(url, { mode: 'cors' });
      const buf  = await resp.arrayBuffer();
      // Convertir ArrayBuffer → base64
      const bytes = new Uint8Array(buf);
      let binary  = '';
      for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
      const b64 = btoa(binary);
      enrichedCss = enrichedCss.split(url).join('data:font/woff2;base64,' + b64);
    } catch(e) {
      console.warn('Impossible de télécharger font:', url, e);
    }
  }));

  // Étape 4 : garder uniquement les @font-face (pas les unicode-range commentaires)
  const faceBlocks = [];
  const faceRegex  = /@font-face\s*\{[^}]+\}/g;
  let fm;
  while ((fm = faceRegex.exec(enrichedCss)) !== null) {
    // Ne garder que les blocs avec base64 (les autres n'ont pas été convertis)
    if (fm[0].includes('base64')) faceBlocks.push(fm[0]);
  }

  _fontsBase64Css = faceBlocks.join('\n');
  console.log('✅ Fonts chargées en base64:', faceBlocks.length, 'variantes');
  return _fontsBase64Css;
}

// ── Variables globales ──────────────────────────────────────
let jpegNormalUrl = '';
let jpegLockedUrl = '';
let currentMatchName = '';
let currentType = 'Safe';

// Largeurs par type de card
const CARD_WIDTHS = { Safe: 1080, Live: 720, Fun: 1080 };

// ── Sélection du type de bet ────────────────────────────────
function selectType(type) {
  currentType = type;

  // Retirer tous les styles actifs des pills
  document.querySelectorAll('.type-pill').forEach(p => {
    p.className = 'type-pill';
  });

  // Activer la pill sélectionnée
  const pill = document.querySelector(`.type-pill[data-type="${type}"]`);
  pill.classList.add('active-' + type.toLowerCase());

  // Afficher/masquer les formulaires
  document.getElementById('form-safe').style.display = (type === 'Safe') ? 'block' : 'none';
  document.getElementById('form-live').style.display = (type === 'Live') ? 'block' : 'none';
  document.getElementById('form-fun').style.display  = (type === 'Fun')  ? 'block' : 'none';
}

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

  } else { // Fun
    const raw = document.getElementById('f-raw-fun').value.trim();
    if (!raw) {
      showError('⚠️ Collez les infos du combiné Fun Bet.');
      return;
    }
    payload.raw_bet = raw;
    currentMatchName = 'FunBet_Combine';
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
    const resp = await fetch('generate-card.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller.signal
    });
    clearTimeout(fetchTimeout);

    let data;
    const text = await resp.text();
    try {
      data = JSON.parse(text);
    } catch (_) {
      showError('❌ Le serveur a renvoyé une erreur (HTTP ' + resp.status + '). Réponse non-JSON : ' + escHtml(text.substring(0, 200)));
      return;
    }

    if (!resp.ok || data.error) {
      showError('❌ ' + (data.error || 'Erreur inconnue.') + (data.raw ? '<br><small style="opacity:0.6">' + escHtml(data.raw) + '</small>' : ''));
      return;
    }

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
    // Injecter les @font-face en base64 dans le <style> (disponibles immédiatement, pas de CORS)
    html = html.replace('</style>', fontsCss + '\n</style>');
    // Retirer le <link> Google Fonts pour éviter double chargement et échec CORS dans l'iframe
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
    backgroundColor: null,
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

  if (croppedHeight > 100 && (topCrop > 5 || (canvas.height - bottomCrop) > 5)) {
    const cropped    = document.createElement('canvas');
    cropped.width    = cardWidth;
    cropped.height   = croppedHeight;
    const croppedCtx = cropped.getContext('2d');
    croppedCtx.fillStyle = '#0a0a0a';
    croppedCtx.fillRect(0, 0, cardWidth, croppedHeight);
    croppedCtx.drawImage(canvas, 0, topCrop, cardWidth, croppedHeight, 0, 0, cardWidth, croppedHeight);
    return cropped.toDataURL('image/jpeg', 0.92);
  }
  return canvas.toDataURL('image/jpeg', 0.92);
}


// ── Télécharger les deux ────────────────────────────────────
function downloadBoth() {
  document.getElementById('dl-normal').click();
  setTimeout(() => document.getElementById('dl-locked').click(), 500);
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
