import { MarketplacePlatform } from '../../../types';

export type WarehouseCodeType = 'location_id' | 'warehouse_code' | 'internal_bin';

export type StoreWarehouseMode = 'no_warehouse' | 'single_warehouse' | 'multi_warehouse';

export type WarehouseSyncState = 
  | 'synced'                // 平台已确认同步
  | 'push_queued'           // JW 本地已生效，平台推送排队中
  | 'push_failed'           // 平台推送失败（本地盘点成功，需重试推送）
  | 'verification_pending'  // 仓库资料待核实（API 超时或权限不足，暂停操作）
  | 'syncing'               // 仓库清单正在读取同步
  | 'sync_unknown'
  | 'unmapped';             // SKU 未映射到该店铺

export interface StorePlatformWarehouse {
  id: string; // e.g. 'wh-shopee-selangor'
  inventoryWarehouseId?: number | null;
  storeId: string;
  storeName: string;
  storeNickname?: string;
  platform: 'webstore' | 'shopee' | 'lazada' | 'tiktok' | 'shopify';
  name: string; // e.g. "Selangor Central Hub"
  warehouseName?: string;
  city?: string;
  state?: string;
  codeType: WarehouseCodeType;
  codeValue: string; // Shopee location_id e.g. "loc-10023812", Lazada warehouse_code e.g. "MY-WH-01"
  region: string;
  isDefault?: boolean;
  addressSummary?: string;
  contactPerson?: string;
  status: 'active' | 'maintenance' | 'verification_pending';
}

export interface SkuWarehouseStockBalance {
  warehouseId: string;
  physicalOwnerId?: string;
  storeId: string;
  warehouseName: string;
  codeType: WarehouseCodeType;
  codeValue: string;
  isCounted: boolean;
  physicalStock: number | null; // null if Not Counted
  reservedStock: number;
  availableStock: number | null; // physicalStock - reservedStock
  lastSyncAt: string;
  syncState: WarehouseSyncState;
  lastPushedQuantity?: number | null;
  lastPushedAt?: string;
  failureReason?: string;
  canRetryPush?: boolean;
}

export interface StoreWithWarehouses {
  storeId: string;
  storeName: string;
  storeNickname: string;
  platform: 'webstore' | 'shopee' | 'lazada' | 'tiktok' | 'shopify';
  warehouseMode: StoreWarehouseMode;
  warehouseSyncStatus: 'synced' | 'syncing' | 'verification_pending';
  syncStatus?: 'synced' | 'syncing' | 'verification_pending';
  verificationPendingReason?: string;
  warehouses: StorePlatformWarehouse[];
  isSkuMapped: boolean; // SKU 是否已映射到此店铺
  unmappedReason?: string;
}

export interface WarehouseLedgerEntry {
  id: string;
  timestamp: string;
  masterSku: string;
  storeId: string;
  storeName: string;
  warehouseId: string;
  warehouseName: string;
  codeValue: string;
  action: 'first_count' | 'intake' | 'audit_adjustment' | 'transfer_in' | 'transfer_out' | 'order_reserved' | 'order_shipped';
  quantityChange: number;
  resultingPhysical: number;
  resultingAvailable: number;
  operator: string;
  reason: string;
  referenceId?: string;
  platformSyncStatus: 'local_only' | 'push_queued' | 'confirmed' | 'failed';
  platformError?: string;
}
