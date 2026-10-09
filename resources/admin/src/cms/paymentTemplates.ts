import type {PageSection} from '../types';
import savedPresets from './paymentTemplatePresets.json';
export const paymentTemplateIds=['payment-essential','payment-guided','payment-editorial'] as const;

/** Approved saved layouts; new instances receive fresh section and item identities. */
export function paymentSections(id:string,prefix:string):PageSection[]{
 const source=(savedPresets as Record<string,PageSection[]>)[id];
 if(!source)return [];
 const identities=new Map<string,string>();
 source.forEach((section,index)=>{
  const next=`${prefix}-${section.id.split('-').at(-1)??index}`;
  identities.set(section.id,next);
  section.items?.forEach((item,i)=>identities.set(item.id,`${next}-item-${i}`));
 });
 const clone=(value:unknown):unknown=>{
  if(typeof value==='string'){
   if(identities.has(value))return identities.get(value);
   for(const [oldId,newId] of identities){
    if(value===`#section-${oldId}`)return `#section-${newId}`;
    if(value===`item:${oldId}:imageUrl`)return `item:${newId}:imageUrl`;
   }
   return value;
  }
  if(Array.isArray(value))return value.map(clone);
  if(value&&typeof value==='object')return Object.fromEntries(Object.entries(value).map(([key,entry])=>[key,clone(entry)]));
  return value;
 };
 return clone(source) as PageSection[];
}
