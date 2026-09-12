import {expect,it,vi} from 'vitest';
import {loadReconciliation} from './loadReconciliation';

it('allows Basic shared safety review but never advanced warehouse reconciliation for a shared pool',async()=>{
 const body={balance:{balance_id:7,variant_id:2,pool_id:3,version:0,physical_stock:12,safety_stock:null,counted_at:'2026-09-08T10:00:00Z'},
 pool_kind:'shared',warehouse_id:null,stock_version:9,derived_reserved_stock:0,unowned_reserved_stock:5,
 allocations:{total:0,last_page:1,current_page:1,data:[]}};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>body});
 const identity={balanceId:7,poolId:3,poolKind:'shared' as const,warehouseId:'shared',warehouseCode:'SHARED',warehouseName:'Shared inventory pool'};
 const props=await loadReconciliation({id:'2',masterSku:'SKU'} as any,identity,'csrf',request,true);
 expect(props.warehouse).toMatchObject({basicWms:true,safetyStockKnown:false,reservedStock:0});
 await expect(loadReconciliation({id:'2'} as any,identity,'csrf',request)).rejects.toThrow('identity');
});

it('prepares the counted balance and submits the exact reviewed versions through HTTP',async()=>{
 const body={balance:{balance_id:7,variant_id:2,pool_id:3,version:0,physical_stock:10,safety_stock:null,counted_at:'2026-09-08T10:00:00Z'},
   pool_kind:'warehouse',warehouse_id:4,stock_version:9,derived_reserved_stock:2,unowned_reserved_stock:0,
   allocations:{total:1,last_page:1,current_page:1,data:[{id:1,quantity:2,owner_type:'order',owner_id:8,
     order:{id:8,number:'JW-8',channel:'webstore',status:'pending',store_id:null}}]}};
 const request=vi.fn().mockImplementation(async(_url,options)=>{
   if(options?.method==='POST') {
     const payload=JSON.parse(options.body);
     return {ok:true,json:async()=>({status:'reconciled',receipt_id:payload.operation_key,timestamp:'2026-09-08T11:00:00Z',
       physical_stock:10,reserved_stock:2,safety_stock:1,atp:7,balance_version:1})};
   }
   return {ok:true,json:async()=>body};
 });
 const item:any={id:'2',masterSku:'SKU'};
 const identity={balanceId:7,poolId:3,warehouseId:'4',warehouseCode:'WH',warehouseName:'Warehouse'};
 const props=await loadReconciliation(item,identity,'csrf',request);
 expect(props.warehouse).toMatchObject({version:0,reservedStock:2,availableAtp:8});
 expect(props.allOrders[0]).toMatchObject({orderNumber:'JW-8',items:[{quantity:2,sku:'SKU'}]});
 await expect(props.onReconcile({masterSku:'SKU',warehouseId:'4',newSafetyStock:1,reason:'Reviewed count',expectedVersion:0,operatorName:'Staff'}))
   .resolves.toMatchObject({success:true,newAtp:7});
 const payload=JSON.parse(request.mock.calls[1][1].body);
 expect(payload).toMatchObject({balance_id:7,balance_version:0,stock_version:9,safety_stock:1});
 expect(payload).not.toHaveProperty('operatorName');
 body.warehouse_id=5;
 await expect(loadReconciliation(item,identity,'csrf',request)).rejects.toThrow('identity');
 body.warehouse_id=4;
 body.unowned_reserved_stock=1;
 await expect(loadReconciliation(item,identity,'csrf',request)).rejects.toThrow('Assign');
 body.unowned_reserved_stock=0;body.balance.counted_at='';
 await expect(loadReconciliation(item,identity,'csrf',request)).rejects.toThrow('physical count');
});
