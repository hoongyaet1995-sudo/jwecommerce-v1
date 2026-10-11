import snapshot from './kidsTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';
export function kidsSections(context:PageTemplateContext,prefix:string):PageSection[]{
 const categories=new Set((context.categories??[]).filter(c=>/^(baby|babies|kids|children|toys|nursery|baby & kids)$/i.test(c.name.trim())).map(c=>c.id));
 const products=(context.products??[]).filter(p=>p.status==='active'&&(categories.has(p.categoryId)||(p.tags??[]).some(tag=>/^(baby|kids|children|toys|nursery)$/i.test(tag))));
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-kids-${index}`;section.items=section.items?.map((item,i)=>({...item,id:`${section.id}-item-${i}`}));
  if(section.type==='product_listing')section.productIds=products.slice(0,4).map(p=>p.id);
  section.productTabs=section.productTabs?.map((tab,i)=>({...tab,id:`${section.id}-tab-${i}`,productIds:products.filter(p=>(p.tags??[]).some(tag=>tag.toLowerCase()===tab.label.toLowerCase())).slice(0,4).map(p=>p.id)}));
  return section;
 });
}
