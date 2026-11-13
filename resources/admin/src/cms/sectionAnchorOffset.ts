import type {PageSection} from '../types';
/** Shared sticky-header clearance; content padding and page width stay independent. */
export function sectionAnchorOffset(section:PageSection):number|undefined {
 if(section.type==='chapter_block')return 144;
 return (['editorial-illustrated','illustrated-panel-right','illustrated-panel','gradient-panel'].includes(section.ctaPreset??'')||['framed-faq','pricing-card-faq','pricing-band-faq','corporate-starter-faq'].includes(section.faqPreset??'')||['contained-wide-editorial','contained-wide-centered','wide-visual-centered','inset-surface-panel','split-visual-surface','image-highlights','team-story-overview','corporate-builder-overview','corporate-section-story'].includes(section.layoutPreset??'')||['focused-steps','scroll-stack-cards','editorial-knowledge-triptych','image-flow','connected-feature-rows','image-cards-four-mosaic','image-cards-three','corporate-connections','corporate-scenarios','corporate-illustrated-steps'].includes(section.galleryPreset??'')||['plan-comparison','saas-packages'].includes(section.pricingPreset??''))?104:undefined;
}
