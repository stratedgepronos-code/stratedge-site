// No real sites or Telegram: exercise the userscript on two browser origins with a local GM store.
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const req=process.env.LIVE90_UI_MODULES?require('module').createRequire(path.join(process.env.LIVE90_UI_MODULES,'package.json')):require;
const {chromium}=req('playwright'),code=fs.readFileSync(path.join(__dirname,'../live90-discipline.user.js'),'utf8');
const root='https://stratedgepronos.fr/panel-x9k3m/slate/live90/',med='https://www.matchendirect.fr/live-score/test.html?p=commentaires';
(async()=>{
const browser=await chromium.launch({executablePath:process.env.LIVE90_CHROME||['/usr/bin/google-chrome','/usr/bin/chromium','/usr/bin/chromium-browser'].find(p=>fs.existsSync(p)),args:['--no-sandbox']});
try{
 const ctx=await browser.newContext(),clock=Date.now();
 await ctx.route('**/*',route=>route.fulfill({contentType:'text/html; charset=utf-8',body:route.request().url().startsWith(root)?'<details open id="discipline-private"><span id="discipline-state"></span><div id="discipline-controls"></div><output id="discipline-result"></output></details>':"<table><tbody><tr><td>56′</td><td>L’arbitre rappelle Yoni à l’ordre.</td></tr></tbody></table>"}));
 await ctx.addInitScript(t=>{
  window.tick=t;Date.now=()=>window.tick;window.db={};window.timers={};window.sends=0;window.confirm=()=>true;
  window.GM_getValue=(k,d)=>structuredClone(window.db[k]??d);
  window.GM_setValue=(k,v)=>{window.db[k]=structuredClone(v)};
  window.GM_deleteValue=k=>{delete window.db[k]};window.GM_listValues=()=>Object.keys(window.db);
  window.GM_registerMenuCommand=()=>{};window.GM_xmlhttpRequest=x=>{window.sends++;x.onload({status:200,responseText:'{"ok":true,"result":{}}'})};
  window.setInterval=(fn,ms)=>{window.timers[ms]=fn;return ms};
 },clock);
 const admin=await ctx.newPage(),feed=await ctx.newPage();
 await admin.goto(root);await feed.goto(med);
 const pushRow=async(min,sec,tot,active)=>{await admin.evaluate(({min,sec,tot,active,clock})=>{
   window.tick=clock+sec*1000;const at=new Date(window.tick).toISOString();
   const r={id:'1',home:'Nice',away:'Paris',kickoff:'2026-11-24T19:00:00Z',sample:min,minute:min,at,received:at,score:{h:0,a:0},red:{h:0,a:0},yellow:{h:0,a:0},fouls:{h:tot,a:1},sides:[{side:'h',confirmed:active,allowSend:true,why:'trois fautes'}]};
   document.querySelector('details').dataset.snapshot=JSON.stringify({server:at,rows:[r]});window.timers[3000]?.();
 },{min,sec,tot,active,clock})};
 await pushRow(57,0,3,false);await admin.addScriptTag({content:code});
 await admin.getByLabel('Lien du commentaire').fill(med);await admin.getByLabel('Joueurs domicile').fill('Yoni');
 await admin.getByLabel('Lecture automatique').check();await admin.getByRole('button',{name:'Associer le match'}).click();
 const copy=async(a,b)=>{const state=await a.evaluate(()=>({db:window.db,tick:window.tick}));await b.evaluate(x=>{window.db=structuredClone(x.db);window.tick=x.tick},state)};
 await copy(admin,feed);await feed.addScriptTag({content:code});
 const add=async(m,txt)=>feed.evaluate(({m,txt})=>{const tr=document.querySelector('tbody').insertRow();tr.insertCell().textContent=m+'′';tr.insertCell().textContent=txt;window.timers[3500]()},{m,txt});
 const records=()=>feed.evaluate(()=>Object.entries(window.db).find(([k])=>k.startsWith('dc1:events:'))?.[1]||[]);
 assert.equal((await records()).length,0,'initial backlog');
 await copy(feed,admin);await pushRow(57,30,3,false);await copy(admin,feed);await feed.evaluate(()=>window.timers[3500]());await copy(feed,admin);await pushRow(58,55,4,false);await copy(admin,feed);await add(58,'L’arbitre rappelle Yoni à l’ordre.');
 assert.equal((await records()).length,1,await feed.evaluate(()=>JSON.stringify({cells:[...document.querySelectorAll('tr')].map(x=>x.innerText),flags:Object.entries(window.db).filter(([k])=>k.includes('status:')||k.includes('bindings'))})));await copy(feed,admin);
 // Some browsers pause for >40 s. Keep the source scanning; entering after a gap must reset.
 await pushRow(58,76,4,false);await copy(admin,feed);await feed.evaluate(()=>window.timers[3500]());await copy(feed,admin);
 await pushRow(59,95,4,false);await copy(admin,feed);await add(59,'Yoni commet une faute sur un attaquant.');
 assert.equal((await records()).length,2);await copy(feed,admin);
 await pushRow(59,98,5,true);
 assert.match(await admin.locator('output').innerText(),/Yoni : rappel verbal à 58′, nouvelle faute à 59′/);
 assert.equal(await admin.evaluate(()=>window.sends),0);
 await admin.evaluate(()=>{window.db['dc1:tg']={chat:'123456',token:'test'};window.timers[3000]()});
 await admin.waitForFunction(()=>window.sends===1);await admin.evaluate(()=>window.timers[3000]());
 await copy(admin,feed);await add(59,'Carton jaune pour Yoni');await copy(feed,admin);await admin.evaluate(()=>window.timers[3000]());
 assert.doesNotMatch(await admin.locator('output').innerText(),/nouvelle faute à/);assert.equal(await admin.evaluate(()=>window.sends),1);
 // Same admin, international mode: mocked official API shapes, no remote requests.
 await admin.getByRole('button',{name:'Arrêter ce suivi'}).click();
 await admin.evaluate(()=>{
  window.db['dc1:soccer']='fixture-token-test-only';delete window.db['dc1:tg'];window.mockComments=[{order:1,minute:55,comment:'Foul by Joe Smith.'}];
  window.GM_xmlhttpRequest=req=>{
   const u=new URL(req.url);if(u.host!=='api.soccersapi.com'||req.headers.Authorization!=='Bearer fixture-token-test-only'||u.searchParams.has('api_key'))throw Error('Unexpected transport');
   const op=u.searchParams.get('t');let data;
   if(op==='live')data=[{id:55,time:{timestamp:Date.parse('2026-11-24T19:00:00Z')/1000},teams:{home:{name:'Nice'},away:{name:'Paris'}}}];
   else if(op==='match_lineups')data={home:{confirmed_formation:1,squad:[{player:{firstname:'Joe',lastname:'Smith'}}]},away:{confirmed_formation:1,squad:[{player:{firstname:'Bob',lastname:'Jones'}}]}};
   else if(op==='match_comments'&&u.searchParams.get('id')==='55')data=window.mockComments;
   else throw Error('Unexpected endpoint');
   req.onload({status:200,responseText:JSON.stringify({data,meta:{pages:1,requests_left:70}})});
  }
 });
 await pushRow(60,150,6,false);
 await admin.getByRole('button',{name:'Suivre avec SoccersAPI'}).click();
 await admin.waitForFunction(()=>window.db['dc1:bindings'].some(c=>c.provider==='55'));
 const poll=async(count)=>{await admin.evaluate(()=>window.timers[6000]());await admin.waitForFunction(count=>Object.values(window.db).some(v=>v?.ok&&v.label?.includes(count+' lignes')),count);await admin.evaluate(()=>window.timers[3000]())};
 await poll(1);
 await pushRow(61,195,7,false);await admin.evaluate(()=>window.mockComments.push({order:2,minute:61,comment:'Joe Smith is given a final warning.'}));await poll(2);
 await pushRow(62,240,7,false);await admin.evaluate(()=>window.mockComments.push({order:3,minute:62,comment:'Fouled by Joe Smith.'}));await poll(3);
 await pushRow(62,244,8,true);
 assert.match(await admin.locator('output').innerText(),/Joe Smith : rappel verbal à 61′, nouvelle faute à 62′/);
 assert.equal(await admin.evaluate(()=>window.db['dc1:quota'].used),5);
 console.log('Navigateur : deux onglets, historique ignoré, warning → faute → radar, Telegram simulé unique, annulation OK.');
}finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
