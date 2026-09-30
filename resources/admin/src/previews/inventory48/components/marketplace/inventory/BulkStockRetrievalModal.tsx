import React, { useEffect, useState } from 'react';
import { storeInventoryApi } from '../../../../../integration/storeInventory';

export type StockRetrievalRun = {
  id: string; status: string; selected_skus: number; completed_skus: number; remaining_skus: number;
  total_locations: number; completed_locations: number; failed_locations: number; skipped_locations?: number;
  last_error_code: string | null; heartbeat_at: string | null; updated_at:string; can_resume:boolean;
  items: Array<{id:number;sku:string;store_name:string|null;facility_name:string|null;
    status:string;action:string|null;observed_quantity:number|null;previous_quantity:number|null;error_code:string|null}>;
};

export function BulkStockRetrievalModal({run:initialRun,onClose,onFinished}:{
  run:StockRetrievalRun;onClose:()=>void;onFinished:()=>void;
}) {
  const [run,setRun]=useState(initialRun);
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState('');
  const active=run.status==='queued'||run.status==='running';
  useEffect(()=>{
    if(!active)return;
    const timer=window.setInterval(async()=>{
      try {
        const next=await storeInventoryApi.stockRetrieval(run.id) as StockRetrievalRun;
        setRun(next);
        if(next.status==='succeeded'||next.status==='completed_with_errors')onFinished();
      }catch(e){setError(e instanceof Error?e.message:'Unable to load task progress.');}
    },2500);
    return()=>window.clearInterval(timer);
  },[run.id,active,onFinished]);
  const percent=run.total_locations?Math.round(100*run.completed_locations/run.total_locations):0;
  const resume=async()=>{
    setBusy(true);setError('');
    try{setRun(await storeInventoryApi.resumeStockRetrieval(run.id) as StockRetrievalRun);}
    catch(e){setError(e instanceof Error?e.message:'Unable to resume.');}
    finally{setBusy(false);}
  };
  return <div className="fixed inset-0 z-[100] bg-black/75 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Bulk Retrieve Stock from Marketplace">
    <div className="w-full max-w-2xl max-h-[88vh] flex flex-col rounded-2xl border border-neutral-700 bg-neutral-900 text-white shadow-2xl">
      <div className="p-5 border-b border-neutral-700 flex items-start justify-between gap-4">
        <div><h2 className="text-lg font-bold">Bulk Retrieve Stock from Marketplace</h2>
          <p className="text-xs text-neutral-400 mt-1">Server worker task · {run.status.replaceAll('_',' ')} · You may close this window.</p></div>
        <button type="button" onClick={onClose} className="text-neutral-400 hover:text-white" aria-label="Close progress">✕</button>
      </div>
      <div className="p-5 overflow-y-auto space-y-4">
        <div className="p-4 rounded-xl border border-neutral-700 bg-neutral-800">
          <div className="flex justify-between gap-3 text-sm font-semibold"><span>{run.completed_skus} / {run.selected_skus} SKUs completed</span><span>{run.remaining_skus} SKU(s) remaining</span></div>
          <div className="mt-3 h-2.5 rounded-full bg-neutral-700 overflow-hidden"><div className="h-full bg-violet-600" style={{width:`${percent}%`}} /></div>
          <p className="mt-2 text-xs text-neutral-400">{run.completed_locations} / {run.total_locations} locations processed · {run.skipped_locations||0} not applicable · {run.failed_locations} need review</p>
        </div>
        {error&&<p className="rounded-lg border border-red-700 p-3 text-sm text-red-300">{error}</p>}
        {run.last_error_code&&<p className="rounded-lg border border-amber-700 p-3 text-sm text-amber-300">Latest issue: {run.last_error_code}</p>}
        <div className="rounded-xl border border-neutral-700 divide-y divide-neutral-700">
          {run.items.map(item=><div key={item.id} className="p-3 flex justify-between gap-3 text-xs">
            <div><strong>{item.sku}</strong><div className="text-neutral-400 mt-1">{item.store_name||'Unmapped store'} · {item.facility_name||'Store stock'}</div></div>
            <div className="text-right"><span className={item.status==='failed'?'text-amber-300':item.status==='succeeded'?'text-emerald-300':'text-violet-300'}>{item.action?.replaceAll('_',' ')||item.status}</span>
              {item.status==='skipped'&&<div className="text-neutral-400 mt-1">SKU is not stocked at this warehouse</div>}
              {item.observed_quantity!==null&&<div className="text-neutral-400 mt-1">Current Stock {item.observed_quantity}</div>}
              {item.error_code&&<div className="text-amber-300 mt-1">{item.error_code}</div>}</div>
          </div>)}
        </div>
      </div>
      <div className="p-4 border-t border-neutral-700 flex justify-between gap-3">
        <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-neutral-600 text-xs font-bold">Continue working</button>
        {run.can_resume&&<button type="button" disabled={busy} onClick={resume} className="px-4 py-2 rounded-lg bg-violet-600 text-xs font-bold disabled:opacity-50">Resume retrieval</button>}
      </div>
    </div>
  </div>;
}
