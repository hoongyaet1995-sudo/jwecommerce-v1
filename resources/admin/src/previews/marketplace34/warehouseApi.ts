import type {EntityWarehouse} from './types';

export function warehouseSaver(csrf:string, existing:{id:string;version:number}|null, request:typeof fetch=fetch) {
  return async (data:Partial<EntityWarehouse>):Promise<{success:boolean;error?:string}>=>{
    if(!csrf) return {success:false,error:'Session unavailable. Reload before saving.'};
    const payload={code:data.code,name:data.name,status:data.status,is_default_master:data.isDefaultMaster,
      address:data.address,state:data.state,contact_person:data.contactPerson,contact_phone:data.contactPhone,
      ...(existing?{version:existing.version}:{})};
    try {
      const response=await request('/admin/api/inventory/warehouses'+(existing?`/${encodeURIComponent(existing.id)}`:''),
        {method:existing?'PUT':'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
      const result=await response.json().catch(()=>null);
      if(!response.ok) return {success:false,error:result?.errors?.warehouse?.[0]||result?.message||'Warehouse save failed. Reload before retrying.'};
      if(!Number.isSafeInteger(result?.id)||!Number.isSafeInteger(result?.version)
        ||(existing && String(result.id)!==existing.id))return {success:false,error:'Save receipt was incomplete. Reload the warehouse list before retrying.'};
      return {success:true};
    } catch {return {success:false,error:'Connection interrupted. Reload the warehouse list before retrying.'};}
  };
}
