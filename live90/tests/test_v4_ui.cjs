/* Browser checks of real V4 HTML/CSS/JS with a local, synthetic API only. */
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const{createRequire}=require('node:module');
const req=process.env.LIVE90_UI_MODULES?createRequire(path.join(process.env.LIVE90_UI_MODULES,'package.json')):require;
const{chromium}=req('playwright');
const assets=path.resolve(__dirname,'../public/admin/slate/live90/assets');const output=process.env.LIVE90_UI_OUTPUT||'/tmp/live90-ui';fs.mkdirSync(output,{recursive:true});
const now=new Date().toISOString();
const d={market:'goal_ht',team:'h',status:'signal',reason:'Deux relevés concordants',intensity:87,threshold:70,metrics:{shots:10,sot:4,shots10:6,sot10:2,activity_ratio:1.45},sample_id:1,context_notes:['Composition probable, à confirmer.']};
const profile={usable:true,prematch:{n_h:10,n_a:10,shots_h:13,shots_a:11,sot_h:4.3,sot_a:3.2,gf_h:1.6,gf_a:1.2,ga_h:1.1,ga_a:1.4}};
const analyst={usable:true,summary:'Le rythme du direct doit être comparé à la production habituelle.',teams:{h:{note:'Calendrier chargé à surveiller.',flags:[]},a:{note:'Effectif à confirmer.',flags:[]}},unknowns:['Onze officiel'],sources:[]};
analyst.referee={name:'Arbitre Test',appointment:'confirmed',note:'Échantillon fictif de recette',stats:{sample_label:'Compétition test',matches:25,yellow_per_match:4.2,red_per_match:.1,fouls_per_match:null}};
const teams=[['Équipe A','Équipe B'],['Équipe C','Équipe D'],['Équipe E','Équipe F'],['Équipe G','Équipe H']];
const board={version:'live4.0',server_time:now,feed:{received_at:now,rows:43,collector_version:'4.0.0'},engine:{at:now,version:'decision5.0'},settings:{telegram_enabled:true,min_odds:1.65},telegram:{configured:true},totals:{total:1,won:0,lost:0,void:0,pending:1,units:0},history_total:1,legacy:[],matches:teams.map((t,i)=>({home:t[0],away:t[1],league:'Compétition de démonstration',packball_id:String(101+i),sample_id:1,received_at:now,kickoff_ts:now,state:'LIVE',minute:32+i*8,score:{h:i%2,a:0},profile,analyst,stats:{red_cards:{h:0,a:0},yellow_cards:{h:0,a:1},fouls:{h:8,a:4}},quotes:[],decisions:['goal_ht','goal_ft','card_ft'].flatMap(m=>['h','a'].map(team=>({...d,market:m,team,status:i===0&&team==='h'&&m==='goal_ht'?'signal':i===1?'price':'watch',intensity:i===1?78:62})))})),signals:[{id:1,match_id:'101',market:'goal_ht',team:'h',line:.5,odds:1.92,created_at:now,outcome:'pending',delivery:'sent',data:{home:'Équipe A',away:'Équipe B',minute:32,score:{h:0,a:0},label:'Équipe A · plus de 0,5 but en première mi-temps',decision:d,profile,context:analyst}}]};
board.learning={revision:2,generated_at:now,review_count:3,match_count:2,settled_count:2,prompt_addendum:'Comparer les faits, pas inventer la cause.',recent:[{radar_id:12,home:'Équipe A',away:'Équipe B',team:'h',market:'goals',label:'Seuil non atteint',revision:1,facts:['Compteur final : 0.'],flags:[{detail:'Qualité des occasions inconnue <script>throw 1</script>'}],hypotheses:['Comparer les deux résultats.'],conclusion:'Cause non identifiée.'}]};
board.radar_telegram={enabled:true,configured:true,counts:{sent:2},last:null};
board.matches[3].state='HT';
board.matches.forEach((r,i)=>{r.advice=['h','a'].flatMap(team=>['goals','corners','cards'].map(market=>({team,market,status:i===0&&team==='h'?'active':i===1?'missing':'neutral',title:i===0&&team==='h'?'À regarder':i===1?'À compléter':'Observer',observed:0,baseline:market==='corners'?null:1.8,sample:10,reason:market==='goals'?'4 tirs dont 2 cadrés en 10 min · 2 tirs en 5 min.':market==='cards'?'3 fautes en 10 min dont 1 en 5 min.':'4 tirs et 0 corner en 10 min.',facts:['Exemple de recette'],limits:['Données synthétiques, pas une recommandation'],criteria:'Seuil descriptif'})));});
board.matches[0].advice.find(d=>d.team==='h'&&d.market==='goals').conversion={status:'available',period:'FT',usage:'context_only',lines:['Repère FT Packball : 8,14 tirs/but · 2,29 cadrés/but · n=10.','Match en cours (cumul FT) : 7 tirs, 3 cadrés, 0 but(s).'],caution:'Aucun but n’est dû. <img src=x onerror=alert(1)>'};
board.matches.forEach(r=>r.advice.forEach(d=>{d.sample_id=r.sample_id;d.period=d.market==='goals'&&r.minute<=45?'HT':'FT';d.coherence={status:'confirmed',policy:'prematch_live_context_v4',lines:['Pré-match et live synthétiques concordants'],missing:[],conflicts:[]};d.metrics=d.market==='cards'?{fouls:9,fouls10:3,fouls5:1}:d.market==='goals'?{sot:8,sot10:2,sot_per_goal:6}:{shots10:5,corners10:0,blocked_shots10:2};if(d.team==='a')d.metrics={};}));
board.matches[0].advice.find(d=>d.team==='h'&&d.market==='goals').situation={clock_line:'égalité · 13 min réglementaires avant la pause restantes, hors arrêts de jeu',lines:['3 tirs dans la surface sur 4 en 10 min.']};
board.matches[0].advice.find(d=>d.team==='h'&&d.market==='cards').context_evidence={support:'documented',lines:['Arbitre Test : 4,7 jaunes / match · n=20','Équipe A : domicile 2,4 jaunes/m · n=20','Équipe B : extérieur 1,8 jaunes/m · n=20']};
board.matches[0].stake_cuts={state:'received',at:now,reason:'Instantané de recette',quotes:[
 {team:'h',kind:'cards',period:'FT',line:2.5,odds:1.8,unit:'stake_bookings',half_line:true,needed:3,current:0,indicative_counter:true,score:{h:0,a:0},received_at:now,note:'Bookings distincts des jaunes'},
 {team:'h',kind:'corners',period:'FT',line:5.5,odds:1.9,unit:'corners',half_line:true,needed:3,current:3,indicative_counter:false,score:{h:0,a:0},received_at:now,note:'Écart au cut uniquement'},
 {team:'h',kind:'goals',period:'FT',line:.5,odds:1.6,unit:'goals',half_line:true,needed:1,current:0,indicative_counter:false,score:{h:0,a:0},received_at:now,note:'Écart au cut uniquement'}]};
