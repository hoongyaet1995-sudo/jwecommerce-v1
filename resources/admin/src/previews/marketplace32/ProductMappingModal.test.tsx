// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import {ProductMappingModal} from './components/marketplace/ProductMappingModal';
import {JourneyProvider, type MarketplaceJourneyContext} from './context/AdminContext';
import {INITIAL_PRODUCTS, INITIAL_CATEGORIES, INITIAL_MARKETPLACE_STORE_PRODUCTS} from './fixtures';

it('keeps a rejected unlink visible rather than closing with an unhandled error', async()=>{
  globalThis.IS_REACT_ACT_ENVIRONMENT=true;
  const container=document.createElement('div');const root=createRoot(container);
  const close=vi.fn(),toast=vi.fn();
  let reject!:(error:Error)=>void;
  const pending=new Promise<void>((_,fail)=>{reject=fail;});
  const value:MarketplaceJourneyContext={products:INITIAL_PRODUCTS,categories:INITIAL_CATEGORIES,
    connections:[],marketplaceStoreProducts:INITIAL_MARKETPLACE_STORE_PRODUCTS,addToast:toast,
    importStoreProductToMaster:vi.fn(),mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:()=>pending,
    publishMasterProductToStore:vi.fn(),setActiveSection:vi.fn()};
  const product=INITIAL_MARKETPLACE_STORE_PRODUCTS[0];
  try {
    await act(async()=>root.render(<JourneyProvider value={value}><ProductMappingModal isOpen onClose={close}
      storeProduct={product} storeVariant={product.variants[0]}/></JourneyProvider>));
    const button=Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Unlink Current Mapping'))!;
    await act(async()=>button.click());
    expect(close).not.toHaveBeenCalled();expect(button.disabled).toBe(true);
    await act(async()=>reject(new Error('Conflict')));
    expect(close).not.toHaveBeenCalled();expect(toast).toHaveBeenCalled();expect(button.disabled).toBe(false);
  } finally {await act(async()=>root.unmount());}
});

it('keeps the creation drawer open until the real operation resolves and shows rejection without closing', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const container = document.createElement('div');
  document.body.append(container);
  const root = createRoot(container);
  let reject!: (error: Error) => void;
  const operation = new Promise<void>((_, fail)=>{reject=fail;});
  const close = vi.fn(); const toast = vi.fn();
  const create = vi.fn(()=>operation);
  const value: MarketplaceJourneyContext = {products:INITIAL_PRODUCTS, categories:INITIAL_CATEGORIES,
    connections:[], marketplaceStoreProducts:INITIAL_MARKETPLACE_STORE_PRODUCTS,
    importStoreProductToMaster:create, mapStoreVariantToJwSku:vi.fn(), unmapStoreVariant:vi.fn(),
    publishMasterProductToStore:vi.fn(), setActiveSection:vi.fn(), addToast:toast};
  const product=INITIAL_MARKETPLACE_STORE_PRODUCTS[0];
  const button=(text:string)=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes(text))!;
  try {
    await act(async()=>root.render(<JourneyProvider value={value}><ProductMappingModal isOpen onClose={close}
      storeProduct={product} storeVariant={product.variants.at(-1)!}/></JourneyProvider>));
    await act(async()=>button('Create Master SKU & Link').click());
    await act(async()=>button('Confirm Master SKU Creation').click());
    expect(create).toHaveBeenCalledTimes(1);
    expect(close).not.toHaveBeenCalled();
    expect(button('Confirm Master SKU Creation').disabled).toBe(true);
    await act(async()=>reject(new Error('SKU already exists')));
    expect(close).not.toHaveBeenCalled();
    expect(toast).toHaveBeenCalled();
    expect(button('Confirm Master SKU Creation').disabled).toBe(false);
  } finally {await act(async()=>root.unmount());container.remove();}
});
