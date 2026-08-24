import { describe, expect, it } from 'vitest';
import { buildMenuTargetOptions, changeMenuLinkType, selectMenuTarget } from '../menuLinkTargets';

const categories = [
  { id: '1', name: 'Audio', slug: 'audio', status: 'active' as const },
  { id: '2', name: 'Hidden', slug: 'hidden', status: 'draft' as const },
];
const collections = [
  { id: '3', title: 'Featured', slug: 'featured', status: 'active' as const },
  { id: '4', title: 'Archived', slug: 'archived', status: 'archived' as const },
];
const pages = [
  { id: '5', title: 'Brand Story', slug: 'brand-story', status: 'published' as const },
  { id: '6', title: 'Draft Page', slug: 'draft-page', status: 'draft' as const },
];

describe('menu link targets', () => {
  it('offers only storefront-eligible target records with canonical public paths', () => {
    expect(buildMenuTargetOptions('category', categories, collections, pages)).toEqual([
      { id: '1', label: 'Audio', url: '/categories/audio' },
    ]);
    expect(buildMenuTargetOptions('collection', categories, collections, pages)).toEqual([
      { id: '3', label: 'Featured', url: '/collections/featured' },
    ]);
    expect(buildMenuTargetOptions('page', categories, collections, pages)).toEqual([
      { id: '5', label: 'Brand Story', url: '/pages/brand-story' },
    ]);
  });

  it('clears stale internal targets when type changes and derives a URL from the selected target', () => {
    expect(changeMenuLinkType({ linkType: 'category', targetId: '1', url: '/categories/audio' }, 'collection'))
      .toEqual({ linkType: 'collection', targetId: undefined, url: '' });
    expect(selectMenuTarget({ linkType: 'collection', url: '' }, { id: '3', label: 'Featured', url: '/collections/featured' }))
      .toEqual({ linkType: 'collection', targetId: '3', url: '/collections/featured' });
  });
});