board.observations=[{id:1,team:'h',market:'goals',created_at:now,data:{home:'Équipe A',away:'Équipe B',minute:32,reading:board.matches[0].advice[0]}}];

board.matches[0].stake_cuts.quotes.push({...board.matches[0].stake_cuts.quotes[2],period:'HT',odds:1.85});
board.observations[0].result={outcome:'observed',data:{current:2,target:{team:'h',market:'goals',period:'HT',line:1.5,mode:'cut'}},notification:{delivery:'sent'}};

board.health={status:'issues',issues:[{code:'stats',detail:'Fautes manquantes sur équipe A'}]};
board.signals[0].data.review={generated_at:now,facts:['Tirs après le signal : 10 → 14'],limits:['Trou de collecte'],conclusion:'Cause non démontrée',next_steps:[]};
let posted=[];let imported=[];
const collectorCode=fs.readFileSync(path.resolve(__dirname,'../live90-packball.user.js'),'utf8');const collectorVersion=collectorCode.match(/^\/\/\s*@version\s+(\S+)/m)[1];let collectorReply={status:503,body:'Fichier indisponible'};
const html=`<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="test"><style>${fs.readFileSync(path.join(assets,'style.css'),'utf8')} .mock-sidebar{position:fixed;inset:0 auto 0 0;width:240px;background:#0c0e16;border-right:1px solid #222635;padding:34px 22px;font:12px Arial;color:#707b94}.mock-sidebar b{display:block;font-size:17px;color:#f7f7ff;margin-bottom:50px}.mock-sidebar div{padding:15px;border-radius:8px;margin-bottom:8px}.mock-sidebar .current{background:#ff2d7817;color:#ff2d78}@media(max-width:900px){.mock-sidebar{display:none}}</style></head><body><aside class="mock-sidebar"><b>STRATEDGE</b><div>Vue d’ensemble</div><div>Football Lab</div><div class="current">◉ Live Intelligence</div><div>Pronostics</div><div>Historique</div></aside><div class="main">${fs.readFileSync(path.join(assets,'shell.html'),'utf8')}</div><script>${fs.readFileSync(path.join(assets,'packball.js'),'utf8')}</script><script>${fs.readFileSync(path.join(assets,'app.js'),'utf8')}</script></body></html>`;
let exportFailure=false;
const server=http.createServer((r,res)=>{if(r.url==='/download.php?file=collector'){res.statusCode=collectorReply.status;res.setHeader('Content-Type','text/plain');res.end(collectorReply.body);return}if(r.url==='/api.php'){let raw='';r.on('data',d=>raw+=d);r.on('end',()=>{let x=raw?JSON.parse(raw):null;let result=board;if(x){posted.push(x);switch(x.action){case 'packball':imported=x.bundle.matches;result={ok:true,imported:imported.length,preserved:0,late:0};break;case 'analyst':result={ok:true,imported:x.bundle.matches.length,preserved:0};break;case 'export_learning':result=exportFailure?{error:'Export temporairement indisponible'}:{schema:'stratedge.learning.v1',memory:board.learning};break;case 'export_review':result=exportFailure?{error:'Export temporairement indisponible'}:{schema:'stratedge.audit.v4',signals:board.signals,health:board.health};break;case 'export_analysis':result={schema:'stratedge.research.v4',timezone:'Europe/Paris',matches:imported};break;case 'pulsescore_auto':board.pulsescore={...(board.pulsescore||{}),auto_enabled:x.enabled};result={ok:true,pulsescore:board.pulsescore};break;case 'pulsescore_key':board.pulsescore={configured:true,attempts_31d:0,local_limit:500};result={ok:true,pulsescore:board.pulsescore};break;case 'pulsescore_remove':board.pulsescore={configured:false,attempts_31d:1,local_limit:500};result={ok:true,pulsescore:board.pulsescore};break;case 'pulsescore_test':board.pulsescore={configured:true,attempts_31d:1,local_limit:500,last_test:{ok:true,at:now,total:1,returned:1,has_next_page:false,events:[{home:'Équipe <A>',away:'Équipe B',market_count:1,markets:[{name:'Half Time Équipe <A> Total Goals',canonical:'HOME_OVER_UNDER',period:'FULL_TIME',reading:{recognized:true,team:'h',period:'HT',period_corrected:true,selections:[{line:.5,compatible_line:true,active_confirmed:true,note:'Total équipe'}]},selections:[{name:'Over',line:.5,odds:1.9}]}]}]}};result={ok:true,pulsescore:board.pulsescore};break;case 'radar_telegram':board.radar_telegram.enabled=x.enabled;board.radar_telegram.card_leagues=x.card_leagues;result={ok:true,radar_telegram:board.radar_telegram};break;case 'telegram_test':result={ok:true,state:'sent'};break;case 'settings':board.settings=x.settings;result={ok:true};break;case 'settle':board.signals[0].outcome=x.outcome;board.signals[0].result_notification={delivery:'queued',delivery_error:null};result={ok:true,result_delivery:'queued'};break;case 'history':result={ok:true,signals:[]};break;default:result={ok:true};}}res.setHeader('Content-Type','application/json');res.end(JSON.stringify(result));});return}res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html)});
(async()=>{await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const executable=process.env.LIVE90_CHROME||['/usr/bin/google-chrome','/usr/bin/chromium','/usr/bin/chromium-browser'].find(p=>fs.existsSync(p));const browser=await chromium.launch({headless:true,...(executable?{executablePath:executable}:{}),args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1600,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);await page.waitForFunction(()=>document.querySelectorAll('#match-rows tr').length===4);assert.equal(await page.locator('#feed-title').textContent(),'Collecte connectée');assert.equal(await page.locator('#live-counter').textContent(),'4');assert((await page.locator('#kpi-live').textContent()).includes('1 à la pause'));

 // The compact table is the default; labelled columns never use another row's position.
 assert.equal(await page.locator('.pack-table').count(),1);
 assert((await page.locator('#match-rows tr').first().boundingBox()).height<=36);
 board.feed.headers=['Fautes','Tirs cadrés 10 dernières minutes','Cartons jaunes','<img src=x onerror=alert(1)>'];
 board.matches[0].live_columns=[{title:'Fautes',value:{h:9,a:4}},{title:'Tirs cadrés 10 dernières minutes',value:{h:2,a:0}},{title:'Cartons jaunes',value:{h:0,a:1}},{title:'<img src=x onerror=alert(1)>',value:{h:1,a:null}}];
 await page.evaluate(()=>refresh());
 let statCells=page.locator('#match-rows tr').filter({hasText:'Équipe A'}).locator('.pack-stat');
 assert.equal(await statCells.nth(0).textContent(),'9–4');assert.equal(await statCells.nth(1).textContent(),'2–0');assert.equal(await statCells.nth(2).textContent(),'0–1');
 assert.equal(await page.locator('#table-head img').count(),0);assert.equal(await page.locator('.pack-ht').nth(1).textContent(),'—');
 board.matches[3].on_packball_page=false;await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').count(),3);await page.locator('[data-layout="analysis"]').click();assert.equal(await page.locator('#match-rows tr').count(),4);await page.locator('[data-layout="table"]').click();delete board.matches[3].on_packball_page;await page.evaluate(()=>refresh());
 // Changing Packball's column order does not shift values into a different statistic.
 board.feed.headers=['Cartons jaunes','Fautes','Tirs cadrés 10 dernières minutes','Statistique absente'];await page.evaluate(()=>refresh());
 assert.equal(await statCells.nth(0).textContent(),'0–1');assert.equal(await statCells.nth(1).textContent(),'9–4');assert.equal(await statCells.nth(3).textContent(),'—–—');
 board.matches[0].live_columns.push({title:'Fautes',value:{h:99,a:99}});await page.evaluate(()=>refresh());assert.equal(await statCells.nth(1).textContent(),'—–—');board.matches[0].live_columns.pop();await page.evaluate(()=>refresh());
 board.matches[0].live_columns[0].value.h=10;
 await page.waitForFunction(()=>[...document.querySelectorAll('#match-rows .pack-stat')].some(x=>x.textContent==='10–4'),{},{timeout:20000});
 assert.equal(await statCells.nth(1).textContent(),'10–4');
 // One-minute emphasis is per side, persists through renders, then expires.
 assert.equal(await statCells.nth(1).locator('.value-updated').count(),1);
 assert.equal(await statCells.nth(1).locator('.value-updated').textContent(),'10');
 assert.equal(await statCells.nth(1).evaluate(el=>getComputedStyle(el).fontSize),'11px');
 assert.equal(await statCells.nth(1).locator('.value-updated').evaluate(el=>getComputedStyle(el).fontWeight),'700');
 await page.evaluate(()=>{const original=Date.now;Date.now=()=>original()+59000;render();Date.now=original});
 assert.equal(await statCells.nth(1).locator('.value-updated').count(),1);
 await page.evaluate(()=>{const original=Date.now;Date.now=()=>original()+61000;expireTableEmphasis();Date.now=original});
 assert.equal(await statCells.nth(1).locator('.value-updated').count(),0);
 const savedHeaders=[...board.feed.headers];board.feed.headers.push('Tacles');board.matches[0].live_columns.push({title:'Tacles',value:{h:5,a:6}});await page.evaluate(()=>refresh());
 assert.deepEqual(await page.locator('.pack-stat-head').evaluateAll(es=>es.map(e=>e.title)),['Cartons jaunes','Fautes','Tacles','Tirs cadrés 10 dernières minutes','Statistique absente']);
 assert.equal(await statCells.nth(2).textContent(),'5–6');board.feed.headers=savedHeaders;board.matches[0].live_columns.pop();
 const savedClock={state:board.matches[0].state,minute:board.matches[0].minute};
 for(const raw of ["45' +3",'45 + 3′','45′＋3′']){board.matches[0].state='OTHER';board.matches[0].minute=null;board.matches[0].status_raw=raw;await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').filter({hasText:'Équipe A'}).locator('.pack-clock').textContent(),'45 + 3′');assert.equal(await page.locator('#match-rows tr').count(),4);}
 await page.locator('[data-scope="live"]').click();assert.equal(await page.locator('#match-rows tr').count(),4);await page.locator('[data-scope="all"]').click();
 board.matches[0].status_raw='90+6';await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').filter({hasText:'Équipe A'}).locator('.pack-clock').textContent(),'90 + 6′');
 board.matches[0].state='FT';await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').count(),3);
 board.matches[0].state='OTHER';board.matches[0].status_raw='Terminé';await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').count(),3);
 board.matches[0].status_raw='AET';await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').filter({hasText:'Équipe A'}).locator('.pack-clock').textContent(),'À vérifier');
 Object.assign(board.matches[0],savedClock);delete board.matches[0].status_raw;await page.evaluate(()=>refresh());

 await page.locator('#match-rows [data-favorite]').first().click();await page.locator('[data-scope="favorites"]').click();assert.equal(await page.locator('#match-rows tr').count(),1);await page.locator('[data-scope="all"]').click();
 await page.locator('[data-scope="live"]').click();assert.equal(await page.locator('#match-rows tr').count(),4);await page.locator('[data-scope="all"]').click();
 await page.locator('#match-rows [data-pin]').last().click();assert((await page.locator('#match-rows tr').first().textContent()).includes('Équipe G'));await page.locator('#match-rows [data-pin][aria-pressed="true"]').click();
 const rowTime=board.matches[0].received_at;board.matches[0].received_at=new Date(Date.parse(board.server_time)-120000).toISOString();await page.evaluate(()=>refresh());assert.equal(await statCells.nth(1).locator('.old').count(),2);board.matches[0].received_at=rowTime;
 delete board.feed.headers;delete board.matches[0].live_columns;await page.evaluate(()=>refresh());
 // Desktop density with a busy Saturday, and horizontally scrolling mobile table.
 const originals=structuredClone(board.matches);board.matches=Array.from({length:36},(_,i)=>({...structuredClone(originals[i%4]),packball_id:String(500+i),home:'Domicile '+(i+1),away:'Visiteur '+(i+1),league:['Bundesliga','Premier League','La Liga','Serie A'][i%4],profile:{...profile,country:['GER','ENG','ESP','ITA'][i%4]},stats:{shots:{h:12+i%6,a:7+i%4},sot:{h:4,a:2},possession:{h:62,a:38},yellow_cards:{h:0,a:2},red_cards:{h:0,a:0},fouls:{h:9,a:7}},ind10:{shots10:{h:4,a:1},sot10:{h:2,a:0}},ind5:{shots5:{h:2,a:0},sot5:{h:1,a:0}}}));await page.evaluate(()=>refresh());
 await page.locator('#table-focus').click();assert.equal(await page.locator('#attention-more').isVisible(),true);await page.setViewportSize({width:2048,height:1150});await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:path.join(output,'packball-table-desktop.png')});
 assert.equal(await page.locator('#match-rows tr').count(),36);assert((await page.locator('#match-rows tr').first().boundingBox()).height<=36);
 await page.setViewportSize({width:390,height:844});await page.locator('.pack-controls').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,'packball-table-mobile.png')});
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Le tableau doit défiler dans son conteneur, pas dans la page');
 await page.locator('.table-scroll').evaluate(e=>{e.scrollLeft=400;e.scrollTop=100});await page.evaluate(()=>refresh());assert((await page.locator('.table-scroll').evaluate(e=>e.scrollLeft))>=399);assert((await page.locator('.table-scroll').evaluate(e=>e.scrollTop))>=99);
 await page.locator('#table-focus').click();await page.setViewportSize({width:1600,height:1100});board.matches=originals;await page.evaluate(()=>refresh());
 await page.locator('[data-layout="analysis"]').click();
 assert((await page.locator('#health-issues').textContent()).includes('Fautes manquantes'));const auditDL=page.waitForEvent('download');await page.locator('#export-review').click();const audit=await auditDL;assert.equal(JSON.parse(fs.readFileSync(await audit.path(),'utf8')).schema,'stratedge.audit.v4');
 assert((await page.locator('#learning-summary').textContent()).includes('3 alertes relues'));
 assert.equal(await page.locator('#learning-reviews script').count(),0);
 const learningDL=page.waitForEvent('download');await page.locator('#export-learning').click();
 assert.equal(JSON.parse(fs.readFileSync(await (await learningDL).path(),'utf8')).schema,'stratedge.learning.v1');
 assert.equal(await page.locator('#learning-export-result a[download]').count(),1);
 assert.equal(await page.locator('#review-export-result a[download]').count(),1);
 const fallbackDL=page.waitForEvent('download');await page.locator('#learning-export-result a').click();
 assert.equal(JSON.parse(fs.readFileSync(await (await fallbackDL).path(),'utf8')).schema,'stratedge.learning.v1');
 exportFailure=true;
 for(const [button,result] of [['#export-learning','#learning-export-result'],['#export-review','#review-export-result']]){
  await page.locator(button).click();await page.waitForFunction(id=>document.querySelector(id).textContent.includes('Export temporairement indisponible'),result);
  assert.equal(await page.locator(button).isEnabled(),true);assert.equal(await page.locator(result+' a').count(),0);
 }
 exportFailure=false;
 board.learning.error={type:'Test'};await page.evaluate(()=>refresh());assert((await page.locator('#learning-summary').textContent()).includes('les alertes live continuent'));delete board.learning.error;await page.evaluate(()=>refresh());
 await page.locator('[data-market="cards"]').click();assert.equal(await page.locator('#match-rows tr').count(),4);await page.locator('[data-market="all"]').click();
 assert((await page.locator('#match-rows tr').first().textContent()).includes('Équipe A'));assert((await page.locator('#match-rows tr').first().textContent()).includes('Équipe B'));assert.equal(await page.locator('#match-rows tr').first().locator('.reading').count(),4);
 const assertTeamHalves=async()=>{const pair=page.locator('#match-rows .match-market-pair').first();const home=pair.locator('[data-side="h"]'),away=pair.locator('[data-side="a"]');assert.equal(await pair.locator('.match-side').count(),2);assert((await home.textContent()).includes('Équipe A'));assert((await away.textContent()).includes('Équipe B'));const h=await home.boundingBox(),a=await away.boundingBox();assert(Math.abs(h.y-a.y)<1,'Les équipes doivent être côte à côte');assert(h.x+h.width<=a.x+1,'Domicile à gauche, visiteurs à droite');assert(Math.abs(h.width-a.width)<2,'Deux moitiés de même largeur');};await assertTeamHalves();
 await page.screenshot({path:path.join(output,'desktop.png'),fullPage:true});
 assert((await page.locator('#match-rows tr').filter({hasText:'Équipe G'}).first().textContent()).includes('Mi-temps'));
 assert((await page.locator('#match-rows').textContent()).includes('Arbitre Test : 4,7'));
 assert((await page.locator('#match-rows').textContent()).includes('3 tirs dans la surface'));
 assert((await page.locator('#attention-cards').textContent()).includes('13 min réglementaires avant la pause'));
 assert((await page.locator('#attention-cards .goals').first().textContent()).includes('avant la pause'));
 assert((await page.locator('#attention-cards .goals').first().textContent()).includes('cote 1,85'));
 assert(!(await page.locator('#attention-cards .goals').first().textContent()).includes('cote 1,6'));
 // Market cuts: several events required must never read as one additional event.
 assert((await page.locator('#attention-cards .cards').first().textContent()).includes('Cut +2,5 · cote 1,8'));
 assert((await page.locator('#attention-cards .cards').first().textContent()).includes('3 de plus nécessaires si même décompte'));
 assert.equal(await page.locator('[data-market="corners"]').count(),0);assert.equal(await page.locator('#attention-cards .corners').count(),0);
 assert((await page.locator('#attention-cards .cards').first().textContent()).includes('Marché distinct'));
 assert.equal(posted.filter(x=>x.action==='pulsescore_test').length,0);
 const quoteTime=board.matches[0].stake_cuts.at;board.matches[0].stake_cuts.at=new Date(Date.parse(board.server_time)-61000).toISOString();await page.evaluate(()=>refresh());
 assert(!(await page.locator('#attention-cards').textContent()).includes('cote 1,8'));
 assert((await page.locator('#attention-cards').textContent()).includes('Cuts à actualiser'));
 board.matches[0].stake_cuts.at=quoteTime;await page.evaluate(()=>refresh());
 // Radar: factual cards, deduplication, filters, halftime, corrected counters and freshness.
 for(const [kind,label] of Object.entries({goals:'But à surveiller',cards:'Carton à surveiller'})){assert.equal(await page.locator('#attention-cards .confirmed.'+kind+' h3').first().textContent(),label)}
 assert.equal(await page.locator('#attention-cards .attention-card').count(),6);
 assert((await page.locator('#attention-cards').textContent()).includes('9 fautes, aucun jaune'));
 // Missing or conflicting pre-match evidence must suppress radar even for old active data.
 for(const r of board.matches)for(const d of r.advice){d.savedCoherence=d.coherence;d.coherence={status:'missing',missing:['Moyenne de fautes avant-match manquante.'],conflicts:[],lines:[]};}
 await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards .attention-card').count(),0);assert.equal(await page.locator('#match-rows tr').count(),4);assert.equal(await page.locator('#match-rows .reading.active').count(),0);assert((await page.locator('#match-rows').textContent()).includes('Moyenne de fautes avant-match manquante.'));
 for(const r of board.matches)for(const d of r.advice)d.coherence={status:'conflict',missing:[],conflicts:['Mi-temps non concordante.'],lines:[]};
 await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards .attention-card').count(),0);assert((await page.locator('#match-rows').textContent()).includes('Mi-temps non concordante.'));
 for(const r of board.matches)for(const d of r.advice){d.coherence=d.savedCoherence;delete d.savedCoherence;}
 await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards .attention-card').count(),6);
 // Excluded leagues suppress even factual cards from old active/missing readings.
 for(const r of board.matches)for(const d of r.advice)if(d.market==='cards')d.telegram_allowed=false;
 await page.evaluate(()=>refresh());
 assert.equal(await page.locator('#attention-cards .cards').count(),0);
 assert.equal(await page.locator('#count-cards').textContent(),'0');
 assert.equal(await page.locator('#match-rows .reading.excluded').count(),8);
 assert((await page.locator('#match-rows .reading.excluded').first().textContent()).includes('Aucune recommandation cartons'));
 assert.equal(await page.locator('#attention-cards .goals').count(),3);
 // New backend exclusion persists even if fresh status and quote data arrive.
 for(const r of board.matches)for(const d of r.advice)if(d.market==='cards'){delete d.telegram_allowed;d.recommendation_allowed=false;}
 await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards .cards').count(),0);
 for(const r of board.matches)for(const d of r.advice)delete d.recommendation_allowed;
 await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards .attention-card').count(),6);
 assert((await page.locator('#attention-cards').textContent()).includes('8 cadrés, 0 buts'));
 assert(!(await page.locator('#attention-cards').textContent()).includes('corners en 10 min'));
 assert(!(await page.locator('#attention-cards').textContent()).includes('Équipe G'));
 assert.equal(await page.locator('#attention-more').isVisible(),false);
 const firstCard=board.matches[0].advice.find(d=>d.team==='h'&&d.market==='cards');
 firstCard.observed=1;firstCard.status='neutral';await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards .attention-card.cards [data-match="101"]').count(),0);
 firstCard.observed=0;firstCard.status='active';await page.evaluate(()=>refresh());
 await page.locator('[data-market="cards"]').click();assert.equal(await page.locator('#attention-cards .attention-card').count(),3);
 await page.locator('#search').fill('Équipe E');assert.equal(await page.locator('#attention-cards .attention-card').count(),1);
 await page.locator('#search').fill('');await page.locator('[data-market="all"]').click();
 const sample=board.matches[0].sample_id;board.matches[0].sample_id=2;await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards [data-match="101"]').count(),0);board.matches[0].sample_id=sample;
 const originalStatus=board.matches[0].state;board.matches[0].state='FT';await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards [data-match="101"]').count(),0);
 assert.equal(await page.locator('#match-rows tr').count(),3);assert.equal(await page.locator('#match-rows [data-match="101"]').count(),0);assert.equal(await page.locator('#kpi-matches').textContent(),'3');assert.equal(await page.locator('#kpi-context').textContent(),'3 / 3');
 await page.locator('[data-view="observations"]').click();assert((await page.locator('#match-rows').textContent()).includes('Équipe A'));assert((await page.locator('#match-rows').textContent()).includes('Seuil atteint'));assert((await page.locator('#match-rows').textContent()).includes('+1,5 buts'));assert((await page.locator('#match-rows').textContent()).includes('Telegram résultat : Envoyé'));assert((await page.locator('#match-rows').textContent()).includes('première mi-temps'));assert((await page.locator('#signals-list').textContent()).includes('Seuil atteint'));
 board.observations[0].result.outcome='unknown';await page.evaluate(()=>refresh());assert((await page.locator('#match-rows').textContent()).includes('données finales manquantes'));board.observations[0].result.outcome='observed';await page.evaluate(()=>refresh());
 await page.locator('[data-view="history"]').click();assert.equal(await page.locator('#match-rows [data-signal="1"]').count(),1);
 await page.locator('[data-view="live"]').click();board.matches[0].state=originalStatus;await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows tr').count(),4);
 const cutoffMinute=board.matches[0].minute;
 for(const minute of [80,90,95]){board.matches[0].minute=minute;await page.evaluate(()=>refresh());assert.equal(await page.locator('#attention-cards [data-match="101"]').count(),0);assert((await page.locator('#match-rows tr').first().textContent()).includes('Alertes arrêtées'));}
 board.matches[0].minute=cutoffMinute;await page.evaluate(()=>refresh());
 const savedMinute=board.matches[0].minute;board.matches[0].minute=94;await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows [data-match="101"]').count(),1);board.matches[0].minute=savedMinute;await page.evaluate(()=>refresh());
 assert.equal(await page.locator('#attention-cards .attention-card').count(),6);
 await page.locator('#attention-cards').screenshot({path:path.join(output,'attention-cards.png')});
 const savedTime=board.server_time;board.server_time=new Date(Date.now()+120000).toISOString();await page.evaluate(()=>refresh());assert.equal(await page.locator('#match-rows .reading.active').count(),0);assert.equal(await page.locator('#attention-cards .attention-card').count(),0);board.server_time=savedTime;await page.evaluate(()=>refresh());
 await page.locator('[data-match="101"]').first().click();assert.equal(await page.locator('#detail-modal').evaluate(e=>e.open),true);assert((await page.locator('#detail-content').textContent()).includes('Arbitre Test'));assert((await page.locator('#detail-content').textContent()).includes('8,14 tirs/but'));assert((await page.locator('#detail-content').textContent()).includes('7 tirs, 3 cadrés'));assert.equal(await page.locator('#detail-content .reading-evidence img').count(),0);await page.locator('#detail-modal').screenshot({path:path.join(output,'shot-conversion.png')});assert((await page.locator('#detail-content').textContent()).includes('25 matchs'));board.matches[0].minute=37;board.matches[0].stats.fouls.h=12;await page.evaluate(()=>refresh());assert((await page.locator('#detail-content').textContent()).includes('37′'));assert((await page.locator('#detail-content').textContent()).includes('Fautes : 12 / 4'));assert.equal(await page.locator('#detail-modal').evaluate(e=>e.open),true);await page.locator('[data-close="detail-modal"]').click();
 await page.locator('#open-packball').click();const a=Array(72).fill('1'),b=Array(47).fill('1');for(const r of[a,b]){r[0]='France';r[2]='Ligue';r[3]='20:45';r[4]='NS';r[5]='Équipe A';r[8]='Équipe B'}a[24]=a[25]='10';a[26]=a[27]='12';a[30]=a[31]='4';const csv=(n,row)=>Array(n).fill('header').join(';')+'\n'+row.join(';');await page.locator('#packball-files').setInputFiles([{name:'PackBall 28-09-2026.csv',mimeType:'text/csv',buffer:Buffer.from(csv(72,a))},{name:'PackBall 2 28-09-2026.csv',mimeType:'text/csv',buffer:Buffer.from(csv(47,b))}]);await page.locator('#packball-save').click();await page.waitForFunction(()=>document.querySelector('#packball-preview').textContent.includes('1 profils importés'));assert.equal(imported[0].match_id,null);assert.equal(imported[0].kickoff,'2026-09-28T18:45:00.000Z');
 const dl=page.waitForEvent('download');await page.locator('#packball-export').click();const downloaded=await dl;const saved=await downloaded.path();assert.equal(JSON.parse(fs.readFileSync(saved,'utf8')).schema,'stratedge.research.v4');assert.equal(await page.locator('#packball-modal').evaluate(e=>e.open),true);assert((await page.locator('#packball-export-result').textContent()).includes('Téléchargement lancé'));await page.screenshot({path:path.join(output,'packball-export.png'),fullPage:true});await page.locator('[data-close="packball-modal"]').click();
 await page.locator('#open-analyst').click();const context={schema:'stratedge.context.v4',timezone:'Europe/Paris',generated_at:now,matches:[{...analyst,home:'Équipe A',away:'Équipe B',watch:true,kickoff:imported[0].kickoff,match_id:null}]};await page.locator('#scenario-file').setInputFiles({name:'analyste.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(context))});await page.locator('#analyst-save').click();await page.waitForFunction(()=>document.querySelector('#analyst-preview').textContent.includes('1 contextes'));await page.locator('[data-close="analyst-modal"]').click();
 // Les deux vrais exports séparés doivent atteindre l'API et le téléchargement GPT.
 await page.locator('#open-packball').click();
 await page.locator('#packball-files').setInputFiles(['b','a'].map(group=>path.join(__dirname,'fixtures',`packball-20260929-${group}.csv`)));
 await page.locator('#packball-save').click();await page.waitForFunction(()=>document.querySelector('#packball-preview').textContent.includes('16 profils importés'));
 assert.equal(imported.length,16);assert.equal(imported[0].prematch.shots_h,15);
 const realBundle=posted.filter(x=>x.action==='packball').at(-1).bundle;
 assert.equal(realBundle.headers.export_a.length,40);assert.equal(realBundle.headers.export_b.length,46);
 const realDownload=page.waitForEvent('download');await page.locator('#packball-export').click();
 const realExport=JSON.parse(fs.readFileSync(await (await realDownload).path(),'utf8'));
 assert.equal(realExport.schema,'stratedge.research.v4');assert.equal(realExport.matches.length,16);
 assert.equal(realExport.matches.find(m=>m.home==='Deportivo Pereira').prematch.sot_a,4.5);
 // Le format sans cotes atteint l’API puis l’export avec les mêmes identités.
 const noOddsTable=realBundle.matches.map(m=>[...m.packball.export_b.slice(0,9),...m.packball.export_b.slice(34)]);
 const newBHeader=[...realBundle.headers.export_b.slice(0,9),...realBundle.headers.export_b.slice(34)];
 const quoteCSV=rows=>rows.map(r=>r.map(v=>'"'+String(v).replaceAll('"','""')+'"').join(';')).join('\n');
 const newA=[realBundle.headers.export_a,...realBundle.matches.map(m=>m.packball.export_a)];
 await page.locator('#packball-files').setInputFiles([{name:'GPT-2-29-09-2026.csv',mimeType:'text/csv',buffer:Buffer.from(quoteCSV([newBHeader,...noOddsTable]))},{name:'GPT-29-09-2026.csv',mimeType:'text/csv',buffer:Buffer.from(quoteCSV(newA))}]);
 const beforeCompact=posted.filter(x=>x.action==='packball').length;
 await page.locator('#packball-save').click();await page.waitForFunction(()=>!document.querySelector('#packball-save').disabled);
 assert.equal(posted.filter(x=>x.action==='packball').length,beforeCompact+1);
 const compactBundle=posted.filter(x=>x.action==='packball').at(-1).bundle;
 assert.equal(compactBundle.headers.export_b.length,21);assert.equal(compactBundle.matches.length,16);
 assert.deepEqual(compactBundle.matches.map(m=>m.packball.teams),realBundle.matches.map(m=>m.packball.teams));
 assert(compactBundle.matches.every(m=>m.packball.odds.length===0));
 const compactDownload=page.waitForEvent('download');await page.locator('#packball-export').click();
 const compactExport=JSON.parse(fs.readFileSync(await (await compactDownload).path(),'utf8'));
 assert.equal(compactExport.matches[0].packball.layout,'packball.prematch31_12.v1');
 await page.locator('[data-close="packball-modal"]').click();
 await page.locator('[data-view="history"]').click();assert.equal(await page.locator('#live-attention').isVisible(),false);assert.equal(await page.locator('#match-rows tr').count(),1);await page.locator('#match-rows [data-signal="1"]').click();assert((await page.locator('#detail-content').textContent()).includes('Cause non démontrée'));await page.locator('[data-close="detail-modal"]').click();await page.locator('#match-rows [data-settle="1"]').click();await page.locator('#settle-form select').selectOption('won');await page.locator('#settle-form textarea').fill('Source officielle de test');await page.locator('#settle-form button[type="submit"]').click();await page.waitForFunction(()=>document.querySelector('#match-rows').textContent.includes('Gagnant'));assert((await page.locator('#match-rows').textContent()).includes('Résultat : En attente'));

 board.signals.push({id:2,market:'card_ft',team:'h',line:1.5,odds:null,created_at:now,outcome:'won',delivery:'sent',data:{home:'Équipe C',away:'Équipe D',minute:60,score:{h:0,a:0},label:'Équipe C · un carton supplémentaire',decision:{...d,market:'card_ft'},context:analyst,mode:'statistical_no_odds'}});
 board.totals={...board.totals,total:2,priced_total:1,priced_won:1,priced_lost:0,statistical_total:1,statistical_won:1,statistical_lost:0};board.history_total=2;
 await page.evaluate(()=>refresh());assert((await page.locator('#kpi-units').textContent()).includes('Radar Telegram actif'));
 const cardRow=page.locator('#match-rows tr').filter({hasText:'Équipe C'});assert((await cardRow.textContent()).includes('Sans cote'));assert((await cardRow.textContent()).includes('Événement observé'));assert(!(await cardRow.textContent()).includes('bet365'));
 await page.locator('#match-rows [data-signal="2"]').click();assert((await page.locator('#detail-content').textContent()).includes('aucun avantage de prix ni rendement calculé'));assert(!(await page.locator('#detail-content').textContent()).includes('bet365'));await page.screenshot({path:path.join(output,'cards-no-odds.png'),fullPage:true});await page.locator('[data-close="detail-modal"]').click();
 await page.locator('#match-rows [data-settle="2"]').click();assert((await page.locator('#settle-form select').textContent()).includes('Événement observé'));await page.locator('[data-close="settle-modal"]').click();
 board.signals.push({id:3,market:'goal_ft',team:'h',line:.5,odds:null,created_at:now,outcome:'lost',delivery:'sent',data:{home:'Équipe E',away:'Équipe F',minute:60,score:{h:0,a:0},label:'Équipe E · un but avant la fin',decision:{...d,market:'goal_ft'},context:analyst,mode:'statistical_no_odds'}});
 await page.evaluate(()=>refresh());
 const goalRow=page.locator('#match-rows tr').filter({hasText:'Équipe E'});assert((await goalRow.textContent()).includes('Sans cote'));assert((await goalRow.textContent()).includes('Non observé'));assert(!(await goalRow.textContent()).includes('bet365'));
 await page.locator('#match-rows [data-signal="3"]').click();assert((await page.locator('#detail-content').textContent()).includes('aucun avantage de prix ni rendement calculé'));await page.locator('[data-close="detail-modal"]').click();
 board.signals[2].data.stake_quote={odds:1.88,line:.5,market_name:'Équipe E Total Goals',source:'Stake via PulseScore',received_at:now};
 await page.evaluate(()=>refresh());assert((await page.locator('#match-rows').textContent()).includes('1,88*'));
 await page.locator('#match-rows [data-signal="3"]').click();assert((await page.locator('#detail-content').textContent()).includes('Cote Stake indicative'));assert((await page.locator('#detail-content').textContent()).includes('Aucun rendement calculé'));await page.screenshot({path:path.join(output,'stake-reference-price.png'),fullPage:true});await page.locator('[data-close="detail-modal"]').click();
 await page.locator('#settings-open').click();
 assert.equal(await page.locator('#min-odds').count(),0);
 assert.equal(await page.locator('#telegram-enabled').isDisabled(),false);assert.equal(await page.locator('#telegram-enabled').isChecked(),true);assert.equal(await page.locator('#pulse-auto').isDisabled(),true);assert.equal(posted.filter(x=>x.action==='pulsescore_test').length,0);
 await page.locator('#pulse-key').fill('test-only-private-key');await page.locator('#pulse-save').click();await page.waitForFunction(()=>document.querySelector('#pulse-message').textContent.includes('Clé enregistrée'));
 assert.equal(await page.locator('#pulse-key').inputValue(),'');assert.equal(posted.filter(x=>x.action==='pulsescore_test').length,0);
 await page.locator('[data-close="settings-modal"]').click();await page.locator('[data-view="live"]').click();await page.locator('#cuts-refresh').click();await page.waitForFunction(()=>document.querySelector('#pulse-message').textContent.includes('Connexion réussie'));
 await page.locator('#settings-open').click();assert.equal(posted.filter(x=>x.action==='pulsescore_test').length,1);assert((await page.locator('#pulse-report').textContent()).includes('Équipe <A>'));assert((await page.locator('#pulse-status').textContent()).includes('1 / 500'));
 await page.locator('#pulse-report summary').click();assert((await page.locator('#pulse-report').textContent()).includes('Période corrigée'));assert((await page.locator('#pulse-report').textContent()).includes('buts avant la pause'));await page.screenshot({path:path.join(output,'pulsescore.png'),fullPage:true});
 await page.evaluate(()=>refresh());assert.equal(posted.filter(x=>x.action==='pulsescore_test').length,1);
 await page.locator('#pulse-remove').click();await page.waitForFunction(()=>document.querySelector('#pulse-message').textContent.includes('Clé retirée'));assert.equal(await page.locator('#pulse-test').isDisabled(),true);


 await page.waitForFunction(()=>document.querySelector('#collector-version').textContent.includes('HTTP 503'));
 collectorReply={status:200,body:'<!doctype html><h1>Connexion</h1>'};await page.locator('#collector-copy').click();await page.waitForFunction(()=>document.querySelector('#collector-result').textContent.includes('Reconnecte-toi'));assert.equal(await page.locator('#collector-code-label').isVisible(),false);
 collectorReply={status:200,body:collectorCode};await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async()=>{throw Error('Blocked for test')}}}));
 await page.locator('#collector-copy').click();await page.waitForFunction(()=>document.querySelector('#collector-result').textContent.includes('Ctrl+C'));assert.equal(await page.locator('#collector-code').inputValue(),collectorCode);assert.equal(await page.locator('#collector-code').evaluate(e=>e.selectionEnd-e.selectionStart),collectorCode.length);assert((await page.locator('#collector-version').textContent()).includes('Les versions diffèrent'));assert((await page.locator('#collector-version').textContent()).includes('Disponible : '+collectorVersion));
 const collectorDL=page.waitForEvent('download');await page.locator('#collector-download').click();const collectorFile=await collectorDL;assert.equal(collectorFile.suggestedFilename(),'live90-packball.user.js');assert.equal(fs.readFileSync(await collectorFile.path(),'utf8'),collectorCode);assert.equal(await page.locator('#settings-modal').evaluate(e=>e.open),true);
 await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async code=>{window.copiedCollector=code}}}));await page.locator('#collector-copy').click();await page.waitForFunction(()=>document.querySelector('#collector-result').textContent.includes('copié'));assert.equal(await page.evaluate(()=>window.copiedCollector),collectorCode);
 board.feed.collector_version=collectorVersion;await page.evaluate(()=>refresh());assert((await page.locator('#collector-version').textContent()).includes('bien envoyé un relevé'));await page.screenshot({path:path.join(output,'collector-update.png'),fullPage:true});
 // Missing selection must not turn into an empty save; restore and explicit disable are visible.
 const savedRadar=structuredClone(board.radar_telegram);
 board.radar_telegram.card_leagues=[];
 board.radar_telegram.default_card_leagues=['USA · MLS','Angleterre · Premier League','Espagne · La Liga','Italie · Serie A','Allemagne · Bundesliga','France · Ligue 1','Arabie saoudite · Saudi Pro League','UEFA · Champions League','UEFA · Europa League','UEFA · Conference League','Australie · A-League Men','Allemagne · 2. Bundesliga'];
 await page.locator('[data-close="settings-modal"]').click();await page.evaluate(()=>refresh());await page.locator('#settings-open').click();
 assert((await page.locator('#card-selection-status').textContent()).includes('Aucune compétition'));
 const beforeSettings=posted.filter(x=>x.action==='radar_telegram').length;
 await page.locator('#settings-form button[type=submit]').click();assert.equal(posted.filter(x=>x.action==='radar_telegram').length,beforeSettings);
 assert((await page.locator('#settings-result').textContent()).includes('Confirme'));
 await page.locator('#card-leagues-restore').click();assert.equal((await page.locator('#card-leagues').inputValue()).split('\n').length,12);
 assert((await page.locator('#card-leagues').inputValue()).includes('UEFA · Conference League'));
 assert((await page.locator('#card-leagues').inputValue()).includes('Australie · A-League Men'));
 assert((await page.locator('#card-leagues').inputValue()).includes('Allemagne · 2. Bundesliga'));
 assert((await page.locator('#settings-result').textContent()).includes('12 compétitions'));
 assert.equal(await page.locator('#disable-cards-label').isVisible(),false);
 await page.locator('#settings-form button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#settings-result').textContent.includes('enregistrés'));
 assert.equal(posted.filter(x=>x.action==='radar_telegram').length,beforeSettings+1);
 assert.equal(board.radar_telegram.card_leagues.length,12);
 await page.locator('[data-close="settings-modal"]').click();await page.evaluate(()=>refresh());await page.locator('#settings-open').click();
 assert.equal((await page.locator('#card-leagues').inputValue()).split('\n').length,12);
 delete board.radar_telegram.card_leagues;
 await page.locator('[data-close="settings-modal"]').click();await page.evaluate(()=>refresh());await page.locator('#settings-open').click();
 await page.locator('#settings-form button[type=submit]').click();assert((await page.locator('#settings-result').textContent()).includes('Sélection non reçue'));
 assert.equal(posted.filter(x=>x.action==='radar_telegram').length,beforeSettings+1);
 board.learning={revision:2,generated_at:now,review_count:3,match_count:2,settled_count:2,prompt_addendum:'Comparer les faits, pas inventer la cause.',recent:[{radar_id:12,home:'Équipe A',away:'Équipe B',team:'h',market:'goals',label:'Seuil non atteint',revision:1,facts:['Compteur final : 0.'],flags:[{detail:'Qualité des occasions inconnue <script>throw 1</script>'}],hypotheses:['Comparer les deux résultats.'],conclusion:'Cause non identifiée.'}]};
