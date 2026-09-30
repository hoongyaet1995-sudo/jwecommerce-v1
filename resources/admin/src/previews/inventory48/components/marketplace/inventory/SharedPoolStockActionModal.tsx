import React, {useEffect, useState} from 'react';
import {Boxes, PlusCircle, Sliders, X} from 'lucide-react';
import {MarketplaceInventoryItem} from '../../../types';
import {InventoryStoreChannel} from './inventoryTypes';
import {storeInventoryApi} from '../../../../../integration/storeInventory';

type Action = 'intake' | 'audit';
type Draft = {quantity:string;reason:string;reference:string;notes:string};
type Stage = {store_id:number|null;facility_id?:number|null;status:string;target_quantity?:number|null};
const empty = (action:Action):Draft => ({quantity:'',reason:action==='intake'?'Supplier PO Delivery Verification':'Physical Cycle Count Reconciliation',reference:'',notes:''});

export function SharedPoolStockActionModal({item,initialAction,stores=[],onClose,onSaved}:{item:MarketplaceInventoryItem;initialAction:Action;stores?:InventoryStoreChannel[];onClose:()=>void;onSaved:()=>Promise<void>}) {
  const recoveryKey=`jw-shared-stock-confirmation:${item.variantId}`;
  const [recovery]=useState(()=>{
    try{const saved=JSON.parse(localStorage.getItem(recoveryKey)||'null');return saved?.review?.id&&saved?.operationKey&&['intake','audit'].includes(saved.action)?saved:null}catch{return null}
  });
  const [action,setAction]=useState<Action>(recovery?.action||initialAction);
  const [drafts,setDrafts]=useState<Record<Action,Draft>>(recovery?.drafts||{intake:empty('intake'),audit:empty('audit')});
  const [step,setStep]=useState<'form'|'review'|'complete'>(recovery?'review':'form');
  const [review,setReview]=useState<any>(recovery?.review||null);
  const [receipt,setReceipt]=useState<any>(null);
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState('');
  const [operationKey,setOperationKey]=useState<string|null>(recovery?.operationKey||null);
  const [confirmationStarted,setConfirmationStarted]=useState(Boolean(recovery));
  const identityLocked=busy||(confirmationStarted&&step==='review');
  const draft=drafts[action];
  const before=item.currentStock == null?null:Number(item.currentStock);
  const number=Number(draft.quantity);
  const valid=/^(0|[1-9]\d*)$/.test(draft.quantity)&&Number.isSafeInteger(number)&&number<=2147483647&&(action==='audit'||number>0);
  const after=valid&&before!==null?(action==='intake'?before+number:number):before;
  const delta=after===null||before===null?0:after-before;
  const title=action==='intake'?'Actual Stock Intake':'Stock Audit Adjustment';
  const setField=(field:keyof Draft,value:string)=>setDrafts(previous=>({...previous,[action]:{...previous[action],[field]:value}}));
  const choose=(next:Action)=>{if(busy||step!=='form')return;setAction(next);setStep('form');setReview(null);setError('')};

  useEffect(()=>{
    if(step!=='complete'||!receipt?.operation_id)return;
    let active=true;
    const refresh=()=>storeInventoryApi.operation(receipt.operation_id).then((result:any)=>{if(active)setReceipt(result)}).catch(()=>{});
    void refresh();const timer=window.setInterval(()=>void refresh(),5000);
    return()=>{active=false;window.clearInterval(timer)};
  },[step,receipt?.operation_id]);

  const createReview=async()=>{
    if(!valid||before===null||after===null||after>2147483647||!draft.reason.trim()||(action==='audit'&&delta===0)){
      setError(action==='audit'&&delta===0?'Verified total matches Current Stock; there is no adjustment to save.':'Enter a valid quantity and reason.');return;
    }
    setBusy(true);setError('');
    try{
      const payload:Record<string,unknown>={variant_id:item.variantId,channel_key:'website',reason:[draft.reason.trim(),draft.notes.trim()].filter(Boolean).join(': '),reference:draft.reference.trim()||undefined};
      if(action==='intake')payload.quantity=number;else payload.confirmed_total=number;
      const result=await storeInventoryApi.reviewDraft(action==='intake'?'intake':'correction',payload);
      setReview(result);setOperationKey(crypto.randomUUID());setStep('review');
    }catch(cause:any){setError(cause?.message||'Review could not be created. Refresh stock and try again.')}finally{setBusy(false)}
  };
  const confirm=async()=>{
    if(!review?.id||!operationKey||busy)return;
    try{localStorage.setItem(recoveryKey,JSON.stringify({review,operationKey,action,drafts}))}catch{setError('Keep this window open. Confirmation cannot start until its recovery identity can be saved.');return}
    setConfirmationStarted(true);setBusy(true);setError('');
    try{
      const result=await storeInventoryApi.confirmReview(review.id,operationKey);
      localStorage.removeItem(recoveryKey);
      setReceipt(result);setStep('complete');
      try{await onSaved()}catch{setError('JW stock was saved. The inventory list could not refresh; reopen it to see the latest balance.')}
    }catch(cause:any){
      if(cause?.status===409&&['balance_changed','configuration_changed','review_expired'].includes(cause.code)){
        localStorage.removeItem(recoveryKey);setConfirmationStarted(false);setError(cause.message);
      }else{setError(`${cause?.message||'Confirmation response unavailable.'} Retry this confirmation to retrieve its saved result; do not create another intake.`)}
    }finally{setBusy(false)}
  };
  const movement=review?.after?.current_stock!=null?Number(review.after.current_stock):after;
  const prior=review?.before?.current_stock!=null?Number(review.before.current_stock):before;
  const stages:Stage[]=(receipt?.external_stages||[]).filter((stage:Stage)=>stage.store_id!==null);
  return <div className="fixed inset-0 z-[60] flex items-center justify-center bg-neutral-950/80 p-3 text-neutral-100 backdrop-blur-sm">
    <div role="dialog" aria-modal="true" aria-label={title} className="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-neutral-700 bg-neutral-900 shadow-2xl">
      <header className="flex items-center justify-between gap-3 border-b border-neutral-800 px-6 py-4">
        <div className="flex items-center gap-3"><div className="rounded-xl bg-neutral-800 p-2 text-emerald-400">{action==='intake'?<PlusCircle className="h-5 w-5"/>:<Sliders className="h-5 w-5"/>}</div><div><h2 className="text-base font-bold">{step==='review'?`Review ${title}`:step==='complete'?`${title} · Execution Pipeline`:title} <span className="rounded border border-purple-800 bg-purple-950/60 px-2 py-0.5 text-[10px] text-purple-300">Shared Pool</span></h2><p className="mt-1 text-xs text-neutral-400">{action==='intake'?'Add received units to this SKU’s one JW Current Stock balance.':'Set this SKU’s verified absolute Current Stock.'}</p></div></div>
        <button type="button" disabled={identityLocked} onClick={onClose} aria-label="Close" className="text-neutral-400 hover:text-white"><X className="h-5 w-5"/></button>
      </header>
      <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-6">
        <div className="flex gap-1 rounded-xl border border-neutral-700 bg-neutral-800 p-1 text-xs font-bold"><button type="button" onClick={()=>choose('intake')} className={`flex-1 rounded-lg px-2 py-2 text-center ${action==='intake'?'bg-neutral-950 text-white':'text-neutral-500'}`}>Actual Inbound Intake (+Qty)</button><button type="button" onClick={()=>choose('audit')} className={`flex-1 rounded-lg px-2 py-2 text-center ${action==='audit'?'bg-neutral-950 text-white':'text-neutral-500'}`}>Audit Count Adjustment</button></div>
        <div className="flex items-center justify-between gap-3 rounded-xl border border-neutral-700 bg-neutral-800/70 p-3"><div className="flex min-w-0 items-center gap-3"><div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-neutral-700">{item.imageUrl?<img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover"/>:<Boxes className="h-5 w-5 text-neutral-400"/>}</div><div className="min-w-0"><b className="block truncate font-mono text-xs">{item.masterSku}</b><span className="block truncate text-xs text-neutral-400">{item.productName}</span></div></div><div className="shrink-0 text-right text-xs"><span className="block text-neutral-400">SKU Total Current Stock</span><b className="text-emerald-400">{before?.toLocaleString()??'Not counted'} units</b></div></div>
        {step==='form'&&<>
          <div className="rounded-xl border border-neutral-700 bg-neutral-800/60 p-3 text-xs"><b>Target JW Stock Pool</b><span className="float-right rounded bg-purple-950 px-2 py-0.5 text-purple-300">One SKU ledger</span><p className="mt-3 font-semibold">Shared Pool · JW Current Stock</p><p className="mt-1 text-neutral-400">Marketplaces are outbound sync destinations; no store or warehouse is selected for this movement.</p></div>
          <label className="block text-xs font-bold">{action==='intake'?'Arrived Inbound Quantity (+ Units) *':'New Confirmed Current Stock (Total Units) *'}<input type="number" min={action==='intake'?1:0} max={2147483647} step={1} inputMode="numeric" value={draft.quantity} onChange={event=>setField('quantity',event.target.value)} placeholder={action==='intake'?'e.g. 10':'e.g. 47 (0 allowed)'} className="mt-2 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-white"/></label>
          <div className="grid grid-cols-3 gap-2 rounded-xl border border-neutral-700 bg-neutral-800/60 p-3 text-center text-xs"><div><span className="block text-neutral-400">Previous Current Stock</span><b>{before??'—'}</b></div><div><span className="block text-neutral-400">{action==='intake'?'Added Quantity':'Audit Variance'}</span><b className={delta<0?'text-rose-400':'text-emerald-400'}>{delta>=0?'+':''}{delta}</b></div><div><span className="block text-neutral-400">New Current Stock</span><b className="text-emerald-400">{after??'—'}</b></div></div>
          <label className="block text-xs font-bold">Reason *<input value={draft.reason} maxLength={180} onChange={event=>setField('reason',event.target.value)} className="mt-2 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm"/></label>
          <label className="block text-xs font-bold">{action==='intake'?'PO / Receiving Reference':'Count / Audit Reference'}<input value={draft.reference} maxLength={80} onChange={event=>setField('reference',event.target.value)} className="mt-2 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm"/></label>
          <label className="block text-xs font-bold">Notes (Optional)<input value={draft.notes} maxLength={60} onChange={event=>setField('notes',event.target.value)} className="mt-2 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm"/></label>
        </>}
        {step==='review'&&<><div className="space-y-2 rounded-xl border border-purple-800 bg-purple-950/20 p-4 text-xs"><b>Pre-Submission Audit Confirmation Summary</b><p>Target: Shared JW Current Stock</p><p>Stock movement: {prior} → {movement} ({Number(movement)-Number(prior)>=0?'+':''}{Number(movement)-Number(prior)})</p><p>Reason: {draft.reason}</p>{draft.reference&&<p>Reference: {draft.reference}</p>}</div><p className="text-xs text-amber-200">Confirmation saves one JW ledger movement, then queues stock sync through the common takeover-gated path. Provider confirmation is tracked separately.</p></>}
        {step==='complete'&&<><div className="rounded-xl border border-emerald-800 bg-emerald-950/30 p-4 text-xs"><b>Step 1: JW Inventory Ledger · Done</b><p className="mt-1">Current Stock: {prior} → {movement}</p></div><div className="rounded-xl border border-neutral-700 bg-neutral-800 p-4 text-xs"><b>Step 2: Marketplace sync destinations</b>{stages.length?stages.map((stage,index)=><div key={index} className="mt-2 flex justify-between gap-3"><span>{stores.find(store=>store.id===String(stage.store_id))?.name||`Store ${stage.store_id}`}{stage.facility_id?` · warehouse ${stage.facility_id}`:''}</span><b>{stage.status}</b></div>):<p className="mt-2 text-neutral-400">No eligible marketplace destination was queued.</p>}</div></>}
        {error&&<p role="alert" className="text-xs text-rose-300">{error}</p>}
      </div>
      <footer className="flex justify-between gap-3 border-t border-neutral-800 bg-neutral-950/70 px-6 py-4 text-xs"><button type="button" disabled={identityLocked} onClick={step==='review'?()=>{setStep('form');setReview(null);setOperationKey(null);setError('')}:onClose} className="rounded-lg border border-neutral-700 px-4 py-2">{step==='review'?'Edit':'Close'}</button>{step==='form'?<button type="button" disabled={busy||before===null} onClick={()=>void createReview()} className="rounded-lg bg-purple-600 px-4 py-2 font-bold disabled:opacity-40">{busy?'Reviewing…':action==='intake'?'Review Intake':'Review Adjustment'}</button>:step==='review'?<button type="button" disabled={busy} onClick={()=>void confirm()} className="rounded-lg bg-purple-600 px-4 py-2 font-bold disabled:opacity-40">{busy?'Saving…':`Confirm ${title}`}</button>:null}</footer>
    </div>
  </div>;
}
