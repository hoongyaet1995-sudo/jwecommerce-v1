import React, {useEffect, useId, useState} from 'react';
import {ChevronDown, Warehouse} from 'lucide-react';
import {MarketplaceLogo} from './MarketplaceLogo';
import {storeInventoryApi} from '../../../../integration/storeInventory';

// History contains persisted sync receipts. Never use Independent facility balances
// or a store-wide snapshot as a Shared SKU/location observation.
export function sharedDestinationReadback(item:any, store:any, facility:any, history:any[]) {
  const tasks=history.flatMap(entry=>entry.detailsPayload?.external_stages || [])
    .filter(task=>Number(task.inventory_balance_id)===Number(item.id)
      && Number(task.product_variant_id)===Number(item.variantId)
      && String(task.marketplace_store_id)===String(store.id)
      && (facility ? Number(task.marketplace_store_facility_id)===Number(facility.id)
        : task.marketplace_store_facility_id==null)
      && task.status!=='superseded')
    .sort((a,b)=>String(b.created_at).localeCompare(String(a.created_at)) || String(b.id).localeCompare(String(a.id)));
  const task=tasks[0];
  let receipt:any={};
  try {receipt=typeof task?.provider_receipt==='string'?JSON.parse(task.provider_receipt):task?.provider_receipt || {}} catch { /* Unknown receipt stays Unknown. */ }
  const exact=facility ? receipt.provider_location_id===facility.provider_id
    : task?.provider_location_id==null ? receipt.provider_location_id==null
    : receipt.provider_location_id===task.provider_location_id;
  const quantity=exact && typeof receipt.observed_quantity==='number' && Number.isFinite(receipt.observed_quantity)
    ? receipt.observed_quantity : null;
  const current=task && Number(task.balance_version)===Number(item.currentStockVersion)
    && Number(task.target_quantity)===Number(item.currentStock ?? item.totalPhysicalStock);
  const status=task?.status==='confirmed' && current && quantity!==null && quantity===(item.currentStock ?? item.totalPhysicalStock)
    ? 'Synced' : ['failed','facility_blocked','auth_expired'].includes(task?.status) ? 'Sync failed'
    : ['queued','processing','facility_pending','uncertain'].includes(task?.status) ? 'Awaiting confirmation' : 'Not synced';
  return {quantity,status,reason:typeof receipt.message==='string'?receipt.message:typeof receipt.code==='string'?receipt.code:null};
}

