import React,{useEffect,useState} from 'react';
import {useAdmin} from '../context/AdminContext';
import {marketplaceOperations,StoreConnection,ProviderCapabilities,MarketplacePlatform} from '../integration/marketplaceOperations';
import {marketplaceRequest} from '../integration/marketplaceData';
import {catalogProductView} from '../integration/marketplaceCatalogView';
import {PreviewContext} from '../previews/marketplace30/context/ContextBridge';
import {MarketplaceMappingPage,MappingPageQuery} from '../previews/marketplace30/components/marketplace/MarketplaceMappingPage';
import {useMarketplaceCatalog} from './useMarketplaceCatalog';
import {useMarketplaceReadTask} from './useMarketplaceReadTask';
import {useMarketplaceImport} from './useMarketplaceImport';

export function OperationalMapping(){
 const admin=useAdmin();
 const [query,setQuery]=useState<MappingPageQuery>({platform:'all',storeId:'all',q:'',status:'unmapped',page:1,per_page:15,view:'grouped'});
 const catalog=useMarketplaceCatalog(query);
 const [stores,setStores]=useState<StoreConnection[]>([]);
 const [capabilities,setCapabilities]=useState<Partial<Record<MarketplacePlatform,ProviderCapabilities>>>({});
 const [connectionError,setConnectionError]=useState('');
 const [canImport,setCanImport]=useState(false);
 const importer=useMarketplaceImport(canImport,catalog.products.flatMap(product=>product.variants[0]?[{id:`${product.store_id}:${product.external_product_id}`,listingId:String(product.variants[0].id)}]:[]),catalog.reload);
 const importProducts=async(ids:string[])=>{
  const results=await importer.run(ids);
  if(results.length){const succeeded=results.filter(result=>result.ok).length;
   admin.addToast({type:succeeded===results.length?'success':'error',title:'Product import',message:`${succeeded} imported, ${results.length-succeeded} failed. New products are drafts with zero stock. Review failures before retrying.`});
  }
  return results;
 };
 const task=useMarketplaceReadTask('catalog',catalog.reload);
 const selectedStore=stores.find(store=>store.id===query.storeId);
 const canFetch=!!selectedStore&&selectedStore.status==='connected'&&capabilities[selectedStore.platform]?.catalog===true&&!task.busy;
 useEffect(()=>{let active=true;
  marketplaceOperations.connections().then(data=>{if(active){setStores(data.stores);setCapabilities(data.capabilities);setCanImport(data.localCapabilities?.importProducts===true);}})
   .catch(()=>{if(active)setConnectionError('Store connections could not be loaded. Reload this page to retry.');});
  return()=>{active=false;};
 },[]);
 const unavailable=()=>{admin.addToast({type:'info',title:'Unavailable',message:'This operation is not enabled. No changes were made.'});return false;};
 const persist=async(listingId:string,method:'PUT'|'DELETE',data?:{productId:number;variantId:number})=>{
  try{await marketplaceRequest(`/listings/${encodeURIComponent(listingId)}/mapping`,method,data);catalog.reload();return true;}
  catch{admin.addToast({type:'error',title:'Mapping failed',message:'The mapping could not be saved. Refresh and check for an existing same-store mapping.'});return false;}
 };
 const fetchCatalog=async()=>{
  const store=stores.find(item=>item.id===query.storeId);
  if(!store||store.status!=='connected'||!capabilities[store.platform]?.catalog){
   admin.addToast({type:'info',title:'Select a store',message:'Select one connected store with catalog access before fetching.'});return;
  }
  await task.start(()=>marketplaceOperations.fetchCatalog(store.id));
 };
 const value={...admin,products:admin.products.map(product=>({...product,images:product.images.map(image=>typeof image==='string'?image:image.path)})),connections:stores,marketplaceStoreProducts:catalog.products.map(product=>catalogProductView(product,stores.find(store=>store.id===String(product.store_id))?.shopId||'')),
  marketplaceOrders:[],marketplaceInventory:[],syncLogs:[],
  mapStoreVariantToJwSku:(_product:string,listing:string,product:string,variant:string)=>persist(listing,'PUT',{productId:Number(product),variantId:Number(variant)}),
  unmapStoreVariant:(_product:string,listing:string)=>persist(listing,'DELETE'),
  refreshStoreProductsFromChannel:fetchCatalog,resetStoreProductsToDefaults:unavailable,importStoreProductToMaster:unavailable,
  batchImportStoreProductsToMaster:unavailable,batchAcceptSuggestedMappings:unavailable,publishProductToStore:unavailable};
 return <PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
  {(catalog.error||connectionError)&&<p role="alert" className="mb-4 text-sm text-rose-500">{catalog.error||connectionError}<button className="ml-3 underline" onClick={catalog.reload}>Retry catalog</button></p>}
  {catalog.loading&&<p role="status" className="mb-4 text-sm text-neutral-500">Loading store catalog…</p>}
  {(task.runId||task.error)&&<p role="status" className="mb-4 text-sm text-neutral-500">{task.error||`Catalog fetch task ${task.runId}: ${task.run?.status??'checking status'}${task.run?` · ${task.run.pages_completed} pages processed`:''}`} {task.error&&task.runId&&<button className="ml-3 underline" onClick={task.checkStatus}>Check status</button>} {task.run?.status==='failed'&&<button className="ml-3 underline" disabled={task.busy} onClick={()=>void task.start(()=>marketplaceOperations.retryCatalog(String(task.runId)))}>Retry task</button>}</p>}
  <MarketplaceMappingPage serverPage={{total:catalog.total,summary:catalog.summary,loading:catalog.loading,onQueryChange:setQuery,canFetch,canImport,importing:importer.busy,onImport:importProducts}}/>
 </PreviewContext.Provider>;
}
