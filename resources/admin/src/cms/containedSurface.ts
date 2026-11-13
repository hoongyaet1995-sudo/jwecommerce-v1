import type {CSSProperties} from 'react';
import type {PageSection} from '../types';
/** Contained surfaces own the card/panel fill, never the surrounding page canvas. */
export const hasContainedSurface=(section:PageSection)=>section.type==='faq_accordion'&&['pricing-card-faq','pricing-band-faq'].includes(section.faqPreset??'')||['inset-surface-panel','contained-wide-centered','contained-wide-editorial'].includes(section.layoutPreset??'')||['staggered-focus-cards','image-cards-contrast'].includes(section.galleryPreset??'')||section.type==='cta_strip'&&['illustrated-panel-right','illustrated-panel'].includes(section.ctaPreset??'');
export function containedSurfaceBackground(section:PageSection):CSSProperties {
 return hasContainedSurface(section)?{background:section.brandPalette?.paper}:{};
}
