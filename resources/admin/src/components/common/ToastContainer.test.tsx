// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {ToastContainer} from './ToastContainer';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({toasts:[{id:'error',type:'error',title:'Failed',message:'Try again'}],removeToast:vi.fn()})}));
it('keeps error visible in the dialog top layer and returns it to the page when dialog closes',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const dialog=document.createElement('dialog');dialog.setAttribute('open','');document.body.append(dialog);
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<ToastContainer/>));expect(dialog.textContent).toContain('Try again');
  await act(async()=>{dialog.removeAttribute('open');await Promise.resolve();});
  expect(dialog.textContent).not.toContain('Try again');expect(host.textContent).toContain('Try again');
 }finally{await act(async()=>root.unmount());document.body.innerHTML='';}
});
