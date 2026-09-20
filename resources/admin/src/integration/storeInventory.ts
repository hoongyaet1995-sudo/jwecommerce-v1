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
  syncAll:()=>json('/admin/api/inventory/store-management/sync-all',{method:'POST',body:'{}'}),
  retry:(id:string,reconcile_outcome?:'not_applied')=>json(`/admin/api/inventory/store-management/sync-tasks/${id}/retry`,{method:'POST',body:JSON.stringify(reconcile_outcome?{reconcile_outcome}:{})}),
  takeoverReadiness:(id:string)=>json(`/admin/api/inventory/store-management/stores/${id}/readiness`),
  takeover:(id:string,reason:string,review_token:string)=>json(`/admin/api/inventory/store-management/stores/${id}/takeover`,{method:'POST',body:JSON.stringify({reason,review_token})}),
  pause:(id:string)=>json(`/admin/api/inventory/store-management/stores/${id}/pause`,{method:'POST',body:'{}'}),
  mode:(target:'shared_pool'|'independent_stores',version:number,distribution:Record<string,unknown>)=>json('/admin/api/inventory/store-management/mode',{method:'POST',body:JSON.stringify({target,configuration_version:version,distribution})}),
};

export function adaptInventory(overview:InventoryOverview){
  const groups=new Map<number,any[]>(); overview.data.forEach(row=>groups.set(row.variant_id,[...(groups.get(row.variant_id)||[]),row]));
  return [...groups.entries()].map(([variantId,rows])=>{
    const known=rows.filter(r=>r.counted); const physical=known.length?known.reduce((n,r)=>n+r.physical,0):null;
    const reserved=known.reduce((n,r)=>n+(r.reserved||0),0); const available=known.length?known.reduce((n,r)=>n+r.available,0):null;
    return {id:String(rows[0].balance_id),variantId,masterSku:rows[0].sku||'',barcode:'',productName:rows[0].product||'',variantName:rows[0].variant||'',imageUrl:'',warehouseLocation:'',
      physicalStockStatus:physical===null?'unknown':'verified',hasUncountedAccounts:rows.some(r=>!r.counted),totalPhysicalStock:physical,reservedStock:reserved,safetyStock:0,availableStock:available,reorderPoint:0,costPrice:0,sellingPrice:0,
      channelMappings:{},autoSyncEnabled:false,historyLog:[],storeAssociations:rows.filter(r=>r.store_id).map(r=>({connectionId:String(r.store_id),storeName:r.store_name||r.account_key,platform:r.platform,
        externalSku:r.sku,connectionState:r.connection_status||'connected',syncState:'enabled',lastSyncStatus:r.external_sync?.status,syncTasks:r.external_sync?.tasks||[],balanceId:r.balance_id}))};
  });
}

export function adaptStores(overview:InventoryOverview){const websiteManaged=overview.website_account?.management_status==='managed';return [{id:'website',name:'JW Ecom Storefront',nickname:'JW Storefront',platform:'webstore',platformDisplayName:'JW Ecom Storefront',region:'Malaysia (MYR)',connectionState:'connected',isManagedByJw:websiteManaged,isComingSoon:false,syncStatus:websiteManaged?'synced':'unlinked',readStatus:'connected'},...overview.stores.map(s=>({id:String(s.id),name:s.name,nickname:s.name,platform:s.platform,platformDisplayName:s.platform==='lazada'?'Lazada':s.platform==='shopee'?'Shopee':s.platform,region:'Malaysia (MYR)',connectionState:s.connection_status,isManagedByJw:s.management_status==='managed',isComingSoon:false,syncStatus:s.management_status==='managed'?'synced':'unlinked',readStatus:s.connection_status})),{id:'tiktok-coming',name:'TikTok Shop',nickname:'TikTok Shop',platform:'tiktok',platformDisplayName:'TikTok Shop',region:'Regional',connectionState:'disconnected',isManagedByJw:false,isComingSoon:true,syncStatus:'unlinked',readStatus:'disconnected'},{id:'shopify-coming',name:'Shopify',nickname:'Shopify',platform:'shopify',platformDisplayName:'Shopify',region:'Global',connectionState:'disconnected',isManagedByJw:false,isComingSoon:true,syncStatus:'unlinked',readStatus:'disconnected'}];}

export function adaptBalances(overview:InventoryOverview){const result:any={};for(const row of overview.data){result[row.sku]??={};const key=row.store_id?String(row.store_id):'website';result[row.sku][key]={storeId:key,storeName:row.store_name||(key==='website'?'JW Ecom Storefront':row.account_key),platform:row.platform,isManagedByJw:true,connectionState:row.connection_status||'connected',isCounted:row.counted,physicalStock:row.physical,reservedStock:row.reserved||0,availableStock:row.available,syncState:'enabled',lastSyncStatus:row.external_sync?.status,syncTasks:row.external_sync?.tasks||[],balanceId:row.balance_id};}return result;}
