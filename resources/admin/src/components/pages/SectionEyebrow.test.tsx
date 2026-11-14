// @vitest-environment jsdom
import {writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect,vi} from 'vitest';
import {SectionEyebrow} from './SectionEyebrow';
import {SectionRenderer} from './SectionRenderer';
import {SECTION_TEMPLATES} from './AddSectionModal';
import {headingCapability} from '../../cms/sectionHeading';
import {stylePresets} from '../../cms/pageStyle';
import type {PageSection} from '../../types';
let current:PageSection;
vi.mock('../../cms/PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[current],style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
describe('shared rendered Eyebrow',()=>{
 it('consumes live typography instead of fixed sample size/weight/color',()=>{const html=renderToStaticMarkup(<SectionEyebrow section={{id:'s',type:'rich_text',title:'Heading',isVisible:true,eyebrow:'Editable',typography:{eyebrow:{fontFamily:'serif',fontSize:24,fontWeight:700,color:'#123456',lineHeight:1.8,letterSpacing:2}}}}/>);const node=document.createElement('div');node.innerHTML=html;const pill=node.querySelector<HTMLElement>('[data-cms-eyebrow]')!;expect(pill).not.toBeNull();expect(pill.style.fontWeight).toBe('700');expect(pill.style.color).toBe('rgb(18, 52, 86)');expect(pill.style.fontSize).toContain('24px');expect(pill.style.lineHeight).toBe('1.8');expect(pill.style.letterSpacing).toBe('2px');expect(pill.style.fontFamily).toContain('Georgia');});
 it.each(['','   '])('returns no pill, dot or wrapper for empty value %j',eyebrow=>{expect(renderToStaticMarkup(<SectionEyebrow section={{id:'s',type:'rich_text',title:'Heading',isVisible:true,eyebrow,subtitle:'Must not revive'}}/>)).toBe('');});
 it('covers every registered applicable heading and slide without changing existing heading words',()=>{
  const gaps:string[]=[];
  for(const t of SECTION_TEMPLATES){current={...structuredClone(t.defaultData),id:'qa',isVisible:true,eyebrow:'CANONICAL EYEBROW',items:t.defaultData.items?.map(i=>({...i,eyebrow:'SLIDE EYEBROW'}))};const owner=headingCapability(current).mode;const html=renderToStaticMarkup(<SectionRenderer section={current} isEditingMode={current.type==='promotion_highlights'}/>);const node=document.createElement('div');node.innerHTML=html;const pills=node.querySelectorAll('[data-cms-eyebrow]');if(owner==='none'&&pills.length)gaps.push(t.title+': should not have pill');if(owner==='section'&&pills.length!==1)gaps.push(t.title+': expected one, found '+pills.length);if(owner==='slide'&&pills.length!==1)gaps.push(t.title+': expected active slide pill');if(owner==='section'&&pills[0]?.textContent!=='CANONICAL EYEBROW')gaps.push(t.title+': canonical value not consumed');}
  expect(gaps).toEqual([]);
 });
 it('clears all registered renderers without legacy fallback or an empty pill',()=>{
  const gaps:string[]=[];
  for(const t of SECTION_TEMPLATES){current={...structuredClone(t.defaultData),id:'qa',isVisible:true,eyebrow:'',items:t.defaultData.items?.map(i=>({...i,eyebrow:''}))};const html=renderToStaticMarkup(<SectionRenderer section={current} isEditingMode={current.type==='promotion_highlights'}/>);if(html.includes('data-cms-eyebrow='))gaps.push(t.title);}
  expect(gaps).toEqual([]);
 });
});

it("renders only one Values heading Eyebrow when the optional heading is enabled",()=>{current={id:"values",type:"values_list",isVisible:true,title:"Values",valuesShowHeading:true,eyebrow:"Our values",items:[]};const html=renderToStaticMarkup(<SectionRenderer section={current}/>);expect(html.match(/data-cms-eyebrow="true"/g)).toHaveLength(1);});

it("keeps a legacy Eyebrow color readable without a page palette and lets Typography override it",()=>{const s:PageSection={id:"raw",type:"rich_text",isVisible:true,title:"Heading",subtitle:"Legacy",customColors:{eyebrowColor:"#ef7b32"}};expect(renderToStaticMarkup(<SectionEyebrow section={s}/>)).toContain("color:#ef7b32");expect(renderToStaticMarkup(<SectionEyebrow section={{...s,typography:{eyebrow:{color:"#123456"}}}}/>)).toContain("color:#123456");});

it('places every applicable Eyebrow before its heading across surfaces and optional heading states',()=>{
 const gaps:string[]=[],rows:any[]=[];
 const variants=[...SECTION_TEMPLATES.map(t=>({name:t.title,section:t.defaultData})),{name:'Saved editorial-triptych',section:{type:'media_gallery',galleryPreset:'editorial-triptych',title:'Saved heading'}},{name:'Saved policy default',section:{type:'policy_content',title:'Saved heading'}}];
 for(const t of variants)for(const surface of ['page','light','contrast'] as const)for(const enabled of [false,true]){
  current={...structuredClone(t.section),id:'order-qa',isVisible:true,surface,eyebrow:'ORDER CHECK',...(enabled?{showSectionHeading:true,valuesShowHeading:true}:{}),items:t.section.items?.map(i=>({...i,eyebrow:'SLIDE ORDER CHECK'}))} as PageSection;
  const node=document.createElement('div');node.innerHTML=renderToStaticMarkup(<SectionRenderer section={current} isEditingMode/>);
  const pills=[...node.querySelectorAll('[data-cms-eyebrow]')],heading=node.querySelector('[data-cms-text="title"]');
  const ordered=pills.every(pill=>!!heading&&!!(pill.compareDocumentPosition(heading)&Node.DOCUMENT_POSITION_FOLLOWING));
  const row={name:t.name,surface,optionalHeading:enabled,pills:pills.length,ordered};rows.push(row);
  if(!ordered)gaps.push(t.name+' / '+surface+' / '+(enabled?'heading enabled':'starter'));
 }
 writeFileSync(resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/order-fix/renderer-order.json'),JSON.stringify({rows,gaps},null,2));
 expect(gaps).toEqual([]);
});
