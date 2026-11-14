// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {writeFileSync,readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {headingCapability} from './sectionHeading';
import {sectionContentFields} from './contentCapabilities';
import {textRoles} from './inspectorCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {renderedColorFields} from './sectionDesignCapabilities';
import type {PageSection} from '../types';
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('scans actual mounted Passport groups for all registered and saved layout variants',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/integration');
 const saved=JSON.parse(readFileSync(resolve(dir,'saved-layouts.json'),'utf8'));
 const variants=SECTION_TEMPLATES.map((t,i)=>({source:'registry',name:t.title,section:{...structuredClone(t.defaultData),id:`scan-${i+1}`,isVisible:true} as PageSection}));
 const known=new Set(variants.map(v=>v.section.type+'|'+JSON.stringify(Object.fromEntries(Object.entries(v.section).filter(([k])=>(k.endsWith('Preset')&&k!=='motionPreset')||k==='paymentLayout').sort(([a],[b])=>a.localeCompare(b))))));
 for(const v of saved.variants){const key=v.type+'|'+JSON.stringify(v.presets);if(!known.has(key)){variants.push({source:'saved',name:key,section:{id:`saved-${variants.length}`,type:v.type,title:'Saved layout',isVisible:true,...v.presets}});known.add(key);}}
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);const report:any[]=[];
 for(const v of variants){
  const s=v.section;await act(async()=>root.render(<SectionInspector key={s.id} pageId="passport-scan" section={s} onChange={vi.fn()} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));
  const owner=headingCapability(s).mode;
  expect(node.querySelectorAll('[data-inspector-field=eyebrow]').length,v.name+' canonical Content owner').toBe(owner==='section'?1:owner==='slide'?(s.items?.length??0):0);
  const controls=()=>[...node.querySelectorAll<HTMLInputElement>('input,select,textarea')].filter(n=>!n.closest('[hidden]')).map(n=>({tag:n.tagName,label:n.getAttribute('aria-label'),field:n.getAttribute('data-inspector-field'),placeholder:n.getAttribute('placeholder'),type:n.type}));
  const groups:any={content:controls()};
  for(const tab of ['design','advanced']){const button=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.toLowerCase()===tab);expect(button,v.name+' '+tab).toBeTruthy();await act(async()=>button!.click());groups[tab]=controls();}
  report.push({source:v.source,name:v.name,type:s.type,presets:Object.fromEntries(Object.entries(s).filter(([k])=>k.endsWith('Preset')&&k!=='motionPreset')),contentFields:sectionContentFields(s),textRoles:textRoles(s),itemFields:repeatedItemFields(s),images:imageTargets(s).map(t=>({id:t.id,field:t.field})),colors:renderedColorFields(s)??'legacy',groups});
 }
 await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();
 expect(report.filter(r=>r.source==='registry')).toHaveLength(161);
 writeFileSync(resolve(dir,'mounted-passport-scan.json'),JSON.stringify({generatedAt:new Date().toISOString(),registry:161,savedAdditional:report.filter(r=>r.source==='saved').length,rows:report},null,2));
},120000);
