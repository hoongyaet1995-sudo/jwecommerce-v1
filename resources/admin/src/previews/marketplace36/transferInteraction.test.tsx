// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {WarehouseTransferTab} from './components/marketplace/WarehouseTransferTab';

it('cancelling the prompt makes no write and pending transitions cannot be double submitted',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const prompt=vi.spyOn(window,'prompt').mockReturnValue(null);
 const alert=vi.spyOn(window,'alert').mockImplementation(()=>{});
 let resolve!:(result:{success:boolean;error?:string})=>void;
 const update=vi.fn().mockImplementation(()=>new Promise(done=>{resolve=done;}));
 const transfer:any={id:'5',masterSku:'SKU',productName:'Test',status:'draft',quantity:2,createdAt:'2026-09-08',
   sourceWarehouseId:'9',destinationWarehouseId:'10',sourceWarehouseName:'Source',destinationWarehouseName:'Destination'};
 const dispatch=()=>Array.from(host.querySelectorAll('button')).find(button=>button.textContent?.includes('Dispatch'))!;
 try {
  await act(async()=>root.render(<WarehouseTransferTab transfers={[transfer]} warehouses={[]} staffUsers={[]}
   onOpenCreateTransferModal={()=>{}} onUpdateTransferStatus={update}/>));
  await act(async()=>dispatch().click());expect(update).not.toHaveBeenCalled();
  prompt.mockReturnValue('Local test dispatch');
  await act(async()=>dispatch().click());
  await act(async()=>dispatch().click());expect(update).toHaveBeenCalledTimes(1);
  await act(async()=>resolve({success:false,error:'Transfer version changed.'}));
  expect(alert).toHaveBeenCalledWith('Transfer version changed.');
 } finally {await act(async()=>root.unmount());host.remove();prompt.mockRestore();alert.mockRestore();}
});
