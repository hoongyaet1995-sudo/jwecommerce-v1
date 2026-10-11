import {describe,it,expect} from 'vitest';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
import {isPageStyle} from './pageStyle';
describe('Food & Beverage template capability gate',()=>{
 it('creates independent ten-section pantry pages with genuine food feeds only',()=>{
  const products=[{id:'coffee',title:'House Blend Coffee',categoryId:'food',status:'active'},{id:'desk',title:'Desk lamp',status:'active'},{id:'table',title:'LACK Coffee table',categoryId:'furniture',status:'active'},{id:'draft',title:'Bread',categoryId:'food',status:'draft'}] as any;
  const a=createPageTemplateSections('food-beverage',{products,categories:[{id:'food',name:'Food & Beverage'}] as any},'a');
  const b=createPageTemplateSections('food-beverage',{},'b');
  expect(a).toHaveLength(10);expect(a[2].productIds).toEqual(['coffee']);expect(b[2].productIds).toEqual([]);
  expect(a[5].productTabs?.map(t=>t.label)).toEqual(['Everyday','Birthday','Thank You','Housewarming','Festive']);
  expect(isPageStyle(createPageTemplateStyle('food-beverage'))).toBe(true);
  expect(a[1].items![0].id).not.toBe(b[1].items![0].id);
 });
 it('provides Content controls for sparse recipe cards, producer values and pantry tabs',()=>{
  const base={id:'s',type:'shop_navigation',title:'Recipes',isVisible:true,shopNavigationPreset:'recipe-cards',items:[]} as any;
  expect(repeatedItemFields(base)).toEqual(expect.arrayContaining(['title','description','imageUrl','badge','linkUrl']));
  base.items=[{id:'new',title:'Recipe',imageUrl:''}];expect(imageTargets(base)[0].id).toBe('item:new:imageUrl');
  expect(repeatedItemFields({id:'p',type:'media_text_split',title:'Story',isVisible:true,layoutPreset:'producer-story'} as any)).toEqual(['title','description','icon']);
  expect(sectionContentFields({id:'p',type:'product_listing',title:'Pantry',isVisible:true,listingPreset:'pantry-tabs'} as any)).toContain('buttonLink');
 });
});
