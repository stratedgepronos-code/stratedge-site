/* Packball preparation: no prediction, no invented fixture IDs. */
'use strict';
function pbNormalize(rows){
 rows=pbCompactExpanded(rows);
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
// Packball peut exporter un même groupe en paires ou en colonnes séparées,
// avec deux colonnes de score HT supplémentaires. Ramener les groupes connus
// au dictionnaire 31/37 sans déduire la nature des statistiques de leur valeur.
function pbCompactExpanded(rows){
 const h=(rows[0]||[]).map(v=>v.trim());
 const base=['Country','Short','League','Hour','Status','Home Team','Result Home','Result Visitor','Visitor Team','Result Home HT','Result Visitor HT'];
 if(![60,71,72].includes(h.length)||!base.every((v,i)=>h[i]===v))return rows;
 const header=h.slice(0,9),columns=[];
 for(let i=11;i<h.length;i++){
  if(h[i]==='Domicile'&&h[i+1]==='Extérieur'){
   header.push('Domicile | Extérieur');columns.push([i,i+1]);i++;
  }else if(['Odds','Global'].includes(h[i])){
   header.push(h[i]);columns.push([i]);
  }else return rows;
 }
 // Un ancien export peut avoir 72 colonnes aussi : vérifier toute la signature.
 if(!pbNewGroup([header]))return rows;
 return [header,...rows.slice(1).map(row=>{
  if(row.length!==h.length)throw Error('Ligne CSV incomplète');
  return [...row.slice(0,9),...columns.map(indices=>indices.map(i=>row[i]).join(' | '))];
 })];
}
function pbNumber(v){if(!/^\d+(?:[.,]\d+)?$/.test(String(v).trim()))throw Error('Statistique requise absente ou invalide');return Number(String(v).replace(',','.'))}
function pbKickoff(day,hour,zone){if(!/^\d{4}-\d{2}-\d{2}$/.test(day)||!/^\d{2}:\d{2}$/.test(hour)||!zone)throw Error('Date, heure ou fuseau manquant');const target=Date.parse(day+'T'+hour+':00Z');if(!Number.isFinite(target))throw Error('Date invalide');let t=target;const format=v=>Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date(v)).map(p=>[p.type,p.value]));for(let i=0;i<3;i++){const p=format(t);t+=target-Date.parse(`${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}:00Z`)}const p=format(t);if(`${p.year}-${p.month}-${p.day}`!==day||`${p.hour}:${p.minute}`!==hour)throw Error('Heure inexistante dans ce fuseau');return new Date(t).toISOString()}
// Dictionnaire établi sur les deux captures et exports du 28/09/2026.
// Les libellés CSV sont génériques : cet ordre appartient à ces deux groupes précis.
const PB31_FIELDS={10:'possession_pct',11:'sample_n',12:'ppg',13:'goals_for_ft',14:'goals_against_ft',15:'goals_for_ht',16:'goals_against_ht',17:'goals_for_2h',18:'goals_against_2h',19:'scored_over_0_5_ft_pct',20:'scored_over_1_5_ft_pct',21:'scored_over_2_5_ft_pct',22:'scored_over_0_5_ht_pct',23:'scored_over_1_5_ht_pct',24:'scored_over_0_5_2h_pct',25:'scored_over_1_5_2h_pct',26:'shots_for_ft',27:'shots_against_ft',28:'sot_for_ft',29:'sot_against_ft',30:'shots_for_ht',31:'shots_against_ht',32:'sot_for_ht',33:'sot_against_ht',34:'cards_for_ft',35:'cards_against_ft',37:'yellow_for_ft',38:'yellow_against_ft',39:'red_for_ft',40:'red_against_ft'};
const PB37_ODDS=[
 [10,'1x2','FT',null,'home',null],[11,'1x2','FT',null,'draw',null],[12,'1x2','FT',null,'away',null],
 [13,'team_goals','FT','h','over',.5],[14,'team_goals','FT','h','over',1.5],[15,'team_goals','FT','h','over',2.5],
 [16,'team_goals','FT','a','over',.5],[17,'team_goals','FT','a','over',1.5],[18,'team_goals','FT','a','over',2.5],
 [19,'team_goals','FT','a','under',.5],[20,'team_goals','FT','a','under',1.5],[21,'team_goals','FT','a','under',2.5],
 [22,'total_goals','FT',null,'over',2.5],[23,'total_goals','FT',null,'under',2.5],
 [24,'total_goals','HT',null,'over',.5],[25,'total_goals','HT',null,'over',1.5],
 [26,'total_goals','2H',null,'over',.5],[27,'total_goals','2H',null,'over',1.5],
 [28,'total_goals','HT',null,'under',.5],[29,'total_goals','HT',null,'under',1.5],
 [30,'total_goals','2H',null,'under',.5],[31,'total_goals','2H',null,'under',1.5],
 [32,'team_goals','FT','h','under',.5],[33,'team_goals','FT','h','under',1.5],[34,'team_goals','FT','h','under',2.5]
];
function pbNewGroup(table){
 const h=(table[0]||[]).map(v=>v.trim()),base=['Country','Short','League','Hour','Status','Home Team','Result Home','Result Visitor','Visitor Team'];
 if(!base.every((v,i)=>h[i]===v))return null;
 if(h.length===40&&h.slice(9).every((v,i)=>i+10===36?v==='Global':i+10===32?['Global','Domicile | Extérieur'].includes(v):v==='Domicile | Extérieur'))return 'A';
 if(h.length===46&&h.slice(9,34).every(v=>v==='Odds')&&h.slice(34).every(v=>v==='Domicile | Extérieur'))return 'B';
 return null;
}
function pbOrderTables(tables){
 const t=tables.map(pbNormalize);
 if(t.length===2&&t.some(x=>[40,46].includes(x[0]?.length)))return t.sort((a,b)=>(pbNewGroup(a)==='A'?0:1)-(pbNewGroup(b)==='A'?0:1));
 return t.sort((a,b)=>b[0].length-a[0].length);
}
function pbJoinNew(a,b,day,zone){
 if(JSON.stringify(a)===JSON.stringify(b))throw Error('Les deux fichiers sont identiques. Exporte un fichier GPT et un fichier GPT - 2.');
 if(pbNewGroup(a)==='B'&&pbNewGroup(b)==='A')[a,b]=[b,a];
 if(pbNewGroup(a)!=='A'||pbNewGroup(b)!=='B')throw Error('Les nouveaux groupes attendus sont GPT (40 colonnes) et GPT - 2 (46 colonnes), avec les choix convenus.');
 const key=r=>JSON.stringify([r[0],r[2],r[3],r[5],r[8]]);
 const index=(rows,width)=>{const map=new Map();for(const r of rows.slice(1)){if(r.length!==width)throw Error('Ligne CSV incomplète');const k=key(r);if(map.has(k))throw Error('Match dupliqué : '+r[5]);map.set(k,r);}return map;};
 const aa=index(a,40),bb=index(b,46);
 if(!aa.size||aa.size>1000||aa.size!==bb.size)throw Error('Les deux exports doivent contenir les mêmes matchs (1 à 1000)');
 return [...aa].map(([k,r])=>{
  const s=bb.get(k);if(!s)throw Error('Match absent du deuxième export : '+r[5]);
  const issues=[],teams={h:{},a:{}},global={};
  const scalar=(v,label,max=200)=>{const z=String(v??'').trim();if(!z||z==='-')return null;const n=/^\d+(?:[.,]\d+)?$/.test(z)?Number(z.replace(',','.')):NaN;if(!Number.isFinite(n)||n>max){issues.push(label+' : valeur invalide, conservée inconnue');return null;}return n;};
  const pair=(v,label,max=200)=>{if(!String(v).trim()||String(v).trim()==='-')return {h:null,a:null};const z=String(v).split('|');if(z.length!==2){issues.push(label+' : paire domicile/extérieur manquante');return {h:null,a:null};}return {h:scalar(z[0],label,max),a:scalar(z[1],label,max)};};
  for(const [col,name] of Object.entries(PB31_FIELDS)){
   let value;
   if(+col===32&&a[0][31].trim()==='Global'){
    global.sot_ht_unallocated=scalar(r[31],'GPT colonne 32');value={h:null,a:null};
    issues.push('GPT colonne 32 en Global : cadrés produits en première mi-temps par équipe inconnus ; aucune répartition inventée');
   }else value=pair(r[+col-1],'GPT colonne '+col,name.endsWith('_pct')?100:200);
   for(const side of ['h','a'])teams[side][name]=value[side];
  }
  const prematch={};for(const [short,name] of Object.entries({n:'sample_n',gf:'goals_for_ft',ga:'goals_against_ft',shots:'shots_for_ft',sot:'sot_for_ft'}))for(const side of ['h','a'])prematch[short+'_'+side]=teams[side][name];
  for(const side of ['h','a']){
   if(!Number.isInteger(prematch['n_'+side])||prematch['n_'+side]<1){prematch['n_'+side]=null;issues.push('Échantillon incomplet pour '+side);}
   if(prematch['shots_'+side]!==null&&prematch['sot_'+side]!==null&&prematch['sot_'+side]>prematch['shots_'+side]){prematch['sot_'+side]=null;teams[side].sot_for_ft=null;issues.push('Cadrés supérieurs aux tirs pour '+side+' : cadrés laissés inconnus');}
  }
  if(Object.values(prematch).some(v=>v===null))issues.push('Profil principal incomplet ; match conservé');
  const intervals=['0-15','16-30','31-45','46-60','61-75','76-90'];
  for(const side of ['h','a'])teams[side].goal_intervals={};
  intervals.forEach((interval,i)=>{const scored=pair(s[34+i],'GPT - 2 colonne '+(35+i)),conceded=pair(s[45-i],'GPT - 2 colonne '+(46-i));for(const side of ['h','a'])teams[side].goal_intervals[interval]={scored:scored[side],conceded:conceded[side]};});
  const odds=PB37_ODDS.map(([column,market,period,team,side,line])=>{const n=scalar(s[column-1],'GPT - 2 cote colonne '+column,10000);return {column,market,period,team,side,line,odds:n!==null&&n>1?n:null};});
  const stamp=r[3].trim().match(/^(\d{2})-(\d{2})-(\d{4}) (\d{2}:\d{2})$/);
  const kickoff=pbKickoff(stamp?`${stamp[3]}-${stamp[2]}-${stamp[1]}`:day,stamp?stamp[4]:r[3],zone);
  const cards_avg=scalar(r[35],'Cartes moyennes Ligue');
  if(cards_avg===null)issues.push('Moyenne de cartons de la compétition indisponible');
  const state=s[4]||r[4];
  if([r[4],s[4]].some(v=>v&&v!=='NS'))issues.push('Au moins un export porte un statut autre que NS : vérifier le statut et ne pas présenter ses cotes comme un relevé avant coup d’envoi');
  return {match_id:null,home:r[5],away:r[8],league:r[2],state,kickoff,prematch,issues,
   packball:{layout:'packball.prematch31_37.v1',sample:{requested_matches:10,venue_scope:'all',competitions_scope:'all',previous_seasons_included:true},teams,league:{cards_avg},global,odds,export_states:{a:r[4],b:s[4]},data_issues:issues,export_a:r,export_b:s}};
 });
}
function pbJoin(a,b,day,zone){a=pbNormalize(a);b=pbNormalize(b);
 if([a[0]?.length,b[0]?.length].some(n=>[40,46].includes(n)))return pbJoinNew(a,b,day,zone);
 if(JSON.stringify(a)===JSON.stringify(b))throw Error('Les deux fichiers sont identiques, même si leurs noms diffèrent. Garde un exemplaire et exporte le deuxième groupe de filtres Packball, puis sélectionne les deux exports complémentaires.');
 if(a[0]?.length===b[0]?.length&&[72,47].includes(a[0]?.length))throw Error('Les deux fichiers correspondent au même groupe de filtres Packball. Il manque '+(a[0].length===72?'le deuxième':'le premier')+' groupe. Exporte-le depuis Packball pour compléter le dossier.');
 if(a[0]?.length!==72||b[0]?.length!==47)throw Error('Disposition des exports non reconnue ('+(a[0]?.length||0)+' et '+(b[0]?.length||0)+' colonnes après lecture). Sélectionne un export de chacun des deux groupes de filtres Packball.');const key=r=>JSON.stringify([r[0],r[2],r[3],r[5],r[8]]);const read=(rows,n)=>{const out=new Map();for(const r of rows.slice(1)){if(r.length!==n)throw Error('Nombre de colonnes incohérent');const k=key(r);if(out.has(k))throw Error('Match dupliqué : '+r[5]);out.set(k,r)}return out};const aa=read(a,72),bb=read(b,47);if(!aa.size||aa.size>1000||aa.size!==bb.size)throw Error('Les deux exports doivent contenir les mêmes matchs (1 à 1000)');return [...aa].map(([k,r])=>{if(!bb.has(k))throw Error('Match absent du deuxième export : '+r[5]);const fields={n_h:25,n_a:26,gf_h:53,gf_a:54,ga_h:55,ga_a:56,shots_h:27,shots_a:28,sot_h:31,sot_a:32};const prematch=Object.fromEntries(Object.entries(fields).map(([f,i])=>[f,String(r[i-1]).trim()===''?null:pbNumber(r[i-1])]));const issues=[];for(const side of ['h','a'])if(!Number.isInteger(prematch['n_'+side])||prematch['n_'+side]<1||prematch['shots_'+side]<=0||prematch['sot_'+side]>prematch['shots_'+side])issues.push('Échantillon ou tirs incomplets pour '+side);if(Object.values(prematch).some(v=>v===null||v>100))issues.push('Profil à compléter avant activation');const stamp=r[3].trim().match(/^(\d{2})-(\d{2})-(\d{4}) (\d{2}:\d{2})$/);const kickoff=pbKickoff(stamp?`${stamp[3]}-${stamp[2]}-${stamp[1]}`:day,stamp?stamp[4]:r[3],zone);return {match_id:null,home:r[5],away:r[8],league:r[2],state:r[4],kickoff,prematch,issues,packball:{export_a:r,export_b:bb.get(k)}}})}
