'use strict';
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),cp=require('node:child_process');
const {chromium}=require(path.join(process.env.ADMIN_UI_MODULES||process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES,'playwright'));
const root=path.resolve(__dirname,'../..'),output=process.env.ADMIN_UI_OUTPUT||'/tmp/admin-ui';fs.mkdirSync(output,{recursive:true});
const reported=['prono-commu-admin','montante-tennis','montante-foot','ht-tracker','edit-bet-image','historique','broadcast','twitter-post'];
const views=['index','empty','membres','creer-card','live90',...reported];
for(const view of reported){
 const source=fs.readFileSync(path.join(root,'public_html/admin',view+'.php'),'utf8');
 assert(source.indexOf("'/sidebar.php'")>source.indexOf('<body>'),view+' must include the navigation inside body');
}
let writes=0;
const server=http.createServer((req,res)=>{
 if(req.method!=='GET'){writes++;res.writeHead(405);res.end('Read-only UI fixture');return;}
 const url=new URL(req.url,'http://localhost');const view=url.pathname.slice(1).replace('.html','')||'index';
 if(views.includes(view)){
  const r=cp.spawnSync(process.env.PHP_BIN||'php',[path.join(__dirname,'render-fixture.php'),view,url.searchParams.has('ordinary')?'ordinary':'',url.searchParams.toString()],{encoding:'utf8'});
  if(r.status!==0||/Fatal error|Warning:|Deprecated:/.test(r.stdout+r.stderr)){res.writeHead(500);res.end(r.stdout+r.stderr);return}
  res.setHeader('Content-Type','text/html; charset=utf-8');res.end(r.stdout);return;
 }
 const assets={'/assets/css/calendar-strateedge.css':'text/css','/assets/js/calendar-strateedge.js':'application/javascript'};
 if(assets[url.pathname]){res.setHeader('Content-Type',assets[url.pathname]);res.end(fs.readFileSync(path.join(root,'public_html',url.pathname)));return;}
 if(url.pathname==='/fixture-bet.svg'){res.setHeader('Content-Type','image/svg+xml');res.end('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="320"><rect width="600" height="320" fill="#192436"/><text x="40" y="170" fill="#64d7ed" font-size="32">RENCONTRE DE DÉMONSTRATION</text></svg>');return;}
 res.writeHead(404);res.end('Fixture resource absent');
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;
 const executable=['/usr/bin/google-chrome','/usr/bin/chromium','/usr/bin/chromium-browser'].find(p=>fs.existsSync(p));
 const browser=await chromium.launch({headless:true,...(executable?{executablePath:executable}:{}),args:['--no-sandbox']});
 try{
 const page=await browser.newPage({viewport:{width:1600,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',route=>route.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.join(process.env.ADMIN_UI_MODULES||process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES,'chart.js/dist/chart.umd.js'))}));
 await page.addInitScript(()=>localStorage.setItem('stratedge_ht_bets',JSON.stringify([{id:1,date:'2026-10-01',league:'Démonstration',match:'Équipe A — Équipe B',time:'21:00',market:'o05ht',odds:1.8,stars:4,stake:10,ev:5,notes:'Donnée fictive',scoreMT:'1-0',scoreFT:'2-1',status:'win'}])));
 async function checkReported(view,width){
  await page.setViewportSize({width,height:1000});
  const response=await page.goto(origin+'/'+view+'.html');assert.equal(response.status(),200,view+' PHP render');await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(500);
  assert.equal(await page.locator('body.se-admin').count(),1,view+' shell initialization');
  assert.equal(await page.locator('.se-topbar').count(),1);assert.equal(await page.locator('#sidebar').count(),1);
  const layout=await page.evaluate(()=>{
   const heading=document.querySelector('.main h1').getBoundingClientRect(),bar=document.querySelector(innerWidth<=768?'.mobile-topbar':'.se-topbar').getBoundingClientRect();
   const clipped=[...document.querySelectorAll('.main>.card,.main>.two-cols,.bet-card,.adm-step-card,.main .form-grid input:not([type=hidden]),.main .form-grid textarea,.main .form-grid select,.main .kpi')].filter(e=>e.getClientRects().length&&!e.closest('.table-wrap table')).filter(e=>{const r=e.getBoundingClientRect();return r.left< -1||r.right>innerWidth+1}).map(e=>e.className||e.tagName);
   const tiny=[...document.querySelectorAll('.main label,.main .bet-titre,.main .tab,.main .kpi-label,.main .sc-lbl')].filter(e=>e.getClientRects().length&&parseFloat(getComputedStyle(e).fontSize)<12.9).map(e=>e.className);
   return {heading:heading.top,bar:bar.bottom,clipped,tiny};
  });
  assert(layout.heading>=layout.bar+12,view+' title hidden by navigation '+JSON.stringify(layout));assert.deepEqual(layout.clipped,[],view+' clipped content at '+width);assert.deepEqual(layout.tiny,[],view+' tiny text');
  await page.screenshot({path:path.join(output,view+'-'+width+'.png'),fullPage:true});
  await page.keyboard.press('Control+k');assert.equal(await page.locator('#se-command').evaluate(e=>e.open),true,view+' navigation search');await page.keyboard.press('Escape');
 }
 await page.goto(origin+'/index.html');await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(700);
 assert.equal(await page.locator('.se-revenue-value').innerText(),'14 854,50€');
 assert.equal(await page.locator('.se-workspace').count(),2);assert.equal(await page.locator('.se-topbar').isVisible(),true);
 assert.equal(await page.locator('#sidebar a.active').getAttribute('aria-current'),'page');
 await page.screenshot({path:path.join(output,'desktop.png'),fullPage:true});
 await page.keyboard.press('Control+k');assert.equal(await page.locator('#se-command').evaluate(e=>e.open),true);
 await page.locator('#se-command-input').fill('live');assert.equal(await page.locator('#se-command-results a').count(),1);
 assert.equal(await page.locator('#se-command-results a').getAttribute('href'),'/panel-x9k3m/slate/live90/');
 await page.keyboard.press('Escape');assert.equal(await page.locator('#se-command').evaluate(e=>e.open),false);
 await page.locator('[data-group="betting"]>button').click();assert.equal(await page.locator('[data-group="betting"]>button').getAttribute('aria-expanded'),'true');
 await page.locator('.se-repartition summary').click();assert.equal(await page.locator('.se-split').isVisible(),true);
 for(const view of ['membres','creer-card','live90','empty']){
  await page.goto(origin+'/'+view+'.html');await page.waitForTimeout(350);
  assert.equal(await page.locator('.se-topbar').count(),1);assert.equal(await page.locator('#sidebar').count(),1);
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),view+' desktop overflow');
  await page.screenshot({path:path.join(output,view+'-desktop.png'),fullPage:true});
 }
 for(const width of [1440,390,320])for(const view of reported)await checkReported(view,width);
 await page.setViewportSize({width:1440,height:1000});
 for(const view of ['montante-tennis','montante-foot']){
  assert.equal((await page.goto(origin+'/'+view+'.html?edit_step=1')).status(),200);assert.equal(await page.locator('form input[name=action][value=edit_step]').count(),1);
  await page.locator('.strateedge-date-wrap').first().click();assert.equal(await page.locator('.cal-popover.is-open').count(),1);
 }
 await page.goto(origin+'/edit-bet-image.html');await page.locator('#searchInput').fill('démonstration 2');assert.equal(await page.locator('.bet-card:visible').count(),1);
 await page.locator('.bet-card:visible .btn-edit').first().click();assert.equal(await page.locator('#modalOverlay').isVisible(),true);await page.locator('#modalOverlay .btn-cancel').click();assert.equal(await page.locator('#modalOverlay').isVisible(),false);
 await page.goto(origin+'/historique.html');await page.locator('.bet-card [onclick^="openLightbox"]').first().click();assert.equal(await page.locator('#lightbox').isVisible(),true);await page.keyboard.press('Escape');assert.equal(await page.locator('#lightbox').isVisible(),false);
 await page.locator('.tabs a[href="?onglet=tennis"]').click();assert.equal(await page.locator('.bet-card .cat-tennis').count(),3);await page.locator('.dossier-header').click();assert.equal(await page.locator('.bet-card').count(),0);
 await page.goto(origin+'/broadcast.html');await page.locator('#inputTitre').fill('Recette locale');await page.locator('#inputMsg').fill('Aucun message envoyé.');assert.equal(await page.locator('#prevTitle').innerText(),'Recette locale');await page.locator('.btn-send').click();assert.equal(await page.locator('#confirmOverlay').isVisible(),true);await page.locator('.btn-confirm-cancel').click();assert.equal(await page.locator('#confirmOverlay').isVisible(),false);
 assert.equal(writes,0,'No form submissions during UI checks');
 await page.goto(origin+'/index.html?ordinary');assert.equal(await page.locator('.se-workspace').count(),0);
 await page.keyboard.press('Control+k');await page.locator('#se-command-input').fill('Live');assert.equal(await page.locator('#se-command-results a').count(),0);await page.keyboard.press('Escape');
 await page.setViewportSize({width:390,height:844});await page.goto(origin+'/index.html');await page.waitForTimeout(700);
 assert.equal(await page.locator('#sidebar').evaluate(e=>e.inert),true);
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile overflow');
 await page.screenshot({path:path.join(output,'mobile.png'),fullPage:true});
 await page.locator('#hamburger').click();assert.equal(await page.locator('#sidebar').evaluate(e=>e.inert),false);
 assert.equal(await page.locator('#hamburger').getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.locator('#hamburger').getAttribute('aria-expanded'),'false');
 await page.locator('.mobile-topbar [data-se-search]').click();assert.equal(await page.locator('#se-command').evaluate(e=>e.open),true);await page.keyboard.press('Escape');
 for(const view of ['membres','creer-card','live90']){await page.goto(origin+'/'+view+'.html');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),view+' mobile overflow');await page.screenshot({path:path.join(output,view+'-mobile.png'),fullPage:true});}
 await page.setViewportSize({width:320,height:700});await page.goto(origin+'/index.html');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'320px overflow');
 await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.locator('.se-page-intro').evaluate(e=>getComputedStyle(e).animationName),'none');
 assert.deepEqual(errors,[]);console.log('ADMIN_UI_OK desktop/mobile, 8 legacy pages at 1440/390/320px, navigation, recherche, rôles, états vides, formulaires, Live, calendriers, images, historique, aperçu broadcast sans envoi, mouvement réduit');
 if(process.env.ADMIN_CAPTURE_LOG==='1')for(const name of ['desktop.png','mobile.png','membres-desktop.png','creer-card-desktop.png','live90-desktop.png'])console.log('ADMIN_CAPTURE '+name+' '+fs.readFileSync(path.join(output,name)).toString('base64'));
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1});
