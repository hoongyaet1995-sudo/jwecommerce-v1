const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const vm=require('node:vm');
test('mobile launcher yields to overlapping action, returns after scroll and stays desktop sized',()=>{
 let callback;let mobile=true;let overlap=true;const classes=new Set();
 const launcher={hidden:false,classList:{toggle:(n,v)=>v?classes.add(n):classes.delete(n)},getBoundingClientRect:()=>({left:310,right:358,top:760,bottom:808})};
 const action={getBoundingClientRect:()=>overlap?({left:40,right:350,top:740,bottom:790,width:310,height:50}):({left:40,right:250,top:640,bottom:690,width:210,height:50})};
 const root={contains:()=>false};const doc={readyState:'complete',activeElement:null,querySelector:s=>s==='#sf-support-launcher'?launcher:root,querySelectorAll:()=>[action],addEventListener:(n,f)=>{if(n==='scroll')callback=f;},body:{}};
 const ctx={document:doc,window:{addEventListener(){},visualViewport:null},matchMedia:()=>({matches:mobile}),requestAnimationFrame:f=>{f();return 0},getComputedStyle:()=>({visibility:'visible',display:'block'}),MutationObserver:class{observe(){}}};vm.createContext(ctx);vm.runInContext(fs.readFileSync('public/js/support-launcher-layout.js','utf8'),ctx);
 assert.equal(classes.has('yields-to-action'),true);overlap=false;callback();assert.equal(classes.has('yields-to-action'),false);overlap=true;mobile=false;callback();assert.equal(classes.has('yields-to-action'),false);
});
