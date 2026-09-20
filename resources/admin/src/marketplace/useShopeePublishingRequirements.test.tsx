// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {marketplaceRequest} from '../integration/marketplaceData';
import {activeShopeeAttributes,useShopeePublishingRequirements,type ShopeeAttribute} from './useShopeePublishingRequirements';
vi.mock('../integration/marketplaceData',()=>({marketplaceRequest:vi.fn()}));
const child:ShopeeAttribute={attribute_id:2,mandatory:true,attribute_info:{name:'Material',input_type:3},attribute_value_list:[]};
const tree:ShopeeAttribute[]=[{attribute_id:1,mandatory:true,attribute_info:{name:'Type',input_type:1},attribute_value_list:[{value_id:10,name:'Box',child_attribute_list:[child]},{value_id:11,name:'Other'}]}];
it('only includes dependencies of the current selected values',()=>{
 expect(activeShopeeAttributes(tree,{1:[{value_id:10,original_value_name:'Box',value_unit:''}]}).map(field=>field.attribute_id)).toEqual([1,2]);
 expect(activeShopeeAttributes(tree,{1:[{value_id:11,original_value_name:'Other',value_unit:''}],2:[{value_id:0,original_value_name:'Old',value_unit:''}]}).map(field=>field.attribute_id)).toEqual([1]);
});
it('clears category attributes and shipping selections before accepting another store',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.mocked(marketplaceRequest).mockImplementation(async(path)=>String(path).includes('/stores/1/')
  ? String(path).includes('listing-attributes')?{storeId:1,categoryId:10,data:tree}:{storeId:1,data:[{logistics_channel_id:24000,logistics_channel_name:'Courier',enabled:true,fee_type:'SIZE_INPUT'}]}
  : Promise.reject(new Error('Reconnect store')));
 let state:ReturnType<typeof useShopeePublishingRequirements>;
 function Probe({store}:{store:string}){state=useShopeePublishingRequirements(store,'10','a');return null;}
 const root=createRoot(document.createElement('div'));
 try{
  await act(async()=>root.render(<Probe store="1"/>));
  await act(async()=>{state!.updateAttribute(1,[{value_id:10,original_value_name:'Box',value_unit:''}]);state!.updateChannel(24000,{logistic_id:24000,is_free:false});});
  expect(state!.fields).toHaveLength(2);expect(state!.logistics).toHaveLength(1);
  expect(state!.canonicalFields.map(field=>field.providerId)).toEqual(['1','2']);
  await act(async()=>root.render(<Probe store="2"/>));
  expect(state!.attributes).toEqual([]);expect(state!.logistics).toEqual([]);expect(state!.error).toContain('Reconnect');
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
