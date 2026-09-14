import React,{useState} from 'react';
import {marketplaceRequest} from '../../../../integration/marketplaceData';
type Review={token:string;sku:string;fromTitle:string;fromId:number;toTitle:string;toId:number;toStatus:string};
export function OwnershipTransferReview({listingId,productId}:{listingId:string;productId:string}){
 const [review,setReview]=useState<Review|null>(null),[reason,setReason]=useState(''),[error,setError]=useState(''),[busy,setBusy]=useState(false);
 const run=async(confirm:boolean)=>{setBusy(true);setError('');try{
  const result=await marketplaceRequest<Review>(`/listings/${listingId}/import-variant`,'POST',confirm?{productId:Number(productId),transferToken:review!.token,reason}:{productId:Number(productId),reviewTransfer:true});
  if(confirm) window.location.reload();else setReview(result);
 }catch(e){setError(e instanceof Error?e.message:'Unable to review transfer.');setReview(null);}finally{setBusy(false);}};
 return <div className="p-3 rounded-lg border border-amber-500/30 space-y-3 text-xs text-neutral-700 dark:text-neutral-300">
  <p>This variant may already belong to another JW product. Review its saved ownership before proceeding.</p>
  {review?<><p><strong>{review.sku}</strong>: {review.fromTitle} #{review.fromId} → {review.toTitle} #{review.toId}</p><p>The same variant will move and link to this listing. Its SKU, images and measurements remain unchanged. The old product is retained.</p>{review.toStatus==='active'&&<p>The destination is published. This variant will become part of that storefront product after confirmation.</p>}<input aria-label="Ownership transfer reason" value={reason} onChange={e=>setReason(e.target.value)} placeholder="Reason for moving this variant" className="w-full p-2 rounded bg-white dark:bg-neutral-800"/><button type="button" disabled={busy||reason.trim().length<3} onClick={()=>run(true)} className="px-3 py-2 rounded bg-amber-600 text-white disabled:opacity-50">Confirm Move &amp; Link</button></>:<button type="button" disabled={busy} onClick={()=>run(false)} className="px-3 py-2 rounded bg-amber-600 text-white disabled:opacity-50">Review Move to Selected Product</button>}
  {error&&<p role="alert" className="text-red-500">{error}</p>}
 </div>;
}
