import React,{useState} from 'react';

export function OrderActivationControl({balanceId,sku,channels}:{balanceId:number;sku:string;channels:{id:string;name:string}[]}) {
 const [channel,setChannel]=useState('');const [reason,setReason]=useState('');
 const [review,setReview]=useState(false);const [busy,setBusy]=useState(false);const [message,setMessage]=useState('');
 const input='w-full rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 p-2 text-xs';
 const button='px-2 py-1 rounded bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 text-[10px] font-bold cursor-pointer transition-colors';
 async function submit(){
  if(!review||busy)return;setBusy(true);setMessage('');
  try {
   const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
   const response=await fetch('/admin/api/inventory/order-activations',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({channel_key:channel,balance_id:balanceId,reason:reason.trim()})});
   const result=await response.json();if(!response.ok)throw new Error(result.message??'Activation failed.');
   if(result.data?.channel_key!==channel||Number(result.data?.inventory_balance_id)!==balanceId)throw new Error('Activation receipt mismatch.');
   setMessage(`Enabled for orders created after ${result.data.orders_created_after}.`);setReview(false);
  }catch(error){setMessage((error as Error).message);}finally{setBusy(false);}
 }
 return <details className="text-left text-xs text-neutral-700 dark:text-neutral-300">
  <summary className={button}>Enable order inventory</summary>
  <div className="space-y-2 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
   <p>{sku}: only orders created after activation use this inventory. Paid orders reserve; outbound deducts physical stock; cancellation releases reservations. Older orders remain excluded. Platform writes require the separate test switch.</p>
   <select aria-label="Order channel" className={input} disabled={review||busy} value={channel} onChange={e=>setChannel(e.target.value)}><option value="" disabled>Select channel</option>{channels.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select>
   <input aria-label="Activation reason" className={input} disabled={review||busy} value={reason} maxLength={2000} onChange={e=>setReason(e.target.value)} placeholder="Activation reason"/>
   {review?<><p>Confirm {channels.find(c=>c.id===channel)?.name} uses {sku} from this inventory balance. The server records your identity and cutoff time.</p><button type="button" className={button} disabled={busy} onClick={()=>setReview(false)}>Back</button><button type="button" className={button} disabled={busy} onClick={submit}>{busy?'Saving…':'Confirm activation'}</button></>:<button type="button" className={button} disabled={!channel||!reason.trim()||busy} onClick={()=>setReview(true)}>Review activation</button>}
   {message&&<p role="status">{message}</p>}
  </div>
 </details>;
}
