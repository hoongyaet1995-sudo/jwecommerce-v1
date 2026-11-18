import type {PageSection} from '../types';
import {textRoles,textRoleLabels,type TextRole,type TextStyle} from './inspectorCapabilities';
import {resetTextProperty} from './styleInheritance';
export function targetRole(section:PageSection,node:HTMLElement):TextRole|undefined{
 const text=node.closest<HTMLElement>('[data-cms-text]');const role=text?.dataset.cmsText as TextRole|undefined;
 return role&&textRoles(section).includes(role)?role:undefined;
}
export function roleScope(role:TextRole):string{
 return ['itemTitle','itemContent','label','number'].includes(role)?'All items: '+textRoleLabels[role]:'This section: '+textRoleLabels[role];
}
/** The same SectionTypography fields and reset semantics as the existing Inspector. */
export function editTextStyle(section:PageSection,role:TextRole,patch:Partial<TextStyle>):PageSection{
 let next=section;const values:Partial<TextStyle>={};
 for(const key of Object.keys(patch) as Array<keyof TextStyle>){if(patch[key]===undefined)next=resetTextProperty(next,role,key);else Object.assign(values,{[key]:patch[key]});}
 if(!Object.keys(values).length)return next;
 return {...next,typography:{...next.typography,[role]:{...next.typography?.[role],...values,...(values.color?{colorOverride:true}:{})}}};
}

export type HeadingSelection={start:number;end:number;text:string};
function normalizedOffsets(value:string){const chars:string[]=[],starts:number[]=[],ends:number[]=[];for(let i=0;i<value.length;){const start=i;if(/\s/.test(value[i])){while(i<value.length&&/\s/.test(value[i]))i++;if(chars.length){chars.push(' ');starts.push(start);ends.push(i);}}else{chars.push(value[i++]);starts.push(start);ends.push(i);}}if(chars.at(-1)===' '){chars.pop();starts.pop();ends.pop();}return {text:chars.join(''),starts,ends};}
/** Translate a browser selection into the existing UTF-16 section title range contract. */
export function headingSelection(section:PageSection,element:HTMLElement,selection:Selection|null):HeadingSelection|undefined{
 if(element.dataset.cmsText!=='title'||element.dataset.cmsItem||!selection?.rangeCount||selection.isCollapsed)return;
 const range=selection.getRangeAt(0);if(!element.contains(range.startContainer)||!element.contains(range.endContainer))return;
 const prefix=document.createRange();prefix.selectNodeContents(element);prefix.setEnd(range.startContainer,range.startOffset);const start=prefix.toString().length;prefix.setEnd(range.endContainer,range.endOffset);const end=prefix.toString().length;
 const dom=normalizedOffsets(element.textContent??''),canonical=normalizedOffsets(section.title);
 if(dom.text!==canonical.text||!range.toString().trim())return;
 const a=dom.ends.findIndex(n=>n>start);let b=-1;dom.starts.forEach((n,i)=>{if(n<end)b=i});if(a<0||b<a)return;
 const first=canonical.starts[a],last=canonical.ends[b];return {start:first,end:last,text:section.title.slice(first,last)};
}
