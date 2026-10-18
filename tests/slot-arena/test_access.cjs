'use strict';
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),assert=require('node:assert/strict'),{spawn}=require('node:child_process'),net=require('node:net');
(async()=>{
 const temp=fs.mkdtempSync(path.join(os.tmpdir(),'slot-arena-test-'));fs.mkdirSync(path.join(temp,'tournoi-slots'));fs.mkdirSync(path.join(temp,'includes'));
 for(const file of ['api.php','state.php'])fs.copyFileSync(path.join(__dirname,'../../public_html/tournoi-slots',file),path.join(temp,'tournoi-slots',file));
 fs.writeFileSync(path.join(temp,'includes/auth.php'),`<?php
 session_start();
 function isAdmin(): bool { return ($_SESSION['is_admin'] ?? false) === true; }
 function verifyCsrf(string $v): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$v); }
 function getDB(): PDO { throw new RuntimeException('Isolated test: database access reached'); }
 `);
 fs.writeFileSync(path.join(temp,'login-fixture.php'),`<?php session_start(); $_SESSION['is_admin']=true; $_SESSION['csrf']='isolated-test-csrf'; echo 'ok';`);
 const server=net.createServer();await new Promise(r=>server.listen(0,'127.0.0.1',r));const port=server.address().port;await new Promise(r=>server.close(r));
 const args=process.env.ARENA_PHP?['-n']:[];const php=spawn(process.env.ARENA_PHP||'php',[...args,'-d','session.save_path='+temp,'-d','display_errors=0','-S','127.0.0.1:'+port,'-t',temp],{stdio:['ignore','pipe','pipe']});let logs='';php.stderr.on('data',x=>logs+=x);let spawnError;php.on('error',e=>spawnError=e);
 const url='http://127.0.0.1:'+port;
 try {
  let ready=false;for(let i=0;i<60;i++){if(spawnError)throw spawnError;try{await fetch(url+'/not-found');ready=true;break}catch{await new Promise(r=>setTimeout(r,50))}}assert(ready,logs);
  const post=(headers={},body={})=>fetch(url+'/tournoi-slots/api.php',{method:'POST',headers:{'Content-Type':'application/json',...headers},body:JSON.stringify(body)});
  let r=await post({}, {state:{title:'Unauthorized edit'},admin:true});assert.equal(r.status,403,await r.clone().text());assert.match((await r.json()).error,/organisateur/);
  r=await fetch(url+'/tournoi-slots/api.php',{method:'DELETE'});assert.equal(r.status,405);
  r=await fetch(url+'/login-fixture.php');const cookie=r.headers.get('set-cookie').split(';')[0];
  r=await post({Cookie:cookie},{revision:0,state:{}});assert.equal(r.status,403,'Admin needs CSRF');
  r=await post({Cookie:cookie,'X-CSRF-Token':'wrong'},{revision:0,state:{}});assert.equal(r.status,403);
  r=await post({Cookie:cookie,'X-CSRF-Token':'isolated-test-csrf','Sec-Fetch-Site':'cross-site'},{revision:0,state:{}});assert.equal(r.status,403);
  r=await post({Cookie:cookie,'X-CSRF-Token':'isolated-test-csrf'},{revision:0,state:{}});assert.equal(r.status,422,'Invalid state rejected before database');
  r=await post({Cookie:cookie,'X-CSRF-Token':'isolated-test-csrf'},{revision:0,state:{schema:'slot-arena.v1',id:'test',title:'Test',players:['A','B'],slots:[],rounds:[],order:'list'}});assert.equal(r.status,503,'Authorized valid request reaches persistence');
  console.log('Slot Arena: anonymous writes, forged admin flag, CSRF, cross-origin, methods and validation gates OK');
 } finally {php.kill();fs.rmSync(temp,{recursive:true,force:true})}
})().catch(e=>{console.error(e);process.exitCode=1});
