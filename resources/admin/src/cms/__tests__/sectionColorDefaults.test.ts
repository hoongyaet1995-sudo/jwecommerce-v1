import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { SECTION_TEMPLATES } from '../../components/pages/AddSectionModal';
import { DEFAULT_SECTION_COLORS, createSectionColorDefaults, mergeSectionColorDefaults } from '../sectionColorDefaults';

const expectedDefaultColors = {
  eyebrowColor: '#818cf8',
  titleColor: '#ffffff',
  contentColor: '#d4d4d8',
  primaryBtnBg: '#4f46e5',
  primaryBtnText: '#ffffff',
  secondaryBtnBg: 'rgba(255, 255, 255, 0.1)',
  secondaryBtnText: '#ffffff',
  customBgColor: '',
};

describe('CMS section color defaults', () => {
  it('uses the same Default Tech values declared by the canonical page contract', () => {
    const manifest = JSON.parse(readFileSync(
      new URL('../../../../../resources/cms/page-contract.v1.json', import.meta.url),
      'utf8',
    ));

    expect(DEFAULT_SECTION_COLORS).toEqual(manifest.definitions.sectionColorConfig.defaults);
  });

  it('retains the original 27 palettes and adds a readable standalone Values List palette', () => {
    expect(SECTION_TEMPLATES).toHaveLength(68);

    for (const template of SECTION_TEMPLATES.filter(template => !template.defaultData.contactPreset && !template.defaultData.layoutPreset && !template.defaultData.ctaPreset && !template.defaultData.galleryPreset && !template.defaultData.featureCardsPreset && !template.defaultData.chapterPreset && !template.defaultData.faqPreset && template.type !== 'chapter_navigation' && template.type !== 'payment_methods' && template.type !== 'delivery_table' && template.type !== 'promotion_highlights' && template.type !== 'shop_navigation' && !template.defaultData.sliderPreset && !template.defaultData.policyPreset && !template.defaultData.listingPreset)) {
      expect(template.defaultData.customColors).toEqual(template.type==='values_list'?{...expectedDefaultColors,eyebrowColor:'#4f46e5',titleColor:'#171717',contentColor:'#737373'}:expectedDefaultColors);
    }
    for (const template of SECTION_TEMPLATES.filter(template => template.defaultData.contactPreset)) { expect(template.defaultData.customColors!.titleColor).toBe('#171717'); }
    for (const template of SECTION_TEMPLATES) {
      expect(Object.keys(template.defaultData.customColors!).sort()).toEqual(Object.keys(expectedDefaultColors).sort());
      expect(template.defaultData.customColors!.titleColor).toMatch(/^#[0-9a-f]{6}$/i);
      expect(template.defaultData.customColors!.contentColor).toMatch(/^#[0-9a-f]{6}$/i);
    }
  });

  it('creates fresh complete defaults and preserves an operator color while filling every missing key', () => {
    const first = createSectionColorDefaults();
    const second = createSectionColorDefaults();
    first.titleColor = '#123456';

    expect(second.titleColor).toBe('#ffffff');
    expect(mergeSectionColorDefaults({ titleColor: '#123456' })).toEqual({
      ...expectedDefaultColors,
      titleColor: '#123456',
    });
  });
});
