import React from 'react';
import {StoreShippingChannelsSection} from './publish37/StoreShippingChannelsSection';
import {ShopeeAttributeSearch} from './ShopeeAttributeSearch';
import {MarketplaceAttributeSections} from './MarketplaceAttributeSections';
import {normalizeShopeeAttributes,serializeShopeeAttributes,validateShopeeAttributeValue} from './publishingAttributes/shopee';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';
import {type AttributeValue,useShopeePublishingRequirements} from './useShopeePublishingRequirements';

type Review=ReturnType<typeof useShopeePublishingRequirements>;

const providerDateToIso=(value:string,dateFormatType:number|undefined):string=>{
 if(dateFormatType===1){const [month,year]=value.split('/');return month&&year?`${year}-${month}`:value;}
 const [day,month,year]=value.split('/');return day&&month&&year?`${year}-${month}-${day}`:value;
};
const typedValue=(field:MarketplaceAttributeField,values:AttributeValue[]):MarketplaceAttributeValue=>{
 if(!values.length)return {kind:'empty'};
 if(field.control==='single')return {kind:'single',value:{id:values[0].value_id>0?String(values[0].value_id):null,label:values[0].original_value_name,custom:values[0].value_id===0}};
 if(field.control==='multi')return {kind:'multi',values:values.map(value=>({id:value.value_id>0?String(value.value_id):null,label:value.original_value_name,custom:value.value_id===0}))};
 if(field.control==='integer'||field.control==='decimal')return {kind:'number',value:values[0].original_value_name};
 if(field.control==='date')return {kind:'date',value:providerDateToIso(values[0].original_value_name,field.dateFormatType)};
 return {kind:'text',value:values[0].original_value_name};
};

export function ShopeeAttributeSections({review,onlyOptional=false}:{review:Review;onlyOptional?:boolean}){
 const fields=review.canonicalFields??normalizeShopeeAttributes(review.fields??[],review.values??{});
 const visible=onlyOptional?fields.filter(field=>!field.required):fields;
 const values=new Map(visible.map(field=>[field.key,typedValue(field,review.values[Number(field.providerId)]??[])]));
 const units=new Map(visible.map(field=>[field.key,review.values[Number(field.providerId)]?.[0]?.value_unit??'']));
 const errors=Object.fromEntries(visible.flatMap(field=>{
  const error=validateShopeeAttributeValue(field,values.get(field.key)!,units.get(field.key)??'');return error?[[field.key,error]]:[];
 }));
 const update=(field:MarketplaceAttributeField,value:MarketplaceAttributeValue,unit=units.get(field.key)??'')=>{
  const serialized=serializeShopeeAttributes([field],new Map([[field.key,value]]),new Map([[field.key,unit]]));
  review.updateAttribute(Number(field.providerId),serialized[0]?.attribute_value_list??[]);
 };
 return <MarketplaceAttributeSections fields={visible} values={values} units={units} errors={errors} onChange={(key,value)=>{const field=visible.find(item=>item.key===key);if(field)update(field,value);}} onUnitChange={(key,unit)=>{const field=visible.find(item=>item.key===key);if(field)update(field,values.get(key)!,unit);}} supplemental={field=>field.supportSearch&&review.storeId?<ShopeeAttributeSearch key={`${review.storeId}:${field.providerId}`} storeId={review.storeId} attributeId={Number(field.providerId)} onSelect={(value:AttributeValue)=>review.updateAttribute(Number(field.providerId),field.control==='multi'?[...(review.values[Number(field.providerId)]??[]).filter(item=>item.value_id!==value.value_id),value]:[value])}/>:null}/>;
}

export function ShopeePublishingRequirements({review,shopName='Selected Shopee store'}:{shopName?:string;review:Review}){
 return <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
  <div className="sm:col-span-2"><ShopeeAttributeSections review={review}/></div>
  <div className="sm:col-span-2"><StoreShippingChannelsSection connection={{shopName}} channels={review.channels.map(channel=>({channelId:String(channel.logistics_channel_id),name:channel.logistics_channel_name,isRequiredByPlatform:!!(channel.force_enable||channel.compulsory_channel),requiresSizeSelection:channel.fee_type==='SIZE_SELECTION',requiresSellerFee:channel.fee_type==='CUSTOM_PRICE',sizeOptions:channel.size_list?.map(size=>({id:String(size.size_id),label:size.name??String(size.size_id),maxDimCm:0}))}))}
   selectedChannels={Object.fromEntries(Object.entries(review.selectedChannels).map(([id,value])=>[id,{enabled:true,selectedSizeTier:value.size_id===undefined?undefined:String(value.size_id),sellerFeeMyr:value.shipping_fee}]))}
   isLoading={review.loading} fetchError={review.error||(!review.loading&&!review.channels.length?'No available shipping channels for this store.':null)} onRefresh={review.retry}
   onChange={next=>{for(const channel of review.channels){const id=channel.logistics_channel_id,value=next[String(id)],prior=review.selectedChannels[id];if(value?.enabled){const size=channel.size_list?.find(size=>String(size.size_id)===value.selectedSizeTier);if(!prior||String(prior.size_id??'')!==String(size?.size_id??'')||prior.shipping_fee!==value.sellerFeeMyr)review.updateChannel(id,{...prior,logistic_id:id,is_free:prior?.is_free??false,size_id:size?.size_id,shipping_fee:value.sellerFeeMyr});}else if(prior&&!channel.force_enable&&!channel.compulsory_channel)review.updateChannel(id,null);}}}/></div>
 </div>;
}
