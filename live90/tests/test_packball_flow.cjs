const assert=require('node:assert/strict');
const {pbJoin,pbKickoff,pbResolve}=require('../../public_html/admin/slate/live90/assets/packball.js');
const a=Array(72).fill('1'),b=Array(47).fill('1');for(const r of [a,b]){r[0]='France';r[2]='Ligue';r[3]='20:45';r[5]='Équipe A';r[8]='Équipe B'}a[24]=a[25]='10';a[26]=a[27]='12';a[30]=a[31]='4';
const A=[Array(72).fill('header'),a],B=[Array(47).fill('header'),b];const m=pbJoin(A,B,'2099-09-25','Europe/Paris')[0];assert.equal(m.kickoff,'2099-09-25T18:45:00.000Z');assert.equal(m.prematch.shots_h,12);assert.equal(m.packball.export_b.length,47);assert.equal(m.match_id,null);
assert.throws(()=>pbJoin(A,[B[0]],'2099-09-25','UTC'));
assert.throws(()=>pbJoin([A[0],a,a],B,'2099-09-25','UTC'));
assert.equal(pbKickoff('2099-01-25','20:45','Europe/Paris'),'2099-01-25T19:45:00.000Z');
const known={home:'Equipe A',away:'Equipe B',kickoff_ts:m.kickoff,packball_id:'123'};assert.equal(pbResolve(m,[known]),'123');assert.equal(pbResolve(m,[known,{...known,packball_id:'124'}]),null);assert.equal(pbResolve(m,[{...known,kickoff_ts:'2099-09-26T18:45:00Z'}]),null);
console.log('Packball workflow: 9 checks passed');
const {pbNormalize}=require('../../public_html/admin/slate/live90/assets/packball.js');
// Compact Packball: nine administrative fields, 13 odds and paired statistics.
const pairedA=new Set([23,24,25,26,27,28,29,30,31,38,39,40,41,42,43,44,45,46,47,48,49]);
const compactHeader=Array.from({length:49},(_,i)=>pairedA.has(i+1)?'Domicile | Extérieur':'Global');
const compact=compactHeader.map(h=>h.includes('|')?'10 | 10':'1');compact[3]='26-09-2099 20:45';
const normalized=pbNormalize([compactHeader,compact]);assert.equal(normalized[0].length,72);assert.equal(normalized[1][24],'10');assert.equal(normalized[1][9],'');assert.equal(normalized[1][3],'26-09-2099 20:45');
assert.throws(()=>pbNormalize([compactHeader,compact.map((v,i)=>i===22?'10':v)]));
console.log('Compact export: 5 additional checks passed');

// Le nom du fichier ne prouve pas que les deux groupes ont été exportés.
assert.throws(()=>pbJoin(A,A,'2099-09-25','Europe/Paris'),/deux fichiers sont identiques/);
const secondA=[A[0],[...a]];secondA[1][11]='2.10';
assert.throws(()=>pbJoin(A,secondA,'2099-09-25','Europe/Paris'),/même groupe.*deuxième/);
const secondB=[B[0],[...b]];secondB[1][11]='2.20';
assert.throws(()=>pbJoin(B,secondB,'2099-09-25','Europe/Paris'),/même groupe.*premier/);
console.log('3 contrôles : doublon exact et groupe complémentaire manquant.');

