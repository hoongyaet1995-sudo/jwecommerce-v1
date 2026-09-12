import {countReviewApi,CountReview} from './countReviewApi';

/** Provision only on an explicit review request; an empty balance never implies zero stock. */
export function initialCountApi(variantId:number,kind:'shared'|'warehouse',warehouseId:number|null,csrf:string,request:typeof fetch=fetch){
 let api:ReturnType<typeof countReviewApi>|undefined;
 async function ensure(){
  if(api)return api;
  if(!csrf||!Number.isSafeInteger(variantId)||variantId<1)throw new Error('Reload before configuring physical inventory.');
  const response=await request('/admin/api/inventory/balances',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
   body:JSON.stringify({variant_id:variantId,pool_kind:kind,...(kind==='warehouse'?{warehouse_id:warehouseId}:{})})});
  const row=await response.json();
  if(!response.ok)throw new Error(row.message||'Physical balance could not be configured.');
  if(!Number.isSafeInteger(row.balance_id)||row.balance_id<1||row.variant_id!==variantId)throw new Error('Unexpected balance identity. Reload before continuing.');
  api=countReviewApi(row.balance_id,csrf,request);return api;
 }
 return {
  review:async(count:number,reason:string)=>(await ensure()).review(count,reason),
  adjustment:async()=>{throw new Error('Record an initial physical count before an adjustment.');},
  confirm:async(review:CountReview)=>{
   if(!api)throw new Error('Reload and review the physical count before confirming.');
   await api.confirm(review);
  },
 };
}
