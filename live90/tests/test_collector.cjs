/* Données de la capture du 27/09, DOM synthétique : aucune collecte ni alerte réelle. */
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(path.resolve(__dirname,'../live90-packball.user.js'),'utf8');
const helpers=source.slice(source.indexOf('const number='),source.indexOf('const badge='));
const heads=['Buts Temps plein','Total des tirs Temps plein','Tirs cadrés Temps plein','Possession Temps plein','Cartons jaunes Temps plein','Cartons rouges Temps plein','Cartons jaunes-rouges Temps plein','Fautes Temps plein','Total des tirs 10 dernières minutes','Tirs cadrés 10 dernières minutes','Total des tirs 5 dernières minutes','Tirs cadrés 5 dernières minutes'];
const texts=['2-2','7-5','4-3','66-34','0-0','0-0','-','8-9','2-1','2-1','0-1','0-1'];
const homeTitle="Ligne suivante Plus - Buts marqués par l'équipe à domicile";
const awayTitle='Ligne suivante Plus - Buts marqués par les visiteurs';
const expectedStats={goals:{h:2,a:2},shots:{h:7,a:5},sot:{h:4,a:3},possession:{h:66,a:34},yellow_cards:{h:0,a:0},red_cards:{h:0,a:0},second_yellow:null,fouls:{h:8,a:9}};
const expected5={shots5:{h:0,a:1},sot5:{h:0,a:1}},expected10={shots10:{h:2,a:1},sot10:{h:2,a:1}};
function cell(text){return {textContent:text,querySelectorAll:()=>[]};}
function unit(){
 const c=vm.createContext({heads,cells:texts.map(cell),score:{h:2,a:2}});vm.runInContext(helpers,c);
 const read=code=>JSON.parse(JSON.stringify(vm.runInContext(code,c)));
 let r=read('readStats(cells,heads,score)');assert.deepEqual(r.stats,expectedStats);assert.deepEqual(r.ind5,expected5);assert.deepEqual(r.ind10,expected10);assert.deepEqual(r.quality_errors,[]);
 assert.equal(r.raw_cells[8].target,'ind10.shots10');assert.equal(r.raw_cells[10].target,'ind5.shots5');
 // Un ordre différent reste valide tant que cellules et en-têtes correspondent.
 r=read('readStats([...cells].reverse(),[...heads].reverse(),score)');assert.deepEqual(r.stats,expectedStats);assert.deepEqual(r.ind10,expected10);
 r=read('readStats(cells.slice(1),heads,score)');assert.equal(r.quality_errors.length,1);assert.deepEqual(r.stats,{});assert.equal(r.raw_cells.length,11);
 r=read('readStats(cells,heads,{h:3,a:2})');assert.match(r.quality_errors[0],/Score/);
 r=read('readStats(cells,heads.map((h,i)=>i===10?heads[8]:h),score)');assert.equal(r.ind10.shots10,null);assert.match(r.quality_errors[0],/ambigu/);
 r=read('readStats(cells,heads.map((h,i)=>i===0?"":h),score)');assert.match(r.quality_errors[0],/Libellé/);
 c.special=cell('66 % – 34 %');assert.deepEqual(read('pair(special)'),{h:66,a:34});
 c.special=cell('1,5 | 2,5');assert.deepEqual(read('pair(special)'),{h:1.5,a:2.5});
 for(const text of ['','-','?','0-']){c.special=cell(text);assert.equal(read('pair(special)'),null);}
 c.title=homeTitle;let q=read('quoteMeta(title,"inp-41")');assert.equal(q.team,'h');assert.equal(q.market,'team_goals');assert.equal(q.period,'FT');assert.equal(q.side,'over');
 c.title=awayTitle;q=read('quoteMeta(title,"inp-42")');assert.equal(q.team,'a');assert.equal(q.period,'FT');
 console.log('Collecteur : correspondance des 14 colonnes, périodes, paires, valeurs absentes et colonnes désalignées OK.');
}
async function browserTest(browser){
 const page=await browser.newPage();
 await page.route('http://localhost/collector-fixture',route=>route.fulfill({contentType:'text/html',body:'<!doctype html>'}));
 await page.goto('http://localhost/collector-fixture');
 // Alternance titre direct / infobulle déplacée / titre imbriqué, spans sans classes.
 const header=heads.map((t,i)=>`<div class="col live" ${i%3===0?`title="${t}"`:i%3===1?`data-original-title="${t}"`:''}>${i%3===2?`<span title="${t}"></span>`:''}</div>`).join('');
 const stats=texts.map(t=>`<div class="col live"><div><span>${t}</span></div></div>`).join('');
 const quotes=`<div class="inplay inp-41"><span class="label">2.5</span><span class="odds-inplay">1.9</span></div><div class="inplay inp-42"><span class="label">2.5</span><span class="odds-inplay">3.75</span></div>`;
 await page.setContent(`<section class="fixtures"><div class="header"><div class="col inp-41" title="${homeTitle}"></div><div class="col inp-42"><span title="${awayTitle}"></span></div>${header}</div><div class="row fix-999001"><div class="col time"><time datetime="1790544600"></time></div><div class="col blin">61</div><span class="team-home">Fortaleza</span><span class="team-away">Athletic Club</span><span class="title-league" title="Serie B"></span><span class="result-f">2-2</span>${quotes}${stats}</div></section>`);
 await page.addScriptTag({content:'const CLIENT_ID="synthetic-client";'+helpers});
 const read=()=>page.evaluate(()=>collect());
 let x=await read(),r=x.rows[0];assert.equal(x.collector_version,'4.0.2');assert.deepEqual(r.stats,expectedStats);assert.deepEqual(r.ind5,expected5);assert.deepEqual(r.ind10,expected10);assert.deepEqual(r.quality_errors,[]);assert.equal(r.minute,61);
 assert.deepEqual(r.quotes.map(q=>[q.team,q.market,q.period,q.line,q.odds]),[['h','team_goals','FT',2.5,1.9],['a','team_goals','FT',2.5,3.75]]);
 assert.equal(x.column_map.stats[8].key,'shots10');assert.equal(x.column_map.odds[1].team,'a');
 await page.locator('.row .col.live').nth(1).evaluate(el=>el.remove());
 r=(await read()).rows[0];assert.deepEqual(r.stats,{});assert.match(r.quality_errors[0],/désalignées/);
 await page.locator('.header .col.live').evaluateAll(els=>els.forEach(el=>el.remove()));
 await assert.rejects(read(),/Aucune colonne live/);
 await page.close();console.log('Collecteur navigateur : tableau synthétique de la capture, deux cotes et douze statistiques correctement associés.');
}
if(require.main===module)unit();
module.exports={browserTest};
