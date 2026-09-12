import {loadCompleteWarehouseReview,createWarehouseReconciliation,warehouseReconciliationPost} from '../../integration/warehouseReconciliation';
import {warehouseReservationOrders} from '../../integration/warehouseReservationOrders';
import {basicSafetyApi} from './basicSafetyApi';
import type {MarketplaceInventoryItem,WarehouseStockBalance} from './types';

/** Prepare the source modal only after the complete server review has arrived. */
export async function loadReconciliation(item:MarketplaceInventoryItem,identity:{balanceId:number;poolId:number;
 warehouseId:string;warehouseCode:string;warehouseName:string;poolKind?:'shared'|'warehouse'},csrf:string,request:typeof fetch=fetch,basic=false) {
 const review=await loadCompleteWarehouseReview(identity.balanceId,Number(item.id),identity.poolId,request);
 const balance=review.balance;
 const shared=identity.poolKind==='shared';
 if(shared?(!basic||review.pool_kind!=='shared'||review.warehouse_id!==null||identity.warehouseId!=='shared')
   :(review.pool_kind!=='warehouse'||String(review.warehouse_id)!==identity.warehouseId))
   throw new Error('Warehouse identity changed. Reopen the review.');
 if(!csrf)throw new Error('Authenticated inventory session required.');
 if(!basic&&review.unowned_reserved_stock!==0)throw new Error('Assign outstanding reservations before reconciliation.');
 if(!Number.isSafeInteger(balance.physical_stock)||balance.physical_stock<0||!balance.counted_at)
   throw new Error('Complete a physical count before reconciliation.');
 if(balance.safety_stock!==null&&(!Number.isSafeInteger(balance.safety_stock)||balance.safety_stock<0))
   throw new Error('Invalid warehouse safety stock.');
 const allOrders=warehouseReservationOrders(review.allocationRows,item.id,item.masterSku,identity.warehouseId);
 const safety=balance.safety_stock??0;
 const warehouse:WarehouseStockBalance={basicWms:basic,poolKind:shared?'shared':'warehouse',warehouseId:identity.warehouseId,warehouseCode:identity.warehouseCode,
   warehouseName:identity.warehouseName,physicalStock:balance.physical_stock,physicalStockStatus:'verified',
   reservedStock:review.derived_reserved_stock,safetyStock:safety,safetyStockKnown:balance.safety_stock!==null,
   availableAtp:Math.max(0,balance.physical_stock-review.derived_reserved_stock-safety),
   version:balance.version,lastAuditAt:balance.counted_at};
 const onReconcile=basic?basicSafetyApi({masterSku:item.masterSku,warehouseId:identity.warehouseId,balanceId:identity.balanceId,version:balance.version},csrf,request):createWarehouseReconciliation({masterSku:item.masterSku,warehouseId:identity.warehouseId,
   balanceId:identity.balanceId,balanceVersion:balance.version,stockVersion:review.stock_version,
   operationKey:crypto.randomUUID()},warehouseReconciliationPost(csrf,request));
 return {warehouse,allOrders,onReconcile};
}
