const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('public/js/cms-shell-design.js', 'utf8');
function shellAt(y) {
 const listeners = [], queue = [], transitions = [];
 const window = {innerWidth:1280,scrollY:y,addEventListener(type,fn){if(type==='scroll')listeners.push(fn);}};
 const values = {headerScrolled:'false',headerMode:'normal'};
 const dataset = new Proxy(values,{set(target,key,value){
   if(key==='headerScrolled' && target[key]!==value){
     transitions.push(value);
     // Real browser scroll anchoring compensates for the22px layout change.
     window.scrollY=Math.max(0,window.scrollY+(value==='true'?-22:22));
     queue.push(()=>listeners.forEach(fn=>fn()));
   }
   target[key]=value;return true;
 }});
 const header={dataset,querySelector(){return null;},querySelectorAll(){return [];},addEventListener(){},getBoundingClientRect(){return {height:dataset.headerScrolled==='true'?95:117};}};
 const document={querySelector(){return header;},addEventListener(){},documentElement:{style:{setProperty(){}}}};
 class Observer{observe(){}}
 vm.runInNewContext(script,{window,document,ResizeObserver:Observer,MutationObserver:Observer,queueMicrotask(){}});
 function settle(){let count=0;while(queue.length && count++<20)queue.shift()();assert.equal(queue.length,0,'Header must settle without a scroll anchoring feedback loop');}
 settle();return {window,header,transitions,scroll(to){window.scrollY=to;listeners.forEach(fn=>fn());settle();}};
}
test('small scroll remains stable despite header layout anchoring',()=>{const s=shellAt(29);assert.equal(s.header.dataset.headerScrolled,'false');assert.equal(s.transitions.length,0);});
test('compact header stays compact after shrink, and expands stably near top',()=>{const s=shellAt(80);assert.equal(s.header.dataset.headerScrolled,'true');s.scroll(5);assert.equal(s.header.dataset.headerScrolled,'false');assert.deepEqual(s.transitions,['true','false']);});

test('unified normal header has an opaque palette surface and animates outer padding',()=>{
 const css=fs.readFileSync('public/css/cms-shell-design.css','utf8');
 const rules=[...css.replace(/\/\*[\s\S]*?\*\//g,'').matchAll(/([^{}]+)\{([^{}]*)\}/g)];
 const outer=rules.filter(([,selector])=>selector.trim()==='.jw-shell-header[data-shell-style=unified]');
 assert.ok(outer.some(([,s,d])=>/transition:[^;]*padding\s+\.3s/.test(d)&&/box-shadow/.test(d)),'The outer header padding must animate like the reference');
 assert.ok(rules.some(([,s,d])=>s.trim()==='.jw-shell-header[data-shell-style=unified][data-header-mode=normal]'&&/background:var\(--shell-paper\)/.test(d)),'Normal mode must not blend with the dark page wrapper');
});

test('scrolled unified header keeps the reference glass surface and a transparent inner row',()=>{
 const css=fs.readFileSync('public/css/cms-shell-design.css','utf8').replace(/\/\*[\s\S]*?\*\//g,'');
 const rules=[...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)];
 assert.ok(rules.some(([,s,d])=>s.includes('[data-header-scrolled=true]')&&/background:color-mix\(in srgb,var\(--shell-paper\) 88%,transparent\)!important/.test(d)),'Scrolled header must retain the approved translucent palette surface');
 assert.ok(rules.some(([,s,d])=>s.includes('[data-header-scrolled=true]')&&s.includes('.jw-shell-main')&&/background:transparent!important/.test(d)),'Solid inner rows must not mask the glass on mobile');
});
