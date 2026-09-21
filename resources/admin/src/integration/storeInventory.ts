export type InventoryOverview = {mode:'shared'|'independent';configuration_version:number;website_account?:any;totals:any;stores:any[];data:any[];mode_catalog?:any};

const csrf=()=>document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
async function json(url:string,init:RequestInit={}){
  const response=await fetch(url,{credentials:'same-origin',...init,headers:{Accept:'application/json',...(init.body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}:{}),...(init.headers||{})}});
  const body=await response.json().catch(()=>({}));
  if(!response.ok){const detail=body?.error?.message||body?.message||Object.values(body?.errors||{}).flat().join(' ')||`Request failed (${response.status})`;throw new Error(detail);}
  return body;
}
export const storeInventoryApi={
  overview:()=>json('/admin/api/inventory/store-management/overview'),
  history:()=>json('/admin/api/inventory/store-management/history?per_page=100'),
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
  mode:(target:'shared_pool'|'independent_stores',version:number,distribution:Record<string,unknown>)=>json('/admin/api/inventory/store-management/mode',{method:'POST',body:JSON.stringify({target,configuration_version:version,distribution})}),
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
    const knownPhysical=currentModeRows.filter(r=>r.counted&&r.physical!==null);
    const knownSellable=eligibleRows.filter(r=>r.counted&&r.available!==null);
    const physical=knownPhysical.length?knownPhysical.reduce((n,r)=>n+r.physical,0):null;
    const reserved=knownPhysical.reduce((n,r)=>n+(r.reserved||0),0);
    const available=knownSellable.length?knownSellable.reduce((n,r)=>n+r.available,0):null;
    const identity=currentModeRows[0] || rows[0] || modeItem || {};
    return {id:eligibleRows[0]?.balance_id ? String(eligibleRows[0].balance_id) : `variant:${variantId}`,variantId,catalogStatus:modeItem?.product_status||'active',isTest:modeItem?.classification==='sandbox_test',masterSku:identity.sku||'',barcode:'',productName:identity.product||'',variantName:identity.variant||'',imageUrl:modeItem?.thumbnail_url||'',warehouseLocation:'',mappedStoreIds:(modeItem?.mapped_stores||[]).map((store:any)=>String(store.id)),
      physicalStockStatus:physical===null?'unknown':'verified',hasUncountedAccounts:eligibleRows.some(r=>!r.counted),totalPhysicalStock:physical,reservedStock:reserved,safetyStock:0,availableStock:available,reorderPoint:0,costPrice:0,sellingPrice:0,
      channelMappings:{},autoSyncEnabled:false,historyLog:[],storeAssociations:currentModeRows.filter(r=>r.store_id).map(r=>({connectionId:String(r.store_id),storeName:r.store_name||r.account_key,platform:r.platform,
        externalSku:r.sku,connectionState:r.connection_status||'connected',syncState:'enabled',syncReadiness:r.sync_readiness,lastSyncStatus:r.external_sync?.status,syncTasks:r.external_sync?.tasks||[],balanceId:r.balance_id}))};
  });
}

export function adaptStores(overview:InventoryOverview){return [{id:'website',name:'JW Ecom Storefront',nickname:'JW Storefront',platform:'webstore',platformDisplayName:'JW Ecom Storefront',region:'Malaysia (MYR)',connectionState:'connected',isManagedByJw:true,inventorySyncState:'active',isComingSoon:false,syncStatus:'pending',readStatus:'connected'},...overview.stores.map(s=>({id:String(s.id),name:s.name,nickname:s.name,platform:s.platform,platformDisplayName:s.platform==='lazada'?'Lazada':s.platform==='shopee'?'Shopee':s.platform,region:'Malaysia (MYR)',connectionState:s.connection_status,isManagedByJw:true,inventorySyncState:s.sync_state||'setup_required',isComingSoon:false,syncStatus:s.sync_state==='paused'?'disabled':'pending',readStatus:s.connection_status})),{id:'tiktok-coming',name:'TikTok Shop',nickname:'TikTok Shop',platform:'tiktok',platformDisplayName:'TikTok Shop',region:'Regional',connectionState:'disconnected',isManagedByJw:false,inventorySyncState:'setup_required',isComingSoon:true,syncStatus:'disabled',readStatus:'disconnected'},{id:'shopify-coming',name:'Shopify',nickname:'Shopify',platform:'shopify',platformDisplayName:'Shopify',region:'Global',connectionState:'disconnected',isManagedByJw:false,inventorySyncState:'setup_required',isComingSoon:true,syncStatus:'disabled',readStatus:'disconnected'}];}

export function adaptBalances(overview:InventoryOverview){const result:any={};for(const row of overview.data){result[row.sku]??={};const key=row.store_id?String(row.store_id):'website';result[row.sku][key]={storeId:key,storeName:row.store_name||(key==='website'?'JW Ecom Storefront':row.account_key),platform:row.platform,isManagedByJw:true,connectionState:row.connection_status||'connected',isCounted:row.counted,physicalStock:row.physical,reservedStock:row.reserved||0,availableStock:row.available,syncState:'enabled',syncReadiness:row.sync_readiness,lastSyncStatus:row.external_sync?.status,syncTasks:row.external_sync?.tasks||[],balanceId:row.balance_id};}return result;}
