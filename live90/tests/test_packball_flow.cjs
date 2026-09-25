const assert=require('node:assert/strict');
const {pbJoin,pbKickoff,pbResolve}=require('../../public_html/admin/slate/live90/assets/packball.js');
const a=Array(72).fill('1'),b=Array(47).fill('1');for(const r of [a,b]){r[0]='France';r[2]='Ligue';r[3]='20:45';r[5]='Équipe A';r[8]='Équipe B'}a[24]=a[25]='10';a[26]=a[27]='12';a[30]=a[31]='4';
const A=[Array(72).fill('header'),a],B=[Array(47).fill('header'),b];const m=pbJoin(A,B,'2099-09-25','Europe/Paris')[0];assert.equal(m.kickoff,'2099-09-25T18:45:00.000Z');assert.equal(m.prematch.shots_h,12);assert.equal(m.packball.export_b.length,47);assert.equal(m.match_id,null);
assert.throws(()=>pbJoin(A,[B[0]],'2099-09-25','UTC'));
assert.throws(()=>pbJoin([A[0],a,a],B,'2099-09-25','UTC'));
assert.equal(pbKickoff('2099-01-25','20:45','Europe/Paris'),'2099-01-25T19:45:00.000Z');
const known={home:'Equipe A',away:'Equipe B',kickoff_ts:m.kickoff,packball_id:'123'};assert.equal(pbResolve(m,[known]),'123');assert.equal(pbResolve(m,[known,{...known,packball_id:'124'}]),null);assert.equal(pbResolve(m,[{...known,kickoff_ts:'2099-09-26T18:45:00Z'}]),null);
console.log('Packball workflow: 9 checks passed');
