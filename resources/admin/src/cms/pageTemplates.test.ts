import { describe, expect, it } from 'vitest';
import { createPageTemplateSections } from './pageTemplates';

describe('page template draft data', () => {
  it('registers independent six-section Chapters drafts with managed photographs',()=>{
    const first=createPageTemplateSections('about-chapters',{shopName:'Example'},'a');
    const second=createPageTemplateSections('about-chapters',{shopName:'Example'},'b');
    expect(first.map(s=>s.type)).toEqual(['media_text_split','chapter_navigation','chapter_block','chapter_block','chapter_block','cta_strip']);
    expect(first[0].leadText).toBe('An introduction to Example.');
    expect(first[0].containerMode).toBe('full-bleed');
    expect(first[3].items).toHaveLength(2);
    expect(first[4].items).toHaveLength(3);
    expect(first[5].ctaPreset).toBe('editorial-band');
    expect(first[2].mediaUrl).toMatch(/^\/storage\/cms\/media\//);
    first[4].items![0].title='Changed';expect(second[4].items![0].title).not.toBe('Changed');
    expect(first[3].items![0].id).not.toBe(second[3].items![0].id);
  });
  it('creates the Studio design with six independent sections',()=>{
    const first=createPageTemplateSections('about-studio',{shopName:'Example Store'},'a');
    const second=createPageTemplateSections('about-studio',{shopName:'Example Store'},'b');
    expect(first.map(s=>s.type)).toEqual(['media_gallery','media_text_split','feature_cards','media_gallery','feature_cards','cta_strip']);
    expect(first[0].title).toBe('The world of Example Store.');
    expect(first[0].galleryPreset).toBe('editorial-montage');
    expect(first[2].columnsCount).toBe(2);expect(first[4].columnsCount).toBe(3);
    expect(first[5].containerMode).toBe('full-bleed');
    first[0].items![0].title='Changed';expect(second[0].items![0].title).not.toBe('Changed');
    expect(first[0].items![0].id).not.toBe(second[0].items![0].id);
  });
  it('registers the approved six-section editorial About template with isolated editable data', () => {
    const first = createPageTemplateSections('about-editorial', {shopName:'Example Store'}, 'editorial-a');
    const second = createPageTemplateSections('about-editorial', {shopName:'Example Store'}, 'editorial-b');
    expect(first.map(s=>s.type)).toEqual(['media_text_split','media_text_split','rich_text','media_text_split','values_list','cta_strip']);
    expect(first[0].title).toContain('Example Store');
    expect(first[0].layoutPreset).toBe('editorial-hero');
    expect(first[5].ctaPreset).toBe('image-banner');
    expect(first.every(s=>s.sectionBorder==='none')).toBe(true);
    expect(first.filter(s=>s.mediaUrl).every(s=>s.mediaUrl?.startsWith('/storage/cms/media/'))).toBe(true);
    expect(first[4].textAlignment).toBeUndefined();
    first[4].items![0].title='Changed';
    expect(second[4].items![0].title).not.toBe('Changed');
    expect(first[0].id).not.toBe(second[0].id);
  });
  it('keeps unconfigured business facts visibly editable without inventing policies', () => {
    const about = createPageTemplateSections('about', {}, 'draft-a');
    const shipping = createPageTemplateSections('shipping', {}, 'draft-b');
    expect(about.some(s => s.type === 'media_text_split' && s.mediaUrl)).toBe(true);
    expect(JSON.stringify(about)).toContain('To edit:');
    expect(JSON.stringify(shipping)).toContain('To edit:');
    expect(shipping.find(s => s.buttonLink === '/order-tracking')).toBeTruthy();
    expect(JSON.stringify([...about, ...shipping])).not.toMatch(/2016|Stockholm|30-day|Free Express|Aurora/);
    expect(createPageTemplateSections('blank', {}, 'blank')).toEqual([]);
    expect(createPageTemplateSections('terms', {}, 'later')).toEqual([]);
  });
  it('copies configured store facts and active rates, with isolated section and item IDs', () => {
    const context = {shopName: 'Real Store', tagline: 'Real tagline', currency: 'MYR', shippingRates: [
      {id:'active', name:'Local delivery',zoneRegion:'peninsular',isActive:true,rateType:'flat',flatRateCost:8},
      {id:'off',name:'Hidden disabled rate',zoneRegion:'international',isActive:false,rateType:'flat',flatRateCost:99},
    ]};
    const first = createPageTemplateSections('shipping', context, 'first');
    const second = createPageTemplateSections('shipping', context, 'second');
    expect(JSON.stringify(first)).toContain('Local delivery');
    expect(JSON.stringify(first)).toContain('MYR 8.00');
    expect(JSON.stringify(first)).not.toContain('Hidden disabled rate');
    expect(JSON.stringify(createPageTemplateSections('about', context, 'brand'))).toContain('Real tagline');
    expect(first.map(s => s.id).some(id => second.some(s => s.id === id))).toBe(false);
    first[1].items![0].title = 'Edited';
    expect(second[1].items![0].title).not.toBe('Edited');
  });
});
