import {storeInventoryApi} from '../../../../../integration/storeInventory';
import {StoreWithWarehouses,SkuWarehouseStockBalance,StorePlatformWarehouse} from './warehouseInventoryTypes';

export async function loadFacilityView(item:any,stores:any[],storeBalances:any = {}) {
  const rows=await Promise.all(stores.filter(store=>!store.isComingSoon).map(async (store:any) => {
    const storeId=String(store.id);
    const basic={storeId,storeName:store.name,storeNickname:store.nickname||store.name,
      platform:store.platform,isSkuMapped:store.platform==='webstore'||item.mappedStoreIds?.includes(storeId)};
    if(store.platform==='webstore') {
      const id=`direct:${storeId}`;const balance=storeBalances[item.masterSku]?.[storeId];
      return {config:{...basic,warehouseMode:'no_warehouse',warehouseSyncStatus:'synced',syncStatus:'synced',warehouses:[]} as StoreWithWarehouses,
        balances:{[id]:{warehouseId:id,storeId,warehouseName:store.name,codeType:'internal_bin',codeValue:'DIRECT-WEB',
          isCounted:!!balance?.isCounted,physicalStock:balance?.physicalStock??null,reservedStock:balance?.reservedStock??0,
          availableStock:balance?.availableStock??null,lastSyncAt:'',syncState:'synced'} as SkuWarehouseStockBalance}};
    }
    try {
      const response=await storeInventoryApi.facilityBalances(storeId,item.variantId);
      const verified=response.status==='verified';
      const stockReady=verified&&response.stock_operations_ready===true&&response.operations_enabled===true;
      if(verified&&response.topology==='no_warehouse') {
        const id=`direct:${storeId}`;const balance=storeBalances[item.masterSku]?.[storeId];
        return {config:{...basic,warehouseMode:'no_warehouse',warehouseSyncStatus:'synced',
          syncStatus:'synced',warehouses:[]} as StoreWithWarehouses,
          balances:{[id]:{warehouseId:id,storeId,warehouseName:store.name,
            codeType:'internal_bin',codeValue:'DIRECT',isCounted:!!balance?.isCounted,
            physicalStock:balance?.physicalStock??null,reservedStock:balance?.reservedStock??0,
            availableStock:balance?.availableStock??null,lastSyncAt:'',syncState:'sync_unknown'} as SkuWarehouseStockBalance}};
      }
      const warehouses:StorePlatformWarehouse[]=(response.facilities||[]).map((facility:any)=>({
        id:String(facility.id),storeId,storeName:store.name,storeNickname:store.nickname||store.name,
        platform:store.platform,name:facility.name,warehouseName:facility.name,
        codeType:store.platform==='shopee'?'location_id':'warehouse_code',codeValue:facility.provider_id,
        region:'',inventoryWarehouseId:facility.inventory_warehouse_id,
        status:stockReady?'active':'verification_pending'
      }));
      const balances:Record<string,SkuWarehouseStockBalance>={};
      for(const facility of response.facilities||[]) {
        const id=String(facility.id);
        balances[id]={warehouseId:id,storeId,warehouseName:facility.name,
          physicalOwnerId:facility.inventory_warehouse_id?`warehouse:${facility.inventory_warehouse_id}`:undefined,
          codeType:store.platform==='shopee'?'location_id':'warehouse_code',codeValue:facility.provider_id,
          isCounted:!!facility.counted,physicalStock:facility.physical??null,
          reservedStock:facility.reserved??0,availableStock:facility.available??null,
          lastSyncAt:'',syncState:stockReady?'sync_unknown':'verification_pending'};
      }
      return {config:{...basic,warehouseMode:response.topology||'no_warehouse',
        warehouseSyncStatus:stockReady?'synced':'verification_pending',
        syncStatus:stockReady?'synced':'verification_pending',
        verificationPendingReason:response.error_code||(!stockReady&&verified?'Warehouse stock actions are pending activation':undefined),warehouses} as StoreWithWarehouses,balances};
    } catch(error:any) {
      return {config:{...basic,warehouseMode:'no_warehouse',warehouseSyncStatus:'verification_pending',
        syncStatus:'verification_pending',verificationPendingReason:error?.message||'Warehouse list unavailable',
        warehouses:[]} as StoreWithWarehouses,balances:{}};
    }
  }));
  return {storeConfigs:rows.map(row=>row.config),warehouseBalances:{[item.masterSku]:Object.assign({},...rows.map(row=>row.balances))} as Record<string,Record<string,SkuWarehouseStockBalance>>};
}
