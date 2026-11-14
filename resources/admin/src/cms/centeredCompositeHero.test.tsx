import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {centeredCompositeHero} from './centeredCompositeHero';
import {CorporateHero} from '../components/pages/CorporateSections';
import {resolveStyledSection,generatePalette} from './pageStyle';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets,pruneImageSlots} from './imageTargets';

describe('Centered composite hero passport',()=>{
 it('keeps owned alt and crop data while a text-only layout hides the image',()=>{
  const s=centeredCompositeHero('switch');s.imageSlots=[{target:'mediaUrl',mediaAlt:'Keep this alt',imageFit:'contain'}];
  expect(pruneImageSlots({...s,heroBannerPreset:'corporate-centered'}).imageSlots).toEqual(s.imageSlots);
 });
 it('keeps a neutral starter and exposes only live copy/media/actions',()=>{
  const s=centeredCompositeHero('generic');
  expect(s.customColors).toBeUndefined();expect(s.brandPalette).toBeUndefined();
  expect(s.title).not.toContain('JW');expect(s.buttonLink).toBe('');
  expect(sectionContentFields(s)).toEqual(expect.arrayContaining(['title','content','mediaUrl','mediaAlt','buttonText','buttonLink','secondaryButtonText','secondaryButtonLink']));
  expect(imageTargets(s).map(x=>x.id)).toEqual(['mediaUrl']);
 });
 it('renders editable media and actions using the resolved page colours without changing the original',()=>{
  const s={...centeredCompositeHero('qa'),title:'Editable heading',mediaUrl:'/images/hero.png',imageSlots:[{target:'mediaUrl',mediaAlt:'Editable alt'}],buttonText:'Demo',buttonLink:'/pages/contact',secondaryButtonText:'Explore',secondaryButtonLink:'#section-next'};
  for(const [main,sub] of [['#2860ff','#8524ff'],['#165d3b','#b04612']]){
   const palette=generatePalette(main,sub);
   const html=renderToStaticMarkup(<CorporateHero section={resolveStyledSection(s,{palette,colorSource:'custom'})}/>);
   expect(html).toContain('corp-hero-centered-composite');expect(html).toContain(`--corp-blue:${main}`);expect(html).toContain(`--corp-violet:${sub}`);
   expect(html).toContain('alt="Editable alt"');expect(html).toContain('data-cms-action="secondary"');expect(html).toContain('href="#section-next"');
  }
  expect(s.customColors).toBeUndefined();
 });
 it('handles sparse data, hides incomplete actions and leaves original centred hero text-only',()=>{
  const s=centeredCompositeHero('sparse');
  expect(renderToStaticMarkup(<CorporateHero section={s} editing/>)).toContain('Add hero artwork');
  expect(renderToStaticMarkup(<CorporateHero section={s}/>)).not.toContain('<a ');
  expect(renderToStaticMarkup(<CorporateHero section={{...s,heroBannerPreset:'corporate-centered',mediaUrl:'/ignored.png'}}/>)).not.toContain('<img');
 });
});
