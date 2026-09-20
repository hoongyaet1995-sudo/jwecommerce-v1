import {useCallback,useEffect,useMemo,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
import type {PublishingSource} from '../integration/marketplacePublishing';
import {normalizeLazadaAttributes,serializeLazadaAttributes,validateAttributeValue,validateLazadaSaleProperties} from './publishingAttributes/lazada';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';
import {useMarketplaceAttributeValues} from './useMarketplaceAttributeValues';

export function useLazadaPublishingAttributes(storeId:string|null,categoryId:string,source?:PublishingSource){
 const key=storeId&&categoryId&&source?`${storeId}:${categoryId}:${source.revision}:${source.variants.map(variant=>variant.sku).join('|')}`:'';
 const [snapshot,setSnapshot]=useState<{key:string;data?:unknown;error?:string;brands?:string[];next?:number|null;moreLoading?:boolean;moreError?:string}|null>(null);
 const [attempt,retry]=useState(0);
 useEffect(()=>{
  if(!key)return;let active=true;
  setSnapshot({key});
  Promise.all([
   marketplaceRequest<{storeId:number;categoryId:number;data:unknown}>(`/stores/${encodeURIComponent(storeId!)}/listing-attributes?categoryId=${encodeURIComponent(categoryId)}`),
   marketplaceRequest<{storeId:number;categoryId:number;data:{names:string[];next_offset:number|null}}>(`/stores/${encodeURIComponent(storeId!)}/listing-brands?categoryId=${encodeURIComponent(categoryId)}&offset=0`),
  ]).then(([result,brands])=>{
   if(String(result.storeId)!==storeId||String(result.categoryId)!==categoryId||String(brands.storeId)!==storeId||String(brands.categoryId)!==categoryId
    ||!Array.isArray(brands.data.names)||!brands.data.names.every(name=>typeof name==='string'))throw new Error('Category identity changed.');
   if(active)setSnapshot({key,data:result.data,brands:brands.data.names,next:brands.data.next_offset});
  }).catch(()=>{if(active)setSnapshot({key,error:'Category requirements could not be loaded. Select the category again to retry.'});});
  return()=>{active=false;};
 },[key,storeId,categoryId,attempt]);
 const current=snapshot?.key===key?snapshot:null;
 const normalized=useMemo(()=>{
  if(!source||!current?.data)return {fields:[] as MarketplaceAttributeField[],error:''};
  try{return {fields:normalizeLazadaAttributes(current.data,source,new Map(),current.brands),error:''};}
  catch{return {fields:[] as MarketplaceAttributeField[],error:'Category requirements could not be understood. Publishing is unavailable for this category.'};}
 },[source,current]);
 const initial=useCallback((field:MarketplaceAttributeField):MarketplaceAttributeValue|undefined=>{
  if(!source)return undefined;
  const variant=field.sku?source.variants.find(item=>item.sku===field.sku):undefined;
  const own:Record<string,unknown>=variant?{
   SellerSku:variant.sku,price:(variant.price_minor/100).toFixed(2),quantity:'0',
   package_weight:variant.weight_grams?variant.weight_grams/1000:null,package_length:variant.package_length_cm,
   package_width:variant.package_width_cm,package_height:variant.package_height_cm,
  }:{name:source.title,title:source.title,description:source.description,brand:source.brand};
  const raw=own[field.providerName];
  if(typeof raw!=='string'&&typeof raw!=='number')return undefined;
  const value=String(raw);
  if(field.control==='integer'||field.control==='decimal')return {kind:'number',value};
  if(field.control==='date')return {kind:'date',value};
  if(field.control==='single'){
   const option=field.options.find(item=>item.label===value);
   if(option)return {kind:'single',value:{id:option.id,label:option.label,custom:false}};
   if(field.allowCustom)return {kind:'single',value:{id:null,label:value,custom:true}};
   return undefined;
  }
  return {kind:'text',value};
 },[source]);
 const state=useMarketplaceAttributeValues(key,normalized.fields,initial);
 const errors=useMemo(()=>Object.fromEntries([...normalized.fields.flatMap(field=>{
  const error=validateAttributeValue(field,state.values.get(field.key)??{kind:'empty'});return error?[[field.key,error]]:[];
 }),...validateLazadaSaleProperties(normalized.fields,state.values,source?.variants.length??0).map((error,index)=>[`sale-properties:${index}`,error])]),[normalized.fields,state.values,source?.variants.length]);
 const serialized=useMemo(()=>serializeLazadaAttributes(normalized.fields,state.values),[normalized.fields,state.values]);
 return {fields:normalized.fields,values:state.values,serialized,errors,update:state.update,loading:!!key&&!current?.data&&!current?.error,
  error:current?.error||normalized.error,retry:()=>retry(value=>value+1),
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
  }};
}
