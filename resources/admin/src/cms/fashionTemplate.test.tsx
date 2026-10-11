import React from 'react';
import {describe,it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {createPageTemplateSections,createPageTemplateStyle} from './pageTemplates';
import {isPageStyle} from './pageStyle';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets,pruneImageSlots} from './imageTargets';
import {textRoles} from './inspectorCapabilities';
import {EditorialOverlaySlider} from '../components/pages/EditorialOverlaySlider';
import {ProductLookbook} from '../components/pages/ProductLookbook';
describe('Fashion template capabilities',()=>{
 it('creates nine independent sections with canonical typography and current catalog identities',()=>{
  const a=createPageTemplateSections('fashion-design',{products:[{id:'live',status:'active'},{id:'draft',status:'draft'}] as any},'a');const b=createPageTemplateSections('fashion-design',{},'b');
  expect(a).toHaveLength(9);expect(isPageStyle(createPageTemplateStyle('fashion-design'))).toBe(true);expect(a[2].productIds).toEqual(['live']);expect(b[2].productIds).toEqual([]);expect(a[0].items![0].id).not.toBe(b[0].items![0].id);
  expect(imageTargets(a[1]).every(t=>t.id.includes('a-fashion-1-item'))).toBe(true);
 });
 it('never maps Men to the Women category and ignores inactive taxonomy',()=>{
  const categories=[{id:'women',name:'Women',slug:'women',status:'active'},{id:'men',name:'Men',slug:'men',status:'active'},{id:'old',name:'Accessories',slug:'accessories',status:'draft'}] as any;
  const tabs=createPageTemplateSections('fashion-design',{categories},'taxonomy')[5].productTabs!;
  expect(tabs.map(tab=>tab.sourceId)).toEqual(['','women','men','']);
 });
 it('exposes all hero copy/actions and lookbook repeatable fields, preserving dormant images on switches',()=>{
  const sections=createPageTemplateSections('fashion-design',{},'x');const hero=sections[0],lookbook=sections[7];
  expect(repeatedItemFields(hero)).toEqual(expect.arrayContaining(['title','description','imageUrl','caption','secondaryButtonLink','thirdButtonText','thirdButtonLink']));
  expect(repeatedItemFields(lookbook)).toEqual(['title','description','imageUrl','buttonText','linkUrl']);expect(textRoles(lookbook)).toContain('lead');
  const target=imageTargets(lookbook)[0];expect(target.itemId).toBe(lookbook.items![0].id);const switched={...lookbook,listingPreset:'editorial-grid' as const,imageSlots:[{target:target.id,imagePosition:'top-center' as const}]};expect(imageTargets(switched)).toEqual([]);expect(pruneImageSlots(switched).imageSlots).toHaveLength(1);
 });
 it('renders all three hero actions and every editable lookbook card, including its button',()=>{
  const sections=createPageTemplateSections('fashion-design',{},'x');const hero=renderToStaticMarkup(<EditorialOverlaySlider section={sections[0]} index={0} onSelect={()=>{}} onPause={()=>{}} isEditingMode/>);
  expect(hero).toContain('Shop New In');expect(hero).toContain('Shop Women');expect(hero).toContain('Shop Men');
  const lookbook=sections[7];lookbook.items!.push({...lookbook.items![0],id:'second',title:'Second feature',buttonText:'Explore'});
  const html=renderToStaticMarkup(<ProductLookbook section={lookbook} products={[]} context={{mode:'admin-preview',currency:'MYR'} as any} editing/>);expect(html).toContain('Second feature');expect(html).toContain('Explore');expect(html).toContain('data-cms-text="lead"');
 });
});

