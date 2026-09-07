import React,{useCallback,useEffect,useState} from 'react';
import {useAdmin} from '../context/AdminContext';
import {marketplaceOperations,OrderSummary,MarketplaceOrderDetail,MarketplacePlatform,StoreConnection} from '../integration/marketplaceOperations';
import {PreviewContext} from '../previews/marketplace30/context/ContextBridge';
import {MarketplaceOMSPage} from '../previews/marketplace30/components/marketplace/MarketplaceOMSPage';
import {OrderInspectionDrawer} from '../previews/marketplace30/OrderInspectionDrawer';
import {useMarketplaceReadTask} from './useMarketplaceReadTask';

export function OperationalOrders(){
 const admin=useAdmin();
 const [query,setQuery]=useState({platform:'all',status:'all',q:''});
 const [page,setPage]=useState(1);const [revision,setRevision]=useState(0);
 const [storeId,setStoreId]=useState('all');
 const [state,setState]=useState({orders:[] as OrderSummary[],summary:{total:0,statuses:{} as Record<string,number>},lastPage:1,loading:true,error:''});
 const [stores,setStores]=useState<StoreConnection[]>([]);
 const [orderPlatforms,setOrderPlatforms]=useState<string[]>([]);
 const [connectionError,setConnectionError]=useState('');
 const task=useMarketplaceReadTask('orders',()=>setRevision(value=>value+1));
 const selectedStore=stores.find(store=>store.id===storeId);
 const canFetch=!!selectedStore&&selectedStore.status==='connected'&&orderPlatforms.includes(selectedStore.platform)&&!task.busy;
 const fetchOrders=()=>{if(!canFetch)return;const to=Math.floor(Date.now()/1000);void task.start(()=>marketplaceOperations.fetchOrders(storeId,to-30*86400,to));};
 const [selected,setSelected]=useState<string|null>(null);
 const [detail,setDetail]=useState<MarketplaceOrderDetail|null>(null);const [detailError,setDetailError]=useState('');
 const onQuery=useCallback((value:typeof query)=>{setQuery(value);setPage(1);},[]);
 useEffect(()=>{let active=true;marketplaceOperations.connections().then(data=>{if(active){setStores(data.stores);setOrderPlatforms(Object.entries(data.capabilities).filter(([,value])=>value.orders).map(([platform])=>platform));}}).catch(()=>{if(active)setConnectionError('Store connections could not be loaded. Reload this page to retry.');});return()=>{active=false;};},[]);
 useEffect(()=>{let active=true;setState(previous=>({...previous,orders:[],loading:true,error:''}));
  const filters={storeId:storeId==='all'?undefined:storeId,platform:query.platform==='all'?undefined:query.platform as MarketplacePlatform,status:query.status==='all'?undefined:query.status,q:query.q,page,per_page:15};
  Promise.all([marketplaceOperations.orders(filters),marketplaceOperations.orderSummary(filters)]).then(([result,summary])=>{
   if(active)setState({orders:result.data,summary,lastPage:result.last_page,loading:false,error:''});
  }).catch(()=>{if(active)setState(previous=>({...previous,orders:[],loading:false,error:'Orders could not be loaded. Please retry.'}));});
  return()=>{active=false;};
 },[storeId,query.platform,query.status,query.q,page,revision]);
 useEffect(()=>{let active=true;setDetail(null);setDetailError('');if(!selected)return;
  marketplaceOperations.orderDetail(selected,{includeRecipient:true}).then(result=>{if(active)setDetail(result.data);})
   .catch(()=>{if(active)setDetailError('Order details could not be loaded. Close and retry.');});
  return()=>{active=false;};
 },[selected]);
 const value={...admin,connections:stores,marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],selectedMarketplaceOrderId:null};
 return <PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
  {connectionError&&<p role="alert" className="mb-4 text-sm text-rose-500">{connectionError}</p>}
  {(state.loading||state.error)&&<p role={state.error?'alert':'status'} className="mb-4 text-sm text-neutral-500">{state.error||'Loading Marketplace orders…'}</p>}
  {(task.runId||task.error)&&<p role="status" className="mb-4 text-sm text-neutral-500">{task.error||`Order fetch task ${task.runId}: ${task.run?.status??'checking status'}${task.run?` · ${task.run.pages_completed} pages processed`:''}`} {task.error&&task.runId&&<button className="ml-3 underline" onClick={task.checkStatus}>Check status</button>} {task.run?.status==='failed'&&<button className="ml-3 underline" disabled={task.busy} onClick={()=>void task.start(()=>marketplaceOperations.retryOrders(String(task.runId)))}>Retry task</button>}</p>}
  <MarketplaceOMSPage operational serverData={{orders:state.orders,summary:state.summary,onSelect:setSelected,onQuery,stores,storeId,onFetch:fetchOrders,canFetch,onStore:id=>{setStoreId(id);setPage(1);setSelected(null);}}}/>
  <div className="flex items-center gap-2 mt-4">
   <button className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30" onClick={()=>setRevision(value=>value+1)} disabled={state.loading}>Reload</button>
   <button className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30" onClick={()=>setPage(value=>value-1)} disabled={state.loading||page<=1}>Previous</button>
   <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Page {page} of {state.lastPage}</span>
   <button className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30" onClick={()=>setPage(value=>value+1)} disabled={state.loading||page>=state.lastPage}>Next</button>
  </div>
  {selected&&!detail&&<div role={detailError?'alert':'status'} className="p-6 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">{detailError||'Loading order details…'}<button className="ml-3 underline" onClick={()=>setSelected(null)}>Close</button></div>}
  {detail&&<OrderInspectionDrawer order={detail} onClose={()=>setSelected(null)}/>}
 </PreviewContext.Provider>;
}
