export async function readPhysicalHistory(variantId:string,signal:AbortSignal,request:typeof fetch=fetch){
  const rows:any[]=[];
  let expected:number|undefined;
  for(let page=1;page<=100;page++){
    const response=await request(`/admin/api/inventory/physical-movements?variantId=${encodeURIComponent(variantId)}&page=${page}&per_page=100`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!response.ok)throw new Error('Stock ledger could not be loaded. Close and reopen to retry.');
    const result=await response.json();
    if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.last_page)||result.last_page<page||result.last_page>100
      ||!Number.isSafeInteger(result.total)||(expected!==undefined&&expected!==result.total))throw new Error('Stock ledger changed. Close and reopen to reload.');
    expected=result.total;rows.push(...result.data);
    if(page===result.last_page)break;
  }
  if(rows.length!==expected||new Set(rows.map(row=>row.id)).size!==rows.length||rows.some(row=>Number(row.product_variant_id)!==Number(variantId)))throw new Error('Stock ledger response was incomplete.');
  return rows.map(row=>({
    action:`${row.pool_kind==='shared'?'Shared pool':`Warehouse #${row.warehouse_id}`} · ${row.physical_before??'Unknown'} → ${row.physical_after}`,
    change:row.physical_delta,reason:row.reason,user:`Employee #${row.actor_id}`,
    timestamp:row.created_at.replace(' ','T')+( /(?:Z|[+-]\d\d:\d\d)$/.test(row.created_at)?'':'Z'),
  }));
}
