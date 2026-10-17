// @vitest-environment jsdom
import {describe,it,expect} from 'vitest';
import {generatePalette,resolvePageStyle,presetStyle,resolveStyledSection,isPageStyle} from './pageStyle';
describe('theme palette inheritance',()=>{
 const theme=generatePalette('#173247','#ef7b32');
 it('follows current theme only with explicit opt-in',()=>{
  const old=presetStyle('warm');expect(resolvePageStyle(old,theme)?.palette).toEqual(old.palette);
  expect(resolvePageStyle({...old,colorSource:'theme'},theme)?.palette).toEqual(theme);
  expect(resolvePageStyle(undefined,theme)).toBeUndefined();
 });
 it('preserves typography, section palettes and default authored overrides',()=>{
  const style=resolvePageStyle({...presetStyle('warm'),colorSource:'theme'},theme);
  const section={id:'a',type:'rich_text' as const,title:'A',isVisible:true,customColors:{primaryBtnBg:'#112233'},typography:{title:{fontSize:32}}};
  expect(resolveStyledSection(section,style).customColors?.primaryBtnBg).toBe('#112233');
  expect(resolveStyledSection({...section,surface:'light'},style).customColors?.secondaryBtnBg).toBe('#ef7b32');
  expect(resolveStyledSection({...section,brandPalette:generatePalette('#883311'),surface:'contrast'},style).customColors?.customBgColor).toBe('#883311');
 });
 it('validates color source without changing legacy contract',()=>{
  expect(isPageStyle({...presetStyle('warm'),colorSource:'theme'})).toBe(true);
  expect(isPageStyle({...presetStyle('warm'),colorSource:'unknown'})).toBe(false);
 });
});

describe('explicit typography above semantic surfaces',()=>{
 for(const surface of ['light','contrast'] as const){
  it(`honors page and section colors on ${surface}`,()=>{
   const page={...presetStyle('warm'),typography:{title:{color:'#ff7300'},content:{color:'#224466'}}};
   const section={id:'text',type:'media_text_split' as const,title:'Text',isVisible:true,surface};
   expect(resolveStyledSection(section,page).typography?.title?.color).toBe('#ff7300');
   expect(resolveStyledSection({...section,typography:{title:{color:'#00aa44'}}},page).typography?.title?.color).toBe('#00aa44');
   expect(resolveStyledSection(section,page).typography?.content?.color).toBe('#224466');
   expect(resolveStyledSection(section,presetStyle('warm')).typography?.title?.color).toBe(surface==='contrast'?presetStyle('warm').palette.light:presetStyle('warm').palette.ink);
  });
 }
});
