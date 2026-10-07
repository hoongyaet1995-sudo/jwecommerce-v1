// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {AIReplyCadenceControls} from './AIReplyCadenceControls';
import {normalizeAIAutoReplyConfig} from '../../data/normalizeAIAutoReply';
it('reply rhythm defaults off and exposes bounded settings with a saved roundtrip',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'),root=createRoot(host),change=vi.fn();
 try{
  await act(async()=>root.render(<AIReplyCadenceControls onChange={change}/>));
  const toggle=host.querySelector('[role="switch"]') as HTMLInputElement;expect(toggle.checked).toBe(false);
  await act(async()=>toggle.click());expect(change).toHaveBeenCalledWith({enabled:true,collectionSeconds:2,intervalSeconds:1,maxSegments:3});
  const sliders=[...host.querySelectorAll('input[type="range"]')];expect(sliders).toHaveLength(3);
  expect(sliders.map(x=>[x.getAttribute('min'),x.getAttribute('max')])).toEqual([['0','3'],['0','3'],['1','3']]);
  const value={enabled:true,collectionSeconds:3,intervalSeconds:2,maxSegments:2};
  expect(normalizeAIAutoReplyConfig({replyCadence:value}).replyCadence).toEqual(value);
 }finally{await act(async()=>root.unmount());}
});
