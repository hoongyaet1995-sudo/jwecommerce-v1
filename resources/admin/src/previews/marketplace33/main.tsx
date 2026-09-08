import React, {useEffect, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {PreviewContext} from './context/AdminContext';
import {MarketplaceInventoryPage} from './components/marketplace/MarketplaceInventoryPage';
import './source.css';
import {readInventoryPages} from './readInventoryPages';
import {readCombinedMovementHistory} from './readMovementHistory';
import {readExceptions} from './readExceptions';
import {WarehousePreview} from '../marketplace34/WarehousePreview';
import {TransferPreview} from '../marketplace34/TransferPreview';

function Preview() {
  const [items,setItems]=useState<any[]>([]);
  const [inventoryLoaded,setInventoryLoaded]=useState(false);
  const [connections,setConnections]=useState<any[]>([]);
  const [connectionsStatus,setConnectionsStatus]=useState('Loading');
  useEffect(()=>{
    const abort=new AbortController();
    fetch('/admin/api/marketplace/connections',{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}})
      .then(async response=>{if(!response.ok)throw new Error('Store connections unavailable');return response.json();})
      .then(result=>{
        if(!Array.isArray(result.stores))throw new Error('Store connections unavailable');
        setConnections(result.stores.filter((store:any)=>['shopee','lazada'].includes(store.platform)));
        setConnectionsStatus('Loaded');
      }).catch(error=>{if(!abort.signal.aborted)setConnectionsStatus('Unavailable');});
    return ()=>abort.abort();
  },[]);
  const [notice,setNotice]=useState('Loading database inventory…');
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
          imageUrl:'',warehouseLocation:'',warehouseName:'',physicalStockStatus:'unknown',totalPhysicalStock:null,
          reservedStock:row.marketplace_reserved_stock,safetyStock:null,availableStock:null,reorderPoint:0,
          costPrice:null,sellingPrice:row.selling_price,channelMappings:{},autoSyncEnabled:false,historyLog:[],
          storeAssociations:row.channel_links.filter((l:any)=>['shopee','lazada'].includes(l.platform)).map((l:any)=>({
            connectionId:String(l.store_id),storeName:l.shop_name,platform:l.platform,externalSku:'',
            externalItemId:l.external_product_id,externalModelId:l.external_variant_id,
            connectionState:l.connection_status,syncState:'awaiting_reconciliation',
          })),
        })));
        setNotice(`Loaded ${rows.length} JW SKUs across all returned pages. This is not an atomic stock review.`);
        setInventoryLoaded(true);
      }).catch(e=>{if(e.name!=='AbortError')setNotice(e.message);});
    return ()=>abort.abort();
  },[]);
  const unavailable=()=>setNotice('Preview only: this operation has no enabled persistence or platform write adapter.');
  const value={marketplaceInventory:items,inventoryLoaded,marketplaceOrders:[],marketplaceStoreProducts:[],connections,connectionsStatus,syncLogs:[],
    loadInventoryHistory:readCombinedMovementHistory,
    storeActivations:[],inventoryOrderExceptions:exceptions,inventoryExceptionStatus:exceptionStatus,staffUsers:[],user:null,currentSection:'marketplace-inventory',
    setCurrentSection:unavailable,saveStoreActivation:unavailable,adjustStock:unavailable,recordPhysicalCountAudit:unavailable,
    broadcastStockToStore:async()=>{unavailable();throw new Error('Stock writes are unavailable in this preview.');},
    broadcastStockToAllStores:async()=>{unavailable();return [];},broadcastStockToChannels:unavailable,
    broadcastAllStockToChannels:unavailable,linkChannelSku:unavailable,addToast:(t:any)=>setNotice(`${t.title}: ${t.message||''}`)};
  return <PreviewContext.Provider value={value}>
    <aside style={{padding:12,background:'#fff3cd',color:'#332701',font:'14px sans-serif'}}>
      LOCAL INVENTORY PREVIEW — ZIP 33 list with ZIP 34 physical count review. Counts require configured balances. Stock takeover and platform pushes are unavailable.
      Physical, safety, warehouse and ATP are unknown. Reserved shows Marketplace allocations only. Ledger shows available-stock movements, not physical counts. Activation adapter is not loaded here.
      <p role="status">{notice}</p>
    </aside>
    <main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 p-4 md:p-8"><MarketplaceInventoryPage /></main>
  </PreviewContext.Provider>;
}
import {RoutingPreview} from '../marketplace34/RoutingPreview';
const previewView=new URLSearchParams(location.search).get('view');
createRoot(document.getElementById('root')!).render(previewView==='routing'?<RoutingPreview/>:previewView==='warehouses'?<WarehousePreview/>:previewView==='transfers'?<TransferPreview/>:<Preview/>);
