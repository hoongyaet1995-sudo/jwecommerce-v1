import { MarketplacePlatform, StoreInventorySyncState } from '../../../types';

export type InventoryModeType = 'shared_pool' | 'independent_warehouses' | 'shared_stock' | 'independent_stock';

export interface InventoryStoreChannel {
  id: string; // 'webstore' | 'conn-shopee-01' | 'conn-shopee-02' | 'conn-lazada-01' | 'conn-lazada-test-01' | 'tiktok-pending' | 'shopify-pending'
  platform: 'webstore' | 'shopee' | 'lazada' | 'tiktok' | 'shopify';
  platformDisplayName: string;
  name: string;
  nickname: string;
  region: string;
  isComingSoon?: boolean;
  connectionState: 'connected' | 'expired' | 'disconnected' | 'coming_soon';
  isManagedByJw: boolean; // 是否已由 JW 接管库存
  readStatus: 'ok' | 'failed' | 'mismatch' | 'unmanaged';
  syncStatus: 'synced' | 'pending' | 'syncing' | 'failed' | 'partially_failed' | 'disabled' | 'auth_expired';
  lastReadAt?: string;
  lastSyncAt?: string;
  platformStockSnapshot?: number;
  environment?: 'production' | 'sandbox';
  mappedSkuCount?: number;
  totalStoreSkuCount?: number;
}

export interface StoreStockAccountBalance {
  storeId: string;
  storeName: string;
  storeNickname: string;
  platform: 'webstore' | 'shopee' | 'lazada' | 'tiktok' | 'shopify';
  isManagedByJw: boolean;
  connectionState: 'connected' | 'expired' | 'disconnected' | 'coming_soon';
  isCounted: boolean; // false = Not Counted (未盘点)
  physicalStock: number | null; // null if Not Counted
  reservedStock: number;
  availableStock: number | null; // null if Not Counted
  platformStockSnapshot?: number;
  platformReadStatus: 'ok' | 'failed' | 'mismatch' | 'unmanaged';
  lastPlatformReadAt?: string;
  syncState: StoreInventorySyncState;
  lastConfirmedPushedQuantity?: number | null;
  lastConfirmedPushedAt?: string;
  lastSyncStatus?: 'confirmed' | 'queued' | 'processing' | 'failed' | 'uncertain';
  lastFailureReason?: string;
}

export interface StoreStockTransferRecord {
  id: string;
  transferNumber: string;
  timestamp: string;
  masterSku: string;
  productName: string;
  variantName: string;
  imageUrl?: string;
  sourceStoreId: string;
  sourceStoreName: string;
  sourcePlatform: 'webstore' | 'shopee' | 'lazada';
  destinationStoreId: string;
  destinationStoreName: string;
  destinationPlatform: 'webstore' | 'shopee' | 'lazada';
  quantity: number;
  operator: string;
  reason: string;
  notes?: string;
  ledgerStatus: 'succeeded' | 'failed';
  sourcePlatformSyncStatus: 'succeeded' | 'syncing' | 'failed' | 'pending';
  destinationPlatformSyncStatus: 'succeeded' | 'syncing' | 'failed' | 'pending';
  sourceFailureReason?: string;
  destinationFailureReason?: string;
}

export type InventoryActivityActionType = 
  | 'first_count'          // 首次盘点
  | 'actual_intake'        // 实际入库
  | 'stock_adjustment'     // 盘点修正
  | 'order_reserved'       // 订单预留
  | 'order_released'       // 订单取消释放
  | 'order_shipped'        // 发货出库扣减
  | 'return_restocked'     // 退货验收入库
  | 'store_transfer'       // 店铺调配
  | 'platform_sync'        // 平台同步与重试
  | 'mode_change'          // 模式变更
  | 'store_takeover';      // 店铺库存接管

export interface InventoryActivityItem {
  id: string;
  timestamp: string;
  actionType: InventoryActivityActionType;
  masterSku: string;
  productName: string;
  variantName?: string;
  imageUrl?: string;
  targetScope: string; // 'Shared Pool' or Store Name
  storeId?: string;
  platform?: string;
  quantityChange: number;
  previousStock: number | string; // e.g. 15 or 'Not Counted'
  resultingStock: number | string; // e.g. 25
  reservedChange?: number;
  availableChange?: number;
  operator: string;
  reason: string;
  notes?: string;
  referenceId?: string; // Order ID, Transfer ID, Audit ID
  status: 'succeeded' | 'failed' | 'partially_completed' | 'pending';
  detailsPayload?: Record<string, any>;
}

export interface PreSwitchSafetyBlocker {
  sku: string;
  productName: string;
  storeName?: string;
  issueType: 'open_orders' | 'reserved_stock' | 'pending_transfer' | 'sync_queue' | 'uncounted';
  title: string;
  description: string;
  actionLabel: string;
}

export type InventoryModeCatalogClassification =
  | 'ready_to_allocate'
  | 'awaiting_first_count'
  | 'sandbox_test'
  | 'excluded_status'
  | 'store_not_managed'
  | 'reservation_conflict';

export interface InventoryModeCatalogItem {
  variant_id: number;
  product_id: number;
  sku: string;
  product: string;
  variant: string;
  product_status: string;
  data_classification?: string | null;
  classification: InventoryModeCatalogClassification;
  balance_id?: number | null;
  physical_stock?: number | null;
  reserved_stock?: number | null;
  counted: boolean;
  mapped_stores: Array<{ id: number; name: string; platform?: string; management_status?: string }>;
}

export interface InventoryModeCatalog {
  items: InventoryModeCatalogItem[];
  summary: { total: number; ready: number; blocked: number; excluded: number };
}
