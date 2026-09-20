import type {PublishingSource} from '../../integration/marketplacePublishing';
import {orderedPublishingVariants} from '../../integration/marketplaceVariantOrdering';
import type {MarketplaceAttributeField, MarketplaceAttributeOption, MarketplaceAttributeValue} from './types';

type LazadaAttribute={
  name?:unknown;
  label?:unknown;
  description?:unknown;
  attribute_type?:unknown;
  input_type?:unknown;
  is_mandatory?:unknown;
  is_sale_prop?:unknown;
  max_value_count?:unknown;
  options?:unknown;
};

export interface LazadaSerializedAttributes {
  normal:Record<string,string>;
  skus:Record<string,Record<string,string>>;
  imageBindings:Record<string,number>;
}

const safeName=(value:unknown):value is string=>typeof value==='string'
  && /^[_a-zA-Z][_a-zA-Z0-9]*$/.test(value)
  && !['__proto__','constructor','prototype'].includes(value);

const typeControl:Record<string,MarketplaceAttributeField['control']>={
  text:'text',richtext:'textarea',numeric:'decimal',date:'date',singleselect:'single',
  multiselect:'multi',enuminput:'single',multienuminput:'multi',img:'image',
};

const optionsFrom=(value:unknown):MarketplaceAttributeOption[]=>{
  if(!Array.isArray(value))return [];
  const seen=new Set<string>();
  return value.flatMap((option,index)=>{
    if(!option||typeof option!=='object')return [];
    const candidate=option as Record<string,unknown>;
    const label=typeof candidate.en_name==='string'?candidate.en_name.trim():'';
    if(!label||seen.has(label.toLocaleLowerCase()))return [];
    seen.add(label.toLocaleLowerCase());
    const rawId=candidate.id??candidate.value_id??candidate.name??label;
    return [{id:String(rawId||index),label}];
  });
};

const positiveLimit=(value:unknown,fallback:number):number=>{
  const parsed=Number(value);
  return Number.isSafeInteger(parsed)&&parsed>0?parsed:fallback;
};

const isSizeChart=(attribute:LazadaAttribute):boolean=>attribute.attribute_type==='normal'
  && (/size[_ ]?chart/i.test(String(attribute.name??''))||/size chart/i.test(String(attribute.label??'')));

export function normalizeLazadaAttributes(
  schema:unknown,
  source:PublishingSource,
  _values:ReadonlyMap<string,MarketplaceAttributeValue>=new Map(),
  brands:string[]=[],
):MarketplaceAttributeField[] {
  if(!Array.isArray(schema))throw new Error('Category attributes could not be read.');
  const identities=new Set<string>();
  const fields:MarketplaceAttributeField[]=[];

  for(const raw of schema){
    if(!raw||typeof raw!=='object')throw new Error('Category attributes could not be read.');
    const attribute=raw as LazadaAttribute;
    if(!safeName(attribute.name)||!['normal','sku'].includes(String(attribute.attribute_type))
      ||typeof attribute.input_type!=='string'||![0,1,false,true].includes(attribute.is_mandatory as 0|1|boolean)){
      throw new Error('Category attributes could not be read.');
    }
    if(attribute.name==='__images__')continue;
    const providerName=attribute.name;
    const scope=attribute.attribute_type==='sku'?'variant':'product';
    const identity=`${scope}:${providerName}`;
    if(identities.has(identity))throw new Error('Duplicate category attribute.');
    identities.add(identity);

    const inputType=attribute.input_type.toLocaleLowerCase();
    const sizeChart=isSizeChart(attribute);
    const control=sizeChart?'image':typeControl[inputType];
    const options=optionsFrom(attribute.options);
    if(providerName==='brand'&&scope==='product'&&inputType==='singleselect'&&!options.length){
      for(const label of [...new Set(['No Brand',...brands.map(value=>value.trim()).filter(Boolean)])]){
        options.push({id:label,label});
      }
    }
    const allowCustom=inputType==='enuminput'||inputType==='multienuminput';
    // Lazada's CreateProduct business validation requires Brand even when some
    // category metadata snapshots report the field as optional.
    const required=Boolean(attribute.is_mandatory)||(scope==='product'&&providerName==='brand');
    const targets=scope==='variant'?orderedPublishingVariants(source.options,source.variants):[undefined];
    for(const variant of targets){
      const variantRequired=required;
      const key=variant
        ?`lazada:variant:${variant.sku}:${providerName}`
        :sizeChart?`lazada:size-chart:${providerName}`:`lazada:product:${providerName}`;
      fields.push({
        key,provider:'lazada',scope,providerName,providerId:providerName,sku:variant?.sku,
        label:typeof attribute.label==='string'&&attribute.label.trim()?attribute.label:providerName,
        control:control??'text',required:variantRequired,options,allowCustom,
        maxValues:control==='multi'?positiveLimit(attribute.max_value_count,100):1,
        units:[],
        help:typeof attribute.description==='string'&&attribute.description.trim()?attribute.description:undefined,
        supported:Boolean(control),
        unsupportedReason:control?undefined:`${String(attribute.input_type)} attributes cannot be completed yet.`,
        purpose:sizeChart?'size-chart':undefined,
        saleProperty:scope==='variant'&&attribute.is_sale_prop==1,
      });
    }
  }
  return fields;
}

