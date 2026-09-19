import {marketplaceRequest} from './marketplaceData';

export type MarketplacePlatform = 'shopee' | 'lazada' | 'tiktok' | 'shopify';
export interface PublishingTask {
  mappingComplete?: boolean;
  platform?: 'shopee' | 'lazada';
  id: number; storeId: number; productId: number;
  status: 'queued' | 'processing' | 'succeeded' | 'rejected' | 'uncertain';
  externalItemId: string|null; createdAt: string; updatedAt: string;
}
export type VariantImportReview = ({newProduct: true; productId?: never} | {productId: number; newProduct?: never}) & {sku?: string};
export interface ProductImportResult {
  productId: number|null;
  status: string|null;
  createdVariants?: number;
  linkedVariants?: number;
  keptVariants?: number;
}
export type OAuthResult = {status: 'success' | 'failed' | 'cancelled'; store: {id: string; platform: MarketplacePlatform; shopId: string; shopName: string} | null};
export interface StoreConnection {
  credentialSource?: string | null; testLeaseExpiresAt?: string | null;
  accountMode?: 'production' | 'test' | null;
  capabilities?: {catalogRead:boolean; productPublish:boolean; orderReceive:boolean; inventoryUpdate:boolean; fulfillmentWrite:boolean};
  id: string; platform: MarketplacePlatform; shopName: string; shopId: string | null;
  region: string; status: string; tokenExpiresAt: string | null; lastSyncAt?: string | null;
  autoSyncStock: boolean; autoPullOrders: boolean;
}
export interface ProviderCapabilities {
  oauth: boolean; catalog: boolean; publish: boolean; orders: boolean; inventory: boolean; fulfillment: boolean;
}
export interface Page<T> {data: T[]; total: number; current_page: number; last_page: number; per_page: number}
export interface CatalogSummary {products: number; variants: number; mapped: number; unmapped: number}
export interface MappingContext {
  jw_variant_id: number;
  same_store_conflict: null | {listing_id: number; external_product_id: string; external_variant_id: string;
    product_title: string | null; variant_title: string | null; sku: string | null};
}
export interface CatalogVariant {
  id: number; store_id: number; external_product_id: string; external_variant_id: string;
  platform: MarketplacePlatform; shop_name: string; jw_variant_id: number | null; jw_sku: string | null;
  jw_product_id: number | null; jw_product_title: string | null; jw_variant_title: string | null; mapped_at: string | null;
  jw_price_minor?: number | null; jw_legacy_stock?: number | null;
  fetched_at: string; observed_data: {productTitle?: string | null; productImage?: string | null; productImages?: string[] | null; variantImage?: string | null; title?: string | null; sku?: string | null; price?: number | string | null; stock?: number | null; currency?: string | null; status?: string | null};
}
export interface CatalogProduct {
  store_id: number; external_product_id: string; platform: MarketplacePlatform; shop_name: string;
  first_listing_id: number; variants: Omit<CatalogVariant, 'platform' | 'shop_name'>[];
}
export interface OrderSummary {
  id: number; store_id: number; external_order_id: string; platform_status: string;
  data_classification?: string;
  platform: MarketplacePlatform; shop_name: string; currency: string | null; total_amount: string | null;
  platform_updated_at: number; fetched_at: string;
}
export interface MarketplaceOrderLine {
  external_product_id: string | null; external_variant_id: string | null;
  title: string | null; variant_title: string | null; external_sku: string | null;
  quantity: number | null; unit_price: number | string | null;
  mapping_status: 'mapped' | 'unmapped'; jw_variant_id: number | null; jw_sku: string | null;
}
export interface MarketplaceOrderDetail extends OrderSummary {
  label_packages?: string[];
  rts_packages?: string[];
  pack_item_ids?: string[];
  fulfillment_attempts?: {id: number; operation: string; status: string; created_at: string; updated_at: string}[];
  lines: MarketplaceOrderLine[];
  shipping_carrier: string | null;
  packages: {package_number: string | null; logistics_status: string | null; shipping_carrier: string | null}[];
  capabilities: {fulfillment: boolean; airway_bill: boolean};
  recipient?: Record<'name' | 'phone' | 'town' | 'district' | 'city' | 'state' | 'region' | 'zipcode' | 'full_address', string | null>;
}
export interface SyncRun {id: number; store_id: number; status: 'queued' | 'running' | 'succeeded' | 'failed'; pages_completed: number; error_code: string | null; finished_at: string | null}
type Filters = {storeId?: string; platform?: MarketplacePlatform; q?: string; status?: string; page?: number; per_page?: number};
function query(filters: Filters): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {if (value !== undefined && value !== '') params.set(key, String(value));});
  return params.toString();
}
const idPath = (id: string) => encodeURIComponent(id);
export function officialShopeeAuthorizationUrl(value: string): string {
  const url = new URL(value);
  if (url.protocol !== 'https:' || url.username || url.password || url.port || url.hash
    || !['partner.shopeemobile.com', 'openplatform.sandbox.test-stable.shopee.sg'].includes(url.hostname)
    || url.pathname !== '/api/v2/shop/auth_partner') throw new Error('Unexpected authorization destination.');
  return url.href;
}
export function officialLazadaAuthorizationUrl(value: string): string {
  const url = new URL(value);
  if (url.protocol !== 'https:' || url.username || url.password || url.port || url.hash
    || url.hostname !== 'auth.lazada.com' || url.pathname !== '/oauth/authorize') {
    throw new Error('Unexpected authorization destination.');
  }
  return url.href;
}
export async function requestMarketplaceAuthorization(platform: MarketplacePlatform, reconnectId?: string): Promise<void> {
  if (platform !== 'shopee' && platform !== 'lazada') throw new Error('This platform is not available yet.');
  const {url} = await marketplaceOperations.authorize(reconnectId, platform);
  window.location.assign(platform === 'lazada' ? officialLazadaAuthorizationUrl(url) : officialShopeeAuthorizationUrl(url));
}
export const marketplaceOperations = {
  attributeValues: (storeId:string,attributeId:number,keyword='',cursor=0) => {
    const params=new URLSearchParams({attributeId:String(attributeId),q:keyword,cursor:String(cursor)});
    return marketplaceRequest<{storeId:number;attributeId:number;data:{
      value_list:{value_id:number;value_name:string}[];page_info:{cursor:number;has_next:boolean}
    }}>(`/stores/${idPath(storeId)}/listing-attribute-values?${params}`);
  },
  recoverPublishingTask: async (storeId: string, requestKey: string): Promise<PublishingTask> => {
    if (!/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(requestKey)) {
      throw new Error('Invalid publishing request identity.');
    }
    const receipt=await marketplaceRequest<PublishingTask>(`/stores/${idPath(storeId)}/publishing-request/${requestKey}`);
    if (!receipt || !Number.isSafeInteger(receipt.id) || receipt.id<=0 || String(receipt.storeId)!==storeId) {
      throw new Error('Invalid publishing recovery destination.');
    }
    const task=await marketplaceOperations.publishingTask(String(receipt.id));
    if (String(task.storeId)!==storeId) throw new Error('Invalid publishing recovery destination.');
    return task;
  },
  publishingTask: async (id: string): Promise<PublishingTask> => {
    const task = await marketplaceRequest<PublishingTask>(`/publishing-tasks/${idPath(id)}`);
    if (!task || !Number.isSafeInteger(task.id) || task.id <= 0 || String(task.id) !== id
      || !Number.isSafeInteger(task.storeId) || task.storeId <= 0
      || !Number.isSafeInteger(task.productId) || task.productId <= 0
      || !['queued','processing','succeeded','rejected','uncertain'].includes(task.status)
      || (task.externalItemId !== null && (typeof task.externalItemId !== 'string' || !task.externalItemId.trim()))
      || (task.status === 'succeeded' && !task.externalItemId)
      || typeof task.createdAt !== 'string' || typeof task.updatedAt !== 'string') {
      throw new Error('Invalid publishing task response. Refresh to verify the saved result.');
    }
    return task;
  },
  reconcileLazadaPublishingTask: (id: string) => marketplaceRequest<{id:number;status:'succeeded'|'rejected';confirmed:boolean;external_item_id?:string}>(`/publishing-tasks/${idPath(id)}/reconcile`, 'POST'),
  importVariant: (listingId: string, review: VariantImportReview) =>
    marketplaceRequest<{variantId: number; productId: number}>(`/listings/${idPath(listingId)}/import-variant`, 'POST', review),
  orderSummary: (filters: Filters = {}) => marketplaceRequest<{total:number;statuses:Record<string,number>}>(`/orders/summary?${query(filters)}`),
  mappingContext: (listingId: string, variantIds: number[]) => {
    const params = new URLSearchParams();
    variantIds.forEach(id => params.append('variantIds[]', String(id)));
    return marketplaceRequest<{data: MappingContext[]}>(`/listings/${idPath(listingId)}/mapping-context?${params}`);
  },
  catalogSummary: (filters: Filters = {}) => marketplaceRequest<CatalogSummary>(`/catalog/summary?${query(filters)}`),
  oauthResult: () => marketplaceRequest<{result: OAuthResult | null}>('/oauth-result'),
  catalogProducts: (filters: Filters = {}) => marketplaceRequest<Page<CatalogProduct>>(`/catalog/products?${query(filters)}`),
  importProduct: (listingId: string, review?: {skuOverrides: Record<string,string>; existingLinks?:Record<string,{productId:number;variantId:number}>;productId?:number;listingIds?:number[]}) => review === undefined
    ? marketplaceRequest<ProductImportResult>(`/listings/${idPath(listingId)}/import-product`, 'POST')
    : marketplaceRequest<ProductImportResult>(`/listings/${idPath(listingId)}/import-product`, 'POST', review),
  retryOrders: (id: string) => marketplaceRequest<{runId: number}>(`/order-runs/${idPath(id)}/retry`, 'POST'),
  connections: () => marketplaceRequest<{stores: StoreConnection[]; capabilities: Record<MarketplacePlatform, ProviderCapabilities>; localCapabilities?:{importProducts:boolean}}>('/connections'),
  authorize: (reconnectId?: string, platform: 'shopee' | 'lazada' = 'shopee') => marketplaceRequest<{url: string}>(`/${platform}/authorize`, 'POST', reconnectId ? {reconnectStoreId: Number(reconnectId)} : {}),
  disconnect: (id: string) => marketplaceRequest<{status: string; providerRevocationConfirmed: boolean}>(`/stores/${idPath(id)}/disconnect`, 'POST'),
  catalog: (filters: Filters = {}) => marketplaceRequest<Page<CatalogVariant>>(`/catalog?${query(filters)}`),
  fetchCatalog: (id: string) => marketplaceRequest<{runId: number}>(`/stores/${idPath(id)}/catalog-fetch`, 'POST'),
  catalogRun: (id: string) => marketplaceRequest<SyncRun>(`/catalog-runs/${idPath(id)}`),
  retryCatalog: (id: string) => marketplaceRequest<{runId: number}>(`/catalog-runs/${idPath(id)}/retry`, 'POST'),
  orders: (filters: Filters = {}) => marketplaceRequest<Page<OrderSummary>>(`/orders?${query(filters)}`),
  orderDetail: (id: string, options: {includeRecipient?: boolean} = {}) => marketplaceRequest<{data: MarketplaceOrderDetail}>(`/orders/${idPath(id)}${options.includeRecipient ? '?includeRecipient=1' : ''}`),
  fetchOrders: (id: string, from: number, to: number) => marketplaceRequest<{runId: number}>(`/stores/${idPath(id)}/orders-fetch`, 'POST', {from, to}),
  orderRun: (id: string) => marketplaceRequest<SyncRun>(`/order-runs/${idPath(id)}`),
};
