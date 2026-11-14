import type {PageSection} from '../types';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
export const copyLabels:Record<string,string>={eyebrow:'Eyebrow',subtitle:'Secondary text',title:'Title',content:'Description',leadText:'Introduction',buttonText:'CTA 1',secondaryButtonText:'CTA 2',description:'Description',badge:'Badge',caption:'Caption',thirdButtonText:'CTA 3'};
export function copyCapability(section:PageSection){
 const text=['title','eyebrow','subtitle','content','leadText','buttonText','secondaryButtonText'];
 let sectionFields=sectionContentFields(section).filter(k=>text.includes(k));
 if(section.type==='hero_slider'&&['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??''))sectionFields=[];
 const protectedItems=['faq_accordion','testimonials_slider','policy_content','payment_methods','delivery_table','product_listing','product_grid','promotion_highlights'].includes(section.type)||(section.type==='chapter_block'&&['policy','delivery'].includes(section.chapterPreset??''));
 const itemFields=protectedItems?[]:repeatedItemFields(section).filter(k=>['title','eyebrow','subtitle','description','buttonText','secondaryButtonText','thirdButtonText','caption','badge'].includes(k));
 return {section:sectionFields,items:itemFields};
}
export function writableCopy(section:PageSection,item?:Record<string,unknown>){
 const keys=copyCapability(section)[item?'items':'section'];const record=item??section;
 return keys.filter(k=>{const link=({buttonText:item?'linkUrl':'buttonLink',secondaryButtonText:'secondaryButtonLink',thirdButtonText:'thirdButtonLink'} as Record<string,string>)[k];if(!link)return true;const value=record[link as keyof typeof record];return typeof value==='string'&&(/^(\/(?!\/)|https?:\/\/|mailto:|tel:|#)/i.test(value))&&!/[\u0000-\u0020]/.test(value);});
}
