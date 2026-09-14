// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {usePublishingProgress} from './usePublishingProgress';
it('paces fast results and waits for actual mapping before checking the last step',async()=>{
 vi.useFakeTimers();vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 let step=0;function Probe({mapped=false,active=true}){step=usePublishingProgress(active,true,true,mapped,false);return null;}
 const root=createRoot(document.createElement('div'));
 try{
  await act(async()=>root.render(<Probe/>));expect(step).toBe(0);
  await act(async()=>vi.advanceTimersByTimeAsync(600));expect(step).toBe(1);
  await act(async()=>vi.advanceTimersByTimeAsync(600));expect(step).toBe(2);
  await act(async()=>vi.advanceTimersByTimeAsync(10000));expect(step).toBe(2);
  await act(async()=>root.render(<Probe mapped/>));
  await act(async()=>vi.advanceTimersByTimeAsync(600));expect(step).toBe(3);
  await act(async()=>vi.advanceTimersByTimeAsync(400));expect(step).toBe(4);
  await act(async()=>root.render(<Probe active={false}/>));expect(step).toBe(0);
 }finally{await act(async()=>root.unmount());vi.useRealTimers();vi.unstubAllGlobals();}
});
