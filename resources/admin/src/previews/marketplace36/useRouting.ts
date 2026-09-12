import {useEffect,useState} from 'react';
import {pages} from '../marketplace34/RoutingPreview';
import {routingSaver} from '../marketplace34/routingApi';

export function useRouting(enabled:boolean,warehouses:any[],onError:(message:string)=>void) {
 const [routes,setRoutes]=useState<any[]>([]),[stores,setStores]=useState<any[]>([]),[revision,setRevision]=useState(0);
 useEffect(()=>{
  if(!enabled)return;
  const abort=new AbortController();
  Promise.all([pages('/admin/api/inventory/fulfillment-routes',abort.signal),pages('/admin/api/inventory/route-stores',abort.signal)])
   .then(([nextRoutes,nextStores])=>{if(!abort.signal.aborted){setRoutes(nextRoutes);setStores(nextStores);}})
   .catch(error=>{if(!abort.signal.aborted)onError(error.message);});
  return()=>abort.abort();
 },[enabled,revision]);
 const bindings=[{id:'website',platform:'webstore',shop_name:'JW Webstore'},...stores.map(store=>({...store,id:`marketplace:${store.id}`}))]
  .map(store=>{
   const route=routes.find(row=>row.channel_key===store.id);
   const warehouse=warehouses.find(row=>String(row.id)===String(route?.warehouse_id));
   return {id:store.id,storeIdentifier:store.id==='website'?'webstore':store.id.slice('marketplace:'.length),
    platform:store.platform,storeName:store.shop_name,dataClassification:store.id==='website'?'operational':store.data_classification??'unclassified',boundWarehouseId:route?String(route.warehouse_id):'',
    boundWarehouseName:warehouse?.name??'Not configured',boundWarehouseCode:warehouse?.code,
    platformLocationId:route?.platform_location_id,platformLocationName:route?.platform_location_name,
    fulfillmentPriority:route?.fulfillment_priority??1,priorityOrder:route?.fulfillment_priority??1,
    routeVersion:route?.version??null,routeEnabled:Boolean(route?.is_enabled),isSharedPoolActive:false};
  });
 return {storeFulfillmentBindings:bindings,saveStoreFulfillmentBinding:async(data:any)=>{
  const identity=bindings.find(row=>row.id===data.id);
  if(!identity)return {success:false,error:'Store route changed. Reload.'};
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
  const result=await routingSaver(csrf,{channelKey:identity.id,version:identity.routeVersion,enabled:identity.routeEnabled})(data);
  if(result.success)setRevision(value=>value+1);
  return result;
 }};
}
