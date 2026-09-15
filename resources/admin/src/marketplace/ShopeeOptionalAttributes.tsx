import React from 'react';
import {OptionalAttributesSection,type PlatformAttributeMeta} from './publish37/OptionalAttributesSection';
import {ShopeeAttributeSearch} from './ShopeeAttributeSearch';
import type {AttributeValue,useShopeePublishingRequirements} from './useShopeePublishingRequirements';
type Review=ReturnType<typeof useShopeePublishingRequirements>;
const token=(id:number)=>`preset:${id}`;
export function ShopeeOptionalAttributes({review}:{review:Review}){
 const fields=review.fields.filter(field=>!field.mandatory);
 const metadata:PlatformAttributeMeta[]=fields.map(field=>{
  const info=field.attribute_info;const free=[2,3,5].includes(info.input_type);
  const options=[...field.attribute_value_list.map(v=>({id:String(v.value_id),label:v.name,value:token(v.value_id)})),...(review.values[field.attribute_id]??[]).filter(v=>v.value_id>0&&!field.attribute_value_list.some(o=>o.value_id===v.value_id)).map(v=>({id:String(v.value_id),label:v.original_value_name,value:token(v.value_id)}))];
  return {id:String(field.attribute_id),name:info.display_name??info.name??`Attribute ${field.attribute_id}`,allowCustom:free,
   inputType:[4,5].includes(info.input_type)?'multi_select':info.input_type===3||(!options.length&&free&&!info.support_search_value)?'text':'single_select',options,
   description:!options.length&&!free&&!info.support_search_value?'No platform options available. Refresh category requirements.':undefined};
 });
 const values=Object.fromEntries(fields.map(field=>{
  const saved=review.values[field.attribute_id]??[];
  const vals=saved.map(v=>v.value_id>0?token(v.value_id):v.original_value_name);
  return [String(field.attribute_id),[4,5].includes(field.attribute_info.input_type)?vals:vals[0]??''];
 }));
 const update=(id:string,value:unknown)=>{
  const field=fields.find(f=>String(f.attribute_id)===id);if(!field)return;
  const old=review.values[field.attribute_id]??[];
  const next=(Array.isArray(value)?value:[value]).filter(v=>typeof v==='string'&&v.trim()).flatMap(v=>{
   const preset=field.attribute_value_list.find(o=>token(o.value_id)===v);
   if(preset)return [{value_id:preset.value_id,original_value_name:preset.name,value_unit:preset.value_unit??''}];
   const prior=old.find(o=>o.value_id>0&&token(o.value_id)===v);if(prior)return [prior];
   if(![2,3,5].includes(field.attribute_info.input_type))return [];
   return [{value_id:0,original_value_name:v,value_unit:old.find(o=>o.value_id===0)?.value_unit??''}];
  });
  review.updateAttribute(field.attribute_id,next);
 };
 if(!fields.length)return null;
 return <OptionalAttributesSection attributesMeta={metadata} values={values} onChange={update} supplemental={id=>{
  const field=fields.find(f=>String(f.attribute_id)===id)!;const info=field.attribute_info;
  const saved=review.values[field.attribute_id]??[];const custom=saved.find(v=>v.value_id===0);
  return <>
   {custom&&info.format_type===2&&<select aria-label={`${info.display_name??info.name} unit`} className="px-2.5 py-2 bg-neutral-100 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-800 dark:text-neutral-200 font-semibold focus:ring-1 focus:ring-indigo-500 shrink-0" value={custom.value_unit} onChange={e=>review.updateAttribute(field.attribute_id,saved.map(v=>v.value_id===0?{...v,value_unit:e.target.value}:v))}><option value="">Select unit</option>{info.attribute_unit_list?.map(unit=><option key={unit} value={unit}>{unit}</option>)}</select>}
   {info.support_search_value&&review.storeId&&<ShopeeAttributeSearch key={`${review.storeId}:${id}`} storeId={review.storeId} attributeId={field.attribute_id} onSelect={(value:AttributeValue)=>review.updateAttribute(field.attribute_id,[...([4,5].includes(info.input_type)?saved.filter(v=>v.value_id!==value.value_id):[]),value])}/>}
  </>;
 }}/>;
}
