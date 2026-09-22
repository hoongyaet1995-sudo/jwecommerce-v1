export type BulkSyncStatusPair = { sku: string; storeId: string; variantId: number };
export type BulkSyncStatusSkip = BulkSyncStatusPair & { reason: 'authorization_expired' | 'sku_not_mapped' | 'first_count_required' | 'already_confirmed' };

export function buildBulkSyncStatusPlan(_items: any[], _stores: any[], _pendingOnly: boolean): {
  pairs: BulkSyncStatusPair[];
  skipped: BulkSyncStatusSkip[];
} {
  const pairs: BulkSyncStatusPair[] = [];
  const skipped: BulkSyncStatusSkip[] = [];
  const externalStores = _stores.filter(store => store.platform !== 'webstore' && !store.isComingSoon);
  for (const item of _items.slice(0, 25)) {
    const mapped = new Set((item.mappedStoreIds || []).map(String));
    for (const store of externalStores) {
      const pair = { sku: item.masterSku, storeId: String(store.id), variantId: Number(item.variantId) };
      if (!mapped.has(pair.storeId)) {
        skipped.push({ ...pair, reason: 'sku_not_mapped' });
        continue;
      }
      if (store.connectionState === 'expired' || store.connectionState === 'disconnected') {
        skipped.push({ ...pair, reason: 'authorization_expired' });
        continue;
      }
      if (item.hasUncountedAccounts || item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null) {
        skipped.push({ ...pair, reason: 'first_count_required' });
        continue;
      }
      const association = (item.storeAssociations || []).find((entry: any) => String(entry.connectionId) === pair.storeId);
      if (_pendingOnly && association?.lastSyncStatus === 'confirmed') {
        skipped.push({ ...pair, reason: 'already_confirmed' });
        continue;
      }
      pairs.push(pair);
    }
  }

  return { pairs, skipped };
}

export function chunkBulkSyncPairs(_pairs: BulkSyncStatusPair[], _size = 10): BulkSyncStatusPair[][] {
  const chunks: BulkSyncStatusPair[][] = [];
  for (let index = 0; index < _pairs.length; index += _size) chunks.push(_pairs.slice(index, index + _size));
  return chunks;
}

export function summarizeBulkSyncSkips(skipped: BulkSyncStatusSkip[]): string {
  const labels: Array<[BulkSyncStatusSkip['reason'], string]> = [
    ['sku_not_mapped', 'SKU Not Mapped'],
    ['authorization_expired', 'Authorization Expired'],
    ['first_count_required', 'First Count Required'],
    ['already_confirmed', 'Already Confirmed'],
  ];
  return labels.map(([reason, label]) => {
    const count = skipped.filter(item => item.reason === reason).length;
    return count ? `${count} ${label}` : null;
  }).filter(Boolean).join(' · ');
}
