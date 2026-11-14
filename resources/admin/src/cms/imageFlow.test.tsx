import React from 'react';import {describe,it,expect} from 'vitest';import {renderToStaticMarkup} from 'react-dom/server';
import {imageFlow} from './imageFlow';import {EditorialGallery} from '../components/pages/EditorialCollections';import {sectionContentFields} from './contentCapabilities';import {repeatedItemFields} from './repeatedItemContract';import {imageTargets} from './imageTargets';import {textRoles} from './inspectorCapabilities';import {resolveStyledSection,generatePalette} from './pageStyle';
describe('Image Flow passport',()=>{
 it('has only editable section copy and three independent image slots, including sparse input',()=>{
  const s=imageFlow('generic');expect(s.type).toBe('media_gallery');expect(s.customColors).toBeUndefined();expect(s.items).toHaveLength(3);
  expect(sectionContentFields(s)).toEqual(['eyebrow','title','content']);expect(repeatedItemFields(s)).toEqual(['imageUrl']);expect(textRoles(s)).toEqual(['eyebrow','title','content']);
  expect(imageTargets({...s,items:[]})).toHaveLength(3);expect(JSON.stringify(s)).not.toMatch(/JW Studio|\/pages\//);
 });
 it('renders only three replaceable images, unique theme-driven SVG connectors and no baked text editors',()=>{
  const s=imageFlow('flow');s.items=s.items!.map((i,n)=>({...i,title:'Hidden card title',imageUrl:`/card-${n}.png`}));
  const style={palette:generatePalette('#165d3b','#b04612')};const html=renderToStaticMarkup(<EditorialGallery section={resolveStyledSection(s,style)}/>);
  expect(html.match(/<img /g)).toHaveLength(3);expect(html.match(/<svg /g)).toHaveLength(2);expect(html).toContain('--flow-primary:#165d3b');expect(html).toContain('--flow-secondary:#b04612');expect(html).not.toContain('Hidden card title');expect(html).not.toContain('<a ');
  const other=renderToStaticMarkup(<EditorialGallery section={{...s,id:'other-flow'}}/>);const id=html.match(/<linearGradient id="([^"]+)"/)![1];expect(other).not.toContain(`id="${id}"`);
 });
});
