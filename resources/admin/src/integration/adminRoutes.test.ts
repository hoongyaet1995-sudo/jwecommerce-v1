import { describe, expect, it } from 'vitest';
import { ADMIN_PATH_BY_SECTION, pathForSection, sectionFromPath } from './adminRoutes';

describe('Admin route state review coverage', () => {
  it('exposes the catalog reviews route in the canonical state map', () => {
    expect(ADMIN_PATH_BY_SECTION['catalog-reviews']).toBe('/admin/catalog/reviews');
    expect(pathForSection('catalog-reviews')).toBe('/admin/catalog/reviews');
    expect(sectionFromPath('/admin/catalog/reviews')).toBe('catalog-reviews');
  });
});
