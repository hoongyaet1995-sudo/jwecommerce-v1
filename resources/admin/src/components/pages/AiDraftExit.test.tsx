// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {AiDraftExit} from './AiDraftExit';
it('keeps close and discard separate and states saved-page boundary',async()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');const root=createRoot(node);const keep=vi.fn(),discard=vi.fn();
 await act(async()=>root.render(<AiDraftExit onBack={()=>{}} onKeep={keep} onDiscard={discard}/>));
 expect(node.textContent).toContain('saved CMS page');
 const buttons=[...node.querySelectorAll('button')];await act(async()=>buttons.find(b=>b.textContent?.includes('Close &'))!.click());expect(keep).toHaveBeenCalledTimes(1);expect(discard).not.toHaveBeenCalled();
 await act(async()=>buttons.find(b=>b.textContent==='Discard AI draft')!.click());expect(discard).toHaveBeenCalledTimes(1);act(()=>root.unmount());
});
