// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {PublishProductModal} from './components/marketplace/PublishProductModal';
import {JourneyProvider,type MarketplaceJourneyContext} from './context/AdminContext';
import {marketplaceOperations} from '../../integration/marketplaceOperations';
import {marketplaceRequest} from '../../integration/marketplaceData';
vi.mock('../../integration/marketplaceData',()=>({marketplaceRequest:vi.fn()}));
vi.mock('../../integration/marketplaceOperations',()=>({marketplaceOperations:{publishingTask:vi.fn()}}));
it('shows destination categories without demo options and cannot publish from metadata review',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.mocked(marketplaceRequest).mockResolvedValue({storeId:1,data:[
  {category_id:10,parent_category_id:0,display_category_name:'Electronics',original_category_name:'Electronics',has_children:true},
  {category_id:11,parent_category_id:10,display_category_name:'Game Consoles',original_category_name:'Game Consoles',has_children:false},
 ]});
 const submit=vi.fn();
 const context={products:[{id:'51',title:'PS5',basePrice:1400,totalInventory:0,variants:[],images:[]}],categories:[],
  connections:[{id:'1',platform:'shopee',shopName:'Sandbox',status:'connected'}],marketplaceStoreProducts:[],
  importStoreProductToMaster:vi.fn(),addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:submit,
  mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()} as unknown as MarketplaceJourneyContext;
 const container=document.createElement('div');const root=createRoot(container);
 try {
  await act(async()=>root.render(<JourneyProvider value={context}><PublishProductModal isOpen onClose={vi.fn()} readOnlyMetadata/></JourneyProvider>));
  await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Category & Attributes'))!.click());
  const select=container.querySelector('select')!;
  expect(select.textContent).toContain('Game Consoles');
  expect(select.textContent).not.toContain('Headphones');
  expect(select.querySelector('option[value="10"]')?.hasAttribute('disabled')).toBe(true);
  expect(select.value).toBe('');
  expect(container.textContent).toContain('Publishing is not enabled in this metadata review.');
  expect(submit).not.toHaveBeenCalled();
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
it('checks the saved task instead of simulating a new publish and does not invent live or mapped results',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const read=vi.mocked(marketplaceOperations.publishingTask);
  const task={id:12,storeId:1,productId:51,status:'uncertain',externalItemId:'846055463',createdAt:'',updatedAt:''};
  read.mockResolvedValueOnce(task as never).mockResolvedValueOnce({...task,status:'succeeded'} as never);
  const submit=vi.fn();
  const context:MarketplaceJourneyContext={products:[],categories:[],connections:[],marketplaceStoreProducts:[],
    importStoreProductToMaster:vi.fn(),addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:submit,
    mapStoreVariantToJwSku:vi.fn(),unmapStoreVariant:vi.fn()};
  const container=document.createElement('div');const root=createRoot(container);
  try{
    await act(async()=>root.render(<JourneyProvider value={context}><PublishProductModal isOpen onClose={vi.fn()} taskId="12"/></JourneyProvider>));
    expect(container.textContent).toContain('Result Requires Verification');
    expect(container.textContent).not.toContain('background worker is polling');
    await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Poll Status Now'))!.click());
    expect(read).toHaveBeenCalledTimes(2);expect(submit).not.toHaveBeenCalled();
    expect(container.textContent).toContain('846055463');
    expect(container.textContent).toContain('Listing Task Completed');
    expect(container.textContent).not.toContain('Published & Live');
    expect(container.textContent).not.toContain('SKUs auto-linked');
  }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
