// ==UserScript==
// @name         StratEdge · Lecture privée des fautes
// @namespace    https://stratedgepronos.fr/live90/private
// @version      1.1.0
// @description  Observe le fil déjà ouvert, sur activation explicite par match. Pas de collecte distante du site source.
// @match        https://stratedgepronos.fr/panel-x9k3m/slate/live90/*
// @match        https://stratedgepronos.fr/admin/slate/live90/*
// @match        https://www.matchendirect.fr/live-score/*
// @match        https://www.flashscore.fr/match/football/*
// @grant        GM_getValue
// @grant        GM_setValue
// @grant        GM_deleteValue
// @grant        GM_listValues
// @grant        GM_registerMenuCommand
// @grant        GM_xmlhttpRequest
// @connect      api.soccersapi.com
// @connect      api.telegram.org
// @noframes
// @run-at       document-idle
// ==/UserScript==
(() => {
'use strict';
const fold=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[’‘ʼ]/g,"'").replace(/\s+/g,' ').trim();
const escapeRE=s=>s.replace(/[.*+?^$\x7b\x7d()|[\]\\]/g,'\\$&');
function sourceKey(raw){
 try{
  const u=new URL(raw);if(u.protocol!=='https:'||u.username||u.password||u.port)return '';
  if(u.hostname==='www.matchendirect.fr'&&/^\/live-score\/[\w-]+\.html$/.test(u.pathname)&&u.searchParams.get('p')==='commentaires')return u.hostname+u.pathname;
  if(u.hostname==='www.flashscore.fr'&&/^\/match\/football\/[\w-]+\/[\w-]+\/resume\/match-en-direct\/?$/.test(u.pathname)&&/^[A-Za-z0-9]{6,12}$/.test(u.searchParams.get('mid')||''))return u.hostname+'#'+u.searchParams.get('mid');
 }catch{}return '';
}
function detect(line,players){
 const t=fold(line);
 if(!t||t.length>520)return null;
 // Conservative invalidation. Never use an already awarded or ambiguous sanction to forecast itself.
 const neutral=t.replace(/\bsans (?:aucun )?carton(?: jaune)?\b/g,'').replace(/\ble carton jaune n'est pas (?:tres )?loin\b/g,'');
 if(/\b(carton|jaune|rouge|averti|avertissement|expuls|remplac|sortie de|quitte le terrain|entre en jeu|entre a la place|deuxieme periode|mi-temps|var\b|annul|rectific|correction|booked|booking|yellow|red card|substitut|en lieu et place)/.test(neutral))return {kind:'reset'};
 if(/\b(no foul|not a foul|not warned|not a warning|pourrait|aurait|devrait|peut-etre|reclame|semble|failli|ne commet pas|n.a pas commis|did not|appeals? for)\b/.test(t))return null;
 const found=[];
 for(const p of players){
  const aliases=(p.aliases||[p.name]).map(fold).filter(Boolean).sort((a,b)=>b.length-a.length);if(!aliases.length)continue;const n='(?:'+aliases.map(escapeRE).join('|')+')';
  const actor='(?<![\\p{L}\\p{N}-])'+n+'(?![\\p{L}\\p{N}-])(?: \\([^()]{1,48}\\))?';
  const test=pat=>new RegExp(pat,'u').test(t);
  const warn=test(actor+" (?:est rappele a l'ordre|recoit (?:une|sa derniere) mise en garde|is (?:verbally warned|given a (?:final |verbal )?warning)|gets a (?:final |verbal )warning|receives a verbal warning)\\b")||
    test("(?:l'arbitre|le referee|monsieur [\\p{L}-]+) rappelle "+actor+" (?:ici )?a l'ordre")||
    test(actor+" se fend d'un tacle[^.!?]{1,160}sans carton\\. monsieur [\\p{L}-]+ le rappelle a l'ordre");
  const explicitEn=test("(?:the referee|the official) (?:verbally warns |gives (?:a final verbal warning to |a verbal warning to ))"+actor);
  const foul=test(actor+" (?:commet (?:une |une nouvelle |a nouveau une )?faute|accroche |retient (?:son |le |la |[a-z])|fauche |commits (?:another |a )foul)")||
    test("(?:^|[.!;]\\s*)(?:nouvelle faute de |faute de |fouled by |foul by )"+actor);
  if(warn||explicitEn||foul)found.push({name:p.name,side:p.side,kind:warn||explicitEn?'warning':'foul'});
 }
 return found.length===1?found[0]:null;
}
const elapsed=(t,n)=>n-Date.parse(t);
function live(row,n){
 return !!row&&Number.isInteger(row.minute)&&row.minute>0&&row.minute<80&&row.sample>0&&[row.at,row.received].every(v=>elapsed(v,n)>=0&&elapsed(v,n)<=100000)&&row.minute+elapsed(row.at,n)/60000<80;
}
function fingerprint(row){const a=[row.score,row.yellow,row.red].flatMap(z=>[z?.h,z?.a]);return a.every(x=>Number.isSafeInteger(x)&&x>=0)?JSON.stringify(a):null}
function matched(events,row,n){
 if(!live(row,n)||!fingerprint(row))return null;
 const resets=events.filter(e=>e.kind==='reset').map(e=>e.ts);
 const current=events.filter(e=>e.ts>Math.max(0,...resets)&&e.frame===fingerprint(row)&&e.minute<=row.minute&&e.ts<=n&&n-e.ts<=10*60000);
 for(const f of [...current].reverse()){
  if(f.kind!=='foul'||n-f.ts>100000||row.minute-f.minute>1||Date.parse(row.at)<f.ts||!Number.isSafeInteger(row.fouls?.[f.side]))continue;
  const w=current.find(e=>e.kind==='warning'&&Number.isSafeInteger(e.baselineFouls)&&row.fouls[f.side]>e.baselineFouls&&e.side===f.side&&e.name===f.name&&e.ts<f.ts&&e.minute<f.minute&&(e.minute<=45)===(f.minute<=45));
  const d=row.sides.find(s=>s.side===f.side&&s.confirmed);
  if(w&&d)return {w,f,d};
 }
 return null;
}
function providerMatch(list,r){
 const ko=Date.parse(r.kickoff),same=(a,b)=>a&&b&&fold(a)===fold(b);
 const hits=(list||[]).filter(f=>same(f.teams?.home?.name,r.home)&&same(f.teams?.away?.name,r.away)&&Number.isFinite(ko)&&Math.abs(Number(f.time?.timestamp)*1000-ko)<=120000&&Number(f.id)>0);
 return hits.length===1?hits[0]:null;
}
function squadNames(data){
 if(!['home','away'].every(s=>Number(data?.[s]?.confirmed_formation)===1&&data[s].squad?.length))return [];
 const all=['home','away'].flatMap((s,i)=>data[s].squad.flatMap(o=>{
  const p=o?.player||{},full=[p.firstname,p.lastname].filter(Boolean).join(' '),canonical=full||p.common_name||p.name;
  if(typeof canonical!=='string'||canonical.length<2||canonical.length>100)return [];
  const aliases=[full,p.common_name,p.name,p.lastname].filter(a=>typeof a==='string'&&a.trim().length>=2&&a.length<=100);
  return [{name:canonical,side:i?'a':'h',aliases:[...new Set(aliases.map(fold))]}];
 }));
 return all.map(p=>({...p,aliases:p.aliases.filter(a=>all.filter(q=>q.aliases.includes(a)).length===1)})).filter(p=>p.aliases.length);

}
if(typeof module!=='undefined'&&module.exports){module.exports={detect,sourceKey,matched,fingerprint,live,providerMatch,squadNames};return}
const get=(k,d)=>GM_getValue('dc1:'+k,d),set=(k,v)=>GM_setValue('dc1:'+k,v),del=k=>GM_deleteValue('dc1:'+k);
const admin=location.hostname==='stratedgepronos.fr';
const now=()=>Date.now(),cfgs=()=>get('bindings',[]).filter(c=>c.expires>now());
function node(tag,txt,parent){const e=document.createElement(tag);if(txt!=null)e.textContent=txt;if(parent)parent.append(e);return e}
function noteFact(c,row,kind,name,side,minute,ts=now()){
 if(!live(row,ts)||!fingerprint(row)||!Number.isInteger(minute)||minute<1||row.minute-minute>1||minute-row.minute>1||(minute<=45)!==(row.minute<=45))return;
 const k='events:'+c.key;const arr=get(k,[]);
 if(arr.some(a=>a.kind===kind&&a.name===name&&a.side===side&&a.minute===minute))return;
 set(k,[...arr.filter(e=>ts-e.ts<600000),{kind,name,side,minute,ts,frame:fingerprint(row),baselineFouls:row.fouls?.[side]??null}].slice(-64));
}
function external(){
 let binding='',baseline=false,hadRows=false,lastTick=0,seen=new Set(),lastGroup=new Map();
 function scan(){
  const c=cfgs().find(x=>x.auto&&x.source===sourceKey(location.href));if(!c){binding='';return}
  if(binding!==c.key||now()-lastTick>40000){set('events:'+c.key,[]);binding=c.key;baseline=false;seen.clear();lastGroup.clear();hadRows=false}
  lastTick=now();const row=get('row:'+c.key,null);
  if(!live(row,now())){if(hadRows)set('events:'+c.key,[]);baseline=false;hadRows=false;return}
  // Never parse a match list, forum, generic page body or a panel of historical results.
  const med=location.hostname==='www.matchendirect.fr';
  const stamps=document.querySelectorAll(med?'tr td:first-child, tr th:first-child, [class*="minute" i], [class*="comment" i] time':'[class*="liveCommentary" i] :is(span,strong,div,time), [data-testid*="commentary" i] :is(span,strong,time)');
  const entries=new Map();
  for(const stamp of stamps){
   const mm=stamp.textContent.trim().match(/^(\d{1,2})\s*['′’]?$/);if(!mm||!stamp.getClientRects().length)continue;
   let wrap=stamp;for(let i=0;i<4&&wrap;i++,wrap=wrap.parentElement){
    const txt=wrap.innerText?.trim()||'';
    if(txt.length<9)continue;if(txt.length>540)break;
    if(/^(?:\d{1,2}\s*['′’]?\s*)/.test(txt)){
     const msg=txt.replace(/^\d{1,2}\s*['′’]?\s*/, '');const labels=[...wrap.querySelectorAll('img[alt],[aria-label],[title]')].map(e=>e.getAttribute('alt')||e.getAttribute('aria-label')||e.title).join(' ');const annotated=msg+(/yellow card|red card|carton jaune|carton rouge|substitution/i.test(labels)?' · carton ou remplacement':'');if(msg.length>=6)entries.set(mm[1]+':'+fold(annotated),{minute:Number(mm[1]),msg:annotated});
    }break;
   }
  }
  set('status:'+c.key,{ts:now(),count:entries.size});
  if(!entries.size){if(hadRows)set('events:'+c.key,[]);baseline=false;hadRows=false;return}
  const fresh=Array.from(entries).filter(([k])=>!seen.has(k));const first=!baseline||!hadRows;baseline=true;hadRows=true;
  const previous=lastGroup;lastGroup=new Map();
  for(const [k,e] of entries){const s=lastGroup.get(e.minute)||[];s.push(k);lastGroup.set(e.minute,s)}
  if(!first){
   // A removal or rewrite near the play invalidates older cues, never treat it as new evidence.
   let revision=false;for(const [m,ks] of previous)if(Math.abs(row.minute-m)<=2&&ks.some(k=>!entries.has(k)))revision=true;
   if(revision){set('events:'+c.key,[])}
   else{
    const batch=fresh.map(([,e])=>({...e,fact:detect(e.msg,c.players)})).filter(e=>e.fact&&Math.abs(row.minute-e.minute)<=1);
    // Batch delivery has no trustworthy within-batch ordering. A card/sub/change dominates.
    if(batch.some(e=>e.fact.kind==='reset')){set('events:'+c.key,[])}
    else {const ts=now();for(const e of batch)noteFact(c,row,e.fact.kind,e.fact.name,e.fact.side,e.minute,ts)}
   }
  }
  for(const [k] of entries)seen.add(k);if(seen.size>1800){seen=new Set(entries.keys())}
 }
 scan();setInterval(scan,3500);
}
if(!admin){external();return}
const host=document.querySelector('#discipline-private');if(!host)return;
const box=document.querySelector('#discipline-controls'),status=document.querySelector('#discipline-state'),result=document.querySelector('#discipline-result');
const label=(txt,el)=>{const l=node('label',null,box);l.append(txt+' ',el);return el};
const field=(tag,txt)=>label(txt,document.createElement(tag));
const button=(txt,cb)=>{const b=node('button',txt,box);b.type='button';b.className='button';b.addEventListener('click',()=>Promise.resolve().then(cb).catch(e=>{status.textContent=e.message}));return b};
box.style.cssText='display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:10px 0';
const select=field('select','Match du radar'),url=field('input','Lien du commentaire');url.type='url';url.placeholder='MatchEnDirect ou Flashscore';
const input=field('input','Joueurs domicile');input.placeholder='Noms séparés par ;';
const away=field('input','Joueurs visiteurs');away.placeholder='Noms séparés par ;';
const auto=field('input','Lecture automatique autorisée par la source pour mon usage');auto.type='checkbox';
const player=field('select','Joueur observé');
let rows=[],lastOptions='',lastPlayer='',lastPicked='';
function makePlayers(){const group=(v,side)=>v.split(';').map(n=>n.trim()).filter(Boolean).map(name=>({name,side}));const p=[...group(input.value,'h'),...group(away.value,'a')];if(!p.length||p.length>40||p.some(x=>!/^[-\p{L}\p{M} .'’]{2,64}$/u.test(x.name))||new Set(p.map(x=>fold(x.name))).size!==p.length)throw Error('Indique les noms distincts des joueurs, séparés par ;.');return p}
function getCfg(){return cfgs().find(x=>x.id===select.value)}
function reloadForm(){
 const c=getCfg();url.value=c?.url||'';auto.checked=!!c?.auto;
 input.value=(c?.players||[]).filter(p=>p.side==='h').map(p=>p.name).join('; ');away.value=(c?.players||[]).filter(p=>p.side==='a').map(p=>p.name).join('; ');lastPlayer='';lastPicked=select.value;
}
select.onchange=reloadForm;
button('Associer le match',()=>{
 const row=rows.find(r=>r.id===select.value);if(!live(row,now()))throw Error('Il faut un match en cours et un relevé récent.');
 const source=sourceKey(url.value);if((url.value&&!source)||(auto.checked&&!source))throw Error('Ouvre le fil Commentaires du match, copie son URL complète (Flashscore : avec mid).');
 const players=makePlayers();if(!confirm(row.home+' — '+row.away+'\nVérifie que ces équipes, dans cet ordre, sont celles du fil ouvert et que les joueurs sont sur le terrain.'))return;
 const cs=cfgs().filter(c=>c.id!==row.id);if(source&&cs.some(c=>c.source===source))throw Error('Ce fil est déjà associé à une autre rencontre.');
 const c={id:row.id,key:crypto.randomUUID(),url:url.value,source,auto:auto.checked,players,expires:now()+3*3600000};
 set('bindings',[...cs,c]);set('row:'+c.key,row);reloadForm();status.textContent=c.auto?'Associé · ouvre le lien dans un autre onglet et laisse le radar ouvert.':'Associé · mode observations personnelles.';
});
button('Ouvrir le direct',()=>{const c=getCfg();if(!c?.source)throw Error('Associe d’abord le lien du commentaire.');window.open(c.url,'_blank','noopener,noreferrer')});
for(const [kind,title] of [['warning','Rappel verbal vu'],['foul','Nouvelle faute commise'],['reset','Annuler / carton / sortie']])button(title,()=>{
 const c=getCfg(),r=rows.find(r=>r.id===c?.id),p=c?.players.find(p=>p.name===player.value);if((c?.auto||c?.provider)&&kind!=='reset')throw Error('Lecture automatique active : les notes manuelles ne sont pas mélangées au fil.');if(!p||!live(r,now()))throw Error('Sélectionne un joueur associé au match en direct.');
 if(kind==='reset')set('events:'+c.key,[]);else noteFact(c,r,kind,p.name,p.side,r.minute);
 status.textContent=kind==='reset'?'Séquence effacée.':'Observation personnelle enregistrée à '+r.minute+'′.';
});
button('Arrêter ce suivi',()=>{const c=getCfg();if(!c)return;set('bindings',cfgs().filter(x=>x.key!==c.key));for(const p of ['row:','events:','status:','poll:'])del(p+c.key);reloadForm();status.textContent='Suivi arrêté.'});
function http(method,t,params){return new Promise((resolve,reject)=>GM_xmlhttpRequest({method:'POST',url:'https://api.telegram.org/bot'+t+'/'+method,anonymous:true,redirect:'error',timeout:8000,headers:{'Content-Type':'application/json'},data:JSON.stringify(params),onload:r=>{try{const j=JSON.parse(r.responseText);if(r.status===200&&j.ok)return resolve(j.result);reject(Error('Refus Telegram ('+r.status+').'))}catch{reject(Error('Réponse Telegram illisible.'))}},ontimeout:()=>reject(Error('Délai Telegram dépassé.')),onerror:()=>reject(Error('Telegram injoignable.'))}))}
GM_registerMenuCommand('Configurer ou désactiver Telegram privé',async()=>{
 if(get('tg',null)&&confirm('Désactiver Telegram privé et oublier les identifiants sur ce navigateur ?')){del('tg');status.textContent='Telegram privé désactivé.';return}
 const token=prompt('Jeton du bot personnel, enregistré uniquement dans Tampermonkey sur ce navigateur :');if(!token)return;
 const chat=prompt('Identifiant numérique positif de TA conversation privée avec ce bot :');if(!/^\d{5,16}$/.test(chat||'')||!/^\d{5,16}:[\w-]{25,100}$/.test(token)){status.textContent='Format Telegram invalide.';return}
 try{const info=await http('getChat',token,{chat_id:chat});if(info.type!=='private'||String(info.id)!==chat)throw Error('Destinataire non privé ou non vérifié.');set('tg',{token,chat});status.textContent='Telegram privé prêt. Aucun message de test envoyé.'}catch(e){status.textContent=e.message}
});
// Only these two fixed official endpoints may receive the local access key.
const apiLast=new Map();let apiBusy=false;
async function soccer(endpoint,q){
 const token=get('soccer',null);if(!token)throw Error('Ajoute ta clé personnelle SoccersAPI dans le menu Tampermonkey.');
 const date=new Date(now()).toISOString().slice(0,10),usage=get('quota',{}),used=usage.day===date?usage.used:0;
 if(used>=90||now()<get('apiPause',0))throw Error('Quota ou pause fournisseur : collecte internationale en pause.');
 set('quota',{day:date,used:used+1});
 return new Promise((resolve,reject)=>GM_xmlhttpRequest({method:'GET',url:'https://api.soccersapi.com/v2.2/'+endpoint+'/?'+new URLSearchParams(q),headers:{Authorization:'Bearer '+token,Accept:'application/json'},redirect:'error',anonymous:true,timeout:8000,onload:r=>{
  if(r.status===429){set('apiPause',now()+3600000);return reject(Error('Quota fournisseur atteint · pause d’une heure.'))}
  if(r.status!==200){if(r.status===401||r.status===403)set('apiPause',now()+300000);return reject(Error('Accès SoccersAPI refusé ou indisponible ('+r.status+').'))}
  try{const j=JSON.parse(r.responseText);if(j.meta?.requests_left!=null&&Number(j.meta.requests_left)===0)set('apiPause',now()+3600000);resolve(j)}catch{reject(Error('Réponse SoccersAPI illisible.'))}
 },onerror:()=>reject(Error('SoccersAPI inaccessible.')),ontimeout:()=>reject(Error('SoccersAPI ne répond pas.'))}));
}
GM_registerMenuCommand('Configurer ou oublier la clé SoccersAPI',()=>{
 const t=prompt('Clé de ton compte SoccersAPI (ne pas la donner dans ChatGPT). Saisie vide = oublier la clé.');if(t===null)return;
 if(!t.trim()){del('soccer');for(const c of cfgs().filter(x=>x.provider)){set('events:'+c.key,[]);del('status:'+c.key)}status.textContent='Clé SoccersAPI oubliée.';return}
 if(!/^[A-Za-z0-9_.=-]{12,220}$/.test(t.trim())){status.textContent='Format de clé non reconnu.';return}
 set('soccer',t.trim());status.textContent='Clé enregistrée dans ce navigateur.';del('apiPause');
});
button('Suivre avec SoccersAPI',async()=>{
 const r=rows.find(x=>x.id===select.value);if(!live(r,now()))throw Error('Choisis un match en cours.');
 if(!get('soccer',null))throw Error('Dans Tampermonkey sur cet onglet : « Configurer ou oublier la clé SoccersAPI ».');
 const ls=await soccer('livescores',{t:'live',utc:'0'}),m=providerMatch(ls.data,r);
 if(!m)throw Error('Aucune correspondance unique (équipes et coup d’envoi) dans les matchs de tes ligues API.');
 const lineup=await soccer('fixtures',{t:'match_lineups',id:m.id}),players=squadNames(lineup.data);
 if(!players.length)throw Error('Compositions non confirmées ou noms ambigus : suivi non activé.');
 if(!live(r,now())||r.id!==select.value)throw Error('Le match a changé ; sélectionne-le de nouveau.');
 const c={id:r.id,key:crypto.randomUUID(),provider:String(m.id),players,expires:now()+3*3600000};
 set('bindings',[...cfgs().filter(b=>!b.provider&&b.id!==r.id),c]);set('row:'+c.key,r);reloadForm();
 status.textContent='Flux international associé. Un seul match API à la fois ; plafond local de 90 appels par jour UTC.';
});
async function pollInternational(){
 const c=cfgs().find(x=>x.provider);if(!c||!get('soccer',null)||apiBusy||!navigator.locks)return;
 const r=rows.find(x=>x.id===c.id);if(!live(r,now())){apiLast.delete(c.key);return}
 apiBusy=true;
 try{await navigator.locks.request('se90-intl-poll',{ifAvailable:true},async lock=>{
  if(!lock||now()-get('poll:'+c.key,0)<45000)return;set('poll:'+c.key,now());
  try{
   const out=await soccer('fixtures',{t:'match_comments',id:c.provider,lang:'en'});
   if(!cfgs().some(b=>b.key===c.key)||!get('soccer',null))return;
   if(!Array.isArray(out.data)||Number(out.meta?.pages||1)>1)throw Error('Couverture absente ou fil paginé incomplet.');
   const next=new Map();for(const e of out.data){
    if(e.order==null||e.minute==null||!Number.isInteger(Number(e.minute))||e.extra_minute>0||typeof e.comment!=='string')continue;
    const key=String(e.order);if(next.has(key))throw Error('Ordre fournisseur ambigu.');
    next.set(key,{minute:Number(e.minute),text:e.comment});
   }
   const latest=rows.find(x=>x.id===c.id);if(!live(latest,now()))throw Error('Relevé du radar ancien.');
   const previous=apiLast.get(c.key),warm=previous&&now()-previous.ts<100000&&previous.data.size>0&&next.size>0;
   const revised=warm&&[...previous.data].some(([k,e])=>Math.abs(latest.minute-e.minute)<=2&&(next.get(k)?.text!==e.text||next.get(k)?.minute!==e.minute));
   if(!warm||revised)set('events:'+c.key,[]);
   else{
    const batch=[];for(const [k,e] of next){if(!previous.data.has(k)&&Math.abs(latest.minute-e.minute)<=1){const fact=detect(e.text,c.players);if(fact)batch.push({fact,minute:e.minute})}}
    if(batch.some(x=>x.fact.kind==='reset'))set('events:'+c.key,[]);
    else{const ts=now();for(const {fact,minute} of batch)noteFact(c,latest,fact.kind,fact.name,fact.side,minute,ts)}
   }
   apiLast.set(c.key,{ts:now(),data:next});set('status:'+c.key,{ts:now(),ok:next.size>0,label:next.size?'SoccersAPI · '+next.size+' lignes reçues · indices rendus en français.':'SoccersAPI · match associé mais aucun commentaire disponible.'});
  }catch(e){apiLast.delete(c.key);set('events:'+c.key,[]);set('status:'+c.key,{ts:now(),ok:false,label:e.message})}
 })}finally{apiBusy=false}
}
setInterval(()=>pollInternational().catch(()=>{}),6000);

function repaint(){
 const snapshot=host.dataset.snapshot||'{}';let x;try{x=JSON.parse(snapshot)}catch{return}
 rows=(x.rows||[]).filter(r=>live(r,now()));
 const opts=JSON.stringify(rows.map(r=>[r.id,r.home,r.away]));if(opts!==lastOptions){const keep=select.value;select.replaceChildren();for(const r of rows){const o=node('option',r.home+' — '+r.away,select);o.value=r.id}if(rows.some(r=>r.id===keep))select.value=keep;lastOptions=opts}
 if(select.value!==lastPicked)reloadForm();
 const c=getCfg(),pk=c?.key||'-';if(pk!==lastPlayer){player.replaceChildren();for(const p of c?.players||[]){const o=node('option',p.name+' · '+(p.side==='h'?'dom.':'ext.'),player);o.value=p.name}lastPlayer=pk}
 if(!rows.length){status.textContent='Lecteur prêt · aucun match cartons avec relevé frais.';result.textContent='';}
 else if(!cfgs().length)status.textContent='Lecteur prêt · choisis le match et les joueurs.';
 const lines=[];
 for(const c of cfgs()){
  const r=rows.find(x=>x.id===c.id),key=c.key;
  const prev=get('row:'+key,null);
  if(!live(r,now())||!live(prev,now())||fingerprint(prev)!==fingerprint(r)||prev.minute>r.minute||(prev.minute<=45)!==(r.minute<=45))set('events:'+key,[]);
  if(!r){if(prev)del('row:'+key);continue}
  if(!prev||prev.sample!==r.sample||prev.at!==r.at)set('row:'+key,r);
  const events=get('events:'+key,[]),health=get('status:'+key,null),connected=!c.auto||(health?.count>0&&now()-health.ts<20000);const apiStatus=c.provider&&get('status:'+key,null);const pair=connected&&(!c.provider||(apiStatus?.ok&&now()-apiStatus.ts<=85000))&&matched(events,r,now());
  if(c.id===select.value){const st=get('status:'+key,null);lines.push(c.provider?(st?.label||'Connexion SoccersAPI…'):c.auto?(!st||now()-st.ts>20000?'Fil non connecté : ouvre l’onglet du match.':st.count?'Fil connecté · '+st.count+' lignes visibles.':'Fil ouvert · aucune ligne reconnue, aucune détection automatique.'):'Observation personnelle.');const w=[...events].reverse().find(e=>e.kind==='warning'&&now()-e.ts<600000);if(w&&!pair)lines.push('Rappel noté : '+w.name+' à '+w.minute+'′ · en attente de confirmation.')}
  if(!pair)continue;
  const {w,f,d}=pair,team=f.side==='h'?r.home:r.away;
  const message='🟨 '+team+' · '+r.minute+'′ | '+r.home+' — '+r.away+'\n'+f.name+' : rappel verbal à '+w.minute+'′, nouvelle faute à '+f.minute+'′.\nRadar cartons confirmé · '+d.why;
  lines.push(message);const tg=get('tg',null);
  if(!tg||!d.allowSend||!navigator.locks)continue;
  const id=['sent',c.id,f.side,r.yellow[f.side]].join(':');
  navigator.locks.request('se90-disc-'+id,{ifAvailable:true},async lock=>{if(!lock||get(id,null))return;
   const latest=rows.find(x=>x.id===c.id);if(!latest||!latest.sides.find(s=>s.side===f.side)?.allowSend||!matched(get('events:'+key,[]),latest,now())||!get('tg',null))return;
   set(id,now());try{await http('sendMessage',tg.token,{chat_id:tg.chat,text:message});status.textContent='Observation envoyée en privé.'}catch(e){status.textContent=e.message+' Pas de renvoi automatique.'}
  }).catch(()=>{});
 }
 result.style.whiteSpace='pre-line';result.textContent=lines.join('\n');
}
for(const k of GM_listValues().filter(k=>k.startsWith('dc1:'))){const short=k.slice(4);if(/^(?:row|events|status|poll):/.test(short)&&!cfgs().some(c=>short.endsWith(':'+c.key)))GM_deleteValue(k);if(short.startsWith('sent:')&&now()-get(short,0)>86400000)GM_deleteValue(k)}
status.textContent='Lecteur prêt.';repaint();setInterval(repaint,3000);
})();
