import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {PageSectionContainer} from './PageSectionContainer';
it('defaults all sections to Standard and switches page width without per-section input',()=>{
 expect(renderToStaticMarkup(<PageSectionContainer><p>Content</p></PageSectionContainer>)).toContain('max-width:1280px');
 expect(renderToStaticMarkup(<PageSectionContainer width="wide"><p>Content</p></PageSectionContainer>)).toContain('max-width:1440px');
 expect(renderToStaticMarkup(<PageSectionContainer width="wide" fullBleed><p>Content</p></PageSectionContainer>)).toContain('max-width:none');
});
