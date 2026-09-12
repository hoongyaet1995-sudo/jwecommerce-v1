import {it,expect,vi} from 'vitest';
import {createWarehouseReconciliation,warehouseReconciliationPost,loadWarehouseReview,loadCompleteWarehouseReview} from './warehouseReconciliation';

it('loads all reservation pages and rejects revision drift and incomplete totals',async()=>{
  const first={balance:{balance_id:7,variant_id:2,pool_id:3,version:4},stock_version:9,derived_reserved_stock:3,unowned_reserved_stock:0,
    allocations:{total:2,last_page:2,current_page:1,data:[{id:1,quantity:1}]}};
  const second={...first,allocations:{...first.allocations,current_page:2,data:[{id:2,quantity:2}]}};
  const request=vi.fn().mockImplementation(async(url:string)=>({ok:true,json:async()=>url.includes('?page=2')?second:first}));
  expect((await loadCompleteWarehouseReview(7,2,3,request)).allocationRows).toHaveLength(2);
  second.stock_version=10;
  await expect(loadCompleteWarehouseReview(7,2,3,request)).rejects.toThrow('changed');
  second.stock_version=9;second.allocations.data[0].quantity=1;
  await expect(loadCompleteWarehouseReview(7,2,3,request)).rejects.toThrow('totals');
});

it('rejects inconsistent ATP and stale receipt versions instead of showing success',async()=>{
  const result={status:'reconciled',receipt_id:'review-1',timestamp:'2026-09-08T10:00:00Z',physical_stock:10,reserved_stock:2,safety_stock:1,atp:8,balance_version:5};
  const post=vi.fn().mockImplementation(async()=>({...result}));
  const submit=createWarehouseReconciliation({masterSku:'SKU',warehouseId:'4',balanceId:7,balanceVersion:4,stockVersion:9,operationKey:'review-1'},post);
  const input={masterSku:'SKU',warehouseId:'4',newSafetyStock:1,reason:'Reviewed',expectedVersion:4,operatorName:'Staff'};
  await expect(submit(input)).rejects.toThrow('verification');
  result.atp=7;result.balance_version=4;
  await expect(submit(input)).rejects.toThrow('verification');
  result.balance_version=5;
  await expect(submit(input)).resolves.toMatchObject({success:true,newAtp:7});
});

it('loads both versions from one atomic obligation snapshot and rejects wrong balance identity',async()=>{
  const body={balance:{balance_id:7,variant_id:2,pool_id:3,version:4},stock_version:9,derived_reserved_stock:2};
  const request=vi.fn().mockResolvedValue({ok:true,json:async()=>body});
  expect(await loadWarehouseReview(7,2,3,request)).toMatchObject({stock_version:9,balance:{version:4}});
  await expect(loadWarehouseReview(8,2,3,request)).rejects.toThrow('identity');
});

it('uses the protected reconciliation endpoint and rejects failed HTTP responses',async()=>{
  const request=vi.fn().mockResolvedValue({ok:false,status:422,json:async()=>({message:'Inventory changed'})});
  const post=warehouseReconciliationPost('csrf-fixture',request);
  const payload={balance_id:7,balance_version:4,stock_version:9,safety_stock:1,operation_key:'review-1',reason:'Reviewed'};
  await expect(post(payload)).rejects.toThrow('Inventory changed');
  expect(request).toHaveBeenCalledWith('/admin/api/inventory/reconciliations',expect.objectContaining({method:'POST',credentials:'same-origin',
    headers:expect.objectContaining({'X-CSRF-TOKEN':'csrf-fixture'}),body:JSON.stringify(payload)}));
});

it('pins review versions and reuses the operation identity after a lost response',async()=>{
  const post=vi.fn().mockRejectedValueOnce(new Error('lost')).mockResolvedValueOnce({status:'reconciled',receipt_id:'review-1',timestamp:'2026-09-08T10:00:00Z',physical_stock:10,reserved_stock:2,safety_stock:1,atp:7,balance_version:5,previous_reserved_stock:2,previous_safety_stock:2,previous_atp:6});
  const submit=createWarehouseReconciliation({masterSku:'SKU',warehouseId:'4',balanceId:7,balanceVersion:4,stockVersion:9,operationKey:'review-1'},post);
  const input={masterSku:'SKU',warehouseId:'4',newSafetyStock:1,reason:'Reviewed',expectedVersion:4,operatorName:'Untrusted name'};
  await expect(submit(input)).rejects.toThrow('lost');
  const receipt=await submit(input);
  expect(post).toHaveBeenNthCalledWith(2,{balance_id:7,balance_version:4,stock_version:9,safety_stock:1,operation_key:'review-1',reason:'Reviewed'});
  expect(receipt).toMatchObject({success:true,status:'RECONCILED_IN_WMS_LEDGER',newAtp:7,previousAtp:6});
  expect(receipt).not.toHaveProperty('operator');
  await expect(submit({...input,newSafetyStock:2})).rejects.toThrow('new review');
  expect(post).toHaveBeenCalledTimes(2);
});
