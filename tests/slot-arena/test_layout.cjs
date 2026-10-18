'use strict';
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const page=fs.readFileSync(path.join(__dirname,'../../public_html/tournoi-slots/app.php'),'utf8');const script=page.split('<script>')[1].split('</script>')[0];
const ctx={assert,console,document:{getElementById(){}},location:{pathname:'/test'},crypto:require('node:crypto').webcrypto};vm.createContext(ctx);vm.runInContext(script.slice(script.indexOf('const $='),script.indexOf("let loadWarning='';")),ctx);
vm.runInContext(`
assert.deepEqual(bracketLayout(6),[[3,3],[2,2],[1,1],[1,0]]);
for(let n=1;n<=64;n++){
 state=fresh();state.slots=Array.from({length:n*2},(_,i)=>({id:'s'+i,name:'Slot '+i,owner:i<n?0:1}));state.rounds=seedRounds(state.slots.map(s=>s.id));validState(state);const layout=bracketLayout(n);assert.equal(state.rounds[0].length,n);
 if(n>1)assert(Math.abs(layout[0][0]-layout[0][1])<=1);
 let games=0;
 for(let r=0;r<state.rounds.length;r++)for(let i=0;i<state.rounds[r].length;i++){
  const m=state.rounds[r][i];if(m.bye)continue;assert(m.players.every(Boolean));
  if(r>0&&r<state.rounds.length-1){const [a,b]=matchSources(state.rounds,r,i,true),left=i<layout[r][0];assert.equal(a<layout[r-1][0],left);if(b!==null)assert.equal(b<layout[r-1][0],left)}
  m.scores=['20','10'];m.winner=m.players[0];games++;sync();validState(state);
 }
 assert.equal(games,n*2-1);assert(state.rounds.at(-1)[0].winner);
 state.rounds[0][0].winner=null;sync();assert.equal(state.rounds.at(-1)[0].winner,null);validState(state);
}
const first=Array.from({length:6},(_,i)=>({players:['a'+i,'b'+i],scores:i===0?['15','8']:['',''],winner:i===0?'a0':null,bye:false}));
const old={bracket:undefined,rounds:[first,Array.from({length:3},()=>makeMatch()),Array.from({length:2},()=>makeMatch()),[makeMatch()]]};const before=JSON.stringify(first);balanceState(old);assert.deepEqual(old.rounds.map(r=>r.length),[6,4,2,1]);assert.equal(JSON.stringify(old.rounds[0]),before);assert.equal(old.rounds[1][0].players[0],'a0');
console.log('Slot Arena: balanced halves for all 64 formats, 3+3 display, no cross-half qualification, exact game counts, corrections and first-round migration OK');
`,ctx);
