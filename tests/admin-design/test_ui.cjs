'use strict';
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),cp=require('node:child_process');
const {chromium}=require(path.join(process.env.ADMIN_UI_MODULES||process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES,'playwright'));
const root=path.resolve(__dirname,'../..'),output=process.env.ADMIN_UI_OUTPUT||'/tmp/admin-ui';fs.mkdirSync(output,{recursive:true});
const views=['index','empty','membres','creer-card','live90'];
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');const view=url.pathname.slice(1).replace('.html','')||'index';
 if(views.includes(view)){
  const r=cp.spawnSync(process.env.PHP_BIN||'php',[path.join(__dirname,'render-fixture.php'),view,url.searchParams.has('ordinary')?'ordinary':''],{encoding:'utf8'});
  if(r.status!==0||/Fatal error|Warning:|Deprecated:/.test(r.stdout+r.stderr)){res.writeHead(500);res.end(r.stdout+r.stderr);return}
  res.setHeader('Content-Type','text/html; charset=utf-8');res.end(r.stdout);return;
 }
 res.writeHead(404);res.end('Fixture resource absent');
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;
 const executable=['/usr/bin/google-chrome','/usr/bin/chromium','/usr/bin/chromium-browser'].find(p=>fs.existsSync(p));
 const browser=await chromium.launch({headless:true,...(executable?{executablePath:executable}:{}),args:['--no-sandbox']});
 try{
 const page=await browser.newPage({viewport:{width:1600,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
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
 assert.deepEqual(errors,[]);console.log('ADMIN_UI_OK desktop/mobile, navigation, recherche, rôles, états vides, formulaires, Live, mouvement réduit');
 if(process.env.ADMIN_CAPTURE_LOG==='1')for(const name of ['desktop.png','mobile.png','membres-desktop.png','creer-card-desktop.png','live90-desktop.png'])console.log('ADMIN_CAPTURE '+name+' '+fs.readFileSync(path.join(output,name)).toString('base64'));
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1});
