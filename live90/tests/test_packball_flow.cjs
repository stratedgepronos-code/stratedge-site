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
