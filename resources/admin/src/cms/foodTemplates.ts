import snapshot from './foodTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';
export function foodSections(context:PageTemplateContext,prefix:string):PageSection[]{
 const foodCategoryIds=new Set((context.categories??[]).filter(c=>/^(food(?: & beverages?)?|food & drink|beverages?|pantry|coffee|tea|bakery|snacks?)$/i.test(c.name.trim())).map(c=>c.id));
 const products=(context.products??[]).filter(p=>p.status==='active'&&(foodCategoryIds.has(p.categoryId)||(p.tags??[]).some(tag=>/^(food|beverage|pantry|bakery)$/i.test(tag))));
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-food-${index}`;
  const ids=new Map<string,string>();
  section.items=section.items?.map((item,i)=>{const id=`${section.id}-item-${i}`;ids.set(item.id,id);return {...item,id};});
  section.imageSlots=section.imageSlots?.map(slot=>{for(const [old,id] of ids)if(slot.target.startsWith(`item:${old}:`))return {...slot,target:slot.target.replace(`item:${old}:`,`item:${id}:`)};return slot;});
  if(section.type==='product_listing'&&section.listingPreset==='pantry-grid')section.productIds=products.slice(0,4).map(p=>p.id);
  section.productTabs=section.productTabs?.map((tab,i)=>({...tab,id:`${section.id}-tab-${i}`,productIds:i===0?products.slice(0,4).map(p=>p.id):[]}));
  return section;
 });
}
