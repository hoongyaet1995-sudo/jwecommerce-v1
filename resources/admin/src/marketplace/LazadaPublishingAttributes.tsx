import React from 'react';
import type {LazadaPublishingField} from '../integration/lazadaPublishingFields';
import {MarketplaceAttributeSections} from './MarketplaceAttributeSections';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';

const control=(field:LazadaPublishingField):MarketplaceAttributeField['control']=>{
 switch(field.type){
  case 'richtext':return 'textarea';case 'numeric':return 'decimal';case 'date':return 'date';
  case 'singleselect':case 'enuminput':return 'single';
  case 'multiselect':case 'multienuminput':return 'multi';case 'img':return 'image';default:return 'text';
 }
};
const typed=(field:LazadaPublishingField):MarketplaceAttributeValue=>{
 if(!field.value.trim())return {kind:'empty'};
 if(field.type==='numeric')return {kind:'number',value:field.value};
 if(field.type==='date')return {kind:'date',value:field.value};
 if(['singleselect','enuminput'].includes(field.type))return {kind:'single',value:{id:field.options.includes(field.value)?field.value:null,label:field.value,custom:field.type==='enuminput'&&!field.options.includes(field.value)}};
 if(['multiselect','multienuminput'].includes(field.type))return {kind:'multi',values:field.value.split(',').map(label=>label.trim()).filter(Boolean).map(label=>({id:field.options.includes(label)?label:null,label,custom:field.type==='multienuminput'&&!field.options.includes(label)}))};
 return {kind:'text',value:field.value};
};
const serialized=(value:MarketplaceAttributeValue):string=>{
 if(value.kind==='text'||value.kind==='number'||value.kind==='date')return value.value;
 if(value.kind==='single')return value.value.label;
 if(value.kind==='multi')return value.values.map(item=>item.label).join(',');
 return '';
};

export function LazadaPublishingAttributes({fields,onChange}:{fields:LazadaPublishingField[];onChange:(key:string,value:string)=>void}){
 const legacy=fields.filter(field=>!field.sizeChart&&(!field.automatic||!field.complete));
 const canonical=legacy.map((field):MarketplaceAttributeField=>({
  key:field.sku?`lazada:variant:${field.sku}:${field.name}`:`lazada:product:${field.name}`,
  provider:'lazada',scope:field.sku?'variant':'product',providerName:field.name,providerId:field.name,sku:field.sku,
  label:field.label,control:control(field),required:field.required,
  options:field.options.map(option=>({id:option,label:option})),allowCustom:['enuminput','multienuminput'].includes(field.type),
  maxValues:['multiselect','multienuminput'].includes(field.type)?100:1,units:[],supported:field.supported,
  unsupportedReason:field.supported?undefined:'This required field cannot be completed yet.',
 }));
 const values=new Map(canonical.map((field,index)=>[field.key,typed(legacy[index])]));
 const errors=Object.fromEntries(canonical.flatMap((field,index)=>legacy[index].required&&!legacy[index].complete?[[field.key,field.supported?`Complete ${field.label}${field.sku?` for ${field.sku}`:''}.`:'This required field cannot be completed yet.']]:[]));
 return <MarketplaceAttributeSections fields={canonical} values={values} errors={errors} onChange={(key,value)=>{
  const index=canonical.findIndex(field=>field.key===key);if(index>=0)onChange(legacy[index].key,serialized(value));
 }}/>;
}

