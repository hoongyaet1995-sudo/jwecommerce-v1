import type { SectionColorConfig } from '../types';

/** ZIP6 Default Tech palette, explicit for strict own-key Inspector controls. */
export const DEFAULT_SECTION_COLORS: Required<SectionColorConfig> = {
  eyebrowColor: '#818cf8',
  titleColor: '#ffffff',
  contentColor: '#d4d4d8',
  primaryBtnBg: '#4f46e5',
  primaryBtnText: '#ffffff',
  secondaryBtnBg: 'rgba(255, 255, 255, 0.1)',
  secondaryBtnText: '#ffffff',
  customBgColor: '',
};

export const createSectionColorDefaults = (): SectionColorConfig => ({ ...DEFAULT_SECTION_COLORS });

export const mergeSectionColorDefaults = (colors?: SectionColorConfig): SectionColorConfig => ({
  ...DEFAULT_SECTION_COLORS,
  ...colors,
});
