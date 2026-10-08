import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {ScopedImage} from './ScopedImage';
import {hasCustomImageGeometry} from './SectionImage';
import type {PageSection} from '../../types';
const section:PageSection={id:'deal',type:'featured_deal',title:'Deal',isVisible:true,mediaUrl:'/image.png',imageSlots:[{target:'mediaUrl',imageRatio:'auto'}]};
describe('scoped image geometry',()=>{
  it('overrides the legacy square class when original image size is selected',()=>{
    const html=renderToStaticMarkup(<ScopedImage section={section} targetId="mediaUrl" className="aspect-square w-full"/>);
    expect(html).toContain('height:auto;aspect-ratio:auto');
  });
  it('recognizes imported main-slot geometry in editorial parent frames',()=>{
    expect(hasCustomImageGeometry({...section,type:'media_text_split'})).toBe(true);
  });
});
