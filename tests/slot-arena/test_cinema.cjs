'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const app=fs.readFileSync(require('node:path').join(__dirname,'../../public_html/tournoi-slots/app.php'),'utf8');
const source=app.slice(app.indexOf('// Finale CROWN v2:'),app.indexOf('\nlet liveReady='));
function setup({reduce=false,canvas=true,width=1366,height=936}={}){
 let now=0,next=1,draws=0;const timers=new Map(),frames=new Map(),listeners=new Map(),nodes={};
 const finite=(...args)=>{for(const a of args)if(typeof a==='number')assert(Number.isFinite(a),'finite canvas coordinates');draws++};
 const ctx2d=new Proxy({createRadialGradient:()=>({addColorStop(){}}),createLinearGradient:()=>({addColorStop(){}})},{get:(o,k)=>k in o?o[k]:finite,set:(o,k,v)=>(o[k]=v,true)});
 function node(id){return nodes[id]??={textContent:'',innerHTML:'',dataset:{},classList:{add(){},remove(){},toggle(){}},style:{setProperty(){}},children:[],clientWidth:width,clientHeight:height,open:false,disabled:false,addEventListener(){},setAttribute(){},replaceChildren(){this.children=[]},append(n){this.children.push(n)},showModal(){this.open=true},close(){this.open=false},focus(){},getContext(){return canvas?ctx2d:null}}}
 const state={title:'Fixture',players:['Alex','Marc'],slots:[1,2],rounds:[]};
 const sandbox={console,state,$:node,document:{activeElement:node('focused'),body:{style:{overflow:'auto'}},createElement:()=>node('gold')},window:{innerWidth:width,innerHeight:height,devicePixelRatio:2,matchMedia:()=>({matches:reduce}),addEventListener:(k,fn)=>listeners.set(k,fn),removeEventListener:(k)=>listeners.delete(k)},performance:{now:()=>now},setTimeout:(fn,t)=>{const id=next++;timers.set(id,{fn,t:now+t});return id},clearTimeout:id=>timers.delete(id),requestAnimationFrame:fn=>{const id=next++;frames.set(id,fn);return id},cancelAnimationFrame:id=>frames.delete(id),preparedAudio:null,name:id=>id==='a'?'<Winner & Co>':'Opponent',owner:()=>0,number:s=>s===''?null:Number(s),esc:s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;')};
 vm.createContext(sandbox);vm.runInContext(source,sandbox);
 const run=js=>vm.runInContext(js,sandbox);
 function tick(t){now=t;for(const[id,v]of[...timers])if(v.t<=t){timers.delete(id);v.fn()}const current=[...frames];frames.clear();for(const[,fn]of current)fn(t)}
 function champion(){state.rounds=[[{players:['a','b'],scores:['500','50'],winner:'a',bye:false}]]}
 return{run,tick,champion,node,timers,frames,listeners,state,get draws(){return draws}};
}
for(const size of[{width:1366,height:936},{width:390,height:844},{width:844,height:390}]){
 const t=setup(size);assert.equal(t.run('startCinema()'),false);assert.equal(t.timers.size,0);t.champion();assert(t.run('startCinema()'));assert(t.node('victory').open);assert.equal(t.node('cinemaName').children[0].textContent,'<Winner & Co>');
 for(const ms of[80,2800,5700,6100,8000,9000,11000,12000,14300,16000,22000,26000])t.tick(ms);
 assert.equal(t.node('victory').dataset.phase,'glory');assert.equal(t.node('victory').dataset.stats,'true');assert(t.draws>1000);assert.equal(t.frames.size,0);assert.equal(t.listeners.size,0);assert(t.node('cinemaMute').disabled);
 t.run('stopCinema()');assert(!t.node('victory').open);assert.equal(t.timers.size,0);t.run('startCinema()');t.tick(26100);t.run('stopCinema()');assert.equal(t.frames.size,0);assert.equal(t.timers.size,0);
}
const reduced=setup({reduce:true});reduced.champion();reduced.run('startCinema()');assert.equal(reduced.frames.size,0);assert.equal(reduced.timers.size,0);assert.equal(reduced.node('victory').dataset.phase,'glory');reduced.run('stopCinema()');
const fallback=setup({canvas:false});fallback.champion();fallback.run('startCinema()');fallback.tick(26000);assert.equal(fallback.node('victory').dataset.phase,'glory');fallback.run('stopCinema()');
console.log('CROWN: champion gate, exact result text, desktop/mobile/landscape rendering, every scene, cleanup/replay, reduced motion and canvas fallback OK');