// Groupes 31/37 : positions validées, fichiers et lignes dans un ordre différent.
const {pbOrderTables,pbNewGroup}=require('../../public_html/admin/slate/live90/assets/packball.js');
const base=['Country','Short','League','Hour','Status','Home Team','Result Home','Result Visitor','Visitor Team'];
const ha=[...base,...Array(31).fill('Domicile | Extérieur')];ha[31]=ha[35]='Global';
const hb=[...base,...Array(25).fill('Odds'),...Array(12).fill('Domicile | Extérieur')];
const ra=[...['France','FRA','Test','28-09-2099 01:00','NS','Alpha','','','Beta'],...Array(31).fill('1 | 2')];
ra[10]='10 | 8';ra[25]='12 | 14';ra[27]='4 | 5';ra[31]='4.3';ra[35]='';
const rb=[...ra.slice(0,9),...Array.from({length:25},(_,i)=>(1.1+i/10).toFixed(2)),...Array.from({length:12},(_,i)=>`${i/10} | ${(i+1)/10}`)];
rb[4]='INPLAY_1ST_HALF';rb[12]='';
const ra2=[...ra],rb2=[...rb];ra2[5]=rb2[5]='Gamma';ra2[4]=rb2[4]='CANCELLED';
const na=[ha,ra,ra2],nb=[hb,rb2,rb];const ordered=pbOrderTables([nb,na]);
assert.equal(pbNewGroup(ordered[0]),'A');assert.equal(pbNewGroup(ordered[1]),'B');
const joined=pbJoin(...ordered,'2099-09-28','Europe/Paris');assert.equal(joined.length,2);
const j=joined[0];assert.equal(j.home,'Alpha');assert.equal(j.kickoff,'2099-09-27T23:00:00.000Z');
assert.equal(j.prematch.n_a,8);assert.equal(j.prematch.shots_a,14);assert.equal(j.prematch.sot_h,4);
assert.equal(j.packball.global.sot_ht_unallocated,4.3);assert.equal(j.packball.teams.h.sot_for_ht,null);assert.equal(j.packball.league.cards_avg,null);
assert.deepEqual(j.packball.teams.h.goal_intervals['0-15'],{scored:0,conceded:1.1});
assert.deepEqual(j.packball.teams.a.goal_intervals['76-90'],{scored:.6,conceded:.7});
const q=c=>j.packball.odds.find(x=>x.column===c);
assert.equal(q(13).odds,null);assert.equal(q(19).team,'a');assert.equal(q(19).side,'under');assert.equal(q(32).team,'h');assert.equal(q(32).side,'under');
assert.equal(q(24).period,'HT');assert.equal(q(26).period,'2H');assert.equal(q(22).period,'FT');assert.equal(q(24).team,null);
assert.equal(j.state,'INPLAY_1ST_HALF');assert.equal(joined[1].state,'CANCELLED');assert(j.issues.length>=3);
const fixed=JSON.parse(JSON.stringify(na));fixed[0][31]='Domicile | Extérieur';fixed[1][31]=fixed[2][31]='1.2 | 2.3';
assert.equal(pbJoin(fixed,nb,'2099-09-28','Europe/Paris')[0].packball.teams.a.sot_for_ht,2.3);
fixed[1][10]='';assert.equal(pbJoin(fixed,nb,'2099-09-28','Europe/Paris')[0].prematch.n_h,null);
assert.throws(()=>pbJoin(na,[hb,rb],'2099-09-28','Europe/Paris'),/mêmes matchs/);
assert.throws(()=>pbJoin([ha,ra,ra],nb,'2099-09-28','Europe/Paris'),/dupliqué/);
const wrong=[...hb];wrong[9]='Global';assert.equal(pbNewGroup([wrong,rb]),null);
assert.throws(()=>pbJoin(na,[wrong,rb2,rb],'2099-09-28','Europe/Paris'),/groupes attendus/);
console.log('Groupes 31/37 : équipes, périodes, tranches inversées, Global, inconnues et statuts validés.');

