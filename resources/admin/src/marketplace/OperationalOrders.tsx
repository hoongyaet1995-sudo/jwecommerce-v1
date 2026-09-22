import React,{useCallback,useEffect,useState} from 'react';
import {useAdmin} from '../context/AdminContext';
import {marketplaceOperations,OrderSummary,MarketplaceOrderDetail,MarketplacePlatform,requestMarketplaceAuthorization,StoreConnection,SyncRun} from '../integration/marketplaceOperations';
import {PreviewContext} from '../previews/marketplace30/context/ContextBridge';
import {MarketplaceOMSPage} from '../previews/marketplace30/components/marketplace/MarketplaceOMSPage';
import {OrderInspectionDrawer} from '../previews/marketplace30/OrderInspectionDrawer';
import {FetchMarketplaceOrdersModal} from './orders/FetchMarketplaceOrdersModal';

export function OperationalOrders(){
 const admin=useAdmin();
 const [query,setQuery]=useState({platform:'all',status:'all',q:''});
 const [page,setPage]=useState(1);const [revision,setRevision]=useState(0);
 const [storeId,setStoreId]=useState('all');
 const [state,setState]=useState({orders:[] as OrderSummary[],summary:{total:0,statuses:{} as Record<string,number>},lastPage:1,loading:true,error:''});
 const [stores,setStores]=useState<StoreConnection[]>([]);
 const [orderPlatforms,setOrderPlatforms]=useState<string[]>([]);
 const [connectionError,setConnectionError]=useState('');
 const [capabilities,setCapabilities]=useState<Record<string,any>>({});
 const [runs,setRuns]=useState<Record<string,SyncRun|null>>({});const [recoveryOpen,setRecoveryOpen]=useState(false);const [recoveryPhase,setRecoveryPhase]=useState<'selection'|'execution'>('selection');
 const [recoveryErrors,setRecoveryErrors]=useState<Record<string,string>>({});
 const [busyStores,setBusyStores]=useState<Set<string>>(new Set());const [refreshing,setRefreshing]=useState(false);
 const canFetch=stores.some(store=>store.status==='connected'&&orderPlatforms.includes(store.platform));
 const [selected,setSelected]=useState<string|null>(null);
 const [detail,setDetail]=useState<MarketplaceOrderDetail|null>(null);const [detailError,setDetailError]=useState('');
 const onQuery=useCallback((value:typeof query)=>{setQuery(value);setPage(1);},[]);
 const refreshRuns=useCallback(async()=>{if(!stores.length)return;setRefreshing(true);try{const values=await Promise.all(stores.filter(s=>s.platform==='shopee'||s.platform==='lazada').map(async store=>[store.id,await marketplaceOperations.latestOrderRun(store.id)] as const));setRuns(previous=>({...previous,...Object.fromEntries(values)}));}catch{/* Keep the last confirmed statuses visible; the operator can refresh again. */}finally{setRefreshing(false);}},[stores]);
 useEffect(()=>{let active=true;marketplaceOperations.connections().then(data=>{if(active){setStores(data.stores);setCapabilities(data.capabilities);setOrderPlatforms(Object.entries(data.capabilities).filter(([,value])=>value.orders).map(([platform])=>platform));}}).catch(()=>{if(active)setConnectionError('Store connections could not be loaded. Reload this page to retry.');});return()=>{active=false;};},[]);
 useEffect(()=>{if(stores.length)void refreshRuns();},[stores,refreshRuns]);
 useEffect(()=>{if(!Object.values(runs).some(run=>run&&['queued','running'].includes(run.status)))return;const timer=setTimeout(()=>void refreshRuns(),2000);return()=>clearTimeout(timer);},[runs,refreshRuns]);
 const startRecovery=async(ids:string[],from:number,to:number)=>{setBusyStores(new Set(ids));setRecoveryErrors(previous=>{const next={...previous};ids.forEach(id=>delete next[id]);return next;});try{const created=await Promise.allSettled(ids.map(async id=>{const result=await marketplaceOperations.fetchOrders(id,from,to);return[id,await marketplaceOperations.orderRun(String(result.runId))] as const;}));const saved:Record<string,SyncRun>={};const errors:Record<string,string>={};created.forEach((result,index)=>{if(result.status==='fulfilled')saved[result.value[0]]=result.value[1];else errors[ids[index]]='JW Ecommerce could not start the recovery task.';});setRuns(previous=>({...previous,...saved}));setRecoveryErrors(previous=>({...previous,...errors}));}finally{setBusyStores(new Set());}};
 const retryRecovery=async(run:SyncRun)=>{const id=String(run.store_id);setBusyStores(current=>new Set(current).add(id));setRecoveryErrors(previous=>({...previous,[id]:''}));try{const result=await marketplaceOperations.retryOrders(String(run.id));const refreshed=await marketplaceOperations.orderRun(String(result.runId));setRuns(previous=>({...previous,[id]:refreshed}));}catch{setRecoveryErrors(previous=>({...previous,[id]:'JW Ecommerce could not restart the recovery task.'}));}finally{setBusyStores(current=>{const next=new Set(current);next.delete(id);return next;});}};
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
  <MarketplaceOMSPage operational serverData={{orders:state.orders,summary:state.summary,onSelect:setSelected,onQuery,stores,storeId,onFetch:()=>{setRecoveryPhase('selection');setRecoveryOpen(true);},onRecoveryStatus:()=>{setRecoveryPhase('execution');setRecoveryOpen(true);},canFetch,recoverySummary:{running:Object.values(runs).filter(run=>run&&['queued','running'].includes(run.status)).length,failed:Object.values(runs).filter(run=>run?.status==='failed').length},onStore:id=>{setStoreId(id);setPage(1);setSelected(null);}}}/>
  <div className="flex items-center gap-2 mt-4">
   <button className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30" onClick={()=>setRevision(value=>value+1)} disabled={state.loading}>Reload</button>
   <button className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30" onClick={()=>setPage(value=>value-1)} disabled={state.loading||page<=1}>Previous</button>
   <span className="text-xs font-medium text-neutral-600 dark:text-neutral-300">Page {page} of {state.lastPage}</span>
   <button className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 disabled:opacity-30" onClick={()=>setPage(value=>value+1)} disabled={state.loading||page>=state.lastPage}>Next</button>
  </div>
  {selected&&!detail&&<div role={detailError?'alert':'status'} className="p-6 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">{detailError||'Loading order details…'}<button className="ml-3 underline" onClick={()=>setSelected(null)}>Close</button></div>}
  {detail&&<OrderInspectionDrawer order={detail} onClose={()=>setSelected(null)}/>}
  {recoveryOpen&&<FetchMarketplaceOrdersModal open initialPhase={recoveryPhase} onClose={()=>setRecoveryOpen(false)} stores={stores} capabilities={capabilities as any} runs={runs} submissionErrors={recoveryErrors} busyStores={busyStores} refreshing={refreshing} onRefresh={()=>void refreshRuns()} onStart={(ids,from,to)=>void startRecovery(ids,from,to)} onRetry={run=>void retryRecovery(run)} onReconnect={store=>void requestMarketplaceAuthorization(store.platform,store.id)}/>}
 </PreviewContext.Provider>;
}
