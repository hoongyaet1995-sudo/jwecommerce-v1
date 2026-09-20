import {useCallback,useEffect,useRef,useState} from 'react';
import type {MarketplaceAttributeField,MarketplaceAttributeValue} from './publishingAttributes/types';

type Snapshot={schemaKey:string;fields:Map<string,MarketplaceAttributeField>;values:Map<string,MarketplaceAttributeValue>;units:Map<string,string>};
const blank=(schemaKey=''):Snapshot=>({schemaKey,fields:new Map(),values:new Map(),units:new Map()});
const compatible=(before:MarketplaceAttributeField,after:MarketplaceAttributeField):boolean=>
 before.provider===after.provider&&before.scope===after.scope&&before.providerId===after.providerId
 &&before.providerName===after.providerName&&before.sku===after.sku&&before.control===after.control;
const permitted=(field:MarketplaceAttributeField,value:MarketplaceAttributeValue):boolean=>{
 if(value.kind==='single'&&!value.value.custom)return field.options.some(option=>option.id===value.value.id&&option.label===value.value.label);
 if(value.kind==='multi')return value.values.every(item=>item.custom?field.allowCustom:field.options.some(option=>option.id===item.id&&option.label===item.label));
 return true;
};

export function useMarketplaceAttributeValues(
 schemaKey:string,
 fields:MarketplaceAttributeField[],
 initial?:(field:MarketplaceAttributeField)=>MarketplaceAttributeValue|undefined,
){
 const carry=useRef<Snapshot>(blank());
 const [active,setActive]=useState<Snapshot>(blank(schemaKey));
 useEffect(()=>{
  if(!schemaKey||!fields.length){setActive(blank(schemaKey));return;}
  const nextFields=new Map(fields.map(field=>[field.key,field]));
  const nextValues=new Map<string,MarketplaceAttributeValue>();
  const nextUnits=new Map<string,string>();
  for(const field of fields){
   const prior=carry.current.fields.get(field.key);
   const value=carry.current.values.get(field.key);
   if(prior&&value&&compatible(prior,field)&&permitted(field,value)){
    nextValues.set(field.key,value);
    const unit=carry.current.units.get(field.key);
    if(unit&&field.units.includes(unit))nextUnits.set(field.key,unit);
   }else{
    const seeded=initial?.(field);
    if(seeded&&seeded.kind!=='empty'&&permitted(field,seeded))nextValues.set(field.key,seeded);
   }
  }
  const next={schemaKey,fields:nextFields,values:nextValues,units:nextUnits};
  carry.current=next;setActive(next);
 },[schemaKey,fields,initial]);
 const update=useCallback((key:string,value:MarketplaceAttributeValue)=>setActive(current=>{
  if(current.schemaKey!==schemaKey||!current.fields.has(key))return current;
  const values=new Map(current.values);if(value.kind==='empty')values.delete(key);else values.set(key,value);
  const next={...current,values};carry.current=next;return next;
 }),[schemaKey]);
 const updateUnit=useCallback((key:string,unit:string)=>setActive(current=>{
  if(current.schemaKey!==schemaKey||!current.fields.has(key))return current;
  const units=new Map(current.units);if(unit)units.set(key,unit);else units.delete(key);
  const next={...current,units};carry.current=next;return next;
 }),[schemaKey]);
 return {values:active.schemaKey===schemaKey?active.values:new Map<string,MarketplaceAttributeValue>(),units:active.schemaKey===schemaKey?active.units:new Map<string,string>(),update,updateUnit};
}
