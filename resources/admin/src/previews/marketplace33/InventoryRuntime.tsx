import React, {useEffect, useRef, useState} from 'react';
import {PreviewContext} from './context/AdminContext';
import {MarketplaceInventoryPage} from './components/marketplace/MarketplaceInventoryPage';
import {MarketplaceInventoryPage as Inventory36} from '../marketplace36/components/marketplace/MarketplaceInventoryPage';
import {readInventoryPages} from './readInventoryPages';
import {readCombinedMovementHistory} from './readMovementHistory';
import {readExceptions} from './readExceptions';
import {serializedFetch} from './serializedFetch';
import {useWarehouses} from '../marketplace36/useWarehouses';
import {useRouting} from '../marketplace36/useRouting';
import {useTransfers} from '../marketplace36/useTransfers';

export function InventoryRuntime({zip36=true,embedded=false,onNavigate,intakeProductIds}:{zip36?:boolean;embedded?:boolean;onNavigate?:(section:any)=>void;intakeProductIds?:number[]}) {
  const [inventoryMode,setInventoryMode]=useState('unconfigured');
  const [configuration,setConfiguration]=useState<any>(null);
  const setupKeys=useRef(new Map<string,string>());
  useEffect(()=>{
    if(!zip36)return;
    const abort=new AbortController();
    serializedFetch('/admin/api/inventory/readiness',{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}})
      .then(async response=>{if(!response.ok)throw new Error('Inventory configuration unavailable.');return response.json();})
      .then(result=>{
        const mode=result.operating_mode===null?'unconfigured':result.operating_mode;
        if(!['shared_pool','independent_warehouses','unconfigured'].includes(mode))
          throw new Error('Inventory configuration requires review.');
        setInventoryMode(mode);
        setConfiguration(result);
      }).catch(error=>{if(!abort.signal.aborted)setNotice(error.message);});
    return()=>abort.abort();
  },[zip36]);
  const [items,setItems]=useState<any[]>([]);
  const [inventoryLoaded,setInventoryLoaded]=useState(false);
  const [inventoryRevision,setInventoryRevision]=useState(0);
  const [connections,setConnections]=useState<any[]>([]);
  const [connectionsStatus,setConnectionsStatus]=useState('Loading');
  useEffect(()=>{
    const abort=new AbortController();
    serializedFetch('/admin/api/marketplace/connections',{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}})
      .then(async response=>{if(!response.ok)throw new Error('Store connections unavailable');return response.json();})
      .then(result=>{
        if(!Array.isArray(result.stores))throw new Error('Store connections unavailable');
        setConnections(result.stores.filter((store:any)=>['shopee','lazada'].includes(store.platform)));
        setConnectionsStatus('Loaded');
      }).catch(error=>{if(!abort.signal.aborted)setConnectionsStatus('Unavailable');});
    return ()=>abort.abort();
  },[]);
  const [notice,setNotice]=useState('Loading database inventory…');
  const warehouses=useWarehouses(zip36,setNotice);
  const routing=useRouting(zip36,warehouses.entityWarehouses,setNotice);
  const transfers=useTransfers(zip36,setNotice);
  const [exceptions,setExceptions]=useState<any[]>([]);
  const [exceptionStatus,setExceptionStatus]=useState('Loading exceptions…');
  useEffect(()=>{
    const abort=new AbortController();
    readExceptions(abort.signal).then(rows=>{setExceptions(rows);setExceptionStatus(`${rows.length} recorded exceptions loaded. Accounting may be disabled; this is not proof of order coverage.`);})
      .catch(error=>{if(!abort.signal.aborted)setExceptionStatus(error.message);});
    return ()=>abort.abort();
  },[]);
  useEffect(()=>{
    const abort=new AbortController();
    readInventoryPages(abort.signal)
      .then(rows=>{
        setItems(rows.map((row:any)=>({
          id:String(row.id),masterSku:row.sku||'',barcode:'',productName:row.product_title||'',variantName:row.variant_title||'',
          dataClassification:row.data_classification??'unclassified',
          legacyJwStock:row.available_stock??null,
          jwProductId:row.product_id,
          imageUrl:'',warehouseLocation:'',warehouseName:'',
          physicalStockStatus:zip36&&row.wms_summary?.physical_stock!=null?'verified':'unknown',
          totalPhysicalStock:zip36?(row.wms_summary?.physical_stock??null):null,
          reservedStock:zip36?(row.wms_summary?.owned_reserved??null):row.marketplace_reserved_stock,
          safetyStock:zip36?(row.wms_summary?.safety_stock??null):null,
          availableStock:zip36?(row.wms_summary?.available??null):null,reorderPoint:0,
          costPrice:null,sellingPrice:row.selling_price,channelMappings:{},autoSyncEnabled:false,historyLog:[],
          storeAssociations:row.channel_links.filter((l:any)=>['shopee','lazada'].includes(l.platform)).map((l:any)=>({
            connectionId:String(l.store_id),storeName:l.shop_name,platform:l.platform,externalSku:l.external_sku??'',
            externalItemId:l.external_product_id,externalModelId:l.external_variant_id,
            connectionState:l.connection_status,syncState:'awaiting_reconciliation',
            channelStockSnapshot:l.catalog_stock,channelStockFetchedAt:l.catalog_fetched_at,
          })),
        })));
        setNotice(`Loaded ${rows.length} JW SKUs across all returned pages. This is not an atomic stock review.`);
        setInventoryLoaded(true);
      }).catch(e=>{if(e.name!=='AbortError')setNotice(e.message);});
    return ()=>abort.abort();
  },[inventoryRevision,zip36]);
  const unavailable=()=>setNotice('Preview only: this operation has no enabled persistence or platform write adapter.');
  const pending=()=>{unavailable();throw new Error('This operation is not connected in the ZIP36 preview.');};
  const configureMode=async(mode:string,_operator:string,reason:string)=>{
    if(!configuration?.basic_wms_enabled||inventoryMode!=='unconfigured')
      return {success:false,error:'Basic WMS initial setup is unavailable. Existing mode conversion is deferred.'};
    const payload={mode,version:configuration.configuration_version,reason};
    const identity=JSON.stringify(payload);
    if(!setupKeys.current.has(identity))setupKeys.current.set(identity,crypto.randomUUID());
    const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if(!csrf)return {success:false,error:'Reload to restore the authenticated session.'};
    const response=await fetch('/admin/api/inventory/basic/configuration',{method:'POST',credentials:'same-origin',
      headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
      body:JSON.stringify({...payload,operation_key:setupKeys.current.get(identity)})});
    const result=await response.json();
    if(!response.ok)return {success:false,error:result.message||'Initial configuration failed.'};
    if(result.operation_key!==setupKeys.current.get(identity)||result.operating_mode!==mode
      ||result.configuration_version!==payload.version+1)throw new Error('Unexpected configuration receipt. Reload before continuing.');
    setInventoryMode(mode);
    setConfiguration({...configuration,operating_mode:mode,configuration_version:result.configuration_version});
    setNotice('Basic WMS mode saved. Order inventory takeover remains disabled.');
    return {success:true};
  };
  const value={marketplaceInventory:items,inventoryLoaded,marketplaceOrders:[],marketplaceStoreProducts:[],connections,connectionsStatus,syncLogs:[],
    reloadInventory:()=>setInventoryRevision(value=>value+1),
    inventoryMode,entityWarehouses:warehouses.entityWarehouses,storeFulfillmentBindings:routing.storeFulfillmentBindings,interWarehouseTransfers:transfers.rows,inventoryModeSwitchAudits:configuration?.basic_setup_audit?[configuration.basic_setup_audit]:[],
    setInventoryModeWithAudit:configureMode,setInventoryModeDirect:pending,
    checkModeSwitchReadiness:()=>({canSwitch:!!configuration?.basic_wms_enabled&&inventoryMode==='unconfigured',blockingOrders:[],inTransitTransfers:[]}),
    saveEntityWarehouse:warehouses.saveEntityWarehouse,deleteEntityWarehouse:pending,saveStoreFulfillmentBinding:routing.saveStoreFulfillmentBinding,
    prepareWarehouseTransfer:transfers.prepare,createWarehouseTransfer:transfers.create,updateTransferStatus:transfers.update,
    loadInventoryHistory:readCombinedMovementHistory,
    storeActivations:[],inventoryOrderExceptions:exceptions,inventoryExceptionStatus:exceptionStatus,staffUsers:[],user:{name:document.getElementById('root')?.dataset.employeeName},currentSection:'marketplace-inventory',
    setCurrentSection:onNavigate??unavailable,saveStoreActivation:unavailable,adjustStock:unavailable,recordPhysicalCountAudit:unavailable,
    broadcastStockToStore:async()=>{unavailable();throw new Error('Stock writes are unavailable in this preview.');},
    broadcastStockToAllStores:async()=>{unavailable();return [];},broadcastStockToChannels:unavailable,
    broadcastAllStockToChannels:unavailable,linkChannelSku:unavailable,addToast:(t:any)=>setNotice(`${t.title}: ${t.message||''}`)};
  return <PreviewContext.Provider value={value}>
    {embedded ? <p role="status" className="mb-4 text-xs text-neutral-500">{notice}</p> : <aside style={{padding:12,background:'#fff3cd',color:'#332701',font:'14px sans-serif'}}>
      {zip36?`LOCAL ZIP36 BASIC WMS PREVIEW — Basic operations ${configuration?.basic_wms_enabled?'enabled':'disabled or loading'}. Initial mode setup, warehouse management and store bindings are connected. Order takeover and platform broadcasting remain disabled.`:'LOCAL INVENTORY PREVIEW — ZIP 33 list with ZIP 34 physical count review. Counts require configured balances. Stock takeover and platform pushes are unavailable.'}
      Uncounted quantities remain unknown. Basic warehouse availability excludes unallocated orders. Physical totals cover recorded balances only.
      <p role="status">{notice}</p>
    </aside>}
    {embedded ? <Inventory36 intakeProductIds={intakeProductIds}/> : <main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 p-4 md:p-8">{zip36?<Inventory36 intakeProductIds={intakeProductIds}/>:<MarketplaceInventoryPage />}</main>}
  </PreviewContext.Provider>;
}

