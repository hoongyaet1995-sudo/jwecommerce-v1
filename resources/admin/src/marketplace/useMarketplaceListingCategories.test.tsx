// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {marketplaceRequest} from '../integration/marketplaceData';
import {useMarketplaceListingCategories} from './useMarketplaceListingCategories';
vi.mock('../integration/marketplaceData',()=>({marketplaceRequest:vi.fn()}));
it('keeps category results scoped to the destination and clears them after failures or closing',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 let old!:(value:unknown)=>void;
 const request=vi.mocked(marketplaceRequest);
 request.mockImplementationOnce(()=>new Promise(resolve=>{old=resolve;}))
  .mockResolvedValueOnce({storeId:2,data:[{category_id:20,parent_category_id:0,display_category_name:'Second store',original_category_name:'Second store',has_children:false}]})
  .mockRejectedValueOnce(new Error('offline'));
 let state:ReturnType<typeof useMarketplaceListingCategories>;
 function Probe({id}:{id:string|null}){state=useMarketplaceListingCategories(id);return null;}
 const root=createRoot(document.createElement('div'));
 try {
  await act(async()=>root.render(<Probe id="1"/>));
  await act(async()=>root.render(<Probe id="2"/>));
  expect(state!.categories[0].category_id).toBe(20);
  expect(request).toHaveBeenLastCalledWith('/stores/2/listing-categories');
  await act(async()=>old({storeId:1,data:[]}));
  expect(state!.categories[0].category_id).toBe(20);
  await act(async()=>state!.reload());
  expect(state!.categories).toEqual([]);expect(state!.error).toBeTruthy();
  await act(async()=>root.render(<Probe id={null}/>));
  expect(state!.categories).toEqual([]);expect(state!.error).toBe('');
  expect(request).toHaveBeenCalledTimes(3);
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
