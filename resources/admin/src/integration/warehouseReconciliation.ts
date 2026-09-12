type Review = {masterSku:string;warehouseId:string;balanceId:number;balanceVersion:number;stockVersion:number;operationKey:string};
type Input = {masterSku:string;warehouseId:string;newSafetyStock:number;reason:string;expectedVersion?:number|string;operatorName:string};
type Payload = {balance_id:number;balance_version:number;stock_version:number;safety_stock:number;operation_key:string;reason:string};
type Result = {status:string;receipt_id:string;timestamp:string;operator?:string;operator_id?:number;physical_stock:number;reserved_stock:number;safety_stock:number;atp:number;balance_version:number;previous_reserved_stock:number|null;previous_safety_stock:number|null;previous_atp:number|null};

export async function loadWarehouseReview(balanceId:number,variantId:number,poolId:number,request:typeof fetch=fetch) {
  if(![balanceId,variantId,poolId].every(id=>Number.isSafeInteger(id)&&id>0)) throw new Error('Invalid balance identity.');
  const response=await request(`/admin/api/inventory/balances/${balanceId}/obligations`,{credentials:'same-origin',headers:{Accept:'application/json'}});
  if(!response.ok) throw new Error('Warehouse review could not be loaded.');
  const body=await response.json();
  if(body?.balance?.balance_id!==balanceId||body.balance.variant_id!==variantId||body.balance.pool_id!==poolId)
    throw new Error('Warehouse review identity changed.');
  if(![body.balance.version,body.stock_version,body.derived_reserved_stock].every(n=>Number.isSafeInteger(n)&&n>=0))
    throw new Error('Warehouse review is incomplete.');
  return body;
}

/** Read every reservation page without combining different balance revisions. */
export async function loadCompleteWarehouseReview(balanceId:number,variantId:number,poolId:number,request:typeof fetch=fetch) {
  const first=await loadWarehouseReview(balanceId,variantId,poolId,request);
  const rows:any[]=[];
  const seen=new Set<number>();
  const total=first.allocations?.total;
  const last=first.allocations?.last_page;
  if(!Number.isSafeInteger(total)||total<0||!Number.isSafeInteger(last)||last<1||last>100)
    throw new Error('Reservation review is incomplete.');
  for(let page=1;page<=last;page++) {
    const snapshot=page===1?first:await loadWarehouseReview(balanceId,variantId,poolId,
      (url,options)=>request(`${url}?page=${page}`,options));
    if(JSON.stringify(snapshot.balance)!==JSON.stringify(first.balance)||snapshot.stock_version!==first.stock_version
      ||snapshot.derived_reserved_stock!==first.derived_reserved_stock||snapshot.unowned_reserved_stock!==first.unowned_reserved_stock)
      throw new Error('Inventory changed while loading reservations. Reopen the review.');
    const pagination=snapshot.allocations;
    if(pagination?.total!==total||pagination.last_page!==last||pagination.current_page!==page||!Array.isArray(pagination.data))
      throw new Error('Reservation review is incomplete.');
    for(const row of pagination.data) {
      if(!Number.isSafeInteger(row.id)||row.id<1||seen.has(row.id)||!Number.isSafeInteger(row.quantity)||row.quantity<1)
        throw new Error('Invalid reservation review.');
      seen.add(row.id);rows.push(row);
    }
  }
  if(rows.length!==total||rows.reduce((sum,row)=>sum+row.quantity,0)!==first.derived_reserved_stock)
    throw new Error('Reservation totals changed. Reopen the review.');
  return {...first,allocationRows:rows};
}

export function warehouseReconciliationPost(csrf:string,request:typeof fetch=fetch) {
  return async (payload:Payload):Promise<Result>=>{
    if(!csrf) throw new Error('Authenticated inventory session required.');
    const response=await request('/admin/api/inventory/reconciliations',{method:'POST',credentials:'same-origin',
      headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
    let body;
    try {body=await response.json();} catch {throw new Error('Reconciliation response unavailable. Verify before retrying.');}
    if(!response.ok) throw new Error(typeof body?.message==='string'?body.message:'Reconciliation failed. Reload the review.');
    if(!body||typeof body!=='object'||Array.isArray(body)) throw new Error('Invalid reconciliation receipt.');
    return body as Result;
  };
}

/** Bind to one reviewed balance. Never replace reviewed versions during submission. */
export function createWarehouseReconciliation(review:Review,post:(payload:Payload)=>Promise<Result>) {
  const pinned={...review};
  let submitted:string|undefined;
  return async (input:Input)=>{
    if(input.masterSku!==pinned.masterSku||input.warehouseId!==pinned.warehouseId
      ||String(input.expectedVersion)!==String(pinned.balanceVersion)) throw new Error('Balance changed. Open a new review.');
    if(!Number.isSafeInteger(input.newSafetyStock)||input.newSafetyStock<0||input.newSafetyStock>2147483647
      ||!input.reason.trim()||input.reason.length>255) throw new Error('Review safety stock and reason.');
    const payload:Payload={balance_id:pinned.balanceId,balance_version:pinned.balanceVersion,stock_version:pinned.stockVersion,
      safety_stock:input.newSafetyStock,operation_key:pinned.operationKey,reason:input.reason};
    const identity=JSON.stringify(payload);
    if(submitted!==undefined&&submitted!==identity) throw new Error('Changed submission requires a new review.');
    submitted=identity;
    const result=await post(payload);
    if(result.status!=='reconciled'||result.receipt_id!==pinned.operationKey
      ||![result.physical_stock,result.reserved_stock,result.safety_stock,result.atp,result.balance_version].every(n=>Number.isSafeInteger(n)&&n>=0)
      ||result.safety_stock!==input.newSafetyStock
      ||result.balance_version!==pinned.balanceVersion+1
      ||result.atp!==Math.max(0,result.physical_stock-result.reserved_stock-result.safety_stock))
      throw new Error('Reconciliation receipt requires verification.');
    return {success:true,receiptId:result.receipt_id,timestamp:result.timestamp,...(result.operator?{operator:result.operator}:{}),masterSku:pinned.masterSku,warehouseId:pinned.warehouseId,
      physicalStock:result.physical_stock,physicalStockStatus:'verified' as const,previousReserved:result.previous_reserved_stock??undefined,
      newReserved:result.reserved_stock,previousSafety:result.previous_safety_stock??undefined,newSafety:result.safety_stock,
      previousAtp:result.previous_atp??undefined,newAtp:result.atp,status:'RECONCILED_IN_WMS_LEDGER' as const};
  };
}
