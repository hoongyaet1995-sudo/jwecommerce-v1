import React from 'react';
import {MarketplaceAttributeControl} from './MarketplaceAttributeControl';
import {OptionalAttributesSection,type PlatformAttributeMeta} from './publish37/OptionalAttributesSection';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';
import type {MarketplaceImageCandidate} from './MarketplaceImageAttribute';
import type {MarketplaceAttributeImageReceipt} from '../integration/marketplaceAttributeImages';

export interface MarketplaceAttributeSectionsProps {
 fields:MarketplaceAttributeField[];
 values:ReadonlyMap<string,MarketplaceAttributeValue>;
 units?:ReadonlyMap<string,string>;
 errors:Record<string,string|undefined>;
 onChange:(key:string,value:MarketplaceAttributeValue)=>void;
 onUnitChange?:(key:string,unit:string)=>void;
 supplemental?:(field:MarketplaceAttributeField)=>React.ReactNode;
 imageContext?:{productId:number;images:MarketplaceImageCandidate[];platform:'shopee'|'lazada';onUploaded?:(receipt:MarketplaceAttributeImageReceipt)=>void};
}

const empty:MarketplaceAttributeValue={kind:'empty'};
const filledValue=(value:MarketplaceAttributeValue|undefined):unknown=>{
 if(!value||value.kind==='empty')return '';
 if(value.kind==='multi')return value.values.map(item=>item.label);
 if(value.kind==='single')return value.value.label;
 if(value.kind==='image')return value.url;
 return value.value;
};

export function MarketplaceAttributeSections({fields,values,units=new Map(),errors,onChange,onUnitChange,supplemental,imageContext}:MarketplaceAttributeSectionsProps){
 const required=fields.filter(field=>field.required);
 const optional=fields.filter(field=>!field.required);
 const metadata:PlatformAttributeMeta[]=optional.map(field=>({
  id:field.key,name:field.label,inputType:'text',description:field.help,
 }));
 const optionalValues=Object.fromEntries(optional.map(field=>[field.key,filledValue(values.get(field.key))]));
 const render=(field:MarketplaceAttributeField)=><div key={field.key} className="space-y-1.5 relative">
  <div className="flex items-center justify-between"><label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5"><span>{field.label}{field.sku?` · ${field.sku}`:''}</span>{field.required&&<span className="text-rose-500">*</span>}{field.dependent&&<span className="text-[10px] font-medium text-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 px-1.5 py-0.5 rounded">Dependent</span>}</label></div>
  <MarketplaceAttributeControl field={field} value={values.get(field.key)??empty} unit={units.get(field.key)??''} error={errors[field.key]} onChange={value=>onChange(field.key,value)} onUnitChange={unit=>onUnitChange?.(field.key,unit)} supplemental={supplemental?.(field)} imageContext={imageContext}/>
 </div>;
 return <div className="space-y-4">
  {required.length>0&&<div className="grid grid-cols-1 sm:grid-cols-2 gap-4">{required.map(render)}</div>}
  {optional.length>0&&<OptionalAttributesSection attributesMeta={metadata} values={optionalValues} onChange={()=>{}} renderField={meta=>render(optional.find(field=>field.key===meta.id)!)} />}
 </div>;
}
