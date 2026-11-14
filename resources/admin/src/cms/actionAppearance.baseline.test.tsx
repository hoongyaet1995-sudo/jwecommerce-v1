// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {readFileSync,writeFileSync,existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {createHash} from 'node:crypto';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {stylePresets} from './pageStyle';
let current:any,index=0,shared:any;
const facts=JSON.parse(readFileSync(resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/color-buttons/runtime-facts.json'),'utf8'));
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({...facts,forms:{contact:{},newsletter:{}},page:{document:{sections:[current],style:shared}}}),usePageSectionRuntime:()=>facts.runtime.sections[String(index)]??{},isCmsFormSubmissionContract:()=>false}));
it('freezes all161 default presentation and inventories real action consumers',()=>{
 const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/color-buttons');
 const html=(s:any)=>{current=s;return renderToStaticMarkup(<SectionRenderer section={s} isEditingMode/>)};
 const rows=SECTION_TEMPLATES.map((t,i)=>{
  index=i;const s:any={...structuredClone(t.defaultData),id:`layout-${i+1}`,isVisible:true};
  const defaults=[undefined,{palette:stylePresets[0].palette},{palette:stylePresets[0].palette,unifiedButtons:false}].map(style=>{shared=style;return createHash('sha256').update(html(s).replace(/ data-cms-button(?:-kind)?="[^"]*"/g,'')).digest('hex')});shared=undefined;
  const active={...s,showSectionHeading:true,valuesShowHeading:true,eyebrow:'Audit eyebrow',content:'Audit content',buttonText:'Audit primary',buttonLink:'/shop',secondaryButtonText:'Audit secondary',secondaryButtonLink:'/shop',items:s.items?.map((v:any)=>({...v,buttonText:'Audit action',linkUrl:'/shop'}))};
  const n=document.createElement('div');n.innerHTML=html(active);
  const actions=[...n.querySelectorAll('a,button')].map(e=>({tag:e.tagName,text:e.textContent?.trim().slice(0,65),action:e.getAttribute('data-cms-action')??e.getAttribute('data-editorial-action'),cls:e.getAttribute('class'),type:e.getAttribute('type'),style:e.getAttribute('style'),item:e.getAttribute('data-cms-item')}));
  const capability=(v:any)=>{const dom=document.createElement('div');dom.innerHTML=html(v);return Object.fromEntries(['primary','secondary'].map(channel=>[channel,{button:!!dom.querySelector(`[data-cms-button="${channel}"][data-cms-button-kind="button"]`),link:!!dom.querySelector(`[data-cms-button="${channel}"][data-cms-button-kind="link"]`)}]));};
  return {number:i+1,name:t.title,section:s,active,defaults,actions,capability:capability(active),headingOff:capability({...active,showSectionHeading:false,valuesShowHeading:false})};
 });
 const file=resolve(dir,'default-baseline.json');const hashes=rows.map(({number,name,defaults})=>({number,name,defaults}));
 if(!existsSync(file))writeFileSync(file,JSON.stringify(hashes,null,2));
 expect(hashes).toEqual(JSON.parse(readFileSync(file,'utf8')));expect(rows).toHaveLength(161);
 writeFileSync(resolve(dir,'consumer-inventory.json'),JSON.stringify({rows},null,2));
},120000);

it('appearance-only page edits preserve every existing layout color and composition',()=>{
 const hash=(value:string)=>createHash('sha256').update(value.replace(/<style data-cms-owned-appearance[^>]*>[\s\S]*?<\/style>/g,'')).digest('hex');
 for(const [i,t]of SECTION_TEMPLATES.entries()){
  index=i;current={...structuredClone(t.defaultData),id:`layout-${i+1}`,isVisible:true};shared=undefined;
  const before=renderToStaticMarkup(<SectionRenderer section={current} isEditingMode/>);
  shared={palette:stylePresets[0].palette,preserveLayout:true,unifiedButtons:false,buttonHover:true,buttonHoverColor:'#654321'};
  const after=renderToStaticMarkup(<SectionRenderer section={current} isEditingMode/>);
  expect(hash(after),t.title+' hover-only must not recolor layout').toBe(hash(before));
 }
 shared=undefined;
},120000);
