// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {useMarketplaceReadTask} from './useMarketplaceReadTask';
import {marketplaceOperations} from '../integration/marketplaceOperations';
it('does not call a queued task successful and refreshes only on confirmed completion',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);vi.useFakeTimers();
 const complete=vi.fn();const submit=vi.fn().mockResolvedValue({runId:9});
 vi.spyOn(marketplaceOperations,'orderRun').mockResolvedValueOnce({id:9,status:'queued',pages_completed:0} as any)
  .mockRejectedValueOnce(new Error('temporary status failure'))
  .mockResolvedValueOnce({id:9,status:'succeeded',pages_completed:1} as any);
 let current:ReturnType<typeof useMarketplaceReadTask>;
 function Probe(){current=useMarketplaceReadTask('orders',complete);return null;}
 const root=createRoot(document.createElement('div'));
 try{
  await act(async()=>root.render(<Probe/>));
  await act(async()=>{void current!.start(submit);void current!.start(submit);});
  expect(submit).toHaveBeenCalledTimes(1);expect(current!.busy).toBe(true);expect(complete).not.toHaveBeenCalled();
  await act(async()=>{await vi.advanceTimersByTimeAsync(2000);});
  expect(current!.busy).toBe(true);expect(current!.error).toContain('could not be checked');expect(complete).not.toHaveBeenCalled();
  await act(async()=>{current!.checkStatus();});
  expect(submit).toHaveBeenCalledTimes(1);
  expect(current!.busy).toBe(false);expect(complete).toHaveBeenCalledTimes(1);
 }finally{await act(async()=>root.unmount());vi.useRealTimers();vi.restoreAllMocks();vi.unstubAllGlobals();}
});
