<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'ht-tracker';
$db = getDB();
require_once __DIR__ . '/sidebar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HT Markets Tracker — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Bebas+Neue&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <link rel="stylesheet" href="../assets/css/calendar-strateedge.css">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--neon-purple:#a855f7;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);--win:#00ff88;--lose:#ff4455;--gold:#c8a45e}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{overflow-x:hidden}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;overflow-x:hidden}
    .main{flex:1;margin-left:240px;padding:2rem;max-width:calc(100vw - 240px)}

    /* ─── PAGE HEADER ─── */
    .page-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem}
    .page-head h1{font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:700}
    .page-head h1 span{color:var(--neon-green)}
    .page-head .sub{color:var(--text-muted);font-size:.85rem;font-family:'Space Mono',monospace;letter-spacing:1px}

    /* ─── KPI BAR ─── */
    .kpi-row{display:grid;grid-template-columns:repeat(6,1fr);gap:.75rem;margin-bottom:1.5rem}
    .kpi{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1rem 1.2rem;position:relative;overflow:hidden;transition:all .3s}
    .kpi:hover{border-color:rgba(255,45,120,.25);transform:translateY(-2px)}
    .kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
    .kpi:nth-child(1)::before{background:var(--neon-green)}
    .kpi:nth-child(2)::before{background:var(--win)}
    .kpi:nth-child(3)::before{background:var(--lose)}
    .kpi:nth-child(4)::before{background:var(--neon-blue)}
    .kpi:nth-child(5)::before{background:var(--gold)}
    .kpi:nth-child(6)::before{background:var(--neon-purple)}
    .kpi-label{font-family:'Space Mono',monospace;font-size:.58rem;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:.4rem}
    .kpi-val{font-family:'Bebas Neue',sans-serif;font-size:1.8rem;line-height:1}
    .kpi-val.green{color:var(--win)}.kpi-val.red{color:var(--lose)}.kpi-val.pink{color:var(--neon-green)}
    .kpi-val.cyan{color:var(--neon-blue)}.kpi-val.gold{color:var(--gold)}.kpi-val.purple{color:var(--neon-purple)}

    /* ─── SECTIONS ─── */
    .section{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;position:relative;overflow:hidden}
    /* Calendrier : pas de clip + au-dessus des sections suivantes (charts) */
    .section.section-bet-form{overflow:visible;z-index:25}
    .section::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--neon-green),var(--neon-blue));opacity:.6}
    .sec-title{font-family:'Orbitron',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:.6rem}
    .sec-title .ico{font-size:1.1rem}

    /* ─── ADD BET FORM ─── */
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;align-items:end}
    .fg{display:flex;flex-direction:column;gap:.3rem}
    .fg label{font-family:'Space Mono',monospace;font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted)}
    .fg input,.fg select{background:rgba(255,255,255,.04);border:1px solid var(--border-subtle);border-radius:8px;padding:.55rem .75rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:.92rem;transition:border .2s;height:38px}
    .fg input:focus,.fg select:focus{outline:none;border-color:var(--neon-green);box-shadow:0 0 12px rgba(255,45,120,.15)}
    .fg select option{background:#0d1220;color:#f0f4f8}
    /* Date picker override — forcer le wrapper à se comporter comme un input normal */
    .fg .strateedge-date-wrap{position:relative;width:100%;height:38px;min-height:unset}
    .fg .strateedge-date-wrap .strateedge-date-display{width:100%;height:38px;background:rgba(255,255,255,.04);border:1px solid var(--border-subtle);border-radius:8px;padding:.55rem 2.2rem .55rem .75rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:.92rem;cursor:pointer;transition:border .2s}
    .fg .strateedge-date-wrap .strateedge-date-display:focus{outline:none;border-color:var(--neon-green);box-shadow:0 0 12px rgba(255,45,120,.15)}
    .fg .strateedge-date-wrap .cal-icon{position:absolute;right:8px;top:0;height:38px;display:flex;align-items:center;pointer-events:none;color:var(--neon-green);z-index:1}
    .fg .strateedge-date-wrap .cal-icon svg{width:16px;height:16px;flex-shrink:0}
    .fg .strateedge-date-wrap .cal-popover{top:42px;z-index:1000}
    .btn-add{background:linear-gradient(135deg,var(--neon-green),#ff6b9d);color:#fff;border:none;border-radius:10px;padding:.65rem 1.5rem;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:1px;cursor:pointer;transition:all .3s;text-transform:uppercase;white-space:nowrap;height:38px}
    .btn-add:hover{transform:translateY(-2px);box-shadow:0 6px 25px rgba(255,45,120,.35)}

    /* ─── BETS TABLE ─── */
    .tbl-wrap{overflow-x:auto;margin-top:.5rem}
    table{width:100%;border-collapse:separate;border-spacing:0;font-size:.85rem}
    thead th{font-family:'Space Mono',monospace;font-size:.6rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);padding:.7rem .5rem;text-align:center;border-bottom:1px solid var(--border-subtle);position:sticky;top:0;background:var(--bg-card);z-index:2}
    tbody tr{transition:background .2s;cursor:default}
    tbody tr:hover{background:rgba(255,45,120,.04)}
    tbody td{padding:.6rem .5rem;text-align:center;border-bottom:1px solid rgba(255,255,255,.03);vertical-align:middle}
    .td-match{text-align:left;font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .badge{display:inline-block;padding:.15rem .55rem;border-radius:6px;font-size:.72rem;font-weight:700;font-family:'Space Mono',monospace;letter-spacing:.5px}
    .badge-xht{background:rgba(255,45,120,.12);color:var(--neon-green);border:1px solid rgba(255,45,120,.25)}
    .badge-o15{background:rgba(0,212,255,.1);color:var(--neon-blue);border:1px solid rgba(0,212,255,.2)}
    .badge-win{background:rgba(0,255,136,.1);color:var(--win);border:1px solid rgba(0,255,136,.2)}
    .badge-lose{background:rgba(255,68,85,.1);color:var(--lose);border:1px solid rgba(255,68,85,.2)}
    .badge-pending{background:rgba(255,255,255,.05);color:var(--text-muted);border:1px solid rgba(255,255,255,.08)}
    .stars{color:var(--gold);font-size:.8rem;letter-spacing:-1px}
    .pl-pos{color:var(--win);font-weight:700}.pl-neg{color:var(--lose);font-weight:700}
    .act-btns{display:flex;gap:.3rem;justify-content:center}
    .act-btn{width:28px;height:28px;border-radius:6px;border:none;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;transition:all .2s}
    .act-btn:hover{transform:scale(1.15)}
    .act-win{background:rgba(0,255,136,.12);color:var(--win)}.act-win:hover{background:rgba(0,255,136,.25)}
    .act-lose{background:rgba(255,68,85,.1);color:var(--lose)}.act-lose:hover{background:rgba(255,68,85,.2)}
    .act-del{background:rgba(255,255,255,.04);color:var(--text-muted)}.act-del:hover{background:rgba(255,68,85,.15);color:var(--lose)}
    .act-edit{background:rgba(0,212,255,.08);color:var(--neon-blue)}.act-edit:hover{background:rgba(0,212,255,.18)}
    .empty-state{text-align:center;padding:3rem 1rem;color:var(--text-muted)}
    .empty-state .big-ico{font-size:3rem;margin-bottom:.5rem;opacity:.3}
    .empty-state p{font-size:.9rem}

    /* ─── CHARTS ROW ─── */
    .charts-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
    .chart-box{position:relative;height:250px}

    /* ─── STATS CARDS ─── */
    .stats-duo{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
    .mkt-stat{text-align:center;padding:1.2rem}
    .mkt-stat .mkt-name{font-family:'Orbitron',sans-serif;font-size:.85rem;font-weight:700;margin-bottom:.8rem}
    .mkt-stat .mkt-row{display:flex;justify-content:space-around;gap:.5rem;flex-wrap:wrap}
    .mkt-mini{text-align:center}
    .mkt-mini .mv{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;line-height:1}
    .mkt-mini .ml{font-size:.55rem;font-family:'Space Mono',monospace;letter-spacing:1px;color:var(--text-muted);text-transform:uppercase;margin-top:.15rem}

    /* ─── MODAL ─── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:500;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:16px;padding:2rem;width:90%;max-width:420px;position:relative}
    .modal h3{font-family:'Orbitron',sans-serif;font-size:1rem;margin-bottom:1rem}
    .modal .close-modal{position:absolute;top:1rem;right:1rem;background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer}

    /* ─── RESPONSIVE ─── */
    @media(max-width:768px){
      .main{margin-left:0;padding:70px 1rem 5rem;max-width:100%}
      .kpi-row{grid-template-columns:repeat(3,1fr)}
      .charts-row,.stats-duo{grid-template-columns:1fr}
      .form-grid{grid-template-columns:1fr 1fr}
    }
    @media(max-width:480px){
      .kpi-row{grid-template-columns:repeat(2,1fr)}
      .form-grid{grid-template-columns:1fr}
      .kpi-val{font-size:1.4rem}
    }
  </style>
</head>
<body>

<div class="main">
  <!-- HEADER -->
  <div class="page-head">
    <div>
      <h1>🎯 <span>HT</span> MARKETS TRACKER</h1>
      <div class="sub">Suivi Nul Mi-Temps · Over 1.5 HT · Prompt v1.0</div>
    </div>
    <div style="display:flex;gap:.5rem;">
      <button class="btn-add" onclick="exportJSON()" style="background:rgba(0,212,255,.15);color:var(--neon-blue);">💾 Export</button>
      <label class="btn-add" style="background:rgba(168,85,247,.15);color:var(--neon-purple);cursor:pointer">📂 Import<input type="file" accept=".json" onchange="importJSON(event)" style="display:none"></label>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpi-row">
    <div class="kpi"><div class="kpi-label">Total Bets</div><div class="kpi-val pink" id="kTotal">0</div></div>
    <div class="kpi"><div class="kpi-label">✅ Gagnés</div><div class="kpi-val green" id="kWon">0</div></div>
    <div class="kpi"><div class="kpi-label">❌ Perdus</div><div class="kpi-val red" id="kLost">0</div></div>
    <div class="kpi"><div class="kpi-label">Win Rate</div><div class="kpi-val cyan" id="kWR">—</div></div>
    <div class="kpi"><div class="kpi-label">ROI</div><div class="kpi-val gold" id="kROI">—</div></div>
    <div class="kpi"><div class="kpi-label">P&L Total</div><div class="kpi-val purple" id="kPL">0.00€</div></div>
  </div>

  <!-- ADD BET -->
  <div class="section section-bet-form">
    <div class="sec-title"><span class="ico">➕</span> Ajouter un Bet</div>
    <div class="form-grid" id="betForm">
      <div class="fg"><label>Date</label>
        <div class="strateedge-date-wrap">
          <input type="hidden" id="fDate" value="<?= date('Y-m-d') ?>">
          <input type="text" class="strateedge-date-display" readonly placeholder="jj/mm/aaaa" value="<?= date('d/m/Y') ?>">
          <span class="cal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
          <div class="cal-popover"></div>
        </div>
      </div>
      <div class="fg"><label>Ligue</label><input type="text" id="fLeague" placeholder="ex: Bundesliga 2"></div>
      <div class="fg"><label>Match</label><input type="text" id="fMatch" placeholder="ex: Hannover vs Braunschweig"></div>
      <div class="fg"><label>Heure</label><input type="time" id="fTime"></div>
      <div class="fg"><label>Marché</label><select id="fMarket"><option value="X HT">X HT (Nul MT)</option><option value="O1.5 HT">O1.5 HT</option><option value="O0.5 HT">O0.5 HT</option></select></div>
      <div class="fg"><label>Cote</label><input type="number" id="fOdds" step="0.01" min="1" placeholder="2.55"></div>
      <div class="fg"><label>Confiance ⭐</label><select id="fStars"><option value="3">⭐⭐⭐</option><option value="4" selected>⭐⭐⭐⭐</option><option value="5">⭐⭐⭐⭐⭐</option></select></div>
      <div class="fg"><label>Mise €</label><input type="number" id="fStake" step="0.01" min="0" placeholder="20.00"></div>
      <div class="fg"><label>EV %</label><input type="number" id="fEV" step="0.1" placeholder="7.5"></div>
      <div class="fg"><label>Notes</label><input type="text" id="fNotes" placeholder="Signal, filtre..."></div>
      <div class="fg" style="justify-content:end"><button class="btn-add" onclick="addBet()">⚡ Ajouter</button></div>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="charts-row">
    <div class="section">
      <div class="sec-title"><span class="ico">📈</span> Courbe P&L</div>
      <div class="chart-box"><canvas id="chartPL"></canvas></div>
    </div>
    <div class="section">
      <div class="sec-title"><span class="ico">🎯</span> Win Rate Cumulé</div>
      <div class="chart-box"><canvas id="chartWR"></canvas></div>
    </div>
  </div>

  <!-- STATS PAR MARCHÉ -->
  <div class="stats-duo">
    <div class="section mkt-stat" id="statsXHT">
      <div class="mkt-name" style="color:var(--neon-green)">🎯 NUL MI-TEMPS (X HT)</div>
      <div class="mkt-row">
        <div class="mkt-mini"><div class="mv" id="sXbets">0</div><div class="ml">Bets</div></div>
        <div class="mkt-mini"><div class="mv green" id="sXwr">—</div><div class="ml">Win%</div></div>
        <div class="mkt-mini"><div class="mv" id="sXpl" style="color:var(--text-primary)">0€</div><div class="ml">P&L</div></div>
        <div class="mkt-mini"><div class="mv cyan" id="sXavg">—</div><div class="ml">Cote Moy</div></div>
      </div>
    </div>
    <div class="section mkt-stat" id="statsO15">
      <div class="mkt-name" style="color:var(--neon-blue)">⚡ OVER 1.5 HT</div>
      <div class="mkt-row">
        <div class="mkt-mini"><div class="mv" id="sObets">0</div><div class="ml">Bets</div></div>
        <div class="mkt-mini"><div class="mv green" id="sOwr">—</div><div class="ml">Win%</div></div>
        <div class="mkt-mini"><div class="mv" id="sOpl" style="color:var(--text-primary)">0€</div><div class="ml">P&L</div></div>
        <div class="mkt-mini"><div class="mv cyan" id="sOavg">—</div><div class="ml">Cote Moy</div></div>
      </div>
    </div>
  </div>

  <!-- BETS TABLE -->
  <div class="section">
    <div class="sec-title"><span class="ico">📋</span> Historique des Bets <span style="font-family:'Space Mono';font-size:.65rem;color:var(--text-muted);margin-left:auto" id="betCount"></span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Date</th><th>Ligue</th><th>Match</th><th>Marché</th>
          <th>Cote</th><th>⭐</th><th>Mise</th><th>Score MT</th><th>Statut</th>
          <th>P&L</th><th>EV%</th><th>Actions</th>
        </tr></thead>
        <tbody id="betBody"></tbody>
      </table>
      <div class="empty-state" id="emptyState">
        <div class="big-ico">🎯</div>
        <p>Aucun bet enregistré. Ajoute ton premier bet HT Markets ci-dessus !</p>
      </div>
    </div>
  </div>

  <!-- FOOTER -->
  <div style="text-align:center;padding:1rem;font-size:.7rem;color:var(--text-muted);font-family:'Space Mono',monospace;letter-spacing:1px">
    STRATEDGE PRONOS · HT Markets Tracker v1.0 · stratedgepronos.fr
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <button class="close-modal" onclick="closeModal()">✕</button>
    <h3>✏️ Modifier le Score MT</h3>
    <input type="hidden" id="editIdx">
    <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:1rem">
      <div class="fg"><label>Score Mi-Temps</label><input type="text" id="editScore" placeholder="0-0, 1-1, 2-1..."></div>
      <div class="fg"><label>Score Final</label><input type="text" id="editFT" placeholder="1-0, 2-2..."></div>
    </div>
    <button class="btn-add" onclick="saveScore()" style="width:100%">💾 Enregistrer</button>
  </div>
</div>

<script>
const STORAGE_KEY = 'stratedge_ht_bets';

// ─── DATA ───
function getBets() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch { return []; }
}
function saveBets(bets) { localStorage.setItem(STORAGE_KEY, JSON.stringify(bets)); }

// ─── ADD BET ───
function addBet() {
  const b = {
    id: Date.now(),
    date: document.getElementById('fDate').value || new Date().toISOString().split('T')[0],
    league: document.getElementById('fLeague').value,
    match: document.getElementById('fMatch').value,
    time: document.getElementById('fTime').value || '',
    market: document.getElementById('fMarket').value,
    odds: parseFloat(document.getElementById('fOdds').value) || 0,
    stars: parseInt(document.getElementById('fStars').value) || 4,
    stake: parseFloat(document.getElementById('fStake').value) || 0,
    ev: parseFloat(document.getElementById('fEV').value) || 0,
    notes: document.getElementById('fNotes').value,
    scoreMT: '',
    scoreFT: '',
    status: 'pending' // pending, win, lose
  };
  if (!b.match || !b.odds || !b.stake) { alert('Remplis au moins Match, Cote et Mise !'); return; }
  const bets = getBets();
  bets.unshift(b);
  saveBets(bets);
  // Reset form
  ['fLeague','fMatch','fTime','fOdds','fStake','fEV','fNotes'].forEach(id => document.getElementById(id).value = '');
  render();
}

// ─── ACTIONS ───
function markWin(id) { updateStatus(id, 'win'); }
function markLose(id) { updateStatus(id, 'lose'); }
function updateStatus(id, st) {
  const bets = getBets();
  const b = bets.find(x => x.id === id);
  if (b) { b.status = st; saveBets(bets); render(); }
}
function deleteBet(id) {
  if (!confirm('Supprimer ce bet ?')) return;
  saveBets(getBets().filter(x => x.id !== id));
  render();
}
function openEdit(id) {
  const b = getBets().find(x => x.id === id);
  if (!b) return;
  document.getElementById('editIdx').value = id;
  document.getElementById('editScore').value = b.scoreMT || '';
  document.getElementById('editFT').value = b.scoreFT || '';
  document.getElementById('editModal').classList.add('open');
}
function closeModal() { document.getElementById('editModal').classList.remove('open'); }
function saveScore() {
  const id = parseInt(document.getElementById('editIdx').value);
  const bets = getBets();
  const b = bets.find(x => x.id === id);
  if (b) {
    b.scoreMT = document.getElementById('editScore').value;
    b.scoreFT = document.getElementById('editFT').value;
    saveBets(bets);
  }
  closeModal();
  render();
}

// ─── COMPUTE STATS ───
function computeStats(bets) {
  const resolved = bets.filter(b => b.status !== 'pending');
  const won = resolved.filter(b => b.status === 'win');
  const lost = resolved.filter(b => b.status === 'lose');
  const totalStake = resolved.reduce((s, b) => s + b.stake, 0);
  const totalPL = resolved.reduce((s, b) => {
    if (b.status === 'win') return s + (b.odds * b.stake - b.stake);
    return s - b.stake;
  }, 0);
  const wr = resolved.length ? (won.length / resolved.length * 100) : null;
  const roi = totalStake ? (totalPL / totalStake * 100) : null;
  return { total: bets.length, won: won.length, lost: lost.length, pending: bets.length - resolved.length, wr, roi, totalPL, totalStake };
}

function marketStats(bets, mkt) {
  const mb = bets.filter(b => b.market === mkt);
  const resolved = mb.filter(b => b.status !== 'pending');
  const won = resolved.filter(b => b.status === 'win');
  const avgOdds = mb.length ? mb.reduce((s, b) => s + b.odds, 0) / mb.length : 0;
  const pl = resolved.reduce((s, b) => b.status === 'win' ? s + (b.odds * b.stake - b.stake) : s - b.stake, 0);
  const wr = resolved.length ? (won.length / resolved.length * 100) : null;
  return { bets: mb.length, wr, pl, avgOdds };
}

// ─── RENDER ───
function render() {
  const bets = getBets();
  const stats = computeStats(bets);

  // KPIs
  document.getElementById('kTotal').textContent = stats.total;
  document.getElementById('kWon').textContent = stats.won;
  document.getElementById('kLost').textContent = stats.lost;
  document.getElementById('kWR').textContent = stats.wr !== null ? stats.wr.toFixed(1) + '%' : '—';
  document.getElementById('kROI').textContent = stats.roi !== null ? (stats.roi >= 0 ? '+' : '') + stats.roi.toFixed(1) + '%' : '—';
  const plEl = document.getElementById('kPL');
  plEl.textContent = (stats.totalPL >= 0 ? '+' : '') + stats.totalPL.toFixed(2) + '€';
  plEl.className = 'kpi-val ' + (stats.totalPL >= 0 ? 'green' : 'red');

  // Market stats
  const xht = marketStats(bets, 'X HT');
  document.getElementById('sXbets').textContent = xht.bets;
  document.getElementById('sXwr').textContent = xht.wr !== null ? xht.wr.toFixed(0) + '%' : '—';
  const sXplEl = document.getElementById('sXpl');
  sXplEl.textContent = (xht.pl >= 0 ? '+' : '') + xht.pl.toFixed(2) + '€';
  sXplEl.style.color = xht.pl >= 0 ? 'var(--win)' : 'var(--lose)';
  document.getElementById('sXavg').textContent = xht.avgOdds ? xht.avgOdds.toFixed(2) : '—';

  const o15 = marketStats(bets, 'O1.5 HT');
  document.getElementById('sObets').textContent = o15.bets;
  document.getElementById('sOwr').textContent = o15.wr !== null ? o15.wr.toFixed(0) + '%' : '—';
  const sOplEl = document.getElementById('sOpl');
  sOplEl.textContent = (o15.pl >= 0 ? '+' : '') + o15.pl.toFixed(2) + '€';
  sOplEl.style.color = o15.pl >= 0 ? 'var(--win)' : 'var(--lose)';
  document.getElementById('sOavg').textContent = o15.avgOdds ? o15.avgOdds.toFixed(2) : '—';

  // Table
  const body = document.getElementById('betBody');
  const empty = document.getElementById('emptyState');
  document.getElementById('betCount').textContent = bets.length + ' bet' + (bets.length > 1 ? 's' : '');

  if (!bets.length) {
    body.innerHTML = '';
    empty.style.display = 'block';
  } else {
    empty.style.display = 'none';
    body.innerHTML = bets.map((b, i) => {
      const num = bets.length - i;
      const mktClass = b.market === 'X HT' ? 'badge-xht' : 'badge-o15';
      const stStr = '⭐'.repeat(b.stars);
      let statusBadge = '<span class="badge badge-pending">⏳ En cours</span>';
      if (b.status === 'win') statusBadge = '<span class="badge badge-win">✅ Gagné</span>';
      if (b.status === 'lose') statusBadge = '<span class="badge badge-lose">❌ Perdu</span>';
      let pl = '';
      if (b.status === 'win') { const v = b.odds * b.stake - b.stake; pl = `<span class="pl-pos">+${v.toFixed(2)}€</span>`; }
      else if (b.status === 'lose') { pl = `<span class="pl-neg">-${b.stake.toFixed(2)}€</span>`; }
      return `<tr>
        <td style="color:var(--text-muted);font-size:.75rem">${num}</td>
        <td style="font-size:.8rem">${b.date}</td>
        <td style="font-size:.78rem;color:var(--text-secondary)">${b.league}</td>
        <td class="td-match">${b.match}</td>
        <td><span class="badge ${mktClass}">${b.market}</span></td>
        <td style="font-weight:700">${b.odds.toFixed(2)}</td>
        <td><span class="stars">${stStr}</span></td>
        <td>${b.stake.toFixed(2)}€</td>
        <td style="font-family:'Space Mono',monospace;font-size:.78rem;color:var(--neon-blue)">${b.scoreMT || '—'}</td>
        <td>${statusBadge}</td>
        <td>${pl || '—'}</td>
        <td style="font-size:.78rem;color:var(--gold)">${b.ev ? b.ev.toFixed(1) + '%' : '—'}</td>
        <td><div class="act-btns">
          ${b.status === 'pending' ? `<button class="act-btn act-win" onclick="markWin(${b.id})" title="Gagné">✓</button><button class="act-btn act-lose" onclick="markLose(${b.id})" title="Perdu">✗</button>` : ''}
          <button class="act-btn act-edit" onclick="openEdit(${b.id})" title="Score">⚽</button>
          <button class="act-btn act-del" onclick="deleteBet(${b.id})" title="Supprimer">🗑</button>
        </div></td>
      </tr>`;
    }).join('');
  }

  renderCharts(bets);
}

// ─── CHARTS ───
let chartPL = null, chartWR = null;

function renderCharts(bets) {
  const resolved = [...bets].reverse().filter(b => b.status !== 'pending');
  if (!resolved.length) {
    if (chartPL) { chartPL.destroy(); chartPL = null; }
    if (chartWR) { chartWR.destroy(); chartWR = null; }
    return;
  }

  // P&L curve
  let cumPL = 0;
  const plData = resolved.map(b => {
    cumPL += b.status === 'win' ? (b.odds * b.stake - b.stake) : -b.stake;
    return cumPL;
  });
  const plLabels = resolved.map((b, i) => '#' + (i + 1));

  const plCtx = document.getElementById('chartPL').getContext('2d');
  if (chartPL) chartPL.destroy();
  const plGrad = plCtx.createLinearGradient(0, 0, 0, 250);
  plGrad.addColorStop(0, cumPL >= 0 ? 'rgba(0,255,136,.25)' : 'rgba(255,68,85,.25)');
  plGrad.addColorStop(1, 'rgba(0,0,0,0)');
  chartPL = new Chart(plCtx, {
    type: 'line',
    data: {
      labels: plLabels,
      datasets: [{
        data: plData, fill: true, backgroundColor: plGrad,
        borderColor: cumPL >= 0 ? '#00ff88' : '#ff4455', borderWidth: 2.5,
        tension: .35, pointRadius: 3, pointBackgroundColor: cumPL >= 0 ? '#00ff88' : '#ff4455',
        pointBorderWidth: 0, pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => (ctx.raw >= 0 ? '+' : '') + ctx.raw.toFixed(2) + '€' } } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.03)' }, ticks: { color: '#8a9bb0', font: { size: 10 } } },
        y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#8a9bb0', callback: v => v + '€' } }
      }
    }
  });

  // Win Rate curve
  let wins = 0;
  const wrData = resolved.map((b, i) => {
    if (b.status === 'win') wins++;
    return (wins / (i + 1)) * 100;
  });

  const wrCtx = document.getElementById('chartWR').getContext('2d');
  if (chartWR) chartWR.destroy();
  const wrGrad = wrCtx.createLinearGradient(0, 0, 0, 250);
  wrGrad.addColorStop(0, 'rgba(0,212,255,.2)');
  wrGrad.addColorStop(1, 'rgba(0,0,0,0)');
  chartWR = new Chart(wrCtx, {
    type: 'line',
    data: {
      labels: plLabels,
      datasets: [{
        data: wrData, fill: true, backgroundColor: wrGrad,
        borderColor: '#00d4ff', borderWidth: 2.5,
        tension: .35, pointRadius: 3, pointBackgroundColor: '#00d4ff',
        pointBorderWidth: 0, pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.raw.toFixed(1) + '%' } } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.03)' }, ticks: { color: '#8a9bb0', font: { size: 10 } } },
        y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#8a9bb0', callback: v => v + '%' }, min: 0, max: 100 }
      }
    }
  });
}

// ─── EXPORT / IMPORT ───
function exportJSON() {
  const data = JSON.stringify(getBets(), null, 2);
  const blob = new Blob([data], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'stratedge_ht_bets_' + new Date().toISOString().split('T')[0] + '.json';
  a.click();
}
function importJSON(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(ev) {
    try {
      const data = JSON.parse(ev.target.result);
      if (Array.isArray(data)) {
        if (confirm(`Importer ${data.length} bets ? (Cela remplacera les données actuelles)`)) {
          saveBets(data);
          render();
        }
      }
    } catch { alert('Fichier JSON invalide'); }
  };
  reader.readAsText(file);
  e.target.value = '';
}

// ─── INIT ───
render();
</script>
<script src="../assets/js/calendar-strateedge.js"></script>
</body>
</html>
