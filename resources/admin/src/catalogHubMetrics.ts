import type { Category, Collection, Product } from './types';

export const LOW_STOCK_UNITS = 5;

export interface CatalogHubMetrics {
  productCount: number;
  activeSkuCount: number;
  inStockActiveProductCount: number;
  soldOutActiveProductCount: number;
  lowStockActiveProductCount: number;
  categoryCount: number;
  activeCategoryCount: number;
  nestedCategoryCount: number;
  seoConfiguredCategoryCount: number;
  categoryImageCount: number;
  collectionCount: number;
  activeCollectionCount: number;
  dynamicCollectionCount: number;
  manualCollectionCount: number;
  collectionRuleCount: number;
  collectionCoverCount: number;
}

export const catalogHubMetrics = (
  products: Product[] = [],
  categories: Category[] = [],
  collections: Collection[] = [],
): CatalogHubMetrics => {
  const activeProducts = products.filter(product => product.status === 'active');

  return {
    productCount: products.length,
    activeSkuCount: activeProducts.reduce((total, product) => total + product.variants.filter(variant => variant.enabled).length, 0),
    inStockActiveProductCount: activeProducts.filter(product => product.totalInventory > 0).length,
    soldOutActiveProductCount: activeProducts.filter(product => product.totalInventory === 0).length,
    lowStockActiveProductCount: activeProducts.filter(product => product.totalInventory > 0 && product.totalInventory <= LOW_STOCK_UNITS).length,
    categoryCount: categories.length,
    activeCategoryCount: categories.filter(category => category.status === 'active').length,
    nestedCategoryCount: categories.filter(category => category.parentId !== null).length,
    seoConfiguredCategoryCount: categories.filter(category => Boolean(category.seoTitle?.trim() || category.seoDescription?.trim() || category.seoKeywords?.trim())).length,
    categoryImageCount: categories.filter(category => Boolean(category.image?.trim())).length,
    collectionCount: collections.length,
    activeCollectionCount: collections.filter(collection => collection.status === 'active').length,
    dynamicCollectionCount: collections.filter(collection => collection.type === 'dynamic').length,
    manualCollectionCount: collections.filter(collection => collection.type === 'manual').length,
    collectionRuleCount: collections.reduce((total, collection) => total + (collection.dynamicRules?.length ?? 0), 0),
    collectionCoverCount: collections.filter(collection => Boolean(collection.coverImage?.trim())).length,
  };
};
