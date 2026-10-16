import snapshot from './logisticsTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';
/** New drafts only. Catalog identities always come from the current store. */
export function logisticsSections(context:PageTemplateContext,prefix:string):PageSection[]{
 const categories=new Set((context.categories??[]).filter(c=>/container|logistics|transport|relocation/i.test(c.name+' '+c.slug)).map(c=>c.id));
 const products=(context.products??[]).filter(p=>p.status==='active'&&(categories.has(p.categoryId)||/container|logistics|relocation/i.test(p.title+' '+(p.tags??[]).join(' '))));
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-logistics-${index}`;
  section.items=section.items?.map((item,i)=>({...item,id:`${section.id}-item-${i}`}));
  if(section.type==='product_listing')section.productIds=products.slice(0,3).map(p=>p.id);
  return section;
 });
}
