import {useEffect,useMemo,useState} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
import {normalizeShopeeAttributes,type ShopeeAttributeDefinition,type ShopeeAttributeSelectionValue} from './publishingAttributes/shopee';

export type ShopeeAttribute=ShopeeAttributeDefinition;
export interface ShopeeChannel {logistics_channel_id:number;logistics_channel_name:string;enabled:boolean;fee_type:string;force_enable?:boolean;compulsory_channel?:boolean;
 size_list?:{size_id:string|number;name?:string}[]}
export type AttributeValue=ShopeeAttributeSelectionValue;
export interface ChannelReview {logistic_id:number;is_free?:boolean;size_id?:string|number;shipping_fee?:number}

export function activeShopeeAttributes(tree:ShopeeAttribute[],values:Record<number,AttributeValue[]>):ShopeeAttribute[]{
 const result=new Map<number,ShopeeAttribute>();
 const visit=(nodes:ShopeeAttribute[],depth:number)=>{
  if(depth>20)throw new Error('Category requirements could not be read.');
  for(const node of nodes){
   if(!node||!Number.isSafeInteger(node.attribute_id)||node.attribute_id<=0||typeof node.mandatory!=='boolean'
    ||!node.attribute_info||!Array.isArray(node.attribute_value_list))throw new Error('Category requirements could not be read.');
   result.set(node.attribute_id,node);
   for(const option of node.attribute_value_list)if(values[node.attribute_id]?.some(value=>value.value_id===option.value_id))visit(option.child_attribute_list??[],depth+1);
  }
 };
 visit(tree,0);return [...result.values()];
}

export function useShopeePublishingRequirements(storeId:string|null,categoryId:string,revision:string){
 const key=storeId&&categoryId?`${storeId}:${categoryId}:${revision}`:'';
 const [attempt,setAttempt]=useState(0);
 const [snapshot,setSnapshot]=useState<{key:string;tree:ShopeeAttribute[];channels:ShopeeChannel[];error:string}|null>(null);
 const [edits,setEdits]=useState<{key:string;attributes:Record<number,AttributeValue[]>;channels:Record<number,ChannelReview>}>({key:'',attributes:{},channels:{}});
 useEffect(()=>{
  if(!key)return;
  let active=true;setSnapshot(null);setEdits({key,attributes:{},channels:{}});
  Promise.all([
   marketplaceRequest<{storeId:number;categoryId:number;data:ShopeeAttribute[]}>(`/stores/${storeId}/listing-attributes?categoryId=${encodeURIComponent(categoryId)}&refresh=${attempt>0?1:0}`),
   marketplaceRequest<{storeId:number;data:ShopeeChannel[]}>(`/stores/${storeId}/listing-logistics?refresh=${attempt>0?1:0}`),
  ]).then(([attributes,logistics])=>{
   if(String(attributes.storeId)!==storeId||String(attributes.categoryId)!==categoryId||String(logistics.storeId)!==storeId
    ||!Array.isArray(attributes.data)||!Array.isArray(logistics.data)||logistics.data.some(channel=>!Number.isSafeInteger(channel.logistics_channel_id)||typeof channel.enabled!=='boolean'))throw new Error('Store requirements could not be verified.');
   activeShopeeAttributes(attributes.data,{});
   if(active){setSnapshot({key,tree:attributes.data,channels:logistics.data.filter(channel=>channel.enabled),error:''});
    setEdits({key,attributes:{},channels:Object.fromEntries(logistics.data.filter(channel=>channel.enabled&&(channel.force_enable||channel.compulsory_channel)).map(channel=>[channel.logistics_channel_id,{logistic_id:channel.logistics_channel_id,is_free:false}]))});}
  }).catch(error=>{if(active)setSnapshot({key,tree:[],channels:[],error:error instanceof Error?error.message:'Unable to load category requirements.'});});
  return()=>{active=false;};
 },[key,storeId,categoryId,attempt]);
 const current=snapshot?.key===key?snapshot:null;
 const selections:{attributes:Record<number,AttributeValue[]>;channels:Record<number,ChannelReview>}=edits.key===key?edits:{attributes:{},channels:{}};
 const computed=useMemo(()=>{
  try{
   const fields=activeShopeeAttributes(current?.tree??[],selections.attributes);
   const attributes=fields.flatMap(field=>selections.attributes[field.attribute_id]?.length?[{attribute_id:field.attribute_id,attribute_value_list:selections.attributes[field.attribute_id]}]:[]);
   const logistics=Object.values(selections.channels);
   const errors:string[]=[];
   for(const field of fields){const values=selections.attributes[field.attribute_id]??[];const name=field.attribute_info.display_name??field.attribute_info.name??`Attribute ${field.attribute_id}`;
    if(field.mandatory&&!values.length)errors.push(`Complete ${name}.`);
    if(values.some(value=>!value.original_value_name.trim()||(value.value_id===0&&field.attribute_info.format_type===2&&!value.value_unit)))errors.push(`Review ${name}.`);
   }
   if(!logistics.length)errors.push('Select a shipping channel.');
   for(const selection of logistics){const channel=current?.channels.find(row=>row.logistics_channel_id===selection.logistic_id);
    if(!channel)errors.push('Reload the selected shipping channel.');
    else if(channel.fee_type==='SIZE_SELECTION'&&selection.size_id===undefined)errors.push(`Select a shipping size for ${channel.logistics_channel_name}.`);
    else if(channel.fee_type==='CUSTOM_PRICE'&&(selection.shipping_fee===undefined||!Number.isFinite(selection.shipping_fee)||selection.shipping_fee<0))errors.push(`Enter a shipping fee for ${channel.logistics_channel_name}.`);
   }
   return {fields,canonicalFields:normalizeShopeeAttributes(current?.tree??[],selections.attributes),attributes,logistics,errors};
  }catch(error){return {fields:[],canonicalFields:[],attributes:[],logistics:[],errors:[error instanceof Error?error.message:'Review category requirements.']};}
 },[current,selections]);
 return {...computed,storeId,channels:current?.channels??[],values:selections.attributes,selectedChannels:selections.channels,
  loading:!!key&&!current,error:current?.error??'',retry:()=>setAttempt(value=>value+1),
  updateAttribute:(id:number,values:AttributeValue[])=>setEdits(old=>({...old,key,attributes:{...(old.key===key?old.attributes:{}),[id]:values}})),
  updateChannel:(id:number,value:ChannelReview|null)=>setEdits(old=>{const channels={...(old.key===key?old.channels:{})};if(value)channels[id]=value;else delete channels[id];return {...old,key,channels};})};
}
