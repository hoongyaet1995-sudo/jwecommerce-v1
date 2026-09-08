// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {InventoryAdjustmentModal} from './components/marketplace/InventoryAdjustmentModal';

it('keeps failed confirmation open and closes only after a verified retry',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'); document.body.append(host);
 const root=createRoot(host);
 const onClose=vi.fn();
 const countApi={review:vi.fn().mockResolvedValue({id:'review',physical_count:10,physical_before:null,actor_id:7,atp:null}),
  confirm:vi.fn().mockRejectedValueOnce(new Error('Inventory changed.')).mockResolvedValueOnce(undefined)};
 const item:any={masterSku:'TEST',productName:'Test',physicalStockStatus:'verified',totalPhysicalStock:10,reservedStock:null,safetyStock:null};
 const button=(text:string)=>Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes(text))!;
 try {
  await act(async()=>root.render(<InventoryAdjustmentModal item={item} isOpen staffUsers={[]} countApi={countApi} onClose={onClose} onApplyAdjustment={()=>{}} onRecordAuditCount={()=>{}}/>));
  await act(async()=>button('Physical Count').click());
  expect(host.textContent).toContain('Not verified');
  await act(async()=>{host.querySelector('form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));});
  expect(countApi.review).toHaveBeenCalledWith(10,expect.any(String));
  expect(host.textContent).toContain('Authenticated employee #7');
  expect(host.textContent).toContain('Pending reconciliation');
  await act(async()=>{(host.querySelector('input[type="checkbox"]') as HTMLInputElement).click();});
  await act(async()=>button('Confirm & Apply').click());
  expect(onClose).not.toHaveBeenCalled();
  expect(host.textContent).toContain('Inventory changed.');
  await act(async()=>button('Confirm & Apply').click());
  expect(countApi.confirm).toHaveBeenCalledTimes(2);
  expect(onClose).toHaveBeenCalledTimes(1);
 } finally {await act(async()=>root.unmount()); host.remove();}
});
