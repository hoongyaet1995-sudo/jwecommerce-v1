// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ImportProductReviewModal} from './components/marketplace/ImportProductReviewModal';
import {JourneyProvider,type MarketplaceJourneyContext} from './context/AdminContext';
import {INITIAL_MARKETPLACE_STORE_PRODUCTS, INITIAL_PRODUCTS} from './fixtures';

it('keeps an auto-assigned missing SKU stable when catalog props refresh during review',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const random=vi.spyOn(Math,'random').mockReturnValueOnce(0.1).mockReturnValue(0.9);
 const external={...INITIAL_MARKETPLACE_STORE_PRODUCTS[0],variants:[{...INITIAL_MARKETPLACE_STORE_PRODUCTS[0].variants[0],channelSellerSku:''}]};
 const context:MarketplaceJourneyContext={products:[],categories:[],connections:[],marketplaceStoreProducts:[],
  importStoreProductToMaster:vi.fn(),addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:vi.fn(),mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()};
 const container=document.createElement('div');const root=createRoot(container);
 const render=(value:MarketplaceJourneyContext)=><JourneyProvider value={value}><ImportProductReviewModal isOpen onClose={vi.fn()} productsToImport={[external]}/></JourneyProvider>;
 try {
  await act(async()=>root.render(render(context)));
  const reviewed=container.querySelector<HTMLInputElement>('input[type="text"]')!.value;
  expect(reviewed).not.toBe('');
  await act(async()=>root.render(render({...context,products:[]})));
  expect(container.querySelector<HTMLInputElement>('input[type="text"]')!.value).toBe(reviewed);
 } finally {await act(async()=>root.unmount());random.mockRestore();vi.unstubAllGlobals();}
});

it('reviews an existing-SKU-only operation as links rather than draft creation',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const external={...INITIAL_MARKETPLACE_STORE_PRODUCTS[0],variants:[{...INITIAL_MARKETPLACE_STORE_PRODUCTS[0].variants[0],channelSellerSku:'EXISTING'}]};
 const context:MarketplaceJourneyContext={products:[{...INITIAL_PRODUCTS[0],id:'9',title:'Existing product',variants:[{...INITIAL_PRODUCTS[0].variants[0],id:'90',sku:'EXISTING',title:'Existing variant',price:20,inventoryQuantity:39}]}],
  categories:[],connections:[],marketplaceStoreProducts:[],importStoreProductToMaster:vi.fn(),addToast:vi.fn(),setActiveSection:vi.fn(),
  publishMasterProductToStore:vi.fn(),mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()};
 const container=document.createElement('div');const root=createRoot(container);
 try {
  await act(async()=>root.render(<JourneyProvider value={context}><ImportProductReviewModal isOpen onClose={vi.fn()} productsToImport={[external]}/></JourneyProvider>));
  expect(container.textContent).toContain('0 Drafts');
  expect(container.textContent).toContain('Will link: Existing JW SKUs');
  expect(container.textContent).not.toContain('Will create: New JW Draft');
  expect(container.textContent).toContain('0 new JW draft item(s) and 1 existing SKU link(s)');
  expect(container.textContent).toContain('Confirm SKU Links');
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});

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
    expect(container.textContent).toContain('0 new draft product(s), 0 new variant(s), 2 existing SKU link(s)');
    expect(container.textContent).not.toContain('Successfully Imported as JW Master Drafts');
  }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});

it('opens Inventory Management with only the saved JW product ids',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const openInventory=vi.fn();
  const context:MarketplaceJourneyContext={products:[],categories:[],connections:[],marketplaceStoreProducts:[],
    importStoreProductToMaster:vi.fn().mockResolvedValue({productId:51,createdVariants:2,linkedVariants:0}),
    addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:vi.fn(),mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()};
  const container=document.createElement('div');const root=createRoot(container);
  try {
    await act(async()=>root.render(<JourneyProvider value={context}><ImportProductReviewModal isOpen onClose={vi.fn()}
      productsToImport={INITIAL_MARKETPLACE_STORE_PRODUCTS.slice(0,1)} onInventoryManagement={openInventory}/></JourneyProvider>));
    await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Confirm Import'))!.click());
    const button=Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Inventory Management'))!;
    expect(button).toBeTruthy();
    await act(async()=>button.click());
    expect(openInventory).toHaveBeenCalledWith({productIds:[51]});
    expect(context.setActiveSection).not.toHaveBeenCalledWith('marketplace_inventory');
  }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
