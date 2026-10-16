import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source=readFileSync(new URL('../../resources/js/app.js',import.meta.url),'utf8').split('// Shared enquiry presentation: no theme-specific submission or business rules.')[1];
function openEnquiry({quick,selector=null,url='https://example.test/shop?variant=999'}){
 const listeners={}; const label={textContent:'Initial option'}; const input={value:'initial'};
 const dialog={open:false,querySelector:s=>s==='[data-enquiry-variant]'?input:s==='[data-enquiry-option-label]'?label:null,style:{setProperty(){}},showModal(){this.open=true;}};
 const context={querySelector:s=>s==='[data-enquiry-dialog]'?dialog:s==='[data-enquiry-quick-variant]'?quick:s==='[data-storefront-variant-selector]'?selector:null};
 const button={disabled:false,closest:s=>s==='[data-enquiry-context]'?context:null};
 const document={addEventListener:(type,fn)=>(listeners[type]??=[]).push(fn),querySelector:()=>null};
 vm.runInNewContext(source,{document,URL,location:{href:url},getComputedStyle:()=>({fontFamily:'Inter'})});
 listeners.click[0]({target:{closest:s=>s==='[data-product-enquiry]'?button:null}});
 return {label:label.textContent,variant:input.value,open:dialog.open};
}
test('quick view names and submits the chosen option with its SKU',()=>{
 assert.deepEqual(openEnquiry({quick:{value:'22',selectedOptions:[{textContent:'Full day',dataset:{sku:'FULL'}}]}}),{label:'Full day · SKU: FULL',variant:'22',open:true});
});
test('single hidden option opens without selectedOptions and ignores unrelated URL variant',()=>{
 assert.deepEqual(openEnquiry({quick:{value:'23',dataset:{title:'Portraits',sku:'PORTRAIT'}}}),{label:'Portraits · SKU: PORTRAIT',variant:'23',open:true});
});
test('empty hidden option clears initial selection rather than borrowing a URL variant',()=>{
 assert.equal(openEnquiry({quick:{value:'',dataset:{}}}).variant,'');
});
test('shared PDP selector keeps its existing option summary',()=>{
 const selector={dataset:{selectedVariantId:'24',variants:JSON.stringify([{id:24,sku:'PDP'}])},querySelector:()=>({textContent:'Ceremony'})};
 assert.deepEqual(openEnquiry({quick:null,selector}),{label:'Ceremony · SKU: PDP',variant:'24',open:true});
});