const hasValue=(value:MarketplaceAttributeValue|undefined):boolean=>{
  if(!value||value.kind==='empty')return false;
  if(value.kind==='multi')return value.values.length>0;
  if(value.kind==='single')return Boolean(value.value.label.trim());
  if(value.kind==='image')return value.imageId>0;
  return Boolean(value.value.trim());
};

export function validateLazadaSaleProperties(
  fields:MarketplaceAttributeField[],
  values:ReadonlyMap<string,MarketplaceAttributeValue>,
  variantCount:number,
):string[] {
  if(variantCount<2)return [];
  const saleFields=fields.filter(field=>field.scope==='variant'&&field.saleProperty);
  if(saleFields.length===0)return [];
  const selected=[...new Set(saleFields.filter(field=>hasValue(values.get(field.key))).map(field=>field.providerName))];
  if(selected.length===0)return ['Choose one variant attribute that distinguishes every SKU. Lazada allows at most two.'];
  if(selected.length>2)return [`Lazada allows at most two variant attributes. Keep one or two and clear: ${selected.slice(2).join(', ')}.`];
  for(const name of selected){
    const group=saleFields.filter(field=>field.providerName===name);
    if(group.length!==variantCount||group.some(field=>!hasValue(values.get(field.key)))){
      const label=group[0]?.label??name;
      return [`Complete ${label} for every SKU, or clear it from every SKU.`];
    }
  }
  const tuples=saleFields.filter(field=>selected.includes(field.providerName)).reduce<Record<string,string[]>>((all,field)=>{
    const value=scalar(values.get(field.key)??{kind:'empty'})??'';
    (all[field.sku??'']??=[]).push(`${field.providerName}:${value}`);return all;
  },{});
  if(new Set(Object.values(tuples).map(parts=>parts.sort().join('|'))).size!==variantCount){
    return ['Each SKU needs a unique combination of the selected variant attributes.'];
  }
  return [];
}

const optionAllowed=(field:MarketplaceAttributeField,label:string,custom:boolean):boolean=>{
  if(custom)return field.allowCustom;
  return field.options.some(option=>option.label===label);
};

const validIsoDate=(value:string):boolean=>{
  if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;
  const date=new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(date.valueOf())&&date.toISOString().slice(0,10)===value;
};

export function validateAttributeValue(field:MarketplaceAttributeField,value:MarketplaceAttributeValue):string|null {
  if(!field.supported)return field.required?`${field.label} cannot be completed yet.`:null;
  if(value.kind==='empty')return field.required?(field.purpose==='size-chart'?'Select a Size Chart image.':`${field.label} is required.`):null;
  if(field.control==='text'||field.control==='textarea'){
    return value.kind==='text'&&value.value.trim()?null:`${field.label} must contain text.`;
  }
  if(field.control==='integer'||field.control==='decimal'){
    if(value.kind!=='number'||!value.value.trim()||!Number.isFinite(Number(value.value)))return `${field.label} must be a finite number.`;
    if(field.control==='integer'&&!Number.isInteger(Number(value.value)))return `${field.label} must be a whole number.`;
    return null;
  }
  if(field.control==='date')return value.kind==='date'&&validIsoDate(value.value)?null:`${field.label} must be a valid date.`;
  if(field.control==='single'){
    if(value.kind!=='single'||!value.value.label.trim())return `${field.label} requires one value.`;
    return optionAllowed(field,value.value.label,value.value.custom)?null:`${field.label} must use the provided options.`;
  }
  if(field.control==='multi'){
    if(value.kind!=='multi'||!value.values.length)return field.required?`${field.label} requires at least one value.`:null;
    if(value.values.length>field.maxValues)return `${field.label} accepts at most ${field.maxValues} values.`;
    const labels=value.values.map(item=>item.label.trim().toLocaleLowerCase());
    if(new Set(labels).size!==labels.length)return `${field.label} contains a duplicate value.`;
    if(value.values.some(item=>!item.label.trim()||!optionAllowed(field,item.label,item.custom)))return `${field.label} must use the provided options.`;
    return null;
  }
  if(field.control==='image')return value.kind==='image'&&Number.isSafeInteger(value.imageId)&&value.imageId>0
    ?null:field.purpose==='size-chart'?'Select a Size Chart image.':`${field.label} requires a product image.`;
  return `${field.label} cannot be completed yet.`;
}

const scalar=(value:MarketplaceAttributeValue):string|null=>{
  if(value.kind==='text'||value.kind==='number'||value.kind==='date')return value.value.trim()||null;
  if(value.kind==='single')return value.value.label.trim()||null;
  if(value.kind==='multi')return value.values.map(item=>item.label.trim()).filter(Boolean).join(',')||null;
  return null;
};

export function serializeLazadaAttributes(
  fields:MarketplaceAttributeField[],
  values:ReadonlyMap<string,MarketplaceAttributeValue>,
):LazadaSerializedAttributes {
  const normal:Record<string,string>={};
  const skus:Record<string,Record<string,string>>={};
  const imageBindings:Record<string,number>={};
  for(const field of fields){
    const value=values.get(field.key);
    if(!value||value.kind==='empty')continue;
    if(value.kind==='image'){
      if(validateAttributeValue(field,value)===null)imageBindings[field.key]=value.imageId;
      continue;
    }
    const serialized=scalar(value);
    if(serialized===null)continue;
    if(field.scope==='variant'&&field.sku)(skus[field.sku]??={})[field.providerName]=serialized;
    else normal[field.providerName]=serialized;
  }
  return {normal,skus,imageBindings};
}
