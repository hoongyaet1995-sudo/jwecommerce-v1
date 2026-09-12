import type {WarehouseStockBalance} from './types';

export async function readWarehouseBalances(variantId:number,request:typeof fetch=fetch):Promise<WarehouseStockBalance[]> {
 if(!Number.isSafeInteger(variantId)||variantId<1)throw new Error('Invalid SKU identity.');
 const rows:any[]=[];let total:number|undefined;
 for(let page=1;page<=100;page++) {
   const response=await request(`/admin/api/inventory/balances?variantId=${variantId}&page=${page}`,
     {credentials:'same-origin',headers:{Accept:'application/json'}});
   if(!response.ok)throw new Error('Warehouse balances unavailable.');
   const result=await response.json();
   if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.total)||result.total<0
     ||!Number.isSafeInteger(result.last_page)||result.last_page<page||result.last_page>100
     ||(total!==undefined&&result.total!==total))throw new Error('Warehouse balance list changed. Reload.');
   total=result.total;rows.push(...result.data);
   if(page===result.last_page)break;
 }
 if(rows.length!==total||new Set(rows.map(row=>row.balance_id)).size!==rows.length
   ||rows.some(row=>row.variant_id!==variantId))throw new Error('Warehouse balance list is incomplete.');
 return rows.map(row=>{
   const shared=row.pool_kind==='shared';
   if(!['shared','warehouse'].includes(row.pool_kind)
     ||(shared?row.warehouse_id!==null:(!Number.isSafeInteger(row.warehouse_id)||row.warehouse_id<1))
     ||![row.balance_id,row.pool_id].every(id=>Number.isSafeInteger(id)&&id>0)
     ||!Number.isSafeInteger(row.version)||row.version<0)throw new Error('Warehouse balance identity is invalid.');
   return {balanceId:row.balance_id,poolId:row.pool_id,poolKind:row.pool_kind,warehouseId:shared?'shared':String(row.warehouse_id),
     warehouseCode:shared?'SHARED':row.warehouse_code,warehouseName:shared?'Shared inventory pool':row.warehouse_name,binLocation:row.bin_location,
     physicalStock:row.physical_stock,physicalStockStatus:row.counted_at&&row.physical_stock!==null?'verified':'unknown',
     reservedStock:row.reserved_stock,safetyStock:row.safety_stock,availableAtp:row.atp,
     basicWms:row.basic_wms!=null,basicAvailable:row.basic_wms?.available??null,
     basicOwnedReserved:row.basic_wms?.owned_reserved??null,
     lastAuditAt:row.counted_at,version:row.version};
 });
}
