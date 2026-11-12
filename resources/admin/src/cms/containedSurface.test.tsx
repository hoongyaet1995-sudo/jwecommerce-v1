// @vitest-environment jsdom
import React,{act} from 'react';import {createRoot} from 'react-dom/client';import {it,expect} from 'vitest';import {containedSurfaceBackground} from './containedSurface';import {resolveStyledSection,generatePalette} from './pageStyle';
it.each(['inset-surface-panel','image-cards-contrast','illustrated-panel'])('%s keeps page paper after repeated Default/Contrast transitions',preset=>{
 globalThis.IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);const root=createRoot(host);const style={palette:generatePalette('#2860ff','#8524ff')};
 for(const surface of ['contrast',undefined,'contrast',undefined,'contrast'] as const){const section=resolveStyledSection({id:'s',type:preset==='illustrated-panel'?'cta_strip':preset==='inset-surface-panel'?'media_text_split':'media_gallery',ctaPreset:preset==='illustrated-panel'?preset:undefined,title:'Surface',isVisible:true,layoutPreset:preset==='inset-surface-panel'?preset:undefined,galleryPreset:preset==='image-cards-contrast'?preset:undefined,surface} as any,style);act(()=>root.render(<section style={{background:section.customColors?.customBgColor,...containedSurfaceBackground(section)}}/>));const expected=document.createElement('div');expected.style.backgroundColor=style.palette.paper;expect(host.querySelector('section')!.style.backgroundColor).toBe(expected.style.backgroundColor);}
 act(()=>root.unmount());host.remove();
});

it('illustrated closing panel keeps its contrast inside the card',()=>{
 const style={palette:generatePalette('#2860ff','#8524ff')};const section=resolveStyledSection({id:'cta',type:'cta_strip',ctaPreset:'illustrated-panel',title:'Next step',isVisible:true,surface:'contrast'} as any,style);
 expect(section.customColors?.customBgColor).toBe(style.palette.ink);expect(containedSurfaceBackground(section)).toEqual({background:style.palette.paper});
});

it('pricing band keeps outer paper and headings in page ink across tone changes',()=>{
 const style={palette:generatePalette('#2860ff','#8524ff')};
 for(const surface of ['contrast','light',undefined,'contrast'] as const){const s=resolveStyledSection({id:'price',type:'faq_accordion',faqPreset:'pricing-band-faq',title:'Plans',isVisible:true,surface} as any,style);expect(containedSurfaceBackground(s)).toEqual({background:style.palette.paper});expect(s.typography?.title?.color).toBe(style.palette.ink);}
});
