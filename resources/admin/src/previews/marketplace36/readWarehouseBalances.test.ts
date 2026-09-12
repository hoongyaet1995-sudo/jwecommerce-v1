import {expect,it,vi} from 'vitest';
import {readWarehouseBalances} from './readWarehouseBalances';

it('exposes one shared balance without inventing a physical warehouse',async()=>{
 const row={balance_id:7,pool_id:3,warehouse_id:null,variant_id:2,pool_kind:'shared',version:0,
 physical_stock:12,safety_stock:2,reserved_stock:null,atp:null,counted_at:'2026-09-08'};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>({total:1,current_page:1,last_page:1,data:[row]})});
 expect(await readWarehouseBalances(2,request)).toEqual([expect.objectContaining({poolKind:'shared',warehouseId:'shared',warehouseName:'Shared inventory pool',balanceId:7})]);
});

it('keeps Basic availability separate from reconciled ATP',async()=>{
 const row={balance_id:7,pool_id:3,warehouse_id:4,variant_id:2,pool_kind:'warehouse',version:0,
   physical_stock:20,reserved_stock:null,safety_stock:2,atp:null,counted_at:'2026-09-08',
   basic_wms:{available:18,owned_reserved:0,order_coverage_complete:false}};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>({total:1,current_page:1,last_page:1,data:[row]})});
 expect(await readWarehouseBalances(2,request)).toEqual([expect.objectContaining({availableAtp:null,
   basicAvailable:18,basicOwnedReserved:0,basicWms:true})]);
});

it('preserves unknown quantities and rejects another SKU in the response',async()=>{
 const row={balance_id:7,pool_id:3,warehouse_id:4,variant_id:2,pool_kind:'warehouse',version:0,
   warehouse_code:'WH',warehouse_name:'Warehouse',physical_stock:null,reserved_stock:null,safety_stock:null,atp:null,counted_at:null};
 const request=vi.fn().mockResolvedValue({ok:true,json:async()=>({total:1,current_page:1,last_page:1,data:[row]})});
 expect(await readWarehouseBalances(2,request)).toEqual([expect.objectContaining({balanceId:7,poolId:3,warehouseId:'4',
   physicalStock:null,reservedStock:null,availableAtp:null,physicalStockStatus:'unknown',version:0})]);
 row.variant_id=9;
 await expect(readWarehouseBalances(2,request)).rejects.toThrow('incomplete');
});
