const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs');
const {JSDOM}=require('../../resources/admin/node_modules/jsdom');
test('image options cannot be displaced by the full-width Bootstrap legend float',()=>{
 const template=fs.readFileSync('resources/views/marketing/automation/editor.blade.php','utf8');const segment=template.match(/<fieldset class="mt-4"><legend class="form-label">How many images\?[\s\S]*?<\/fieldset>/)[0];
 const dom=new JSDOM('<body class="marketing-automation-post"><form id="automation-form">'+segment+'</form></body>');const w=dom.window,style=w.document.createElement('style');style.textContent='fieldset{min-width:0}legend{float:left;width:100%}legend+*{clear:left}'+fs.readFileSync('public/marketing-studio/automation.css','utf8');w.document.head.append(style);
 assert.equal(w.getComputedStyle(w.document.querySelector('legend')).float,'none');
 assert.equal(w.document.querySelector('legend').nextElementSibling.id,'automation-images');
 assert.equal(w.document.querySelector('#automation-images').parentElement.tagName,'FIELDSET');
});
