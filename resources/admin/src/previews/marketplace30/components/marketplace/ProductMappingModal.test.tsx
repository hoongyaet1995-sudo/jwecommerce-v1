// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import {PreviewContext} from '../../context/ContextBridge';
import {ProductMappingModal} from './ProductMappingModal';
import type {MarketplaceStoreProduct, MarketplaceStoreVariant} from '../../types';

it('blocks confirmation while the server checks conflicts outside the visible page',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const container=document.createElement('div');const root=createRoot(container);
 const save=vi.fn();let finish!:(result:any)=>void;
 const check=vi.fn(()=>new Promise<any>(resolve=>{finish=resolve;}));
 const variant={id:'1',mappingStatus:'mapped',mappedJwProductId:'2',mappedJwVariantId:'3',price:10,currency:'MYR'} as MarketplaceStoreVariant;
 const product={id:'p',connectionId:'1',platform:'shopee',variants:[variant]} as MarketplaceStoreProduct;
 const context={products:[{id:'2',title:'JW Product',basePrice:10,variants:[{id:'3',title:'Blue',sku:'LOCAL',price:10,stock:5}]}],categories:[],marketplaceStoreProducts:[],mapStoreVariantToJwSku:save,addToast:vi.fn()};
 try{
  await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
   <ProductMappingModal isOpen onClose={vi.fn()} storeProduct={product} storeVariant={variant} checkConflict={check}/>
  </PreviewContext.Provider>));
  const confirm=container.querySelector<HTMLButtonElement>('#btn-confirm-mapping')!;
  expect(confirm.disabled).toBe(true);expect(check).toHaveBeenCalledWith('1','3');
  await act(async()=>finish({storeProductTitle:'Outside this page',storeVariantTitle:'Red',channelSku:'TAKEN',variantId:'88'}));
  expect(container.textContent).toContain('Outside this page');expect(confirm.disabled).toBe(true);
  await act(async()=>confirm.click());expect(save).not.toHaveBeenCalled();
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});

it.each(['#btn-confirm-mapping','#btn-unlink-variant'])('keeps %s open until persistence succeeds and prevents duplicate submission', async selector => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const container = document.createElement('div');
  const root = createRoot(container);
  const close = vi.fn();
  let finish!: (value: boolean) => void;
  const save = vi.fn(() => new Promise<boolean>(resolve => {finish = resolve;}));
  const context = {products:[],categories:[],marketplaceStoreProducts:[],mapStoreVariantToJwSku:save,
    unmapStoreVariant:save,importStoreProductToMaster:vi.fn(),addToast:vi.fn()};
  const variant = {id:'1',title:'Blue',channelSellerSku:'SKU',channelSkuId:'v',price:10,currency:'MYR',stock:7,
    mappingStatus:'mapped',mappedJwProductId:'2',mappedJwVariantId:'3'} as MarketplaceStoreVariant;
  const product = {id:'p',connectionId:'1',platform:'shopee',shopName:'Shop',title:'Product',variants:[variant]} as MarketplaceStoreProduct;
  await act(async () => root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
    <ProductMappingModal isOpen onClose={close} storeProduct={product} storeVariant={variant}/>
  </PreviewContext.Provider>));
  const button = container.querySelector<HTMLButtonElement>(selector)!;
  await act(async () => {button.click(); button.click();});
  expect(close).not.toHaveBeenCalled();
  expect(save).toHaveBeenCalledTimes(1);
  expect(button.disabled).toBe(true);
  await act(async () => finish(false));
  expect(close).not.toHaveBeenCalled();
  expect(button.disabled).toBe(false);
  save.mockRejectedValueOnce(new Error('Private backend detail'));
  await act(async () => button.click());
  expect(close).not.toHaveBeenCalled();
  expect(context.addToast).toHaveBeenCalledWith({type:'error',title:'Mapping failed',message:'The mapping could not be saved. Please retry.'});
  expect(button.disabled).toBe(false);
  await act(async () => button.click());
  await act(async () => finish(true));
  expect(close).toHaveBeenCalledTimes(1);
  await act(async () => root.unmount());
  vi.unstubAllGlobals();
});
