const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),test=require('node:test');
const source=fs.readFileSync('public/marketing-studio/template-workflow.js','utf8');
function fn(name,next){return source.slice(source.indexOf('function '+name+'('),source.indexOf('function '+next+'('));}
test('unchanged poll preserves visited page and does not rerender an open editor',()=>{
 const before={run:{id:'r1',status:'producing',phase:'Rendering'}};let rendered=0;
 const c={dto:before,page:'storyboard',modal:{open:true},statusPage:()=> 'progress',schedule(){},render(){rendered++;},JSON};
 vm.runInNewContext(fn('apply','schedule')+';apply('+JSON.stringify(before)+')',c);
 assert.equal(c.page,'storyboard');assert.equal(rendered,0);
});
test('completion transition automatically presents the finished page',()=>{
 const c={dto:{run:{id:'r1',status:'rendering'}},page:'progress',modal:{open:false},statusPage:()=> 'finished',schedule(){},render(){},JSON};
 vm.runInNewContext(fn('apply','schedule')+';apply({run:{id:"r1",status:"completed"}})',c);assert.equal(c.page,'finished');
});
test('completed quote offers playback instead of another start action',()=>{
 const c={run:()=>({status:'completed',quote:{cap_microusd:100,digest:'x'}}),app:{innerHTML:''},footer:{innerHTML:''},money:String,esc:String,summary:()=>'',settingsSummary:()=>'',icon:()=>'',locked:()=>false,secondary:(s,a)=>a,primary:(s,a)=>s+':'+a};
 vm.runInNewContext(fn('quote','progress')+';quote()',c);assert.match(c.footer.innerHTML,/查看成片:finished/);assert.doesNotMatch(c.footer.innerHTML,/confirm-modal/);
});
test('previous completed playback uses its own media id instead of latest failed run',()=>{
 const c={base:'/templates',project:()=>({id:'project'}),run:()=>({id:'failed'}),playback:()=>({id:'successful'})};
 vm.runInNewContext(fn('media','history')+';output=media("portrait")',c);assert.match(c.output,/media\/successful\/portrait$/);
});
