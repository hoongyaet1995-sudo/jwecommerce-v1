import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {resolveShopCard,shopNavigationHref,changeShopNavigationLayout} from '../../cms/shopNavigation';
import {ShopNavigation} from './ShopNavigation';
import {imageTargets,pruneImageSlots} from '../../cms/imageTargets';
import type {PageSection} from '../../types';
import {parseDraftImport} from '../../cms/pageImport';
import {collectionBannerDefaults} from '../../cms/collectionBanners';
const catalog={products:[],categories:[{id:'51',type:'category' as const,slug:'living-room',label:'Living Room',image:'/images/living.jpg'}],collections:[]};
describe('Shop Navigation',()=>{
 it('centers headings and applies spacious padding in both layouts',()=>{
  for(const shopNavigationPreset of ['cards','collection-banners'] as const){
   const section:PageSection={id:'nav',type:'shop_navigation',title:'Rooms',isVisible:true,textAlignment:'center',paddingY:'spacious',showSectionHeading:true,shopNavigationPreset,items:[]};
   const html=renderToStaticMarkup(<ShopNavigation section={section} catalog={catalog}/>);
   expect(html).toContain('text-align:center');expect(html).toContain('py-20');
   if(shopNavigationPreset==='cards')expect(html).toContain('flex-col items-center');
  }
 });
 it('switches a cropped card into owned banner geometry without losing its image or destination',()=>{
  const section:PageSection={id:'nav',...collectionBannerDefaults(),shopNavigationPreset:'cards',imageSlots:[{target:'item:collection-living:imageUrl',imageRatio:'1:1',imagePosition:'top-left',imageOverlay:true,imageOverlayOpacity:30}]};
  const next=changeShopNavigationLayout(section,'collection-banners');expect(next.items).toEqual(section.items);expect(next.imageSlots).toEqual([{target:'item:collection-living:imageUrl',imagePosition:'top-left',imageOverlay:true,imageOverlayOpacity:30}]);
 });
 it('imports the complete factory including image shade without weakening contracts',()=>{
  const doc={title:'Home',slug:'home',template:'standard',sections:[{id:'banners',...collectionBannerDefaults()}]};
  expect(parseDraftImport(JSON.stringify(doc),'54').sections[0].bannerProportions).toBe('60:40');
 });
 it('renders every banner with shared image slots and preserves source-owned destinations',()=>{
  const section:PageSection={id:'banners',...collectionBannerDefaults()};
  section.items!.push({id:'third',title:'More rooms',description:'Third banner',buttonText:'Browse',sourceType:'category',sourceId:'51',linkUrl:'/wrong'});
  const html=renderToStaticMarkup(<ShopNavigation section={section} catalog={catalog}/>);
  expect(html.match(/<article/g)).toHaveLength(3);
  expect(html).toContain('grid-cols-1');expect(html).toContain('grid-cols-[3fr_2fr]');
  expect(html).toContain('href="/categories/living-room"');expect(html).not.toContain('/wrong');
  expect(html).toContain('Third banner');expect(html).toContain('data-image-overlay');
  expect(html).not.toContain('<h2');expect(imageTargets(section).every(t=>t.geometryLocked)).toBe(true);
  expect(collectionBannerDefaults().items).toHaveLength(2);
 });
 it('follows taxonomy metadata while presentation overrides never redirect it',()=>{
  expect(resolveShopCard({id:'a',title:'',sourceType:'category',sourceId:'51'},catalog)).toEqual({title:'Living Room',image:'/images/living.jpg',href:'/categories/living-room',available:true});
  expect(resolveShopCard({id:'a',title:'My room',imageUrl:'https://example.com/photo.jpg',linkUrl:'/wrong',sourceType:'category',sourceId:'51'},catalog)).toEqual({title:'My room',image:'https://example.com/photo.jpg',href:'/categories/living-room',available:true});
 });
 it('does not redirect missing taxonomy or execute custom unsafe links',()=>{
  expect(resolveShopCard({id:'a',title:'Gone',sourceType:'category',sourceId:'999',linkUrl:'/stale'},catalog).available).toBe(false);
  for(const href of ['javascript:alert(1)','//evil.example','/\\evil','\nhttps://example.com','data:text/html,x'])expect(shopNavigationHref(href)).toBe('');
  expect(shopNavigationHref('/shop')).toBe('/shop');
 });
 it('renders all seven cards and preserves stable image identities after reorder/delete',()=>{
  const section:PageSection={id:'nav',type:'shop_navigation',title:'Shop by room',isVisible:true,columnsCount:5,items:Array.from({length:7},(_,i)=>({id:`card-${i}`,title:`Room ${i}`,sourceType:'custom',linkUrl:'/shop',imageUrl:'https://example.com/room.jpg'}))};
  const html=renderToStaticMarkup(<ShopNavigation section={section} catalog={catalog}/>);
  expect(html.match(/href="\/shop"/g)).toHaveLength(7);expect(html).toContain('grid-cols-2');expect(html).toContain('grid-cols-5');
  const reordered={...section,items:[...section.items!].reverse(),imageSlots:[{target:'item:card-1:imageUrl',imageRatio:'1:1' as const},{target:'item:deleted:imageUrl',imageRatio:'1:1' as const}]};
  expect(imageTargets(reordered)[0].id).toBe('item:card-6:imageUrl');
  expect(pruneImageSlots(reordered).imageSlots).toHaveLength(1);
 });
 it('hides unavailable customer cards but leaves an editable placeholder',()=>{
  const section:PageSection={id:'nav',type:'shop_navigation',title:'Rooms',isVisible:true,items:[{id:'a',title:'Missing',sourceType:'category',sourceId:'999'}]};
  expect(renderToStaticMarkup(<ShopNavigation section={section} catalog={catalog}/>)).not.toContain('Missing');
  expect(renderToStaticMarkup(<ShopNavigation section={section} catalog={catalog} editing/>)).toContain('Select a destination');
 });
});
