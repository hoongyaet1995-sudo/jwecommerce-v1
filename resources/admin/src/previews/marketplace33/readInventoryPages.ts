import {serializedFetch} from './serializedFetch';
/** Complete bounded preview read, not a transactionally consistent inventory review. */
export async function readInventoryPages(signal:AbortSignal, request:typeof fetch=serializedFetch):Promise<any[]> {
  const rows:any[]=[];
  const seen=new Set<number>();
  let total:number|undefined;
  let last:number|undefined;
  for(let current=1;current<=100;current++) {
    const response=await request(`/admin/api/marketplace/inventory?per_page=100&page=${current}`,{credentials:'same-origin',signal,headers:{Accept:'application/json'}});
    if(!response.ok)throw new Error(`Inventory read failed (${response.status}). No complete result loaded.`);
    const page=await response.json();
    if(!Number.isSafeInteger(page.total)||page.total<0||!Number.isSafeInteger(page.last_page)||page.last_page<1||page.current_page!==current||!Array.isArray(page.data))throw new Error('Invalid inventory page.');
    if(page.last_page>100||page.total>10000)throw new Error('Inventory exceeds the local preview limit; server pagination is required.');
    total??=page.total; last??=page.last_page;
    if(total!==page.total||last!==page.last_page)throw new Error('Inventory changed while loading. Reload the preview.');
    for(const row of page.data) {
      if(!Number.isSafeInteger(row.id)||seen.has(row.id))throw new Error('Inventory changed while loading. Reload the preview.');
      seen.add(row.id); rows.push(row);
    }
    if(current===last) {
      if(rows.length!==total)throw new Error('Inventory changed while loading. Reload the preview.');
      return rows;
    }
  }
  throw new Error('Inventory exceeds the local preview limit.');
}

