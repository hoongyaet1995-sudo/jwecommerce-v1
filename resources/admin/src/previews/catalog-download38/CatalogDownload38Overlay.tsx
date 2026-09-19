import React,{useEffect,useMemo,useState} from 'react';
import type {StoreConnection,SyncRun} from '../../integration/marketplaceOperations';
import {FetchStoreProductsConfirmModal38} from './FetchStoreProductsConfirmModal38';
import {CatalogDownloadDrawer38} from './CatalogDownloadDrawer38';
import {PersistentCatalogTaskIndicator38} from './PersistentCatalogTaskIndicator38';
import type {CatalogDownloadTask38} from './catalogDownload38';

export function catalogTaskFor(store:StoreConnection|undefined,run:SyncRun|null,restored=false):CatalogDownloadTask38|null {
 if(!store||!run||!Number.isSafeInteger(run.id)||String(run.store_id)!==store.id)return null;
 const status=run.status==='queued'?'queued':run.status==='running'?(run.total_products?'downloading':'preparing'):run.status==='succeeded'?'completed':'failed';
 const started=run.created_at||run.updated_at||'';
 return {id:String(run.id),connectionId:store.id,platform:store.platform,shopName:store.shopName,shopId:store.shopId||'',region:store.region==='MY'?'Malaysia (MYR)':store.region,accountType:store.accountMode==='test'?'Test account':'Production account',status,statusMessage:run.error_code||'Saved server task status.',downloadedProducts:run.items_processed||0,totalProducts:run.total_products??null,variantsCaptured:run.variants_processed||0,imagesCaptured:null,currentPage:run.pages_completed||0,totalPages:null,startedAt:started,updatedAt:run.updated_at||started,elapsedSeconds:started?Math.max(0,Math.round((Date.parse(run.finished_at||new Date().toISOString())-Date.parse(started))/1000)):0,unmappedVariantsCount:null,workerId:'server-side job',isRestoredFromSavedProgress:restored,errorDetails:status==='failed'?{reason:run.error_code||'The task stopped before completion.',technicalCode:run.error_code||undefined,recoverable:true,preservedProductsCount:run.items_processed||0}:undefined};
}
type Props={stores:StoreConnection[];selectedStore?:StoreConnection;run:SyncRun|null;onStart:()=>Promise<boolean|void>;onRetry:()=>Promise<void>;onCheck:()=>void;requestFetch:boolean;onRequestConsumed:()=>void;requestOpen?:boolean;onOpenRequestConsumed?:()=>void;restoredRunId?:string|null;onSelectStore:(id:string)=>void;pending?:boolean};
export function CatalogDownload38Overlay({stores,selectedStore,run,onStart,onRetry,onCheck,requestFetch,onRequestConsumed,requestOpen=false,onOpenRequestConsumed,restoredRunId,onSelectStore,pending=false}:Props){
 const [confirm,setConfirm]=useState(false),[drawer,setDrawer]=useState(false),[submitting,setSubmitting]=useState(false);
 const task=useMemo(()=>catalogTaskFor(selectedStore,run,restoredRunId===String(run?.id)),[selectedStore,run,restoredRunId]);
 const openProgress=()=>{setConfirm(false);setDrawer(true);};
 useEffect(()=>{if(requestFetch){setConfirm(true);onRequestConsumed();}},[requestFetch]);
 useEffect(()=>{if(requestOpen){openProgress();onOpenRequestConsumed?.();}},[requestOpen]);
 const start=async()=>{
  if(submitting||pending)return;
  setSubmitting(true);
  try{if(await onStart()!==false){setConfirm(false);setDrawer(true);}}
  finally{setSubmitting(false);}
 };
 return <>
  <FetchStoreProductsConfirmModal38 open={confirm} connections={stores} connection={selectedStore||null} pending={pending||submitting} unavailableReason={selectedStore?.status!=='connected'?'Reconnect this store to start':undefined}
   existingTask={task&&['queued','preparing','downloading'].includes(task.status)?{downloadedProducts:task.downloadedProducts}:undefined}
   onViewProgress={openProgress} onClose={()=>setConfirm(false)} onConfirmStart={()=>void start()} onSelectAlternativeConnection={id=>{if(!submitting)onSelectStore(id);}}/>
  {task&&!drawer&&<PersistentCatalogTaskIndicator38 task={task} variant="floating" onOpen={openProgress}/>}
  <CatalogDownloadDrawer38 open={drawer} minimized={false} task={task} onClose={()=>setDrawer(false)} onMinimize={()=>setDrawer(false)} onOpen={openProgress}
   onResume={()=>void onRetry()} onCheckConnection={onCheck} onNavigateToMapping={()=>setDrawer(false)} onNavigateToUnmapped={()=>setDrawer(false)}/>
 </>;
}
