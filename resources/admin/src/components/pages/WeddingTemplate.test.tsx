import {describe,it,expect} from 'vitest';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections} from '../../cms/pageTemplates';
import {EditorialGallery,EditorialColumns} from './EditorialCollections';
import {EditorialStoryCards} from './EditorialStoryCards';
import {imageTargets} from '../../cms/imageTargets';
import {repeatedItemFields} from '../../cms/repeatedItemContract';
describe('Wedding portfolio',()=>{
 it('uses independent authored sections and repairs internal anchors for each new draft',()=>{
  const one=createPageTemplateSections('wedding-photography',{},'one');const two=createPageTemplateSections('wedding-photography',{},'two');
  expect(one).toHaveLength(9);expect(one.some(s=>s.type==='product_listing')).toBe(false);
  expect(one[0].items![0].linkUrl).toBe('#section-one-wedding-2');one[1].items![0].title='Edited';expect(two[1].items![0].title).not.toBe('Edited');
 });
 it('renders portfolio links and all authored photographs with owned targets',()=>{
  const section=createPageTemplateSections('wedding-photography',{},'test')[5];const html=renderToStaticMarkup(<EditorialGallery section={section}/>);
  expect(html).toContain('Couple Sessions');expect(html.match(/data-cms-action="button"/g)).toHaveLength(3);expect(imageTargets(section)).toHaveLength(3);expect(repeatedItemFields(section)).toContain('linkUrl');
 });
 it('keeps triptych photo-only roles, numbered steps and journal rows',()=>{
  const s=createPageTemplateSections('wedding-photography',{},'test');expect(repeatedItemFields(s[1])).toEqual(['title','imageUrl']);expect(renderToStaticMarkup(<EditorialGallery section={s[1]}/>)).toContain('Natural, documentary');
  expect(renderToStaticMarkup(<EditorialColumns section={s[6]}/>)).toContain('03');
  const journal=renderToStaticMarkup(<EditorialStoryCards section={s[7]}/>);expect(journal).toContain('Finding beauty');expect(journal).not.toContain('grid-cols-2');expect(journal).toContain('data-cms-item');
 });
});
