// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import {PreviewContext} from '../../context/ContextBridge';
import {MarketplaceMappingPage} from './MarketplaceMappingPage';

it('keeps a server page visible on page two without slicing it a second time', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const container=document.createElement('div'); const root=createRoot(container);
  const query=vi.fn();
  const context={products:[],connections:[],marketplaceOrders:[],marketplaceInventory:[],syncLogs:[],
    marketplaceStoreProducts:[{id:'1:p',connectionId:'1',platform:'shopee',shopName:'Sandbox',channelProductId:'p',title:'SERVER PAGE PRODUCT',
      updatedAt:'2026-09-06T10:00:00Z',variants:[{id:'1',channelSkuId:'v',channelSellerSku:'REAL-SKU',title:'Blue',price:10,currency:'MYR',stock:7,mappingStatus:'unmapped'}]}],
    currentSection:'marketplace-mapping',setCurrentSection:vi.fn(),addToast:vi.fn()};
  await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
    <MarketplaceMappingPage serverPage={{total:320,summary:{products:320,variants:640,mapped:320,unmapped:320},loading:false,onQueryChange:query}}/>
  </PreviewContext.Provider>));
  const next=Array.from(container.querySelectorAll('.lucide-chevron-right')).at(-1)?.closest('button');
  expect(container.querySelector<HTMLButtonElement>('#btn-push-master-to-store')!.disabled).toBe(true);
  expect(next).toBeTruthy();
  await act(async()=>next!.click());
  expect(container.textContent).toContain('SERVER PAGE PRODUCT');
  expect(container.textContent).toContain('Page 2 of 22');
  expect(query).toHaveBeenLastCalledWith(expect.objectContaining({page:2,per_page:15,view:'grouped'}));
  const importProducts=vi.fn().mockResolvedValue([{id:'1:p',ok:true,productId:12,status:'draft'}]);
  await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
    <MarketplaceMappingPage serverPage={{total:320,summary:{products:320,variants:640,mapped:320,unmapped:320},loading:false,onQueryChange:query,canImport:true,onImport:importProducts}}/>
  </PreviewContext.Provider>));
  const importButton=Array.from(container.querySelectorAll('button')).find(button=>button.textContent?.includes('Import Product to JW Master'))!;
  expect(importButton.disabled).toBe(false);
  await act(async()=>importButton.click());
  expect(importProducts).toHaveBeenCalledWith(['1:p']);
  const previewModal=vi.fn(()=>null);
  await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
    <MarketplaceMappingPage renderVariantModal={previewModal} serverPage={{total:320,summary:{products:320,variants:640,mapped:320,unmapped:320},loading:false,onQueryChange:query}}/>
  </PreviewContext.Provider>));
  await act(async()=>container.querySelector<HTMLButtonElement>('#btn-link-spec-1')!.click());
  expect(previewModal).toHaveBeenCalledWith(expect.objectContaining({storeProduct:expect.objectContaining({id:'1:p'}),storeVariant:expect.objectContaining({id:'1'})}),expect.any(Function));
  const reviewPublishing=vi.fn();
  await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
    <MarketplaceMappingPage serverPage={{total:320,summary:{products:320,variants:640,mapped:320,unmapped:320},loading:false,onQueryChange:query,onReviewPublishing:reviewPublishing}}/>
  </PreviewContext.Provider>));
  const publishReview=container.querySelector<HTMLButtonElement>('#btn-push-master-to-store')!;
  expect(publishReview.disabled).toBe(false);
  expect(publishReview.title).toContain('read-only');
  await act(async()=>publishReview.click());
  expect(reviewPublishing).toHaveBeenCalledWith(undefined);
  await act(async()=>root.render(<PreviewContext.Provider value={context as unknown as React.ContextType<typeof PreviewContext>}>
    <MarketplaceMappingPage renderVariantModal={previewModal} serverPage={{total:320,summary:{products:320,variants:640,mapped:320,unmapped:320},loading:false,onQueryChange:query,canImport:true}}/>
  </PreviewContext.Provider>));
  const importVariant=container.querySelector<HTMLButtonElement>('#btn-import-spec-1')!;
  expect(importVariant.disabled).toBe(false);
  await act(async()=>importVariant.click());
  expect(previewModal).toHaveBeenLastCalledWith(expect.objectContaining({intent:'import',storeVariant:expect.objectContaining({id:'1'})}),expect.any(Function));
  await act(async()=>root.unmount()); vi.unstubAllGlobals();
});
