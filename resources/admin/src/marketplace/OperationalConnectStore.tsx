import React, {useCallback,useEffect,useState} from 'react';
import {useAdmin} from '../context/AdminContext';
import {marketplaceOperations, StoreConnection, ProviderCapabilities, MarketplacePlatform, OAuthResult} from '../integration/marketplaceOperations';
import {PreviewContext} from '../previews/marketplace30/context/ContextBridge';
import {MarketplaceAuthPage} from '../previews/marketplace30/components/marketplace/MarketplaceAuthPage';

export function OperationalConnectStore() {
  const admin=useAdmin();
  const [stores,setStores]=useState<StoreConnection[]>([]);
  const [capabilities,setCapabilities]=useState<Partial<Record<MarketplacePlatform,ProviderCapabilities>>>({});
  const [loading,setLoading]=useState(true);
  const [error,setError]=useState('');
  const [authorizationResult,setAuthorizationResult]=useState<OAuthResult|null>(null);
  useEffect(()=>{
    let active=true;
    marketplaceOperations.oauthResult().then(({result})=>{if(active)setAuthorizationResult(result);}).catch(()=>{});
    return()=>{active=false;};
  },[]);
  const load=useCallback(async()=>{
    setLoading(true);setError('');
    try {const data=await marketplaceOperations.connections();setStores(data.stores);setCapabilities(data.capabilities);}
    catch {setError('Store connections could not be loaded.');}
    finally {setLoading(false);}
  },[]);
  useEffect(()=>{void load();},[load]);
  const unavailable=()=>admin.addToast({type:'info',title:'Unavailable',message:'This operation is not enabled. No remote changes were made.'});
  const disconnectConnection=async(id:string)=>{
    try {
      await marketplaceOperations.disconnect(id);
      await load();
      admin.addToast({type:'success',title:'Store disconnected',message:'JW synchronization is stopped. Historical orders and mappings are preserved.'});
      return true;
    } catch {admin.addToast({type:'error',title:'Disconnect failed',message:'The connection could not be updated. Please retry.'});return false;}
  };
  const value={...admin,connections:stores.map(store=>({...store,tokenExpiresAt:store.tokenExpiresAt||'',lastSyncAt:store.lastSyncAt||'',
    autoSyncStock:false,autoPullOrders:false})),marketplaceOrders:[],marketplaceInventory:[],marketplaceStoreProducts:[],syncLogs:[],
    deleteConnection:unavailable,toggleConnectionSync:unavailable,refreshToken:unavailable,syncAllMarketplaces:unavailable,disconnectConnection};
  if(loading)return <p role="status" className="text-sm text-neutral-500">Loading Marketplace settings…</p>;
  if(error)return <div role="alert" className="text-sm text-rose-500">{error}<button className="ml-3 underline" onClick={()=>void load()}>Retry</button></div>;
  return <PreviewContext.Provider value={value as unknown as React.ContextType<typeof PreviewContext>}>
    <MarketplaceAuthPage operational authorizationResult={authorizationResult} oauthAvailability={Object.fromEntries((['shopee','lazada','tiktok','shopify'] as const).map(key=>[key,capabilities[key]?.oauth===true]))}/>
  </PreviewContext.Provider>;
}
