// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SupportMessageImages} from './SupportMessageImages';
it('allows another explicit retry when an accepted download fails again',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const fetcher=vi.fn().mockResolvedValue({ok:true});vi.stubGlobal('fetch',fetcher);
 const host=document.createElement('div'),root=createRoot(host);
 const attachment:any={id:'doc',type:'document',name:'receipt.pdf',status:'failed',retryUrl:'/retry'};
 await act(async()=>root.render(<SupportMessageImages attachments={[attachment]}/>));
 await act(async()=>host.querySelector('button')!.click());
 await act(async()=>root.render(<SupportMessageImages attachments={[{...attachment,status:'pending'}]}/>));
 await act(async()=>root.render(<SupportMessageImages attachments={[attachment]}/>));
 expect(host.querySelector('button')!.disabled).toBe(false);
 await act(async()=>host.querySelector('button')!.click());expect(fetcher).toHaveBeenCalledTimes(2);
 await act(async()=>root.unmount());vi.unstubAllGlobals();
});
