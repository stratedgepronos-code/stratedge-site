'use strict';
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),cp=require('node:child_process'),assert=require('node:assert/strict'),net=require('node:net');
const {chromium}=require(path.join(process.env.PUBLIC_UI_MODULES||process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES,'playwright'));
const output=process.env.PUBLIC_UI_OUTPUT||'/tmp/public-ui';fs.mkdirSync(output,{recursive:true});const temp=fs.mkdtempSync(path.join(os.tmpdir(),'stratedge-public-'));const fixture=path.join(temp,'site');
const php=process.env.PHP_BIN||'php';const prepared=cp.spawnSync(php,[path.join(__dirname,'prepare_fixture.php'),fixture],{encoding:'utf8'});assert.equal(prepared.status,0,prepared.stdout+prepared.stderr);
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer().listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p))})});
 const server=cp.spawn(php,['-S','127.0.0.1:'+port,'-t',fixture],{stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',s=>logs+=s);const origin='http://127.0.0.1:'+port;
 for(let i=0;i<40;i++){try{const ready=await fetch(origin+'/');await ready.arrayBuffer();break}catch(e){await new Promise(r=>setTimeout(r,100))}}
 const executable=['/usr/bin/google-chrome','/usr/bin/chromium','/usr/bin/chromium-browser'].find(p=>fs.existsSync(p));
 const browser=await chromium.launch({headless:true,...(executable?{executablePath:executable}:{}),args:['--no-sandbox']});
 try{
 const page=await browser.newPage({viewport:{width:1440,height:1050}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 for(const width of [1440,390,320]){
  await page.setViewportSize({width,height:1000});
  for(const route of ['/', '/journal.php','/offres.php','/methode.php','/historique.php','/historique.php?vue=bilan','/historique-multi.php','/article.php?slug=journee-sans-pari-une-decision']){
   const res=await page.goto(origin+route);assert.equal(res.status(),200,route+' render');await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(100);
   if(route!=='/historique-multi.php')assert.equal(await page.locator('h1').count(),1,route+' heading');assert(!(await page.locator('body').innerText()).match(/Warning:|Fatal error|BROUILLON PRIVÉ|FUTUR NON PUBLIÉ/),route+' data or PHP leak');
   if(route==='/'){await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=700){scrollTo(0,y);await new Promise(r=>setTimeout(r,50))}scrollTo(0,0)});await page.waitForTimeout(600)}
   await page.screenshot({path:path.join(output,(route.includes('vue=bilan')?'bilan':route==='/'?'home':route.split(/[?/.]/).filter(Boolean)[0])+'-'+width+'.png'),fullPage:true});
   const dimensions=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll('main>*')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).map(e=>e.className)}));assert(dimensions.scroll<=dimensions.width+1,route+' horizontal overflow '+width+' '+JSON.stringify(dimensions));
  }
 }
 await page.setViewportSize({width:390,height:844});await page.goto(origin+'/');
 assert.equal(await page.locator('body').getAttribute('data-design'),'stratedge-original');
 assert((await page.locator('h1').innerText()).includes('Analyse précise.'));assert.equal(await page.locator('.tip-card').count(),4);
 assert(await page.locator('.mascot-img').evaluate(e=>e.complete&&e.naturalWidth>0),'Owner mascot missing');
 assert.equal(await page.locator('#mobileMenu').evaluate(e=>e.inert),true);await page.locator('.hamburger').click();assert.equal(await page.locator('.hamburger').getAttribute('aria-expanded'),'true');assert.equal(await page.locator('#mobileMenu').evaluate(e=>e.inert),false);await page.keyboard.press('Escape');assert.equal(await page.locator('.hamburger').getAttribute('aria-expanded'),'false');
 await page.emulateMedia({reducedMotion:'reduce'});await page.goto(origin+'/');assert.equal(await page.locator('.hero-badge').first().evaluate(e=>getComputedStyle(e).animationName),'none');await page.emulateMedia({reducedMotion:'no-preference'});
 await page.goto(origin+'/journal.php');assert.equal(await page.locator('#public-nav').evaluate(e=>e.inert),true);await page.locator('.menu-toggle').click();assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'true');await page.keyboard.press('Escape');assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'false');
 await page.locator('.motion-toggle').click();assert.equal(await page.locator('body').evaluate(e=>e.classList.contains('motion-paused')),true);
 await page.goto(origin+'/journal.php?type=analyse');assert.equal(await page.locator('.story').count(),0);await page.getByRole('link',{name:'Tout le journal',exact:true}).click();assert.equal(await page.locator('.story').count(),3);
 assert.equal((await page.request.get(origin+'/article.php?slug=private-draft')).status(),404);assert.equal((await page.request.get(origin+'/article.php?slug=future-post')).status(),404);
 const xml=await (await page.request.get(origin+'/journal-sitemap.php')).text();assert(xml.includes('journee-sans-pari'));assert(!xml.includes('private-draft')&&!xml.includes('future-post'));
 for(const alias of ['offre-tennis.php','offre-fun.php','offres-multisports.php','souscrire.php','packs-daily.php','offre.php?type=vip_max']){const res=await page.goto(origin+'/'+alias);assert.equal(res.status(),200,alias+' public offers');assert.equal(await page.locator('.offer').count(),4)}
 await page.goto(origin+'/historique.php');assert.equal(await page.locator('.t-card').count(),3);await page.getByRole('link',{name:/Bilan complet/}).click();assert((await page.locator('.proof-note').first().innerText()).includes('cote absente'));assert.equal((await page.locator('.results-stats strong').nth(2).innerText()),'—');
 await page.goto(origin+'/decouvrir.php?offre=tennis&utm_source=x');await page.getByRole('link',{name:'Créer mon compte'}).click();assert.equal(await page.locator('input[name=csrf_token]').count(),1,'Existing registration form and CSRF intact');await page.screenshot({path:path.join(output,'register-mobile.png'),fullPage:true});
 const form={csrf_token:'fixture-csrf',nom:'Recette',email:'recette@example.test',password:'fixture-password',confirm:'fixture-password',antibot:'pass'};
 const signup=await page.request.post(origin+'/register.php',{form,maxRedirects:0});assert.equal(signup.status(),302);assert.equal(signup.headers().location,'/offre-tennis.php','Chosen offer lost after signup');
 const anonymous=await browser.newContext();let denied=await anonymous.request.get(origin+'/panel-x9k3m/journal.php');assert.equal(denied.status(),403);denied=await anonymous.request.get(origin+'/panel-x9k3m/journal.php',{headers:{'X-Test-Role':'admin'}});assert.equal(denied.status(),403,'Ordinary admin leaked journal');
 const admin=await browser.newContext({extraHTTPHeaders:{'X-Test-Role':'super'}});const editor=await admin.newPage();await editor.setViewportSize({width:1440,height:1050});await editor.goto(origin+'/panel-x9k3m/journal.php');assert.equal(await editor.locator('input[name=title]').count(),1);
 await editor.screenshot({path:path.join(output,'editor-desktop.png'),fullPage:true});await editor.setViewportSize({width:390,height:844});await editor.waitForTimeout(400);assert(await editor.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Editor mobile overflow');await editor.screenshot({path:path.join(output,'editor-mobile.png'),fullPage:true});
 await editor.locator('input[name=title]').fill('Article de recette navigateur');await editor.locator('textarea[name=summary]').fill('Un résumé pour vérifier le parcours complet de création.');await editor.locator('textarea[name=body]').fill('Un texte de recette entièrement fictif, enregistré dans une base isolée pour vérifier le comportement du formulaire.');
 await Promise.all([editor.waitForURL(/saved=1/),editor.getByRole('button',{name:'Enregistrer la publication'}).click()]);assert((await editor.locator('.editor-success').innerText()).includes('brouillon'));let id=new URL(editor.url()).searchParams.get('id');assert(id);
 let noCsrf=await admin.request.post(origin+'/panel-x9k3m/journal.php?id='+id,{form:{title:'Malicious change'}});assert((await noCsrf.text()).includes('Session expirée'),'CSRF not enforced');
 await editor.goto(origin+'/panel-x9k3m/journal.php?id='+id);await editor.locator('select[name=status]').selectOption('published');await Promise.all([editor.waitForURL(/saved=1/),editor.getByRole('button',{name:'Enregistrer la publication'}).click()]);
 const read=await anonymous.request.get(origin+'/article.php?slug=article-de-recette-navigateur');assert.equal(read.status(),200,'Published draft missing');
 await editor.locator('textarea[name=body]').fill('Un texte de recette corrigé et complété après la publication pour vérifier le suivi et la conservation de la première version.');await editor.locator('input[name=reason]').fill('Précision de recette ajoutée');await Promise.all([editor.waitForURL(/saved=1/),editor.getByRole('button',{name:'Enregistrer la publication'}).click()]);
 const article=await (await anonymous.request.get(origin+'/article.php?slug=article-de-recette-navigateur')).text();assert(article.includes('Précision de recette ajoutée')&&article.includes('entièrement fictif'),'Public revision lost');
 const privatePreview=await anonymous.request.get(origin+'/panel-x9k3m/journal.php?id='+id+'&preview=1');assert.equal(privatePreview.status(),403);
 const login=await anonymous.request.post(origin+'/login.php?redirect=https://evil.test',{form:{csrf_token:'fixture-csrf',email:'recette@example.test',password:'fixture-password'},maxRedirects:0});assert.equal(login.headers().location,'/dashboard.php','External redirect');
 const silent=await browser.newContext();await silent.request.post(origin+'/register.php',{form:{...form,antibot:'fail'},maxRedirects:0});
 const counts=cp.spawnSync(php,['-r',`$db=new PDO('sqlite:${fixture}/fixture.sqlite');echo $db->query("SELECT COUNT(*) FROM fixture_accounts")->fetchColumn().':'.$db->query("SELECT SUM(total) FROM se_public_counts WHERE event='inscription'")->fetchColumn();`],{encoding:'utf8'});assert.equal(counts.stdout,'1:1','Honeypot must not create account or count signup');
 assert.deepEqual(errors,[]);assert(!/Fatal error|PHP Warning|PHP Deprecated/.test(logs),logs.split('\n').filter(line=>/Fatal error|PHP Warning|PHP Deprecated/.test(line)).join('\n'));
 console.log('PUBLIC_UI_OK real PHP routes, responsive 1440/390/320, private drafts/future posts, public offers, registration continuity, CSRF, admin roles, publishing/corrections, filters, reduced motion and no external messages');
 }finally{await browser.close();server.kill();fs.rmSync(temp,{recursive:true,force:true})}
})().catch(e=>{console.error(e);process.exit(1)});
