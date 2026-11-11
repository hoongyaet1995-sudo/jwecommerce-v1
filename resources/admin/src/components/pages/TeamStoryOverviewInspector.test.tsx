// @vitest-environment jsdom
import React,{act,useState} from 'react';import {createRoot} from 'react-dom/client';import {it,expect,vi} from 'vitest';
import type {PageSection} from '../../types';import {teamStoryOverview} from '../../cms/teamStoryOverview';import {SectionInspector} from './SectionInspector';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('exposes one picture and editable icon labels, retaining images across layout switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState({...teamStoryOverview('qa'),imageSlots:[{target:'mediaUrl',mediaAlt:'Keep this alt'}]});latest=s;return <SectionInspector pageId="qa" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect([...node.querySelectorAll('[aria-label="Choose icon"]')].filter(el=>!el.closest('[hidden]'))).toHaveLength(3);
 const add=[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()==='Add label')!;await act(async()=>add.click());expect(latest!.items).toHaveLength(4);await act(async()=>node.querySelectorAll<HTMLButtonElement>('[title="Delete item"]')[3].click());expect(latest!.items).toHaveLength(3);
 expect(node.querySelector('[data-inspector-item] input')).not.toBeNull();expect(node.querySelector('[placeholder="e.g. Shop Now"]')).toBeNull();
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="team-story-overview"]')!.click());
 expect(latest!.imageSlots?.[0].mediaAlt).toBe('Keep this alt');expect(latest!.items).toHaveLength(3);await act(async()=>root.unmount());node.remove();
});
