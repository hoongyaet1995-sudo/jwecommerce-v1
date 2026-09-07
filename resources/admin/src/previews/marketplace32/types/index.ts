export type {Product, ProductVariant, Category, MarketplacePlatform, MarketplaceConnection} from '../../marketplace30/types';
export type {MarketplaceStoreProduct, MarketplaceStoreVariant} from './catalog';

export interface ImportProductOptions {
  createDraftStatus?: 'draft' | 'active';
  zeroPhysicalStock?: boolean;
  skuOverrides?: Record<string, string>;
  linkExistingVariantIds?: Record<string, {productId: string; variantId: string}>;
  targetProductId?: string;
}
export interface PublishListingOptions {
  priceMultiplier?: number;
  categoryName?: string;
  marketplaceCategoryId?: string;
  brand?: string;
  warrantyType?: string;
  warrantyPeriod?: string;
  packageWeightKg?: number;
  packageLengthCm?: number;
  packageWidthCm?: number;
  packageHeightCm?: number;
  condition?: 'NEW' | 'REFURBISHED';
  initialStockMode?: 'warehouse_stock' | 'zero_stock';
  enableOngoingSync?: boolean;
}
export type PublishAsyncStatus = 'idle' | 'queued' | 'processing' | 'success' | 'rejected' | 'uncertain';
