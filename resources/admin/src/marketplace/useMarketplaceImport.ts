import {useRef,useState} from 'react';
import {importCatalogProducts,type ImportResult} from '../integration/marketplaceImport';
export function useMarketplaceImport(enabled:boolean,sources:{id:string;listingId:string}[],onSuccess:()=>void){
 const lock=useRef(false);const [busy,setBusy]=useState(false);
 const run=async(ids:string[]):Promise<ImportResult[]>=>{
  if(!enabled||lock.current)return [];
  lock.current=true;setBusy(true);
  try{const results=await importCatalogProducts(ids,sources);if(results.some(result=>result.ok))onSuccess();return results;}
  finally{lock.current=false;setBusy(false);}
 };
 return {busy,run};
}
