// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {marketplaceRequest} from '../integration/marketplaceData';
import {useMarketplaceListingBrands} from './useMarketplaceListingBrands';
vi.mock('../integration/marketplaceData',()=>({marketplaceRequest:vi.fn()}));
it('loads brand pages explicitly and discards the prior category on a category change',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const request=vi.mocked(marketplaceRequest);
 request.mockResolvedValueOnce({storeId:1,categoryId:10,data:{brand_list:[{brand_id:2,original_brand_name:'Sony'}],has_next_page:true,next_offset:20,is_mandatory:true,input_type:'DROP_DOWN'}})
  .mockResolvedValueOnce({storeId:1,categoryId:10,data:{brand_list:[{brand_id:3,original_brand_name:'Nintendo'}],has_next_page:false,next_offset:0,is_mandatory:true,input_type:'DROP_DOWN'}})
  .mockRejectedValueOnce(new Error('offline'));
 let state:ReturnType<typeof useMarketplaceListingBrands>;
 function Probe({category}:{category:string}){state=useMarketplaceListingBrands('1',category);return null;}
 const root=createRoot(document.createElement('div'));
 try {
  await act(async()=>root.render(<Probe category="10"/>));
  expect(state!.brands.map(b=>b.brand_id)).toEqual([2]);
  expect(request).toHaveBeenCalledTimes(1);
  await act(async()=>state!.loadMore());
  expect(request).toHaveBeenLastCalledWith('/stores/1/listing-brands?categoryId=10&offset=20&refresh=1');
  expect(state!.brands.map(b=>b.brand_id)).toEqual([2,3]);
  expect(state!.hasMore).toBe(false);
  await act(async()=>root.render(<Probe category="11"/>));
  expect(state!.brands).toEqual([]);expect(state!.error).toBeTruthy();
  request.mockResolvedValueOnce({storeId:1,categoryId:10,data:{brand_list:[{brand_id:2,original_brand_name:'Sony'}],has_next_page:true,next_offset:20,is_mandatory:true,input_type:'DROP_DOWN'}});
  await act(async()=>root.render(<Probe category="10"/>));
  expect(request).toHaveBeenLastCalledWith('/stores/1/listing-brands?categoryId=10&offset=0&refresh=1');
  expect(state!.brands.map(b=>b.brand_id)).toEqual([2]);
 } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