function pbResolve(m,known){const norm=s=>String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase();const candidates=known.filter(r=>norm(r.home)===norm(m.home)&&norm(r.away)===norm(m.away)&&(()=>{let t=r.prematch?.kickoff||r.kickoff_ts;if(typeof t==='number')t*=t<1e12?1000:1;return Number.isFinite(new Date(t).getTime())&&Math.abs(new Date(t).getTime()-Date.parse(m.kickoff))<60000})());const ids=[...new Set(candidates.map(r=>String(r.packball_id)))];return ids.length===1&&/^\d{1,20}$/.test(ids[0])?ids[0]:null}


function parseCSV(text){text=text.replace(/^\uFEFF/,'');const line=text.split(/\r?\n/)[0];const delimiter=(line.match(/;/g)||[]).length>(line.match(/,/g)||[]).length?';':',';const rows=[];let row=[],cell='',quoted=false;for(let i=0;i<text.length;i++){const c=text[i];if(c==='"'){if(quoted&&text[i+1]==='"'){cell+='"';i++}else quoted=!quoted}else if(c===delimiter&&!quoted){row.push(cell);cell=''}else if((c==='\n'||c==='\r')&&!quoted){if(c==='\r'&&text[i+1]==='\n')i++;row.push(cell);if(row.some(v=>v.trim()))rows.push(row);row=[];cell=''}else cell+=c}if(quoted)throw Error('CSV : guillemet non fermé');row.push(cell);if(row.some(v=>v.trim()))rows.push(row);return rows}
if(typeof module!=='undefined')module.exports={pbJoin,pbKickoff,pbResolve,pbNormalize,pbOrderTables,pbNewGroup,parseCSV};
