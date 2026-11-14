import {connectedPassport} from './connectedFamilies';
import type {PageSection,SectionItem} from '../types';
export type HeadingMode='section'|'slide'|'none';
export type LegacySubtitleMeaning='eyebrow'|'secondary-heading'|'chapter-label'|'caption'|'unused';
export function headingCapability(s:PageSection):{mode:HeadingMode;legacySubtitle:LegacySubtitleMeaning}{
 const connected=connectedPassport(s);if(connected)return {mode:connected.sectionFields.includes('eyebrow')||connected.sectionFields.includes('title')?'section':'none',legacySubtitle:'unused'};
 const unused=s.type==='cta_strip'&&['industrial-outline','illustrated-panel-right','illustrated-panel','gradient-panel','editorial-band'].includes(s.ctaPreset??'')||s.type==='media_gallery'&&['connected-feature-rows','image-cards-four-mosaic','image-cards-three','corporate-connections','corporate-illustrated-steps','corporate-scenarios','staggered-focus-cards','image-cards-contrast','image-cards-three-staggered','image-flow'].includes(s.galleryPreset??'')||s.type==='media_text_split'&&['image-highlights','corporate-section-story','inset-surface-panel','wide-visual-centered','split-visual-surface','contained-wide-centered','corporate-builder-overview'].includes(s.layoutPreset??'');
 const legacySubtitle:LegacySubtitleMeaning=unused?'unused':s.type==='chapter_block'?'chapter-label':s.type==='media_text_split'&&s.layoutPreset==='kids-discovery'||s.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq','corporate-starter-faq'].includes(s.faqPreset??'')?'secondary-heading':s.type==='media_gallery'&&s.galleryPreset==='photo-marquee'?'caption':'eyebrow';
 if(s.type==='hero_slider')return {mode:'slide',legacySubtitle:'eyebrow'};
 if(['announcement_bar','chapter_navigation','stats_counter','icon_list'].includes(s.type)||s.showSectionHeading===false||s.type==='chapter_block'&&s.chapterPreset==='principles'||s.type==='feature_cards'&&s.featureCardsPreset==='saas-addons'||(s.type==='values_list'||s.featureCardsPreset==='values-list')&&!s.valuesShowHeading||s.type==='feature_cards'&&s.featureCardsPreset==='service-highlights'&&s.showSectionHeading!==true)return {mode:'none',legacySubtitle};
 return {mode:'section',legacySubtitle};
}
const own=(o:object,k:string)=>Object.prototype.hasOwnProperty.call(o,k);
const text=(v:unknown)=>typeof v==='string'&&v.trim()?v:'';
export function sectionEyebrow(s:PageSection):string{
 const c=headingCapability(s);if(c.mode!=='section')return '';
 return own(s,'eyebrow')?text(s.eyebrow):c.legacySubtitle==='eyebrow'?text(s.subtitle):'';
}
export function slideEyebrow(s:PageSection,item:SectionItem):string{
 if(headingCapability(s).mode!=='slide')return '';
 if(own(item,'eyebrow'))return text(item.eyebrow);
 return ['editorial-overlay','fashion-editorial'].includes(s.sliderPreset??'')?text(item.subtitle)||text(item.badge):text(item.badge)||text(item.subtitle)||text(s.subtitle);
}
const layoutKey=(s:PageSection)=>JSON.stringify([s.type,...Object.entries(s).filter(([key])=>key.endsWith('Preset')&&key!=='motionPreset'||key==='paymentLayout').sort(([a],[b])=>a.localeCompare(b)),s.showSectionHeading,s.valuesShowHeading]);
/** Preserve the source layout meaning before the target layout can reinterpret legacy text. */
export function legacyTypographyRoles(s:PageSection):Array<'secondaryTitle'|'label'|'number'>{
 const result:Array<'secondaryTitle'|'label'|'number'>=[];
 if(headingCapability(s).legacySubtitle==='secondary-heading')result.push('secondaryTitle');
 if(s.type==='media_gallery'&&s.galleryPreset==='focused-steps'||s.type==='chapter_block'||s.type==='feature_cards'&&['industrial-cards','industrial-process','industrial-process-split'].includes(s.featureCardsPreset??''))result.push('number');
 if(['chapter_navigation','timeline_story','stats_counter','icon_list','split_hero'].includes(s.type)||s.type==='comparison_matrix'&&s.comparisonPreset!=='device-table'||s.type==='shop_navigation'&&['market-hero','market-offers','recipe-cards'].includes(s.shopNavigationPreset??'')||s.type==='feature_cards'&&!s.featureCardsPreset||s.type==='media_gallery'&&s.galleryPreset==='photo-marquee'||s.type==='pricing_table'&&s.pricingPreset==='plan-comparison'||s.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq'].includes(s.faqPreset??''))result.push('label');
 return result;
}
export function preserveLegacyTypography(source:PageSection,next:PageSection,inherited?:PageSection['typography']):PageSection{
 const roles=legacyTypographyRoles(source);if(!roles.length)return next;
 const typography={...next.typography};
 for(const role of roles)if(!own(typography,role))typography[role]={...(source.typography?.[role]??source.typography?.eyebrow??inherited?.[role]??inherited?.eyebrow??{})};
 return {...next,typography};
}
export function normalizeEyebrowChange(source:PageSection,next:PageSection,inherited?:PageSection['typography']):PageSection{
 const switched=layoutKey(source)!==layoutKey(next);
 const changed=source.eyebrow!==next.eyebrow||JSON.stringify(source.typography?.eyebrow)!==JSON.stringify(next.typography?.eyebrow);
 const sealed=switched||changed?preserveLegacyTypography(source,next,inherited):next;
 if(!switched)return sealed;
 const items=headingCapability(source).mode==='slide'&&headingCapability(next).mode==='slide'?next.items?.map(item=>{const before=source.items?.find(i=>i.id===item.id);return before&&!own(item,'eyebrow')?{...item,eyebrow:slideEyebrow(source,before)}:item;}):undefined;
 return {...sealed,...(items?{items}:{}),...(!own(source,'eyebrow')&&!own(next,'eyebrow')?{eyebrow:sectionEyebrow(source)}:{})};
}
export function legacySubtitleLabel(s:PageSection):string{
 return ({'secondary-heading':s.layoutPreset==='kids-discovery'?'Panel heading':'FAQ heading','chapter-label':'Chapter label',caption:'Caption',eyebrow:'Eyebrow',unused:'Subtitle'})[headingCapability(s).legacySubtitle];
}
