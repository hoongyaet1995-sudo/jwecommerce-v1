import React,{useEffect,useState} from 'react';
import {useAdmin} from '../context/AdminContext';
import {marketplaceOperations,StoreConnection,ProviderCapabilities,MarketplacePlatform} from '../integration/marketplaceOperations';
import {marketplaceRequest} from '../integration/marketplaceData';
import {publishLazadaWithRecovery,publishShopeeWithRecovery} from '../integration/publishingReviewSession';
import {readAdminBootstrap} from '../integration/adminBootstrap';
import {catalogProductView} from '../integration/marketplaceCatalogView';
import {PreviewContext} from '../previews/marketplace30/context/ContextBridge';
import {MarketplaceMappingPage,MappingPageQuery} from '../previews/marketplace30/components/marketplace/MarketplaceMappingPage';
import {useMarketplaceCatalog} from './useMarketplaceCatalog';
import {useMarketplaceReadTask} from './useMarketplaceReadTask';
import {useMarketplaceImport} from './useMarketplaceImport';
import {ConnectedVariantModal} from '../previews/marketplace32/ConnectedVariantModal';
import {ConnectedImportReviewModal} from '../previews/marketplace32/ConnectedImportReviewModal';
import type {MarketplaceStoreProduct} from '../previews/marketplace32/types';
import {PublishProductModal} from '../previews/marketplace32/components/marketplace/PublishProductModal';
import {JourneyProvider,type MarketplaceJourneyContext} from '../previews/marketplace32/context/AdminContext';
import {PersistentCatalogTaskIndicator38} from '../previews/catalog-download38/PersistentCatalogTaskIndicator38';
import {CatalogTaskBanner} from '../previews/catalog-download38/CatalogTaskBanner';
import {CatalogDownload38Overlay,catalogTaskFor} from '../previews/catalog-download38/CatalogDownload38Overlay';
import {encodeInventoryIntake,INVENTORY_INTAKE_STORAGE_KEY} from './inventoryIntake';

