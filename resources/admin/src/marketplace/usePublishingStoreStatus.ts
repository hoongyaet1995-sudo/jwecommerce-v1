import {useEffect,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
import type {PublishingTask} from '../integration/marketplaceOperations';
export function usePublishingStoreStatus(store:string|null){
 const [snapshot,setSnapshot]=useState<{store:string;products:Record<string,string>;tasksByProduct:Record<string,PublishingTask>}|null>(null);
 const [error,setError]=useState('');
 useEffect(()=>{if(!store)return;let active=true;setError('');
 marketplaceRequest<{products:Record<string,string>;tasksByProduct?:Record<string,PublishingTask>}>(`/stores/${store}/publishing-status`).then(data=>{if(!data||!data.products||Array.isArray(data.products))throw Error();if(active)setSnapshot({store,products:data.products,tasksByProduct:data.tasksByProduct??{}});}).catch(()=>{if(active)setError('Listing status could not be checked. Reopen this window to retry.');});
 return()=>{active=false;};},[store]);
 return {products:snapshot?.store===store?snapshot.products:{},tasksByProduct:snapshot?.store===store?snapshot.tasksByProduct:{},loading:!!store&&snapshot?.store!==store&&!error,error};
}
