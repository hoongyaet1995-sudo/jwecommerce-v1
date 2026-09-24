import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel } from './inventoryTypes';

export type OperationalSyncStatus =
  | 'synced'                 // Emerald: Stock is synchronized
  | 'not_synced'             // Amber: Marketplace stock does not match JW inventory
  | 'sync_failed'            // Red: Provider explicitly rejected request
  | 'awaiting_confirmation'  // Amber/Blue: Update sent. Waiting for marketplace confirmation
  | 'auth_expired'           // Red: This store must be reconnected
  | 'sku_not_mapped'         // Amber: This JW SKU is not linked to a listing in this store
  | 'listing_not_eligible'   // Neutral/Red: This marketplace listing is not currently accepting stock updates
  | 'sync_replaced'          // Amber/Neutral: Replaced by Newer Stock (replaces "superseded")
  | 'jw_internal';           // Internal JW Storefront (no external sync needed)

export interface StoreSyncItemState {
  sku: string;
  storeId: string;
  status: OperationalSyncStatus;
  jwAvailableStock: number;
  marketplaceReportedStock: number | null; // null = 'Unknown' (never displayed as 0)
  difference: number | null; // calculated when marketplaceReportedStock !== null
  lastConfirmedSyncAt: string | null; // formatted string or null -> 'Never'
  lastCheckedAt: string;
  requestSentAt?: string | null;
  requestedQuantity?: number | null;
  failureReason?: string;
  failureRecoveryType?: 'resync' | 'reconnect' | 'mapping' | 'check_status';
  sellerCenterUrl?: string;
  providerWriteSent?: boolean;
}

export interface StockSyncModalData {
  item: MarketplaceInventoryItem;
  store: InventoryStoreChannel;
  syncInfo: StoreSyncItemState;
}
