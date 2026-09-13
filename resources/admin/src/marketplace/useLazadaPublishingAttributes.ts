import {useEffect,useMemo,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
import type {PublishingSource} from '../integration/marketplacePublishing';
import {lazadaPublishingFields,lazadaPublishingValues} from '../integration/lazadaPublishingFields';

export function useLazadaPublishingAttributes(storeId:string|null,categoryId:string,source?:PublishingSource){
 const key=storeId&&categoryId&&source?`${storeId}:${categoryId}:${source.revision}`:'';
 const [snapshot,setSnapshot]=useState<{key:string;data?:unknown;error?:string;brands?:string[];next?:number|null;moreLoading?:boolean;moreError?:string}|null>(null);
 const [attempt,retry]=useState(0);
 const [editState,setEdits]=useState<{key:string;values:Record<string,string>}>({key:'',values:{}});
 useEffect(()=>{
  if(!key)return;let active=true;
  setSnapshot({key});
  Promise.all([
   marketplaceRequest<{storeId:number;categoryId:number;data:unknown}>(`/stores/${encodeURIComponent(storeId!)}/listing-attributes?categoryId=${encodeURIComponent(categoryId)}`),
   marketplaceRequest<{storeId:number;categoryId:number;data:{names:string[];next_offset:number|null}}>(`/stores/${encodeURIComponent(storeId!)}/listing-brands?categoryId=${encodeURIComponent(categoryId)}&offset=0`)
  ]).then(([result,brands])=>{
    if(String(result.storeId)!==storeId||String(result.categoryId)!==categoryId||String(brands.storeId)!==storeId||String(brands.categoryId)!==categoryId
     ||!Array.isArray(brands.data.names)||!brands.data.names.every(name=>typeof name==='string'))throw new Error('Category identity changed.');
    if(active)setSnapshot({key,data:result.data,brands:brands.data.names,next:brands.data.next_offset});
   }).catch(()=>{if(active)setSnapshot({key,error:'Category requirements could not be loaded. Select the category again to retry.'});});
  return()=>{active=false;};
 },[key,storeId,categoryId,attempt]);
 const current=snapshot?.key===key?snapshot:null;
 const edits=editState.key===key?editState.values:{};
 const result=useMemo(()=>{
  if(!source||!current?.data)return {fields:[],error:''};
  try{return {fields:lazadaPublishingFields(current.data,source,edits,current.brands),error:''};}
  catch{return {fields:[],error:'Category requirements could not be understood. Publishing is unavailable for this category.'};}
 },[source,current,edits]);
 return {fields:result.fields,values:lazadaPublishingValues(result.fields),loading:!!key&&!current?.data&&!current?.error,
  error:current?.error||result.error, retry:()=>retry(value=>value+1),
  moreBrands:current?.next!=null,moreLoading:current?.moreLoading,moreError:current?.moreError,
  loadMore:async()=>{
   if(current?.next==null||current.moreLoading)return;
   const offset=current.next;
   setSnapshot(previous=>previous?.key===key?{...previous,moreLoading:true,moreError:undefined}:previous);
   try{
    const page=await marketplaceRequest<{storeId:number;categoryId:number;data:{names:string[];next_offset:number|null}}>(`/stores/${encodeURIComponent(storeId!)}/listing-brands?categoryId=${encodeURIComponent(categoryId)}&offset=${offset}`);
    if(String(page.storeId)!==storeId||String(page.categoryId)!==categoryId||!Array.isArray(page.data.names)||!page.data.names.every(name=>typeof name==='string')
     ||page.data.next_offset!==null&&(!Number.isInteger(page.data.next_offset)||page.data.next_offset<=offset))throw new Error('Invalid brand page.');
    setSnapshot(previous=>previous?.key===key?{...previous,brands:[...new Set([...(previous.brands??[]),...page.data.names])],next:page.data.next_offset,moreLoading:false}:previous);
   }catch{setSnapshot(previous=>previous?.key===key?{...previous,moreLoading:false,moreError:'More brands could not be loaded. Retry.'}:previous);}
  },
  update:(field:string,value:string)=>setEdits(previous=>({key,values:{...(previous.key===key?previous.values:{}),[field]:value}}))};
}
