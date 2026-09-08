import React,{useEffect,useRef,useState} from 'react';
import {WarehouseTransferTab} from './components/marketplace/WarehouseTransferTab';
import {CreateWarehouseTransferModal} from './components/marketplace/CreateWarehouseTransferModal';

export function TransferPreview(){
 const [rows,setRows]=useState<any[]>([]);const [message,setMessage]=useState('Loading transfers…');const [revision,setRevision]=useState(0);
 const keys=useRef(new Map<string,string>());
 const [options,setOptions]=useState<any[]|null>(null);
 const opening=useRef(false);
 async function openCreate(){
  if(message){alert(message);return;}if(opening.current)return;opening.current=true;
  try{
   const all:any[]=[];let total:number|undefined;
   for(let page=1;page<=100;page++){
    const response=await fetch(`/admin/api/inventory/transfer-options?page=${page}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!response.ok)throw new Error('Warehouse balances could not be loaded.');
    const result=await response.json();
    if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.total)||!Number.isSafeInteger(result.last_page)
      ||result.last_page<page||result.last_page>100||(total!==undefined&&total!==result.total))throw new Error('Warehouse balances changed. Reload before creating a transfer.');
    total=result.total;all.push(...result.data);if(page===result.last_page)break;
   }
   if(all.length!==total||new Set(all.map(row=>row.balance_id)).size!==all.length)throw new Error('Warehouse balances were incomplete.');
   if(!all.length)throw new Error('Configure active warehouse balances before creating a transfer.');
   setOptions(all);
  }catch(error){alert(error instanceof Error?error.message:'Transfer options unavailable.');}finally{opening.current=false;}
 }
 useEffect(()=>{
  const abort=new AbortController();setMessage('Loading transfers…');
  async function load(){
   const all:any[]=[];let total:number|undefined;let mode:unknown;
   for(let page=1;page<=100;page++){
    const response=await fetch(`/admin/api/inventory/transfers?page=${page}`,{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!response.ok)throw new Error('Transfer list unavailable. Reload before continuing.');
    const result=await response.json();
    if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.total)||!Number.isSafeInteger(result.last_page)
     ||result.last_page<page||result.last_page>100||(total!==undefined&&total!==result.total)||(page>1&&mode!==result.operating_mode))throw new Error('Transfer list changed. Reload before continuing.');
    total=result.total;mode=result.operating_mode;all.push(...result.data);if(page===result.last_page)break;
   }
   if(all.length!==total||new Set(all.map(row=>row.id)).size!==all.length)throw new Error('Transfer list was incomplete.');
   if(abort.signal.aborted)return;
   setRows(all.map(row=>({...row,id:String(row.id),masterSku:row.sku,productName:row.product_name,variantName:row.variant_name,
    sourceWarehouseId:String(row.source_warehouse_id),sourceWarehouseName:row.source_warehouse_name,
    destinationWarehouseId:String(row.destination_warehouse_id),destinationWarehouseName:row.destination_warehouse_name,
    requestedBy:`Employee #${row.actor_id}`,createdAt:row.created_at,dispatchedAt:row.dispatched_at,completedAt:row.completed_at})));
   setMessage(mode==='independent_warehouses'?'':'Independent warehouse accounting is not configured. Transfers are unavailable.');
  }
  load().catch(error=>{if(!abort.signal.aborted){setRows([]);setMessage(error.message);}});return()=>abort.abort();
 },[revision]);
 return <main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 p-4 md:p-8">
  <WarehouseTransferTab transfers={rows} warehouses={[]} staffUsers={[]} availabilityMessage={message}
   onOpenCreateTransferModal={openCreate}
   onUpdateTransferStatus={async(id,status,_operator,notes)=>{
    const row=rows.find(row=>row.id===id);if(!row||message)return {success:false,error:message||'Reload the transfer.'};
    const action=status==='in_transit'?'dispatch':status==='completed'?'receive':'cancel';
    const quantity=action==='receive'?row.quantity-row.received_quantity:0;
    const identity=JSON.stringify([id,row.version,action,quantity,notes??'']);
    if(!keys.current.has(identity))keys.current.set(identity,crypto.randomUUID());
    const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if(!csrf)return {success:false,error:'Reload your session before continuing.'};
    const response=await fetch(`/admin/api/inventory/transfers/${encodeURIComponent(id)}/transition`,{method:'POST',credentials:'same-origin',
     headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
     body:JSON.stringify({action,quantity,version:row.version,notes:notes??'',operation_key:keys.current.get(identity)})});
    const result=await response.json().catch(()=>null);
    if(!response.ok)return {success:false,error:result?.errors?.transfer?.[0]||result?.message||'Transfer update failed.'};
    if(String(result?.id)!==id||result?.version!==row.version+1)return {success:false,error:'Transfer receipt could not be verified. Reload the list.'};
    setRevision(value=>value+1);return {success:true};
   }}/>
  {options && <CreateWarehouseTransferModal isOpen onClose={()=>setOptions(null)}
    warehouses={Array.from(new Map(options.map(row=>[row.warehouse_id,{id:String(row.warehouse_id),code:row.warehouse_code,name:row.warehouse_name,status:'active'}])).values()) as any}
    inventory={Array.from(new Map(options.map(row=>[row.variant_id,{masterSku:row.sku,productName:row.product_name,variantName:row.variant_name,
      warehouseBalances:options.filter(balance=>balance.variant_id===row.variant_id).map(balance=>({warehouseId:String(balance.warehouse_id),availableAtp:balance.atp}))}])).values()) as any}
    staffUsers={[{id:document.getElementById('root')?.dataset.employeeId??'',fullName:document.getElementById('root')?.dataset.employeeName??'',username:'',position:'Admin'}] as any}
    currentUserName={document.getElementById('root')?.dataset.employeeName}
    onCreateTransfer={async data=>{
      const source=options.find(row=>row.sku===data.masterSku&&String(row.warehouse_id)===data.sourceWarehouseId);
      const destination=options.find(row=>row.sku===data.masterSku&&String(row.warehouse_id)===data.destinationWarehouseId);
      if(!source||!destination)return {success:false,error:'Configure this SKU in both warehouses before transferring.'};
      const notes=[data.reason,data.trackingNumber?`Tracking: ${data.trackingNumber}`:''].filter(Boolean).join('\n');
      const payload={source_balance_id:source.balance_id,destination_balance_id:destination.balance_id,quantity:data.quantity,notes};
      const identity=JSON.stringify(payload);if(!keys.current.has(identity))keys.current.set(identity,crypto.randomUUID());
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
      if(!csrf)return {success:false,error:'Reload your session before continuing.'};
      const response=await fetch('/admin/api/inventory/transfers',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
        body:JSON.stringify({...payload,operation_key:keys.current.get(identity)})});
      const result=await response.json().catch(()=>null);
      if(!response.ok)return {success:false,error:result?.errors?.transfer?.[0]||result?.message||'Transfer creation failed.'};
      if(!Number.isSafeInteger(result?.id)||result.source_balance_id!==source.balance_id||result.destination_balance_id!==destination.balance_id)return {success:false,error:'Transfer receipt could not be verified. Reload the list.'};
      setRevision(value=>value+1);return {success:true};
    }}/>} 
 </main>;
}
