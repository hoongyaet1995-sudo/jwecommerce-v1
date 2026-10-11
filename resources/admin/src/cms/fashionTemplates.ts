import snapshot from './fashionTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';

/** Each draft owns fresh item/slot identities and resolves only the current catalog. */
export function fashionSections(context:PageTemplateContext,prefix:string):PageSection[]{
 const products=(context.products??[]).filter(product=>product.status==='active');
 const categories=(context.categories??[]).filter(category=>category.status==='active');
 const patterns:Record<string,RegExp>={Women:/\bwomen\b/i,Men:/\bmen\b/i,Accessories:/\baccessor/i};
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-fashion-${index}`;
  const ids=new Map<string,string>();
  section.items=section.items?.map((item,i)=>{
   const id=`${section.id}-item-${i}`;ids.set(item.id,id);return {...item,id};
  });
  section.imageSlots=section.imageSlots?.map(slot=>{
   for(const [old,id] of ids)if(slot.target.startsWith(`item:${old}:`))return {...slot,target:slot.target.replace(`item:${old}:`,`item:${id}:`)};
   return slot;
  });
  if(section.type==='product_listing'){
   section.productIds=products.slice(0,section.productLimit??4).map(product=>product.id);
   section.productTabs=section.productTabs?.map((tab,i)=>{
    const pattern=patterns[tab.label];
    const category=pattern?categories.find(category=>pattern.test(category.name+' '+category.slug)):undefined;
    return {...tab,id:`${section.id}-tab-${i}`,sourceType:category?'category':'all',sourceId:category?.id??''};
   });
  }
  return section;
 });
}
