// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import { PageRuntimeProvider } from '../PageRuntimeContext';
import { SectionRenderer } from '../../components/pages/SectionRenderer';
(globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;

afterEach(() => { vi.useRealTimers(); document.body.innerHTML = ''; });

it('uses PLP light surfaces for Nordic white canvas without changing the dark palette or carousel structure', () => {
  const section = {id:'listing',type:'product_listing',title:'Shop',isVisible:true,autoSlide:false,backgroundColor:'bg-white'} as any;
  const product = {id:'1',title:'Item',slug:'item',brand:'Brand',summary:'Description',images:[],tags:[],basePrice:10,baseCompareAtPrice:null};
  const payload = {page:{document:{sections:[section]}},context:{mode:'preview',currency:'MYR'},runtime:{sections:{0:{listing:{products:[product]}}}}} as any;
  const container=document.createElement('div'); document.body.append(container); const root=createRoot(container);
  act(()=>root.render(<PageRuntimeProvider payload={payload}><SectionRenderer section={section}/></PageRuntimeProvider>));
  const card=container.querySelector('[data-product-listing-track] > div > div')!;
  const productImage=card.querySelector('img')!;
  expect(productImage.classList.contains('object-cover')).toBe(true);
  expect(productImage.parentElement!.classList.contains('aspect-square')).toBe(true);
  expect(productImage.parentElement!.classList.contains('p-6')).toBe(false);
  expect(productImage.parentElement!.classList.contains('sm:h-64')).toBe(false);
  expect(card.classList.contains('bg-white')).toBe(true);
  expect(card.classList.contains('border-zinc-200')).toBe(true);
  expect(card.classList.contains('shadow-sm')).toBe(true);
  expect(card.querySelector('h3')!.classList.contains('text-zinc-900')).toBe(true);
  const lightStructure=[...card.querySelectorAll('*')].map(el=>el.tagName);
  section.backgroundColor='bg-neutral-950';
  act(()=>root.render(<PageRuntimeProvider payload={payload}><SectionRenderer section={{...section}}/></PageRuntimeProvider>));
  expect(card.classList.contains('bg-[#121316]')).toBe(true);
  expect([...card.querySelectorAll('*')].map(el=>el.tagName)).toEqual(lightStructure);
  act(()=>root.unmount());
});

it('auto slides using actual item count and pauses while hovered', () => {
  vi.useFakeTimers();
  const section = {id:'listing',type:'product_listing',title:'Shop',isVisible:true,productLimit:12,autoSlide:true,autoSlideInterval:3} as any;
  const products = [1,2].map(id => ({id:String(id),title:`Item ${id}`,slug:`item-${id}`,brand:'',summary:'',images:[],tags:[],basePrice:10,baseCompareAtPrice:null}));
  const payload = {page:{document:{sections:[section]}},context:{mode:'preview',currency:'MYR'},runtime:{sections:{0:{listing:{sourceId:{valid:true},source:null,products}}}},links:{}} as any;
  const container=document.createElement('div'); document.body.append(container); const root=createRoot(container);
  act(()=>root.render(<PageRuntimeProvider payload={payload}><SectionRenderer section={section} viewport="mobile"/></PageRuntimeProvider>));
  const track=container.querySelector('[data-product-listing-track]') as HTMLElement;
  expect(track.style.transform).toBe('translateX(-0%)');
  act(()=>vi.advanceTimersByTime(3000)); expect(track.style.transform).toBe('translateX(-100%)');
  act(()=>vi.advanceTimersByTime(3000)); expect(track.style.transform).toBe('translateX(-0%)');
  act(()=>container.querySelector('.max-w-7xl')!.dispatchEvent(new MouseEvent('mouseover',{bubbles:true})));
  act(()=>vi.advanceTimersByTime(6000)); expect(track.style.transform).toBe('translateX(-0%)');
  act(()=>root.unmount());
});

it('renders real listing facts without invented discount and gates Quick Add in preview', () => {
  const section = { id:'listing', type:'product_listing', title:'Real products', isVisible:true, productLimit:12, autoSlide:false } as any;
  const product = {id:'1',title:'Real Item',slug:'real',brand:'',summary:'',images:[],tags:[],basePrice:10,baseCompareAtPrice:null,commerce:{variantId:'2',sku:'REAL-SKU',price:12,compareAtPrice:null,optionLabels:[],canChoose:true}};
  const payload = { page:{document:{sections:[section]}}, context:{mode:'public',currency:'MYR (RM)'}, runtime:{sections:{0:{listing:{sourceId:{valid:true},source:null,products:[product]}}}},links:{},forms:{contact:{},newsletter:{}} } as any;
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  act(() => root.render(<PageRuntimeProvider payload={payload}><SectionRenderer section={section}/></PageRuntimeProvider>));
  expect(container.textContent).toContain('Real Item');
  expect(container.textContent).not.toContain('SAVE');
  expect(container.textContent).not.toContain('Aurora Design');
  expect(container.querySelector('a[href="/products/real"] img')).not.toBeNull();
  expect(container.querySelector('a[href="/products/real"] h3')).not.toBeNull();
  expect(container.querySelector('[data-quick-add-trigger]')?.getAttribute('data-quick-add-product-id')).toBe('1');
  payload.context.mode = 'preview';
  act(() => root.render(<PageRuntimeProvider payload={{...payload}}><SectionRenderer section={section}/></PageRuntimeProvider>));
  expect(container.querySelector('[data-quick-add-trigger]')).toBeNull();
  expect(container.querySelector('a[href="/products/real"]')).toBeNull();
  act(() => root.unmount());
});

it('selects real variants and passes the selected SKU to Quick Add; single SKU uses the existing cart form', () => {
  const section={id:'listing',type:'product_listing',title:'Shop',isVisible:true,autoSlide:false} as any;
  const variants=[{id:'2',label:'Black',sku:'BLACK',price:12,compareAtPrice:15,stock:3,enabled:true,image:null},{id:'3',label:'Silver',sku:'SILVER',price:20,compareAtPrice:null,stock:0,enabled:true,image:'/silver.png'}];
  const product={id:'1',title:'Item',slug:'item',images:[],brand:'',summary:'',tags:[],basePrice:12,baseCompareAtPrice:null,commerce:{variantId:'2',sku:'BLACK',price:12,compareAtPrice:15,optionLabels:[],canChoose:true,variants}};
  const payload={page:{document:{sections:[section]}},context:{mode:'public',currency:'MYR'},runtime:{sections:{0:{listing:{products:[product]}}}}} as any;
  const container=document.createElement('div'); document.body.append(container); const root=createRoot(container);
  act(()=>root.render(<PageRuntimeProvider payload={payload}><SectionRenderer section={section}/></PageRuntimeProvider>));
  const silver=container.querySelector('button[aria-label="Select Silver"]');
  expect(silver).not.toBeNull();
  act(()=>silver!.dispatchEvent(new MouseEvent('click',{bubbles:true})));
  expect(container.textContent).toContain('SKU: SILVER');
  expect(container.textContent).not.toContain('SAVE');
  expect(container.querySelector('[data-quick-add-trigger]')?.getAttribute('data-quick-add-variant-id')).toBe('3');
  expect(container.querySelector('a img')?.getAttribute('src')).toBe('/silver.png');
  product.commerce.variants=[variants[0]];
  act(()=>root.render(<PageRuntimeProvider payload={{...payload}}><SectionRenderer section={section}/></PageRuntimeProvider>));
  expect(container.querySelector('form[action="/cart/items"] input[name="variant_id"]')?.getAttribute('value')).toBe('2');
  expect(container.querySelector('[data-quick-add-trigger]')).toBeNull();
  act(()=>root.unmount());
});
