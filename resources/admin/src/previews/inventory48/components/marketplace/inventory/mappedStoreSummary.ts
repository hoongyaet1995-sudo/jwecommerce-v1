import type { MarketplaceInventoryItem } from '../../../types';
import type { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';

export type MappedStoreSummary = {
  store: InventoryStoreChannel;
  availableStock: number | null;
  isCounted: boolean;
  facilityCount: number | null;
  unknownFacilities: number;
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
    const partialCurrent = (balance as any)?.singleCurrentStock === true && balance?.availableStock !== null
      && balance?.availableStock !== undefined;
    return {
      store,
      availableStock: counted || partialCurrent ? balance.availableStock : null,
      isCounted: counted,
      facilityCount: balance?.facilityCount ?? null,
      unknownFacilities: Number((balance as any)?.unknownFacilities || 0),
      needsAttention: store.connectionState !== 'connected' || tasks.some(task => ['failed', 'auth_expired', 'facility_blocked', 'uncertain'].includes(task.status || '')),
    };
  });
}
