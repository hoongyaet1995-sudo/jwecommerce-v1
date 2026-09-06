import {marketplaceRequest} from './marketplaceData';

export type MarketplacePlatform = 'shopee' | 'lazada' | 'tiktok' | 'shopify';
export type OAuthResult = {status: 'success' | 'failed' | 'cancelled'; store: {id: string; platform: MarketplacePlatform; shopId: string; shopName: string} | null};
export interface StoreConnection {
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
  fetched_at: string; observed_data: {productTitle?: string | null; productImage?: string | null; title?: string | null; sku?: string | null; price?: number | string | null; stock?: number | null; currency?: string | null; status?: string | null};
}
export interface CatalogProduct {
  store_id: number; external_product_id: string; platform: MarketplacePlatform; shop_name: string;
  first_listing_id: number; variants: Omit<CatalogVariant, 'platform' | 'shop_name'>[];
}
export interface OrderSummary {
  id: number; store_id: number; external_order_id: string; platform_status: string;
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
export async function requestMarketplaceAuthorization(platform: MarketplacePlatform, reconnectId?: string): Promise<void> {
  if (platform !== 'shopee') throw new Error('This platform is not available yet.');
  const {url} = await marketplaceOperations.authorize(reconnectId);
  window.location.assign(officialShopeeAuthorizationUrl(url));
}
export const marketplaceOperations = {
  orderSummary: (filters: Filters = {}) => marketplaceRequest<{total:number;statuses:Record<string,number>}>(`/orders/summary?${query(filters)}`),
  mappingContext: (listingId: string, variantIds: number[]) => {
    const params = new URLSearchParams();
    variantIds.forEach(id => params.append('variantIds[]', String(id)));
    return marketplaceRequest<{data: MappingContext[]}>(`/listings/${idPath(listingId)}/mapping-context?${params}`);
  },
  catalogSummary: (filters: Filters = {}) => marketplaceRequest<CatalogSummary>(`/catalog/summary?${query(filters)}`),
  oauthResult: () => marketplaceRequest<{result: OAuthResult | null}>('/oauth-result'),
  catalogProducts: (filters: Filters = {}) => marketplaceRequest<Page<CatalogProduct>>(`/catalog/products?${query(filters)}`),
  importProduct: (listingId: string) => marketplaceRequest<{productId: number; status: string}>(`/listings/${idPath(listingId)}/import-product`, 'POST'),
  retryOrders: (id: string) => marketplaceRequest<{runId: number}>(`/order-runs/${idPath(id)}/retry`, 'POST'),
  connections: () => marketplaceRequest<{stores: StoreConnection[]; capabilities: Record<MarketplacePlatform, ProviderCapabilities>}>('/connections'),
  authorize: (reconnectId?: string) => marketplaceRequest<{url: string}>('/shopee/authorize', 'POST', reconnectId ? {reconnectStoreId: Number(reconnectId)} : {}),
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
