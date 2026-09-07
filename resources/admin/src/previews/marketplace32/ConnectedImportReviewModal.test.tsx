// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ConnectedImportReviewModal} from './ConnectedImportReviewModal';
import {marketplaceOperations} from '../../integration/marketplaceOperations';
import {INITIAL_MARKETPLACE_STORE_PRODUCTS} from './fixtures';

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
