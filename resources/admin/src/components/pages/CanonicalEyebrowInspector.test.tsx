// @vitest-environment jsdom
import React,{act,useState} from 'react';import {createRoot} from 'react-dom/client';import {it,expect,vi} from 'vitest';
import {SectionInspector} from './SectionInspector';import type {PageSection} from '../../types';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(n:number)=>String(n)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('offers one empty-capable canonical Eyebrow writer and live typography, without duplicate legacy color control',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({id:'qa',type:'media_gallery',galleryPreset:'image-flow',title:'Heading',isVisible:true});latest=s;return <SectionInspector pageId="qa" section={s} onChange={set} onClose={vi.fn()} onDelete={vi.fn()} onMediaUpload={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));const input=host.querySelector<HTMLInputElement>('[data-inspector-field=eyebrow]')!;expect(input).not.toBeNull();expect(host.querySelector('[data-inspector-field=subtitle]')).toBeNull();
 await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,'A real label');input.dispatchEvent(new Event('input',{bubbles:true}));});expect(latest!.eyebrow).toBe('A real label');
 const design=[...host.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!;await act(async()=>design.click());const role=host.querySelector<HTMLButtonElement>('[aria-label="Edit Eyebrow"]')!;expect(role).not.toBeNull();await act(async()=>role.click());
 const size=host.querySelector<HTMLInputElement>('[aria-label="Text size slider"]')!;await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(size,'22');size.dispatchEvent(new Event('input',{bubbles:true}));});expect(latest!.typography?.eyebrow?.fontSize).toBe(22);
 const weight=host.querySelector<HTMLButtonElement>('[aria-label="Text weight 700"]')!;await act(async()=>weight.click());expect(latest!.typography?.eyebrow?.fontWeight).toBe(700);
 expect(host.querySelector('[data-legacy-eyebrow-color]')).toBeNull();await act(async()=>root.unmount());host.remove();
});
