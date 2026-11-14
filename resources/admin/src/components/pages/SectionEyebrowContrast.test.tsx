// @vitest-environment jsdom
import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {SectionEyebrow} from './SectionEyebrow';
import type {PageSection} from '../../types';
const section:PageSection={id:'old-image-hero',type:'rich_text',title:'Heading',eyebrow:'Legacy label',isVisible:true};
it('keeps legacy light text readable on the capsule without rewriting its color',()=>{
 const html=renderToStaticMarkup(<SectionEyebrow section={{...section,typography:{eyebrow:{color:'#d6d6d6'}}}}/>);
 expect(html).toContain('data-eyebrow-background="dark"');expect(html).toContain('color:#d6d6d6');
});
it('keeps an explicit dark text color readable on a contrast surface',()=>{
 const html=renderToStaticMarkup(<SectionEyebrow section={{...section,surface:'contrast',typography:{eyebrow:{color:'#123456'}}}}/>);
 expect(html).toContain('data-eyebrow-background="light"');expect(html).toContain('color:#123456');
});
it('keeps an already readable original surface unchanged',()=>{
 expect(renderToStaticMarkup(<SectionEyebrow section={section}/>)).not.toContain('data-eyebrow-background');
 expect(renderToStaticMarkup(<SectionEyebrow section={{...section,surface:'contrast',typography:{eyebrow:{color:'#ffffff'}}}}/>)).not.toContain('data-eyebrow-background');
});