export function OperationalMapping(){
 const [catalog38Request,setCatalog38Request]=useState(false);
 const [catalog38OpenRequest,setCatalog38OpenRequest]=useState(false);
 const [catalog38RestoredRunId,setCatalog38RestoredRunId]=useState<string|null>(null);
 const journeyPreview=typeof window!=='undefined'&&['127.0.0.1','localhost'].includes(window.location.hostname)
  &&new URLSearchParams(window.location.search).get('journeyPreview')==='32';
 const admin=useAdmin();
 const [reviewProducts,setReviewProducts]=useState<MarketplaceStoreProduct[]|null>(null);
 const [publishReview,setPublishReview]=useState<{storeId?:string}|null>(null);
 const [query,setQuery]=useState<MappingPageQuery>({platform:'all',storeId:'all',q:'',status:'unmapped',page:1,per_page:15,view:'grouped'});
 const catalog=useMarketplaceCatalog(query);
 const [stores,setStores]=useState<StoreConnection[]>([]);
 const [capabilities,setCapabilities]=useState<Partial<Record<MarketplacePlatform,ProviderCapabilities>>>({});
 const [connectionError,setConnectionError]=useState('');
 const [canImport,setCanImport]=useState(false);
 const importer=useMarketplaceImport(canImport,catalog.products.flatMap(product=>product.variants[0]?[{id:`${product.store_id}:${product.external_product_id}`,listingId:String(product.variants[0].id)}]:[]),catalog.reload);
 const importProducts=async(ids:string[])=>{
  if(canImport){
   const selected=catalog.products.map(product=>catalogProductView(product,stores.find(store=>store.id===String(product.store_id))?.shopId||''))
    .filter(product=>ids.includes(product.id));
   if(canImport&&selected.length===new Set(ids).size&&selected.length)setReviewProducts(selected);
   return [];
  }
  const results=await importer.run(ids);
  if(results.length){const succeeded=results.filter(result=>result.ok).length;
   admin.addToast({type:succeeded===results.length?'success':'error',title:'Product import',message:`${succeeded} imported, ${results.length-succeeded} failed. New products are drafts with zero stock. Review failures before retrying.`});
  }
  return results;
 };
 const task=useMarketplaceReadTask('catalog',catalog.reload);
 const [downloadStoreId,setDownloadStoreId]=useState<string|null>(null);
 const [checkingTask,setCheckingTask]=useState(false);
 const eligibleStores=stores.filter(store=>capabilities[store.platform]?.catalog===true);
 const downloadStore=stores.find(store=>store.id===downloadStoreId);
 const downloadTask=catalogTaskFor(downloadStore,task.run);
 const selectDownloadStore=(id:string)=>{if(id===downloadStoreId)return;task.reset();setCheckingTask(true);setDownloadStoreId(id);localStorage.setItem('jw.catalog-download.monitored-store',id);};
 const selectedStore=stores.find(store=>store.id===query.storeId);
 const canFetch=!!selectedStore&&selectedStore.status==='connected'&&capabilities[selectedStore.platform]?.catalog===true&&!task.busy;
 const canRequestFetch=eligibleStores.length>0;
 useEffect(()=>{let active=true;
  marketplaceOperations.connections().then(data=>{if(active){setStores(data.stores);setCapabilities(data.capabilities);setCanImport(data.localCapabilities?.importProducts===true);}})
   .catch(()=>{if(active)setConnectionError('Store connections could not be loaded. Reload this page to retry.');});
  return()=>{active=false;};
 },[]);
 useEffect(()=>{let active=true;
  if(!downloadStoreId)return()=>{active=false;};
  setCheckingTask(true);
  marketplaceOperations.latestCatalogRun(downloadStoreId).then(run=>{if(active&&run){task.resume(run);setCatalog38RestoredRunId(String(run.id));}}).catch(()=>{if(active)admin.addToast({type:'error',title:'Task status unavailable',message:'Check the store connection before starting a download.'});}).finally(()=>{if(active)setCheckingTask(false);});
  return()=>{active=false;};
 },[downloadStoreId]);
 useEffect(()=>{if(!stores.length||downloadStoreId)return;const saved=localStorage.getItem('jw.catalog-download.monitored-store');if(saved&&stores.some(store=>store.id===saved))setDownloadStoreId(saved);},[stores]);
 const unavailable=()=>{admin.addToast({type:'info',title:'Unavailable',message:'This operation is not enabled. No changes were made.'});return false;};
 const persist=async(listingId:string,method:'PUT'|'DELETE',data?:{productId:number;variantId:number})=>{
  try{await marketplaceRequest(`/listings/${encodeURIComponent(listingId)}/mapping`,method,data);catalog.reload();return true;}
  catch{admin.addToast({type:'error',title:'Mapping failed',message:'The mapping could not be saved. Refresh and check for an existing same-store mapping.'});return false;}
 };
 const fetchCatalog=async()=>{
  if(!eligibleStores.length)return;
  if(!downloadStore||!eligibleStores.some(store=>store.id===downloadStore.id)){
   selectDownloadStore((eligibleStores.find(store=>store.id===query.storeId)||eligibleStores[0]).id);
  }
  setCatalog38Request(true);
 };
 const value={...admin,products:admin.products.map(product=>({...product,images:product.images.map(image=>typeof image==='string'?image:image.path)})),connections:stores,marketplaceStoreProducts:catalog.products.map(product=>catalogProductView(product,stores.find(store=>store.id===String(product.store_id))?.shopId||'')),
  marketplaceOrders:[],marketplaceInventory:[],syncLogs:[],
  mapStoreVariantToJwSku:(_product:string,listing:string,product:string,variant:string)=>persist(listing,'PUT',{productId:Number(product),variantId:Number(variant)}),
  unmapStoreVariant:(_product:string,listing:string)=>persist(listing,'DELETE'),
  refreshStoreProductsFromChannel:fetchCatalog,resetStoreProductsToDefaults:unavailable,importStoreProductToMaster:unavailable,
  batchImportStoreProductsToMaster:unavailable,batchAcceptSuggestedMappings:unavailable,publishProductToStore:unavailable};
 const publishingContext:MarketplaceJourneyContext={
  products:value.products,categories:admin.categories,
  connections:stores.filter(store=>['shopee','lazada'].includes(store.platform)&&store.status==='connected') as MarketplaceJourneyContext['connections'],
  marketplaceStoreProducts:value.marketplaceStoreProducts,
  addToast:admin.addToast,setActiveSection:()=>catalog.reload(),importStoreProductToMaster:()=>{unavailable();},
  publishMasterProductToStore:unavailable,mapStoreVariantToJwSku:()=>{unavailable();},unmapStoreVariant:()=>{unavailable();},
 };
 return <PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
  <CatalogDownload38Overlay stores={eligibleStores} selectedStore={downloadStore} run={task.run} pending={checkingTask||task.submitting} requestFetch={catalog38Request} onRequestConsumed={()=>setCatalog38Request(false)} requestOpen={catalog38OpenRequest} onOpenRequestConsumed={()=>setCatalog38OpenRequest(false)} restoredRunId={catalog38RestoredRunId} onSelectStore={selectDownloadStore} onStart={async()=>{setCatalog38RestoredRunId(null);return await task.start(()=>marketplaceOperations.fetchCatalog(downloadStore!.id));}} onRetry={async()=>{if(task.runId){setCatalog38RestoredRunId(null);await task.start(()=>marketplaceOperations.retryCatalog(String(task.runId)));}}} onCheck={task.checkStatus}/>
  {(catalog.error||connectionError)&&<p role="alert" className="mb-4 text-sm text-rose-500">{catalog.error||connectionError}<button className="ml-3 underline" onClick={catalog.reload}>Retry catalog</button></p>}
  {catalog.loading&&<p role="status" className="mb-4 text-sm text-neutral-500">Loading store catalog…</p>}
  {(task.runId||task.error)&&<p role="status" className="mb-4 text-sm text-neutral-500">{task.error||`Catalog fetch task ${task.runId}: ${task.run?.status??'checking status'}${task.run?` · ${task.run.pages_completed} pages processed`:''}`} {task.error&&task.runId&&<button className="ml-3 underline" onClick={task.checkStatus}>Check status</button>} {task.run?.status==='failed'&&<button className="ml-3 underline" disabled={task.busy} onClick={()=>void task.start(()=>marketplaceOperations.retryCatalog(String(task.runId)))}>Retry task</button>}</p>}
  {journeyPreview&&<p role="status" className="mb-4 text-sm text-neutral-500">Local ZIP 32 acceptance — real catalog and saved changes. Official entry unchanged.</p>}
  {reviewProducts&&<ConnectedImportReviewModal productsToImport={reviewProducts} products={value.products}
    canImport={canImport} onClose={()=>setReviewProducts(null)} onImported={catalog.reload} addToast={admin.addToast}
    onInventoryManagement={intake=>{
      if(!intake.productIds.length){admin.addToast({type:'info',title:'Inventory Management',message:'No new JW product needs an initial physical count.'});return;}
      sessionStorage.setItem(INVENTORY_INTAKE_STORAGE_KEY,encodeInventoryIntake(intake));
      admin.setCurrentSection('marketplace-inventory');
    }}/>}
  {publishReview&&<JourneyProvider value={publishingContext}>
    <PublishProductModal isOpen liveData preselectedConnectionId={publishReview.storeId}
      submitReviewed={async(productId,storeId,options)=>{
       if(stores.find(store=>store.id===storeId)?.platform==='shopee'){
        if(options.condition!=='NEW'&&options.condition!=='USED')throw new Error('Review the Shopee product condition.');
        return publishShopeeWithRecovery(String(readAdminBootstrap().user.id??''),{productId:Number(productId),storeId:Number(storeId),
         recoveryAuthorizationId:options.recoveryAuthorizationId,
         sourceRevision:options.sourceRevision??'',categoryId:options.categoryName??'',brandId:options.shopeeBrandId??null,
         sizeChartImageId:options.sizeChartImageId,
         condition:options.condition,weightKg:options.packageWeightKg??0,lengthCm:options.packageLengthCm??0,widthCm:options.packageWidthCm??0,heightCm:options.packageHeightCm??0,
         variantImageChoices:options.shopeeVariantImageChoices,attributes:options.shopeeAttributes??[],logistics:options.shopeeLogistics??[]});
       }
       if(stores.find(store=>store.id===storeId)?.platform!=='lazada')throw new Error('This platform publishing adapter is not ready yet.');
       return publishLazadaWithRecovery(String(readAdminBootstrap().user.id??''),{productId:Number(productId),storeId:Number(storeId),
        recoveryAuthorizationId:options.recoveryAuthorizationId,
        sourceRevision:options.sourceRevision??'',categoryId:options.categoryName??'',
        sizeChartImageId:options.sizeChartImageId,
        attributeImageIds:options.lazadaAttributeImageIds,
        attributes:options.lazadaAttributes??{},skuAttributes:options.lazadaSkuAttributes??{}});
      }}
      onClose={()=>{setPublishReview(null);catalog.reload();}}/>
  </JourneyProvider>}
  <MarketplaceMappingPage renderVariantModal={(selection,onClose)=><React.Fragment key={`${selection.storeVariant.id}:${selection.intent ?? 'link'}`}><ConnectedVariantModal
    isOpen canImport={canImport} startWithImport={selection.intent==='import'} storeProduct={selection.storeProduct} variantId={selection.storeVariant.id}
    products={value.products} categories={admin.categories} onClose={onClose} onImported={catalog.reload} addToast={admin.addToast}/></React.Fragment>}
    serverPage={{total:catalog.total,summary:catalog.summary,loading:catalog.loading,onQueryChange:setQuery,canFetch,canRequestFetch,canImport,importing:importer.busy,onImport:importProducts,catalogTaskIndicator:downloadTask?<PersistentCatalogTaskIndicator38 task={downloadTask} onOpen={()=>setCatalog38OpenRequest(true)}/>:null,catalogTaskBanner:downloadTask&&['queued','preparing','downloading'].includes(downloadTask.status)?<CatalogTaskBanner task={downloadTask} onOpen={()=>setCatalog38OpenRequest(true)}/>:null,
      onReviewPublishing:storeId=>setPublishReview({storeId})}}/>
 </PreviewContext.Provider>;
}
