export async function readSafetyHistory(variantId:string,signal:AbortSignal,request:typeof fetch=fetch){
 const rows:any[]=[];let expected:number|undefined;
 for(let page=1;page<=100;page++){
  const response=await request(`/admin/api/inventory/basic/safety-history?variantId=${encodeURIComponent(variantId)}&page=${page}`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
  if(!response.ok)throw new Error('Safety stock history could not be loaded. Reopen the ledger.');
  const result=await response.json();
  if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.last_page)||result.last_page<page||result.last_page>100
   ||!Number.isSafeInteger(result.total)||result.total<0||(expected!==undefined&&expected!==result.total))throw new Error('Safety history changed. Reload.');
  expected=result.total;rows.push(...result.data);if(page===result.last_page)break;
 }
 if(rows.length!==expected||new Set(rows.map(row=>row.operation_key)).size!==rows.length||rows.some(row=>Number(row.product_variant_id)!==Number(variantId)))throw new Error('Safety history response was incomplete.');
 return rows.map(row=>{
  const receipt=row.receipt;
  if(!receipt||receipt.operation_key!==row.operation_key||!Number.isSafeInteger(receipt.safety_stock)||receipt.safety_stock<0
    ||!Number.isSafeInteger(receipt.actor_id)||receipt.actor_id<1||!Number.isFinite(Date.parse(receipt.timestamp)))throw new Error('Invalid safety audit receipt.');
  return {action:`${row.pool_kind==='shared'?'Shared pool':`Warehouse #${row.warehouse_id}`} · Safety ${receipt.previous_safety_stock??'Unknown'} → ${receipt.safety_stock}`,
    change:0,reason:receipt.reason,user:`Employee #${receipt.actor_id}`,timestamp:receipt.timestamp};
 });
}
