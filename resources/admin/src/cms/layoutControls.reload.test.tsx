// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {it,expect,vi} from 'vitest';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import manifest from '../../../cms/page-contract.v1.json';
let current:any;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[current]}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
it('renders actual API-reloaded values identically to every offered Inspector edit for all161',()=>{
 const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/spacing-alignment');
 const rows=JSON.parse(readFileSync(resolve(dir,'edit-fixtures.json'),'utf8')).rows;
 const receipts=JSON.parse(readFileSync(resolve(dir,'api-reloaded.json'),'utf8')).receipts;
 const html=(section:any)=>{current=section;return renderToStaticMarkup(<SectionRenderer section={section} isEditingMode/>)};
 let checked=0;
 for(const receipt of receipts){
  expect(receipt.document.sections).toHaveLength(161);
  for(const [i,saved]of receipt.document.sections.entries()){
   const row=rows[i];let expected=row.original;
   if(receipt.state.startsWith('edited-option-'))expected=row.edits[Number(receipt.state.split('-').at(-1))]?.section??row.original;
   if(receipt.state==='retained-legacy-values'){
    expected={...row.legacyUnsupported};const allowed=(manifest.section.sectionTypes as any)[expected.type].fields;
    for(const key of ['paddingY','textAlignment'])if(!allowed.includes(key))delete expected[key];
   }
   expect(saved,row.name+' '+receipt.state+' canonical fields').toEqual(expected);
   expect(html(saved),row.name+' '+receipt.state+' reloaded rendering').toBe(html(expected));checked++;
  }
 }
 writeFileSync(resolve(dir,'reload-rendering.json'),JSON.stringify({sections:161,states:receipts.length,comparisons:checked,gaps:[]},null,2));
});
