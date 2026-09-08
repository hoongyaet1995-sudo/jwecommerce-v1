export async function readMovementHistory(variantId:string,signal:AbortSignal,request:typeof fetch=fetch,kind:'available'|'physical'='available') {
  if(!/^\d+$/.test(variantId))throw new Error('Invalid SKU identity.');
  const history:any[]=[]; const seen=new Set<number>(); let expected:number|undefined;
  for(let page=1;page<=100;page++) {
    const response=await request(`/admin/api/inventory/${kind==='physical'?'physical-movements':'movements'}?variantId=${variantId}&per_page=100&page=${page}`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!response.ok)throw new Error(`Inventory ledger read failed (${response.status}).`);
    const data=await response.json();
    if(data.current_page!==page||!Number.isSafeInteger(data.last_page)||data.last_page<page||data.last_page>100||!Number.isSafeInteger(data.total)||data.total<0||data.total>10000||!Array.isArray(data.data))throw new Error('Ledger exceeds preview limits or returned an invalid page.');
    expected??=data.total;
    if(expected!==data.total)throw new Error('Ledger changed during loading. Reopen the ledger.');
    for(const row of data.data) {
      if(String(row.product_variant_id)!==variantId||!Number.isSafeInteger(row.id)||seen.has(row.id))throw new Error('Ledger identity changed during loading.');
      seen.add(row.id);
      if(kind==='physical') {
        history.push({id:`physical:${row.id}`,timestamp:row.created_at,action:'Physical count',change:row.physical_delta,
          resultingStock:row.physical_after,user:`User #${row.actor_id}`,
          reason:`${row.reason} · ${row.pool_kind==='shared'?'Shared pool':`Warehouse #${row.warehouse_id}`} · Physical after: ${row.physical_after} · Version: ${row.balance_version}`});
        continue;
      }
      history.push({id:String(row.id),timestamp:row.created_at,action:'Available stock adjustment',change:row.available_delta,
        resultingStock:null,user:row.actor_id===null?'Actor not recorded':`User #${row.actor_id}`,
        reason:`${row.reason} · Available after: ${row.available_after} · Version: ${row.stock_version}`});
    }
    if(page===data.last_page){if(history.length!==expected)throw new Error('Incomplete ledger.');return history;}
  }
  throw new Error('Ledger exceeds preview limits.');
}

export async function readCombinedMovementHistory(variantId:string,signal:AbortSignal,request:typeof fetch=fetch) {
  const [available,physical]=await Promise.all([readMovementHistory(variantId,signal,request),readMovementHistory(variantId,signal,request,'physical')]);
  return [...available.map(row=>({...row,id:`available:${row.id}`})),...physical]
    .sort((a,b)=>String(b.timestamp).localeCompare(String(a.timestamp))||b.id.localeCompare(a.id));
}
