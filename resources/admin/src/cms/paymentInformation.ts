import {useEffect,useState} from 'react';
import {usePageRuntime,usePageSectionRuntime} from './PageRuntimeContext';
export interface PaymentMethodFact {id:string;title:string;description:string;category:string;status:'available'|'sandbox'|'setup_required'|'integration_pending'|'unavailable'}
export interface PaymentFacts {methods:PaymentMethodFact[];bankTransfer:boolean}
export function isPaymentFacts(value:unknown):value is PaymentFacts {
 if(!value||typeof value!=='object'||Array.isArray(value))return false;
 const v=value as PaymentFacts;
 return Object.keys(v).every(k=>['methods','bankTransfer'].includes(k))&&typeof v.bankTransfer==='boolean'&&Array.isArray(v.methods)&&v.methods.every(m=>m&&typeof m==='object'&&Object.keys(m).every(k=>['id','title','description','category','status'].includes(k))&&['id','title','description','category'].every(k=>typeof m[k as keyof PaymentMethodFact]==='string')&&['available','sandbox','setup_required','integration_pending','unavailable'].includes(m.status));
}
export function usePaymentInformation(sectionId:string){
 const runtime=usePageRuntime(),server=usePageSectionRuntime(sectionId).payments;
 const [loaded,setLoaded]=useState<PaymentFacts>(),[failed,setFailed]=useState(false);
 useEffect(()=>{
  if(server||runtime.context.mode!=='editing')return;
  const abort=new AbortController();setFailed(false);
  fetch(`/admin/api/cms/payment-methods?currency=${encodeURIComponent(runtime.context.currency)}`,{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>{if(!r.ok)throw Error('Unavailable');return r.json()}).then(v=>{if(!isPaymentFacts(v))throw Error('Invalid facts');setLoaded(v)}).catch(()=>{if(!abort.signal.aborted)setFailed(true)});
  return ()=>abort.abort();
 },[server,runtime.context.mode,runtime.context.currency]);
 return {facts:server??loaded,failed,editing:runtime.context.mode==='editing'};
}
