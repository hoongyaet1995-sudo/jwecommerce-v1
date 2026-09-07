// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ConnectedVariantModal} from './ConnectedVariantModal';
import {marketplaceOperations} from '../../integration/marketplaceOperations';
import {INITIAL_MARKETPLACE_STORE_PRODUCTS} from './fixtures';
import * as transport from '../../integration/marketplaceData';
import {renderToStaticMarkup} from 'react-dom/server';

it('renders missing channel price and stock as unknown without crashing or inventing zero',()=>{
  const product={...INITIAL_MARKETPLACE_STORE_PRODUCTS[0],id:'1:100',variants:[{
    ...INITIAL_MARKETPLACE_STORE_PRODUCTS[0].variants[0],id:'12',price:null,stock:null}]};
  const html=renderToStaticMarkup(<ConnectedVariantModal isOpen canImport storeProduct={product as never} variantId="12"
    products={[]} categories={[]} onClose={vi.fn()} onImported={vi.fn()} addToast={vi.fn()}/>);
  expect(html).toContain('Not provided');
  expect(html).not.toContain('NaN');
  expect(html).not.toContain('0.00');
});

it('unlinks the selected persisted listing through the mapping endpoint',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const api=vi.spyOn(transport,'marketplaceRequest').mockResolvedValue(undefined);
  const product={...INITIAL_MARKETPLACE_STORE_PRODUCTS[0],id:'1:100',variants:[{
    ...INITIAL_MARKETPLACE_STORE_PRODUCTS[0].variants[0],id:'12'}]};
  const close=vi.fn(),reload=vi.fn();const container=document.createElement('div');const root=createRoot(container);
  try {
    await act(async()=>root.render(<ConnectedVariantModal isOpen canImport storeProduct={product} variantId="12"
      products={[]} categories={[]} onClose={close} onImported={reload} addToast={vi.fn()}/>));
    await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Unlink Current Mapping'))!.click());
    expect(api).toHaveBeenCalledWith('/listings/12/mapping','DELETE',undefined);
    expect(reload).toHaveBeenCalledTimes(1);expect(close).toHaveBeenCalledTimes(1);
  } finally {await act(async()=>root.unmount());vi.restoreAllMocks();vi.unstubAllGlobals();}
});

it('submits the selected saved identity and closes only after API confirmation',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  let finish!:(value:{variantId:number;productId:number})=>void;
  const api=vi.spyOn(marketplaceOperations,'importVariant').mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));
  const product={...INITIAL_MARKETPLACE_STORE_PRODUCTS[0],id:'1:100',variants:[{
    ...INITIAL_MARKETPLACE_STORE_PRODUCTS[0].variants.at(-1)!,id:'12',channelSellerSku:'JW-TEST',mappingStatus:'unmapped' as const}]};
  const close=vi.fn(),reload=vi.fn();
  const container=document.createElement('div');document.body.append(container);const root=createRoot(container);
  const click=(label:string)=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes(label))!.click();
  try {
    await act(async()=>root.render(<ConnectedVariantModal isOpen canImport storeProduct={product} variantId="12"
      products={[]} categories={[]} onClose={close} onImported={reload} addToast={vi.fn()}/>));
    await act(async()=>click('Create Master SKU & Link'));
    await act(async()=>click('Confirm Master SKU Creation'));
    expect(api).toHaveBeenCalledWith('12',{newProduct:true,sku:'JW-TEST'});
    expect(close).not.toHaveBeenCalled();expect(reload).not.toHaveBeenCalled();
    await act(async()=>finish({variantId:92,productId:51}));
    expect(reload).toHaveBeenCalledTimes(1);expect(close).toHaveBeenCalledTimes(1);
  } finally {await act(async()=>root.unmount());container.remove();vi.restoreAllMocks();vi.unstubAllGlobals();}
});
