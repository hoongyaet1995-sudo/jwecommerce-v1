import snapshot from './beautyTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';
/** Starter photography is illustrative; commerce always resolves from the merchant catalog. */
export function beautySections(context:PageTemplateContext,prefix:string):PageSection[]{
 const products=(context.products??[]).filter(p=>p.status==='active'&&/skin|serum|cleanser|toner|moisturi|facial|beauty|cream/i.test(p.title));
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-beauty-${index}`;
  const ids=new Map<string,string>();section.items=section.items?.map((item,i)=>{const id=`${section.id}-item-${i}`;ids.set(item.id,id);return {...item,id};});
  section.imageSlots=section.imageSlots?.map(slot=>{for(const [old,id] of ids)if(slot.target.startsWith(`item:${old}:`))return {...slot,target:slot.target.replace(`item:${old}:`,`item:${id}:`)};return slot;});
  if(section.type==='product_listing')section.productIds=products.slice(0,4).map(p=>p.id);
  return section;
 });
}
