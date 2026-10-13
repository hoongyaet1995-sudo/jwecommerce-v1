import {describe,it,expect} from 'vitest';
import {resolveHeaderPresentation,isHeaderPresentation} from './headerPresentation';
import type {PageSection} from '../types';
const hero={type:'hero_slider',isVisible:true} as PageSection;
describe('header presentation',()=>{
 it('preserves normal behavior for old documents',()=>expect(resolveHeaderPresentation(undefined,undefined,[hero]).mode).toBe('normal'));
 it('uses store settings ahead of retained legacy page overrides',()=>{
  expect(resolveHeaderPresentation({mode:'inherit',tone:'dark'},{mode:'transparent',tone:'light'},[hero])).toEqual({mode:'transparent',tone:'light'});
  expect(resolveHeaderPresentation({mode:'normal',tone:'dark'},{mode:'transparent',tone:'light'},[hero]).mode).toBe('transparent');
 });
 it('falls back when the first visible section is not a hero',()=>{
  expect(resolveHeaderPresentation({mode:'translucent',tone:'light'},undefined,[{...hero,isVisible:false},{type:'product_listing',isVisible:true} as PageSection]).mode).toBe('normal');
  expect(resolveHeaderPresentation({mode:'transparent',tone:'dark'},undefined,[]).mode).toBe('normal');
 });
 it('rejects unsupported values',()=>{
  expect(isHeaderPresentation({mode:'transparent',tone:'light'})).toBe(true);
  expect(isHeaderPresentation({mode:'fixed',tone:'light'})).toBe(false);
  expect(isHeaderPresentation({mode:'normal',tone:'dark',extra:true})).toBe(false);
 });
});
