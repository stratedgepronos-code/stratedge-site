/* Packball preparation: no prediction, no invented fixture IDs. */
'use strict';
function pbNormalize(rows){
 const h=rows[0]||[];
 if(![49,39].includes(h.length))return rows;
 const paired=h.map(v=>v.trim()==='Domicile | Extérieur');
 const expand=(row,header=false)=>{
  if(row.length!==h.length)throw Error('Ligne CSV incomplète');
  const out=[];
  row.forEach((v,i)=>{
   if(i===9)out.push(header?'Result Home HT':'',header?'Result Visitor HT':'');
   if(paired[i]){
    if(header)out.push('Domicile','Extérieur');
    else if(!v.trim())out.push('','');
    else {const pair=v.split('|').map(x=>x.trim());if(pair.length!==2)throw Error('Paire domicile/extérieur invalide');out.push(...pair)}
   }else out.push(v);
  });return out;
 };
 const result=rows.map((row,i)=>expand(row,i===0));
 if(result[0].length!==(h.length===49?72:47))throw Error('Disposition Packball non reconnue');
 return result;
}
function pbNumber(v){if(!/^\d+(?:[.,]\d+)?$/.test(String(v).trim()))throw Error('Statistique requise absente ou invalide');return Number(String(v).replace(',','.'))}
function pbKickoff(day,hour,zone){if(!/^\d{4}-\d{2}-\d{2}$/.test(day)||!/^\d{2}:\d{2}$/.test(hour)||!zone)throw Error('Date, heure ou fuseau manquant');const target=Date.parse(day+'T'+hour+':00Z');if(!Number.isFinite(target))throw Error('Date invalide');let t=target;const format=v=>Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date(v)).map(p=>[p.type,p.value]));for(let i=0;i<3;i++){const p=format(t);t+=target-Date.parse(`${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}:00Z`)}const p=format(t);if(`${p.year}-${p.month}-${p.day}`!==day||`${p.hour}:${p.minute}`!==hour)throw Error('Heure inexistante dans ce fuseau');return new Date(t).toISOString()}
function pbJoin(a,b,day,zone){a=pbNormalize(a);b=pbNormalize(b);
 if(JSON.stringify(a)===JSON.stringify(b))throw Error('Les deux fichiers sont identiques, même si leurs noms diffèrent. Garde un exemplaire et exporte le deuxième groupe de filtres Packball, puis sélectionne les deux exports complémentaires.');
 if(a[0]?.length===b[0]?.length&&[72,47].includes(a[0]?.length))throw Error('Les deux fichiers correspondent au même groupe de filtres Packball. Il manque '+(a[0].length===72?'le deuxième':'le premier')+' groupe. Exporte-le depuis Packball pour compléter le dossier.');
 if(a[0]?.length!==72||b[0]?.length!==47)throw Error('Disposition des exports non reconnue ('+(a[0]?.length||0)+' et '+(b[0]?.length||0)+' colonnes après lecture). Sélectionne un export de chacun des deux groupes de filtres Packball.');const key=r=>JSON.stringify([r[0],r[2],r[3],r[5],r[8]]);const read=(rows,n)=>{const out=new Map();for(const r of rows.slice(1)){if(r.length!==n)throw Error('Nombre de colonnes incohérent');const k=key(r);if(out.has(k))throw Error('Match dupliqué : '+r[5]);out.set(k,r)}return out};const aa=read(a,72),bb=read(b,47);if(!aa.size||aa.size>1000||aa.size!==bb.size)throw Error('Les deux exports doivent contenir les mêmes matchs (1 à 1000)');return [...aa].map(([k,r])=>{if(!bb.has(k))throw Error('Match absent du deuxième export : '+r[5]);const fields={n_h:25,n_a:26,gf_h:53,gf_a:54,ga_h:55,ga_a:56,shots_h:27,shots_a:28,sot_h:31,sot_a:32};const prematch=Object.fromEntries(Object.entries(fields).map(([f,i])=>[f,String(r[i-1]).trim()===''?null:pbNumber(r[i-1])]));const issues=[];for(const side of ['h','a'])if(!Number.isInteger(prematch['n_'+side])||prematch['n_'+side]<1||prematch['shots_'+side]<=0||prematch['sot_'+side]>prematch['shots_'+side])issues.push('Échantillon ou tirs incomplets pour '+side);if(Object.values(prematch).some(v=>v===null||v>100))issues.push('Profil à compléter avant activation');const stamp=r[3].trim().match(/^(\d{2})-(\d{2})-(\d{4}) (\d{2}:\d{2})$/);const kickoff=pbKickoff(stamp?`${stamp[3]}-${stamp[2]}-${stamp[1]}`:day,stamp?stamp[4]:r[3],zone);return {match_id:null,home:r[5],away:r[8],league:r[2],state:r[4],kickoff,prematch,issues,packball:{export_a:r,export_b:bb.get(k)}}})}
function pbResolve(m,known){const norm=s=>String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase();const candidates=known.filter(r=>norm(r.home)===norm(m.home)&&norm(r.away)===norm(m.away)&&(()=>{let t=r.prematch?.kickoff||r.kickoff_ts;if(typeof t==='number')t*=t<1e12?1000:1;return Number.isFinite(new Date(t).getTime())&&Math.abs(new Date(t).getTime()-Date.parse(m.kickoff))<60000})());const ids=[...new Set(candidates.map(r=>String(r.packball_id)))];return ids.length===1&&/^\d{1,20}$/.test(ids[0])?ids[0]:null}


function parseCSV(text){text=text.replace(/^\uFEFF/,'');const line=text.split(/\r?\n/)[0];const delimiter=(line.match(/;/g)||[]).length>(line.match(/,/g)||[]).length?';':',';const rows=[];let row=[],cell='',quoted=false;for(let i=0;i<text.length;i++){const c=text[i];if(c==='"'){if(quoted&&text[i+1]==='"'){cell+='"';i++}else quoted=!quoted}else if(c===delimiter&&!quoted){row.push(cell);cell=''}else if((c==='\n'||c==='\r')&&!quoted){if(c==='\r'&&text[i+1]==='\n')i++;row.push(cell);if(row.some(v=>v.trim()))rows.push(row);row=[];cell=''}else cell+=c}if(quoted)throw Error('CSV : guillemet non fermé');row.push(cell);if(row.some(v=>v.trim()))rows.push(row);return rows}
if(typeof module!=='undefined')module.exports={pbJoin,pbKickoff,pbResolve,pbNormalize,parseCSV};
