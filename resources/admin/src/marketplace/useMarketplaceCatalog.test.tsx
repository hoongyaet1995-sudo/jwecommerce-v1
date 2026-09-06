// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {marketplaceOperations} from '../integration/marketplaceOperations';
import {useMarketplaceCatalog} from './useMarketplaceCatalog';

afterEach(()=>{vi.restoreAllMocks();vi.unstubAllGlobals();});

it('ignores a previous filter response after a newer request completes',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 let resolveOld!:(value:any)=>void;
 vi.spyOn(marketplaceOperations,'catalogProducts').mockImplementationOnce(()=>new Promise(resolve=>{resolveOld=resolve;}))
  .mockResolvedValue({data:[],total:22,current_page:1,last_page:2,per_page:15});
 vi.spyOn(marketplaceOperations,'catalogSummary').mockResolvedValue({products:22,variants:22,mapped:0,unmapped:22});
 let current:ReturnType<typeof useMarketplaceCatalog>;
 const query={platform:'all' as const,storeId:'all',q:'old',status:'unmapped',page:1,per_page:15,view:'grouped' as const};
 function Probe({q}:{q:string}){current=useMarketplaceCatalog({...query,q});return null;}
 const root=createRoot(document.createElement('div'));
 await act(async()=>root.render(<Probe q="old"/>));
 await act(async()=>root.render(<Probe q="new"/>));
 expect(current!.total).toBe(22);
 await act(async()=>resolveOld({data:[],total:999,current_page:1,last_page:67,per_page:15}));
 expect(current!.total).toBe(22);expect(current!.loading).toBe(false);
 await act(async()=>root.unmount());vi.restoreAllMocks();vi.unstubAllGlobals();
});

it('keeps flat pagination measured in variants and exposes request failures',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.spyOn(marketplaceOperations,'catalog').mockResolvedValueOnce({data:[
  {id:1,store_id:1,external_product_id:'p',platform:'shopee',shop_name:'Store'},
  {id:2,store_id:1,external_product_id:'p',platform:'shopee',shop_name:'Store'},
 ] as any,total:320,current_page:2,last_page:22,per_page:15}).mockRejectedValueOnce(new Error('network'));
 vi.spyOn(marketplaceOperations,'catalogSummary').mockResolvedValue({products:160,variants:320,mapped:0,unmapped:320});
 let current:ReturnType<typeof useMarketplaceCatalog>;
 function Probe(){current=useMarketplaceCatalog({platform:'all',storeId:'all',q:'',status:'all',page:2,per_page:15,view:'flat'});return null;}
 const root=createRoot(document.createElement('div'));
 try{
  await act(async()=>root.render(<Probe/>));
  expect(current!.total).toBe(320);expect(current!.products).toHaveLength(1);
  expect(current!.products[0].variants).toHaveLength(2);
  await act(async()=>current!.reload());
  expect(current!.error).toContain('could not be loaded');
  expect(current!.products).toHaveLength(0);expect(current!.loading).toBe(false);
 }finally{await act(async()=>root.unmount());}
});
