import type {PageSection} from '../types';
export interface HeaderPresentation {mode:'inherit'|'normal'|'transparent'|'translucent';tone:'dark'|'light'}
export const defaultHeaderPresentation:HeaderPresentation={mode:'normal',tone:'dark'};
export function resolveHeaderPresentation(page:HeaderPresentation|undefined,global:HeaderPresentation|undefined,sections:PageSection[]):HeaderPresentation {
 const preference=global??(page?.mode&&page.mode!=='inherit'?page:defaultHeaderPresentation);
 const first=sections.find(section=>section.isVisible);
 return {...preference,mode:first?.type==='hero_slider'?preference.mode==='inherit'?'normal':preference.mode:'normal'};
}
export function isHeaderPresentation(value:unknown):value is HeaderPresentation {
 if(!value||typeof value!=='object'||Array.isArray(value))return false;
 const v=value as HeaderPresentation;
 return Object.keys(v).every(key=>['mode','tone'].includes(key))&&['inherit','normal','transparent','translucent'].includes(v.mode)&&['dark','light'].includes(v.tone);
}
