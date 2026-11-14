import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderStory} from './websiteBuilderStory';
import {CorporateFeature} from '../components/pages/CorporateSections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {textRoles} from './inspectorCapabilities';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';

describe('Website Builder section story',()=>{
 it('reuses narrative type with one image, editable copy and icon labels only',()=>{
  const s=websiteBuilderStory('f01','/storage/cms/media/story.webp');
  expect(s.type).toBe('media_text_split');expect(s.layoutPreset).toBe('corporate-section-story');
  expect(s.containerMode).toBe('page');expect(s.motionPreset).toBe('subtle-lift');
  expect(sectionContentFields(s)).toEqual(['eyebrow','title','content','mediaUrl','mediaAlt']);
  expect(repeatedItemFields(s)).toEqual(['title','icon']);
  expect(textRoles(s)).toEqual(['eyebrow','title','content','itemTitle']);expect(imageTargets(s).map(t=>t.id)).toEqual(['mediaUrl']);
  expect(supportsMediaWidth(s)).toBe(false);
  expect(s.items?.map(i=>i.title)).toEqual(['Hero & introduction','Services & features','Galleries, FAQs & contact']);
 });
 it('renders semantic copy before transparent art, hides retained actions/eyebrow and internal artwork editors',()=>{
  const s={...websiteBuilderStory('f01','/storage/cms/media/story.webp'),subtitle:'Hidden',buttonText:'Old CTA',buttonLink:'/old'};
  const html=renderToStaticMarkup(<CorporateFeature section={s}/>);
  expect(html).toContain('corp-section-story');expect(html).toContain('data-image-slot="mediaUrl"');
  expect(html.indexOf('<h2')).toBeLessThan(html.indexOf('<img'));
  expect(html).toContain('lucide-layout-grid');expect(html).toContain('lucide-image');
  expect(html).not.toContain('<a ');expect(html).not.toContain('Hidden');expect(html).not.toContain('corp-workflow');
 });
 it('keeps sparse editing and new starters independent',()=>{
  const sparse={id:'empty',type:'media_text_split' as const,layoutPreset:'corporate-section-story' as const,title:'Story',isVisible:true};
  expect(imageTargets(sparse)).toHaveLength(1);expect(repeatedItemFields(sparse)).toEqual(['title','icon']);
  expect(renderToStaticMarkup(<CorporateFeature section={sparse} editing/>)).toContain('Add section artwork');
  const a=websiteBuilderStory('a'),b=websiteBuilderStory('b');a.items![0].title='Changed';expect(b.items![0].title).toBe('Hero & introduction');
 });
});
