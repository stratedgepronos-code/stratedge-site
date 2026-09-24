// ==UserScript==
// @name         SEUIL90 · Packball Live Console
// @namespace    https://stratedgepronos.fr/live90
// @version      3.0.0
// @description  Collecte horodatée, fenêtres séparées, aucun pari automatique
// @match        https://packball.com/*/matches*
// @match        https://www.packball.com/*/matches*
// @grant        GM_xmlhttpRequest
// @grant        GM_getValue
// @grant        GM_setValue
// @grant        GM_registerMenuCommand
// @connect      stratedgepronos.fr
// @run-at       document-idle
// ==/UserScript==
(()=>{'use strict';
const ENDPOINT='https://stratedgepronos.fr/api/live90/ingest.php';
let busy=false,last=null;
const number=t=>{const s=String(t??'').replace(/%/g,'').replace(',','.').trim();return /^-?\d+(?:\.\d+)?$/.test(s)?Number(s):null};
const norm=t=>String(t??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
function pair(el){const s=el.querySelectorAll(':scope > div > span[class]');if(s.length<2)return null;return {h:number(s[0].textContent),a:number(s[s.length-1].textContent)}}
function field(title){
 const t=norm(title);const window=/\b10\b/.test(t)?10:/\b5\b/.test(t)?5:0;
 const group=window?'ind'+window:'stats';
 let key=null;
 if(/buts attendus/.test(t))return window?{group,key:'exg'+window}:null;
 if(/jaunes.?rouges|second.*jaune/.test(t))key='second_yellow';
 else if(/cartons rouges/.test(t))key='red_cards';
 else if(/total des tirs/.test(t))key='shots';
 else if(/tirs cadres/.test(t))key='sot';
 else if(/possession/.test(t))key='possession';
 else if(/difference de pression/.test(t))key='press_diff';
 else if(/indice de pression/.test(t))key='press_idx';
 else if(/attaques dangereuses/.test(t))key='da';
 return key?{group,key:key+(window?window:'')}:null;
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
 if(!/buts/.test(t)||/corner|carton|prochain but|les deux equipes|double chance/.test(t))return unknown;
 let side=/\bplus\b|\bover\b/.test(t)?'over':/\bmoins\b|\bunder\b/.test(t)?'under':null;
 const home=/domicile/.test(t),away=/visiteur|exterieur/.test(t);
 const half=/1(?:ere|re|er)? mi-temps|premiere mi-temps/.test(t);
 if(/mi-temps/.test(t)&&!half||home&&away)return unknown;
 if(!side&&!half&&!home&&!away&&/premiere ligne/.test(t))side=cls==='inp-7'?'over':cls==='inp-8'?'under':null;
 if(!side||(/equipe/.test(t)&&!home&&!away))return unknown;
 return {market:home||away?'team_goals':'total_goals',side,period:half?'HT':'FT',team:home?'h':away?'a':null,verified:true};
}
function collect(){
 const header=document.querySelector('section.fixtures .header, .fixtures .header');if(!header)throw Error('Tableau Packball introuvable');
 const heads=[...header.querySelectorAll('.col.live')].map(el=>el.getAttribute('title')||'');
 const meta={};header.querySelectorAll('[class*="inp-"]').forEach(el=>{const k=[...el.classList].find(c=>/^inp-\d+$/.test(c));if(k)meta[k]=el.getAttribute('title')||''});
 const rows=[];
 document.querySelectorAll('section.fixtures .row, .fixtures [class*="row fix-"]').forEach(row=>{
 const id=(String(row.className).match(/fix-(\d+)/)||[])[1];if(!id)return;
 const timeEl=row.querySelector('.col.time time');let ts=number(timeEl?.getAttribute('datetime'));if(ts!==null&&ts<1e11)ts*=1000;
 const raw=row.querySelector('.col.blin')?.textContent||'';const score=(row.querySelector('.result-f')?.textContent||'').trim().match(/^(\d+)\s*[-–]\s*(\d+)$/);
 const r={packball_id:id,home:row.querySelector('.team-home')?.textContent.trim()||'',away:row.querySelector('.team-away')?.textContent.trim()||'',league:row.querySelector('.title-league')?.getAttribute('title')||row.querySelector('.title-league')?.textContent.trim()||'',kickoff_ts:ts!==null&&Number.isFinite(new Date(ts).getTime())?new Date(ts).toISOString():null,status_raw:raw,...status(raw),score:score?{h:+score[1],a:+score[2]}:null,stats:{},ind5:{},ind10:{},quotes:[],raw_cells:[],quality_errors:[]};
 const seen=new Set();
 row.querySelectorAll('.col.live').forEach((el,i)=>{const title=heads[i]||'';const f=field(title);const value=pair(el);r.raw_cells.push({title,text:el.textContent.trim(),value});if(!f)return;const k=f.group+'.'+f.key;if(seen.has(k)){r.quality_errors.push('Colonne ambiguë : '+k);r[f.group][f.key]=null}else{seen.add(k);r[f.group][f.key]=value}});
 row.querySelectorAll('.inplay').forEach(el=>{
 const cls=[...el.classList].find(c=>/^inp-\d+$/.test(c));const title=meta[cls]||'';const t=norm(title);
 const mapping=quoteMeta(title,cls);
 r.quotes.push({...mapping,line:number(el.querySelector('.label')?.textContent),odds:number(el.querySelector('.odds-inplay')?.textContent),title,column:cls,bookmaker:'bet365',observed_at:new Date().toISOString()});
 });rows.push(r);
 });
 return {schema:2,collector_version:'3.0.0',cycle_id:crypto.randomUUID(),collected_at:new Date().toISOString(),source:'packball',page_rows:rows.length,odds_meta:meta,stat_headers:heads,rows};
}
const badge=document.createElement('div');Object.assign(badge.style,{position:'fixed',bottom:'14px',right:'14px',zIndex:'2147483647',background:'#0b1221',color:'#8eeadd',border:'1px solid #327d78',padding:'10px 16px',borderRadius:'12px',font:'12px system-ui',boxShadow:'0 6px 30px #0005'});badge.textContent='S90 · Initialisation';document.body.append(badge);
function cycle(){if(busy)return;try{
 last=collect();const token=GM_getValue('SE90_TOKEN','');if(!token){badge.textContent='S90 · Configurer le token dans Tampermonkey';return;}
 busy=true;badge.textContent='S90 · Envoi de '+last.rows.length+' matchs';
 GM_xmlhttpRequest({method:'POST',url:ENDPOINT,headers:{'Content-Type':'application/json','X-SE-Token':token},data:JSON.stringify(last),timeout:15000,onload:res=>{busy=false;let x;try{x=JSON.parse(res.responseText)}catch{}badge.textContent=res.status===200&&x?.ok?'S90 · '+x.inserted+' relevés reçus · '+new Date().toLocaleTimeString():'S90 · Erreur '+res.status;},onerror:()=>{busy=false;badge.textContent='S90 · Connexion interrompue'},ontimeout:()=>{busy=false;badge.textContent='S90 · Délai dépassé'}});
 }catch(e){busy=false;badge.textContent='S90 · '+e.message}}
GM_registerMenuCommand('S90 · Définir le token',()=>{const t=prompt('Token de collecte (reste dans Tampermonkey)');if(t!==null){GM_setValue('SE90_TOKEN',t.trim());cycle()}});
GM_registerMenuCommand('S90 · Exporter le dernier relevé',()=>{if(!last)return;const url=URL.createObjectURL(new Blob([JSON.stringify(last,null,2)],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download='S90-releve.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000)});
cycle();setInterval(cycle,45000);
})();
