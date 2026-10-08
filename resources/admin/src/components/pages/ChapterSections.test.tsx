import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {ChapterNavigation,ChapterBlock,EditorialCover,EditorialCtaBand} from './ChapterSections';
import {PageRuntimeProvider,createAdminPageRuntimePayload} from '../../cms/PageRuntimeContext';
import type {CmsPage,PageSection} from '../../types';
const chapters:PageSection[]=[{id:'hidden',type:'chapter_block',title:'Hidden',subtitle:'Hidden',isVisible:false},{id:'story',type:'chapter_block',chapterPreset:'story',title:'Start with what matters.',subtitle:'Our story',content:'One paragraph.\n\nSecond paragraph.',isVisible:true},{id:'principles',type:'chapter_block',chapterPreset:'principles',title:'Principles',subtitle:'Our principles',isVisible:true,items:[{id:'one',title:'Useful details',description:'Real details.'}]}];
const render=(node:React.ReactNode)=>renderToStaticMarkup(<PageRuntimeProvider payload={createAdminPageRuntimePayload({id:'qa',title:'QA',slug:'qa',description:'',template:'standard',status:'draft',createdAt:'',updatedAt:'',sections:chapters} as CmsPage,{products:[],categories:[],collections:[]})}>{node}</PageRuntimeProvider>);
describe('chapter layouts',()=>{
 it('builds navigation from visible chapter order using actual section anchors',()=>{
  const html=render(<ChapterNavigation section={{id:'nav',type:'chapter_navigation',title:'About chapters',isVisible:true}} isEditingMode/>);
  expect(html).toContain('#section-story');expect(html).toContain('#section-principles');expect(html).not.toContain('#section-hidden');
 });
 it('automatically numbers principles after visible story and renders editable value rows',()=>{
  const html=render(<ChapterBlock section={chapters[2]}/>);expect(html).toContain('02');expect(html).toContain('Useful details');expect(html).toContain('Real details.');
 });
 it('keeps cover copy editable rather than putting headings inside the image',()=>{
  const html=render(<EditorialCover section={{id:'cover',type:'media_text_split',layoutPreset:'editorial-cover',title:'Behind the everyday.',leadText:'An introduction.',content:'Editable story.',isVisible:true}}/>);
  expect(html).toContain('<h1');expect(html).toContain('An introduction.');expect(html).toContain('Editable story.');
 });
 it('renders a lateral band with a real action link',()=>{
  const html=render(<EditorialCtaBand section={{id:'cta',type:'cta_strip',title:'Next chapter',buttonText:'Explore',buttonLink:'/shop',isVisible:true}} isEditingMode/>);
  expect(html).toContain('href="/shop"');expect(html).toContain('Next chapter');
 });
});
