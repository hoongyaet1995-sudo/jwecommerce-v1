import snapshot from './weddingTemplate.json';
import type {PageSection} from '../types';
/** Portfolio starter: authored content only, independent of commerce runtime. */
export function weddingSections(prefix:string):PageSection[]{
 const sections=structuredClone(snapshot.sections) as PageSection[];
 const anchors=new Map([['wedding-stories',`${prefix}-wedding-3`],['wedding-services',`${prefix}-wedding-3`],['wedding-enquiry',`${prefix}-wedding-8`]]);
 const link=(value:string|undefined)=>value?.replace(/^#section-(wedding-[a-z]+)$/,(_,key)=>`#section-${anchors.get(key)??key}`);
 return sections.map((section,index)=>({...section,id:`${prefix}-wedding-${index}`,buttonLink:link(section.buttonLink),items:section.items?.map((item,i)=>({...item,id:`${prefix}-wedding-${index}-item-${i}`,linkUrl:link(item.linkUrl)}))}));
}
