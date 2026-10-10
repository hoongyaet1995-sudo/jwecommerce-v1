// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {describe,it,expect} from 'vitest';
import {EditorialProductTabs} from './EditorialProductTabs';
import {ProductTabsSettings} from './ProductTabsSettings';
import {ProductListingSettings} from './ProductListingSettings';
import {renderToStaticMarkup} from 'react-dom/server';
import {duplicateSection} from '../../cms/duplicateSection';
import type {PageSection} from '../../types';
(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
const section:PageSection={id:'products',type:'product_listing',title:'Made for living',isVisible:true,listingPreset:'editorial-tabs',productLimit:4,productTabs:[{id:'all',label:'Best Sellers',sourceType:'all'},{id:'lighting',label:'Lighting',sourceType:'category',sourceId:'4'}]};
const product=(id:string,title:string)=>({id,title,slug:id,brand:'JW',summary:'',images:['/product.jpg'],tags:[],basePrice:49,baseCompareAtPrice:null});
describe('Editorial Product Tabs',()=>{
 it('switches runtime products and preserves selected identity on reorder, with keyboard and empty states',()=>{
  const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
  const facts={tabs:[{sourceId:{valid:true},source:null,products:[product('chair','Chair')]},{sourceId:{valid:true},source:null,products:[product('lamp','Lamp')]}]};
  const render=(s=section,f=facts)=>root.render(<EditorialProductTabs section={s} facts={f} context={{mode:'preview',currency:'MYR'}}/>);
  act(()=>render());expect(host.querySelector('[role=tabpanel]')?.textContent).toContain('Chair');
  act(()=>host.querySelectorAll<HTMLButtonElement>('[role=tab]')[1].click());expect(host.querySelector('[role=tabpanel]')?.textContent).toContain('Lamp');
  act(()=>render({...section,productTabs:[...section.productTabs!].reverse()},{tabs:[...facts.tabs].reverse()}));expect(host.querySelector('[role=tabpanel]')?.textContent).toContain('Lamp');
  act(()=>host.querySelectorAll('[role=tab]')[0].dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true})));expect(host.querySelector('[role=tabpanel]')?.textContent).toContain('Chair');
  act(()=>render({...section,productTabs:[]},{tabs:[]}));expect(host.textContent).toContain('Add a product tab');act(()=>root.unmount());host.remove();
 });
 it('provides sparse add/edit/source/reorder/delete controls without dropping data on preset changes',()=>{
  let s={...section,productTabs:[]} as PageSection;const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
  const render=()=>root.render(<ProductTabsSettings section={s} categories={[]} collections={[]} products={[]} onChange={value=>{s=value;render();}}/>);
  act(render);const button=(text:string)=>[...host.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent===text)!;
  act(()=>button('Add tab').click());act(()=>button('Add tab').click());expect(s.productTabs).toHaveLength(2);expect(new Set(s.productTabs!.map(t=>t.id)).size).toBe(2);
  const set=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!;const name=host.querySelector<HTMLInputElement>('[aria-label="Tab name"]')!;
  act(()=>{set.call(name,'Lighting edit');name.dispatchEvent(new Event('input',{bubbles:true}));});expect(s.productTabs![1].label).toBe('Lighting edit');
  act(()=>{const select=host.querySelector<HTMLSelectElement>('[aria-label="Show products from"]')!;select.value='all';select.dispatchEvent(new Event('change',{bubbles:true}));});expect(s.productTabs![1].sourceType).toBe('all');
  act(()=>button('Move up').click());expect(s.productTabs![0].label).toBe('Lighting edit');act(()=>host.querySelector<HTMLButtonElement>('[aria-label="Delete tab"]')!.click());expect(s.productTabs).toHaveLength(1);
  act(()=>root.render(<ProductListingSettings section={s} categories={[]} collections={[]} products={[]} mode="design" onChange={value=>{s=value;}}/>));act(()=>button('Editorial Grid').click());expect(s.productTabs).toHaveLength(1);expect(s.listingPreset).toBe('editorial-grid');
  const copy=duplicateSection(s);expect(copy.productTabs![0].id).not.toBe(s.productTabs![0].id);act(()=>root.unmount());host.remove();
 });
 it('makes unavailable sources visible and keeps preview wishlist inert',()=>{const html=renderToStaticMarkup(<EditorialProductTabs section={section} facts={{tabs:[{sourceId:{valid:false},source:null,products:[]}]}} context={{mode:'preview',currency:'MYR'}}/>);expect(html).toContain('unavailable');expect(html).not.toContain('<form');});
});
