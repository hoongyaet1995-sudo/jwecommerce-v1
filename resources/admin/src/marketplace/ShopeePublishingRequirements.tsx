import React from 'react';
import {type ShopeeAttribute,type AttributeValue,useShopeePublishingRequirements} from './useShopeePublishingRequirements';
const control='w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500';

export function ShopeePublishingRequirements({review}:{review:ReturnType<typeof useShopeePublishingRequirements>}){
 const render=(field:ShopeeAttribute)=>{
  const id=field.attribute_id,info=field.attribute_info,type=info.input_type,values=review.values[id]??[];
  const label=info.display_name??info.name??`Attribute ${id}`;const multiple=[4,5].includes(type);
  const predefined=values.filter(value=>value.value_id>0),custom=values.find(value=>value.value_id===0);
  const updateCustom=(name:string,unit:string)=>review.updateAttribute(id,[...(multiple?predefined:[]),...(name?[{value_id:0,original_value_name:name,value_unit:unit}]:[])]);
  return <div key={id} className="space-y-1.5">
   <label htmlFor={`shopee-attribute-${id}`} className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">{label}{field.mandatory&&<span className="text-rose-500">*</span>}</label>
   {type!==3&&<select id={`shopee-attribute-${id}`} className={control} multiple={multiple}
    value={multiple?predefined.map(value=>String(value.value_id)):String(predefined[0]?.value_id??'')}
    onChange={(event:React.ChangeEvent<HTMLSelectElement>)=>{const selected:AttributeValue[]=Array.from(event.target.selectedOptions).flatMap((option:HTMLOptionElement)=>{const value=field.attribute_value_list.find(row=>String(row.value_id)===option.value);return value?[{value_id:value.value_id,original_value_name:value.name,value_unit:value.value_unit??''}]:[];});review.updateAttribute(id,[...selected,...(multiple&&custom?[custom]:[])]);}}>
     {!multiple&&<option value="">Select {label}</option>}{field.attribute_value_list.map(option=><option key={option.value_id} value={option.value_id}>{option.name}</option>)}
    </select>}
   {[2,3,5].includes(type)&&<input id={type===3?`shopee-attribute-${id}`:undefined} aria-label={`Custom ${label}`} className={control} value={custom?.original_value_name??''} placeholder={type===3?label:'Custom value'} onChange={event=>updateCustom(event.target.value,custom?.value_unit??'')}/>}
   {[2,3,5].includes(type)&&info.format_type===2&&<select aria-label={`${label} unit`} className={control} value={custom?.value_unit??''} onChange={event=>updateCustom(custom?.original_value_name??'',event.target.value)}>
    <option value="">Select unit</option>{info.attribute_unit_list?.map(unit=><option key={unit} value={unit}>{unit}</option>)}
   </select>}
  </div>;
 };
 return <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
  {review.fields.filter(field=>field.mandatory).map(render)}
  {review.fields.some(field=>!field.mandatory)&&<details className="space-y-1.5 sm:col-span-2"><summary className="text-xs font-bold text-neutral-700 dark:text-neutral-300">Optional attributes</summary>{review.fields.filter(field=>!field.mandatory).map(render)}</details>}
  <div className="space-y-1.5 sm:col-span-2">
   <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">Store shipping channels</label>
   {review.channels.map(channel=>{const id=channel.logistics_channel_id,selected=review.selectedChannels[id];return <div key={id} className="space-y-1.5">
    <label className="text-xs text-neutral-700 dark:text-neutral-300 flex items-center gap-2"><input type="checkbox" checked={!!selected} disabled={!!(channel.force_enable||channel.compulsory_channel)} onChange={event=>review.updateChannel(id,event.target.checked?{logistic_id:id,is_free:false}:null)}/>{channel.logistics_channel_name}</label>
    {selected&&channel.fee_type==='SIZE_SELECTION'&&<select className={control} aria-label={`${channel.logistics_channel_name} size`} value={String(selected.size_id??'')} onChange={event=>{const size=channel.size_list?.find(row=>String(row.size_id)===event.target.value);review.updateChannel(id,{...selected,size_id:size?.size_id});}}><option value="">Select shipping size</option>{channel.size_list?.map(size=><option key={size.size_id} value={size.size_id}>{size.name??String(size.size_id)}</option>)}</select>}
    {selected&&channel.fee_type==='CUSTOM_PRICE'&&<input type="number" min="0" step="0.01" className={control} aria-label={`${channel.logistics_channel_name} fee`} value={selected.shipping_fee??''} onChange={event=>review.updateChannel(id,{...selected,shipping_fee:event.target.value===''?undefined:Number(event.target.value)})}/>}
   </div>;})}
  </div>
  {review.error&&<button type="button" className="text-xs font-bold text-neutral-700 dark:text-neutral-300" onClick={review.retry}>Retry category and shipping requirements</button>}
 </div>;
}
