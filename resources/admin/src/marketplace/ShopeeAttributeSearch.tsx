import React,{useRef,useState,useEffect} from 'react';
import {marketplaceRequest} from '../integration/marketplaceData';
import type {AttributeValue} from './useShopeePublishingRequirements';
export function ShopeeAttributeSearch({storeId,attributeId,onSelect}:{storeId:string;attributeId:number;onSelect:(value:AttributeValue)=>void}){
 const [query,setQuery]=useState(''),[rows,setRows]=useState<{value_id:number;value_name:string}[]>([]);
 const [next,setNext]=useState<number|null>(null),[busy,setBusy]=useState(false),[error,setError]=useState('');
 const serial=useRef(0);
 useEffect(()=>()=>{serial.current++;},[]);
 const search=async(cursor=0)=>{
  const ticket=++serial.current;setBusy(true);setError('');
  try{
   const result=await marketplaceRequest<{storeId:number;attributeId:number;data:{value_list:typeof rows;page_info:{cursor:number;has_next:boolean}}}>(`/stores/${storeId}/listing-attribute-values?attributeId=${attributeId}&q=${encodeURIComponent(query)}&cursor=${cursor}`);
   if(String(result.storeId)!==storeId||result.attributeId!==attributeId||!Array.isArray(result.data.value_list)
     ||result.data.value_list.some(v=>!Number.isSafeInteger(v.value_id)||v.value_id<=0||typeof v.value_name!=='string')
     ||(result.data.page_info.has_next&&result.data.page_info.cursor<=cursor))throw new Error();
   if(ticket!==serial.current)return;
   setRows(old=>Array.from(new Map([...(cursor?old:[]),...result.data.value_list].map(v=>[v.value_id,v])).values()));
   setNext(result.data.page_info.has_next?result.data.page_info.cursor:null);
  }catch{if(ticket===serial.current)setError('Unable to load platform values. Retry search.');}
  finally{if(ticket===serial.current)setBusy(false);}
 };
 return <div className="space-y-1.5">
  <input aria-label="Search platform values" className="w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs" value={query} onChange={e=>{serial.current++;setBusy(false);setQuery(e.target.value);setRows([]);setNext(null);}}/>
  <button type="button" disabled={busy} onClick={()=>search()} className="text-xs text-indigo-500">{busy?'Loading…':'Search platform values'}</button>
  {rows.map(row=><button type="button" key={row.value_id} className="text-xs text-indigo-500 block" onClick={()=>onSelect({value_id:row.value_id,original_value_name:row.value_name,value_unit:''})}>{row.value_name}</button>)}
  {next!==null&&<button type="button" disabled={busy} onClick={()=>search(next)} className="text-xs text-indigo-500">Load more values</button>}
  {error&&<p className="text-xs text-rose-500">{error}</p>}
 </div>;
}
