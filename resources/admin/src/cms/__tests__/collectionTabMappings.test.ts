import {describe, expect, it} from 'vitest';
import {
  createCollectionTabMappings,
  repairLegacyCollectionTabs,
} from '../collectionTabMappings';

describe('Collection Tabs catalog mappings', () => {
  const categories = [
    {id: '1', name: 'Acoustic Studio', status: 'active'},
    {id: '2', name: 'Ergonomic Desk', status: 'active'},
    {id: '3', name: 'Hidden', status: 'draft'},
  ] as const;
  const collections = [
    {id: '10', title: 'The Monolith Series', status: 'active'},
    {id: '11', title: 'Deep Obsidian', status: 'active'},
    {id: '12', title: 'Archived', status: 'archived'},
  ] as const;

  it('creates tabs only from active catalog records, never the old mock IDs', () => {
    expect(createCollectionTabMappings(categories, collections)).toEqual([
      {id: 'tab-1', label: 'Acoustic Studio', sourceType: 'category', sourceId: '1', productLimit: 4},
      {id: 'tab-2', label: 'Ergonomic Desk', sourceType: 'category', sourceId: '2', productLimit: 4},
      {id: 'tab-3', label: 'The Monolith Series', sourceType: 'collection', sourceId: '10', productLimit: 4},
      {id: 'tab-4', label: 'Deep Obsidian', sourceType: 'collection', sourceId: '11', productLimit: 4},
    ]);
  });

  it('repairs exactly the legacy mock collection tabs without overwriting a custom tab', () => {
    const legacy = {
      id: 'tabs', type: 'collection_tabs', title: 'Explore', isVisible: true,
      tabCategories: ['Electronics & Gadgets', 'Premium Audio', 'Best Sellers 2026', 'Studio Essentials'],
      tabs: [
        {id: 'tab-1', label: 'Electronics & Gadgets', sourceType: 'category', sourceId: 'cat-electronics', productLimit: 4},
        {id: 'tab-2', label: 'Premium Audio', sourceType: 'category', sourceId: 'cat-audio', productLimit: 4},
        {id: 'tab-3', label: 'Best Sellers 2026', sourceType: 'collection', sourceId: 'col-best-sellers', productLimit: 4},
        {id: 'tab-4', label: 'Studio Essentials', sourceType: 'collection', sourceId: 'col-studio-essentials', productLimit: 4},
      ],
    } as any;

    expect(repairLegacyCollectionTabs(legacy, categories, collections)).toMatchObject({
      tabCategories: ['Acoustic Studio', 'Ergonomic Desk', 'The Monolith Series', 'Deep Obsidian'],
      tabs: createCollectionTabMappings(categories, collections),
    });

    const custom = {...legacy, tabs: [{...legacy.tabs[0], sourceId: '1'}]};
    expect(repairLegacyCollectionTabs(custom, categories, collections)).toBe(custom);
  });

  it('uses no unmapped tab when there is no active catalog yet', () => {
    expect(createCollectionTabMappings([], [])).toEqual([]);
  });
});
