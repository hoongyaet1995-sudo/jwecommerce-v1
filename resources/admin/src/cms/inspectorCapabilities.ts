import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {fontFamilies,type FontFamily} from './fontCatalog';
import type {CSSProperties} from 'react';
import type {PageSection} from '../types';
export type InspectorTab='content'|'design'|'advanced';
export type TextRole='eyebrow'|'title'|'lead'|'content'|'itemTitle'|'itemContent';
export interface TextStyle {fontFamily?:FontFamily;fontSize?:number;fontWeight?:number;lineHeight?:number;letterSpacing?:number;color?:string}
export type SectionTypography=Partial<Record<TextRole,TextStyle>>;
export const textRoleLabels:Record<TextRole,string>={eyebrow:'Eyebrow',title:'Heading',lead:'Introduction',content:'Description',itemTitle:'Item headings',itemContent:'Item descriptions'};
export const roleFields:Record<TextRole,string>={eyebrow:'subtitle',title:'title',lead:'leadText',content:'content',itemTitle:'items',itemContent:'items'};
export const inspectorGroups:Array<{name:string;tab:InspectorTab}>=[
 {name:'Collection layout',tab:'design'},{name:'Text & hierarchy',tab:'content'},
 {name:'Split hero card',tab:'content'},{name:'Ticker speed',tab:'design'},
 {name:'Collection mappings',tab:'content'},{name:'Featured products',tab:'content'},
 {name:'Product listing source',tab:'content'},{name:'Deal configuration',tab:'content'},
 {name:'Before & after assets',tab:'content'},{name:'Video source',tab:'content'},
 {name:'Comparison labels',tab:'content'},{name:'Contact form',tab:'content'},
 {name:'Newsletter & coupon',tab:'content'},{name:'Slider behavior',tab:'design'},
 {name:'Chapter content',tab:'content'},{name:'Media text layout',tab:'design'},
 {name:'Values layout',tab:'design'},{name:'CTA layout',tab:'design'},
 {name:'Rich text layout',tab:'design'},{name:'Image assets',tab:'content'},
 {name:'Main media',tab:'content'},{name:'Buttons & links',tab:'content'},
 {name:'Value entries',tab:'content'},{name:'Repeatable entries',tab:'content'},
 {name:'Layout & theme',tab:'design'},{name:'Motion',tab:'design'},{name:'Palette & colors',tab:'design'},
];
export function textRoles(section:PageSection):TextRole[] {
 if(section.type==='hero_slider'&&section.sliderPreset==='editorial-overlay')return ['eyebrow','title','content'];
 if(section.type==='chapter_navigation')return ['eyebrow'];
 if(section.type==='announcement_bar')return ['itemTitle'];
 const roles:TextRole[]=[];
 const contentFields=sectionContentFields(section),itemFields=repeatedItemFields(section);
 const hidden=section.showSectionHeading===false||section.type==='values_list'&&!section.valuesShowHeading||section.type==='chapter_block'&&section.chapterPreset==='principles';
 if(contentFields.includes('subtitle') && section.showSectionHeading!==false)roles.push('eyebrow');
 if(!hidden&&contentFields.includes('title'))roles.push('title');
 if(contentFields.includes('leadText'))roles.push('lead');
 if(!hidden&&contentFields.includes('content'))roles.push('content');
 if(section.type==='product_listing'&&['editorial-grid','editorial-tabs'].includes(section.listingPreset??''))roles.push('itemTitle');
 if(['payment_methods','promotion_highlights'].includes(section.type))roles.push('itemTitle','itemContent');
 if(itemFields.includes('title'))roles.push('itemTitle');
 if((section.type==='delivery_table'||section.type==='chapter_block'&&section.chapterPreset==='delivery')&&section.deliverySource!=='manual')roles.push('itemTitle','itemContent');
 if(itemFields.includes('description')||itemFields.includes('deliveryEstimate'))roles.push('itemContent');
 return [...new Set(roles)].filter(role=>{
  if(['stats_counter','icon_list'].includes(section.type)&&['title','content'].includes(role))return false;
  if(section.type==='hero_slider'&&role.startsWith('item'))return false;
  if(section.type==='instagram_feed'&&role==='itemTitle')return false;
  if((section.galleryPreset==='editorial-montage'||section.type==='chapter_block'&&section.chapterPreset==='perspective')&&role.startsWith('item'))return false;
  return true;
 });
}
export function isTypography(value:unknown):value is SectionTypography {
 if(!value||typeof value!=='object'||Array.isArray(value))return false;
 return Object.entries(value).every(([role,style])=>Object.prototype.hasOwnProperty.call(textRoleLabels,role)&&style&&typeof style==='object'&&!Array.isArray(style)&&Object.entries(style).every(([key,v])=>{
  if(key==='fontFamily')return typeof v==='string'&&Object.prototype.hasOwnProperty.call(fontFamilies,v);
  if(key==='color')return typeof v==='string'&&/^#[\da-f]{6}$/i.test(v);
  if(typeof v!=='number'||!Number.isFinite(v))return false;
  if(key==='fontSize')return Number.isInteger(v)&&v>=8&&v<=120;
  if(key==='fontWeight')return [400,500,600,700,800,900].includes(v);
  if(key==='lineHeight')return v>=.8&&v<=3;
  if(key==='letterSpacing')return v>=-5&&v<=10;
  return false;
 }));
}
export function typographyStyle(section:PageSection,role:TextRole):CSSProperties {
 const value=section.typography?.[role];if(!value)return {};
 return {...(value.fontFamily?{fontFamily:fontFamilies[value.fontFamily]}:{}),
  ...(value.fontSize?{fontSize:`clamp(${Math.max(8,Math.round(value.fontSize*.7))}px, ${value.fontSize/7.68}cqi, ${value.fontSize}px)`}:{}),
  ...(value.fontWeight?{fontWeight:value.fontWeight}:{}),...(value.lineHeight?{lineHeight:value.lineHeight}:{}),...(value.letterSpacing!==undefined?{letterSpacing:`${value.letterSpacing}px`}:{}),...(value.color?{color:value.color}:{}),
 };
}
