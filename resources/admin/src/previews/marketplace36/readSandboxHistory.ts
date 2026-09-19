export async function readSandboxHistory(variantId:string,signal:AbortSignal,request:typeof fetch=fetch){
 const rows:any[]=[];let expected:number|undefined;
 for(let page=1;page<=100;page++){
  const response=await request(`/admin/api/inventory/sandbox-attempts?variantId=${encodeURIComponent(variantId)}&page=${page}`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
  if(!response.ok)throw new Error('Sandbox audit history could not be loaded. Reopen the ledger.');
  const body=await response.json();
  if(!Array.isArray(body.data)||body.current_page!==page||!Number.isSafeInteger(body.last_page)||body.last_page<page||body.last_page>100
   ||!Number.isSafeInteger(body.total)||body.total<0||(expected!==undefined&&body.total!==expected))throw new Error('Sandbox audit changed. Reload.');
  expected=body.total;rows.push(...body.data);if(page===body.last_page)break;
 }
 if(rows.length!==expected||new Set(rows.map(row=>row.id)).size!==rows.length||rows.some(row=>Number(row.product_variant_id)!==Number(variantId)))throw new Error('Sandbox audit response was incomplete.');
 return rows.flatMap(row=>[{attemptId:row.status==='uncertain'?row.id:undefined,action:`Sandbox ${row.snapshot.platform} store #${row.store_id} · ${row.status} · Platform ${row.before_quantity??'Unknown'} → ${row.observed_quantity??'Unconfirmed'}`,
  change:0,reason:`${row.reason} · ${row.snapshot.platform_location_id} · Attempt ${row.id}${row.error_code?' · '+row.error_code:''}`,
  user:`Employee #${row.actor_id}`,timestamp:row.updated_at.replace(' ','T')+(/(?:Z|[+-]\d\d:\d\d)$/.test(row.updated_at)?'':'Z')},...(row.rechecks??[]).map((check:any)=>({
  action:`Sandbox ${row.snapshot.platform} store #${row.store_id} · Recheck ${check.outcome} · Platform ${check.observed_quantity??'Unknown'}`,
  change:0,reason:`Read only; original attempt ${row.id} retained.`,user:`Employee #${check.actor_id}`,
  timestamp:check.created_at.replace(' ','T')+(/(?:Z|[+-]\d\d:\d\d)$/.test(check.created_at)?'':'Z')
 }))]);
}
