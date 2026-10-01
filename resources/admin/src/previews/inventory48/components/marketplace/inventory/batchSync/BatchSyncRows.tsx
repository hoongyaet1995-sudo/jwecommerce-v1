import React from 'react';
import {Package} from 'lucide-react';
import type {BatchItem, CheckInput, ReviewTarget} from './types';

const reason=(code:string|null)=>{
  if(!code)return 'This destination is unavailable for this review.';
  if(code.includes('first_count')||code.includes('uncounted'))return 'Awaiting first count';
  if(code.includes('pause'))return 'Store or warehouse is paused';
  if(code.includes('map'))return 'Mapping needs attention';
  if(code.includes('overlap'))return 'Already in progress';
  return code.replaceAll('_',' ');
};
const awaitingCount=(code:string|null)=>!!code&&(code.includes('first_count')||code.includes('uncounted'));
const quantityLabel=(quantity:number|null,code:string|null)=>quantity??(awaitingCount(code)?'Awaiting first count':'Unknown');
const groupByIdentity=<T,>(rows:T[],identity:(row:T)=>string)=>{
  const groups=new Map<string,T[]>();
  for(const row of rows){const key=identity(row);groups.set(key,[...(groups.get(key)??[]),row])}
  return [...groups.values()];
};
const Media=({image,name}:{image:string;name:string})=>image?<img src={image} alt="" className="bs-media"/>:<span className="bs-media bs-media-fallback" aria-hidden="true"><Package size={26} strokeWidth={1.5}/></span>;

export function ReviewRows({targets}:{targets:ReviewTarget[]}){
  if(!targets.length)return <div className="bs-empty">No destinations are available in this review.</div>;
  return <div className="bs-row-list">{groupByIdentity(targets,row=>String(row.variantId)).map(group=>{
    const first=group[0],valid=group.filter(row=>row.disposition==='valid');
    const skipped=valid.length===0;
    const partial=valid.length>0&&valid.length<group.length;
    const quantities=[...new Set(group.map(row=>row.quantity))];
    const shared=first.mode==='shared_pool';
    return <section className={`bs-sku-card ${skipped?'bs-skipped':''}`} key={`${first.variantId}-${first.display.sku}`}>
      <div className="bs-sku-head">
        <span className={`bs-marker ${skipped?'bs-marker-off':''}`} aria-label={skipped?'Skipped SKU':'Included SKU'}>{skipped?'':'✓'}</span>
        <Media image={first.display.image} name={first.display.name}/>
        <div className="bs-product"><strong title={first.display.name}>{first.display.name}</strong><span title={first.display.sku}>{first.display.sku}</span></div>
        <div className="bs-current"><small>{shared?'Current quantity in JW stock':'Destination quantity'}</small><strong className={quantities.length===1&&quantities[0]===null?'bs-unknown-quantity':undefined}>{quantities.length===1&&quantities[0]===null?(group.every(row=>awaitingCount(row.reasonCode))?'Awaiting first count':'Unknown'):shared&&quantities.length===1?quantities[0]:'Per destination'}</strong></div>
        <div className="bs-eligibility"><span className={`bs-pill ${skipped||partial?'bs-pill-amber':'bs-pill-green'}`}>{skipped?'⚠ Skipped':partial?'Partially eligible':'✓ Eligible'}</span><small>{skipped?reason(first.reasonCode):partial?`${valid.length} ready · ${group.length-valid.length} skipped destinations`:'Store and warehouse settings verified.'}</small></div>
      </div>
      {group.length>0&&<div className="bs-destinations"><div className="bs-destination-title">Destination updates ({valid.length})</div>{group.map(row=><div className={`bs-destination ${row.disposition==='skipped'?'bs-destination-skipped':''}`} key={row.rowKey}>
        <span className="bs-channel" aria-hidden="true">{row.platform==='shopee'?'S':row.platform==='lazada'?'L':'M'}</span>
        <span className="bs-destination-name" title={`${row.display.store} · ${row.display.facility}`}>{row.display.store} · {row.display.facility}</span>
        <strong data-target-quantity={row.quantity??undefined}>{quantityLabel(row.quantity,row.reasonCode)}</strong>
        {row.disposition==='skipped'?<small className="bs-destination-reason">Skipped: {reason(row.reasonCode)}</small>:null}
      </div>)}</div>}
    </section>;
  })}</div>;
}

const statusLabel:Record<BatchItem['status'],string>={queued:'Queued',confirming:'Awaiting confirmation',confirmed:'Confirmed',failed:'Failed',skipped:'Skipped'};
const resultIdentity=(item:BatchItem)=>'variant_id' in item&&typeof item.variant_id==='number'&&Number.isSafeInteger(item.variant_id)&&item.variant_id>0?`variant:${item.variant_id}`:`item:${item.id}`;
export function ResultRows({items,onCheck}:{items:BatchItem[];onCheck:(itemId:string,input:CheckInput)=>void}){
  if(!items.length)return <div className="bs-empty">No destination results are available yet.</div>;
  return <div className="bs-row-list">{groupByIdentity(items,resultIdentity).map(group=>{
    const first=group[0];
    return <section className="bs-sku-card" key={`${first.row_key}-${first.display.sku}`}>
      <div className="bs-sku-head"><span className="bs-marker bs-marker-neutral" aria-hidden="true">•</span><Media image={first.display.image} name={first.display.name}/><div className="bs-product"><strong title={first.display.name}>{first.display.name}</strong><span title={first.display.sku}>{first.display.sku}</span></div></div>
      <div className="bs-destinations"><div className="bs-destination-title">Destination results ({group.length})</div>{group.map(item=>{
        const attempt=item.current_attempt;
        const checkable=(item.status==='confirming'||item.status==='failed')&&item.can_check&&!!attempt?.task_id&&!!attempt?.snapshot_token;
        return <div className="bs-destination bs-result" key={item.id}>
          <span className="bs-channel" aria-hidden="true">↗</span><span className="bs-destination-name" title={`${item.display.store} · ${item.display.facility}`}>{item.display.store} · {item.display.facility}</span>
          <span className={`bs-result-status bs-status-${item.status}`}>{item.status==='confirming'&&attempt?.exhausted_at?'Confirming - Needs review':statusLabel[item.status]}</span>
          <strong>{attempt?.target_quantity??'Unknown'}</strong>
          {checkable?<button type="button" className="bs-link" onClick={()=>onCheck(item.id,{task_id:attempt!.task_id!,snapshot_token:attempt!.snapshot_token!,operation_key:crypto.randomUUID()})}>Check confirmation</button>:null}
          {item.message?<small className="bs-result-message">{item.message}</small>:null}
          {attempt&&(attempt.check_count>0||attempt.checked_at||attempt.exhausted_at||attempt.observed_quantity!==null)?<small className="bs-result-message">Observed quantity: {attempt.observed_quantity??'Unknown'} · Checks: {attempt.check_count}{attempt.checked_at?<> · Last checked: <time dateTime={attempt.checked_at}>{attempt.checked_at}</time></>:null}</small>:null}
        </div>;
      })}</div>
    </section>;
  })}</div>;
}
