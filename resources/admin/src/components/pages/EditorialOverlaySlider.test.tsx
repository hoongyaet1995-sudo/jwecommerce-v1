import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {EditorialOverlaySlider} from './EditorialOverlaySlider';
import {SECTION_TEMPLATES} from './AddSectionModal';
import {itemSupports,supportedNewItem} from '../../cms/repeatedItemContract';
import type {PageSection} from '../../types';
const starter=SECTION_TEMPLATES.find(template=>template.title==='Editorial Overlay Slider')!;
const section:PageSection={...starter.defaultData,id:'hero'};
const html=(value:PageSection,index=0)=>renderToStaticMarkup(<EditorialOverlaySlider section={value} index={index} onSelect={()=>{}} onPause={()=>{}} isEditingMode={false}/>);
describe('Editorial Overlay carousel',()=>{
 it.each([['left','justify-start'],['center','justify-center'],['right','justify-end']] as const)('aligns both actions with %s copy', (alignment,css)=>{const rendered=html({...section,textAlignment:alignment,items:[{...section.items![0],secondaryButtonText:'Read more',secondaryButtonLink:'/shop'}]});expect(rendered).toContain(`items-center ${css}`);expect(rendered).toContain('Read more');expect(rendered).toContain(`text-align:${alignment}`);});
 it('keeps live text separate from the background and hides navigation for one slide',()=>{
  const rendered=html(section);
  expect(rendered).toContain('Make room\nfor everyday\nbeauty.');
  expect(rendered).toContain('/storage/cms/media/b773291f-3f89-42ec-b036-efb180c23494.png');
  expect(rendered).toContain('Home\nfor a brighter\neveryday.');
  expect(rendered).not.toContain('Show slide');
 });
 it('renders all added slides with stable image identities and navigates beyond slide three',()=>{
  const slides=Array.from({length:4},(_,i)=>({...section.items![0],id:`slide-${i}`,title:`Collection ${i}`,caption:`Caption ${i}`}));
  const rendered=html({...section,items:slides},3);
  expect(rendered.match(/Show slide /g)).toHaveLength(4);
  expect(rendered).toContain('Collection 3</h1>');
  expect(rendered).toContain('Caption 3');
  expect(rendered).toContain('item:slide-3:imageUrl');
 });
 it('handles empty content without inventing audio products or incomplete actions',()=>{
  expect(html({...section,items:[]})).toContain('Add your first slide');
  expect(html({...section,items:[{id:'empty',title:'Headline',buttonText:'Incomplete'}]})).not.toContain('<a ');
 });
 it('offers captions only on the layout that renders them',()=>{
  expect(itemSupports(section,'caption')).toBe(true);
  expect(itemSupports({...section,sliderPreset:'default'},'caption')).toBe(false);
  expect(supportedNewItem({...section,sliderPreset:'default'},{id:'s',title:'Slide',caption:'Hidden'})).not.toHaveProperty('caption');
 });
});
