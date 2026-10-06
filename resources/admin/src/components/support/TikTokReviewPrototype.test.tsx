// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {TikTokReviewPrototype} from './TikTokReviewPrototype';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:vi.fn()})}));
let root:ReturnType<typeof createRoot>;
afterEach(async()=>{await act(async()=>root.unmount());document.body.innerHTML='';vi.unstubAllGlobals();});
it('demonstrates the planned flow with no provider calls or real delivery claims',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const network=vi.fn();vi.stubGlobal('fetch',network);
 const host=document.createElement('div');document.body.append(host);root=createRoot(host);
 await act(async()=>root.render(<TikTokReviewPrototype/>));
 async function click(label:string){const button=Array.from(document.querySelectorAll('button')).find(b=>b.textContent===label)!;expect(button).toBeTruthy();await act(async()=>button.click());expect(document.body.textContent).toContain('Prototype · Simulated data');}
 await click('Start authorization demo');await click('Continue with demo account');await click('Connect demo account');await click('Simulate customer message');await click('Take over');
 const input=document.querySelector('textarea')!;const setter=Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value')!.set!;
 await act(async()=>{setter.call(input,'Thanks, we can help with your order.');input.dispatchEvent(new Event('input',{bubbles:true}));});
 await click('Send demo reply');
 expect(document.body.textContent).toContain('Thanks, we can help with your order.');expect(document.body.textContent).toContain('Simulated reply — not sent to TikTok');expect(network).not.toHaveBeenCalled();
 await click('Reset demo');expect(document.body.textContent).toContain('Start authorization demo');
});
