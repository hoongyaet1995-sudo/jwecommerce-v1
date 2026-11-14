import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderConnections} from './websiteBuilderConnections';
import {EditorialGallery} from '../components/pages/EditorialCollections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {textRoles} from './inspectorCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';

describe('Website next connections',()=>{
 it('owns independent images, copy and button destinations on an existing gallery',()=>{
  const s=websiteBuilderConnections('f01',['/shop.webp','/chat.webp','/ai.webp']);
  expect(s.type).toBe('media_gallery');expect(s.galleryPreset).toBe('corporate-connections');
  expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');
  expect(sectionContentFields(s)).toEqual(['eyebrow','title','content']);
  expect(repeatedItemFields(s)).toEqual(['title','description','imageUrl','buttonText','linkUrl']);
  expect(textRoles(s)).toEqual(['eyebrow','title','content','itemTitle','itemContent']);expect(supportsMediaWidth(s)).toBe(false);
  expect(imageTargets(s).map(t=>t.url)).toEqual(['/shop.webp','/chat.webp','/ai.webp']);
 });
 it('links only the three CTA buttons and never image, title or whole card',()=>{
  const html=renderToStaticMarkup(<EditorialGallery section={websiteBuilderConnections('f01',['/a.webp','/b.webp','/c.webp'])}/>);
  const doc=html;
  expect(doc.match(/<a /g)).toHaveLength(3);expect(doc.match(/data-cms-action="button"/g)).toHaveLength(3);
  expect(doc).not.toMatch(/<a[^>]*>\s*<img/);expect(doc).not.toMatch(/<a[^>]*>\s*<h3/);
  expect(doc).toContain('href="/pages/ecommerce"');expect(doc).toContain('href="/pages/website-ai-chat"');expect(doc).toContain('href="/pages/ai-page-generator"');
  expect(doc).toContain('corp-connections');expect(doc.match(/corp-primary corp-connection-button/g)).toHaveLength(3);expect(doc.match(/<img /g)).toHaveLength(3);
 });
 it('keeps sparse controls and supports absent or unsafe CTA destinations without a link',()=>{
  const s=websiteBuilderConnections('s');s.items=[];
  expect(repeatedItemFields(s)).toContain('buttonText');expect(renderToStaticMarkup(<EditorialGallery section={s} editing/>)).not.toContain('<a ');
  s.items=[{id:'empty',title:'Untitled',description:'Body',buttonText:'Go',linkUrl:'javascript:alert(1)'}];
  expect(renderToStaticMarkup(<EditorialGallery section={s}/>)).not.toContain('<a ');
  const b=websiteBuilderConnections('b');s.items[0].title='Changed';expect(b.items![0].title).toBe('Ecommerce');
 });
});
