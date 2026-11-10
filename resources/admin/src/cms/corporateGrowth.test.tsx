import {describe,it,expect} from 'vitest';
import {corporateGrowthSections} from './corporateLanding';
import {repeatedItemFields} from './repeatedItemContract';
import {sectionContentFields} from './contentCapabilities';
import {imageTargets} from './imageTargets';
import {supportsMediaWidth} from '../components/pages/MediaWidthRegion';
describe('approved AI Support and Marketing starters',()=>{
 it('keeps independent content, page alignment and exact CTA ownership',()=>{
  const a=corporateGrowthSections('a'),b=corporateGrowthSections('b');
  expect(a.map(s=>s.type)).toEqual(['media_text_split','media_text_split']);
  expect(a[0].layoutPreset).toBe('corporate-image-story');expect(a[1].layoutPreset).toBe('corporate-image-story');
  expect(a.every(s=>s.containerMode==='page'&&s.motionPreset==='subtle-lift')).toBe(true);
  expect(a[0].mediaPosition).toBe('right');expect(a[1].mediaPosition).toBe('left');
  expect(a[0].buttonLink).toBe('/pages/multichannel-ai-chat');expect(a[1].buttonLink).toBe('/pages/marketing-studio');
  expect(a[1].secondaryButtonText).toBe('');
  expect(a[1].items).toHaveLength(3);
  expect(a[0].items).toHaveLength(3);
  a[0].items![0].title='Changed';expect(b[0].items![0].title).not.toBe('Changed');
 });
 it('exposes sparse support editing without optional values and hides unsupported full-width controls',()=>{
  const s={...corporateGrowthSections('s')[0],items:[],mediaUrl:''};
  expect(repeatedItemFields(s)).toEqual(['title','description','icon']);
  expect(sectionContentFields(s)).toContain('secondaryButtonLink');
  expect(imageTargets(s).map(t=>t.id)).toEqual(['mediaUrl']);expect(supportsMediaWidth(s)).toBe(false);
 });
});
