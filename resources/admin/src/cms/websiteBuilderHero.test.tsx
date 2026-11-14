import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {websiteBuilderHero} from './websiteBuilderHero';
import {CorporateHero} from '../components/pages/CorporateSections';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets} from './imageTargets';
import {repeatedItemFields} from './repeatedItemContract';
import {pageContainerStyle} from '../components/pages/PageSectionContainer';

describe('Website Builder composite Hero',()=>{
 it('uses existing hero semantics with editable copy, two actions and a single replaceable image',()=>{
  const section=websiteBuilderHero('f01','/storage/cms-media/hero.webp');
  expect(section.type).toBe('hero_banner');
  expect(section.heroBannerPreset).toBe('corporate-split-composite');
  expect(sectionContentFields(section)).toEqual(expect.arrayContaining(['eyebrow','title','content','mediaUrl','mediaAlt','buttonText','buttonLink','secondaryButtonText','secondaryButtonLink']));
  expect(imageTargets(section).map(target=>target.id)).toEqual(['mediaUrl']);
  expect(repeatedItemFields(section)).toEqual([]);
  expect(section.containerMode).toBe('page');
  expect(pageContainerStyle('site').paddingInline).toContain('--storefront-content-gutter');
 });
 it('renders semantic editable text and both actions outside the image',()=>{
  const section=websiteBuilderHero('f01','/storage/cms-media/hero.webp');
  const html=renderToStaticMarkup(<CorporateHero section={section}/>);
  expect(html).toContain('corp-hero-split');
  expect(html).toContain('<h1');
  expect(html).toContain('Your business.');
  expect(html).toContain('data-cms-action="secondary"');
  expect(html).toContain('src="/storage/cms-media/hero.webp"');
  expect(html).toContain('href="#section-f01-builder"');
  expect(html).not.toContain('corp-workflow');
 });
 it('keeps centered Hero unchanged and supports sparse composite data',()=>{
  const sparse={id:'sparse',type:'hero_banner' as const,title:'Editable',heroBannerPreset:'corporate-split-composite' as const,isVisible:true};
  expect(imageTargets(sparse)).toHaveLength(1);
  expect(renderToStaticMarkup(<CorporateHero section={sparse} editing/>)).toContain('Add hero artwork');
  const html=renderToStaticMarkup(<CorporateHero section={{...sparse,heroBannerPreset:'corporate-centered'}}/>);
  expect(html).not.toContain('corp-hero-split');
 });
});
it('keeps split Hero secondary text readable across section surfaces',()=>{
 const base={...websiteBuilderHero('qa','/hero.webp'),customColors:{secondaryBtnBg:'#8524ff',secondaryBtnText:'#ffffff',titleColor:'#0d1234'}};
 const light=renderToStaticMarkup(<CorporateHero section={{...base,surface:'light'}}/>);
 const contrast=renderToStaticMarkup(<CorporateHero section={{...base,surface:'contrast',customColors:{...base.customColors,titleColor:'#ffffff'}}}/>);
 expect(light).toContain('style="color:#8524ff"');expect(contrast).toContain('style="color:#ffffff"');
});
