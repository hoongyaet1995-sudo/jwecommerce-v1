import {useEffect,useState} from 'react';
import {warehouseSaver} from '../marketplace34/warehouseApi';
import {serializedFetch} from '../marketplace33/serializedFetch';

export function useWarehouses(enabled:boolean,onError:(message:string)=>void) {
 const [rows,setRows]=useState<any[]>([]);
 const [revision,setRevision]=useState(0);
 useEffect(()=>{
  if(!enabled)return;
  const abort=new AbortController();
  async function load() {
   const all:any[]=[];let total:number|undefined;
   for(let page=1;page<=100;page++) {
    const response=await serializedFetch(`/admin/api/inventory/warehouses?page=${page}`,{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!response.ok)throw new Error('Warehouse list unavailable.');
    const body=await response.json();
    if(!Array.isArray(body.data)||body.current_page!==page||!Number.isSafeInteger(body.total)||body.total<0
      ||!Number.isSafeInteger(body.last_page)||body.last_page<page||body.last_page>100
      ||(total!==undefined&&total!==body.total))throw new Error('Warehouse list changed. Reload.');
    total=body.total;all.push(...body.data);
    if(page===body.last_page)break;
   }
   if(all.length!==total||new Set(all.map(row=>row.id)).size!==all.length)throw new Error('Incomplete warehouse list.');
   if(!abort.signal.aborted)setRows(all.map(row=>({...row,id:String(row.id),isDefaultMaster:row.is_default_master,
     dataClassification:row.data_classification??'unclassified',
     isActive:row.is_active,contactPerson:row.contact_person,contactPhone:row.contact_phone,supportedCarriers:row.supported_carriers})));
  }
  load().catch(error=>{if(!abort.signal.aborted)onError(error.message);});
  return()=>abort.abort();
 },[enabled,revision]);
 return {entityWarehouses:rows,saveEntityWarehouse:async(data:any)=>{
   const existing=data.id?rows.find(row=>row.id===data.id):null;
   if(data.id&&!existing)return {success:false,error:'Warehouse changed. Reload.'};
   const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
   const result=await warehouseSaver(csrf,existing?{id:existing.id,version:existing.version}:null)(data);
   if(result.success)setRevision(value=>value+1);
   return result;
 }};
}

