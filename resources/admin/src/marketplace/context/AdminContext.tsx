import React,{createContext,useContext,useEffect,useState} from 'react';
import {useAdmin as useOperationalAdmin} from '../../context/AdminContext';
import {marketplaceRequest} from '../../integration/marketplaceData';
import type {MarketplaceConnection,MarketplaceInventoryItem,MarketplaceOrder,MarketplaceStoreProduct,MarketplaceSyncLog,Product,Category,NavSection} from '../types';

function useRuntime(){
  const admin=useOperationalAdmin();
  const [connections,setConnections]=useState<MarketplaceConnection[]>([]);
  const [selectedMarketplaceOrderId,setSelectedMarketplaceOrderId]=useState<string|null>(null);
  const [loading,setLoading]=useState(true);
  const [error,setError]=useState('');
  const [marketplaceStoreProducts,setListings]=useState<MarketplaceStoreProduct[]>([]);
  const load=async()=>{
    setLoading(true);setError('');
    try {
      const data=await marketplaceRequest<{stores:MarketplaceConnection[];products:MarketplaceStoreProduct[]}>();
      setConnections(data.stores);setListings(data.products);
    } catch(e){setError(e instanceof Error?e.message:'Marketplace data could not be loaded.');}
    finally {setLoading(false);}
  };
  useEffect(()=>{void load();},[]);
  const unavailable=(..._args:unknown[])=>admin.addToast({type:'info',title:'Open API not connected',message:'This operation is not available yet. No remote changes were made.'});
  const saveConnection=async(connection:MarketplaceConnection)=>{
    try {
      const persisted=/^\d+$/.test(connection.id);
      await marketplaceRequest(`/stores${persisted?'/'+connection.id:''}`,persisted?'PATCH':'POST',{
        platform:connection.platform,shopName:connection.shopName,region:connection.region,environment:connection.environment||'sandbox',
      });
      await load();admin.addToast({type:'success',title:'Store configuration saved',message:'Store details were saved. Open API authorization is still required.'});return true;
    }catch(e){admin.addToast({type:'error',title:'Save failed',message:e instanceof Error?e.message:'Could not save store.'});return false;}
  };
  const deleteConnection=async(id:string)=>{
    try{await marketplaceRequest('/stores/'+encodeURIComponent(id),'DELETE');await load();}
    catch(e){admin.addToast({type:'error',title:'Unable to remove store',message:e instanceof Error?e.message:'Request failed.'});}
  };
  const mapStoreVariantToJwSku=async(_productId:string,listingId:string,productId:string,variantId:string)=>{
    try{await marketplaceRequest('/listings/'+encodeURIComponent(listingId)+'/mapping','PUT',{productId:Number(productId),variantId:Number(variantId)});await load();return true;}
    catch(e){admin.addToast({type:'error',title:'Mapping failed',message:e instanceof Error?e.message:'Request failed.'});return false;}
  };
  const unmapStoreVariant=async(_productId:string,listingId:string)=>{
    try{await marketplaceRequest('/listings/'+encodeURIComponent(listingId)+'/mapping','DELETE');await load();return true;}
    catch(e){admin.addToast({type:'error',title:'Unlink failed',message:e instanceof Error?e.message:'Request failed.'});return false;}
  };
  return {
    ...admin, currentSection:admin.currentSection as NavSection,
    products:admin.products as unknown as Product[],categories:admin.categories as unknown as Category[],
    loading,error,reload:load,connections,marketplaceStoreProducts,
    marketplaceOrders:[] as MarketplaceOrder[],marketplaceInventory:[] as MarketplaceInventoryItem[],syncLogs:[] as MarketplaceSyncLog[],
    selectedMarketplaceOrderId,setSelectedMarketplaceOrderId,
    saveConnection,deleteConnection,mapStoreVariantToJwSku,unmapStoreVariant,
    toggleConnectionSync:unavailable,refreshToken:unavailable,syncAllMarketplaces:unavailable,triggerChannelSyncAll:unavailable,isSyncingMarketplace:false,
    saveInventoryItem:unavailable,adjustStock:unavailable,broadcastStockToChannels:unavailable,broadcastAllStockToChannels:unavailable,linkChannelSku:unavailable,unlinkChannelSku:unavailable,
    batchAcceptSuggestedMappings:unavailable,refreshStoreProductsFromChannel:unavailable,resetStoreProductsToDefaults:unavailable,
    importStoreProductToMaster:unavailable,batchImportStoreProductsToMaster:unavailable,publishMasterProductToStore:unavailable,
    generateAirwayBill:unavailable,markOrderPacked:unavailable,markOrderShipped:unavailable,batchPrintAWB:unavailable,batchPackOrders:unavailable,simulateIncomingOrder:unavailable,retrySyncLog:unavailable,clearSyncLogs:unavailable,
  };
}
const Context=createContext<ReturnType<typeof useRuntime>|null>(null);
export function MarketplaceProvider({children}:{children:React.ReactNode}){return <Context.Provider value={useRuntime()}>{children}</Context.Provider>;}
export function useAdmin(){const value=useContext(Context);if(!value)throw new Error('MarketplaceProvider required');return value;}
