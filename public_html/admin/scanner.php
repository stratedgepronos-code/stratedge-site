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
  <title>Command Center — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --pink: #ff2d78; --cyan: #00d4ff; --green: #00ff88; --gold: #ffd700; --bg: #0a0a1a; --card: #111118; --brd: #222; }
    .main { padding: 1rem !important; background: linear-gradient(135deg, var(--bg) 0%, #0d1117 50%, #1a0a2e 100%); min-height: 100vh; color: #e0e0e0; font-family: 'Rajdhani', sans-serif; }
    .cc-hdr { border-bottom: 2px solid; border-image: linear-gradient(90deg, var(--pink), var(--cyan)) 1; padding-bottom: 10px; margin-bottom: 12px; text-align: center; }
    .cc-hdr h2 { font-family: 'Orbitron', monospace; font-size: clamp(16px, 3vw, 24px); background: linear-gradient(90deg, var(--pink), var(--cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; }
    .cc-hdr small { font-size: 11px; color: #666; }
    .cc-tabs { display: flex; gap: 6px; margin-bottom: 12px; }
    .cc-tab { flex: 1; padding: 10px 8px; border-radius: 8px; border: 1px solid var(--brd); background: var(--bg); color: #888; font-weight: 700; font-size: 12px; cursor: pointer; text-align: center; font-family: 'Rajdhani', sans-serif; }
    .cc-tab.on { border-color: var(--cyan); background: rgba(0,212,255,0.08); color: var(--cyan); }
    .cc-p { display: none; } .cc-p.on { display: block; }
    .cc-lgs { display: flex; flex-wrap: wrap; gap: 5px; justify-content: center; margin-bottom: 12px; }
    .cc-lg { padding: 5px 10px; border-radius: 6px; border: 1px solid var(--brd); background: var(--card); color: #888; font-size: 11px; font-weight: 600; cursor: pointer; font-family: 'Rajdhani', sans-serif; }
    .cc-lg.on { border-color: var(--cyan); background: rgba(0,212,255,0.1); color: var(--cyan); }
    .cc-btn { display: block; margin: 0 auto 12px; padding: 10px 32px; border-radius: 8px; border: none; background: linear-gradient(90deg, var(--pink), var(--cyan)); color: #fff; font-family: 'Orbitron', monospace; font-size: 13px; font-weight: 700; cursor: pointer; }
    .cc-btn:disabled { opacity: 0.5; }
    .cc-card { background: var(--card); border: 1px solid var(--brd); border-radius: 8px; padding: 10px 14px; margin-bottom: 6px; cursor: pointer; }
    .cc-card:hover { border-color: var(--pink); }
    .cc-tm { font-weight: 700; color: #fff; font-size: 14px; }
    .cc-v { color: var(--pink); margin: 0 6px; font-size: 12px; }
    .cc-m { font-size: 11px; color: #888; margin-top: 3px; }
    .cc-og { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 6px; margin-top: 8px; }
    .cc-oi { background: var(--card); border: 1px solid var(--brd); border-radius: 6px; padding: 6px 8px; text-align: center; }
    .cc-ol { font-size: 10px; color: #aaa; } .cc-op { font-size: 18px; font-weight: 700; color: var(--cyan); } .cc-ob { font-size: 9px; color: #666; }
    .cc-bx { background: rgba(0,212,255,0.04); border: 1px solid rgba(0,212,255,0.3); border-radius: 8px; padding: 10px; margin-bottom: 10px; }
    .cc-bx .lb { font-size: 11px; color: var(--cyan); font-weight: 600; margin-bottom: 4px; }
    .cc-bx code { background: #000; color: var(--green); padding: 3px 6px; border-radius: 4px; font-size: 10px; display: block; margin-top: 3px; word-break: break-all; user-select: all; }
    .cc-bx .ht { font-size: 9px; color: #555; margin-top: 3px; }
    .cc-st { text-align: center; padding: 16px; color: var(--cyan); font-size: 13px; }
    .cc-er { background: rgba(255,45,120,0.1); border: 1px solid var(--pink); border-radius: 8px; padding: 10px; text-align: center; color: var(--pink); margin-bottom: 8px; font-size: 12px; }
    .cc-ti { color: var(--pink); font-family: 'Orbitron', monospace; font-size: 12px; margin: 12px 0 6px; }
    .cc-bk { background: none; border: 1px solid var(--pink); color: var(--pink); border-radius: 6px; padding: 5px 14px; cursor: pointer; font-size: 11px; margin-bottom: 10px; }
    .cc-ft { text-align: center; margin-top: 24px; padding: 10px 0; border-top: 1px solid var(--brd); font-size: 9px; color: #444; }
    .cc-sg { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 4px; margin-top: 6px; }
    .cc-sb { background: #0d0d2a; border: 1px solid #1a1a3a; border-radius: 4px; padding: 3px 6px; text-align: center; font-size: 10px; }
    .cc-sl { color: #888; display: block; } .cc-sv { color: var(--cyan); font-weight: 700; font-size: 13px; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">

<div class="cc-hdr"><h2>⚡ STRATEDGE COMMAND CENTER</h2><small>PROMPT v7.1 · 49 rules · Odds + FootyStats + Claude Opus</small></div>

<div class="cc-tabs">
  <button class="cc-tab on" data-panel="odds">📊 Odds</button>
  <button class="cc-tab" data-panel="stats">📈 Stats</button>
  <button class="cc-tab" data-panel="ai">🧠 Auto-Analyse</button>
  <button class="cc-tab" data-panel="urls">🤖 URLs</button>
</div>

<div id="ccErr" class="cc-er" style="display:none;"></div>
<div id="ccLoad" class="cc-st" style="display:none;">⏳ Chargement...</div>

<!-- ODDS -->
<div id="p-odds" class="cc-p on">
  <div class="cc-lgs" id="ccLgs"></div>
  <button class="cc-btn" id="btnScan">🔍 SCANNER COTES</button>
  <div id="ccQ" style="text-align:center;font-size:10px;color:#555;margin-bottom:8px;"></div>
  <div id="ccOR"></div>
  <div id="ccOD" style="display:none;"></div>
</div>

<!-- STATS -->
<div id="p-stats" class="cc-p">
  <button class="cc-btn" id="btnToday">📅 MATCHS DU JOUR</button>
  <div class="cc-bx"><div class="lb">Rechercher une equipe</div>
    <div style="display:flex;gap:6px;margin-top:4px;">
      <input id="ccSrch" type="text" placeholder="Ex: Arsenal, Bayern..." style="flex:1;padding:6px 10px;border-radius:6px;border:1px solid var(--brd);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
      <button id="btnSrch" style="padding:6px 14px;border-radius:6px;border:none;background:var(--pink);color:#fff;font-weight:700;cursor:pointer;">🔍</button>
    </div>
  </div>
  <div id="ccSR"></div>
</div>

<!-- AI -->
<div id="p-ai" class="cc-p">
  <div class="cc-bx">
    <div class="lb">🧠 Auto-Analyse (PROMPT v7.1 + data auto)</div>
    <div class="ht" style="margin-bottom:8px;">Claude Opus 4.6 · ~$0.20/analyse</div>
    <select id="ccAiLg" style="width:100%;padding:8px;border-radius:6px;border:1px solid var(--brd);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;margin-bottom:8px;">
      <option value="soccer_epl">🏴 Premier League</option>
      <option value="soccer_spain_la_liga">🇪🇸 La Liga</option>
      <option value="soccer_italy_serie_a">🇮🇹 Serie A</option>
      <option value="soccer_germany_bundesliga">🇩🇪 Bundesliga</option>
      <option value="soccer_france_ligue_one">🇫🇷 Ligue 1</option>
      <option value="soccer_uefa_champs_league">🏆 Champions League</option>
      <option value="soccer_netherlands_eredivisie">🇳🇱 Eredivisie</option>
      <option value="soccer_brazil_campeonato">🇧🇷 Brasileirao</option>
      <option value="soccer_usa_mls">🇺🇸 MLS</option>
    </select>
    <div style="display:flex;gap:6px;margin-bottom:8px;">
      <input id="ccAiH" type="text" placeholder="Domicile" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--brd);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
      <span style="color:var(--pink);align-self:center;font-weight:700;">vs</span>
      <input id="ccAiA" type="text" placeholder="Exterieur" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--brd);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
    </div>
    <button class="cc-btn" id="btnAI" style="margin:0;width:100%;">🧠 LANCER L'ANALYSE</button>
  </div>
  <div class="cc-bx" style="border-color:rgba(255,45,120,0.3);">
    <div class="lb" style="color:var(--pink);">💬 Question libre</div>
    <div style="display:flex;gap:6px;margin-top:4px;">
      <input id="ccAiQ" type="text" placeholder="Ex: Meilleurs Over 2.5 Serie A ?" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--brd);background:#0a0a1a;color:#fff;font-size:12px;font-family:'Rajdhani',sans-serif;">
      <button id="btnFQ" style="padding:8px 16px;border-radius:6px;border:none;background:var(--pink);color:#fff;font-weight:700;cursor:pointer;">💬</button>
    </div>
  </div>
  <div id="ccAiR"></div>
</div>

<!-- URLS -->
<div id="p-urls" class="cc-p">
  <div class="cc-ti">📊 ODDS API</div>
  <div class="cc-bx"><div class="lb">Scanner ligue</div><code id="u1"></code><div class="ht">Actions: sports, events, odds, props, scan</div></div>
  <div class="cc-bx"><div class="lb">Props joueur</div><code id="u2"></code></div>
  <div class="cc-ti">📈 FOOTYSTATS</div>
  <div class="cc-bx"><div class="lb">Matchs du jour</div><code id="u3"></code></div>
  <div class="cc-bx"><div class="lb">Chercher equipe</div><code id="u4"></code></div>
  <div class="cc-bx"><div class="lb">Stats equipe</div><code id="u5"></code></div>
  <div class="cc-bx"><div class="lb">H2H</div><code id="u6"></code></div>
  <div class="cc-ti">🗝️ CLES DE LIGUE</div>
  <div style="font-size:11px;color:#aaa;columns:2;column-gap:12px;line-height:1.8;">
    PL: <b>soccer_epl</b><br>Liga: <b>soccer_spain_la_liga</b><br>Serie A: <b>soccer_italy_serie_a</b><br>
    Buli: <b>soccer_germany_bundesliga</b><br>L1: <b>soccer_france_ligue_one</b><br>CL: <b>soccer_uefa_champs_league</b><br>
    Ered: <b>soccer_netherlands_eredivisie</b><br>Bra: <b>soccer_brazil_campeonato</b><br>Bel: <b>soccer_belgium_first_div</b><br>
    MLS: <b>soccer_usa_mls</b><br>MX: <b>soccer_mexico_ligamx</b><br>CDM: <b>soccer_fifa_world_cup_qualifiers_europe</b>
  </div>
</div>

<div class="cc-ft">StratEdge Pronos · PROMPT v7.1 · Odds + FootyStats + Claude Opus</div>
</div>

<script>
var O=window.location.origin, T='stratedge2026', cL='soccer_epl';
var LL=[
  {k:'soccer_epl',l:'PL'},{k:'soccer_spain_la_liga',l:'Liga'},{k:'soccer_italy_serie_a',l:'SerieA'},
  {k:'soccer_germany_bundesliga',l:'Buli'},{k:'soccer_france_ligue_one',l:'L1'},
  {k:'soccer_uefa_champs_league',l:'CL'},{k:'soccer_uefa_europa_league',l:'EL'},
  {k:'soccer_fifa_world_cup_qualifiers_europe',l:'CDM'},{k:'soccer_netherlands_eredivisie',l:'Ered'},
  {k:'soccer_brazil_campeonato',l:'Bra'},{k:'soccer_belgium_first_div',l:'Bel'},
  {k:'soccer_usa_mls',l:'MLS'},{k:'soccer_mexico_ligamx',l:'MX'}
];

// TABS
document.querySelectorAll('.cc-tab').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.querySelectorAll('.cc-tab').forEach(function(b){b.classList.remove('on');});
    document.querySelectorAll('.cc-p').forEach(function(p){p.classList.remove('on');});
    btn.classList.add('on');
    document.getElementById('p-'+btn.getAttribute('data-panel')).classList.add('on');
  });
});

// LEAGUES
var lgEl=document.getElementById('ccLgs');
LL.forEach(function(l){
  var b=document.createElement('button');
  b.className='cc-lg'+(l.k===cL?' on':'');
  b.textContent=l.l;
  b.addEventListener('click',function(){
    cL=l.k;
    lgEl.querySelectorAll('.cc-lg').forEach(function(x){x.classList.remove('on');});
    b.classList.add('on');
  });
  lgEl.appendChild(b);
});

// URLS
document.getElementById('u1').textContent=O+'/odds-api.php?token='+T+'&action=scan&league=soccer_epl';
document.getElementById('u2').textContent=O+'/odds-api.php?token='+T+'&action=props&league=soccer_epl&event=EVENT_ID';
document.getElementById('u3').textContent=O+'/stats-api.php?token='+T+'&action=today';
document.getElementById('u4').textContent=O+'/stats-api.php?token='+T+'&action=search&q=arsenal';
document.getElementById('u5').textContent=O+'/stats-api.php?token='+T+'&action=team&id=TEAM_ID';
document.getElementById('u6').textContent=O+'/stats-api.php?token='+T+'&action=h2h&home=ID1&away=ID2';

// HELPERS
function ccFetch(base,p){
  var u=new URL(base,O);
  u.searchParams.set('token',T);
  for(var k in p) u.searchParams.set(k,p[k]);
  return fetch(u).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();});
}
function ccLoading(v){document.getElementById('ccLoad').style.display=v?'block':'none';}
function ccError(m){var e=document.getElementById('ccErr');e.textContent=m;e.style.display='block';}
function ccClrErr(){document.getElementById('ccErr').style.display='none';}

// ODDS SCAN
document.getElementById('btnScan').addEventListener('click', function(){
  ccLoading(true);ccClrErr();
  document.getElementById('ccOR').innerHTML='';
  document.getElementById('ccOD').style.display='none';
  ccFetch('/odds-api.php',{action:'scan',league:cL}).then(function(d){
    ccLoading(false);
    if(d.error){ccError(d.error);return;}
    var mm=d.matches||d;
    if(!mm||!mm.length){document.getElementById('ccOR').innerHTML='<div class="cc-st">Aucun match</div>';return;}
    document.getElementById('ccQ').textContent=(d.matches_count||mm.length)+' matchs';
    var h='';
    mm.forEach(function(m){
      var k=m.kickoff?new Date(m.kickoff).toLocaleDateString('fr-FR',{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):'';
      var os='';
      if(m.odds&&m.odds.h2h){
        var entries=Object.entries(m.odds.h2h);
        os=entries.map(function(e){return e[0]+': '+e[1].price.toFixed(2);}).join(' | ');
      }
      h+='<div class="cc-card" data-eid="'+m.id+'" data-home="'+(m.home||'').replace(/"/g,'&quot;')+'" data-away="'+(m.away||'').replace(/"/g,'&quot;')+'">';
      h+='<div><span class="cc-tm">'+m.home+'</span><span class="cc-v">vs</span><span class="cc-tm">'+m.away+'</span></div>';
      h+='<div class="cc-m">'+k+(os?' | '+os:'')+'</div></div>';
    });
    document.getElementById('ccOR').innerHTML=h;
    document.getElementById('ccOR').querySelectorAll('.cc-card').forEach(function(card){
      card.addEventListener('click',function(){
        ccOpenOdds(card.getAttribute('data-eid'),card.getAttribute('data-home'),card.getAttribute('data-away'));
      });
    });
  }).catch(function(e){ccLoading(false);ccError(e.message);});
});

function ccOpenOdds(eid,home,away){
  document.getElementById('ccOR').style.display='none';
  var det=document.getElementById('ccOD');
  det.style.display='block';
  ccLoading(true);
  var h='<button class="cc-bk" id="btnBack">← Retour</button>';
  h+='<div class="cc-card"><span class="cc-tm">'+home+'</span><span class="cc-v">vs</span><span class="cc-tm">'+away+'</span></div>';

  Promise.all([
    ccFetch('/odds-api.php',{action:'odds',league:cL,event:eid}),
    ccFetch('/odds-api.php',{action:'props',league:cL,event:eid})
  ]).then(function(results){
    h+=ccRenderMkts(results[0]);
    h+=ccRenderMkts(results[1],true);
    h+='<div class="cc-bx" style="margin-top:10px"><div class="lb">URL Claude</div><code>'+O+'/odds-api.php?token='+T+'&action=props&league='+cL+'&event='+eid+'</code></div>';
    det.innerHTML=h;
    document.getElementById('btnBack').addEventListener('click',function(){
      det.style.display='none';
      document.getElementById('ccOR').style.display='block';
    });
    ccLoading(false);
  }).catch(function(e){
    h+='<div class="cc-er">'+e.message+'</div>';
    det.innerHTML=h;
    ccLoading(false);
  });
}

function ccRenderMkts(data,isP){
  if(!data||!data.bookmakers||!data.bookmakers.length) return isP?'<div class="cc-m" style="margin-top:8px">Pas de props dispo</div>':'';
  var M={};
  data.bookmakers.forEach(function(bk){
    (bk.markets||[]).forEach(function(mk){
      if(!M[mk.key])M[mk.key]={};
      mk.outcomes.forEach(function(oc){
        var l=(oc.description?oc.name+' '+oc.description+' ':oc.name+' ')+(oc.point!=null?oc.point:'');
        l=l.trim();
        if(!M[mk.key][l]||oc.price>M[mk.key][l].price) M[mk.key][l]={price:oc.price,bk:bk.title};
      });
    });
  });
  var L={h2h:'1X2',totals:'Over/Under',spreads:'Handicap',btts:'BTTS',double_chance:'DC',team_totals:'Buts/Equipe',player_shots_on_target:'TIRS CADRES',player_goal_scorer_anytime:'BUTEUR'};
  var h='';
  for(var mk in M){
    h+='<div class="cc-ti">'+(L[mk]||mk)+'</div><div class="cc-og">';
    var entries=Object.entries(M[mk]).sort(function(a,b){return a[1].price-b[1].price;});
    entries.forEach(function(e){
      h+='<div class="cc-oi"><div class="cc-ol">'+e[0]+'</div><div class="cc-op">'+e[1].price.toFixed(2)+'</div><div class="cc-ob">'+e[1].bk+'</div></div>';
    });
    h+='</div>';
  }
  return h;
}

// STATS TODAY
document.getElementById('btnToday').addEventListener('click',function(){
  ccLoading(true);ccClrErr();document.getElementById('ccSR').innerHTML='';
  ccFetch('/stats-api.php',{action:'today'}).then(function(d){
    ccLoading(false);if(d.error){ccError(d.error);return;}
    var mm=d.matches||[];
    if(!mm.length){document.getElementById('ccSR').innerHTML='<div class="cc-st">Aucun match</div>';return;}
    var h='<div class="cc-ti">'+d.matches_count+' matchs - '+d.date+'</div>';
    mm.forEach(function(m){
      var s=m.stats||{};
      var st='';
      [['O2.5',s.over25_potential],['BTTS',s.btts_potential],['O1.5',s.over15_potential],['xG H',s.home_xg],['xG A',s.away_xg]].forEach(function(x){
        if(x[1]!=null) st+='<div class="cc-sb"><span class="cc-sl">'+x[0]+'</span><span class="cc-sv">'+x[1]+'</span></div>';
      });
      h+='<div class="cc-card"><div><span class="cc-tm">'+(m.home||'?')+'</span><span class="cc-v">vs</span><span class="cc-tm">'+(m.away||'?')+'</span></div>';
      h+='<div class="cc-m">'+(m.date||'')+' | '+(m.league||'')+'</div>';
      if(st) h+='<div class="cc-sg">'+st+'</div>';
      h+='</div>';
    });
    document.getElementById('ccSR').innerHTML=h;
  }).catch(function(e){ccLoading(false);ccError(e.message);});
});

// SEARCH TEAM
document.getElementById('btnSrch').addEventListener('click',doSearch);
document.getElementById('ccSrch').addEventListener('keydown',function(e){if(e.key==='Enter')doSearch();});
function doSearch(){
  var q=document.getElementById('ccSrch').value.trim();
  if(!q)return;ccLoading(true);ccClrErr();
  ccFetch('/stats-api.php',{action:'search',q:q}).then(function(d){
    ccLoading(false);if(d.error){ccError(d.error);return;}
    var t=d.data||d;if(!t||!t.length){document.getElementById('ccSR').innerHTML='<div class="cc-st">Rien trouve</div>';return;}
    var h='<div class="cc-ti">Resultats</div>';
    (Array.isArray(t)?t:[t]).slice(0,10).forEach(function(x){
      h+='<div class="cc-card"><span class="cc-tm">'+(x.name||x.team_name||'?')+'</span> <span class="cc-m">ID:'+(x.id||x.team_id)+' | '+(x.country||'')+'</span></div>';
    });
    document.getElementById('ccSR').innerHTML=h;
  }).catch(function(e){ccLoading(false);ccError(e.message);});
}

// AI ANALYSIS
document.getElementById('btnAI').addEventListener('click',function(){
  var lg=document.getElementById('ccAiLg').value;
  var hm=document.getElementById('ccAiH').value.trim();
  var aw=document.getElementById('ccAiA').value.trim();
  if(!hm||!aw){ccError('Remplis les deux equipes');return;}
  var btn=document.getElementById('btnAI');
  btn.disabled=true;btn.textContent='Analyse en cours...';
  ccClrErr();
  document.getElementById('ccAiR').innerHTML='<div class="cc-st">Claude Opus 4.6 analyse... (~30 sec)</div>';
  ccFetch('/claude-api.php',{action:'analyze',league:lg,home:hm,away:aw}).then(function(d){
    btn.disabled=false;btn.textContent='🧠 LANCER L\'ANALYSE';
    if(d.error){ccError(d.error);document.getElementById('ccAiR').innerHTML='';return;}
    ccShowAI(d);
  }).catch(function(e){btn.disabled=false;btn.textContent='🧠 LANCER L\'ANALYSE';ccError(e.message);document.getElementById('ccAiR').innerHTML='';});
});

document.getElementById('btnFQ').addEventListener('click',doFreeQ);
document.getElementById('ccAiQ').addEventListener('keydown',function(e){if(e.key==='Enter')doFreeQ();});
function doFreeQ(){
  var q=document.getElementById('ccAiQ').value.trim();
  if(!q)return;ccClrErr();
  document.getElementById('ccAiR').innerHTML='<div class="cc-st">Claude reflechit...</div>';
  ccFetch('/claude-api.php',{action:'ask',q:q}).then(function(d){
    if(d.error){ccError(d.error);document.getElementById('ccAiR').innerHTML='';return;}
    ccShowAI(d);
  }).catch(function(e){ccError(e.message);document.getElementById('ccAiR').innerHTML='';});
}

function ccShowAI(d){
  var a=(d.analysis||'Pas de reponse').replace(/\n/g,'<br>').replace(/\*\*(.*?)\*\*/g,'<b>$1</b>');
  var s=d.data_sources||{}, t=d.tokens_used||{};
  var b='';
  if(s.footystats) b+='<span style="background:rgba(0,255,136,0.15);color:#00ff88;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;">FootyStats OK</span> ';
  if(s.odds_api) b+='<span style="background:rgba(0,212,255,0.15);color:#00d4ff;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700;">Odds OK</span> ';
  document.getElementById('ccAiR').innerHTML='<div style="margin-top:12px;background:var(--card);border:1px solid var(--brd);border-radius:10px;padding:14px;">'
    +'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;"><div>'+b+'</div>'
    +'<div style="font-size:10px;color:#666;">'+(t.input||0)+'+'+(t.output||0)+' tok | '+(t.cost_estimate||'')+'</div></div>'
    +'<div style="font-size:13px;line-height:1.6;color:#ddd;">'+a+'</div></div>';
}
</script>
</body>
</html>
