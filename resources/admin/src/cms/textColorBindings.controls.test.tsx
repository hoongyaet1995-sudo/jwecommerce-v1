import {textRoles} from './inspectorCapabilities';
// @vitest-environment jsdom
import {createHash} from 'node:crypto';
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync,readFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {layoutControlCapabilities} from './layoutControls';
import {stylePresets} from './pageStyle';
import {requiresTextColorItem} from './textColorBindings';
let current:any;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:current?.type==='chapter_navigation'?[current,{id:"chapter-a",type:"chapter_block",title:"First chapter",isVisible:true},{id:"chapter-b",type:"chapter_block",title:"Second chapter",isVisible:true}]:[current],style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;

it('mounts the canonical color source owner for every registered starter and preserves all unrelated fields',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);const rows:any[]=[];
 for(const [i,t]of SECTION_TEMPLATES.entries()){
  const section:any={...structuredClone(t.defaultData),id:`color-control-${i}`,isVisible:true};current=section;
  const roles=textRoles(section);const itemId=section.items?.[0]?.id??(section.type==='chapter_navigation'?'chapter-a':undefined);
  const role=roles.find(r=>itemId&&['itemTitle','itemContent'].includes(r))??roles.find(r=>!requiresTextColorItem(section,r))??(section.type==='chapter_navigation'?'label':undefined);
  if(!role){rows.push({card:i+1,name:t.title,result:'N/A: no authorable text target; source/decoration retains layout colors'});continue;}
  const target=requiresTextColorItem(section,role)?itemId:undefined;let latest:any;
  await act(async()=>root.render(<SectionInspector key={i} pageId="color-audit" section={section} selectedElement={{target:'typography',role,itemId:target,revision:i}} onChange={s=>latest=s} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));
  const control=node.querySelector<HTMLSelectElement>(`[data-typography-role="${role}"] [aria-label="Text color source"]`);expect(control,`${i+1} ${t.title} ${role}`).not.toBeNull();
  for(const source of ['auto','dark','light','custom']){
   await act(async()=>{control!.value=source;control!.dispatchEvent(new Event('change',{bubbles:true}));});
   const binding=latest.textColorBindings[target??'$section'][role];expect(binding.source).toBe(source);if(source==='custom')expect(binding.color).toMatch(/^#[a-f0-9]{6}$/i);
   const {textColorBindings,...preserved}=latest;expect(preserved).toEqual(section);
  }
  rows.push({card:i+1,name:t.title,role,itemId:target,result:'Four color sources; only selected binding changed'});
 }
 await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();expect(rows).toHaveLength(173);mkdirSync(resolve(process.cwd(),'../../output/text-color-bindings-20261118'),{recursive:true});writeFileSync(resolve(process.cwd(),'../../output/text-color-bindings-20261118/control-matrix-173.json'),JSON.stringify(rows,null,2));
},120000);
