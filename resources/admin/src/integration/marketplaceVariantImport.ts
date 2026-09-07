import {marketplaceOperations} from './marketplaceOperations';
import {variantImportReview} from './marketplaceVariantImportReview';

export async function importCatalogVariant(enabled: boolean,
  sources: {id:string;variants:{id:string}[]}[], productId:string, variantId:string,
  options: Parameters<typeof variantImportReview>[1] = {}) {
  if (!enabled) throw new Error('Product import is not enabled.');
  const product=sources.find(item=>item.id===productId);
  if (!product?.variants.some(variant=>variant.id===variantId) || !/^[1-9]\d*$/.test(variantId)) {
    throw new Error('Refresh the catalog and select a saved variant belonging to this product.');
  }
  return marketplaceOperations.importVariant(variantId,variantImportReview(variantId,options));
}
