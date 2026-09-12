// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {WarehouseInventoryReconcileModal} from './components/marketplace/WarehouseInventoryReconcileModal';

it('submits initial balance version zero and retains review after server rejection',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);
 const root=createRoot(host);
 const submit=vi.fn().mockRejectedValue(new Error('Inventory changed.'));
 const close=vi.fn();
 const item:any={masterSku:'TEST',productName:'Test',storeAssociations:[]};
 const warehouse:any={warehouseId:'1',warehouseCode:'WH',warehouseName:'Warehouse',physicalStock:10,
  physicalStockStatus:'verified',reservedStock:0,safetyStock:1,availableAtp:9,version:0,lastAuditAt:'2026-09-08T10:00:00Z'};
 const button=(text:string)=>Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes(text))!;
 try {
  await act(async()=>root.render(<WarehouseInventoryReconcileModal isOpen onClose={close} item={item}
    warehouse={warehouse} currentUserName="Authenticated staff" onReconcile={submit}/>));
  await act(async()=>button('Proceed to Review').click());
  await act(async()=>button('Confirm & Apply').click());
  expect(submit).toHaveBeenCalledWith(expect.objectContaining({expectedVersion:0,warehouseId:'1',operatorName:'Authenticated staff'}));
  expect(host.textContent).toContain('Inventory changed.');
  expect(close).not.toHaveBeenCalled();
 } finally {await act(async()=>root.unmount());host.remove();}
});
