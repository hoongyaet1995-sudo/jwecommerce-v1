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
  sourceRevision?: string;
  shopeeVariantImageChoices?: Record<string,number>;
  lazadaAttributes?: Record<string,string>;
  lazadaSkuAttributes?: Record<string,Record<string,string>>;
  shopeeAttributes?: unknown[];
  shopeeLogistics?: {logistic_id:number;is_free?:boolean;size_id?:string|number;shipping_fee?:number}[];
  shopeeBrandId?: number|null;
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
  condition?: 'NEW' | 'REFURBISHED' | 'USED';
  initialStockMode?: 'warehouse_stock' | 'zero_stock';
  enableOngoingSync?: boolean;
}
export type PublishAsyncStatus = 'idle' | 'queued' | 'processing' | 'success' | 'rejected' | 'uncertain';
