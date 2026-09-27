import type { MarketplaceInventoryItem } from '../../../types';
import type { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';

export type MappedStoreSummary = {
  store: InventoryStoreChannel;
  availableStock: number | null;
  isCounted: boolean;
  facilityCount: number | null;
  needsAttention: boolean;
};

export function mappedStoreSummaries(
  item: MarketplaceInventoryItem,
  stores: InventoryStoreChannel[],
  balances: Record<string, StoreStockAccountBalance> = {},
): MappedStoreSummary[] {
  const mappedIds = new Set((item.mappedStoreIds || []).map(String));
  return stores.filter(store => !store.isComingSoon && (
    store.platform === 'webstore' ? !!balances[store.id] : mappedIds.has(String(store.id))
  )).map(store => {
    const balance = balances[store.id];
    const tasks = balance?.syncTasks || [];
    const counted = balance?.isCounted === true && balance.availableStock !== null && balance.availableStock !== undefined;
    return {
      store,
      availableStock: counted ? balance.availableStock : null,
      isCounted: counted,
      facilityCount: balance?.facilityCount ?? null,
      needsAttention: store.connectionState !== 'connected' || tasks.some(task => ['failed', 'auth_expired', 'facility_blocked', 'uncertain'].includes(task.status || '')),
    };
  });
}
