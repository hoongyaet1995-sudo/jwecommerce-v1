import {useEffect,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
export function usePublishingStoreStatus(store:string|null){
 const [snapshot,setSnapshot]=useState<{store:string;products:Record<string,string>}|null>(null);
 const [error,setError]=useState('');
 useEffect(()=>{if(!store)return;let active=true;setError('');
 marketplaceRequest<{products:Record<string,string>}>(`/stores/${store}/publishing-status`).then(data=>{if(!data||!data.products||Array.isArray(data.products))throw Error();if(active)setSnapshot({store,products:data.products});}).catch(()=>{if(active)setError('Listing status could not be checked. Reopen this window to retry.');});
 return()=>{active=false;};},[store]);
 return {products:snapshot?.store===store?snapshot.products:{},loading:!!store&&snapshot?.store!==store&&!error,error};
}
