// ==UserScript==
// @name         SEUIL90 · Packball Live Console
// @namespace    https://stratedgepronos.fr/live90
// @version      4.0.1
// @description  Collecte horodatée, fenêtres séparées, aucun pari automatique
// @match        https://packball.com/*
// @match        https://www.packball.com/*
// @grant        GM_xmlhttpRequest
// @grant        GM_getValue
// @grant        GM_setValue
// @grant        GM_registerMenuCommand
// @connect      stratedgepronos.fr
// @updateURL    https://raw.githubusercontent.com/stratedgepronos-code/stratedge-site/master/live90/live90-packball.user.js
// @downloadURL  https://raw.githubusercontent.com/stratedgepronos-code/stratedge-site/master/live90/live90-packball.user.js
// @noframes
// @run-at       document-idle
// ==/UserScript==
(()=>{'use strict';
const ENDPOINT='https://stratedgepronos.fr/api/live90/ingest.php';
let busy=false,last=null;
const number=t=>{const s=String(t??'').replace(/%/g,'').replace(',','.').trim();return /^-?\d+(?:\.\d+)?$/.test(s)?Number(s):null};
const norm=t=>String(t??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
function pair(el){
 // Packball peut retirer les classes des spans : lire aussi la paire visible.
 // Une cellule vide ou « - » reste inconnue, jamais 0-0.
 const m=String(el.textContent||'').trim().match(/^(-?\d+(?:[.,]\d+)?)\s*%?\s*[-–—|:]\s*(-?\d+(?:[.,]\d+)?)\s*%?$/);
 if(m)return {h:number(m[1]),a:number(m[2])};
 const values=[...el.querySelectorAll(':scope > div > span[class]')].map(s=>number(s.textContent)).filter(v=>v!==null);
 return values.length===2?{h:values[0],a:values[1]}:null;
}
function columnTitle(el){
 const attributes=['title','data-original-title','data-bs-original-title','aria-label'];
 for(const a of attributes){const t=el.getAttribute(a);if(t?.trim())return t.trim();}
 const candidates=[...el.querySelectorAll('[title], [data-original-title], [data-bs-original-title], [aria-label]')]
  .flatMap(child=>attributes.map(a=>child.getAttribute(a)?.trim()).filter(Boolean));
 // Ne pas décider arbitrairement entre deux infobulles différentes.
 const unique=[...new Set(candidates)];return unique.length===1?unique[0]:'';
}
function field(title){
 const t=norm(title);const window=/\b10\b/.test(t)?10:/\b5\b/.test(t)?5:0;
 const group=window?'ind'+window:'stats';
 let key=null;
 if(/buts attendus/.test(t))return window?{group,key:'exg'+window}:null;
 if(/^buts(?:\s|$)/.test(t))key='goals';
 else if(/jaunes.?rouges|second.*jaune/.test(t))key='second_yellow';
 else if(/cartons rouges/.test(t))key='red_cards';
 else if(/cartons jaunes/.test(t))key='yellow_cards';
 else if(/fautes/.test(t))key='fouls';
 else if(/total des tirs/.test(t))key='shots';
 else if(/tirs cadres/.test(t))key='sot';
 else if(/possession/.test(t))key='possession';
 else if(/difference de pression/.test(t))key='press_diff';
 else if(/indice de pression/.test(t))key='press_idx';
 else if(/attaques dangereuses/.test(t))key='da';
 return key?{group,key:key+(window?window:'')}:null;
}
function readStats(cells,heads,score){
 const out={stats:{},ind5:{},ind10:{},raw_cells:[],quality_errors:[]};
 // Un trou dans le DOM ne doit jamais attribuer les valeurs aux colonnes suivantes.
 if(cells.length!==heads.length){out.raw_cells=cells.map((el,i)=>({column:i+1,title:null,target:null,text:el.textContent.trim(),value:pair(el)}));out.quality_errors.push('Colonnes statistiques désalignées : '+cells.length+' cellules / '+heads.length+' en-têtes');return out;}
 const seen=new Set();
 cells.forEach((el,i)=>{
  const title=heads[i],f=field(title),value=pair(el),target=f?f.group+'.'+f.key:null;
  out.raw_cells.push({column:i+1,title,target,text:el.textContent.trim(),value});
  if(!title){out.quality_errors.push('Libellé statistique absent : colonne '+(i+1));return;}
  if(!f)return;
  if(seen.has(target)){out.quality_errors.push('Colonne ambiguë : '+target);out[f.group][f.key]=null;}
  else{seen.add(target);out[f.group][f.key]=value;}
 });
 const goals=out.stats.goals;
 if(goals&&score&&(goals.h!==score.h||goals.a!==score.a))out.quality_errors.push('Score et colonne Buts différents : attendre leur synchronisation');
 return out;
}
function status(raw){const t=norm(raw).trim();
 if(/aet|prolong|pen\.|tab|tirs au but/.test(t))return {state:'OTHER',minute:null};
 if(/^(termine|fini|ft\b|fin\b)/.test(t))return {state:'FT',minute:null};
 if(/^(mt|ht|mi-temps|pause)\b/.test(t))return {state:'HT',minute:45};
 const m=t.match(/^(\d{1,3})(?:\s*\+\s*(\d+))?\s*['′’]?$/);
 return m?{state:'LIVE',minute:+m[1],minute_extra:m[2]?+m[2]:0}:{state:t?'OTHER':'NS',minute:null};
}
function quoteMeta(title,cls){
 const t=norm(title).replace(/\s+/g,' ').trim();
 const unknown={market:'unmapped',side:null,period:null,team:null,verified:false};
 if(/corner|prochain but|les deux equipes|double chance|points|prochaine equipe/.test(t))return unknown;
 const cards=/cartons|cartes/.test(t);
 if(!cards&&!/buts/.test(t))return unknown;
 let side=/\bplus\b|\bover\b/.test(t)?'over':/\bmoins\b|\bunder\b/.test(t)?'under':null;
 const home=/domicile/.test(t),away=/visiteur|exterieur/.test(t);
 const half=/1(?:ere|re|er)? mi-temps|premiere mi-temps/.test(t);
 if((/mi-temps|2nd|2eme|deuxieme periode/.test(t)&&!half)||home&&away)return unknown;
 if(!side&&!cards&&!half&&!home&&!away&&/premiere ligne/.test(t))side=cls==='inp-7'?'over':cls==='inp-8'?'under':null;
 if(!side||(/equipe/.test(t)&&!home&&!away)||cards&&(!home&&!away||half))return unknown;
 return {market:cards?'team_cards':home||away?'team_goals':'total_goals',side,period:half?'HT':'FT',team:home?'h':away?'a':null,verified:true,...(cards?{unit:/jaunes/.test(t)?'yellow_cards':'cards'}:{})};
}
function collect(){
 const header=document.querySelector('section.fixtures .header, .fixtures .header');if(!header)throw Error('Tableau Packball introuvable');
 const heads=[...header.querySelectorAll('.col.live')].map(columnTitle);
 const meta={};header.querySelectorAll('[class*="inp-"]').forEach(el=>{const k=[...el.classList].find(c=>/^inp-\d+$/.test(c));if(k)meta[k]=columnTitle(el)});
 const rows=[];
 document.querySelectorAll('section.fixtures .row, .fixtures [class*="row fix-"]').forEach(row=>{
 const id=(String(row.className).match(/fix-(\d+)/)||[])[1];if(!id)return;
 const timeEl=row.querySelector('.col.time time');let ts=number(timeEl?.getAttribute('datetime'));if(ts!==null&&ts<1e11)ts*=1000;
 const raw=row.querySelector('.col.blin')?.textContent||'';const score=(row.querySelector('.result-f')?.textContent||'').trim().match(/^(\d+)\s*[-–]\s*(\d+)$/);
 const r={packball_id:id,home:row.querySelector('.team-home')?.textContent.trim()||'',away:row.querySelector('.team-away')?.textContent.trim()||'',league:row.querySelector('.title-league')?.getAttribute('title')||row.querySelector('.title-league')?.textContent.trim()||'',kickoff_ts:ts!==null&&Number.isFinite(new Date(ts).getTime())?new Date(ts).toISOString():null,status_raw:raw,...status(raw),score:score?{h:+score[1],a:+score[2]}:null,stats:{},ind5:{},ind10:{},quotes:[],raw_cells:[],quality_errors:[]};
 Object.assign(r,readStats([...row.querySelectorAll('.col.live')],heads,r.score));
 row.querySelectorAll('.inplay').forEach(el=>{
 const cls=[...el.classList].find(c=>/^inp-\d+$/.test(c));const title=meta[cls]||'';const t=norm(title);
 const mapping=quoteMeta(title,cls);
 r.quotes.push({...mapping,line:number(el.querySelector('.label')?.textContent),odds:number(el.querySelector('.odds-inplay')?.textContent),title,column:cls,bookmaker:'bet365',observed_at:new Date().toISOString()});
 });rows.push(r);
 });
 const column_map={stats:heads.map((title,i)=>({column:i+1,title,...field(title)})),odds:Object.entries(meta).map(([column,title])=>({column,title,...quoteMeta(title,column)}))};
 return {schema:2,collector_version:'4.0.1',cycle_id:crypto.randomUUID(),collected_at:new Date().toISOString(),source:'packball',page_rows:rows.length,odds_meta:meta,stat_headers:heads,column_map,rows};
}
const badge=document.createElement('div');Object.assign(badge.style,{position:'fixed',bottom:'14px',right:'14px',zIndex:'2147483647',background:'#0b1221',color:'#8eeadd',border:'1px solid #327d78',padding:'10px 16px',borderRadius:'12px',font:'12px system-ui',boxShadow:'0 6px 30px #0005'});badge.textContent='S90 · Initialisation';function mountBadge(){if(document.body&&!badge.isConnected)document.body.append(badge)}mountBadge();
const watcher=new MutationObserver(mountBadge);watcher.observe(document.documentElement,{childList:true,subtree:true});
function cycle(){mountBadge();if(busy)return;if(!/\/matches(?:\/|$|\?)/.test(location.pathname)){badge.textContent='S90 · Ouvre le tableau des matchs Packball';return}try{
 last=collect();const token=GM_getValue('SE90_TOKEN','');if(!token){badge.textContent='S90 · Configurer le token dans Tampermonkey';return;}
 busy=true;badge.textContent='S90 · Envoi de '+last.rows.length+' matchs';
 GM_xmlhttpRequest({method:'POST',url:ENDPOINT,headers:{'Content-Type':'application/json','X-SE-Token':token},data:JSON.stringify(last),timeout:15000,onload:res=>{busy=false;let x;try{x=JSON.parse(res.responseText)}catch{}badge.textContent=res.status===200&&x?.ok?'S90 · '+x.inserted+' relevés reçus · '+new Date().toLocaleTimeString():'S90 · Erreur '+res.status;},onerror:()=>{busy=false;badge.textContent='S90 · Connexion interrompue'},ontimeout:()=>{busy=false;badge.textContent='S90 · Délai dépassé'}});
 }catch(e){busy=false;badge.textContent='S90 · '+e.message}}
GM_registerMenuCommand('S90 · Définir le token',()=>{const t=prompt('Token de collecte (reste dans Tampermonkey)');if(t!==null){GM_setValue('SE90_TOKEN',t.trim());cycle()}});
GM_registerMenuCommand('S90 · Exporter le dernier relevé',()=>{if(!last)return;const url=URL.createObjectURL(new Blob([JSON.stringify(last,null,2)],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download='S90-releve.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000)});
GM_registerMenuCommand('S90 · Relancer la collecte',cycle);
window.addEventListener('pageshow',()=>{mountBadge();cycle()});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)cycle()});
cycle();setInterval(cycle,30000);
})();
