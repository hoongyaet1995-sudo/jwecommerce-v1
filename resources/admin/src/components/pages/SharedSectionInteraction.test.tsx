// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect,vi} from 'vitest';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {AddSectionModal,SECTION_TEMPLATES} from './AddSectionModal';
import {SectionInspector} from './SectionInspector';
import {sharedSectionFamilies,createSharedSection} from '../../cms/sharedSectionLibrary';
import {connectedPassport} from '../../cms/connectedFamilies';
import {parseDraftImport} from '../../cms/pageImport';
import type {PageSection} from '../../types';
import {ConnectedFamilySection} from './ConnectedFamilySection';
import {UnifiedPricing} from './UnifiedPricing';
import {isUnifiedPricing} from '../../cms/unifiedPricing';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
mkdirSync('../../output/jw-cms-integration-20261114/section-library-20261116',{recursive:true});
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
const props={pageId:'sample',onMediaUpload:vi.fn(),onDelete:vi.fn(),onClose:vi.fn()};
it.each(sharedSectionFamilies)('inserts $id through the actual picker diagram step',async f=>{
  const node=document.createElement('div'),root=createRoot(node),add=vi.fn();
  await act(async()=>root.render(<AddSectionModal isOpen onClose={()=>{}} onAdd={add} categories={[]} collections={[]}/>));
  const card=[...node.querySelectorAll('button')].find(b=>b.querySelector('h3')?.textContent===f.title)!;
  await act(async()=>card.click());expect(node.querySelectorAll('[data-visual-choice] button')).toHaveLength(f.layouts.length);
  await act(async()=>(node.querySelector(`[data-choice-value="${f.layouts.at(-1)!.id}"]`) as HTMLButtonElement).click());
  await act(async()=>([...node.querySelectorAll('button')].find(b=>b.textContent==='Insert selected layout')!).click());
  expect(add).toHaveBeenCalledTimes(1);expect(add.mock.calls[0][0].type).toBe(f.type);
  await act(async()=>root.unmount());
});
it('keeps layout first for legacy and shared sections and switches without losing authored data',async()=>{
 const sources=[...sharedSectionFamilies.map(f=>createSharedSection(f.id,f.layouts[0].id)),{...SECTION_TEMPLATES.find(t=>t.title==='SaaS Packages')!.defaultData,id:'legacy-pricing'} as PageSection,{...SECTION_TEMPLATES.find(t=>t.title==='Editorial Quote')!.defaultData,id:'legacy-quote'} as PageSection];
 for(const source of sources){
  const node=document.createElement('div'),root=createRoot(node);let current=source;
  function Editor(){const [s,set]=useState(source);current=s;return <SectionInspector {...props} section={s} onChange={set}/>}
  await act(async()=>root.render(<Editor/>));await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('[role="tab"]')].find(b=>b.textContent==='design')!.click());
  const visible=[...node.querySelectorAll('details.cms-settings-group')].filter(n=>!n.closest('[hidden]'));
  expect(visible[0]?.classList.contains('cms-section-layout'),source.type).toBe(true);
  expect(node.querySelector('select[aria-label="Connected section layout"]')).toBeNull();
  const options=[...visible[0].querySelectorAll<HTMLButtonElement>('[data-choice-value]')];expect(options.length).toBeGreaterThan(0);
  const before=structuredClone(current);await act(async()=>options.at(-1)!.click());
  expect(current.title).toBe(before.title);expect(current.items).toEqual(before.items);expect(current.mediaUrl).toBe(before.mediaUrl);
  await act(async()=>root.unmount());
 }
});
it('canonicalizes and renders every newly insertable layout after edit and refresh',()=>{
 const base=JSON.parse(readFileSync(resolve(process.cwd(),'../../tests/Fixtures/Cms/page-document-all-sections.v1.json'),'utf8'));
 const docs=[];
 for(const f of sharedSectionFamilies)for(const l of f.layouts){
  const section=createSharedSection(f.id,l.id);const heading=isUnifiedPricing(section)||connectedPassport(section)?.textRoles.includes('title');if(heading)section.title=`Edited ${f.id} ${l.id}`;else if(section.items?.[0])section.items[0].title=`Edited ${f.id} item`;
  const doc={...base,title:section.title,slug:'accept-'+l.id,sections:[section],containerWidth:'site'};
  expect(()=>parseDraftImport(JSON.stringify(doc),'shared-library')).not.toThrow();
  const refreshed=JSON.parse(JSON.stringify(section));
  const html=renderToStaticMarkup(isUnifiedPricing(refreshed)?<UnifiedPricing section={refreshed} sections={[refreshed]}/>:<ConnectedFamilySection section={refreshed}/>);
  const dom=document.createElement('div');dom.innerHTML=html;expect(dom.textContent).toContain(heading?section.title:section.items![0].title);docs.push(doc);
 }
 writeFileSync(resolve(process.cwd(),'../../output/jw-cms-integration-20261114/section-library-20261116/documents.json'),JSON.stringify(docs,null,2));
});
