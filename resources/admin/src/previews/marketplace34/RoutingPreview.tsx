import React,{useEffect,useState} from 'react';
import {StoreWarehouseBindingTab} from './components/marketplace/StoreWarehouseBindingTab';
import {StoreFulfillmentBindingModal} from './components/marketplace/StoreFulfillmentBindingModal';
import {routingSaver} from './routingApi';

async function pages(path:string,signal:AbortSignal) {
  const rows:any[]=[];let total:number|undefined;
  for(let page=1;page<=100;page++){
    const response=await fetch(`${path}?page=${page}`,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!response.ok)throw Error('Routing data unavailable. Reload the page.');
    const data=await response.json();
    if(!Array.isArray(data.data)||data.current_page!==page||!Number.isSafeInteger(data.total)||!Number.isSafeInteger(data.last_page)
      ||data.last_page<page||data.last_page>100||(total!==undefined&&total!==data.total))throw Error('Routing data changed. Reload.');
    total=data.total;rows.push(...data.data);if(page===data.last_page)break;
  }
  if(rows.length!==total||new Set(rows.map(r=>r.id)).size!==rows.length)throw Error('Routing data incomplete.');
  return rows;
}

export function RoutingPreview(){
  const [bindings,setBindings]=useState<any[]>([]),[warehouses,setWarehouses]=useState<any[]>([]);
  const [editing,setEditing]=useState<any>(null),[revision,setRevision]=useState(0),[status,setStatus]=useState('Loading routing configuration…');
  useEffect(()=>{
    const abort=new AbortController();
    Promise.all([pages('/admin/api/inventory/fulfillment-routes',abort.signal),pages('/admin/api/inventory/route-stores',abort.signal),pages('/admin/api/inventory/warehouses',abort.signal)])
      .then(([routes,stores,wh])=>{
        if(abort.signal.aborted)return;
        setWarehouses(wh.map(w=>({...w,id:String(w.id),address:{city:w.address||'',state:w.state||''}})));
        setBindings([{id:'website',platform:'webstore',shop_name:'JW Webstore'},...stores.map(s=>({...s,id:`marketplace:${s.id}`}))].map(s=>{
          const route=routes.find(r=>r.channel_key===s.id);
          return {id:s.id,storeIdentifier:s.id,platform:s.platform,storeName:s.shop_name,boundWarehouseId:route?String(route.warehouse_id):'',
            boundWarehouseName:'Not configured',platformLocationId:route?.platform_location_id,platformLocationName:route?.platform_location_name,
            fulfillmentPriority:route?.fulfillment_priority??1,priorityOrder:route?.fulfillment_priority??1,isSharedPoolActive:false,
            routeVersion:route?.version??null,routeEnabled:Boolean(route?.is_enabled)};
        }));setStatus('Local routing configuration. Inventory activation and platform location verification are separate.');
      }).catch(error=>{if(!abort.signal.aborted){setBindings([]);setStatus(error.message);}});
    return()=>abort.abort();
  },[revision]);
  return <main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 p-4 md:p-8">
    <p role="status">{status}</p>
    <StoreWarehouseBindingTab bindings={bindings} warehouses={warehouses} onOpenEditBindingModal={setEditing}/>
    {editing&&<StoreFulfillmentBindingModal key={editing.id} isOpen binding={editing} warehouses={warehouses} onClose={()=>setEditing(null)} onSave={async data=>{
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
      const result=await routingSaver(csrf,{channelKey:editing.id,version:editing.routeVersion,enabled:editing.routeEnabled})(data);
      if(result.success)setRevision(r=>r+1);return result;
    }}/>}
  </main>;
}
