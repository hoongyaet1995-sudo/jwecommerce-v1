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

  it('creates every one of the 26 source-supported section templates with the complete ZIP6 palette contract', () => {
    expect(SECTION_TEMPLATES).toHaveLength(26);

    for (const template of SECTION_TEMPLATES) {
      expect(template.defaultData.customColors).toEqual(expectedDefaultColors);
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
