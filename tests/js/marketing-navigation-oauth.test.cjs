const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const script=fs.readFileSync(path.join(__dirname,'../../public/marketing-studio/navigation-preview.js'),'utf8');

function render(hash,page='channels'){
 const nodes=new Map();let channels=0;
 const boot={page,channels:{accounts:[]},urls:{}};
 const get=id=>{if(!nodes.has(id))nodes.set(id,{textContent:id==='navigation-boot'?JSON.stringify(boot):'',innerHTML:'',close(){},addEventListener(){},classList:{remove(){},toggle(){}}});return nodes.get(id);};
 const location={hash,pathname:'/admin/marketing/channels',search:'?platform=instagram&result=select'};
 const window={scrollTo(){},addEventListener(){},JWChannels:{render(){channels++;}}};
 vm.runInNewContext(script,{document:{getElementById:get,querySelectorAll:()=>[],addEventListener(){}},window,location,sessionStorage:{getItem:()=>null,setItem(){}},setTimeout(){},clearTimeout(){},URLSearchParams});
 return {channels,nodes,location};
}

test('Meta OAuth fragment preserves the server Channels page and selection query',()=>{
 const r=render('#_=_');assert.equal(r.channels,1);assert.equal(r.location.search,'?platform=instagram&result=select');
});
test('plain Channels and explicit Channels navigation retain the real controller',()=>{
 assert.equal(render('').channels,1);assert.equal(render('#channels').channels,1);
});
test('normal preview navigation still works',()=>{
 const r=render('#overview');assert.equal(r.channels,0);assert.equal(r.nodes.get('title').textContent,'Your marketing, in one place');
});
