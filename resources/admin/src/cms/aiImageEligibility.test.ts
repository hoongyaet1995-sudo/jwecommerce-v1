import {describe,it,expect} from 'vitest';
import {canGenerateImageTarget} from './imageTargets';
import type {PageSection} from '../types';
const section=(type:string,extra={})=>({id:'s',type,title:'Section',isVisible:true,...extra}) as PageSection;
describe('editorial generation eligibility',()=>{
 it('allows existing editorial photo slots and excludes protected or unsupported fields',()=>{
  expect(canGenerateImageTarget(section('hero_slider',{items:[{id:'slide',imageUrl:'/original.png'}]}),'item:slide:imageUrl')).toBe(true);
  expect(canGenerateImageTarget(section('shop_navigation',{items:[{id:'i',imageUrl:'/editorial.png'}]}),'item:i:imageUrl')).toBe(true);
  expect(canGenerateImageTarget(section('media_text_split'),'mediaUrl')).toBe(true);
  for(const type of ['promotion_highlights','logo_cloud','testimonials_slider'])expect(canGenerateImageTarget(section(type,{items:[{id:'i',imageUrl:'/original.png',authorAvatar:'/avatar.png'}]}),'item:i:imageUrl')).toBe(false);
  expect(canGenerateImageTarget(section('hero_banner',{mediaType:'video'}),'mediaUrl')).toBe(false);
  expect(canGenerateImageTarget(section('hero_slider',{items:[{id:'slide',videoUrl:'/video.mp4'}]}),'item:slide:imageUrl')).toBe(false);
 });
});
