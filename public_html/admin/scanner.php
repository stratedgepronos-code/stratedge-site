<?php
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pageActive = 'scanner';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>⚡ Command Center — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --pink: #ff2d78; --cyan: #00d4ff; --green: #00ff88; --gold: #ffd700; --bg: #0a0a1a; --card: #111118; --border: #222; }
    .main { padding: 1rem !important; background: linear-gradient(135deg, var(--bg) 0%, #0d1117 50%, #1a0a2e 100%); min-height: 100vh; }
    .cc-header { border-bottom: 2px solid; border-image: linear-gradient(90deg, var(--pink), var(--cyan)) 1; padding-bottom: 10px; margin-bottom: 12px; text-align: center; }
    .cc-header h1 { font-family: 'Orbitron', monospace; font-size: clamp(16px, 3vw, 24px); background: linear-gradient(90deg, var(--pink), var(--cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin:0; }
    .cc-sub { font-size: 11px; color: #666; margin-top: 2px; }
    .cc-nav { display: flex; gap: 6px; margin-bottom: 12px; }
    .cc-nav-btn { flex: 1; padding: 10px 8px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: #888; font-weight: 700; font-size: 12px; cursor: pointer; text-align: center; font-family: 'Rajdhani', sans-serif; }
    .cc-nav-btn.active { border-color: var(--cyan); background: rgba(0,212,255,0.08); color: var(--cyan); }
    .cc-panel { display: none; } .cc-panel.active { display: block; }
    .cc-leagues { display: flex; flex-wrap: wrap; gap: 5px; justify-content: center; margin-bottom: 12px; }
    .cc-league-btn { padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--card); color: #888; font-size: 11px; font-weight: 600; cursor: pointer; font-family: 'Rajdhani', sans-serif; }
    .cc-league-btn.active { border-color: var(--cyan); background: rgba(0,212,255,0.1); color: var(--cyan); }
    .cc-scan-btn { display: block; margin: 0 auto 12px; padding: 10px 32px; border-radius: 8px; border: none; background: linear-gradient(90deg, var(--pink), var(--cyan)); color: #fff; font-family: 'Orbitron', monospace; font-size: 13px; font-weight: 700; cursor: pointer; }
    .cc-scan-btn:disabled { opacity: 0.5; }
    .cc-match { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 6px; cursor: pointer; }
    .cc-match:hover { border-color: var(--pink); }
    .cc-teams { font-weight: 700; color: #fff; font-size: 14px; }
    .cc-vs { color: var(--pink); margin: 0 6px; font-size: 12px; }
    .cc-meta { font-size: 11px; color: #888; margin-top: 3px; }
    .cc-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 4px; margin-top: 6px; }
    .cc-stat { background: #0d0d2a; border: 1px solid #1a1a3a; border-radius: 4px; padding: 3px 6px; text-align: center; font-size: 10px; }
    .cc-stat-l { color: #888; display: block; } .cc-stat-v { color: var(--cyan); font-weight: 700; font-size: 13px; }
    .cc-stat-v.hot { color: var(--green); } .cc-stat-v.cold { color: var(--pink); }
    .cc-odds-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 6px; margin-top: 8px; }
    .cc-odds-item { background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 6px 8px; text-align: center; }
    .cc-odds-lbl { font-size: 10px; color: #aaa; } .cc-odds-p { font-size: 18px; font-weight: 700; color: var(--cyan); } .cc-odds-bk { font-size: 9px; color: #666; }
    .cc-box { background: rgba(0,212,255,0.04); border: 1px solid rgba(0,212,255,0.3); border-radius: 8px; padding: 10px; margin-bottom: 10px; }
    .cc-box .lbl { font-size: 11px; color: var(--cyan); font-weight: 600; margin-bottom: 4px; }
    .cc-box code { background: #000; color: var(--green); padding: 3px 6px; border-radius: 4px; font-size: 10px; display: block; margin-top: 3px; word-break: break-all; user-select: all; }
    .cc-box .hint { font-size: 9px; color: #555; margin-top: 3px; }
    .cc-status { text-align: center; padding: 16px; color: var(--cyan); font-size: 13px; }
    .cc-error { background: rgba(255,45,120,0.1); border: 1px solid var(--pink); border-radius: 8px; padding: 10px; text-align: center; color: var(--pink); margin-bottom: 8px; font-size: 12px; }
    .cc-title { color: var(--pink); font-family: 'Orbitron', monospace; font-size: 12px; margin: 12px 0 6px; }
    .cc-back { background: none; border: 1px solid var(--pink); color: var(--pink); border-radius: 6px; padding: 5px 14px; cursor: pointer; font-size: 11px; margin-bottom: 10px; }
    .cc-footer { text-align: center; margin-top: 24px; padding: 10px 0; border-top: 1px solid var(--border); font-size: 9px; color: #444; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">

<div class="cc-header"><h1>⚡ STRATEDGE COMMAND CENTER</h1><div class="cc-sub">PROMPT v7.1 · 49 règles · Odds API + FootyStats API</div></div>

<div class="cc-nav">
  <button class="cc-nav-btn active" onclick="ccSwitch('odds',this)">📊 Odds</button>
  <button class="cc-nav-btn" onclick="ccSwitch('stats',this)">📈 Stats</button>
  <button class="cc-nav-btn" onclick="ccSwitch('ai',this)">🧠 Auto-Analyse</button>
  <button class="cc-nav-btn" onclick="ccSwitch('urls',this)">🤖 URLs</button>
</div>

<div id="ccError" class="cc-error" style="display:none;"></div>
<div id="ccLoad" class="cc-status" style="display:none;">⏳ Chargement...</div>

<!-- ODDS -->
<div id="cc-odds" class="cc-panel active">
  <div class="cc-leagues" id="ccLeagues"></div>
  <button class="cc-scan-btn" onclick="ccScanOdds()">🔍 SCANNER COTES</button>
  <div id="ccQuota" style="text-align:center;font-size:10px;color:#555;margin-bottom:8px;"></div>
  <div id="ccOddsRes"></div>
  <div id="ccOddsDet" style="display:none;"></div>
</div>

<!-- STATS -->
<div id="cc-stats" class="cc-panel">
  <button class="cc-scan-btn" onclick="ccToday()">📅 MATCHS DU JOUR</button>
  <div class="cc-box"><div class="lbl">Rechercher une équipe</div>
    <div style="display:flex;gap:6px;margin-top:4px;">
      <input id="ccSearch" type="text" placeholder="Ex: Arsenal, Bayern..." style="flex:1;padding:6px 10px;border-radius:6px;border:1px solid var(--border);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
      <button onclick="ccSearchTeam()" style="padding:6px 14px;border-radius:6px;border:none;background:var(--pink);color:#fff;font-weight:700;cursor:pointer;">🔍</button>
    </div>
  </div>
  <div id="ccStatsRes"></div>
</div>

<!-- AI -->
<div id="cc-ai" class="cc-panel">
  <div class="cc-box">
    <div class="lbl">🧠 Auto-Analyse StratEdge (PROMPT v7.1 + data auto)</div>
    <div class="hint" style="margin-bottom:8px;">Claude Opus 4.6 · ~$0.20/analyse</div>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <select id="ccAiLeague" style="padding:8px;border-radius:6px;border:1px solid var(--border);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
        <option value="soccer_epl">🏴 Premier League</option>
        <option value="soccer_spain_la_liga">🇪🇸 La Liga</option>
        <option value="soccer_italy_serie_a">🇮🇹 Serie A</option>
        <option value="soccer_germany_bundesliga">🇩🇪 Bundesliga</option>
        <option value="soccer_france_ligue_one">🇫🇷 Ligue 1</option>
        <option value="soccer_uefa_champs_league">🏆 Champions League</option>
        <option value="soccer_netherlands_eredivisie">🇳🇱 Eredivisie</option>
        <option value="soccer_brazil_campeonato">🇧🇷 Brasileirão</option>
        <option value="soccer_usa_mls">🇺🇸 MLS</option>
      </select>
      <div style="display:flex;gap:6px;">
        <input id="ccAiHome" type="text" placeholder="Domicile" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
        <span style="color:var(--pink);align-self:center;font-weight:700;">vs</span>
        <input id="ccAiAway" type="text" placeholder="Extérieur" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
      </div>
      <button onclick="ccRunAI()" id="ccAiBtn" class="cc-scan-btn" style="margin:0;">🧠 LANCER L'ANALYSE</button>
    </div>
  </div>
  <div class="cc-box" style="border-color:rgba(255,45,120,0.3);">
    <div class="lbl" style="color:var(--pink);">💬 Question libre</div>
    <div style="display:flex;gap:6px;margin-top:4px;">
      <input id="ccAiQ" type="text" placeholder="Ex: Meilleurs Over 2.5 Serie A ce week-end ?" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--border);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
      <button onclick="ccFreeQ()" style="padding:8px 16px;border-radius:6px;border:none;background:var(--pink);color:#fff;font-weight:700;cursor:pointer;">💬</button>
    </div>
  </div>
  <div id="ccAiRes"></div>
</div>

<!-- URLS -->
<div id="cc-urls" class="cc-panel">
  <div class="cc-title">📊 ODDS API</div>
  <div class="cc-box"><div class="lbl">Scanner ligue</div><code id="u1"></code><div class="hint">Actions: sports, events, odds, props, scan</div></div>
  <div class="cc-box"><div class="lbl">Props joueur</div><code id="u2"></code></div>
  <div class="cc-title">📈 FOOTYSTATS</div>
  <div class="cc-box"><div class="lbl">Matchs du jour</div><code id="u3"></code></div>
  <div class="cc-box"><div class="lbl">Chercher équipe</div><code id="u4"></code></div>
  <div class="cc-box"><div class="lbl">Stats équipe</div><code id="u5"></code></div>
  <div class="cc-box"><div class="lbl">H2H</div><code id="u6"></code></div>
  <div class="cc-title">🗝️ CLÉS DE LIGUE</div>
  <div style="font-size:11px;color:#aaa;columns:2;column-gap:12px;line-height:1.8;">
    🏴 PL → <b>soccer_epl</b><br>🇪🇸 Liga → <b>soccer_spain_la_liga</b><br>🇮🇹 Serie A → <b>soccer_italy_serie_a</b><br>
    🇩🇪 Buli → <b>soccer_germany_bundesliga</b><br>🇫🇷 L1 → <b>soccer_france_ligue_one</b><br>🏆 CL → <b>soccer_uefa_champs_league</b><br>
    🇳🇱 Ered → <b>soccer_netherlands_eredivisie</b><br>🇧🇷 Bra → <b>soccer_brazil_campeonato</b><br>🇧🇪 Bel → <b>soccer_belgium_first_div</b><br>
    🇺🇸 MLS → <b>soccer_usa_mls</b><br>🇲🇽 MX → <b>soccer_mexico_ligamx</b><br>🌍 CDM → <b>soccer_fifa_world_cup_qualifiers_europe</b>
  </div>
</div>

<div class="cc-footer">StratEdge Pronos · PROMPT v7.1 · Odds + FootyStats + Claude Opus · ⚠️ Ne jamais partager ces URLs</div>
</div>

<script>
const O=window.location.origin,T='stratedge2026';
const LL=[{k:"soccer_epl",l:"🏴󠁧󠁢󠁥󠁮󠁧󠁿 PL"},{k:"soccer_spain_la_liga",l:"🇪🇸 Liga"},{k:"soccer_italy_serie_a",l:"🇮🇹 SerieA"},{k:"soccer_germany_bundesliga",l:"🇩🇪 Buli"},{k:"soccer_france_ligue_one",l:"🇫🇷 L1"},{k:"soccer_uefa_champs_league",l:"🏆 CL"},{k:"soccer_uefa_europa_league",l:"🏆 EL"},{k:"soccer_fifa_world_cup_qualifiers_europe",l:"🌍 CDM"},{k:"soccer_netherlands_eredivisie",l:"🇳🇱 Ered"},{k:"soccer_brazil_campeonato",l:"🇧🇷 Bra"},{k:"soccer_belgium_first_div",l:"🇧🇪 Bel"},{k:"soccer_usa_mls",l:"🇺🇸 MLS"},{k:"soccer_mexico_ligamx",l:"🇲🇽 MX"}];
let cL=LL[0].k;
function ccSwitch(p,b){document.querySelectorAll('.cc-panel').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.cc-nav-btn').forEach(x=>x.classList.remove('active'));document.getElementById('cc-'+p).classList.add('active');b.classList.add('active');}
async function ccF(base,p){const u=new URL(base,O);u.searchParams.set('token',T);Object.entries(p).forEach(([k,v])=>u.searchParams.set(k,v));const r=await fetch(u);if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}
function ccLoading(v){document.getElementById('ccLoad').style.display=v?'block':'none';}
function ccErr(m){const e=document.getElementById('ccError');e.textContent='❌ '+m;e.style.display='block';}
function ccClrErr(){document.getElementById('ccError').style.display='none';}

// INIT
(function(){
  const el=document.getElementById('ccLeagues');
  LL.forEach(l=>{const b=document.createElement('button');b.className='cc-league-btn'+(l.k===cL?' active':'');b.textContent=l.l;b.onclick=()=>{cL=l.k;el.querySelectorAll('.cc-league-btn').forEach(x=>x.classList.remove('active'));b.classList.add('active');};el.appendChild(b);});
  document.getElementById('u1').textContent=O+'/odds-api.php?token='+T+'&action=scan&league=soccer_epl';
  document.getElementById('u2').textContent=O+'/odds-api.php?token='+T+'&action=props&league=soccer_epl&event=EVENT_ID';
  document.getElementById('u3').textContent=O+'/stats-api.php?token='+T+'&action=today';
  document.getElementById('u4').textContent=O+'/stats-api.php?token='+T+'&action=search&q=arsenal';
  document.getElementById('u5').textContent=O+'/stats-api.php?token='+T+'&action=team&id=TEAM_ID';
  document.getElementById('u6').textContent=O+'/stats-api.php?token='+T+'&action=h2h&home=ID1&away=ID2';
})();

// ODDS
async function ccScanOdds(){ccLoading(true);ccClrErr();document.getElementById('ccOddsRes').innerHTML='';document.getElementById('ccOddsDet').style.display='none';
  try{const d=await ccF('/odds-api.php',{action:'scan',league:cL});if(d.error)throw new Error(d.error);const mm=d.matches||d;if(!mm||!mm.length){document.getElementById('ccOddsRes').innerHTML='<div class="cc-status">Aucun match</div>';ccLoading(false);return;}
  document.getElementById('ccQuota').textContent=(d.matches_count||mm.length)+' matchs · '+(d.scanned_at||'');let h='';(d.matches||mm).forEach(m=>{const k=m.kickoff?new Date(m.kickoff).toLocaleDateString('fr-FR',{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):'';let os='';if(m.odds&&m.odds.h2h)os=Object.entries(m.odds.h2h).map(([n,o])=>n+': <b>'+o.price.toFixed(2)+'</b>').join(' · ');const hn=(m.home||'').replace(/'/g,"\\'"),an=(m.away||'').replace(/'/g,"\\'");h+='<div class="cc-match" onclick="ccOpenOdds(\''+m.id+"','"+hn+"','"+an+"')\"><div><span class=\"cc-teams\">"+m.home+'</span><span class="cc-vs">vs</span><span class="cc-teams">'+m.away+'</span></div><div class="cc-meta">'+k+(os?' · '+os:'')+'</div></div>';});
  document.getElementById('ccOddsRes').innerHTML=h;}catch(e){ccErr(e.message);}ccLoading(false);}

async function ccOpenOdds(id,h,a){document.getElementById('ccOddsRes').style.display='none';const d=document.getElementById('ccOddsDet');d.style.display='block';ccLoading(true);
  let x='<button class="cc-back" onclick="ccCloseOdds()">← Retour</button><div class="cc-match"><span class="cc-teams">'+h+'</span><span class="cc-vs">vs</span><span class="cc-teams">'+a+'</span></div>';
  try{const o=await ccF('/odds-api.php',{action:'odds',league:cL,event:id});x+=ccMkts(o);const p=await ccF('/odds-api.php',{action:'props',league:cL,event:id});x+=ccMkts(p,true);}catch(e){x+='<div class="cc-error">'+e.message+'</div>';}
  x+='<div class="cc-box" style="margin-top:10px"><div class="lbl">🤖 URL Claude</div><code>'+O+'/odds-api.php?token='+T+'&action=props&league='+cL+'&event='+id+'</code></div>';d.innerHTML=x;ccLoading(false);}

function ccMkts(data,isP){if(!data?.bookmakers?.length)return isP?'<div class="cc-meta" style="margin-top:8px">Pas de props dispo</div>':'';const M={};for(const bk of data.bookmakers)for(const mk of bk.markets||[]){if(!M[mk.key])M[mk.key]={};for(const oc of mk.outcomes){const l=((oc.description?oc.name+' '+oc.description+' ':oc.name+' ')+(oc.point!=null?oc.point:'')).trim();if(!M[mk.key][l]||oc.price>M[mk.key][l].price)M[mk.key][l]={price:oc.price,bk:bk.title};}}
  const L={h2h:'1X2',totals:'Over/Under',spreads:'Handicap',btts:'BTTS',double_chance:'DC',team_totals:'Buts/Équipe',player_shots_on_target:'🎯 TIRS CADRÉS',player_goal_scorer_anytime:'⚽ BUTEUR'};let h='';for(const[mk,ocs]of Object.entries(M)){h+='<div class="cc-title">'+(L[mk]||mk)+'</div><div class="cc-odds-grid">';Object.entries(ocs).sort((a,b)=>a[1].price-b[1].price).forEach(([l,o])=>{h+='<div class="cc-odds-item"><div class="cc-odds-lbl">'+l+'</div><div class="cc-odds-p">'+o.price.toFixed(2)+'</div><div class="cc-odds-bk">'+o.bk+'</div></div>';});h+='</div>';}return h;}
function ccCloseOdds(){document.getElementById('ccOddsDet').style.display='none';document.getElementById('ccOddsRes').style.display='block';}

// STATS
async function ccToday(){ccLoading(true);ccClrErr();document.getElementById('ccStatsRes').innerHTML='';
  try{const d=await ccF('/stats-api.php',{action:'today'});if(d.error)throw new Error(d.error);const mm=d.matches||[];if(!mm.length){document.getElementById('ccStatsRes').innerHTML='<div class="cc-status">Aucun match</div>';ccLoading(false);return;}
  let h='<div class="cc-title">📅 '+d.matches_count+' matchs — '+d.date+'</div>';mm.forEach(m=>{const s=m.stats||{};let st='';
    [['O2.5',s.over25_potential],['BTTS',s.btts_potential],['O1.5',s.over15_potential],['xG H',s.home_xg],['xG A',s.away_xg]].forEach(([l,v])=>{if(v!=null)st+='<div class="cc-stat"><span class="cc-stat-l">'+l+'</span><span class="cc-stat-v'+(v>=60?' hot':v<35?' cold':'')+'">'+v+'</span></div>';});
    h+='<div class="cc-match"><div><span class="cc-teams">'+(m.home||'?')+'</span><span class="cc-vs">vs</span><span class="cc-teams">'+(m.away||'?')+'</span></div><div class="cc-meta">'+(m.date||'')+' · '+(m.league||'')+'</div>'+(st?'<div class="cc-stats">'+st+'</div>':'')+'</div>';});
  document.getElementById('ccStatsRes').innerHTML=h;}catch(e){ccErr(e.message);}ccLoading(false);}

async function ccSearchTeam(){const q=document.getElementById('ccSearch').value.trim();if(!q)return;ccLoading(true);ccClrErr();
  try{const d=await ccF('/stats-api.php',{action:'search',q:q});if(d.error)throw new Error(d.error);const t=d.data||d;if(!t?.length){document.getElementById('ccStatsRes').innerHTML='<div class="cc-status">Rien trouvé</div>';ccLoading(false);return;}
  let h='<div class="cc-title">Résultats</div>';(Array.isArray(t)?t:[t]).slice(0,10).forEach(x=>{h+='<div class="cc-match"><span class="cc-teams">'+(x.name||x.team_name||'?')+'</span><span class="cc-meta"> · ID:'+(x.id||x.team_id)+' · '+(x.country||'')+'</span></div>';});
  document.getElementById('ccStatsRes').innerHTML=h;}catch(e){ccErr(e.message);}ccLoading(false);}

// AI
async function ccRunAI(){const lg=document.getElementById('ccAiLeague').value,hm=document.getElementById('ccAiHome').value.trim(),aw=document.getElementById('ccAiAway').value.trim();if(!hm||!aw){ccErr('Remplis les deux équipes');return;}
  const btn=document.getElementById('ccAiBtn');btn.disabled=true;btn.textContent='🧠 Analyse en cours...';ccClrErr();
  document.getElementById('ccAiRes').innerHTML='<div class="cc-status">⏳ Claude Opus 4.6 analyse...<br><span style="font-size:11px;color:#888;">~20-45 sec</span></div>';
  try{const r=await fetch(O+'/claude-api.php?token='+T+'&action=analyze&league='+lg+'&home='+encodeURIComponent(hm)+'&away='+encodeURIComponent(aw));const d=await r.json();if(d.error){ccErr(d.error);document.getElementById('ccAiRes').innerHTML='';btn.disabled=false;btn.textContent='🧠 LANCER L\'ANALYSE';return;}ccRenderAI(d);}catch(e){ccErr(e.message);document.getElementById('ccAiRes').innerHTML='';}btn.disabled=false;btn.textContent='🧠 LANCER L\'ANALYSE';}

async function ccFreeQ(){const q=document.getElementById('ccAiQ').value.trim();if(!q)return;ccClrErr();document.getElementById('ccAiRes').innerHTML='<div class="cc-status">⏳ Claude réfléchit...</div>';
  try{const r=await fetch(O+'/claude-api.php?token='+T+'&action=ask&q='+encodeURIComponent(q));const d=await r.json();if(d.error){ccErr(d.error);document.getElementById('ccAiRes').innerHTML='';return;}ccRenderAI(d);}catch(e){ccErr(e.message);document.getElementById('ccAiRes').innerHTML='';}}

function ccRenderAI(d){const a=(d.analysis||'Pas de réponse').replace(/\n/g,'<br>').replace(/\*\*(.*?)\*\*/g,'<b>$1</b>');const s=d.data_sources||{},t=d.tokens_used||{};let b='';if(s.footystats)b+='<span style="background:rgba(0,255,136,0.15);color:var(--green);padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;">📈 FootyStats ✓</span> ';if(s.odds_api)b+='<span style="background:rgba(0,212,255,0.15);color:var(--cyan);padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;">📊 Odds ✓</span> ';
  document.getElementById('ccAiRes').innerHTML='<div style="margin-top:12px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px;"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;"><div>'+b+'</div><div style="font-size:10px;color:#666;">'+(t.input||0)+'+'+(t.output||0)+' tok · '+(t.cost_estimate||'')+'</div></div><div style="font-size:13px;line-height:1.6;color:#ddd;">'+a+'</div></div>';}

document.getElementById('ccSearch')?.addEventListener('keydown',e=>{if(e.key==='Enter')ccSearchTeam();});
document.getElementById('ccAiQ')?.addEventListener('keydown',e=>{if(e.key==='Enter')ccFreeQ();});
</script>
</body>
</html>
