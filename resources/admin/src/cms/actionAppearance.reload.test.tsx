// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {it,expect,vi} from 'vitest';
import {SectionRenderer} from '../components/pages/SectionRenderer';
let current:any,index=0,style:any;
const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/color-buttons');
const facts=JSON.parse(readFileSync(resolve(dir,'runtime-facts.json'),'utf8'));
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({...facts,forms:{contact:{},newsletter:{}},page:{document:{sections:[current],style}}}),usePageSectionRuntime:()=>facts.runtime.sections[String(index)]??{},isCmsFormSubmissionContract:()=>false}));
it('renders all161 API-reloaded color/button edits exactly as authored across every saved state',()=>{
 const rows=JSON.parse(readFileSync(resolve(dir,'edit-fixtures.json'),'utf8')).rows;
 const receipts=JSON.parse(readFileSync(resolve(dir,'api-reloaded.json'),'utf8')).receipts;
 const html=(section:any)=>{current=section;return renderToStaticMarkup(<SectionRenderer section={section} isEditingMode/>)};
 let comparisons=0;
 for(const r of receipts){style=r.document.style;expect(r.document.sections).toHaveLength(161);
  for(const [i,saved]of r.document.sections.entries()){
   index=i;const row=rows[i];let expected=row.original;
   if(r.state.startsWith('edited-option-'))expected=row.edits[Number(r.state.split('-').at(-1))]?.section??row.original;
   if(['all-overrides','page-and-local-overrides'].includes(r.state))expected=row.edited;
   expect(saved,row.name+' '+r.state).toEqual(expected);expect(html(saved)).toBe(html(expected));comparisons++;
  }
 }
 writeFileSync(resolve(dir,'reload-rendering.json'),JSON.stringify({sections:161,states:receipts.length,comparisons,gaps:[]},null,2));
},120000);
