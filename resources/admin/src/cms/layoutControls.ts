import type {PageSection} from '../types';
import matrix from './layoutControlMatrix.json';

export type LayoutAlignment='left'|'center'|'right';
export interface LayoutControlCapability {spacing:boolean;alignment:readonly LayoutAlignment[];headingOnly?:boolean;headingOffAlignment?:readonly LayoutAlignment[]}
const presetFields:Partial<Record<PageSection['type'],keyof PageSection>>={hero_banner:'heroBannerPreset',hero_slider:'sliderPreset',rich_text:'richTextPreset',media_text_split:'layoutPreset',media_gallery:'galleryPreset',feature_cards:'featureCardsPreset',product_listing:'listingPreset',testimonials_slider:'testimonialPreset',shop_navigation:'shopNavigationPreset',comparison_matrix:'comparisonPreset',pricing_table:'pricingPreset',faq_accordion:'faqPreset',contact_form:'contactPreset',store_locator:'contactPreset',cta_strip:'ctaPreset',newsletter_signup:'newsletterPreset',chapter_block:'chapterPreset',policy_content:'policyPreset',logo_cloud:'logoPreset',payment_methods:'paymentLayout'};
/** Renderer-backed layout capabilities; never infer support from retained authored fields.
 * Matrix verified against all161 starters, heading on/off and pairwise value differences.
 * Fixed layout defaults and unsupported old values are preserved, never normalized here.
 */
export function layoutControlCapabilities(section:PageSection):LayoutControlCapability {
 const field=presetFields[section.type];
 const preset=field?section[field]??'default':'default';
 const capability=(matrix as Record<string,LayoutControlCapability>)[`${section.type}|${preset}`];
 if(!capability)return {spacing:false,alignment:[]};
 if(capability.headingOnly&&section.showSectionHeading===false)return {...capability,alignment:[]};
 if(capability.headingOffAlignment&&section.showSectionHeading===false)return {...capability,alignment:capability.headingOffAlignment};
 return capability;
}
