import {describe,it,expect} from 'vitest';
import {duplicateSection} from './duplicateSection';
import {collectionBannerDefaults} from './collectionBanners';
import {imageTargets} from './imageTargets';
import {parseDraftImport} from './pageImport';
describe('Section duplication',()=>{
 it('remaps item image ownership and saves both independent sections',()=>{
  const original={id:'banner',...collectionBannerDefaults()};const copy=duplicateSection(original);
  expect(copy.id).not.toBe(original.id);
  expect(copy.items![0].id).not.toBe(original.items![0].id);
  expect(copy.imageSlots!.map(s=>s.target)).toEqual(imageTargets(copy).map(t=>t.id));
  expect(parseDraftImport(JSON.stringify({title:'Home',slug:'home',template:'standard',sections:[original,copy]}),'54').sections).toHaveLength(2);
  copy.items![0].title='Changed';copy.customColors!.primaryBtnBg='#000000';copy.typography!.itemTitle!.fontSize=50;
  expect(original.items![0].title).toBe('The warm\nminimal edit.');expect(original.customColors!.primaryBtnBg).toBe('#454e37');expect(original.typography!.itemTitle!.fontSize).toBe(40);
 });
 it('preserves section-owned image targets while generating unique item IDs',()=>{
  const section={id:'story',type:'media_text_split' as const,title:'Story',isVisible:true,items:[{id:'one',title:'Step'}],imageSlots:[{target:'mediaUrl',imageOverlay:true,imageOverlayOpacity:30 as const}]};
  const a=duplicateSection(section),b=duplicateSection(section);
  expect(a.imageSlots).toEqual(section.imageSlots);expect(a.items![0].id).not.toBe(b.items![0].id);
 });
});
