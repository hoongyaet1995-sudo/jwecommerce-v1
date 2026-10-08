import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {SectionImage} from './SectionImage';
import type {PageSection} from '../../types';
const base:PageSection={id:'image',type:'media_text_split',title:'Image QA',isVisible:true,mediaUrl:'/saved.png',mediaAlt:'Saved description'};
describe('shared section image',()=>{
  it('consumes ratio, nine-point crop, fit, width, shape and two independent layers',()=>{
    const html=renderToStaticMarkup(<SectionImage section={{...base,imageRatio:'3:4',imagePosition:'bottom-right',imageFit:'contain',imageWidth:'75',imageShape:'rounded',imageOverlay:true,imageOverlayOpacity:40,imageForeground:true,imageForegroundColor:'#ff0000',imageForegroundOpacity:20}} />);
    expect(html).toContain('aspect-ratio:3 / 4');expect(html).toContain('object-position:right bottom');expect(html).toContain('object-fit:contain');expect(html).toContain('width:75%');expect(html).toContain('rounded-3xl');expect(html).toContain('data-image-overlay');expect(html).toContain('opacity:0.4');expect(html).toContain('data-image-foreground');expect(html).toContain('opacity:0.2');
  });
  it('keeps legacy crop, descriptive alt and no layers by default',()=>{
    const html=renderToStaticMarkup(<SectionImage section={{...base,mediaFocalPoint:'top'}} />);
    expect(html).toContain('object-position:center top');expect(html).toContain('alt="Saved description"');expect(html).not.toContain('data-image-overlay');expect(html).not.toContain('data-image-foreground');
  });
  it('uses a square frame for circles and leaves empty image slots empty',()=>{
    expect(renderToStaticMarkup(<SectionImage section={{...base,imageShape:'circle',imageRatio:'16:9'}} />)).toContain('aspect-ratio:1 / 1');
    expect(renderToStaticMarkup(<SectionImage section={{...base,mediaUrl:''}} />)).toBe('');
  });
});
