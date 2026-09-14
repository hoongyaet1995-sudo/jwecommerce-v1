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
it('submits live Shopee requirements from the existing three-step review',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>{
  if(String(path).includes('publishing-status'))return {products:{}};
  if(String(path).includes('publishing-source'))return {product_id:51,title:'Saved',brand:'',description:null,revision:'a'.repeat(64),images:[],variants:[{id:8,sku:'JW',price_minor:1599,initial_quantity:0,weight_grams:500,package_length_cm:10,package_width_cm:10,package_height_cm:10}]};
  if(String(path).includes('listing-brands'))return {storeId:1,categoryId:11,data:{brand_list:[],has_next_page:false,next_offset:0,is_mandatory:false,input_type:'DROP_DOWN'}};
  if(String(path).includes('listing-attributes'))return {storeId:1,categoryId:11,data:[]};
  if(String(path).includes('listing-logistics'))return {storeId:1,data:[{logistics_channel_id:24000,logistics_channel_name:'Sandbox courier',enabled:true,fee_type:'SIZE_INPUT'}]};
  return {storeId:1,data:[{category_id:11,parent_category_id:0,display_category_name:'Boxes',original_category_name:'Boxes',has_children:false}]};
 });
 vi.mocked(marketplaceOperations.publishingTask).mockResolvedValue({id:42,storeId:1,productId:51,status:'uncertain',externalItemId:'1001'} as never);
 const submit=vi.fn().mockResolvedValue('42');
 const context={products:[{id:'51',title:'Saved',basePrice:15.99,totalInventory:17,variants:[],images:[]}],categories:[],connections:[{id:'1',platform:'shopee',shopName:'Sandbox',status:'connected'}],marketplaceStoreProducts:[],addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:vi.fn()} as unknown as MarketplaceJourneyContext;
 const container=document.createElement('div'),root=createRoot(container);
 try{
  await act(async()=>root.render(<JourneyProvider value={context}><PublishProductModal isOpen liveData onClose={vi.fn()} submitReviewed={submit}/></JourneyProvider>));
  await act(async()=>Array.from(container.querySelectorAll('button')).find(button=>button.textContent?.includes('Category & Attributes'))!.click());
  const category=container.querySelector('select')!;
  await act(async()=>{category.value='11';category.dispatchEvent(new Event('change',{bubbles:true}));});
  expect(container.textContent).toContain('Sandbox courier');
  await act(async()=>container.querySelector<HTMLInputElement>('input[type="checkbox"]')!.click());
  await act(async()=>Array.from(container.querySelectorAll('button')).find(button=>button.textContent?.includes('Pricing & Sync'))!.click());
  await act(async()=>Array.from(container.querySelectorAll('button')).find(button=>button.textContent?.includes('Publish to SHOPEE'))!.click());
  expect(submit).toHaveBeenCalledWith('51','1',expect.objectContaining({shopeeAttributes:[],shopeeBrandId:null,shopeeLogistics:[{logistic_id:24000,is_free:false}],sourceRevision:'a'.repeat(64)}));
  expect(container.textContent).toContain('Result Requires Verification');
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
it('uses saved enabled variants and JW prices in live review instead of demo shipping and markup',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>String(path).includes('publishing-source')
  ? {product_id:51,title:'Saved product',brand:'Saved brand',description:null,revision:'a'.repeat(64),images:[],
     variants:[{id:8,sku:'SAVED-SKU',title:'Saved variant',price_minor:1599,initial_quantity:0,weight_grams:250,package_length_cm:12,package_width_cm:8,package_height_cm:4}]}
  : {storeId:1,data:[]});
 const context={products:[{id:'51',title:'Stale product',basePrice:1400,totalInventory:77,variants:[],images:[]}],categories:[],
  connections:[{id:'1',platform:'lazada',shopName:'Test',status:'connected'}],marketplaceStoreProducts:[],
  addToast:vi.fn(),setActiveSection:vi.fn(),publishMasterProductToStore:vi.fn()} as unknown as MarketplaceJourneyContext;
 const container=document.createElement('div');const root=createRoot(container);
 try {
  await act(async()=>root.render(<JourneyProvider value={context}><PublishProductModal isOpen liveData onClose={vi.fn()}/></JourneyProvider>));
  expect(container.textContent).toContain('JW Legacy Stock: 77 units');
  expect(container.textContent).not.toContain('Physical Stock:');
  await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Pricing & Sync'))!.click());
  expect(container.textContent).toContain('SAVED-SKU');
  expect(container.textContent).toContain('15.99');
  expect(container.textContent).toContain('Channel Price (+0%)');
  expect(container.textContent).not.toContain('77 units');
  expect(Array.from(container.querySelectorAll('input[type="checkbox"]')).every(input=>(input as HTMLInputElement).disabled)).toBe(true);
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
it('hands reviewed values to the real adapter and reads its saved task without mock success',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const submit=vi.fn().mockResolvedValue('42');
 vi.mocked(marketplaceOperations.publishingTask).mockReset().mockResolvedValue({id:42,storeId:1,productId:51,status:'uncertain',externalItemId:null} as never);
 const mockPublish=vi.fn();
 const context={products:[{id:'51',title:'PS5',basePrice:1400,totalInventory:0,variants:[],images:[]}],categories:[],
  connections:[{id:'1',platform:'shopee',shopName:'Sandbox',status:'connected'}],marketplaceStoreProducts:[],
  setActiveSection:vi.fn(),publishMasterProductToStore:mockPublish} as unknown as MarketplaceJourneyContext;
 const container=document.createElement('div');const root=createRoot(container);
 try {
  await act(async()=>root.render(<JourneyProvider value={context}><PublishProductModal isOpen onClose={vi.fn()} submitReviewed={submit}/></JourneyProvider>));
  await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Pricing & Sync'))!.click());
  await act(async()=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Publish to SHOPEE'))!.click());
  expect(submit).toHaveBeenCalledTimes(1);
  expect(submit.mock.calls[0].slice(0,2)).toEqual(['51','1']);
  expect(container.textContent).toContain('Result Requires Verification');
  expect(container.textContent).not.toContain('background worker is polling');
  expect(mockPublish).not.toHaveBeenCalled();
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
it.each(['shopee','lazada'] as const)('shows %s destination categories without demo options and cannot publish from metadata review',async(platform)=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>String(path).includes('listing-brands')
  ? {storeId:1,categoryId:11,data:{brand_list:[{brand_id:2,original_brand_name:'Sony'}],has_next_page:false,next_offset:0,is_mandatory:true,input_type:'DROP_DOWN'}}
  : {storeId:1,data:[
  {category_id:10,parent_category_id:0,display_category_name:'Electronics',original_category_name:'Electronics',has_children:true},
  {category_id:11,parent_category_id:10,display_category_name:'Game Consoles',original_category_name:'Game Consoles',has_children:false},
 ]});
 const submit=vi.fn();
 const context={products:[{id:'51',title:'PS5',basePrice:1400,totalInventory:0,variants:[],images:[]}],categories:[],
  connections:[{id:'1',platform,shopName:'Sandbox',status:'connected'}],marketplaceStoreProducts:[],
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
  await act(async()=>{select.value='11';select.dispatchEvent(new Event('change',{bubbles:true}));});
  if(platform==='shopee') expect(container.querySelector('datalist')?.textContent).toContain('Sony');
  expect(container.querySelector<HTMLInputElement>('input[list]')?.value).toBe('');
  expect(container.textContent).toContain('Publishing is not enabled in this metadata review.');
  expect(submit).not.toHaveBeenCalled();
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
it('checks the saved task instead of simulating a new publish and does not invent live or mapped results',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const read=vi.mocked(marketplaceOperations.publishingTask);
  const task={id:12,storeId:1,productId:51,status:'uncertain',externalItemId:'846055463',createdAt:'',updatedAt:''};
  read.mockReset().mockResolvedValueOnce(task as never).mockResolvedValueOnce({...task,status:'succeeded'} as never);
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
