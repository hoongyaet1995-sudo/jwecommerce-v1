import solution from './logisticsSolutionTemplate.json';
import gallery from './logisticsGalleryTemplate.json';
import type {PageSection} from '../types';
import type {PageTemplateContext} from './pageTemplates';
export function logisticsDetailSections(id:string,context:PageTemplateContext,prefix:string):PageSection[] {
 const snapshot=id==='logistics-media-gallery'?gallery:solution;
 const products=(context.products??[]).filter(p=>p.status==='active'&&/container/i.test(p.title));
 return (structuredClone(snapshot.sections) as PageSection[]).map((section,index)=>{
  section.id=`${prefix}-detail-${index}`;
  const oldIds=new Map<string,string>();
  section.items=section.items?.map((item,i)=>{const next=`${section.id}-item-${i}`;oldIds.set(item.id,next);return {...item,id:next};});
  section.imageSlots=section.imageSlots?.map(slot=>({...slot,target:slot.target.replace(/^item:([^:]+):/,(_,old)=>`item:${oldIds.get(old)??old}:`)}));
  if(section.type==='product_listing')section.productIds=products.slice(0,2).map(p=>p.id);
  return section;
 });
}
