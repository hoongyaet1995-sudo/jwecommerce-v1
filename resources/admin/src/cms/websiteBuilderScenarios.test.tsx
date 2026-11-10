import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderScenarios} from './websiteBuilderScenarios';
import {EditorialGallery} from '../components/pages/EditorialCollections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {textRoles} from './inspectorCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';

describe('Website business scenarios',()=>{
 it('reuses gallery with independently replaceable images and icon/name entries',()=>{
  const s=websiteBuilderScenarios('f01',['/business.webp','/services.webp','/catalogue.webp']);
  expect(s.type).toBe('media_gallery');expect(s.galleryPreset).toBe('corporate-scenarios');
  expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');
  expect(sectionContentFields(s)).toEqual(['title','content']);
  expect(repeatedItemFields(s)).toEqual(['title','icon','imageUrl']);
  expect(textRoles(s)).toEqual(['title','content','itemTitle']);expect(supportsMediaWidth(s)).toBe(false);
  expect(imageTargets(s).map(t=>t.url)).toEqual(['/business.webp','/services.webp','/catalogue.webp']);
  expect(s.items?.map(i=>i.title)).toEqual(['Business websites','Service showcases','Product catalogues']);
 });
 it('renders three independent images and live icon labels without links from legacy data',()=>{
  const s=websiteBuilderScenarios('f01',['/business.webp','/services.webp','/catalogue.webp']);
  s.buttonText='Old action';s.buttonLink='/old';s.items!.forEach(i=>{i.linkUrl='/old';i.buttonText='Old link';});
  const html=renderToStaticMarkup(<EditorialGallery section={s}/>);
  expect(html).toContain('corp-scenarios');expect(html.match(/<img /g)).toHaveLength(3);
  expect(html).toContain('lucide-monitor');expect(html).toContain('data-cms-text="itemTitle"');
  expect(html).not.toContain('<a ');expect(html).not.toContain('Old action');
 });
 it('keeps sparse editing available and independent factories',()=>{
  const sparse={id:'s',type:'media_gallery' as const,galleryPreset:'corporate-scenarios' as const,title:'Scenarios',isVisible:true,items:[]};
  expect(repeatedItemFields(sparse)).toEqual(['title','icon','imageUrl']);
  const a=websiteBuilderScenarios('a'),b=websiteBuilderScenarios('b');a.items![0].title='Changed';
  expect(b.items![0].title).toBe('Business websites');expect(imageTargets(b)).toHaveLength(3);
 });
});
