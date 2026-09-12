export type SandboxStockReview={platform:'lazada'|'shopee';store_id:number;inventory_balance_id:number;listing_variant_id:number;
 desired_quantity:number;balance_version:number;route_version:number;configuration_version:number;external_product_id:string;external_variant_id:string;external_shop_id:string;platform_location_id:string;order_coverage_complete:false};
export async function sandboxOptions(variantId:string,signal:AbortSignal,request:typeof fetch=fetch):Promise<SandboxStockReview[]>{
 const response=await request(`/admin/api/inventory/sandbox-options?variantId=${encodeURIComponent(variantId)}`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
 if(!response.ok)throw new Error('Sandbox eligibility could not be loaded.');
 const body=await response.json();if(!Array.isArray(body.options))throw new Error('Sandbox eligibility response was incomplete.');
 return body.enabled===true?body.options:[];
}
export function sandboxPush(review:SandboxStockReview,csrf:string,request:typeof fetch=fetch){
 const key=crypto.randomUUID();
 return async()=>{
  if(!csrf)throw new Error('Reload the authenticated session.');
  const post=async(url:string,body:object)=>{
   const response=await request(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});
   const result=await response.json();if(!response.ok)throw new Error(result.message||'Sandbox operation failed.');return result;
  };
  let receipt=await post('/admin/api/inventory/sandbox-attempts',{store_id:review.store_id,balance_id:review.inventory_balance_id,operation_key:key,reason:'Operator initiated sandbox stock test from ZIP36 single-store controller'});
  const matches=(r:any)=>r.id===key&&r.store_id===review.store_id&&r.inventory_balance_id===review.inventory_balance_id
   &&Object.keys(review).every(field=>r.snapshot?.[field]===review[field as keyof SandboxStockReview]);
  if(!matches(receipt))throw new Error('The test stock target changed. Reopen the controller before sending.');
  if(receipt.status==='staged')receipt=await post(`/admin/api/inventory/sandbox-attempts/${key}/dispatch`,{});
  if(!matches(receipt))throw new Error('Test write receipt identity could not be verified. Inspect the audit before retrying.');
  if(receipt.status!=='confirmed'||receipt.observed_quantity!==review.desired_quantity)throw new Error(`Test attempt ${key}: ${receipt.status}. Inspect provider state before another write.`);
  return receipt;
 };
}
