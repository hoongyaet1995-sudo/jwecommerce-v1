// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SectionButtonSettings} from '../components/pages/SectionButtonSettings';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {textRoles,textRoleLabels} from './inspectorCapabilities';
import {actionCapabilities,buttonTarget} from './actionAppearance';
import {stylePresets} from './pageStyle';
let current:any,index=0;
const dir=resolve(process.cwd(),'../../docs/planning/section-passport-2026-11-13/color-buttons');
const facts=JSON.parse(readFileSync(resolve(dir,'runtime-facts.json'),'utf8'));
vi.mock('./PageRuntimeContext',()=>({usePageRuntime:()=>({...facts,forms:{contact:{},newsletter:{}},page:{document:{sections:[current]}}}),usePageSectionRuntime:()=>facts.runtime.sections[String(index)]??{},isCmsFormSubmissionContract:()=>false}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('./useCmsBrand',()=>({useCmsBrand:()=>({palette:stylePresets[0].palette})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('edits every offered text/action property across all161 actual Inspectors, exporting exact canonical writes',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const small=document.createElement('div');document.body.append(small);const smallRoot=createRoot(small),rows:any[]=[];
 const html=(s:any)=>{current=s;return renderToStaticMarkup(<SectionRenderer section={s} isEditingMode/>)};
 const button=(node:Element,label:string)=>node.querySelector<HTMLButtonElement>(`button[aria-label="${label}"]`)!;
 for(const [i,t]of SECTION_TEMPLATES.entries()){
  index=i;const original:any={...structuredClone(t.defaultData),id:`layout-${i+1}`,isVisible:true};const cap=actionCapabilities(original),edits:any[]=[];let latest:any;
  const onChange=vi.fn((s:any)=>latest=s);
  await act(async()=>root.render(<SectionInspector key={String(i)} pageId="action-qa" section={original} onChange={onChange} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));
  await act(async()=>[...host.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!.click());
  expect(onChange,t.title+' no mount write').not.toHaveBeenCalled();
  const geometry=cap.primary.button||cap.secondary.button;
  expect(host.querySelectorAll('[aria-label="Section button corners"]').length,t.title).toBe(geometry?1:0);
  expect(host.querySelectorAll('[aria-label="Accent color picker"]')).toHaveLength(0);
  expect(host.querySelectorAll('[data-section-button-style]').length,t.title).toBe(cap.primary.button||cap.primary.link||cap.secondary.button||cap.secondary.link?1:0);
  let edited=structuredClone(original);
  for(const role of textRoles(original)){
   await act(async()=>button(host,'Edit '+textRoleLabels[role]).click());
   const input=host.querySelector<HTMLInputElement>('[aria-label="Text color hex"]')!;
   await act(async()=>{input.value='#123456';input.dispatchEvent(new FocusEvent('focusout',{bubbles:true}));});
   expect(latest.typography[role]).toMatchObject({color:'#123456',colorOverride:true});expect(latest.customColors).toEqual(original.customColors);
   edits.push({label:'text-'+role,section:structuredClone(latest)});
   edited.typography={...edited.typography,[role]:latest.typography[role]};
  }
  for(const channel of ['primary','secondary'] as const){
   const c=cap[channel];if(!c.button&&!c.link)continue;
   const name=channel==='primary'?'Primary':'Secondary';
   const fields=[...(c.button?[['Bg',name+' button background','#234567']]:[]),['Text',name+' action text','#fedcba'],['Hover',name+' hover color','#654321']];
   for(const [suffix,label,value]of fields){
    await act(async()=>smallRoot.render(<SectionButtonSettings key={label} section={original} onChange={onChange}/>));
    await act(async()=>button(small,'Customize '+label).click());
    await act(async()=>smallRoot.render(<SectionButtonSettings key={label} section={latest} onChange={onChange}/>));
    const input=small.querySelector<HTMLInputElement>(`[aria-label="${label}"]`)!;
    await act(async()=>{input.value=value;input.dispatchEvent(new FocusEvent('focusout',{bubbles:true}));});
    expect(latest.buttonColors[channel+suffix]).toBe(value);expect(latest.customColors).toEqual(original.customColors);expect(latest.typography).toEqual(original.typography);
    edits.push({label:channel+suffix,section:structuredClone(latest)});edited.buttonColors={...edited.buttonColors,[channel+suffix]:value};
   }
  }
  if(geometry)for(const [field,label,values]of [['buttonRadius','Section button corners',['0','6','999']],['buttonSize','Section button size',['compact','normal','large']]] as const){
   const select=host.querySelector<HTMLSelectElement>(`[aria-label="${label}"]`)!;
   for(const value of values){await act(async()=>{select.value=value;select.dispatchEvent(new Event('change',{bubbles:true}));});expect(latest[field]).toBe(field==='buttonRadius'?Number(value):value);edits.push({label:field+'-'+value,section:structuredClone(latest)});}
   edited[field]=field==='buttonRadius'?999:'large';
  }
  const originalDom=document.createElement('div');originalDom.innerHTML=html(original);
  const editedDom=document.createElement('div');editedDom.innerHTML=html(edited);
  // New style overrides do not rewrite content, links, images or old palette values.
  const strip=(n:HTMLElement)=>{n.querySelectorAll('[data-cms-owned-appearance]').forEach(e=>e.remove());return n.textContent};
  expect(strip(editedDom),t.title+' editable copy preserved').toBe(strip(originalDom));
  const targets=Object.fromEntries(['primary','secondary'].map(c=>[c,originalDom.querySelectorAll(buttonTarget(c as any)).length]));
  rows.push({number:i+1,name:t.title,capability:cap,original,edited,edits,targets});
 }
 await act(async()=>{root.unmount();smallRoot.unmount()});host.remove();small.remove();vi.unstubAllGlobals();
 expect(rows).toHaveLength(161);writeFileSync(resolve(dir,'edit-fixtures.json'),JSON.stringify({rows},null,2));
},240000);