// Régression réelle du 29/09 : 71/60 colonnes, paires séparées et scores HT.
const fs=require('node:fs'),path=require('node:path');
const {parseCSV}=require('../../public_html/admin/slate/live90/assets/packball.js');
const expandedA=parseCSV(fs.readFileSync(path.join(__dirname,'fixtures/packball-20260929-a.csv'),'utf8'));
const expandedB=parseCSV(fs.readFileSync(path.join(__dirname,'fixtures/packball-20260929-b.csv'),'utf8'));
assert.equal(expandedA[0].length,71);assert.equal(expandedB[0].length,60);
const orderedExpanded=pbOrderTables([expandedB,expandedA]);
assert.deepEqual(orderedExpanded.map(t=>t[0].length),[40,46]);
assert.deepEqual(pbNormalize(orderedExpanded[0]),orderedExpanded[0]);
const actual=pbJoin(...orderedExpanded,'2026-09-29','Europe/Paris');
assert.equal(actual.length,16);assert.equal(new Set(actual.map(m=>m.home+' / '+m.away)).size,16);
assert.deepEqual(pbJoin(expandedB,expandedA,'2026-09-29','Europe/Paris'),actual);
assert.deepEqual(actual[0].prematch,{n_h:10,n_a:10,gf_h:1.4,gf_a:2.1,ga_h:1,ga_a:1.2,shots_h:15,shots_a:14.9,sot_h:5.1,sot_a:6.1});
assert.equal(actual[0].kickoff,'2026-09-28T22:15:00.000Z');
assert.equal(actual[0].packball.global.sot_ht_unallocated,4.6);
assert.equal(actual[0].packball.teams.h.sot_for_ht,null);
assert.equal(actual[0].packball.league.cards_avg,4.4);
assert.equal(actual[0].packball.teams.a.yellow_for_ft,1.9);
assert.deepEqual(actual[0].packball.teams.h.goal_intervals['0-15'],{scored:0,conceded:.3});
assert.equal(actual[0].packball.odds.find(q=>q.column===13).odds,null);
const upcoming=actual.find(m=>m.home==='Deportivo Pereira');
assert.equal(upcoming.state,'NS');assert.equal(upcoming.kickoff,'2026-09-29T20:30:00.000Z');
assert.equal(upcoming.prematch.shots_h,8.7);assert.equal(upcoming.prematch.sot_a,4.5);
assert.equal(upcoming.packball.odds.find(q=>q.column===13).odds,1.39);
assert.equal(upcoming.packball.odds.find(q=>q.column===24).period,'HT');
assert.equal(upcoming.packball.odds.find(q=>q.column===26).period,'2H');
// Chaque paire doit conserver le côté et la position, même si un côté manque.
for(let i=1;i<expandedA.length;i++){
 const raw=expandedA[i],m=actual[i-1];
 assert.equal(m.prematch.shots_h,Number(raw[43]));assert.equal(m.prematch.shots_a,Number(raw[44]));
 assert.equal(m.prematch.sot_h,Number(raw[47]));assert.equal(m.prematch.sot_a,Number(raw[48]));
}
const partial=structuredClone(expandedA);partial[1][43]='';
assert.equal(pbJoin(partial,expandedB,'2026-09-29','Europe/Paris')[0].prematch.shots_h,null);
assert.equal(pbJoin(partial,expandedB,'2026-09-29','Europe/Paris')[0].prematch.shots_a,14.9);
const pairedHT=structuredClone(expandedA);pairedHT[0].splice(55,1,'Domicile','Extérieur');
for(const row of pairedHT.slice(1))row.splice(55,1,'2.1','3.2');
assert.equal(pairedHT[0].length,72);
assert.equal(pbJoin(pairedHT,expandedB,'2026-09-29','Europe/Paris')[0].packball.teams.a.sot_for_ht,3.2);
const truncated=structuredClone(expandedA);truncated[1].pop();assert.throws(()=>pbNormalize(truncated),/incomplète/);
const reversed=structuredClone(expandedA);[reversed[0][11],reversed[0][12]]=[reversed[0][12],reversed[0][11]];
assert.throws(()=>pbJoin(reversed,expandedB,'2026-09-29','Europe/Paris'),/groupes attendus/);
console.log('Exports séparés 71/60 et 72/60 : 16 matchs, valeurs, cotes, périodes, Global et champs manquants validés.');
