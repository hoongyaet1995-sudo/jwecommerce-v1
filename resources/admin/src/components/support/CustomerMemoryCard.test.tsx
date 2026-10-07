// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {CustomerMemoryCard} from './CustomerMemoryCard';
it('confirmation cancels safely and stale mutation cannot contaminate another customer',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'),root=createRoot(host);let finish:any;
 const memory={id:1,kind:'language',text:'Malay',sourceConversationId:'A',expiresAt:'2026-11-01'};
 const response=(items:any[])=>({ok:true,json:async()=>({memories:items})});
 const fetcher=vi.fn().mockResolvedValueOnce(response([memory])).mockImplementationOnce(()=>new Promise(resolve=>{finish=resolve})).mockResolvedValueOnce(response([]));
 vi.stubGlobal('fetch',fetcher);
 const click=async(label:string)=>act(async()=>{(Array.from(host.querySelectorAll('button')).find(b=>b.textContent===label) as HTMLButtonElement).click()});
 try{
 await act(async()=>root.render(<CustomerMemoryCard conversationId="A"/>));
 await click('Delete');expect(fetcher).toHaveBeenCalledTimes(1);await click('Cancel');expect(fetcher).toHaveBeenCalledTimes(1);
 await click('Edit');await click('Confirm');
 await act(async()=>root.render(<CustomerMemoryCard conversationId="B"/>));
 await act(async()=>finish(response([{...memory,text:'Stale A'}])));
 expect(host.textContent).not.toContain('Stale A');expect(host.textContent).toContain('No eligible memory');
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();}
});
