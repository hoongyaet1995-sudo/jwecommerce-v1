import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {it,expect} from 'vitest';
import {PageSectionContainer,pageContainerStyle,storefrontContentVariables} from './PageSectionContainer';
it('defaults all sections to Follow website and switches page width without per-section input',()=>{
 expect(renderToStaticMarkup(<PageSectionContainer><p>Content</p></PageSectionContainer>)).toContain('max-width:var(--storefront-content-width, 1280px)');
 expect(renderToStaticMarkup(<PageSectionContainer width="wide"><p>Content</p></PageSectionContainer>)).toContain('max-width:1440px');
 expect(renderToStaticMarkup(<PageSectionContainer width="wide" fullBleed><p>Content</p></PageSectionContainer>)).toContain('max-width:none');
});
it('scopes the website frame to Builder while page and section width overrides stay authoritative',()=>{
 for(const [width,expected]of [['standard','1280px'],['wide','1440px'],['full','100%']] as const){
  const vars=storefrontContentVariables(width);
  expect(vars['--storefront-content-width']).toBe(expected);
  expect(vars.containerType).toBe('inline-size');
  if(width==='full')expect(vars['--storefront-content-gutter']).toBeUndefined();
  else expect(vars['--storefront-content-gutter']).toContain('cqi');
  expect(pageContainerStyle('site').maxWidth).toBe('var(--storefront-content-width, 1280px)');
  expect(pageContainerStyle('standard').maxWidth).toBe(1280);
  expect(pageContainerStyle('wide').maxWidth).toBe(1440);
  expect(pageContainerStyle('full').maxWidth).toBe('100%');
  expect(pageContainerStyle('site',true).maxWidth).toBe('none');
 }
});

it('applies website side spacing only once when a layout owns its native gutter',()=>{
 const html=renderToStaticMarkup(<PageSectionContainer nativeGutter><p>Native layout</p></PageSectionContainer>);expect(html).toContain('padding-inline:0');
 const special=renderToStaticMarkup(<PageSectionContainer nativeGutter fullBleed><p>Special</p></PageSectionContainer>);expect(special).toContain('max-width:none');
});
