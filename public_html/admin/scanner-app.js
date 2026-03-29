// StratEdge Command Center v1.0
(function(){
  var O = window.location.origin;
  var T = window.STRATEDGE_TOKEN || ''; // injecté par scanner.php via PHP — jamais en dur ici
  var cL = 'soccer_epl';

  var LEAGUES = [
    {k:'soccer_epl',l:'PL'},{k:'soccer_spain_la_liga',l:'Liga'},{k:'soccer_italy_serie_a',l:'Serie A'},
    {k:'soccer_germany_bundesliga',l:'Buli'},{k:'soccer_france_ligue_one',l:'L1'},
    {k:'soccer_uefa_champs_league',l:'CL'},{k:'soccer_uefa_europa_league',l:'EL'},
    {k:'soccer_fifa_world_cup_qualifiers_europe',l:'CDM'},{k:'soccer_netherlands_eredivisie',l:'Ered'},
    {k:'soccer_brazil_campeonato',l:'Bra'},{k:'soccer_belgium_first_div',l:'Bel'},
    {k:'soccer_usa_mls',l:'MLS'},{k:'soccer_mexico_ligamx',l:'MX'}
  ];

  var AI_LEAGUES = [
    {v:'soccer_epl',t:'Premier League'},{v:'soccer_spain_la_liga',t:'La Liga'},
    {v:'soccer_italy_serie_a',t:'Serie A'},{v:'soccer_germany_bundesliga',t:'Bundesliga'},
    {v:'soccer_france_ligue_one',t:'Ligue 1'},{v:'soccer_uefa_champs_league',t:'Champions League'},
    {v:'soccer_netherlands_eredivisie',t:'Eredivisie'},{v:'soccer_brazil_campeonato',t:'Brasileirao'},
    {v:'soccer_usa_mls',t:'MLS'}
  ];

  // BUILD UI
  var app = document.getElementById('app');
  if(!app) return;

  var css = document.createElement('style');
  css.textContent = [
    ':root{--pk:#ff2d78;--cy:#00d4ff;--gn:#00ff88;--gd:#ffd700;--bg:#0a0a1a;--cd:#111118;--bd:#222;}',
    '#app{background:linear-gradient(135deg,var(--bg),#0d1117,#1a0a2e);color:#e0e0e0;font-family:"Rajdhani",sans-serif;padding:0;border:none;}',
    '.ct{display:flex;gap:6px;margin-bottom:12px;}',
    '.ct button{flex:1;padding:10px 8px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);color:#888;font-weight:700;font-size:12px;cursor:pointer;font-family:"Rajdhani",sans-serif;}',
    '.ct button.on{border-color:var(--cy);background:rgba(0,212,255,.08);color:var(--cy);}',
    '.cp{display:none;}.cp.on{display:block;}',
    '.cl{display:flex;flex-wrap:wrap;gap:5px;justify-content:center;margin-bottom:12px;}',
    '.cl button{padding:5px 10px;border-radius:6px;border:1px solid var(--bd);background:var(--cd);color:#888;font-size:11px;font-weight:600;cursor:pointer;font-family:"Rajdhani",sans-serif;}',
    '.cl button.on{border-color:var(--cy);background:rgba(0,212,255,.1);color:var(--cy);}',
    '.cb{display:block;margin:0 auto 12px;padding:10px 32px;border-radius:8px;border:none;background:linear-gradient(90deg,var(--pk),var(--cy));color:#fff;font-family:"Orbitron",monospace;font-size:13px;font-weight:700;cursor:pointer;}',
    '.cb:disabled{opacity:.5;}',
    '.mc{background:var(--cd);border:1px solid var(--bd);border-radius:8px;padding:10px 14px;margin-bottom:6px;cursor:pointer;}',
    '.mc:hover{border-color:var(--pk);}',
    '.tm{font-weight:700;color:#fff;font-size:14px;}',
    '.vs{color:var(--pk);margin:0 6px;font-size:12px;}',
    '.mt{font-size:11px;color:#888;margin-top:3px;}',
    '.og{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:6px;margin-top:8px;}',
    '.oi{background:var(--cd);border:1px solid var(--bd);border-radius:6px;padding:6px 8px;text-align:center;}',
    '.ol{font-size:10px;color:#aaa;}.op{font-size:18px;font-weight:700;color:var(--cy);}.ob{font-size:9px;color:#666;}',
    '.bx{background:rgba(0,212,255,.04);border:1px solid rgba(0,212,255,.3);border-radius:8px;padding:10px;margin-bottom:10px;}',
    '.bx .lb{font-size:11px;color:var(--cy);font-weight:600;margin-bottom:4px;}',
    '.bx code{background:#000;color:var(--gn);padding:3px 6px;border-radius:4px;font-size:10px;display:block;margin-top:3px;word-break:break-all;user-select:all;}',
    '.bx .ht{font-size:9px;color:#555;margin-top:3px;}',
    '.st{text-align:center;padding:16px;color:var(--cy);font-size:13px;}',
    '.er{background:rgba(255,45,120,.1);border:1px solid var(--pk);border-radius:8px;padding:10px;text-align:center;color:var(--pk);margin-bottom:8px;font-size:12px;}',
    '.ti{color:var(--pk);font-family:"Orbitron",monospace;font-size:12px;margin:12px 0 6px;}',
    '.bk{background:none;border:1px solid var(--pk);color:var(--pk);border-radius:6px;padding:5px 14px;cursor:pointer;font-size:11px;margin-bottom:10px;}',
    '.sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:4px;margin-top:6px;}',
    '.sb{background:#0d0d2a;border:1px solid #1a1a3a;border-radius:4px;padding:3px 6px;text-align:center;font-size:10px;}',
    '.sl{color:#888;display:block;}.sv{color:var(--cy);font-weight:700;font-size:13px;}',
    'input,select{font-family:"Rajdhani",sans-serif;}'
  ].join('\n');
  document.head.appendChild(css);

  // HTML
  var h = '';
  // Tabs
  h += '<div class="ct">';
  h += '<button class="on" data-p="odds">Odds</button>';
  h += '<button data-p="stats">Stats</button>';
  h += '<button data-p="ai">Auto-Analyse</button>';
  h += '<button data-p="urls">URLs</button>';
  h += '</div>';
  h += '<div id="ccE" class="er" style="display:none"></div>';
  h += '<div id="ccL" class="st" style="display:none">Chargement...</div>';

  // ODDS PANEL
  h += '<div id="p-odds" class="cp on">';
  h += '<div class="cl" id="lgBtns"></div>';
  h += '<button class="cb" id="bScan">SCANNER COTES</button>';
  h += '<div id="oQ" style="text-align:center;font-size:10px;color:#555;margin-bottom:8px"></div>';
  h += '<div id="oR"></div><div id="oD" style="display:none"></div>';
  h += '</div>';

  // STATS PANEL
  h += '<div id="p-stats" class="cp">';
  h += '<button class="cb" id="bToday">MATCHS DU JOUR</button>';
  h += '<div class="bx"><div class="lb">Rechercher une equipe</div>';
  h += '<div style="display:flex;gap:6px;margin-top:4px">';
  h += '<input id="iSrch" type="text" placeholder="Ex: Arsenal, Bayern..." style="flex:1;padding:6px 10px;border-radius:6px;border:1px solid var(--bd);background:#0a0a1a;color:#fff;font-size:12px">';
  h += '<button id="bSrch" style="padding:6px 14px;border-radius:6px;border:none;background:var(--pk);color:#fff;font-weight:700;cursor:pointer">OK</button>';
  h += '</div></div>';
  h += '<div id="sR"></div></div>';

  // AI PANEL
  h += '<div id="p-ai" class="cp">';
  h += '<div class="bx"><div class="lb">Auto-Analyse (PROMPT v7.1 + data auto)</div>';
  h += '<div class="ht" style="margin-bottom:8px">Claude Opus 4.6 | ~$0.20/analyse</div>';
  h += '<select id="iAiLg" style="width:100%;padding:8px;border-radius:6px;border:1px solid var(--bd);background:#0a0a1a;color:#fff;font-size:12px;margin-bottom:8px">';
  AI_LEAGUES.forEach(function(l){ h += '<option value="'+l.v+'">'+l.t+'</option>'; });
  h += '</select>';
  h += '<div style="display:flex;gap:6px;margin-bottom:8px">';
  h += '<input id="iAiH" type="text" placeholder="Domicile" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--bd);background:#0a0a1a;color:#fff;font-size:12px">';
  h += '<span style="color:var(--pk);align-self:center;font-weight:700">vs</span>';
  h += '<input id="iAiA" type="text" placeholder="Exterieur" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--bd);background:#0a0a1a;color:#fff;font-size:12px">';
  h += '</div>';
  h += '<button class="cb" id="bAI" style="margin:0;width:100%">LANCER ANALYSE</button>';
  h += '</div>';
  h += '<div class="bx" style="border-color:rgba(255,45,120,.3)"><div class="lb" style="color:var(--pk)">Question libre</div>';
  h += '<div style="display:flex;gap:6px;margin-top:4px">';
  h += '<input id="iAiQ" type="text" placeholder="Ex: Meilleurs Over 2.5 Serie A ?" style="flex:1;padding:8px;border-radius:6px;border:1px solid var(--bd);background:#0a0a1a;color:#fff;font-size:12px">';
  h += '<button id="bFQ" style="padding:8px 16px;border-radius:6px;border:none;background:var(--pk);color:#fff;font-weight:700;cursor:pointer">OK</button>';
  h += '</div></div>';
  h += '<div id="aR"></div></div>';

  // URLS PANEL
  h += '<div id="p-urls" class="cp">';
  h += '<div class="ti">ODDS API</div>';
  h += '<div class="bx"><div class="lb">Scanner ligue</div><code>'+O+'/odds-api.php?token='+T+'&action=scan&league=soccer_epl</code><div class="ht">Actions: sports, events, odds, props, scan</div></div>';
  h += '<div class="bx"><div class="lb">Props joueur</div><code>'+O+'/odds-api.php?token='+T+'&action=props&league=soccer_epl&event=EVENT_ID</code></div>';
  h += '<div class="ti">FOOTYSTATS</div>';
  h += '<div class="bx"><div class="lb">Matchs du jour</div><code>'+O+'/stats-api.php?token='+T+'&action=today</code></div>';
  h += '<div class="bx"><div class="lb">Chercher equipe</div><code>'+O+'/stats-api.php?token='+T+'&action=search&q=arsenal</code></div>';
  h += '<div class="bx"><div class="lb">Stats equipe</div><code>'+O+'/stats-api.php?token='+T+'&action=team&id=TEAM_ID</code></div>';
  h += '<div class="bx"><div class="lb">H2H</div><code>'+O+'/stats-api.php?token='+T+'&action=h2h&home=ID1&away=ID2</code></div>';
  h += '<div class="ti">CLES DE LIGUE</div>';
  h += '<div style="font-size:11px;color:#aaa;line-height:1.8">';
  h += 'PL: <b>soccer_epl</b> | Liga: <b>soccer_spain_la_liga</b> | Serie A: <b>soccer_italy_serie_a</b><br>';
  h += 'Buli: <b>soccer_germany_bundesliga</b> | L1: <b>soccer_france_ligue_one</b> | CL: <b>soccer_uefa_champs_league</b><br>';
  h += 'Ered: <b>soccer_netherlands_eredivisie</b> | Bra: <b>soccer_brazil_campeonato</b> | MLS: <b>soccer_usa_mls</b>';
  h += '</div></div>';

  app.innerHTML = h;

  // TABS
  app.querySelectorAll('.ct button').forEach(function(btn){
    btn.addEventListener('click', function(){
      app.querySelectorAll('.ct button').forEach(function(b){b.classList.remove('on');});
      app.querySelectorAll('.cp').forEach(function(p){p.classList.remove('on');});
      btn.classList.add('on');
      document.getElementById('p-'+btn.getAttribute('data-p')).classList.add('on');
    });
  });

  // LEAGUES
  var lgEl = document.getElementById('lgBtns');
  LEAGUES.forEach(function(l){
    var b = document.createElement('button');
    b.textContent = l.l;
    if(l.k === cL) b.className = 'on';
    b.addEventListener('click', function(){
      cL = l.k;
      lgEl.querySelectorAll('button').forEach(function(x){x.classList.remove('on');});
      b.classList.add('on');
    });
    lgEl.appendChild(b);
  });

  // HELPERS
  function apiCall(base, params){
    var u = new URL(base, O);
    u.searchParams.set('token', T);
    for(var k in params) u.searchParams.set(k, params[k]);
    return fetch(u).then(function(r){
      if(!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }
  function loading(v){ document.getElementById('ccL').style.display = v ? 'block' : 'none'; }
  function err(m){ var e = document.getElementById('ccE'); e.textContent = m; e.style.display = 'block'; }
  function clrErr(){ document.getElementById('ccE').style.display = 'none'; }

  function renderMkts(data, isP){
    if(!data || !data.bookmakers || !data.bookmakers.length)
      return isP ? '<div class="mt" style="margin-top:8px">Pas de props dispo</div>' : '';
    var M = {};
    data.bookmakers.forEach(function(bk){
      (bk.markets||[]).forEach(function(mk){
        if(!M[mk.key]) M[mk.key] = {};
        mk.outcomes.forEach(function(oc){
          var lab = (oc.description ? oc.name+' '+oc.description+' ' : oc.name+' ') + (oc.point != null ? oc.point : '');
          lab = lab.trim();
          if(!M[mk.key][lab] || oc.price > M[mk.key][lab].price)
            M[mk.key][lab] = {price: oc.price, bk: bk.title};
        });
      });
    });
    var L = {h2h:'1X2',totals:'Over/Under',spreads:'Handicap',btts:'BTTS',double_chance:'DC',team_totals:'Buts/Equipe',player_shots_on_target:'TIRS CADRES',player_goal_scorer_anytime:'BUTEUR'};
    var out = '';
    for(var mk in M){
      out += '<div class="ti">'+(L[mk]||mk)+'</div><div class="og">';
      Object.entries(M[mk]).sort(function(a,b){return a[1].price - b[1].price;}).forEach(function(e){
        out += '<div class="oi"><div class="ol">'+e[0]+'</div><div class="op">'+e[1].price.toFixed(2)+'</div><div class="ob">'+e[1].bk+'</div></div>';
      });
      out += '</div>';
    }
    return out;
  }

  // SCAN ODDS
  document.getElementById('bScan').addEventListener('click', function(){
    loading(true); clrErr();
    document.getElementById('oR').innerHTML = '';
    document.getElementById('oD').style.display = 'none';
    apiCall('/odds-api.php', {action:'scan', league:cL}).then(function(d){
      loading(false);
      if(d.error){ err(d.error); return; }
      var mm = d.matches || d;
      if(!mm || !mm.length){ document.getElementById('oR').innerHTML = '<div class="st">Aucun match</div>'; return; }
      document.getElementById('oQ').textContent = (d.matches_count||mm.length) + ' matchs';
      var html = '';
      mm.forEach(function(m){
        var k = m.kickoff ? new Date(m.kickoff).toLocaleDateString('fr-FR',{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : '';
        var os = '';
        if(m.odds && m.odds.h2h){
          os = Object.entries(m.odds.h2h).map(function(e){return e[0]+': '+e[1].price.toFixed(2);}).join(' | ');
        }
        html += '<div class="mc" data-id="'+m.id+'" data-h="'+(m.home||'').replace(/"/g,'&quot;')+'" data-a="'+(m.away||'').replace(/"/g,'&quot;')+'">';
        html += '<div><span class="tm">'+m.home+'</span><span class="vs">vs</span><span class="tm">'+m.away+'</span></div>';
        html += '<div class="mt">'+k+(os?' | '+os:'')+'</div></div>';
      });
      document.getElementById('oR').innerHTML = html;
      document.getElementById('oR').querySelectorAll('.mc').forEach(function(card){
        card.addEventListener('click', function(){
          openMatch(card.getAttribute('data-id'), card.getAttribute('data-h'), card.getAttribute('data-a'));
        });
      });
    }).catch(function(e){ loading(false); err(e.message); });
  });

  function openMatch(eid, home, away){
    document.getElementById('oR').style.display = 'none';
    var det = document.getElementById('oD');
    det.style.display = 'block';
    loading(true);
    Promise.all([
      apiCall('/odds-api.php', {action:'odds', league:cL, event:eid}),
      apiCall('/odds-api.php', {action:'props', league:cL, event:eid})
    ]).then(function(res){
      var html = '<button class="bk" id="btnBk">Retour</button>';
      html += '<div class="mc"><span class="tm">'+home+'</span><span class="vs">vs</span><span class="tm">'+away+'</span></div>';
      html += renderMkts(res[0]);
      html += renderMkts(res[1], true);
      html += '<div class="bx" style="margin-top:10px"><div class="lb">URL Claude</div><code>'+O+'/odds-api.php?token='+T+'&action=props&league='+cL+'&event='+eid+'</code></div>';
      det.innerHTML = html;
      document.getElementById('btnBk').addEventListener('click', function(){
        det.style.display = 'none';
        document.getElementById('oR').style.display = 'block';
      });
      loading(false);
    }).catch(function(e){ loading(false); err(e.message); });
  }

  // STATS TODAY
  document.getElementById('bToday').addEventListener('click', function(){
    loading(true); clrErr(); document.getElementById('sR').innerHTML = '';
    apiCall('/stats-api.php', {action:'today'}).then(function(d){
      loading(false);
      if(d.error){ err(d.error); return; }
      var mm = d.matches || [];
      if(!mm.length){ document.getElementById('sR').innerHTML = '<div class="st">Aucun match</div>'; return; }
      var html = '<div class="ti">'+d.matches_count+' matchs - '+d.date+'</div>';
      mm.forEach(function(m){
        var s = m.stats || {};
        var st = '';
        [['O2.5',s.over25_potential],['BTTS',s.btts_potential],['O1.5',s.over15_potential],['xG H',s.home_xg],['xG A',s.away_xg]].forEach(function(x){
          if(x[1] != null) st += '<div class="sb"><span class="sl">'+x[0]+'</span><span class="sv">'+x[1]+'</span></div>';
        });
        html += '<div class="mc"><div><span class="tm">'+(m.home||'?')+'</span><span class="vs">vs</span><span class="tm">'+(m.away||'?')+'</span></div>';
        html += '<div class="mt">'+(m.date||'')+' | '+(m.league||'')+'</div>';
        if(st) html += '<div class="sg">'+st+'</div>';
        html += '</div>';
      });
      document.getElementById('sR').innerHTML = html;
    }).catch(function(e){ loading(false); err(e.message); });
  });

  // SEARCH
  document.getElementById('bSrch').addEventListener('click', doSearch);
  document.getElementById('iSrch').addEventListener('keydown', function(e){ if(e.key==='Enter') doSearch(); });
  function doSearch(){
    var q = document.getElementById('iSrch').value.trim();
    if(!q) return;
    loading(true); clrErr();
    apiCall('/stats-api.php', {action:'search', q:q}).then(function(d){
      loading(false);
      if(d.error){ err(d.error); return; }
      var t = d.data || d;
      if(!t || !t.length){ document.getElementById('sR').innerHTML = '<div class="st">Rien trouve</div>'; return; }
      var html = '<div class="ti">Resultats</div>';
      (Array.isArray(t)?t:[t]).slice(0,10).forEach(function(x){
        html += '<div class="mc"><span class="tm">'+(x.name||x.team_name||'?')+'</span> <span class="mt">ID:'+(x.id||x.team_id)+' | '+(x.country||'')+'</span></div>';
      });
      document.getElementById('sR').innerHTML = html;
    }).catch(function(e){ loading(false); err(e.message); });
  }

  // AI
  document.getElementById('bAI').addEventListener('click', function(){
    var lg = document.getElementById('iAiLg').value;
    var hm = document.getElementById('iAiH').value.trim();
    var aw = document.getElementById('iAiA').value.trim();
    if(!hm || !aw){ err('Remplis les deux equipes'); return; }
    var btn = document.getElementById('bAI');
    btn.disabled = true; btn.textContent = 'Analyse en cours...';
    clrErr();
    document.getElementById('aR').innerHTML = '<div class="st">Claude Opus 4.6 analyse... (~30 sec)</div>';
    apiCall('/claude-api.php', {action:'analyze', league:lg, home:hm, away:aw}).then(function(d){
      btn.disabled = false; btn.textContent = 'LANCER ANALYSE';
      if(d.error){ err(d.error); document.getElementById('aR').innerHTML = ''; return; }
      showAI(d);
    }).catch(function(e){
      btn.disabled = false; btn.textContent = 'LANCER ANALYSE';
      err(e.message); document.getElementById('aR').innerHTML = '';
    });
  });

  document.getElementById('bFQ').addEventListener('click', freeQ);
  document.getElementById('iAiQ').addEventListener('keydown', function(e){ if(e.key==='Enter') freeQ(); });
  function freeQ(){
    var q = document.getElementById('iAiQ').value.trim();
    if(!q) return;
    clrErr();
    document.getElementById('aR').innerHTML = '<div class="st">Claude reflechit...</div>';
    apiCall('/claude-api.php', {action:'ask', q:q}).then(function(d){
      if(d.error){ err(d.error); document.getElementById('aR').innerHTML = ''; return; }
      showAI(d);
    }).catch(function(e){ err(e.message); document.getElementById('aR').innerHTML = ''; });
  }

  function showAI(d){
    var a = (d.analysis||'Pas de reponse').replace(/\n/g,'<br>').replace(/\*\*(.*?)\*\*/g,'<b>$1</b>');
    var s = d.data_sources||{}, t = d.tokens_used||{};
    var badges = '';
    if(s.footystats) badges += '<span style="background:rgba(0,255,136,.15);color:#00ff88;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700">FootyStats OK</span> ';
    if(s.odds_api) badges += '<span style="background:rgba(0,212,255,.15);color:#00d4ff;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:700">Odds OK</span> ';
    document.getElementById('aR').innerHTML = '<div style="margin-top:12px;background:var(--cd);border:1px solid var(--bd);border-radius:10px;padding:14px;">'
      + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><div>'+badges+'</div>'
      + '<div style="font-size:10px;color:#666">'+(t.input||0)+'+'+(t.output||0)+' tok | '+(t.cost_estimate||'')+'</div></div>'
      + '<div style="font-size:13px;line-height:1.6;color:#ddd">'+a+'</div></div>';
  }

})();
