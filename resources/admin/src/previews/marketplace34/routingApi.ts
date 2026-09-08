import type {StoreFulfillmentBinding} from './types';

export type RouteIdentity = {channelKey:string; version:number|null; enabled:boolean};

export function routingSaver(csrf:string, identity:RouteIdentity, request:typeof fetch=fetch) {
  // Capture the loaded identity; edited form values cannot retarget another store.
  const {channelKey, version, enabled}=identity;
  return async (data:Partial<StoreFulfillmentBinding>):Promise<{success:boolean;error?:string}>=>{
    if(!csrf)return {success:false,error:'Session unavailable. Reload before saving.'};
    const warehouse=Number(data.boundWarehouseId);
    if(!Number.isSafeInteger(warehouse)||warehouse<1)return {success:false,error:'Select a configured warehouse.'};
    const payload={channel_key:channelKey,version,warehouse_id:warehouse,is_enabled:enabled,
      platform_location_id:data.platformLocationId||null,platform_location_name:data.platformLocationName||null,
      fulfillment_priority:data.fulfillmentPriority??1};
    try {
      const response=await request('/admin/api/inventory/fulfillment-routes',{method:'PUT',credentials:'same-origin',
        headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
      const receipt=await response.json().catch(()=>null);
      if(!response.ok)return {success:false,error:receipt?.errors?.route?.[0]||receipt?.message||'Route save failed.'};
      if(receipt?.channel_key!==channelKey||Number(receipt?.warehouse_id)!==warehouse
        ||!Number.isSafeInteger(receipt?.version)||receipt.version!==(version===null?0:version+1)) {
        return {success:false,error:'Route receipt was incomplete. Reload before retrying.'};
      }
      return {success:true};
    } catch {return {success:false,error:'Connection interrupted. Reload route configuration before retrying.'};}
  };
}
