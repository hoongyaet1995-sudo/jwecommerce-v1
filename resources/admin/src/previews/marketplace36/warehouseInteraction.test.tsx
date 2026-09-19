// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {EntityWarehouseModal} from './components/marketplace/EntityWarehouseModal';

it('waits for warehouse save and preserves the form after server rejection',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 let resolve!:(result:{success:boolean;error?:string})=>void;
 const save=vi.fn().mockImplementation(()=>new Promise(done=>{resolve=done;}));
 const close=vi.fn();
 const warehouse:any={id:'4',code:'WH-TEST',name:'Test warehouse',status:'active',address:'Test address'};
 const submit=()=>host.querySelector('form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
 try {
  await act(async()=>root.render(<EntityWarehouseModal isOpen warehouse={warehouse} onClose={close} onSave={save}/>));
  await act(async()=>{submit();});
  expect((host.querySelector('button[type="submit"]') as HTMLButtonElement).disabled).toBe(true);
  await act(async()=>{submit();});expect(save).toHaveBeenCalledTimes(1);
  expect(save.mock.calls[0][0]).toMatchObject({contactPerson:'',contactPhone:''});
  await act(async()=>resolve({success:false,error:'Warehouse version changed.'}));
  expect(close).not.toHaveBeenCalled();expect(host.textContent).toContain('Warehouse version changed.');
  expect((host.querySelector('input') as HTMLInputElement).value).toBe('WH-TEST');
  await act(async()=>{submit();});
  await act(async()=>resolve({success:true}));expect(close).toHaveBeenCalledTimes(1);
 } finally {await act(async()=>root.unmount());host.remove();}
});
