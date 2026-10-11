import {describe,it,expect} from 'vitest';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets} from './imageTargets';
import {isPageStyle} from './pageStyle';
describe('Beauty starter capability gate',()=>{
 it('creates nine independent sections and only maps actual beauty catalog products',()=>{
  const catalog=[{id:'skin',title:'Daily skin serum',status:'active'},{id:'desk',title:'Desk lamp',status:'active'}] as any;
  const a=createPageTemplateSections('beauty-skincare',{products:catalog},'a');
  const b=createPageTemplateSections('beauty-skincare',{},'b');
  expect(a).toHaveLength(9);expect(a[2].listingPreset).toBe('beauty-grid');expect(a[2].productIds).toEqual(['skin']);expect(b[2].productIds).toEqual([]);
  expect(isPageStyle(createPageTemplateStyle('beauty-skincare'))).toBe(true);
  expect(a[0].items![0].id).not.toBe(b[0].items![0].id);
 });
 it('exposes fields before entries have values and supports image targets after adding them',()=>{
  const sparse={id:'x',type:'shop_navigation',title:'Skin',isVisible:true,shopNavigationPreset:'image-overlay',items:[]} as any;
  expect(repeatedItemFields(sparse)).toContain('description');
  sparse.items=[{id:'i',title:'Hydration',imageUrl:''}];expect(imageTargets(sparse)[0].id).toBe('item:i:imageUrl');
  const routine={id:'r',type:'media_gallery',title:'Routine',isVisible:true,galleryPreset:'routine-steps',items:[{id:'s',title:'Cleanse',imageUrl:''}]} as any;
  expect(repeatedItemFields(routine)).toEqual(expect.arrayContaining(['title','description','imageUrl']));expect(imageTargets(routine)).toHaveLength(1);
  expect(sectionContentFields({id:'p',type:'product_listing',title:'Products',isVisible:true,listingPreset:'beauty-grid'} as any)).toContain('buttonLink');
 });
});
