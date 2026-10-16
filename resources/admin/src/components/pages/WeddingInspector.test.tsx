// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from './SectionInspector';
import type {PageSection} from '../../types';
import {parseDraftImport} from '../../cms/pageImport';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(value:number)=>String(value)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
it('switches sparse gallery A B A and add reorder delete preserve canonical image identities',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 let latest:PageSection={id:'gallery',type:'media_gallery',title:'Gallery',isVisible:true,galleryPreset:'photo-marquee',items:[]};
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 function Harness(){const [section,setSection]=useState(latest);return <SectionInspector pageId="test" section={section} onChange={s=>{latest=s;setSection(s)}} onMediaUpload={vi.fn()} onDelete={vi.fn()} onClose={vi.fn()}/>}
 await act(async()=>root.render(<Harness/>));
 const click=async(selector:string)=>{const button=node.querySelector<HTMLButtonElement>(selector);expect(button,selector).not.toBeNull();await act(async()=>button!.click())};
 await act(async()=>node.querySelectorAll<HTMLButtonElement>('[role="tab"]')[1].click());
 await click('[aria-label="Collection layout preset"] [data-choice-value="portfolio-cards"]');
 await click('[aria-label="Collection layout preset"] [data-choice-value="photo-marquee"]');
 await act(async()=>node.querySelectorAll<HTMLButtonElement>('[role="tab"]')[0].click());
 const add=()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.includes('Add Photo'))!;
 await act(async()=>add().click());await act(async()=>add().click());expect(latest.items).toHaveLength(2);
 const ids=latest.items!.map(i=>i.id);await click('[aria-label="Move item 1 down"]');expect(latest.items!.map(i=>i.id)).toEqual(ids.reverse());
 await click('[title="Delete item"]');expect(latest.items).toHaveLength(1);
 const doc={title:'Test',slug:'test',template:'standard',sections:[latest]};expect(parseDraftImport(JSON.stringify(doc),'trusted')).toEqual(doc);
 await act(async()=>root.unmount());node.remove();
});
