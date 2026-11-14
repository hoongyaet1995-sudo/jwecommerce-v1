import {it,expect} from 'vitest';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {readPageRuntimeBootstrap} from './PageRuntimeContext';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
const read=(document:any)=>readPageRuntimeBootstrap({getElementById:()=>({textContent:JSON.stringify({page:{id:'82',status:'draft',stage:'draft',schemaVersion:1,lockVersion:1,contentHash:'fixture-hash',document},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},links:{},runtime:{sections:{},errors:{}},context:{mode:'preview',currency:'MYR'}})})} as any);
it('accepts all161 registered defaults and all8 complete factory documents through actual public bootstrap',()=>{
 const pages=JSON.parse(readFileSync(resolve(process.cwd(),'../../output/jw-cms-integration-20261114/new-page-documents.json'),'utf8'));
 expect(pages).toHaveLength(8);expect(SECTION_TEMPLATES).toHaveLength(161);
 for(const [i,t] of SECTION_TEMPLATES.entries())expect(read({title:t.title,slug:`legacy-${i}`,template:'standard',sections:[{...structuredClone(t.defaultData),id:`legacy-${i}`,isVisible:true}]}),t.title).not.toBeNull();
 for(const page of pages)expect(read(page),page.slug).not.toBeNull();
});
it('rejects unregistered fields and malformed connected, title ranges, motion and pricing shapes',()=>{
 const page=JSON.parse(readFileSync(resolve(process.cwd(),'../../output/jw-cms-integration-20261114/new-page-documents.json'),'utf8'))[0];
 for(const patch of [{unknownRuntimeField:'bad'},{connectedDiagram:'unregistered'},{titleEmphasis:[{start:4,end:2}]},{motionPace:'instant'},{pricingMatrix:{layout:'arbitrary'}},{connectedLayoutMemory:{arbitrary:'unregistered'}}]){
  const document=structuredClone(page);Object.assign(document.sections[0],patch);expect(read(document),JSON.stringify(patch)).toBeNull();
 }
});
