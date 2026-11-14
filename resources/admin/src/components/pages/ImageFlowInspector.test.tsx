// @vitest-environment jsdom
import React,{act,useState} from 'react';import {createRoot} from 'react-dom/client';import {it,expect,vi} from 'vitest';
import type {PageSection} from '../../types';import {imageFlow} from '../../cms/imageFlow';import {SectionInspector} from './SectionInspector';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('exposes only three images and live heading/copy, retaining images across layout switches',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...imageFlow('qa'),imageSlots:[{target:'item:qa-image-1:imageUrl',mediaAlt:'Keep this alt'}]});latest=s;return <SectionInspector pageId="qa" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect([...node.querySelectorAll('[aria-label^="Edit Image"]')].filter(el=>!el.closest('[hidden]'))).toHaveLength(3);
 expect(node.querySelector('[data-inspector-item] input')).toBeNull();expect(node.textContent).not.toContain('Item Cards (3)');expect(node.querySelector('[placeholder="e.g. Shop Now"]')).toBeNull();
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!;await act(async()=>design.click());
 await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="image-flow"]')!.click());
 expect(latest!.imageSlots?.[0].mediaAlt).toBe('Keep this alt');expect(latest!.items).toHaveLength(3);await act(async()=>root.unmount());node.remove();
});