export function SharedPoolStoreCard({item,store,history,syncState}:{item:any;store:any;history:any[];syncState:string}) {
  const [topology,setTopology]=useState<any>(null);
  const [error,setError]=useState('');
  const [hovered,setHovered]=useState(false);
  const [focused,setFocused]=useState(false);
  const [expanded,setExpanded]=useState(false);
  const detailId=useId();
  const local=store.platform==='webstore';
  const total=item.currentStock===undefined?item.totalPhysicalStock:item.currentStock;
  const uncounted=item.physicalStockStatus==='unknown' || total===null;
  useEffect(()=>{
    let active=true;setTopology(null);setError('');setExpanded(false);
    if(!local)void storeInventoryApi.facilities(store.id).then(value=>{if(active)setTopology(value)})
      .catch(reason=>{if(active)setError(reason.message || 'Warehouse details unavailable')});
    return ()=>{active=false};
  },[store.id,local,item]);
  const verified=topology?.status==='verified';
  const facilities=verified && topology.topology!=='no_warehouse'?topology.facilities || []:[];
  const destinations=facilities.map((facility:any)=>({...facility,...sharedDestinationReadback(item,store,facility,history)}));
  const direct=sharedDestinationReadback(item,store,null,history);
  const quantity=local?total:verified && topology.topology==='no_warehouse'?direct.quantity:destinations.length===1?destinations[0].quantity:null;
  const failed=destinations.some((destination:any)=>destination.status==='Sync failed');
  const status=uncounted?'Awaiting first count':local?'JW Ledger':store.connectionState!=='connected'?'Reconnect store'
    :!store.isManagedByJw?'Takeover paused':error?'Details unavailable':!verified?'Warehouses not verified'
    :failed?'Sync failed':syncState==='synced'?'Synced':destinations.length===0?direct.status:'Not synced';
  const quantityLabel=destinations.length>1?`${destinations.length} warehouses`:quantity==null?'Unknown':`${quantity} units`;
  const open=hovered || focused || expanded;
  const positive=status==='Synced' || status==='JW Ledger';
  return <div className="rounded-xl border border-neutral-200 bg-neutral-50 dark:border-neutral-700/60 dark:bg-neutral-800/60 text-[11px]"
    onMouseEnter={()=>setHovered(true)} onMouseLeave={()=>setHovered(false)}
    onFocus={()=>setFocused(true)} onBlur={event=>{if(!event.currentTarget.contains(event.relatedTarget as Node))setFocused(false)}}>
    <div className="flex items-center justify-between gap-3 p-3">
      <div className="flex min-w-0 items-center gap-2.5">
        <MarketplaceLogo platform={store.platform} size="xs" />
        <div className="min-w-0"><strong className="block truncate text-neutral-900 dark:text-neutral-100">{store.nickname || store.name}</strong>
          <span className="block mt-1 text-neutral-500 dark:text-neutral-400">{local?'Shared ledger':'Platform stock'} <span className="text-neutral-800 dark:text-neutral-200">{quantityLabel}</span></span>
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-1.5">
        <span className={`rounded-md border px-2 py-1 text-[10px] font-semibold ${positive?'border-emerald-800/50 bg-emerald-950/30 text-emerald-400':status==='Sync failed'?'border-rose-800/50 bg-rose-950/30 text-rose-400':'border-amber-800/50 bg-amber-950/20 text-amber-500'}`}>{status}</span>
        {!local && <button type="button" aria-label={`Warehouse details for ${store.nickname || store.name} / ${item.masterSku}`} aria-expanded={open} aria-controls={detailId}
          onClick={()=>setExpanded(value=>!value)} className="rounded p-1.5 text-neutral-400 hover:text-purple-400 focus-visible:outline focus-visible:outline-purple-500"><ChevronDown className="h-3.5 w-3.5" /></button>}
      </div>
    </div>
    {!local && open && <div id={detailId} className="border-t border-neutral-200 dark:border-neutral-700/60 px-3 py-2 space-y-2">
      {error?<p role="status" className="text-rose-400">{error}</p>:!verified?<p className="text-amber-500">{topology?.error_code || 'Warehouse list not yet verified'}</p>
        :topology.topology==='no_warehouse'?<p className="text-neutral-400">Store-level stock · No separate warehouses{direct.reason && <span className="block text-rose-400">{direct.reason}</span>}</p>
        :destinations.length===0?<p className="text-amber-500">No warehouses discovered</p>
        :destinations.map((destination:any)=><div key={destination.id} className="space-y-1">
          <div className="flex items-center justify-between gap-2"><span className="flex items-center gap-1.5 text-neutral-800 dark:text-neutral-200"><Warehouse className="h-3.5 w-3.5 text-purple-400" />{destination.name}</span><strong>{destination.quantity===null?'Unknown':`${destination.quantity} units`}</strong></div>
          <div className="flex flex-wrap justify-between gap-1 text-[10px] text-neutral-500 dark:text-neutral-400"><span>{destination.shared_pool_takeover_enabled && store.isManagedByJw?'Takeover active':'Takeover paused'}</span><span>{uncounted?'Awaiting first count':destination.status} · Target {uncounted?'Unknown':`${total} units`}</span></div>
          {destination.reason && <p className="text-rose-400">{destination.reason}</p>}
        </div>)}
      <p className="text-[10px] text-neutral-500">Last saved readback. Missing observations remain Unknown.</p>
    </div>}
  </div>;
}
