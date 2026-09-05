import type {Category, Collection, Product} from '../types';

/** Same eligibility as the published listing; admin totals may include drafts. */
export function listingSources(categories: Category[], collections: Collection[], products: Product[]) {
  const categoryCounts = new Map<string, number>();
  const collectionCounts = new Map<string, number>();
  for (const product of products) {
    if (product.status !== 'active') continue;
    categoryCounts.set(product.categoryId, (categoryCounts.get(product.categoryId) ?? 0) + 1);
    for (const id of product.collectionIds) collectionCounts.set(id, (collectionCounts.get(id) ?? 0) + 1);
  }
  return {
    categories: categories.filter(source => source.status === 'active').map(source => ({...source, productCount:categoryCounts.get(source.id) ?? 0})),
    collections: collections.filter(source => source.status === 'active').map(source => ({...source, productCount:collectionCounts.get(source.id) ?? 0})),
  };
}
