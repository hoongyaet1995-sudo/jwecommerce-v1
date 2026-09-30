export type InventoryOverview = {mode:'shared'|'independent';single_current_stock?:boolean;configuration_version:number;website_account?:any;totals:any;stores:any[];data:any[];mode_catalog?:any};

const csrf=()=>document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
async function json(url:string,init:RequestInit={}){
  const response=await fetch(url,{credentials:'same-origin',...init,headers:{Accept:'application/json',...(init.body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}:{}),...(init.headers||{})}});
  const body=await response.json().catch(()=>({}));
  if(!response.ok){const detail=body?.error?.message||body?.message||Object.values(body?.errors||{}).flat().join(' ')||`Request failed (${response.status})`;throw new Error(detail);}
  return body;
}
const terminalSyncStatuses=new Set(['confirmed','not_applicable','superseded','uncertain','auth_expired','failed']);
export async function waitForInventoryOperation(id:string,options:{maxAttempts?:number;wait?:(milliseconds:number)=>Promise<void>}={}){
  const maxAttempts=options.maxAttempts??20;
  const wait=options.wait??((milliseconds:number)=>new Promise<void>(resolve=>window.setTimeout(resolve,milliseconds)));
  let latest:any=null;
  for(let attempt=0;attempt<maxAttempts;attempt+=1){
    latest=await json(`/admin/api/inventory/store-management/operations/${encodeURIComponent(id)}`);
    const stages=latest?.external_stages||[];
    if(stages.length>0&&stages.every((stage:any)=>terminalSyncStatuses.has(stage.status)))return latest;
    if(attempt<maxAttempts-1)await wait(750);
  }
  return latest;
}
export const storeInventoryApi={
  overview:()=>json('/admin/api/inventory/store-management/overview'),
  facilities:(storeId:string)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities`),
  facilityBalances:(storeId:string,variantId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/skus/${variantId}/facilities`),
  testFacilityAcceptance:(storeId:string,variantId:number,facilityId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/skus/${variantId}/test-facility-acceptance?facility=${facilityId}`),
  stockTakeRecovery:(storeId:string,facilityId:number,variantId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/skus/${variantId}/stock-take-recovery`),
  reviewHistoricalStockTakeOrders:(storeId:string,facilityId:number,variantId:number,lines:Array<{order_id:number;line_key:string;decision:'historical'|'risk_accepted'}>,reason:string)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/skus/${variantId}/stock-take-recovery/order-line-review`,{method:'POST',body:JSON.stringify({lines,reason})}),
  facilityMigrationPreview:(storeId:string,variantId:number,physicalByProviderId:Record<string,number>)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/skus/${variantId}/facilities/migration-preview`,{method:'POST',body:JSON.stringify({physical_by_provider_id:physicalByProviderId})}),
  refreshFacilities:(storeId:string)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/refresh`,{method:'POST',body:'{}'}),
  reviewFacilityBinding:(storeId:string,facilityId:number,warehouseId:number,reason:string)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/binding-reviews`,{method:'POST',body:JSON.stringify({warehouse_id:warehouseId,reason})}),
  confirmFacilityBinding:(reviewId:string,operationKey:string)=>json(`/admin/api/inventory/store-management/facility-binding-reviews/${encodeURIComponent(reviewId)}/confirm`,{method:'POST',body:JSON.stringify({operation_key:operationKey})}),
  async facilityStockAction(storeId:string,facilityId:number,variantId:number,kind:'count'|'intake'|'audit',quantity:number,reason:string){
    const review=await json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/skus/${variantId}/stock-reviews`,{method:'POST',body:JSON.stringify({kind,quantity,reason})});
    return json(`/admin/api/inventory/store-management/facility-stock-reviews/${encodeURIComponent(review.id)}/confirm`,{method:'POST',body:JSON.stringify({operation_key:crypto.randomUUID()})});
  },
  async directAllocation(variantId:number,physicalCount:number,allocations:Record<string,number>,reason:string){
    return json('/admin/api/inventory/store-management/direct-allocations',{method:'POST',body:JSON.stringify({variant_id:variantId,physical_count:physicalCount,allocations,reason,operation_key:crypto.randomUUID()})});
  },
  baselineObserve:(storeKey:string,facilityId:number|null,variantId:number)=>json('/admin/api/inventory/store-management/first-count-baseline/observe',{method:'POST',body:JSON.stringify({store_key:storeKey,facility_id:facilityId,variant_id:variantId})}),
  baselineCommit:(payload:{store_key:string;facility_id:number|null;variant_id:number;source:'marketplace'|'manual';quantity:number;expected_provider_quantity:number|null;operation_key:string;allow_provider_overwrite?:boolean})=>json('/admin/api/inventory/store-management/first-count-baseline/commit',{method:'POST',body:JSON.stringify(payload)}),
  startStockRetrieval:(variantIds:number[])=>json('/admin/api/inventory/store-management/stock-retrieval-runs',{method:'POST',body:JSON.stringify({variant_ids:variantIds})}),
  latestStockRetrieval:()=>json('/admin/api/inventory/store-management/stock-retrieval-runs/latest'),
  stockRetrieval:(runId:string)=>json(`/admin/api/inventory/store-management/stock-retrieval-runs/${encodeURIComponent(runId)}`),
  resumeStockRetrieval:(runId:string)=>json(`/admin/api/inventory/store-management/stock-retrieval-runs/${encodeURIComponent(runId)}/resume`,{method:'POST',body:'{}'}),
  async facilityTransfer(variantId:number,sourceFacilityId:number|null,destinationFacilityId:number|null,quantity:number,reason:string,
    sourceStoreId:number|null=null,destinationStoreId:number|null=null){
    const review=await json('/admin/api/inventory/store-management/facility-transfer-reviews',{method:'POST',body:JSON.stringify({variant_id:variantId,source_facility_id:sourceFacilityId,destination_facility_id:destinationFacilityId,source_store_id:sourceStoreId,destination_store_id:destinationStoreId,quantity,reason})});
    return json(`/admin/api/inventory/store-management/facility-transfer-reviews/${encodeURIComponent(review.id)}/confirm`,{method:'POST',body:JSON.stringify({operation_key:crypto.randomUUID()})});
  },
  facilitySyncDetails:(storeId:string,facilityId:number,variantId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/skus/${variantId}/sync`),
  facilitySyncCheck:(storeId:string,facilityId:number,variantId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/skus/${variantId}/sync/check`,{method:'POST',body:'{}'}),
  facilityResync:(storeId:string,facilityId:number,variantId:number,operationKey:string)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/facilities/${facilityId}/skus/${variantId}/sync/resync`,{method:'POST',body:JSON.stringify({operation_key:operationKey})}),
  syncDetails:(storeId:string,variantId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/skus/${variantId}/sync`),
  checkStock:(storeId:string,variantId:number)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/skus/${variantId}/sync/check`,{method:'POST',body:'{}'}),
  bulkCheckStock:(items:Array<{store_id:number;variant_id:number}>)=>json('/admin/api/inventory/store-management/sync-status/bulk-check',{method:'POST',body:JSON.stringify({items})}),
  resyncStock:(storeId:string,variantId:number,operationKey:string)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(storeId)}/skus/${variantId}/sync/resync`,{method:'POST',body:JSON.stringify({operation_key:operationKey})}),
  history:()=>json('/admin/api/inventory/store-management/history?per_page=100'),
  operation:(id:string)=>json(`/admin/api/inventory/store-management/operations/${encodeURIComponent(id)}`),
  checkOperation:(id:string)=>json(`/admin/api/inventory/store-management/operations/${encodeURIComponent(id)}/check`,{method:'POST',body:'{}'}),
  waitForOperation:waitForInventoryOperation,
  async review(kind:string,payload:Record<string,unknown>){
    const review=await json('/admin/api/inventory/store-management/reviews',{method:'POST',body:JSON.stringify({kind,...payload})});
    return json(`/admin/api/inventory/store-management/reviews/${review.id}/confirm`,{method:'POST',body:JSON.stringify({operation_key:crypto.randomUUID()})});
  },
  async allocate(payload:Record<string,unknown>){
    const review=await json('/admin/api/inventory/store-management/allocations',{method:'POST',body:JSON.stringify(payload)});
    return json(`/admin/api/inventory/store-management/allocations/${review.id}/confirm`,{method:'POST',body:JSON.stringify({operation_key:crypto.randomUUID()})});
  },
  syncAll:()=>json('/admin/api/inventory/store-management/sync-all',{method:'POST',body:'{}'}),
  retry:(id:string)=>json(`/admin/api/inventory/store-management/sync-tasks/${id}/retry`,{method:'POST',body:'{}'}),
  syncReadiness:(id:string)=>json(`/admin/api/inventory/store-management/stores/${id}/sync-readiness`),
  resumeSync:(id:string,reason:string,review_token:string)=>json(`/admin/api/inventory/store-management/stores/${id}/resume-sync`,{method:'POST',body:JSON.stringify({reason,review_token})}),
  pauseSync:(id:string,reason:string)=>json(`/admin/api/inventory/store-management/stores/${id}/pause-sync`,{method:'POST',body:JSON.stringify({reason})}),
  sharedPoolTakeover:(id:string,enabled:boolean)=>json(`/admin/api/inventory/store-management/stores/${encodeURIComponent(id)}/${enabled?'takeover':'pause'}`,{method:'POST',body:'{}'}),
  mode:(target:'shared_pool'|'independent_stores',version:number,distribution:Record<string,unknown>,confirmReset=false)=>json('/admin/api/inventory/store-management/mode',{method:'POST',body:JSON.stringify({target,configuration_version:version,distribution,confirm_reset:confirmReset})}),
};

export function adaptInventory(overview:InventoryOverview){
  const groups=new Map<number,any[]>(); overview.data.forEach(row=>groups.set(row.variant_id,[...(groups.get(row.variant_id)||[]),row]));
  for (const item of overview.mode_catalog?.items || []) {
    if (!groups.has(Number(item.variant_id))) groups.set(Number(item.variant_id), []);
  }
  return [...groups.entries()].map(([variantId,rows])=>{
    const modeItem=overview.mode_catalog?.items?.find((entry:any)=>Number(entry.variant_id)===variantId);
    const mappedIds=new Set((modeItem?.mapped_stores||[]).map((store:any)=>String(store.id)));
    const currentModeRows=rows.filter(r=>overview.mode==='shared' ? r.account_key==='shared' : r.account_key!=='shared');
    const eligibleRows=currentModeRows.filter(r=>r.allocation_eligible ?? (!r.store_id || mappedIds.has(String(r.store_id))));
    const knownPhysical=currentModeRows.filter(r=>r.not_applicable!==true&&r.counted&&(overview.single_current_stock?r.current_stock!==null:r.physical!==null));
    const knownSellable=eligibleRows.filter(r=>r.counted&&r.available!==null);
    const physical=knownPhysical.length?knownPhysical.reduce((n,r)=>n+(overview.single_current_stock?r.current_stock:r.physical),0):null;
    const reserved=overview.single_current_stock?0:knownPhysical.reduce((n,r)=>n+(r.reserved||0),0);
    const available=knownSellable.length?knownSellable.reduce((n,r)=>n+r.available,0):null;
    const identity=currentModeRows[0] || rows[0] || modeItem || {};
    return {id:eligibleRows[0]?.balance_id ? String(eligibleRows[0].balance_id) : `variant:${variantId}`,variantId,productId:Number(modeItem?.product_id||identity.product_id||0),catalogStatus:modeItem?.product_status||'active',isTest:modeItem?.classification==='sandbox_test',masterSku:identity.sku||'',barcode:'',productName:identity.product||modeItem?.product||'',variantName:identity.variant||'',imageUrl:modeItem?.thumbnail_url||'',warehouseLocation:'',mappedStoreIds:(modeItem?.mapped_stores||[]).map((store:any)=>String(store.id)),singleCurrentStock:overview.single_current_stock===true,currentStock:physical,
      physicalStockStatus:physical===null?'unknown':'verified',hasUncountedAccounts:eligibleRows.some(r=>!r.counted),totalPhysicalStock:physical,reservedStock:reserved,safetyStock:0,availableStock:available,reorderPoint:0,costPrice:0,sellingPrice:0,
      channelMappings:{},autoSyncEnabled:false,historyLog:[],storeAssociations:currentModeRows.filter(r=>r.store_id).map(r=>({connectionId:String(r.store_id),storeName:r.store_name||r.account_key,platform:r.platform,
        externalSku:r.sku,connectionState:r.connection_status||'connected',syncState:'enabled',syncReadiness:r.sync_readiness,lastSyncStatus:r.external_sync?.status,syncTasks:r.external_sync?.tasks||[],balanceId:r.balance_id}))};
  });
}

export function adaptStores(overview:InventoryOverview){return [{id:'website',name:'JW Ecom Storefront',nickname:'JW Storefront',platform:'webstore',platformDisplayName:'JW Ecom Storefront',region:'Malaysia (MYR)',connectionState:'connected',isManagedByJw:true,inventorySyncState:'active',isComingSoon:false,syncStatus:'pending',readStatus:'connected'},...overview.stores.map(s=>{const readiness=(overview.mode_catalog?.items||[]).flatMap((item:any)=>item.mapped_stores||[]).filter((mapped:any)=>String(mapped.id)===String(s.id)).map((mapped:any)=>mapped.sync_readiness);const hasReady=readiness.some((state:any)=>state?.ready);const hasBlocked=readiness.some((state:any)=>state&&!state.ready);const inventorySyncState=s.sync_state==='paused'?'paused':hasReady&&hasBlocked?'partially_ready':hasReady?'active':'setup_required';return {id:String(s.id),name:s.name,nickname:s.name,platform:s.platform,environment:s.account_mode==='production'?'production':'sandbox',platformDisplayName:s.platform==='lazada'?'Lazada':s.platform==='shopee'?'Shopee':s.platform,region:'Malaysia (MYR)',connectionState:s.connection_status,isManagedByJw:overview.mode==='shared'?s.shared_pool_takeover_enabled===true:s.management_status==='managed',inventorySyncState,isComingSoon:false,syncStatus:inventorySyncState==='paused'?'disabled':'pending',readStatus:s.connection_status}}),{id:'tiktok-coming',name:'TikTok Shop',nickname:'TikTok Shop',platform:'tiktok',platformDisplayName:'TikTok Shop',region:'Regional',connectionState:'disconnected',isManagedByJw:false,inventorySyncState:'setup_required',isComingSoon:true,syncStatus:'disabled',readStatus:'disconnected'},{id:'shopify-coming',name:'Shopify',nickname:'Shopify',platform:'shopify',platformDisplayName:'Shopify',region:'Global',connectionState:'disconnected',isManagedByJw:false,inventorySyncState:'setup_required',isComingSoon:true,syncStatus:'disabled',readStatus:'disconnected'}];}

export function adaptBalances(overview:InventoryOverview){
  const result:any={};
  const facilities=new Map<string,any[]>();
  for(const row of overview.data){
    if(row.pool_kind==='facility'){
      if(!row.store_id && row.account_key!=='website')continue;
      const key=`${row.sku}:${row.store_id||'website'}`;
      facilities.set(key,[...(facilities.get(key)||[]),row]);
      continue;
    }
    result[row.sku]??={};
    const key=row.store_id?String(row.store_id):'website';
    result[row.sku][key]={storeId:key,storeName:row.store_name||(key==='website'?'JW Ecom Storefront':row.account_key),platform:row.platform,isManagedByJw:true,connectionState:row.connection_status||'connected',isCounted:row.counted,physicalStock:overview.single_current_stock?row.current_stock:row.physical,reservedStock:overview.single_current_stock?0:row.reserved??0,availableStock:row.available,currentStock:overview.single_current_stock?row.current_stock:undefined,singleCurrentStock:overview.single_current_stock===true,syncState:'enabled',syncReadiness:row.sync_readiness,lastSyncStatus:row.external_sync?.status,syncTasks:row.external_sync?.tasks||[],balanceId:row.balance_id};
  }
  for(const rows of facilities.values()){
    const first=rows[0];
    const key=first.store_id?String(first.store_id):'website';
    const applicableRows=rows.filter(row=>row.not_applicable!==true);
    const counted=applicableRows.length>0&&applicableRows.every(row=>row.counted===true&&Number.isFinite(overview.single_current_stock?row.current_stock:row.physical)&&Number.isFinite(row.available));
    const knownCurrentRows=overview.single_current_stock?applicableRows.filter(row=>row.counted===true&&Number.isFinite(row.current_stock)):[];
    const knownCurrent=knownCurrentRows.length?knownCurrentRows.reduce((sum,row)=>sum+Number(row.current_stock),0):null;
    result[first.sku]??={};
    result[first.sku][key]={storeId:key,storeName:first.store_name||(key==='website'?'JW Ecom Storefront':first.account_key),platform:first.platform,isManagedByJw:true,connectionState:first.connection_status||'connected',isCounted:counted,physicalStock:overview.single_current_stock?knownCurrent:counted?applicableRows.reduce((sum,row)=>sum+row.physical,0):null,reservedStock:overview.single_current_stock?0:applicableRows.reduce((sum,row)=>sum+(row.reserved??0),0),availableStock:overview.single_current_stock?knownCurrent:counted?applicableRows.reduce((sum,row)=>sum+row.available,0):null,currentStock:overview.single_current_stock?knownCurrent:undefined,unknownFacilities:overview.single_current_stock?applicableRows.length-knownCurrentRows.length:undefined,notApplicableFacilities:rows.length-applicableRows.length,singleCurrentStock:overview.single_current_stock===true,facilityCount:rows.length,syncState:'enabled',syncReadiness:first.sync_readiness,lastSyncStatus:first.external_sync?.status,syncTasks:first.external_sync?.tasks||[]};
  }
  return result;
}
