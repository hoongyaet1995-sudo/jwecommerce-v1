import React,{createContext,useContext,useEffect,useMemo,useState,useCallback,useRef} from 'react';
import {adaptBalances,adaptInventory,adaptStores,storeInventoryApi} from '../../../integration/storeInventory';
import {adaptInventoryHistoryEntry} from './inventoryHistoryAdapters';

export const PreviewContext=createContext<any>(null);
export const useAdmin=()=>useContext(PreviewContext);

export function Inventory39Provider({children,onNavigate=()=>{}}:{children:React.ReactNode;onNavigate?:(section:any)=>void}){
  const [overview,setOverview]=useState<any>(null);const [history,setHistory]=useState<any[]>([]);const [notice,setNotice]=useState('Loading inventory…');
  const request=useRef(0);const polling=useRef(false);
  const reload=useCallback(async()=>{const version=++request.current;try{const [o,h]=await Promise.all([storeInventoryApi.overview(),storeInventoryApi.history()]);if(version!==request.current)return;setOverview(o);setHistory(h.data||[]);setNotice('');}catch(e:any){if(version===request.current)setNotice(e.message)}},[]);
  useEffect(()=>{void reload();return()=>{request.current++}},[reload]);
  const confirming=Object.values(overview?.shared_store_confirmations || {}).some((stores:any)=>
    Object.values(stores).some((tasks:any)=>tasks.some((task:any)=>task.status==='Confirming stock')));
  useEffect(()=>{if(!confirming)return;const timer=window.setInterval(()=>{
    if(polling.current)return;polling.current=true;void reload().finally(()=>{polling.current=false});
  },5000);return()=>window.clearInterval(timer)},[confirming,reload]);
  const value=useMemo(()=>({currentSection:'marketplace-inventory',setCurrentSection:onNavigate,marketplaceOrders:[],connections:[],
    marketplaceInventory:overview?adaptInventory(overview):[],inventoryModeCatalog:overview?.mode_catalog||{items:[],summary:{total:0,ready:0,blocked:0,excluded:0}},inventoryMode:overview?.mode||'shared',inventoryConfigurationVersion:overview?.configuration_version||0,
    inventoryStores:overview?adaptStores(overview):[],inventoryStoreBalances:overview?adaptBalances(overview):{},inventoryActivityHistory:history.map(adaptInventoryHistoryEntry),
    reloadInventory:reload,addToast:(t:any)=>setNotice(`${t.title}: ${t.message||''}`),
    recordPhysicalCountAudit:async(_id:string,count:number,reason:string,storeId?:string,item?:any)=>{const association=storeId&&storeId!=='website'?item?.storeAssociations?.find((a:any)=>a.connectionId===storeId):null;const balanceId=storeId&&storeId!=='website'?Number(association?.balanceId):Number(_id);const payload=Number.isFinite(balanceId)&&balanceId>0?{balance_id:balanceId,physical_count:count,reason}:{variant_id:item?.variantId,channel_key:storeId&&storeId!=='website'?`marketplace:${storeId}`:'website',physical_count:count,reason};await storeInventoryApi.review('count',payload);await reload()},
    allocateStock:async(item:any,count:number,allocations:Record<string,number>,reason:string)=>{const normalized=Object.fromEntries(Object.entries(allocations).map(([id,quantity])=>[id==='website'?'website':`marketplace:${id}`,quantity]));const receipt=await storeInventoryApi.allocate({variant_id:item.variantId,physical_count:count,allocations:normalized,reason});await reload();return receipt},
    adjustStock:async(_id:string,delta:number,reason:string,kind='correction',storeId?:string,item?:any)=>{const normalized=kind==='adjustment'?'correction':kind;let balanceId=Number(_id);if(storeId){const target=overview.data.find((r:any)=>r.variant_id===item?.variantId&&r.pool_kind!=='facility'&&(r.store_id?String(r.store_id):'website')===storeId);if(!target)throw new Error('The selected store balance is unavailable. Refresh inventory.');balanceId=target.balance_id;}if(!Number.isFinite(balanceId)||balanceId<=0)throw new Error('A counted balance is required.');await storeInventoryApi.review(normalized,{balance_id:balanceId,...(normalized==='intake'?{quantity:delta}:{quantity_delta:delta}),reason});await reload()},
    executeStoreTransfer:async(p:any)=>{const src=overview.data.find((r:any)=>r.sku===p.masterSku&&(r.store_id?String(r.store_id):'website')===p.sourceStoreId);const dst=overview.data.find((r:any)=>r.sku===p.masterSku&&(r.store_id?String(r.store_id):'website')===p.destinationStoreId);if(!src||!dst)throw new Error('Store balance is unavailable.');let receipt=await storeInventoryApi.review('transfer',{source_balance_id:src.balance_id,destination_balance_id:dst.balance_id,quantity:p.quantity,reason:p.reason});const pending=(receipt.external_stages||[]).some((stage:any)=>['queued','waiting_source','processing'].includes(stage.status));if(pending)receipt=await storeInventoryApi.waitForOperation(receipt.operation_id);await reload();return receipt},
    syncAll:async()=>{const r=await storeInventoryApi.syncAll();await reload();return r},retrySync:storeInventoryApi.retry,bulkCheckStock:storeInventoryApi.bulkCheckStock,
    getInventoryOperation:storeInventoryApi.operation,checkInventoryOperation:storeInventoryApi.checkOperation,waitForInventoryOperation:storeInventoryApi.waitForOperation,
    switchInventoryMode:async(mode:any,distribution:any,confirmReset=false)=>{const r=await storeInventoryApi.mode(mode==='shared'?'shared_pool':'independent_stores',overview.configuration_version,distribution||{},confirmReset);await reload();return r},
    toggleStoreManagement:async(id:string,enable:boolean,_reason:string)=>{const r=await storeInventoryApi.sharedPoolTakeover(id,enable);await reload();return r},
    broadcastStockToChannels:()=>{},
  }),[overview,history,onNavigate]);
  return <PreviewContext.Provider value={value}><div role="status" className="px-4 pt-3 text-xs text-amber-600">{notice}</div>{children}</PreviewContext.Provider>;
}
