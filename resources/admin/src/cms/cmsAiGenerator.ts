import type {CmsPage,PageDocument,PageSection} from '../types';
export type QuestionType='text'|'select'|'radio'|'multiple_select';
export interface BriefQuestion {id:string;type:QuestionType;question:string;help:string;required:boolean;options:string[]}
export type BriefAnswers=Record<string,string|string[]>;
export interface AiBrief {summary:string;questions:BriefQuestion[];warnings:string[];sections:{id:string;purpose:string}[];provisional?:boolean}
export function questionError(q:BriefQuestion,value:string|string[]|undefined):string {
 if(q.type==='multiple_select'){
  if(value!==undefined&&(!Array.isArray(value)||value.some(v=>!q.options.includes(v))))return 'Choose from the available answers.';
  if(q.required&&(!Array.isArray(value)||!value.length))return 'Choose at least one answer.';
 }else{
  if(value!==undefined&&typeof value!=='string')return 'Enter a valid answer.';
  if(q.required&&!(value as string|undefined)?.trim())return 'Please answer this question.';
  if(q.type!=='text'&&value&&!q.options.includes(value as string))return 'Choose from the available answers.';
 }
 return '';
}
export function reorderSections<T>(sections:T[],from:number,to:number):T[]{
 if(from<0||to<0||from>=sections.length||to>=sections.length)return sections;
 const next=[...sections];next.splice(to,0,next.splice(from,1)[0]);return next;
}
export function pageToAiDocument(page:CmsPage):PageDocument {
 return {title:page.title,slug:page.slug,template:page.template,sections:structuredClone(page.sections),
 ...Object.fromEntries(['description','heroImage','seoTitle','seoDescription','style','containerWidth','headerPresentation','shellDesign'].filter(k=>page[k as keyof CmsPage]!==undefined).map(k=>[k,structuredClone(page[k as keyof CmsPage])]))};
}
export const pageSourceSignature=(page:CmsPage)=>JSON.stringify(pageToAiDocument(page));
export function documentChanges(before:PageDocument,after:PageDocument){
 const changes:{section:string;field:string;before:string;after:string}[]=[];
 const walk=(a:unknown,b:unknown,path:string,section:string)=>{
  if(JSON.stringify(a)===JSON.stringify(b))return;
  if(a&&b&&typeof a==='object'&&typeof b==='object'&&!Array.isArray(a)&&!Array.isArray(b)){
   for(const key of new Set([...Object.keys(a),...Object.keys(b)]))walk((a as Record<string,unknown>)[key],(b as Record<string,unknown>)[key],path?`${path}.${key}`:key,section);
  }else if(Array.isArray(a)&&Array.isArray(b)&&a.length===b.length){a.forEach((v,i)=>walk(v,b[i],`${path}.${i+1}`,section));}
  else changes.push({section,field:path,before:typeof a==='string'?a:JSON.stringify(a)??'—',after:typeof b==='string'?b:JSON.stringify(b)??'—'});
 };
 before.sections.forEach((s,i)=>walk(s,after.sections.find(n=>n.id===s.id),'' ,s.title||`Section ${i+1}`));
 if(before.sections.map(s=>s.id).join()!==after.sections.map(s=>s.id).join())changes.push({section:'Page',field:'Section order',before:before.sections.map(s=>s.title).join(' → '),after:after.sections.map(s=>s.title).join(' → ')});
 return changes;
}
