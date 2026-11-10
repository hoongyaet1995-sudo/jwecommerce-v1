import {describe,it,expect} from 'vitest';
import {corporateLandingSections} from './corporateLanding';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {imageTargets} from './imageTargets';
describe('corporate editable starter contracts',()=>{
 it('creates independent page-aligned sections with sparse capabilities',()=>{
  const a=corporateLandingSections('a'),b=corporateLandingSections('b');
  expect(a.map(s=>s.type)).toEqual(['hero_banner','media_gallery','media_text_split']);
  expect(a.every(s=>s.containerMode==='page'&&s.motionPreset==='subtle-lift')).toBe(true);
  a[1].items![0].title='Changed';expect(b[1].items![0].title).not.toBe('Changed');
  const tabs={...b[1],items:[]};expect(repeatedItemFields(tabs)).toEqual(expect.arrayContaining(['title','description','icon','imageUrl','buttonText','linkUrl']));
  const feature={...b[2],items:[]};expect(repeatedItemFields(feature)).toContain('icon');expect(imageTargets(feature).map(t=>t.id)).toContain('mediaUrl');
  expect(sectionContentFields(feature)).toContain('secondaryButtonLink');
  expect(imageTargets(b[0])).toEqual([]);
 });
});
