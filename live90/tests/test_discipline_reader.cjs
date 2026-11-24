'use strict';
const assert=require('node:assert/strict');
const {detect,sourceKey,matched,fingerprint,live}=require('../live90-discipline.user.js');
const players=[{side:'h',name:'Yoni'},{side:'a',name:'Doué'}];
const kind=(line)=>detect(line,players);
assert.equal(kind('L’arbitre rappelle Yoni à l’ordre.').kind,'warning');
assert.equal(kind('Yoni (Nice) commet une faute sur Doué.').name,'Yoni');
assert.equal(kind('Faute de Doué sur Yoni.').side,'a');
assert.equal(kind('Yoni obtient un coup franc.'),null);
assert.equal(kind('Faute possible de Yoni.'),null);
assert.equal(kind('Yoni est averti pour une faute.' ).kind,'reset');
assert.equal(kind('Carton jaune pour Yoni.').kind,'reset');
assert.equal(kind('Sortie de Yoni').kind,'reset');
assert.equal(kind('Yoni pourrait commettre une faute.'),null);
assert.equal(kind('Le banc réclame un jaune pour Doué.').kind,'reset');
assert.equal(kind('Yoni accroche Doué. Doué commet une faute.'),null);
assert.equal(kind('Yoni n’est pas rappelé à l’ordre.'),null);
assert.equal(kind("Yoni se fend d’un tacle sur Doué et s’en sort sans carton. Monsieur Delajod le rappelle à l’ordre.").kind,'warning');
assert.equal(sourceKey('https://www.flashscore.fr/match/football/a-x/b-y/resume/match-en-direct/?mid=CnnNjeFM'),'www.flashscore.fr#CnnNjeFM');
assert.equal(sourceKey('https://www.flashscore.fr/match/football/a-x/b-y/resume/match-en-direct/'),'');
assert.equal(sourceKey('https://www.matchendirect.fr/live-score/test.html?p=forum'),'');
const now=Date.now(),ago=n=>new Date(now-n).toISOString();
const r={sample:123,minute:62,at:ago(500),received:ago(100),score:{h:0,a:1},yellow:{h:0,a:0},red:{h:0,a:0},fouls:{h:9,a:10},sides:[{side:'h',confirmed:true}]};
const base={frame:fingerprint(r),...players[0]},w={...base,kind:'warning',minute:58,ts:now-240000,baselineFouls:7},f={...base,kind:'foul',minute:62,ts:now-2200};
assert.ok(matched([w,f],r,now));
for(const [events,row] of [
 [[w,{...f,name:'autre'}],r], [[w,{...f,side:'a'}],r], [[w,{...f,minute:58}],r],
 [[{...w,ts:f.ts},f],r], [[w,{kind:'reset',ts:now-3000},f],r], [[w,f],{...r,yellow:{h:1,a:0}}],
 [[w,f],{...r,fouls:{h:null,a:0}}], [[w,f],{...r,sides:[{side:'h',confirmed:false}]}],
 [[w,f],{...r,at:ago(100001)}], [[w,f],{...r,minute:80}], [[w,f],{...r,score:{h:1,a:1}}],
 [[w,f],{...r,at:ago(3000)}], [[{...w,minute:45},f],r], [[{...w,baselineFouls:9},f],r]
])assert.equal(matched(events,row,now),null);
assert.equal(live({...r,minute:79,at:ago(59999)},now),true);
assert.equal(live({...r,minute:79,at:ago(60000)},now),false);
console.log('Lecteur disciplinaire : attribution, chronologie et garde-fous OK');