board.radar_telegram={...savedRadar,card_leagues:['Compétition de démonstration']};
 await page.locator('[data-close="settings-modal"]').click();await page.evaluate(()=>refresh());await page.locator('#settings-open').click();
 assert.equal(await page.locator('#telegram-test').isDisabled(),false);assert.equal(posted.filter(x=>x.action==='telegram_test').length,0);await page.locator('#card-leagues').fill('Compétition de démonstration');await page.locator('#telegram-enabled').uncheck();await page.locator('#settings-form button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#kpi-units').textContent.includes('Radar Telegram arrêté'));assert.equal(posted.filter(x=>x.action==='radar_telegram').length,2);await page.locator('[data-close="settings-modal"]').click();
 await page.locator('[data-view="observations"]').click();assert((await page.locator('#match-rows').textContent()).includes('4 tirs'));
 await page.locator('[data-view="live"]').click();await page.setViewportSize({width:390,height:844});await assertTeamHalves();await page.screenshot({path:path.join(output,'mobile.png'),fullPage:true});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Débordement horizontal du document');assert.deepEqual(errors,[]);assert.deepEqual(posted.filter(x=>x.action==='radar_telegram').at(-1).card_leagues,['Compétition de démonstration']);assert(posted.some(x=>x.action==='analyst'));assert(posted.some(x=>x.action==='settle'));console.log('V5 browser: imports, export, four readings, details, history, settlement, radar Telegram toggle, mobile and no JS errors passed.');await require('./test_collector.cjs').browserTest(browser);await browser.close();server.close();})().catch(e=>{console.error(e);server.close();process.exit(1)});


