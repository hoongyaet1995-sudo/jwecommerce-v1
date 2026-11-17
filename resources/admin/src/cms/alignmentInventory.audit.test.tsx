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
let current:any;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:current?.type==='chapter_navigation'?[current,{id:"chapter-a",type:"chapter_block",title:"First chapter",isVisible:true},{id:"chapter-b",type:"chapter_block",title:"Second chapter",isVisible:true}]:[current],style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('audits alignment controls and rendering for every registered card',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 mkdirSync(resolve(process.cwd(),'../../output/alignment-audit-20261117'),{recursive:true});
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node),rows:any[]=[];
 const render=(section:any)=>{current=section;return renderToStaticMarkup(<SectionRenderer section={section} isEditingMode/>)};
 for(const [i,t]of SECTION_TEMPLATES.entries()){
 const s:any={...structuredClone(t.defaultData),id:`spacing-${i}`,isVisible:true};let changed:any;const edits:any[]=[];
 await act(async()=>root.render(<SectionInspector key={i} pageId="spacing-audit" section={s} onChange={next=>{changed=next}} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));
 await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(n=>n.textContent==='design')!.click());
 const controls=[...node.querySelectorAll<HTMLButtonElement>('[aria-label^="Text alignment "]')];
 const outerHidden=controls.some(b=>!!b.closest('[hidden]'));
 for(const button of controls){await act(async()=>button.click());const textAlignment=button.getAttribute('aria-label')!.split(' ').at(-1);expect(changed).toEqual({...s,textAlignment});edits.push({label:button.getAttribute('aria-label'),section:JSON.parse(JSON.stringify(changed))});}
 const reset=node.querySelector<HTMLButtonElement>('[aria-label="Use layout alignment"]');if(reset){await act(async()=>reset.click());const expected={...s};delete expected.textAlignment;expect(changed).toEqual(expected);edits.push({label:'Use layout alignment',section:JSON.parse(JSON.stringify(changed))});}
 const states=Object.fromEntries(['left','center','right'].map(textAlignment=>[textAlignment,render({...s,textAlignment})]));const variants=Object.values(states);
 rows.push({original:s,edits,states,number:i+1,name:t.title,type:s.type,presets:Object.fromEntries(Object.entries(s).filter(([k])=>k.endsWith('Preset')&&k!=='motionPreset'||k==='paymentLayout')),defaultHash:createHash("sha256").update(render(s)).digest("hex"),capability:layoutControlCapabilities(s).alignment,controlCount:controls.length,outerHidden,rendererDistinct:new Set(variants).size});
 }
 await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();
 writeFileSync(resolve(process.cwd(),'../../output/alignment-audit-20261117/inventory.json'),JSON.stringify(rows,null,2));
 expect(rows).toHaveLength(173);
 for(const row of rows)expect(row.controlCount, `${row.number} ${row.name}`).toBe(3);
},120000);
