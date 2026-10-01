import React from 'react';
import type {BatchAggregate, DestinationCounts, Review} from './types';

type Props={mode:'shared_pool'|'independent_stores';skus:number;destinationCounts:DestinationCounts;eligibleSkus?:number;skippedSkus?:number;aggregate?:BatchAggregate};
export function BatchSyncSummary({mode,skus,destinationCounts,eligibleSkus,skippedSkus,aggregate}:Props){
  const inReview=eligibleSkus!==undefined;
  return <aside className="bs-summary" aria-label="Batch summary">
    <h2>Batch summary</h2>
    <div className="bs-summary-line"><strong>{skus}</strong><span>SKUs selected</span></div>
    {inReview?<>
      <div className="bs-summary-line"><strong>{eligibleSkus}</strong><span>Eligible SKUs</span></div>
      <div className="bs-summary-line"><strong>{skippedSkus}</strong><span>Skipped SKUs</span></div>
      <div className="bs-summary-line"><strong>{destinationCounts.queued}</strong><span>Destination updates ready</span></div>
      <div className="bs-summary-line"><strong>{destinationCounts.skipped}</strong><span>Skipped destinations</span></div>
    </>:<>
      <div className="bs-summary-line"><strong>{destinationCounts.confirmed}</strong><span>Confirmed destinations</span></div>
      <div className="bs-summary-line"><strong>{destinationCounts.queued+destinationCounts.confirming}</strong><span>Pending destinations</span></div>
      <div className="bs-summary-line"><strong>{destinationCounts.failed}</strong><span>Failed destinations</span></div>
      <div className="bs-summary-line"><strong>{destinationCounts.skipped}</strong><span>Skipped destinations</span></div>
      {aggregate?<p className="bs-aggregate">{aggregate==='running'?'Sync in progress':aggregate==='needs_attention'?'Needs attention':aggregate==='completed_with_issues'?'Completed with issues':'Completed'}</p>:null}
    </>}
    <div className="bs-mode-note"><span aria-hidden="true">ⓘ</span><div><strong>{mode==='shared_pool'?'Shared stock mode':'Independent stock mode'}</strong><p>{mode==='shared_pool'?'Each enabled warehouse receives the same shared quantity.':'Each destination uses its own reviewed stock quantity.'}</p></div></div>
    <p className="bs-stock-note"><span aria-hidden="true">ⓘ</span>Your JW stock quantities will not change.</p>
  </aside>;
}

export function reviewSummary(review:Review){
  const eligible=new Set(review.targets.filter(target=>target.disposition==='valid').map(target=>target.variantId));
  const all=new Set(review.targets.map(target=>target.variantId));
  return {mode:review.mode,skus:review.counts.skus,eligibleSkus:eligible.size,skippedSkus:Math.max(0,all.size-eligible.size),
    destinationCounts:{queued:review.counts.validDestinations,confirming:0,confirmed:0,failed:0,skipped:review.counts.skippedRows}};
}
