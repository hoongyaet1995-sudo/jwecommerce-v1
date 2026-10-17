import {colorContrast,resolvePageStyle,resolveStyledSection,type BrandPalette} from './pageStyle';
import type {PageDocument} from '../types';
import {imageTargets} from './imageTargets';
export interface AiQualityIssue {id:string;sectionId?:string;severity:'block'|'warning';message:string}
export function aiQuality(document:PageDocument,theme?:BrandPalette):AiQualityIssue[]{
 const issues:AiQualityIssue[]=[];const add=(id:string,message:string,severity:'block'|'warning'='warning',sectionId?:string)=>issues.push({id,message,severity,sectionId});
 if(!document.title.trim())add('page-title','Add a page title before applying.','block');
 if(!document.sections.length)add('empty-page','Add at least one section.','block');
 const style=resolvePageStyle(document.style,theme);
 for(const s of document.sections){
  if(s.isVisible===false)continue;const name=s.title||s.type;
  if(!s.title.trim())add(s.id+'-title',`${name}: add a heading or review this empty section.`,'warning',s.id);
  const links=[s.buttonLink,s.secondaryButtonLink,...(s.items??[]).flatMap(i=>[i.linkUrl])].filter((l):l is string=>!!l);
  for(const link of links)if(!/^(\/(?!\/)|#[\w-]+$|https?:\/\/|mailto:|tel:)/i.test(link.trim())||/[\x00-\x1f]/.test(link))add(s.id+'-link',`${name}: replace the unsupported link destination.`,'block',s.id);
  const targets=imageTargets(s);if(targets.some(t=>!t.url))add(s.id+'-media',`${name}: one or more image placements are empty.`,'warning',s.id);
  const resolved=resolveStyledSection(s,style);const bg=resolved.customColors?.customBgColor;
  if(bg&&s.type!=='hero_banner'&&s.type!=='hero_slider')for(const role of ['title','content'] as const){const color=resolved.typography?.[role]?.color;if(color&&/^#[a-f\d]{6}$/i.test(color)&&/^#[a-f\d]{6}$/i.test(bg)&&colorContrast(color,bg)<4.5)add(s.id+'-'+role+'-contrast',`${name}: ${role==='title'?'heading':'body'} color may be difficult to read on this surface.`,'warning',s.id);}
 }
 return issues;
}
