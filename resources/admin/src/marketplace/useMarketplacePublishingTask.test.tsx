// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {marketplaceOperations} from '../integration/marketplaceOperations';
import {useMarketplacePublishingTask} from './useMarketplacePublishingTask';
vi.mock('../integration/marketplaceOperations',()=>({marketplaceOperations:{publishingTask:vi.fn()}}));
afterEach(()=>{vi.useRealTimers();vi.restoreAllMocks();vi.unstubAllGlobals();});
it('stops on uncertainty and rechecks the same task without a publishing request',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);vi.useFakeTimers();
  const read=vi.mocked(marketplaceOperations.publishingTask);
  read.mockResolvedValueOnce({id:12,status:'processing'} as never)
    .mockResolvedValueOnce({id:12,status:'uncertain',externalItemId:'846055463'} as never)
    .mockRejectedValueOnce(new Error('offline'));
  let state!:ReturnType<typeof useMarketplacePublishingTask>;
  function Harness(){state=useMarketplacePublishingTask('12',true);return null;}
  const root=createRoot(document.createElement('div'));
  try{
    await act(async()=>root.render(<Harness/>));
    expect(state.task?.status).toBe('processing');
    await act(async()=>vi.advanceTimersByTimeAsync(2000));
    expect(state.task?.status).toBe('uncertain');
    await act(async()=>vi.advanceTimersByTimeAsync(10000));
    expect(read).toHaveBeenCalledTimes(2);
    await act(async()=>state.checkStatus());
    expect(state.task?.status).toBe('uncertain');
    expect(state.error).toContain('Do not submit again');
    expect(read.mock.calls.every(([id])=>id==='12')).toBe(true);
  }finally{await act(async()=>root.unmount());}
});
it('ignores an in-flight response after the dialog closes',async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);vi.useFakeTimers();
  let resolve!:(value:never)=>void;
  vi.mocked(marketplaceOperations.publishingTask).mockReset().mockImplementation(()=>new Promise(r=>{resolve=r;}));
  let state!:ReturnType<typeof useMarketplacePublishingTask>;
  function Harness({open}:{open:boolean}){state=useMarketplacePublishingTask('12',open);return null;}
  const root=createRoot(document.createElement('div'));
  try{
    await act(async()=>root.render(<Harness open/>));
    await act(async()=>root.render(<Harness open={false}/>));
    await act(async()=>resolve({id:12,status:'succeeded',externalItemId:'846055463'} as never));
    expect(state.task).toBeNull();
    expect(vi.getTimerCount()).toBe(0);
  }finally{await act(async()=>root.unmount());}
});
