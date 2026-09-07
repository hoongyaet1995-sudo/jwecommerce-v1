import {useEffect,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
export interface ListingBrand {brand_id:number;original_brand_name:string;display_brand_name?:string}
interface Page {brand_list:ListingBrand[];has_next_page:boolean;next_offset:number;is_mandatory:boolean;input_type:string}
interface Snapshot {key:string;brands:ListingBrand[];next:number;hasMore:boolean;mandatory:boolean;loading:boolean;error:string}

/** Brand identity is scoped to both store and category; pagination never runs automatically. */
export function useMarketplaceListingBrands(storeId:string|null,categoryId:string|null) {
 const key=storeId && categoryId ? `${storeId}:${categoryId}` : '';
 const [request,setRequest]=useState({key:'',offset:0,revision:0});
 const [snapshot,setSnapshot]=useState<Snapshot|null>(null);
 const offset=request.key===key ? request.offset : 0;
 useEffect(()=>{
  setRequest(old=>old.key===key ? old : {...old,key,offset:0});
  if (!key) return;
  let active=true;
  setSnapshot(old=>({key,brands:offset && old?.key===key ? old.brands : [],next:offset,
   hasMore:false,mandatory:old?.key===key ? old.mandatory : false,loading:true,error:''}));
  const params=new URLSearchParams({categoryId:categoryId!,offset:String(offset)});
  marketplaceRequest<{storeId:number;categoryId:number;data:Page}>(`/stores/${encodeURIComponent(storeId!)}/listing-brands?${params}`)
   .then(result=>{
    const page=result.data;
    if (String(result.storeId)!==storeId || String(result.categoryId)!==categoryId || !page
     || !Array.isArray(page.brand_list) || typeof page.has_next_page!=='boolean' || typeof page.is_mandatory!=='boolean'
     || !Number.isSafeInteger(page.next_offset) || (page.has_next_page && page.next_offset<=offset)
     || page.brand_list.some(brand=>!Number.isSafeInteger(brand.brand_id) || brand.brand_id<0 || typeof brand.original_brand_name!=='string')) {
     throw new Error('Invalid brand metadata.');
    }
    if (active) setSnapshot(old=>({key,brands:Array.from(new Map([...(offset && old?.key===key ? old.brands : []),...page.brand_list].map(brand=>[brand.brand_id,brand])).values()),
     next:page.next_offset,hasMore:page.has_next_page,mandatory:page.is_mandatory,loading:false,error:''}));
   }).catch(()=>{if(active)setSnapshot(old=>({key,brands:old?.key===key ? old.brands : [],next:offset,hasMore:false,
    mandatory:old?.key===key ? old.mandatory : false,loading:false,error:'Brand choices could not be loaded. Retry this page.'}));});
  return()=>{active=false;};
 },[key,storeId,categoryId,offset,request.revision]);
 const current=key && snapshot?.key===key ? snapshot : null;
 return {brands:current?.brands ?? [],hasMore:current?.hasMore ?? false,mandatory:current?.mandatory ?? false,
  loading:!!key && (current?.loading ?? true),error:current?.error ?? '',
  loadMore:()=>{if(current?.hasMore && !current.loading)setRequest(old=>({key,offset:current.next,revision:old.revision+1}));},
  retry:()=>setRequest(old=>({key,offset,revision:old.revision+1}))};
}
