import type {PageSection} from '../types';
import {copyCapability} from './aiCopyContract';
export function sectionImageDirection(section:PageSection,target:string,business:string,goal:string,style:string,ratio:string){
 const match=/^item:([^:]+):/.exec(target),item=match?section.items?.find(i=>i.id===match[1]):undefined;
 const copy=copyCapability(section),fields=item?copy.items:copy.section,record=(item??section) as unknown as Record<string,unknown>;
 const message=fields.filter(k=>!k.endsWith('Text')).map(k=>record[k]).filter(v=>typeof v==='string'&&v.trim()).join('\n');
 return `Create an illustrative editorial photograph for ${business}.\nSection message:\n${message||section.title}\nPage goal: ${goal}. Visual direction: ${style}.\n${ratio} composition. Leave uncluttered space for the section copy; keep the subject clear of text and button areas. Match the subject, setting and mood to the section message.\nNo lettering, logos, invented product details, prices or unsupported business claims. Use supplied product photography for accurate product appearance.`;
}
