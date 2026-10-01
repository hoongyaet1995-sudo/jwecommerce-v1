import React, {useEffect, useId, useState} from 'react';
import {ChevronDown, Warehouse} from 'lucide-react';
import {MarketplaceLogo} from './MarketplaceLogo';
import {storeInventoryApi} from '../../../../integration/storeInventory';

// A single overview task snapshot owns quantity, status and the current error.
export function sharedDestinationReadback(item:any, store:any, facility:any, _history:any[]=[]) {
  const snapshot=(item.sharedStoreConfirmations?.[String(store.id)] || []).find((entry:any)=>
    facility ? Number(entry.facility_id)===Number(facility.id) && entry.provider_location_id===facility.provider_id : entry.facility_id==null);
  const current=snapshot && Number(snapshot.balance_version)===Number(item.currentStockVersion)
    && snapshot.target_quantity!==null && Number(snapshot.target_quantity)===Number(item.currentStock ?? item.totalPhysicalStock);
  return current ? {...snapshot,reason:snapshot.status==='Synced'?null:snapshot.error}
    : {quantity:null,status:snapshot?'Stock changed':'Not synced',reason:null,can_check:false};
}

export function SharedPoolStoreCard({item,store,history,syncState,onChecked}:{item:any;store:any;history:any[];syncState:string;onChecked?:()=>Promise<void>|void}) {
  const [topology,setTopology]=useState<any>(null);
  const [error,setError]=useState('');
  const [checked,setChecked]=useState<Record<string,any>>({});
  const [checking,setChecking]=useState('');
  const [checkError,setCheckError]=useState('');
  const checkKeys=React.useRef<Record<string,string>>({});
  const busy=React.useRef(false);
  const identity=React.useRef('');
  identity.current=`${item.id}:${item.currentStockVersion}`;
  const [expanded,setExpanded]=useState(false);
  const detailId=useId();
  const local=store.platform==='webstore';
  const total=item.currentStock===undefined?item.totalPhysicalStock:item.currentStock;
  const uncounted=item.physicalStockStatus==='unknown' || total===null;
  useEffect(()=>{
    let active=true;setTopology(null);setError('');
    if(!local)void storeInventoryApi.facilities(store.id).then(value=>{if(active)setTopology(value)})
      .catch(reason=>{if(active)setError(reason.message || 'Warehouse details unavailable')});
    return ()=>{active=false};
  },[store.id,local,item]);
  const verified=topology?.status==='verified';
  const facilities=verified && topology.topology!=='no_warehouse'?topology.facilities || []:[];
  const readback=(facility:any)=>{
    const saved=sharedDestinationReadback(item,store,facility,history);
    const latest=checked[saved.task_id];
    return latest && latest.balance_version===saved.balance_version && latest.check_count>=saved.check_count
      ? {...latest,reason:latest.status==='Synced'?null:latest.error} : saved;
  };
  const destinations=facilities.map((facility:any)=>({...facility,...readback(facility)}));
  const direct=readback(null);
  const check=async(snapshot:any)=>{
    if(busy.current || !snapshot.can_check)return;
    busy.current=true;setChecking(snapshot.task_id);setCheckError('');const started=identity.current;
    const key=checkKeys.current[snapshot.task_id] ||= crypto.randomUUID();
    try {
      const result=await storeInventoryApi.checkConfirmation(snapshot.operation_id,snapshot.task_id,{
        operation_key:key,store_id:snapshot.store_id,variant_id:snapshot.variant_id,balance_version:snapshot.balance_version,
        facility_id:snapshot.facility_id,provider_location_id:snapshot.provider_location_id,provider_shop_id:snapshot.provider_shop_id});
      if(identity.current!==started)return;
      setChecked(previous=>({...previous,[snapshot.task_id]:result.confirmation}));delete checkKeys.current[snapshot.task_id];
      await onChecked?.();
    }catch(reason:any){if(identity.current===started)setCheckError(reason.message || 'Confirmation could not be checked.');}
    finally{busy.current=false;setChecking('');}
  };
  const confirmationDetails=(snapshot:any)=><div className="mt-1 space-y-1 text-[10px] text-neutral-400">
    <p>Last checked: {snapshot.last_checked_at || 'Not checked'} · Checks: {snapshot.check_count || 0}</p>
    {snapshot.can_check && <button type="button" disabled={!!checking} onClick={()=>void check(snapshot)}
      className="rounded border border-purple-700 px-2 py-1 text-purple-300 focus-visible:outline focus-visible:outline-purple-500 disabled:opacity-50">{checking===snapshot.task_id?'Checking…':'Check confirmation'}</button>}
  </div>;

  const quantity=local?total:verified && topology.topology==='no_warehouse'?direct.quantity:destinations.length===1?destinations[0].quantity:null;
  const failed=destinations.some((destination:any)=>destination.status==='Sync failed');
  const status=uncounted?'Awaiting first count':local?'JW Ledger':store.connectionState!=='connected'?'Reconnect store'
    :!store.isManagedByJw?'Takeover paused':error?'Details unavailable':!verified?'Warehouses not verified'
    :failed?'Sync failed':destinations.length===0?direct.status:destinations.every((d:any)=>d.status==='Synced')?'Synced'
    :destinations.some((d:any)=>d.status==='Confirming stock')?'Confirming stock'
    :destinations.some((d:any)=>d.status==='Confirmation needs review')?'Confirmation needs review':'Not synced';
  const quantityLabel=destinations.length>1?`${destinations.length} warehouses`:quantity==null?'Unknown':`${quantity} units`;
  const open=expanded;
  const positive=status==='Synced' || status==='JW Ledger';
  return <div className="rounded-xl border border-neutral-200 bg-neutral-50 dark:border-neutral-700/60 dark:bg-neutral-800/60 text-[11px]">
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
        :topology.topology==='no_warehouse'?<div><p className="text-neutral-400">Store-level stock · No separate warehouses{direct.reason && <span className="block text-rose-400">{direct.reason}</span>}</p>{confirmationDetails(direct)}</div>
        :destinations.length===0?<p className="text-amber-500">No warehouses discovered</p>
        :destinations.map((destination:any)=><div key={destination.id} className="space-y-1">
          <div className="flex items-center justify-between gap-2"><span className="flex items-center gap-1.5 text-neutral-800 dark:text-neutral-200"><Warehouse className="h-3.5 w-3.5 text-purple-400" />{destination.name}</span><strong>{destination.quantity===null?'Unknown':`${destination.quantity} units`}</strong></div>
          <div className="flex flex-wrap justify-between gap-1 text-[10px] text-neutral-500 dark:text-neutral-400"><span>{destination.shared_pool_takeover_enabled && store.isManagedByJw?'Takeover active':'Takeover paused'}</span><span>{uncounted?'Awaiting first count':destination.status} · Target {uncounted?'Unknown':`${total} units`}</span></div>
          {destination.reason && <p className="text-rose-400">{destination.reason}</p>}{confirmationDetails(destination)}
        </div>)}
      {checkError && <p role="alert" className="text-rose-400">{checkError}</p>}
      <p className="text-[10px] text-neutral-500">Last saved readback. Missing observations remain Unknown.</p>
    </div>}
  </div>;
}
