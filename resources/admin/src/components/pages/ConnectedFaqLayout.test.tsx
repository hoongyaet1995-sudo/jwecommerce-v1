import {readFileSync,writeFileSync,mkdirSync,existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {parseDraftImport} from '../../cms/pageImport';
// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from './SectionInspector';
import {ConnectedFamilySection} from './ConnectedFamilySection';
import {connectedPageIds,connectedPageSections} from '../../cms/connectedPageTemplates';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
const projectRoot=existsSync(resolve(process.cwd(),'tests/Fixtures/Cms/page-document-all-sections.v1.json'))?process.cwd():resolve(process.cwd(),'../..');
const faqs=connectedPageIds.flatMap(id=>connectedPageSections(id,{},'faq-qa')).filter(s=>s.type==='faq_accordion');
it('keeps both shared FAQ compositions at any count in the selected list layout',()=>{
 expect(new Set(faqs.map(s=>(s as any).connectedDiagram)).size).toBe(2);
 for(const source of faqs)for(const count of [0,1,4,5,6,8,9,24]){
 const s={...source,items:Array.from({length:count},(_,i)=>({...source.items![0],id:'faq-'+i,title:'Question '+i,description:'Answer '+i}))};
 const node=document.createElement('div');node.innerHTML=renderToStaticMarkup(<ConnectedFamilySection section={s}/>);
 expect(node.querySelector('.faq-grid.sample-collection-reflow')).toBeNull();expect(node.querySelectorAll('.faq-grid > details')).toHaveLength(count);
 }
});
it('reuses Item cards and retains FAQ metadata on add, reorder and delete',async()=>{
 const source=faqs[0],node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest=source;
 function Editor(){const[s,set]=useState(source);latest=s;return <SectionInspector pageId="qa" section={s} onChange={set} onClose={vi.fn()} onDelete={vi.fn()} onMediaUpload={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));const group=node.querySelector<HTMLElement>('[data-inspector-group="23"]')!;expect(group.hidden).toBe(false);
 const add=[...group.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add Card')!;expect(add).toBeTruthy();
 expect(node.querySelector('[data-connected-content]')?.textContent).not.toContain('Add item');const before=source.items!.map(i=>({...i}));await act(async()=>add.click());
 const item=latest.items!.at(-1)!;expect((item as any).layoutGroup).toBe('c0');expect((item as any).layoutVariant).toBe(0);expect(latest.items!.slice(0,-1)).toEqual(before);
 await act(async()=>group.querySelector<HTMLButtonElement>(`[aria-label="Move item ${latest.items!.length} up"]`)!.click());expect(latest.items!.at(-2)!.id).toBe(item.id);
 await act(async()=>group.querySelector<HTMLButtonElement>(`[data-inspector-item="${item.id}"] [title="Delete item"]`)!.click());expect(latest.items).toEqual(before);
 await act(async()=>root.unmount());node.remove();
});

it('offers the same nine FAQ choices and round trips native and legacy layouts without resetting data',async()=>{
 const {faqLayoutOptions,changeFaqLayout}=await import('../../cms/faqLayouts');
 expect(faqLayoutOptions).toHaveLength(9);
 for(const source of faqs){
  const original={...source,planName:'Keep plan',planAmount:299,faqInitiallyOpen:true,textAlignment:'right' as const,paddingY:'compact' as const};
  for(const [layout] of faqLayoutOptions){
   const next=changeFaqLayout(original,layout);expect(next.items).toEqual(original.items);expect(next.planName).toBe('Keep plan');
   const restored=changeFaqLayout(next,'faq-list');expect((restored as any).connectedDiagram).toBe((source as any).connectedDiagram);expect(restored.items).toEqual(original.items);
  }
 }
 const source=faqs[0],node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest=source;
 function Editor(){const[s,set]=useState(source);latest=s;return <SectionInspector pageId="qa" section={s} onChange={set} onClose={vi.fn()} onDelete={vi.fn()} onMediaUpload={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent?.trim().toLowerCase()==='design')!.click());
 for(const [value] of faqLayoutOptions)expect(node.querySelector(`[data-choice-value="${value}"]`)).not.toBeNull();
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="minimal"]')!.click());expect(latest.faqPreset).toBe('minimal');expect((latest as any).connectedDiagram).toBeUndefined();
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="faq-list"]')!.click());expect((latest as any).connectedDiagram).toBe((source as any).connectedDiagram);
 await act(async()=>root.unmount());node.remove();
});

it('canonicalizes every legacy/native FAQ transition including retained plan fields',async()=>{
 const {faqLayoutOptions,changeFaqLayout}=await import('../../cms/faqLayouts');
 const base=JSON.parse(readFileSync(resolve(projectRoot,'tests/Fixtures/Cms/page-document-all-sections.v1.json'),'utf8'));
 const docs=[];
 for(const source of faqs)for(const [layout] of faqLayoutOptions){
  const start={...source,planName:'Saved plan',planAmount:299,planCurrency:'MYR',planPeriod:'/ month',planDescription:'Saved plan detail',faqInitiallyOpen:true};
  for(const section of [changeFaqLayout(start,layout),changeFaqLayout(changeFaqLayout(start,layout),'faq-list')]){
   const doc={...base,title:'FAQ transition QA',slug:'faq-transition-'+docs.length,sections:[section]};expect(()=>parseDraftImport(JSON.stringify(doc),'faq-transition')).not.toThrow();docs.push(doc);
  }
 }
 mkdirSync(resolve(projectRoot,'output/faq-layout-20261117'),{recursive:true});writeFileSync(resolve(projectRoot,'output/faq-layout-20261117/layout-documents.json'),JSON.stringify(docs));
});
