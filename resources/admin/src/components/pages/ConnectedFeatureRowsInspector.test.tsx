// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import type {PageSection} from '../../types';
import {connectedFeatureRows} from '../../cms/connectedFeatureRows';
import {SectionInspector} from './SectionInspector';
import {AddSectionModal} from './AddSectionModal';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('creates independent section-scoped card identities from the registered neutral starter',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);const add=vi.fn();await act(async()=>root.render(<AddSectionModal isOpen onAdd={add} onClose={vi.fn()} categories={[]} collections={[]}/>));const button=[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.querySelector('h3')?.textContent==='Connected Feature Rows')!;await act(async()=>button.click());await act(async()=>button.click());const a=add.mock.calls[0][0] as PageSection,b=add.mock.calls[1][0] as PageSection;expect(a.id).not.toBe(b.id);expect(a.items).toHaveLength(3);expect(a.items!.every(i=>i.id.startsWith(a.id))).toBe(true);expect(b.items!.some(i=>a.items!.some(j=>i.id===j.id))).toBe(false);await act(async()=>root.unmount());node.remove();
});
it('offers three sparse editable slots without structural or unsupported controls and retains authored data on A/B/A',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);let latest:PageSection;
 function Editor(){const [s,set]=useState<PageSection>({...connectedFeatureRows('sparse'),items:[]});latest=s;return <SectionInspector pageId="test" section={s} onChange={set} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>;}
 await act(async()=>root.render(<Editor/>));expect(node.querySelectorAll('[data-inspector-item]')).toHaveLength(3);expect(node.querySelector('[title="Delete item"]')).toBeNull();expect(node.querySelector('[aria-label="Move item 1 down"]')).toBeNull();expect([...node.querySelectorAll('button')].some(b=>b.textContent?.trim()==='Add Card')).toBe(false);expect([...node.querySelectorAll('[data-inspector-item] label')].filter(l=>l.textContent==='Button label')).toHaveLength(3);expect([...node.querySelectorAll('[data-inspector-item] label')].filter(l=>l.textContent==='Destination')).toHaveLength(3);expect(node.querySelector('[aria-label="Choose icon"]')).toBeNull();
 const title=node.querySelector<HTMLInputElement>('[data-inspector-item] input[placeholder="Title / Name"]')!;expect(title).not.toBeNull();await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(title,'Editable card');title.dispatchEvent(new Event('input',{bubbles:true}));});expect(latest!.items![0].title).toBe('Editable card');expect(latest!.items).toHaveLength(3);
 const design=[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(b=>b.textContent==='design')!;await act(async()=>design.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="default"]')!.click());await act(async()=>node.querySelector<HTMLButtonElement>('[data-choice-value="connected-feature-rows"]')!.click());expect(latest!.items![0].title).toBe('Editable card');expect(node.querySelector('[title="Button Background"]')).toBeNull();await act(async()=>root.unmount());node.remove();
});
