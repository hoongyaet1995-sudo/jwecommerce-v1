import React,{useEffect,useState} from 'react';
import {WarehouseInventoryReconcileModal} from './components/marketplace/WarehouseInventoryReconcileModal';
import {loadReconciliation} from './loadReconciliation';
import type {MarketplaceInventoryItem,MarketplaceOrder,StoreFulfillmentBinding,InventoryOrderException} from './types';

type Props={item:MarketplaceInventoryItem;identity:Parameters<typeof loadReconciliation>[1];
  storeBindings:StoreFulfillmentBinding[];exceptions:InventoryOrderException[];
  onOpenAuditModal:(item:MarketplaceInventoryItem,warehouseId:string)=>void;
  onClose:()=>void;onError:(message:string)=>void;onCommitted:()=>void};

export function ConnectedReconciliationModal(props:Props) {
 return <LoadedReconciliation key={`${props.item.id}:${props.identity.balanceId}`} {...props}/>;
}

const LoadedReconciliation:React.FC<Props>=({item,identity,storeBindings,exceptions,onOpenAuditModal,onClose,onError,onCommitted})=>{
 const [review,setReview]=useState<Awaited<ReturnType<typeof loadReconciliation>>|null>(null);
 const [employee,setEmployee]=useState('');
 useEffect(()=>{
   const abort=new AbortController();
   const root=document.getElementById('root');
   const name=root?.dataset.employeeName;
   const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
   if(!root?.dataset.employeeId||!name) {onError('Authenticated inventory employee required.');return;}
   setEmployee(name);
   loadReconciliation(item,identity,csrf,(url,options)=>fetch(url,{...options,signal:abort.signal}),true)
     .then(result=>{if(!abort.signal.aborted)setReview(result);})
     .catch(error=>{if(!abort.signal.aborted)onError(error.message);});
   return()=>abort.abort();
 },[item.id,identity.balanceId]);
 if(!review)return null;
 return <WarehouseInventoryReconcileModal isOpen item={item} warehouse={review.warehouse}
   allOrders={review.allOrders as unknown as MarketplaceOrder[]} storeBindings={storeBindings}
   exceptions={exceptions} onOpenAuditModal={onOpenAuditModal}
   currentUserName={employee} onClose={onClose} onReconcile={async input=>{
     const receipt=await review.onReconcile(input);
     onCommitted();
     return {...receipt,warehouseCode:identity.warehouseCode,warehouseName:identity.warehouseName,productName:item.productName};
   }}/>;
}
