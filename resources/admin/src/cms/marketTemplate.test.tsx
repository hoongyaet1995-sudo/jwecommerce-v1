import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {isPageStyle} from './pageStyle';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {MarketNavigation} from '../components/pages/MarketNavigation';
import {changeShopNavigationLayout} from './shopNavigation';
import {sectionContentFields} from './contentCapabilities';
describe('Everyday Market template',()=>{
 it('creates independent eleven-section drafts with live catalog identities',()=>{
  const a=createPageTemplateSections('everyday-market',{products:[{id:'active',status:'active'},{id:'old',status:'draft'}] as any},'a');const b=createPageTemplateSections('everyday-market',{},'b');
  expect(a).toHaveLength(11);expect(isPageStyle(createPageTemplateStyle('everyday-market'))).toBe(true);expect(a[3].productIds).toEqual(['active']);expect(a[0].items![0].id).not.toBe(b[0].items![0].id);expect(a.every(s=>s.motionPreset==='subtle-lift')).toBe(true);
 });
 it('preserves repeatable fields and owned photos across layout switches',()=>{
  const section=createPageTemplateSections('everyday-market',{},'a')[2];
  expect(repeatedItemFields(section)).toEqual(expect.arrayContaining(['subtitle','description','badge','buttonText','imageUrl','linkUrl']));expect(imageTargets(section)).toHaveLength(3);
  expect(sectionContentFields(createPageTemplateSections('everyday-market',{},'a')[0])).not.toContain('buttonText');
  const restored=changeShopNavigationLayout(changeShopNavigationLayout(section,'cards'),'market-offers');expect(restored.items).toEqual(section.items);
 });
 it('renders all authored cards, actions and optional circle labels',()=>{
  const sections=createPageTemplateSections('everyday-market',{},'a');const section=sections[2];section.items!.push({...section.items![0],id:'fourth',title:'Fourth offer',description:'Editable offer description'});
  const html=renderToStaticMarkup(<MarketNavigation section={section} catalog={{categories:[],collections:[]} as any} editing/>);
  expect(html).toContain('Fourth offer');expect(html).toContain('20%');expect(html).toContain('Shop beauty');expect(html).toContain('data-cms-text="itemContent"');
  const hero=renderToStaticMarkup(<MarketNavigation section={sections[0]} catalog={{categories:[],collections:[]} as any} editing/>);expect(hero).toContain('row-span-2');expect(hero).toContain('The beauty shelf');expect(hero).toContain('data-cms-text="lead"');
 });
});
