import {connectedPassport} from './connectedFamilies';
import {headingCapability,legacyTypographyRoles} from './sectionHeading';
import {isPlanComparison} from './planComparison';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {fontFamilies,type FontFamily} from './fontCatalog';
import type {CSSProperties} from 'react';
import type {PageSection} from '../types';
export type InspectorTab='content'|'design'|'advanced';
export type TextRole='eyebrow'|'title'|'lead'|'content'|'itemTitle'|'itemContent'|'secondaryTitle'|'label'|'number';
export interface TextStyle {inheritPage?:boolean;colorOverride?:boolean;fontFamily?:FontFamily;fontSize?:number;fontWeight?:number;lineHeight?:number;letterSpacing?:number;color?:string}
export type SectionTypography=Partial<Record<TextRole,TextStyle>>;
export const textRoleLabels:Record<TextRole,string>={eyebrow:'Eyebrow',title:'Heading',lead:'Introduction',content:'Description',itemTitle:'Item headings',itemContent:'Item descriptions',secondaryTitle:'Secondary heading',label:'Labels',number:'Numbers'};
export const roleFields:Record<TextRole,string>={eyebrow:'eyebrow',title:'title',lead:'leadText',content:'content',itemTitle:'items',itemContent:'items',secondaryTitle:'subtitle',label:'items',number:'items'};
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
function legacyTextRoles(section:PageSection):TextRole[] {
 if(isPlanComparison(section))return ['eyebrow','title','content','itemTitle','itemContent'];
 if(section.galleryPreset==='photo-marquee')return section.showSectionHeading===false?['eyebrow']:['eyebrow','title','content'];
 if(section.type==='hero_slider'&&['editorial-overlay','fashion-editorial'].includes(section.sliderPreset??''))return ['eyebrow','title','content'];
 if(section.type==='shop_navigation'&&['market-hero','market-offers'].includes(section.shopNavigationPreset??''))return [...(section.showSectionHeading===false?[]:['title','content'] as TextRole[]),'eyebrow',...(section.shopNavigationPreset==='market-hero'?['lead'] as TextRole[]:[]),'itemTitle','itemContent'];
 if(section.type==='chapter_navigation')return ['eyebrow'];
 if(section.type==='announcement_bar')return ['itemTitle'];
 const roles:TextRole[]=[];
 const contentFields=sectionContentFields(section),itemFields=repeatedItemFields(section);
 const hidden=section.showSectionHeading===false||section.type==='values_list'&&!section.valuesShowHeading||section.type==='chapter_block'&&section.chapterPreset==='principles';
 if(contentFields.includes('subtitle') && section.showSectionHeading!==false)roles.push('eyebrow');
 if(!hidden&&contentFields.includes('title'))roles.push('title');
 if(contentFields.includes('leadText'))roles.push('lead');
 if(!hidden&&contentFields.includes('content'))roles.push('content');
 if(section.type==='product_listing'&&['industrial-related','editorial-grid','editorial-tabs','fashion-lookbook','beauty-grid','pantry-grid','pantry-tabs','tech-grid','tech-tabs','kids-grid','kids-tabs'].includes(section.listingPreset??''))roles.push('itemTitle');
 if(section.type==='product_listing'&&section.listingPreset==='fashion-lookbook')roles.push('itemContent','lead');
 if(['payment_methods','promotion_highlights'].includes(section.type))roles.push('itemTitle','itemContent');
 if(itemFields.includes('title'))roles.push('itemTitle');
 if((section.type==='delivery_table'||section.type==='chapter_block'&&section.chapterPreset==='delivery')&&section.deliverySource!=='manual')roles.push('itemTitle','itemContent');
 if(itemFields.includes('description')||itemFields.includes('deliveryEstimate'))roles.push('itemContent');
 return [...new Set(roles)].filter(role=>{
  if(['stats_counter','icon_list'].includes(section.type)&&['title','content'].includes(role))return false;
  if(section.type==='hero_slider'&&role.startsWith('item'))return false;
  if(section.type==='instagram_feed'&&role==='itemTitle')return false;
  if((['editorial-montage','editorial-triptych'].includes(section.galleryPreset??'')||section.type==='chapter_block'&&section.chapterPreset==='perspective')&&role.startsWith('item'))return false;
  if(section.layoutPreset==='artisan-story'&&role.startsWith('item'))return false;
  return true;
 });
}
export function textRoles(section:PageSection):TextRole[]{
 const connected=connectedPassport(section);if(connected)return [...new Set(connected.textRoles.map(r=>r==='value'?'number':r))] as TextRole[];
 const imageOnlyItems=section.type==='logo_cloud'&&section.logoPreset==='partners'||section.type==='media_text_split'&&['corporate-courier','corporate-payments'].includes(section.layoutPreset??'');
 const roles=legacyTextRoles(section).filter(r=>r!=='eyebrow'&&!(imageOnlyItems&&r.startsWith('item'))&&!(section.type==='media_text_split'&&!section.layoutPreset&&r.startsWith('item')));
 return [...new Set([...(headingCapability(section).mode!=='none'?['eyebrow'] as TextRole[]:[]),...roles,...legacyTypographyRoles(section).filter(r=>!(r==='label'&&section.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq'].includes(section.faqPreset??'')))])];
}
export function isTypography(value:unknown):value is SectionTypography {
 if(!value||typeof value!=='object'||Array.isArray(value))return false;
 return Object.entries(value).every(([role,style])=>Object.prototype.hasOwnProperty.call(textRoleLabels,role)&&style&&typeof style==='object'&&!Array.isArray(style)&&Object.entries(style).every(([key,v])=>{
  if(key==='inheritPage'||key==='colorOverride')return typeof v==='boolean';
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
const readableRoleFloor:Partial<Record<TextRole,number>>={content:16,lead:16,itemContent:14,itemTitle:18,eyebrow:12};
export function typographyStyle(section:PageSection,role:TextRole):CSSProperties {
 const value=section.typography?.[role]??(legacyTypographyRoles(section).includes(role as any)?section.typography?.eyebrow:undefined);if(!value)return {};
 return {...(value.fontFamily?{fontFamily:fontFamilies[value.fontFamily]}:{}),
  ...(value.fontSize?{fontSize:`clamp(${Math.min(value.fontSize,Math.max(readableRoleFloor[role]??8,Math.round(value.fontSize*.7)))}px, ${value.fontSize/7.68}cqi, ${value.fontSize}px)`}:{}),
  ...(value.fontWeight?{fontWeight:value.fontWeight}:{}),...(value.lineHeight?{lineHeight:value.lineHeight}:{}),...(value.letterSpacing!==undefined?{letterSpacing:`${value.letterSpacing}px`}:{}),...(value.color?{color:value.color}:{}),
 };
}
