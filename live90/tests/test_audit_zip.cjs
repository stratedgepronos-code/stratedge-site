/* Verify self-contained ZIP entries, Unicode bytes, CRC, directory and offsets. */
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const src=fs.readFileSync(path.join(__dirname,'../public/admin/slate/live90/assets/app.js'),'utf8');
const fn=src.slice(src.indexOf('function auditZip('),src.indexOf("$('#export-coverage').onclick="));
const c=vm.createContext({TextEncoder,Uint8Array,DataView,Blob});vm.runInContext(fn,c);
(async()=>{
 const entries=[['manifest.json',{total:2,export_complete:true}],['part-1.json',{home:'Équipe A',id:1}],['part-2.json',{home:'日本',id:2}]];
 const bytes=Buffer.from(await c.auditZip(entries).arrayBuffer());let off=0;
 const crc=b=>{let v=0xffffffff;for(const x of b){v^=x;for(let i=0;i<8;i++)v=(v>>>1)^((v&1)?0xedb88320:0)}return (v^0xffffffff)>>>0};
 const offsets=[];
 for(const [name,data] of entries){offsets.push(off);assert.equal(bytes.readUInt32LE(off),0x04034b50);const n=bytes.readUInt16LE(off+26),size=bytes.readUInt32LE(off+18);assert.equal(bytes.subarray(off+30,off+30+n).toString(),name);const body=bytes.subarray(off+30+n,off+30+n+size);assert.deepEqual(JSON.parse(body),data);assert.equal(bytes.readUInt32LE(off+14),crc(body));off+=30+n+size}
 for(let i=0;i<entries.length;i++){assert.equal(bytes.readUInt32LE(off),0x02014b50);assert.equal(bytes.readUInt32LE(off+42),offsets[i]);off+=46+bytes.readUInt16LE(off+28)}
 assert.equal(bytes.readUInt32LE(off),0x06054b50);assert.equal(bytes.readUInt16LE(off+8),3);console.log('ZIP audit: entries, UTF-8, CRC and offsets OK');
})().catch(e=>{console.error(e);process.exitCode=1});
