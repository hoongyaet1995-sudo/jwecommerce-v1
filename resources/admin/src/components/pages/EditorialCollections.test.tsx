import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {EditorialGallery,EditorialColumns} from './EditorialCollections';
import type {PageSection} from '../../types';
const gallery:PageSection={id:'gallery',type:'media_gallery',title:'The world of a store.',isVisible:true,galleryPreset:'editorial-montage',items:[{id:'one',title:'Desk',imageUrl:'/storage/cms/media/fb690366-d0bb-49ff-9d32-bfea5408b4bc.png'},{id:'two',title:'Clothes',imageUrl:'/storage/cms/media/eb51bde3-ddd3-4b20-9268-85c4efcda3e3.png'}]};
describe('editorial collections',()=>{
 it('renders the montage with a real headline and separately selectable images',()=>{
  const html=renderToStaticMarkup(<EditorialGallery section={gallery}/>);
  expect(html).toContain('<h1');expect(html).toContain('item:one:imageUrl');expect(html).toContain('item:two:imageUrl');
 });
 it('renders card captions outside the photographs and hides the optional heading',()=>{
  const html=renderToStaticMarkup(<EditorialGallery section={{...gallery,galleryPreset:'editorial-cards',showSectionHeading:false}}/>);
  expect(html).not.toContain('The world of a store.');expect(html).toContain('<h3');expect(html).not.toContain('bg-gradient');
 });
 it('renders plain editable text columns without card icons or extra section headings',()=>{
  const html=renderToStaticMarkup(<EditorialColumns section={{...gallery,type:'feature_cards',featureCardsPreset:'editorial-columns',showSectionHeading:false,columnsCount:2,items:[{id:'a',title:'What inspires us',description:'Paragraph one.\n\nParagraph two.'}]}}/>);
  expect(html).toContain('What inspires us');expect(html).toContain('Paragraph two.');expect(html).not.toContain('The world of a store.');expect(html).not.toContain('<svg');
 });
});
