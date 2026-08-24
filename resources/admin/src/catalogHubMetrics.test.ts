import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { catalogHubMetrics } from './catalogHubMetrics';
import type { Category, Collection, Product } from './types';

const product = (overrides: Partial<Product> = {}): Product => ({
  id: 'product-1', title: 'Product', slug: 'product', brand: 'JW', categoryId: 'category-1', collectionIds: [],
  descriptionHtml: '', summary: '', images: [], status: 'active', hasVariants: true, basePrice: 100,
  options: [], variants: [], tags: [], totalInventory: 0, featured: false, createdAt: '2026-08-21', updatedAt: '2026-08-21', ...overrides,
});

const category = (overrides: Partial<Category> = {}): Category => ({
  id: 'category-1', name: 'Category', slug: 'category', parentId: null, icon: 'FolderTree', status: 'active', sortOrder: 1, productCount: 0, createdAt: '2026-08-21', ...overrides,
});

const collection = (overrides: Partial<Collection> = {}): Collection => ({
  id: 'collection-1', title: 'Collection', slug: 'collection', type: 'manual', status: 'active', productCount: 0, createdAt: '2026-08-21', ...overrides,
});

describe('catalogHubMetrics', () => {
  it('uses real aggregate inventory instead of a nonexistent product stock field', () => {
    const metrics = catalogHubMetrics([
      product({totalInventory: 12, variants: [{id: 'sku-1', sku: 'A', title: 'A', options: {}, price: 100, inventoryQuantity: 12, enabled: true}]}),
      product({id: 'product-2', totalInventory: 3, variants: [{id: 'sku-2', sku: 'B', title: 'B', options: {}, price: 100, inventoryQuantity: 3, enabled: true}]}),
      product({id: 'product-3', status: 'draft', totalInventory: 8}),
      product({id: 'product-4', totalInventory: 0}),
    ], [], []);

    expect(metrics).toMatchObject({
      productCount: 4,
      inStockActiveProductCount: 2,
      lowStockActiveProductCount: 1,
      soldOutActiveProductCount: 1,
      activeSkuCount: 2,
    });
  });

  it('derives taxonomy and collection health from catalog records only', () => {
    const metrics = catalogHubMetrics([], [
      category({image: '/storage/cms/media/category.jpg', seoTitle: 'Category SEO'}),
      category({id: 'category-2', parentId: 'category-1', status: 'draft', seoDescription: 'Child SEO'}),
    ], [
      collection({coverImage: '/storage/cms/media/collection.jpg'}),
      collection({id: 'collection-2', type: 'dynamic', status: 'archived', dynamicRules: [
        {id: 'rule-1', field: 'tag', operator: 'contains', value: 'audio'},
        {id: 'rule-2', field: 'inventory', operator: 'greater_than', value: '5'},
      ]}),
    ]);

    expect(metrics).toMatchObject({
      categoryCount: 2,
      activeCategoryCount: 1,
      nestedCategoryCount: 1,
      seoConfiguredCategoryCount: 2,
      categoryImageCount: 1,
      collectionCount: 2,
      activeCollectionCount: 1,
      dynamicCollectionCount: 1,
      manualCollectionCount: 1,
      collectionRuleCount: 2,
      collectionCoverCount: 1,
    });
  });

  it('wires the dedicated product reviews admin route and source entry points', () => {
    const appSource = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8');
    const sidebarSource = readFileSync(new URL('./components/layout/AdminSidebar.tsx', import.meta.url), 'utf8');
    const catalogHubSource = readFileSync(new URL('./components/catalog/CatalogHub.tsx', import.meta.url), 'utf8');

    expect(appSource).toContain("case 'catalog-reviews':");
    expect(sidebarSource).toContain("{ id: 'catalog-reviews' as NavSection, label: 'Product Reviews' }");
    expect(catalogHubSource).toContain('pendingReviewsCount');
  });
});
