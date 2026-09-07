// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {marketplaceOperations} from '../integration/marketplaceOperations';
import {useMarketplaceImport} from './useMarketplaceImport';
it('blocks duplicate submission and reloads only after confirmed import success',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 let finish!:(value:{productId:number;status:string})=>void;
 const api=vi.spyOn(marketplaceOperations,'importProduct').mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));
 const reload=vi.fn();let state!:ReturnType<typeof useMarketplaceImport>;
 function Harness(){state=useMarketplaceImport(true,[{id:'1:p',listingId:'7'}],reload);return null;}
 const root=createRoot(document.createElement('div'));
 try{
  await act(async()=>root.render(<Harness/>));
  let first!:ReturnType<typeof state.run>;
  await act(async()=>{first=state.run(['1:p']);await state.run(['1:p']);});
  expect(api).toHaveBeenCalledTimes(1);expect(state.busy).toBe(true);expect(reload).not.toHaveBeenCalled();
  await act(async()=>{finish({productId:12,status:'draft'});await first;});
  expect(state.busy).toBe(false);expect(reload).toHaveBeenCalledTimes(1);
 }finally{await act(async()=>root.unmount());vi.restoreAllMocks();vi.unstubAllGlobals();}
});
