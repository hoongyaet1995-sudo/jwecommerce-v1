// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync,readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {layoutControlCapabilities} from './layoutControls';
import {stylePresets} from './pageStyle';
let current:any;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[current],style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('all161 actual Inspectors offer only effective values, preserve legacy data, and export edits for API acceptance',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/spacing-alignment');
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node),rows:any[]=[];
 const render=(section:any)=>{current=section;return renderToStaticMarkup(<SectionRenderer section={section} isEditingMode/>)};
 const evidence=JSON.parse(readFileSync(resolve(dir,'renderer-evidence.json'),'utf8')).rows;
 for(const [i,t]of SECTION_TEMPLATES.entries()){
  const original={...structuredClone(t.defaultData),id:`layout-${i+1}`,isVisible:true};const capability=layoutControlCapabilities(original);
  let changed:any;const onChange=vi.fn((next:any)=>changed=next);
  await act(async()=>root.render(<SectionInspector key={String(i)} pageId="layout-acceptance" section={original} onChange={onChange} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));
  await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(n=>n.textContent==='design')!.click());
  const spacing=[...node.querySelectorAll<HTMLButtonElement>('[aria-label^="Vertical spacing "]')];
  const alignment=[...node.querySelectorAll<HTMLButtonElement>('[aria-label^="Text alignment "]')];
  expect(spacing.length,t.title+' spacing options').toBe(capability.spacing?3:0);expect(alignment.length,t.title+' alignment options').toBe(capability.alignment.length);
  expect(onChange,t.title+' mounting cannot rewrite defaults').not.toHaveBeenCalled();
  const edits:any[]=[];
  for(const button of [...spacing,...alignment]){
   const label=button.getAttribute('aria-label')!;const key=label.startsWith('Vertical')?'paddingY':'textAlignment';const value=label.split(' ').at(-1)!;
   await act(async()=>button.click());expect(changed).toEqual({...original,[key]:value});
   const reloaded=JSON.parse(JSON.stringify(changed));expect(render(reloaded)).toBe(render(changed));edits.push({label,section:reloaded});
  }
  const spacingMarkup=edits.filter(e=>e.label.startsWith('Vertical')).map(e=>render(e.section));
  if(spacingMarkup.length)expect(new Set(spacingMarkup).size,t.title+' three distinct spacings').toBe(3);
  const alignmentMarkup=edits.filter(e=>e.label.startsWith('Text')).map(e=>render(e.section));
  expect(new Set(alignmentMarkup).size,t.title+' distinct alignment choices').toBe(alignmentMarkup.length);
  // Capability derives from layout, not presence of old fields; heading-only controls disappear.
  const sparse={id:'sparse',type:original.type,title:'Sparse',isVisible:true,...Object.fromEntries(Object.entries(original).filter(([k])=>k.endsWith('Preset')&&k!=='motionPreset'||k==='paymentLayout'))};
  expect(layoutControlCapabilities(sparse)).toEqual(layoutControlCapabilities({...sparse,paddingY:'spacious',textAlignment:'right'}));
  const on=layoutControlCapabilities({...original,showSectionHeading:true,valuesShowHeading:true}),off=layoutControlCapabilities({...original,showSectionHeading:false,valuesShowHeading:false});
  if(on.headingOnly)expect(off.alignment,t.title+' hidden heading').toEqual([]);
  for(const flags of [{},{showSectionHeading:true,valuesShowHeading:true},{showSectionHeading:false,valuesShowHeading:false}]){
   const v={...original,...flags};const c=layoutControlCapabilities(v);
   if(c.spacing)expect(new Set(['compact','normal','spacious'].map(paddingY=>render({...v,paddingY}))).size).toBe(3);
   expect(new Set(c.alignment.map(textAlignment=>render({...v,textAlignment}))).size,t.title+' distinct choices '+JSON.stringify(flags)).toBe(c.alignment.length);
  }
  rows.push({number:i+1,name:t.title,capability,original,edits,legacyUnsupported:{...original,paddingY:'spacious',textAlignment:'right'},defaultHash:evidence[i].defaultHash});
 }
 await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();expect(rows).toHaveLength(161);
 writeFileSync(resolve(dir,'edit-fixtures.json'),JSON.stringify({rows},null,2));
},120000);
