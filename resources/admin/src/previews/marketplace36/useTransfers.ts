import {useEffect,useState,useRef} from 'react';
import {pages} from '../marketplace34/RoutingPreview';

export function useTransfers(enabled:boolean,onError:(message:string)=>void) {
 const [rows,setRows]=useState<any[]>([]);
 const [revision,setRevision]=useState(0);
 const keys=useRef(new Map<string,string>());
 const reviewedOptions=useRef<any[]>([]);
 useEffect(()=>{
  if(!enabled)return;
  const abort=new AbortController();
  pages('/admin/api/inventory/transfers',abort.signal).then(transfers=>{
    if(abort.signal.aborted)return;
    setRows(transfers.map(row=>({...row,id:String(row.id),masterSku:row.sku,productName:row.product_name,
      variantName:row.variant_name,sourceWarehouseId:String(row.source_warehouse_id),sourceWarehouseName:row.source_warehouse_name,
      destinationWarehouseId:String(row.destination_warehouse_id),destinationWarehouseName:row.destination_warehouse_name,
      requestedBy:`Employee #${row.actor_id}`,createdAt:row.created_at,dispatchedAt:row.dispatched_at,completedAt:row.completed_at})));
  }).catch(error=>{if(!abort.signal.aborted)onError(error.message);});
  return()=>abort.abort();
 },[enabled,revision]);
 const prepare=async()=>{
  const options=await pages('/admin/api/inventory/transfer-options',new AbortController().signal,'balance_id');
  if(!options.length)throw new Error('Configure active warehouse balances before creating a transfer.');
  reviewedOptions.current=options;
  return options;
 };
 return {rows,prepare,create:async(data:any)=>{
  const source=reviewedOptions.current.find(row=>row.sku===data.masterSku&&String(row.warehouse_id)===data.sourceWarehouseId);
  const destination=reviewedOptions.current.find(row=>row.sku===data.masterSku&&String(row.warehouse_id)===data.destinationWarehouseId);
  if(!source||!destination||source.variant_id!==destination.variant_id||source.balance_id===destination.balance_id)
    return {success:false,error:'Select this SKU in two configured warehouses.'};
  const payload={source_balance_id:source.balance_id,destination_balance_id:destination.balance_id,quantity:data.quantity,
    notes:[data.reason,data.trackingNumber?`Tracking: ${data.trackingNumber}`:''].filter(Boolean).join('\n')};
  const identity=JSON.stringify(payload);
  if(!keys.current.has(identity))keys.current.set(identity,crypto.randomUUID());
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
  if(!csrf)return {success:false,error:'Authenticated session required.'};
  try {
   const response=await fetch('/admin/api/inventory/transfers',{method:'POST',credentials:'same-origin',
    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
    body:JSON.stringify({...payload,operation_key:keys.current.get(identity)})});
   const result=await response.json();
   if(!response.ok)return {success:false,error:result?.errors?.transfer?.[0]||result?.message||'Transfer creation failed.'};
   if(!Number.isSafeInteger(result?.id)||result.source_balance_id!==source.balance_id||result.destination_balance_id!==destination.balance_id)
    return {success:false,error:'Transfer receipt requires verification.'};
   setRevision(value=>value+1);return {success:true};
  } catch {return {success:false,error:'Response unavailable. Verify transfer before retrying.'};}
 },update:async(id:string,status:string,_operator:string,notes='')=>{
  const row=rows.find(row=>row.id===id);
  if(!row||!['in_transit','completed','cancelled'].includes(status))return {success:false,error:'Reload the transfer before continuing.'};
  const action=status==='in_transit'?'dispatch':status==='completed'?'receive':'cancel';
  const quantity=action==='receive'?row.quantity-row.received_quantity:0;
  const identity=JSON.stringify([id,row.version,action,quantity,notes]);
  if(!keys.current.has(identity))keys.current.set(identity,crypto.randomUUID());
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
  if(!csrf)return {success:false,error:'Authenticated session required.'};
  try {
   const response=await fetch(`/admin/api/inventory/transfers/${encodeURIComponent(id)}/transition`,{method:'POST',credentials:'same-origin',
     headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
     body:JSON.stringify({action,quantity,version:row.version,notes,operation_key:keys.current.get(identity)})});
   const result=await response.json();
   if(!response.ok)return {success:false,error:result?.errors?.transfer?.[0]||result?.message||'Transfer update failed.'};
   if(String(result?.id)!==id||result?.version!==row.version+1)return {success:false,error:'Transfer receipt requires verification.'};
   setRevision(value=>value+1);return {success:true};
  } catch {return {success:false,error:'Response unavailable. Verify transfer before retrying.'};}
 }};
}
