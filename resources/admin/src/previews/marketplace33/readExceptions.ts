import {serializedFetch} from './serializedFetch';
export function exceptionView(row:any) {
 if(row.error_code==='warehouse_allocation_requires_review')return {id:String(row.order_id),orderNumber:row.external_order_id,
  platform:row.platform,storeName:row.shop_name,placedAt:row.checked_at,type:'other',severity:'warning',channelSku:'Not available',
  channelItemTitle:'Order-level warehouse exception',requestedQuantity:null,availableAtp:null,status:'open',isHistoricalExcluded:false,
  statusLabel:'Warehouse allocation requires review',explanation:'Review the warehouse route, verified ATP and reservation ownership. This processing attempt was rolled back; the platform order remains available for review.'};
 const kinds:Record<string,string>={historical_order:'predates_activation',unmapped_order_line:'unmapped_sku',insufficient_stock:'insufficient_stock'};
 const explanations:Record<string,string>={historical_order:'Historical order requires reconciliation. Reading this order did not deduct stock; no exclusion decision has been recorded.',unmapped_order_line:'One or more order lines have no same-store JW SKU mapping. Review the order before inventory accounting.',insufficient_stock:'The attempted reservation exceeded JW available stock. Current physical stock and ATP are not verified by this exception.'};
 return {id:String(row.order_id),orderNumber:row.external_order_id,platform:row.platform,storeName:row.shop_name,
  placedAt:row.checked_at,type:kinds[row.error_code]||'other',severity:'warning',channelSku:'Not available',channelItemTitle:'Order-level inventory exception',
  requestedQuantity:null,availableAtp:null,status:'open',isHistoricalExcluded:false,
  statusLabel:row.error_code==='historical_order'?'Requires reconciliation':row.error_code==='unmapped_order_line'?'Unmapped SKU':row.error_code==='insufficient_stock'?'Reservation rejected':row.error_code,
  explanation:explanations[row.error_code]||`Inventory processing requires review: ${row.error_code}. No resolution is implied.`};
}
export async function readExceptions(signal:AbortSignal,request:typeof fetch=serializedFetch) {
 const result:any[]=[];const seen=new Set<number>();let total:number|undefined;
 for(let page=1;page<=100;page++) {
  const response=await request(`/admin/api/marketplace/inventory/exceptions?per_page=100&page=${page}`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
  if(!response.ok)throw new Error(`Exception read failed (${response.status}).`);
  const body=await response.json();
  if(body.current_page!==page||!Number.isSafeInteger(body.last_page)||body.last_page<page||body.last_page>100||!Number.isSafeInteger(body.total)||body.total<0||body.total>10000||!Array.isArray(body.data))throw new Error('Invalid or oversized exception result.');
  total??=body.total;if(total!==body.total)throw new Error('Exceptions changed while loading. Reload to retry.');
  for(const row of body.data){if(!Number.isSafeInteger(row.order_id)||seen.has(row.order_id))throw new Error('Exceptions changed while loading.');seen.add(row.order_id);result.push(exceptionView(row));}
  if(page===body.last_page){if(result.length!==total)throw new Error('Incomplete exception read.');return result;}
 }
 throw new Error('Exception preview limit exceeded.');
}

