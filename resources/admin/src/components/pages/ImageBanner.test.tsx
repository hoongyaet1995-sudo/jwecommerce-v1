import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {ImageBanner} from './ImageBanner';
import {ctaPresetPatch} from '../../cms/ctaPresets';
import type {PageSection} from '../../types';
const section:PageSection={id:'cta',type:'cta_strip',ctaPreset:'image-banner',title:'Simple things,\nconsidered.',isVisible:true,mediaUrl:'/storage/banner.jpg',buttonText:'Explore',buttonLink:'/shop',imageSlots:[{target:'mediaUrl',imageOverlay:true,imageOverlayOpacity:40}]};
it('renders shared background layers and only complete actions',()=>{
 const html=renderToStaticMarkup(<ImageBanner section={section} isEditingMode={false}/>);
 expect(html).toContain('data-image-banner');expect(html).toContain('/storage/banner.jpg');expect(html).toContain('data-image-overlay');expect(html).toContain('opacity:0.4');expect(html).toContain('href="/shop"');
 expect(renderToStaticMarkup(<ImageBanner section={{...section,buttonLink:''}} isEditingMode={false}/>)).not.toContain('data-editorial-action');
});
it('preserves content and explicit image tools while choosing presets',()=>{
 const next={...section,...ctaPresetPatch(section,'image-banner')};expect(next.title).toBe(section.title);expect(next.imageSlots).toEqual(section.imageSlots);expect(ctaPresetPatch(next,'default')).toEqual({ctaPreset:'default'});
});
