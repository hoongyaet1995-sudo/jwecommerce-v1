// @vitest-environment jsdom
import {expect,it} from 'vitest';
import type {PageSection} from '../types';
import {resolveTextTarget} from './textToolbar';
import {requiresTextColorItem,resetTextColorBinding,setTextColorBinding,textColorBindingCss} from './textColorBindings';
import {resetTextRole} from './styleInheritance';
import {stylePresets} from './pageStyle';

it('uses the same ancestor item identity and ignores operational controls',()=>{
  const section:PageSection={id:'cards',type:'feature_cards',title:'Cards',isVisible:true,items:[{id:'a',title:'First'},{id:'b',title:'Second'}]};
  const root=document.createElement('div');
  root.innerHTML='<article data-cms-item="a"><h3 data-cms-text="itemTitle"><span>First</span></h3></article><form><label data-cms-text="title">Form</label></form>';
  expect(resolveTextTarget(section,root.querySelector('span')!)).toMatchObject({role:'itemTitle',itemId:'a'});
  expect(resolveTextTarget(section,root.querySelector('label')!)).toBeUndefined();
});
it('allows catalog presentation targets while keeping their colors shared',()=>{
  const section:PageSection={id:'list',type:'product_listing',listingPreset:'editorial-grid',title:'Catalog',isVisible:true};
  const root=document.createElement('div');
  root.innerHTML='<article data-cms-source><h3 data-cms-text="itemTitle" data-cms-source-typography>Product</h3><p data-cms-text="title">Price</p></article>';
  expect(resolveTextTarget(section,root.querySelector('h3')!)).toMatchObject({role:'itemTitle',itemId:undefined});
  expect(resolveTextTarget(section,root.querySelector('p')!)).toBeUndefined();
  expect(requiresTextColorItem(section,'itemTitle')).toBe(false);
});
it('separates role resets from item color resets and leaves siblings intact',()=>{
  const original:PageSection={id:'cards',type:'feature_cards',title:'Cards',isVisible:true,
    typography:{itemTitle:{fontFamily:'serif',fontSize:30,fontWeight:700,lineHeight:1.4,letterSpacing:1}},
    items:[{id:'a',title:'First'},{id:'b',title:'Second'}]};
  const styled=setTextColorBinding(setTextColorBinding(original,'itemTitle','a',{source:'light'}),'itemTitle','b',{source:'dark'});
  const reset=resetTextRole(styled,'itemTitle');
  expect(reset.typography?.itemTitle).toEqual({inheritPage:true});
  expect(reset.textColorBindings).toEqual(styled.textColorBindings);
  const next=resetTextColorBinding(reset,'itemTitle','a');
  expect(next.textColorBindings).toEqual({b:{itemTitle:{source:'dark'}}});
  expect(next.items).toEqual(original.items);
  const css=textColorBindingCss(next,stylePresets[0].palette);
  expect(css).toContain('data-cms-item="b"');
  expect(css).not.toContain('data-cms-item="a"');
});
