import React,{useEffect,useState} from 'react';
import {WarehousePanel} from './WarehousePanel';
import {EntityWarehouseModal} from './components/marketplace/EntityWarehouseModal';
import {warehouseSaver} from './warehouseApi';

export function WarehousePreview() {
  const [rows,setRows]=useState<any[]>([]);
  const [status,setStatus]=useState('Loading warehouses…');
  const [editing,setEditing]=useState<any>(undefined);
  const [revision,setRevision]=useState(0);
  useEffect(()=>{
    const abort=new AbortController();
    setStatus('Loading warehouses…');
    async function load() {
      const all:any[]=[];let total:number|undefined;
      for(let page=1;page<=100;page++) {
        const response=await fetch(`/admin/api/inventory/warehouses?page=${page}`,{signal:abort.signal,credentials:'same-origin',headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error('Warehouse list unavailable. Reload the page.');
        const result=await response.json();
        if(!Array.isArray(result.data)||result.current_page!==page||!Number.isSafeInteger(result.total)
          ||!Number.isSafeInteger(result.last_page)||result.last_page<page||result.last_page>100
          ||(total!==undefined&&total!==result.total))throw new Error('Warehouse list changed. Reload the page.');
        total=result.total;all.push(...result.data);
        if(page===result.last_page)break;
      }
      if(all.length!==total||new Set(all.map(row=>row.id)).size!==all.length)throw new Error('Warehouse list was incomplete.');
      if(abort.signal.aborted)return;
      setRows(all.map(row=>({...row,id:String(row.id),isDefaultMaster:row.is_default_master,isActive:row.is_active,
        contactPerson:row.contact_person,contactPhone:row.contact_phone,supportedCarriers:row.supported_carriers})));
      setStatus(all.length?`${all.length} Facilities`:'No warehouses configured');
    }
    load().catch(error=>{if(!abort.signal.aborted){setRows([]);setStatus(error.message);}});
    return()=>abort.abort();
  },[revision]);
  return <main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 p-4 md:p-8">
    <WarehousePanel entityWarehouses={rows} statusMessage={status} onOpenAddWarehouseModal={()=>setEditing(null)} onOpenEditWarehouseModal={setEditing}/>
    {editing!==undefined && <EntityWarehouseModal key={editing?.id??'new'} isOpen warehouse={editing} onClose={()=>setEditing(undefined)}
      onSave={async data=>{
        const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
        const result=await warehouseSaver(csrf,editing?{id:editing.id,version:editing.version}:null)(data);
        if(result.success)setRevision(value=>value+1);
        return result;
      }}/>}
  </main>;
}
