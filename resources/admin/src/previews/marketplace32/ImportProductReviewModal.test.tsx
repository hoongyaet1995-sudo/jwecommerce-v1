// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ImportProductReviewModal} from './components/marketplace/ImportProductReviewModal';
import {JourneyProvider,type MarketplaceJourneyContext} from './context/AdminContext';
import {INITIAL_MARKETPLACE_STORE_PRODUCTS} from './fixtures';

it('does not claim batch completion on failure and retries only unfinished products',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const submit=vi.fn().mockResolvedValueOnce(undefined).mockRejectedValueOnce(new Error('Conflict')).mockResolvedValueOnce(undefined);
  const success=vi.fn(),toast=vi.fn();
  const context:MarketplaceJourneyContext={products:[],categories:[],connections:[],marketplaceStoreProducts:[],
    importStoreProductToMaster:submit,addToast:toast,setActiveSection:vi.fn(),publishMasterProductToStore:vi.fn(),
    mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()};
  const container=document.createElement('div');const root=createRoot(container);
  const products=INITIAL_MARKETPLACE_STORE_PRODUCTS.slice(0,2);
  const confirm=()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Confirm Import'))!.click();
  try {
    await act(async()=>root.render(<JourneyProvider value={context}><ImportProductReviewModal isOpen onClose={vi.fn()} productsToImport={products} onSuccess={success}/></JourneyProvider>));
    await act(async()=>confirm());
    expect(success).not.toHaveBeenCalled();expect(toast).toHaveBeenCalledWith(expect.objectContaining({message:expect.stringContaining('Conflict')}));
    expect(container.textContent).not.toContain('Import Completed');
    await act(async()=>confirm());
    expect(submit).toHaveBeenCalledTimes(3);
    expect(submit.mock.calls[2][0]).toBe(products[1].id);
    expect(success).toHaveBeenCalledTimes(1);expect(container.textContent).toContain('Import Completed');
  }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});

it('reports an all-existing result without claiming a new draft',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const context:MarketplaceJourneyContext={products:[],categories:[],connections:[],marketplaceStoreProducts:[],
    importStoreProductToMaster:vi.fn().mockResolvedValue({productId:null,createdVariants:0,linkedVariants:2}),
    addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:vi.fn(),mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()};
  const container=document.createElement('div');const root=createRoot(container);
  try {
    await act(async()=>root.render(<JourneyProvider value={context}><ImportProductReviewModal isOpen onClose={vi.fn()} productsToImport={INITIAL_MARKETPLACE_STORE_PRODUCTS.slice(0,1)}/></JourneyProvider>));
    await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Confirm Import'))!.click());
    expect(container.textContent).toContain('0 new draft product(s), 0 new variant(s), and 2 existing SKU link(s)');
    expect(container.textContent).not.toContain('Successfully Imported as JW Master Drafts');
  }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
