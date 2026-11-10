import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderOverview} from './websiteBuilderOverview';
import {CorporateBuilderOverview} from '../components/pages/CorporateSections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {textRoles} from './inspectorCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';

describe('Website Builder overview',()=>{
 it('reuses narrative content, one image and independent editable icon labels without phantom actions',()=>{
  const s=websiteBuilderOverview('f01','/storage/cms/media/builder.webp');
  expect(s.type).toBe('media_text_split');expect(s.id).toBe('f01-builder');
  expect(s.layoutPreset).toBe('corporate-builder-overview');
  expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');
  expect(sectionContentFields(s)).toEqual(['title','content','mediaUrl','mediaAlt']);
  expect(repeatedItemFields(s)).toEqual(['title','icon']);
  expect(textRoles(s)).toEqual(['title','content','itemTitle']);
  expect(imageTargets(s).map(t=>t.id)).toEqual(['mediaUrl']);
  expect(supportsMediaWidth(s)).toBe(false);
  expect(s.items?.map(i=>i.title)).toEqual(['Arrange sections','Edit content','Preview each device']);
 });
 it('renders all icon labels outside the artwork, no tabs or CTA even with retained legacy data',()=>{
  const s={...websiteBuilderOverview('f01','/storage/cms/media/builder.webp'),subtitle:'Hidden eyebrow',buttonText:'Legacy action',buttonLink:'/old'};
  s.items!.push({id:'extra',title:'Fourth label',icon:'Layers'});
  const html=renderToStaticMarkup(<CorporateBuilderOverview section={s}/>);
  expect(html).toContain('<h2');expect(html).toContain('Fourth label');
  expect(html).toContain('data-cms-item="extra"');expect(html).toContain('data-image-slot="mediaUrl"');
  expect(html).toContain('lucide-pencil');
  expect(html).not.toContain('<a ');expect(html).not.toContain('role="tab');
  expect(html).not.toContain('Hidden eyebrow');expect(html).not.toContain('Legacy action');
 });
 it('exposes sparse item/image capabilities and returns independent starters',()=>{
  const sparse={id:'empty',type:'media_text_split' as const,title:'Overview',layoutPreset:'corporate-builder-overview' as const,isVisible:true};
  expect(repeatedItemFields(sparse)).toEqual(['title','icon']);expect(imageTargets(sparse)).toHaveLength(1);
  expect(renderToStaticMarkup(<CorporateBuilderOverview section={sparse} editing/>)).toContain('Add builder artwork');
  const a=websiteBuilderOverview('a'),b=websiteBuilderOverview('b');a.items![0].title='Changed';
  expect(b.items![0].title).toBe('Arrange sections');
 });
});
