import type {CSSProperties} from 'react';
import type {PageSection} from '../types';
/** Contained surfaces own the card/panel fill, never the surrounding page canvas. */
export const hasContainedSurface=(section:PageSection)=>section.type==='faq_accordion'&&section.faqPreset==='pricing-band-faq'||section.layoutPreset==='inset-surface-panel'||section.galleryPreset==='image-cards-contrast'||section.type==='cta_strip'&&section.ctaPreset==='illustrated-panel';
export function containedSurfaceBackground(section:PageSection):CSSProperties {
 return hasContainedSurface(section)?{background:section.brandPalette?.paper}:{};
}
