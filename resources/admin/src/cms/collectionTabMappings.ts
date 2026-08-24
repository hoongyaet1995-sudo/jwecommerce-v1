import type {PageCollectionTab, PageSection} from '../types';

type CatalogCategory = Pick<import('../types').Category, 'id' | 'name' | 'status'>;
type CatalogCollection = Pick<import('../types').Collection, 'id' | 'title' | 'status'>;

const legacySourceIds = new Set([
  'cat-electronics',
  'cat-audio',
  'col-best-sellers',
  'col-studio-essentials',
]);

/**
 * Creates a publishable Collection Tabs mapping from records that are actually
 * available in the catalog. A catalog that has no active sources intentionally
 * receives no tabs; the existing Inspector can then add a mapping later.
 */
export const createCollectionTabMappings = (
  categories: readonly CatalogCategory[],
  collections: readonly CatalogCollection[],
): PageCollectionTab[] => {
  const categoryTabs = categories
    .filter(category => category.status === 'active')
    .slice(0, 2)
    .map((category, index): PageCollectionTab => ({
      id: `tab-${index + 1}`,
      label: category.name,
      sourceType: 'category',
      sourceId: category.id,
      productLimit: 4,
    }));
  const collectionTabs = collections
    .filter(collection => collection.status === 'active')
    .slice(0, 2)
    .map((collection, index): PageCollectionTab => ({
      id: `tab-${categoryTabs.length + index + 1}`,
      label: collection.title,
      sourceType: 'collection',
      sourceId: collection.id,
      productLimit: 4,
    }));

  return [...categoryTabs, ...collectionTabs];
};

const isLegacyMockCollectionTabs = (section: PageSection): boolean => (
  section.type === 'collection_tabs'
  && Array.isArray(section.tabs)
  && section.tabs.length === legacySourceIds.size
  && section.tabs.every(tab => legacySourceIds.has(tab.sourceId))
);

/** Repairs only the old four-mock-ID template, preserving every custom mapping. */
export const repairLegacyCollectionTabs = (
  section: PageSection,
  categories: readonly CatalogCategory[],
  collections: readonly CatalogCollection[],
): PageSection => {
  if (!isLegacyMockCollectionTabs(section)) return section;

  const tabs = createCollectionTabMappings(categories, collections);

  return {
    ...section,
    tabs,
    tabCategories: tabs.map(tab => tab.label),
  };
};
