import {useEffect,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';

export interface ListingCategory {
 category_id:number; parent_category_id:number; original_category_name:string;
 display_category_name:string; has_children:boolean;
}

/** Destination-scoped metadata; never fall back to demo options on read failure. */
export function useMarketplaceListingCategories(storeId:string|null) {
 const [revision,setRevision]=useState(0);
 const [snapshot,setSnapshot]=useState<{storeId:string;categories:ListingCategory[];error:string;loading:boolean}|null>(null);
 useEffect(()=>{
  if (!storeId) return;
  let active=true;
  setSnapshot({storeId,categories:[],error:'',loading:true});
  marketplaceRequest<{storeId:number;data:ListingCategory[]}>(`/stores/${encodeURIComponent(storeId)}/listing-categories?refresh=${revision>0?1:0}`)
   .then(result=>{
    if (String(result.storeId)!==storeId || !Array.isArray(result.data)) throw new Error('Invalid category response.');
    if (active) setSnapshot({storeId,categories:result.data,error:'',loading:false});
   }).catch(()=>{
    if (active) setSnapshot({storeId,categories:[],error:'Store categories could not be loaded. Retry before continuing.',loading:false});
   });
  return()=>{active=false;};
 },[storeId,revision]);
 const current=storeId && snapshot?.storeId===storeId ? snapshot : null;
 return {categories:current?.categories ?? [],error:current?.error ?? '',
  loading:!!storeId && (current?.loading ?? true),reload:()=>setRevision(value=>value+1)};
}
