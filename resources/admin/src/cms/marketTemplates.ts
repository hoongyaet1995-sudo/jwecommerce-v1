import snapshot from './marketTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';

export function marketSections(context:PageTemplateContext,prefix:string):PageSection[]{
 const products=(context.products??[]).filter(p=>p.status==='active');
 const categories=(context.categories??[]).filter(c=>c.status==='active');
 const patterns:Record<string,RegExp>={Home:/home|living/i,Fashion:/fashion|cloth/i,Beauty:/beauty|skin/i,Toys:/toy|kid/i,Baby:/baby/i,Tech:/tech|audio/i,Kids:/kid|toy/i};
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-market-${index}`;const ids=new Map<string,string>();
  section.items=section.items?.map((item,i)=>{const id=`${section.id}-item-${i}`;ids.set(item.id,id);const source=section.shopNavigationPreset==='cards'?categories.find(c=>patterns[item.title]?.test(c.name+' '+c.slug)):undefined;return {...item,id,...(source?{sourceType:'category' as const,sourceId:source.id}:{})};});
  section.imageSlots=section.imageSlots?.map(slot=>{for(const [old,id] of ids)if(slot.target.startsWith(`item:${old}:`))return {...slot,target:slot.target.replace(`item:${old}:`,`item:${id}:`)};return slot;});
  if(section.type==='product_listing'){
   section.sourceType='manual';
   section.productIds=products.slice(0,section.productLimit??4).map(p=>p.id);
   section.productTabs=section.productTabs?.map((tab,i)=>{const category=categories.find(c=>patterns[tab.label]?.test(c.name+' '+c.slug));return {...tab,id:`${section.id}-tab-${i}`,sourceType:category?'category':'all',sourceId:category?.id??''};});
  }
  return section;
 });
}
