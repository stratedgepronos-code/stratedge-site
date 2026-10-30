const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const root=path.resolve(__dirname,'..');
const collector=fs.readFileSync(path.join(root,'live90-packball.user.js'),'utf8');
const c=vm.createContext({});vm.runInContext(collector.slice(collector.indexOf('const number='),collector.indexOf('function collect()')),c);
const value=code=>JSON.parse(JSON.stringify(vm.runInContext(code,c)));
assert.deepEqual(value('field("Buts attendus pour les 5 prochaines minutes")'),{group:'ind5',key:'exg5'});
assert.deepEqual(value('field("Buts attendus pour les 10 prochaines minutes")'),{group:'ind10',key:'exg10'});
assert.deepEqual(value('field("Tirs cadrés 5 dernières minutes")'),{group:'ind5',key:'sot5'});
assert.deepEqual(value('field("Tirs cadrés Temps plein")'),{group:'stats',key:'sot'});
assert.deepEqual(value('field("Cartons jaunes-rouges Temps plein")'),{group:'stats',key:'second_yellow'});
assert.equal(value('status("90+3").minute_extra'),3);
assert.equal(value('status("AET").state'),'OTHER');
assert.equal(value('status("Terminé").state'),'FT');
assert.equal(value('status("\'").state'),'OTHER');
assert.equal(value('number("1,5")'),1.5);
assert.equal(value('number("1.5,2")'),null);
const app=fs.readFileSync(path.join(root,'public/admin/slate/live90/assets/packball.js'),'utf8');
vm.runInContext(app.slice(app.indexOf('function parseCSV('),app.indexOf("if(typeof module")),c);
const csv='home;away;shots\r\n"A; United";"B ""FC""";"12,4-10,7"\r\n';c.csv=csv;
assert.deepEqual(value('parseCSV(csv)'),[['home','away','shots'],['A; United','B "FC"','12,4-10,7']]);
assert.throws(()=>vm.runInContext('parseCSV(\'a;b\\n"unfinished\')',c));
console.log('13 assertions parseurs réussies.');
assert.equal(value('quoteMeta("Plus Buts - Première ligne", "inp-7").market'),'total_goals');
assert.equal(value('quoteMeta("Plus Buts - Première ligne 1ère mi-temps", "inp-99").period'),'HT');
assert.equal(value('quoteMeta("Ligne suivante Plus - Buts marqués par les visiteurs", "inp-99").team'),'a');
assert.equal(value('quoteMeta("Plus Buts équipe 2ème mi-temps", "inp-7").verified'),false);
assert.equal(value('quoteMeta("Qui marquera le prochain but", "inp-7").verified'),false);
assert.equal(value('quoteMeta("Plus Corners - Première ligne", "inp-7").verified'),false);
assert.equal(value('quoteMeta("", "inp-7").verified'),false);
assert.equal(value('quoteMeta("Plus Buts marqués par équipe", "inp-7").verified'),false);
console.log('8 assertions marchés supplémentaires réussies.');

assert.deepEqual(value('field("Fautes Temps plein")'),{group:'stats',key:'fouls'});
assert.deepEqual(value('field("Cartons jaunes Temps plein")'),{group:'stats',key:'yellow_cards'});
assert.equal(value('quoteMeta("Plus Cartons équipe à domicile", "inp-90").market'),'team_cards');
assert.equal(value('quoteMeta("Plus Cartons équipe à domicile", "inp-90").unit'),'cards');
assert.equal(value('quoteMeta("Plus Cartons - Première ligne", "inp-90").verified'),false);
assert.equal(value('quoteMeta("Plus points de cartons domicile", "inp-90").verified'),false);
assert.equal(value('quoteMeta("Plus Buts domicile première mi-temps", "inp-90").period'),'HT');
console.log('7 assertions V4 : cartons par équipe, fautes et refus des marchés ambigus.');

// Analyst imports measure the compact UTF-8 request, preserving all content.
{
 const source=require('node:fs').readFileSync(require('node:path').join(__dirname,'../public/admin/slate/live90/assets/app.js'),'utf8');
 const code=source.slice(source.indexOf('function analystImportPayload('),source.indexOf("$('#analyst-save').onclick="));
 const scope={TextEncoder};require('node:vm').createContext(scope);require('node:vm').runInContext(code,scope);
 const bundle={schema:'stratedge.context.v4',matches:[{note:'é🟢',value:null,watch:true}],padding:''};
 const prepared=scope.analystImportPayload(bundle,'test.json');assert.equal(prepared.bundle,bundle);
 const overhead=Buffer.byteLength(JSON.stringify(prepared));bundle.padding='x'.repeat(1900000-overhead);
 assert.equal(Buffer.byteLength(JSON.stringify(scope.analystImportPayload(bundle,'boundary.json'))),1900000);
 bundle.padding+='x';assert.throws(()=>scope.analystImportPayload(bundle,'oversize.json'),/trop volumineux/);
 bundle.padding='é'.repeat(1000000);assert(JSON.stringify(bundle).length<1900000);assert.throws(()=>scope.analystImportPayload(bundle,'unicode.json'),/trop volumineux/);
 assert.throws(()=>scope.analystImportPayload(null,'null.json'),/ancien format/);
 console.log('Analyst import: exact byte boundary, UTF-8, envelope, null and lossless payload OK.');
}
