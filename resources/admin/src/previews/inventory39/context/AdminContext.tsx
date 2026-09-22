import React,{createContext,useContext,useEffect,useMemo,useState} from 'react';
import {adaptBalances,adaptInventory,adaptStores,storeInventoryApi} from '../../../integration/storeInventory';

export const PreviewContext=createContext<any>(null);
export const useAdmin=()=>useContext(PreviewContext);
const providerStatus=(stages:any[]=[])=>{const priority:any={uncertain:8,auth_expired:7,failed:6,processing:5,queued:4,waiting_source:3,confirmed:2,superseded:1,not_applicable:0};return [...stages].sort((a,b)=>(priority[b.status]||0)-(priority[a.status]||0))[0]?.status};

export function Inventory39Provider({children,onNavigate=()=>{}}:{children:React.ReactNode;onNavigate?:(section:any)=>void}){
  const [overview,setOverview]=useState<any>(null);const [history,setHistory]=useState<any[]>([]);const [notice,setNotice]=useState('Loading inventory…');
  const reload=async()=>{try{const [o,h]=await Promise.all([storeInventoryApi.overview(),storeInventoryApi.history()]);setOverview(o);setHistory(h.data||[]);setNotice('');}catch(e:any){setNotice(e.message)}};
  useEffect(()=>{void reload()},[]);
  const value=useMemo(()=>({currentSection:'marketplace-inventory',setCurrentSection:onNavigate,marketplaceOrders:[],connections:[],
    marketplaceInventory:overview?adaptInventory(overview):[],inventoryModeCatalog:overview?.mode_catalog||{items:[],summary:{total:0,ready:0,blocked:0,excluded:0}},inventoryMode:overview?.mode||'shared',inventoryConfigurationVersion:overview?.configuration_version||0,
    inventoryStores:overview?adaptStores(overview):[],inventoryStoreBalances:overview?adaptBalances(overview):{},inventoryActivityHistory:history.map((h:any)=>{const activity=h.result?.activity||{};const transfer=h.result?.transfer;const source=activity.source_store;const destination=activity.destination_store;return {
      id:h.id,timestamp:h.created_at,actionType:h.kind==='count'?'first_count':h.kind==='intake'?'actual_intake':h.kind==='correction'?'stock_adjustment':h.kind==='transfer'?'store_transfer':h.kind,
      masterSku:activity.master_sku||transfer?.master_sku||'Unavailable',productName:activity.product_name||transfer?.product_name||'Unavailable',variantName:activity.variant_name||transfer?.variant_name,
      targetScope:source&&destination?`${source.name} → ${destination.name}`:activity.store?.name||'JW inventory',storeId:activity.store?.id,platform:activity.store?.platform,
      quantityChange:Number(activity.quantity_change??transfer?.quantity??0),previousStock:activity.previous_stock??'Unavailable',resultingStock:activity.resulting_stock??'Unavailable',
      operator:h.actor,reason:activity.reason||transfer?.reason||h.kind,notes:activity.notes||undefined,referenceId:h.id,status:h.ledger_status==='applied'?'succeeded':'failed',providerSyncStatus:providerStatus(h.result?.external_stages)
    }}),
    reloadInventory:reload,addToast:(t:any)=>setNotice(`${t.title}: ${t.message||''}`),
    recordPhysicalCountAudit:async(_id:string,count:number,reason:string,storeId?:string,item?:any)=>{let balanceId=Number(_id);if(storeId&&storeId!=='website')balanceId=item?.storeAssociations?.find((a:any)=>a.connectionId===storeId)?.balanceId||balanceId;const payload=Number.isFinite(balanceId)&&balanceId>0?{balance_id:balanceId,physical_count:count,reason}:{variant_id:item?.variantId,channel_key:storeId&&storeId!=='website'?`marketplace:${storeId}`:'website',physical_count:count,reason};await storeInventoryApi.review('count',payload);await reload()},
    allocateStock:async(item:any,count:number,allocations:Record<string,number>,reason:string)=>{const normalized=Object.fromEntries(Object.entries(allocations).map(([id,quantity])=>[id==='website'?'website':`marketplace:${id}`,quantity]));const receipt=await storeInventoryApi.allocate({variant_id:item.variantId,physical_count:count,allocations:normalized,reason});await reload();return receipt},
    adjustStock:async(_id:string,delta:number,reason:string,kind='correction',storeId?:string,item?:any)=>{const normalized=kind==='adjustment'?'correction':kind;let balanceId=Number(_id);if(storeId){const target=overview.data.find((r:any)=>r.variant_id===item?.variantId&&(r.store_id?String(r.store_id):'website')===storeId);balanceId=target?.balance_id||balanceId;}await storeInventoryApi.review(normalized,{balance_id:balanceId,...(normalized==='intake'?{quantity:delta}:{quantity_delta:delta}),reason});await reload()},
    executeStoreTransfer:async(p:any)=>{const src=overview.data.find((r:any)=>r.sku===p.masterSku&&(r.store_id?String(r.store_id):'website')===p.sourceStoreId);const dst=overview.data.find((r:any)=>r.sku===p.masterSku&&(r.store_id?String(r.store_id):'website')===p.destinationStoreId);if(!src||!dst)throw new Error('Store balance is unavailable.');let receipt=await storeInventoryApi.review('transfer',{source_balance_id:src.balance_id,destination_balance_id:dst.balance_id,quantity:p.quantity,reason:p.reason});const pending=(receipt.external_stages||[]).some((stage:any)=>['queued','waiting_source','processing'].includes(stage.status));if(pending)receipt=await storeInventoryApi.waitForOperation(receipt.operation_id);await reload();return receipt},
    syncAll:async()=>{const r=await storeInventoryApi.syncAll();await reload();return r},retrySync:storeInventoryApi.retry,bulkCheckStock:storeInventoryApi.bulkCheckStock,
    getInventoryOperation:storeInventoryApi.operation,checkInventoryOperation:storeInventoryApi.checkOperation,waitForInventoryOperation:storeInventoryApi.waitForOperation,
    switchInventoryMode:async(mode:any,distribution:any)=>{const r=await storeInventoryApi.mode(mode==='shared'?'shared_pool':'independent_stores',overview.configuration_version,distribution||{});await reload();return r},
    toggleStoreManagement:async(id:string,enable:boolean,reason:string)=>{let r;if(enable){const review=await storeInventoryApi.syncReadiness(id);if(!review.ready)return review;r=await storeInventoryApi.resumeSync(id,reason,review.review_token)}else r=await storeInventoryApi.pauseSync(id,reason);await reload();return r},
    broadcastStockToChannels:()=>{},
  }),[overview,history,onNavigate]);
  return <PreviewContext.Provider value={value}><div role="status" className="px-4 pt-3 text-xs text-amber-600">{notice}</div>{children}</PreviewContext.Provider>;
}
