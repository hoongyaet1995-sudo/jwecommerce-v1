// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ConnectedImportReviewModal} from './ConnectedImportReviewModal';
import {marketplaceOperations} from '../../integration/marketplaceOperations';
import {INITIAL_MARKETPLACE_STORE_PRODUCTS,INITIAL_PRODUCTS} from './fixtures';

it('submits saved catalog ids and refreshes only after confirmed whole import',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  let finish!:(value:{productId:number;status:string})=>void;
  const api=vi.spyOn(marketplaceOperations,'importProduct').mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));
  const product={...INITIAL_MARKETPLACE_STORE_PRODUCTS[0],id:'1:100',variants:[{
    ...INITIAL_MARKETPLACE_STORE_PRODUCTS[0].variants.at(-1)!,id:'12',channelSellerSku:'JW-TEST'}]};
  const reload=vi.fn();const container=document.createElement('div');const root=createRoot(container);
  try {
    await act(async()=>root.render(<ConnectedImportReviewModal productsToImport={[product]} products={[]}
      canImport onClose={vi.fn()} onImported={reload} addToast={vi.fn()}/>));
    await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Confirm Import'))!.click());
    expect(api).toHaveBeenCalledWith('12',{skuOverrides:{}});expect(reload).not.toHaveBeenCalled();
    await act(async()=>finish({productId:51,status:'draft'}));
    expect(reload).toHaveBeenCalledTimes(1);expect(container.textContent).toContain('Import Completed');
  }finally{await act(async()=>root.unmount());vi.restoreAllMocks();vi.unstubAllGlobals();}
});

it('reviews existing mappings and appends new variants to the selected parent',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const api=vi.spyOn(marketplaceOperations,'importProduct').mockResolvedValue({productId:52,status:'active',createdVariants:1,linkedVariants:0,keptVariants:1});
  const parent={...INITIAL_PRODUCTS[0],id:'52',title:'Selected parent',status:'active' as const,variants:[{...INITIAL_PRODUCTS[0].variants[0],id:'92',sku:'EXISTING'}]};
  const sample=INITIAL_MARKETPLACE_STORE_PRODUCTS[0];
  const product={...sample,id:'2:100',variants:[
    {...sample.variants[0],id:'12',channelSellerSku:'EXISTING',mappedJwProductId:'52',mappedJwVariantId:'92'},
    {...sample.variants[0],id:'13',channelSellerSku:'NEW',mappedJwProductId:undefined,mappedJwVariantId:undefined}]};
  const container=document.createElement('div');const root=createRoot(container);
  try {
    await act(async()=>root.render(<ConnectedImportReviewModal productsToImport={[product]} products={[parent,{...parent,id:'53',title:'Other destination',variants:[]}]} canImport onClose={vi.fn()} onImported={vi.fn()} addToast={vi.fn()}/>));
    const select=container.querySelector('select[aria-label="Batch import destination"]') as HTMLSelectElement;
    expect(select).not.toBeNull();
    await act(async()=>{select.value='53';select.dispatchEvent(new Event('change',{bubbles:true}));});
    expect(container.textContent).toContain('Conflict: belongs to #52');
    expect((Array.from(container.querySelectorAll('button')).find(b=>b.textContent==='Confirm Append & Link') as HTMLButtonElement).disabled).toBe(true);
    expect(api).not.toHaveBeenCalled();
    await act(async()=>{select.value='52';select.dispatchEvent(new Event('change',{bubbles:true}));});
    expect(container.textContent).toContain('1 retained');
    expect(container.textContent).toContain('1 new');
    expect(container.textContent).toContain('published');
    await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent==='Confirm Append & Link')!.click());
    expect(api).toHaveBeenCalledWith('12',{productId:52,listingIds:[12,13],skuOverrides:{}});
    expect(container.textContent).toContain('1 retained');
  } finally {await act(async()=>root.unmount());vi.restoreAllMocks();vi.unstubAllGlobals();}
});
