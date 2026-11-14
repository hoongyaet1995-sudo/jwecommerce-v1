// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync,readFileSync,existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {createHash} from 'node:crypto';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {stylePresets} from './pageStyle';
let current:any;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[current],style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
it('captures default parity and independent layout control effects without changing data',()=>{
 const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/spacing-alignment');
 const html=(s:any)=>{current=s;return renderToStaticMarkup(<SectionRenderer section={s} isEditingMode/>)};
 const hash=(v:string)=>createHash('sha256').update(v).digest('hex');
 const rows=SECTION_TEMPLATES.map((t,i)=>{
  const s:any={...structuredClone(t.defaultData),id:`layout-${i+1}`,isVisible:true};
  const preset=Object.fromEntries(Object.entries(s).filter(([k])=>k.endsWith('Preset')&&k!=='motionPreset'||k==='paymentLayout'));
  const states=[{}, {showSectionHeading:true,valuesShowHeading:true}, {showSectionHeading:false,valuesShowHeading:false}].map(flags=>{
   const v={...s,...flags};const align=Object.fromEntries(['left','center','right'].map(textAlignment=>[textAlignment,hash(html({...v,textAlignment}))]));
   return {flags,spacing:html({...v,paddingY:'compact'})!==html({...v,paddingY:'spacious'}),align};
  });
  return {number:i+1,name:t.title,type:s.type,preset,states,defaultHash:hash(html(s)),section:s};
 });
 const baseline=resolve(dir,'default-baseline.json');
 if(!existsSync(baseline))writeFileSync(baseline,JSON.stringify(rows.map(({number,name,defaultHash})=>({number,name,defaultHash})),null,2));
 expect(rows.map(({number,name,defaultHash})=>({number,name,defaultHash}))).toEqual(JSON.parse(readFileSync(baseline,'utf8')));
 expect(rows).toHaveLength(161);writeFileSync(resolve(dir,'renderer-evidence.json'),JSON.stringify({rows},null,2));
},120000);
