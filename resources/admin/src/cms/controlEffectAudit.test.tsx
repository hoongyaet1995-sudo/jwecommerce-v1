// @vitest-environment jsdom
// Diagnostic audit only: unchanged markup is a candidate, not a universal visual verdict.
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {textRoles} from './inspectorCapabilities';
import {sectionContentFields} from './contentCapabilities';
import {stylePresets} from './pageStyle';
import {imageTargets} from './imageTargets';
import type {PageSection} from '../types';
let current:PageSection;
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({page:{document:{sections:[current],style:{palette:stylePresets[0].palette}}},catalog:{products:[],categories:[],collections:[]},forms:{contact:{},newsletter:{}},context:{mode:'preview',currency:'MYR'}}),usePageSectionRuntime:()=>({}),isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('collects mounted ownership and renderer effect evidence for all 161 starters',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node),rows:any[]=[];
 const html=(s:PageSection)=>{current=s;return renderToStaticMarkup(<SectionRenderer section={s} isEditingMode/>)};
 for(const [i,t] of SECTION_TEMPLATES.entries()){
  const s={...structuredClone(t.defaultData),id:'audit',isVisible:true} as PageSection;
  await act(async()=>root.render(<SectionInspector key={String(i)} pageId="audit" section={s} onChange={vi.fn()} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));
  await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!.click());
  const design=[...node.querySelectorAll<HTMLElement>('[role=tabpanel]')].find(n=>!n.hidden)!;
  const visible=design.cloneNode(true) as HTMLElement;visible.querySelectorAll('[hidden]').forEach(n=>n.remove());
  const text=visible.textContent??'';
  const active={...s,eyebrow:'Audit eyebrow',content:'Audit description',subtitle:'Audit subtitle',leadText:'Audit introduction',quoteText:'Audit quote',showSectionHeading:true,valuesShowHeading:true,buttonText:'Audit action',buttonLink:'/shop',secondaryButtonText:'Audit secondary',secondaryButtonLink:'/shop',items:s.items?.map(item=>({...item,title:item.title||'Audit item',subtitle:item.subtitle||'Audit label',description:item.description||'Audit item description',buttonText:'Audit action',linkUrl:'/shop',imageUrl:item.imageUrl||'/audit.jpg'}))} as PageSection;
  const compare=(a:any,b:any)=>html({...s,...a})!==html({...s,...b});
  const probes:any={};
  if(text.includes('Vertical Spacing'))probes.paddingY=compare({paddingY:'compact'},{paddingY:'spacious'});
  if(text.includes('Text Alignment')){probes.alignmentLeftRight=compare({textAlignment:'left'},{textAlignment:'right'});probes.alignmentCenterRight=compare({textAlignment:'center'},{textAlignment:'right'});}
  const roles=Object.fromEntries(textRoles(s).map(role=>[role,html({...active,typography:{...s.typography,[role]:{fontSize:17,color:'#123456'}}})!==html({...active,typography:{...s.typography,[role]:{fontSize:61,color:'#987654'}}})]));
  const colors=Object.fromEntries([...visible.querySelectorAll<HTMLInputElement>('input[type=color]')].map(n=>n.getAttribute('aria-label')).filter(n=>n&&n!=='Text color picker').map(label=>{
   const key=({'Primary button background picker':'primaryBtnBg','Primary button text picker':'primaryBtnText','Secondary button background picker':'secondaryBtnBg','Secondary button text picker':'secondaryBtnText','Accent color picker':'eyebrowColor'} as any)[label!];
   return [label,key?html({...active,surface:'page',customColors:{...s.customColors,[key]:'#123456'},colorOverrides:[key]})!==html({...active,surface:'page',customColors:{...s.customColors,[key]:'#987654'},colorOverrides:[key]}):null];
  }));
  const fields=Object.fromEntries(sectionContentFields(s).filter(k=>typeof (s as any)[k]==='string').map(k=>[k,compare({[k]:'AUDIT A'},{[k]:'AUDIT B'})]));
  const defaults=html(active),parsed=document.createElement('div');parsed.innerHTML=defaults;
  const actions=parsed.querySelectorAll(':is(a,button):is([data-cms-action="button"],[data-cms-action="primary"],[data-cms-action="secondary"],[data-cms-action="item"],[data-cms-action="submit"],[data-editorial-action="button"])').length;
  const shownButtons=!!visible.querySelector('[aria-label="Section button size"]');
  const imageEffects=Object.fromEntries(imageTargets(active).map(target=>[target.id,html({...active,imageSlots:[{target:target.id,imageFit:'contain',imagePosition:'top-center'}]})!==html({...active,imageSlots:[{target:target.id,imageFit:'cover',imagePosition:'bottom-center'}]})]));
  rows.push({number:i+1,name:t.title,type:s.type,presets:Object.fromEntries(Object.entries(s).filter(([k])=>k.endsWith('Preset')&&k!=='motionPreset')),probes,roles,colors,imageEffects,contentStringFields:fields,sharedButtonsOffered:shownButtons,sharedButtonTargets:actions,designSummaries:[...visible.querySelectorAll('summary')].map(n=>n.textContent),designLabels:[...visible.querySelectorAll('input,select')].map(n=>n.getAttribute('aria-label')).filter(Boolean)});
 }
 await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();expect(rows).toHaveLength(161);
 writeFileSync(resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/control-effect-audit/effects.json'),JSON.stringify({generatedAt:new Date().toISOString(),method:'SSR differential + mounted controls; no-op candidates require context/source verification',rows},null,2));
},120000);
