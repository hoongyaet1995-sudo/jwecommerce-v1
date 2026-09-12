type Identity={masterSku:string;warehouseId:string;balanceId:number;version:number};
type Input={masterSku:string;warehouseId:string;newSafetyStock:number;reason:string;expectedVersion?:number|string;operatorName:string};
export function basicSafetyApi(identity:Identity,csrf:string,request:typeof fetch=fetch){
 const key=crypto.randomUUID();let submitted:string|undefined;
 return async(input:Input)=>{
  if(!csrf||input.masterSku!==identity.masterSku||input.warehouseId!==identity.warehouseId||String(input.expectedVersion)!==String(identity.version))throw new Error('Reload the safety stock review.');
  if(!Number.isSafeInteger(input.newSafetyStock)||input.newSafetyStock<0||input.newSafetyStock>2147483647||!input.reason.trim())throw new Error('Enter safety stock and a reason.');
  const payload={balance_id:identity.balanceId,balance_version:identity.version,safety_stock:input.newSafetyStock,reason:input.reason,operation_key:key};
  const hash=JSON.stringify(payload);if(submitted&&submitted!==hash)throw new Error('Reopen the review before changing a submitted operation.');submitted=hash;
  const response=await request('/admin/api/inventory/basic/safety',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:hash});
  const result=await response.json();if(!response.ok)throw new Error(result.message||'Safety stock save failed.');
  const basic=result.basic_wms;
  if(result.operation_key!==key||result.balance_id!==identity.balanceId||result.balance_version!==identity.version+1||result.safety_stock!==input.newSafetyStock
   ||!basic||basic.order_coverage_complete!==false||basic.platform_write_authorized!==false
   ||![basic.physical_stock,basic.owned_reserved,basic.available].every(n=>Number.isSafeInteger(n)&&n>=0)
   ||basic.available!==Math.max(0,basic.physical_stock-basic.owned_reserved-result.safety_stock))throw new Error('Safety receipt could not be verified. Retry the same operation.');
  return {success:true,receiptId:key,timestamp:result.timestamp,operator:`Employee #${result.actor_id}`,masterSku:identity.masterSku,warehouseId:identity.warehouseId,
   physicalStock:basic.physical_stock,physicalStockStatus:'verified' as const,newReserved:basic.owned_reserved,
   previousSafety:result.previous_safety_stock??undefined,newSafety:result.safety_stock,newAtp:basic.available,status:'BASIC_SAFETY_CONFIGURED' as const};
 };
}
