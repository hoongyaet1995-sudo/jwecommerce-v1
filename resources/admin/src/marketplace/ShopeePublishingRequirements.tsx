import {ShopeeOptionalAttributes} from './ShopeeOptionalAttributes';
import {StoreShippingChannelsSection} from './publish37/StoreShippingChannelsSection';
import {ShopeeAttributeSearch} from './ShopeeAttributeSearch';
import React from 'react';
import {type ShopeeAttribute,type AttributeValue,useShopeePublishingRequirements} from './useShopeePublishingRequirements';
const control='w-full px-3 py-2 bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-xl text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500';

export function ShopeePublishingRequirements({review,shopName='Selected Shopee store'}:{shopName?:string;review:ReturnType<typeof useShopeePublishingRequirements>}){
 const render=(field:ShopeeAttribute)=>{
  const id=field.attribute_id,info=field.attribute_info,type=info.input_type,values=review.values[id]??[];
  const label=info.display_name??info.name??`Attribute ${id}`;const multiple=[4,5].includes(type);
  const predefined=values.filter(value=>value.value_id>0),custom=values.find(value=>value.value_id===0);
  const updateCustom=(name:string,unit:string)=>review.updateAttribute(id,[...(multiple?predefined:[]),...(name?[{value_id:0,original_value_name:name,value_unit:unit}]:[])]);
  return <div key={id} className="space-y-1.5">
   <label htmlFor={`shopee-attribute-${id}`} className="text-xs font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1">{label}{field.mandatory&&<span className="text-rose-500">*</span>}</label>
   {info.support_search_value&&review.storeId&&<ShopeeAttributeSearch key={`${review.storeId}:${id}`} storeId={review.storeId} attributeId={id} onSelect={value=>review.updateAttribute(id,multiple?[...values.filter(v=>v.value_id!==value.value_id),value]:[value])}/>}
   {info.support_search_value&&predefined.map(value=><button type="button" key={value.value_id} className="text-xs text-indigo-500" onClick={()=>review.updateAttribute(id,values.filter(v=>v.value_id!==value.value_id))}>{value.original_value_name} ×</button>)}
   {type!==3&&field.attribute_value_list.length>0&&<select id={`shopee-attribute-${id}`} className={control} multiple={multiple}
    value={multiple?predefined.map(value=>String(value.value_id)):String(predefined[0]?.value_id??'')}
    onChange={(event:React.ChangeEvent<HTMLSelectElement>)=>{const selected:AttributeValue[]=Array.from(event.target.selectedOptions).flatMap((option:HTMLOptionElement)=>{const value=field.attribute_value_list.find(row=>String(row.value_id)===option.value);return value?[{value_id:value.value_id,original_value_name:value.name,value_unit:value.value_unit??''}]:[];});review.updateAttribute(id,[...selected,...(multiple&&custom?[custom]:[])]);}}>
     {!multiple&&<option value="">Select {label}</option>}{field.attribute_value_list.map(option=><option key={option.value_id} value={option.value_id}>{option.name}</option>)}
    </select>}
   {![2,3,5].includes(type)&&!field.attribute_value_list.length&&!info.support_search_value&&<p className="text-xs text-neutral-500">No platform options available. Refresh category requirements.</p>}
   {[2,3,5].includes(type)&&<input id={type===3?`shopee-attribute-${id}`:undefined} aria-label={`Custom ${label}`} className={control} value={custom?.original_value_name??''} placeholder={type===3?label:'Custom value'} onChange={event=>updateCustom(event.target.value,custom?.value_unit??'')}/>}
   {[2,3,5].includes(type)&&info.format_type===2&&<select aria-label={`${label} unit`} className={control} value={custom?.value_unit??''} onChange={event=>updateCustom(custom?.original_value_name??'',event.target.value)}>
    <option value="">Select unit</option>{info.attribute_unit_list?.map(unit=><option key={unit} value={unit}>{unit}</option>)}
   </select>}
  </div>;
 };
 return <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
  {review.fields.filter(field=>field.mandatory).map(render)}
  <div className="sm:col-span-2"><StoreShippingChannelsSection connection={{shopName}} channels={review.channels.map(channel=>({channelId:String(channel.logistics_channel_id),name:channel.logistics_channel_name,isRequiredByPlatform:!!(channel.force_enable||channel.compulsory_channel),requiresSizeSelection:channel.fee_type==='SIZE_SELECTION',requiresSellerFee:channel.fee_type==='CUSTOM_PRICE',sizeOptions:channel.size_list?.map(size=>({id:String(size.size_id),label:size.name??String(size.size_id),maxDimCm:0}))}))}
   selectedChannels={Object.fromEntries(Object.entries(review.selectedChannels).map(([id,value])=>[id,{enabled:true,selectedSizeTier:value.size_id===undefined?undefined:String(value.size_id),sellerFeeMyr:value.shipping_fee}]))}
   isLoading={review.loading} fetchError={review.error||(!review.loading&&!review.channels.length?'No available shipping channels for this store.':null)} onRefresh={review.retry}
   onChange={next=>{for(const channel of review.channels){const id=channel.logistics_channel_id,value=next[String(id)],prior=review.selectedChannels[id];if(value?.enabled){const size=channel.size_list?.find(s=>String(s.size_id)===value.selectedSizeTier);if(!prior||String(prior.size_id??'')!==String(size?.size_id??'')||prior.shipping_fee!==value.sellerFeeMyr)review.updateChannel(id,{...prior,logistic_id:id,is_free:prior?.is_free??false,size_id:size?.size_id,shipping_fee:value.sellerFeeMyr});}else if(prior&&!channel.force_enable&&!channel.compulsory_channel)review.updateChannel(id,null);}}}/></div>
  <div className="sm:col-span-2"><ShopeeOptionalAttributes review={review}/></div>
 </div>;
}
