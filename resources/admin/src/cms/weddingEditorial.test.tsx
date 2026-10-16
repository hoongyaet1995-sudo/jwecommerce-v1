import {pageContainerStyle} from '../components/pages/PageSectionContainer';
import React from 'react';
import {describe,it,expect,vi} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets,updateImageTarget} from './imageTargets';
import {parseDraftImport} from './pageImport';
import {PhotoMarquee} from '../components/pages/PhotoMarquee';
import {EditorialGradientHero,EditorialQuote} from '../components/pages/WeddingEditorial';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
vi.mock('../components/pages/motion/CmsMotionWrapper',()=>({MotionItem:({children,...p}:any)=><div {...p}>{children}</div>,MotionButton:({children}:any)=><div>{children}</div>}));
describe('Wedding editorial integration',()=>{
 it('follows canonical storefront width and gutter once while preserving authored widths',()=>{
  expect(pageContainerStyle('site')).toEqual({maxWidth:'var(--storefront-content-width, 1280px)',paddingInline:'var(--storefront-content-gutter, 24px)',boxSizing:'border-box'});
  expect(pageContainerStyle('site',true)).toEqual({maxWidth:'none'});
  expect(pageContainerStyle('standard')).toEqual({maxWidth:1280});expect(pageContainerStyle('wide')).toEqual({maxWidth:1440});expect(pageContainerStyle('full')).toEqual({maxWidth:'100%'});
  for(const mediaUrl of [undefined,'/images/themes/wedding/hero.png']){const html=renderToStaticMarkup(<EditorialGradientHero section={{id:'h',type:'hero_banner',title:'Copy always aligned',isVisible:true,containerMode:'full-bleed',mediaUrl}}/>);expect(html).toContain('padding-inline:var(--cms-page-gutter');expect(html).not.toContain('px-6');}
 });
 it('creates independent canonical drafts with actual anchor remapping and bounded presets',()=>{
  const a=createPageTemplateSections('wedding-photography',{},'one'),b=createPageTemplateSections('wedding-photography',{},'two');
  expect(a).toHaveLength(9);expect(a[0].buttonLink).toBe('#section-one-wedding-3');expect(a[2].galleryPreset).toBe('photo-marquee');expect(a[7].testimonialPreset).toBe('editorial-quote');
  expect(a.every(s=>s.sectionBorder==='none'&&s.motionPreset==='subtle-lift')).toBe(true);
  a[2].items![0].imageUrl='/changed.jpg';expect(b[2].items![0].imageUrl).not.toBe('/changed.jpg');
  const doc={title:'Wedding',slug:'wedding',template:'standard',style:createPageTemplateStyle('wedding-photography'),sections:b};
  expect(parseDraftImport(JSON.stringify(doc),'trusted')).toEqual(doc);
 });
 it('rejects unregistered or traversing bundled media paths',()=>{
  for(const url of ['/images/themes/wedding/unknown.png','/images/themes/wedding/../hero.png','/images/themes/wedding/%2e%2e/hero.png','/images/themes/wedding/hero.png?file=secret']){
   const doc={title:'Test',slug:'test',template:'standard',sections:[{id:'h',type:'hero_banner',title:'Hero',isVisible:true,mediaUrl:url}]};
   expect(()=>parseDraftImport(JSON.stringify(doc),'trusted')).toThrow();
  }
 });
 it('provides sparse photo-only repeatable controls and persistent alt targets',()=>{
  const sparse={id:'marquee',type:'media_gallery',title:'Photos',isVisible:true,galleryPreset:'photo-marquee',items:[]} as any;
  expect(repeatedItemFields(sparse)).toEqual(['imageUrl']);expect(sectionContentFields({...sparse,showSectionHeading:false})).toEqual(['subtitle']);
  expect(imageTargets(sparse)).toEqual([]);
  const filled={...sparse,items:[{id:'p',title:'',imageUrl:'/photo.jpg'}]};
  const edited=updateImageTarget(filled,'item:p:imageUrl',{mediaAlt:'A garden celebration'});
  expect(edited.imageSlots?.[0].mediaAlt).toBe('A garden celebration');expect(imageTargets(edited)[0].geometryLocked).toBe(true);
  const html=renderToStaticMarkup(<PhotoMarquee section={edited}/>);expect(html).toContain('A garden celebration');expect(html).toContain('Pause photo marquee');expect(html).toContain('aria-hidden="true"');expect(html).toContain('cms-photo-marquee-group');
 });
 it('renders editable gradient copy/actions and quote fields without hidden avatar controls',()=>{
  const sections=createPageTemplateSections('wedding-photography',{},'a');
  const hero=renderToStaticMarkup(<EditorialGradientHero section={sections[0]}/>);expect(hero).toContain('data-cms-hero-safe');expect(hero).toContain('var(--cms-page-width)');expect(hero).toContain('Explore our work');
  expect(repeatedItemFields(sections[7])).toEqual(['title','description']);expect(imageTargets(sections[7])).toEqual([]);expect(renderToStaticMarkup(<EditorialQuote section={sections[7]}/>)).toContain('Sample couple');
 });
 it('registers new presets through existing Add Section semantic types',()=>{
  expect(SECTION_TEMPLATES.find(t=>t.title==='Photo Marquee')?.defaultData.galleryPreset).toBe('photo-marquee');expect(SECTION_TEMPLATES.find(t=>t.title==='Editorial Quote')?.type).toBe('testimonials_slider');
 });
});
