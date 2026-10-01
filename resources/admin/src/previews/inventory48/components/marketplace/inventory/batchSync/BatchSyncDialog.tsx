import React, {useEffect, useRef} from 'react';
import type {BatchSyncState, CheckInput} from './types';
import {ReviewRows,ResultRows} from './BatchSyncRows';
import {BatchSyncSummary,reviewSummary} from './BatchSyncSummary';
import './batchSync.css';

type Props={state:BatchSyncState;previewOnly?:boolean;onStart:()=>void|Promise<void>;onClose:()=>void;onReviewAgain:()=>void|Promise<void>;onReviewFailed:(itemIds:string[])=>void|Promise<void>;onCheck:(itemId:string,input:CheckInput)=>void|Promise<void>};
const focusable='button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])';
export function BatchSyncDialog({state,previewOnly=false,onStart,onClose,onReviewAgain,onReviewFailed,onCheck}:Props):React.ReactElement|null{
  const dialogRef=useRef<HTMLDivElement>(null),returnFocus=useRef<HTMLElement|null>(null),closeRef=useRef(onClose);
  closeRef.current=onClose;
  const open=state.kind!=='idle';
  useEffect(()=>{
    if(!open)return;
    returnFocus.current=document.activeElement instanceof HTMLElement?document.activeElement:null;
    const dialog=dialogRef.current;
    const oldOverflow=document.body.style.overflow;
    document.body.style.overflow='hidden';
    dialog?.focus();
    const onKey=(event:KeyboardEvent)=>{
      if(event.key==='Escape'){event.preventDefault();closeRef.current();return}
      if(event.key!=='Tab'||!dialog)return;
      const controls=[...dialog.querySelectorAll<HTMLElement>(focusable)];
      if(!controls.length){event.preventDefault();dialog.focus();return}
      const first=controls[0],last=controls[controls.length-1];
      if(event.shiftKey&&(document.activeElement===first||document.activeElement===dialog)){event.preventDefault();last.focus()}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    };
    document.addEventListener('keydown',onKey);
    return()=>{document.removeEventListener('keydown',onKey);document.body.style.overflow=oldOverflow;returnFocus.current?.focus()};
  },[open]);
  if(!open)return null;
  const reviewing=state.kind==='reviewing';
  const review=state.kind==='review'||state.kind==='submitting'||state.kind==='unknown'?state.review:null;
  const batch=state.kind==='batch'?state.batch:null;
  const submitted=state.kind==='submitting'||state.kind==='unknown'||!!batch;
  const step=batch?3:submitted?2:1;
  const title=state.kind==='error'?'Batch sync needs attention':batch?'Batch sync results':state.kind==='unknown'?'Checking submission':'Review batch sync';
  const valid=review?.counts.validDestinations??0;
  const retryIds=batch?.items.filter(item=>item.status==='failed'&&item.can_retry).map(item=>item.id)??[];
  const counts=batch?.counts.destinations;
  const allConfirmed=batch&&counts&&counts.confirmed>0&&counts.queued===0&&counts.confirming===0&&counts.failed===0&&counts.skipped===0;
  const needsReview=batch?.items.filter(item=>item.status==='confirming'&&item.current_attempt?.exhausted_at).length??0;
  return <div className="bs-overlay"><div className="bs-dialog" role="dialog" aria-modal="true" aria-labelledby="bs-title" ref={dialogRef} tabIndex={-1}>
    <header className="bs-header"><div><h1 id="bs-title">{title}</h1><p>{batch?'Track each marketplace destination in this batch.':'Review the selected SKUs and destination updates before syncing to the marketplace.'}</p></div>
      <nav className="bs-steps" aria-label="Batch sync steps">{['Review','Confirm sync','Results'].map((label,index)=><div className={`bs-step ${step===index+1?'bs-active':''} ${step>index+1?'bs-done':''}`} key={label}><span>{index+1}</span><small>{label}</small></div>)}</nav>
      <button type="button" className="bs-icon-close" aria-label="Close" onClick={onClose}>×</button>
    </header>
    {counts?<p className="bs-sr-only" role="status" aria-live="polite" aria-atomic="true">{`${counts.confirmed} confirmed, ${counts.queued} queued, ${counts.confirming} awaiting confirmation, ${counts.failed} failed, ${counts.skipped} skipped destinations. ${needsReview} confirmations need review.`}</p>:null}
    <div className="bs-content"><div className="bs-main">
      {previewOnly?<p className="bs-inline-notice" role="status">UI preview — batch sync is not connected</p>:null}
      {review?<ReviewRows targets={review.targets}/>:null}
      {batch?<ResultRows items={previewOnly?batch.items.map(item=>({...item,can_check:false})):batch.items} onCheck={onCheck}/>:null}
      {reviewing?<div className="bs-empty" role="status">Preparing review…</div>:null}
      {state.kind==='error'?<div className="bs-empty bs-error" role="alert"><strong>Review needs to be refreshed</strong><p>{state.message}</p><button type="button" className="bs-secondary" onClick={onReviewAgain}>Review again</button></div>:null}
      {state.kind==='unknown'?<p className="bs-inline-notice" role="status">The submission outcome is still unknown. Closing this dialog will not cancel a submitted batch. Open Sync activity to check its status.</p>:null}
      {state.kind==='submitting'?<p className="bs-inline-notice" role="status">Submitting the reviewed destinations…</p>:null}
      {batch?<p className="bs-inline-notice">Submitted work continues in the background when you close this dialog.</p>:null}
    </div>
      {review?<BatchSyncSummary {...reviewSummary(review)}/>:null}
      {batch?<BatchSyncSummary mode={batch.mode} skus={batch.counts.skus} destinationCounts={batch.counts.destinations} aggregate={batch.aggregate}/>:null}
    </div>
    <footer className="bs-footer"><button type="button" className="bs-secondary" onClick={onClose}>{submitted||previewOnly?'Close':'Cancel'}</button><span className="bs-footer-note">{previewOnly?'Preview only. No batch is created and no stock is sent.':submitted?'Submitted work continues in the background.':'Quantities and destinations will be checked again before syncing.'}</span>
      {state.kind==='review'?<button type="button" className="bs-primary" disabled={previewOnly||valid===0} onClick={onStart}>Start sync ({valid} destinations)</button>:null}
      {batch&&retryIds.length>0?<button type="button" className="bs-primary" disabled={previewOnly} onClick={()=>onReviewFailed(retryIds)}>Review failed items</button>:null}
      {allConfirmed?<span className="bs-confirmed">All updates confirmed</span>:null}
    </footer>
  </div></div>;
}
