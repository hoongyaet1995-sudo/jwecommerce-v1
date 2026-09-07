import type {VariantImportReview} from './marketplaceOperations';

interface SourceVariantReview {
  createDraftStatus?: 'draft' | 'active';
  zeroPhysicalStock?: boolean;
  targetProductId?: string;
  skuOverrides?: Record<string, string>;
  linkExistingVariantIds?: Record<string, {productId:string;variantId:string}>;
}

/** Converts a selected source drawer action; never treats bulk link/import as a single creation. */
export function variantImportReview(variantId: string, options: SourceVariantReview = {}): VariantImportReview {
  if (options.zeroPhysicalStock === false || (options.createDraftStatus && options.createDraftStatus !== 'draft')) {
    throw new Error('Import creates drafts with zero warehouse stock only.');
  }
  if (Object.keys(options.linkExistingVariantIds ?? {}).length) {
    throw new Error('Use the existing SKU mapping action to link an existing variant.');
  }
  if (Object.keys(options.skuOverrides ?? {}).some(id => id !== variantId)) {
    throw new Error('Review one selected variant at a time.');
  }
  const sku = options.skuOverrides?.[variantId];
  if (sku !== undefined && (typeof sku !== 'string' || !sku.trim() || [...sku.trim()].length > 255)) {
    throw new Error('Enter a valid SKU of at most 255 characters.');
  }
  const reviewedSku = sku === undefined ? {} : {sku:sku.trim()};
  if (options.targetProductId !== undefined) {
    const id = options.targetProductId;
    if (!/^[1-9]\d*$/.test(id) || !Number.isSafeInteger(Number(id))) {
      throw new Error('Choose an existing JW product.');
    }
    return {productId:Number(id), ...reviewedSku};
  }
  return {newProduct:true, ...reviewedSku};
}
