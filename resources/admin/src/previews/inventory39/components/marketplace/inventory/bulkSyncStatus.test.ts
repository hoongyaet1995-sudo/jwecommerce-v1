import { describe, expect, it } from 'vitest';
import { buildBulkSyncStatusPlan, chunkBulkSyncPairs, summarizeBulkSyncSkips } from './bulkSyncStatus';

const stores: any[] = [
  { id: 'website', platform: 'webstore', connectionState: 'connected' },
  { id: '1', platform: 'shopee', connectionState: 'connected' },
  { id: '4', platform: 'lazada', connectionState: 'expired' },
];

const items: any[] = [
  { masterSku: 'SKU-A', variantId: 10, mappedStoreIds: ['1', '4'], storeAssociations: [
    { connectionId: '1', lastSyncStatus: 'uncertain' },
    { connectionId: '4', lastSyncStatus: 'confirmed' },
  ] },
  { masterSku: 'SKU-B', variantId: 11, mappedStoreIds: [], storeAssociations: [] },
  { masterSku: 'SKU-C', variantId: 12, mappedStoreIds: ['1'], storeAssociations: [
    { connectionId: '1', lastSyncStatus: 'confirmed' },
  ] },
  { masterSku: 'SKU-D', variantId: 13, mappedStoreIds: ['1'], hasUncountedAccounts: true, storeAssociations: [
    { connectionId: '1', lastSyncStatus: 'queued' },
  ] },
];

describe('bulk stock status planning', () => {
  it('builds mapped external readbacks and records truthful skip reasons', () => {
    const plan = buildBulkSyncStatusPlan(items, stores, false);
    expect(plan.pairs).toEqual([
      { sku: 'SKU-A', storeId: '1', variantId: 10 },
      { sku: 'SKU-C', storeId: '1', variantId: 12 },
    ]);
    expect(plan.skipped).toEqual(expect.arrayContaining([
      expect.objectContaining({ sku: 'SKU-A', storeId: '4', reason: 'authorization_expired' }),
      expect.objectContaining({ sku: 'SKU-B', storeId: '1', reason: 'sku_not_mapped' }),
      expect.objectContaining({ sku: 'SKU-D', storeId: '1', reason: 'first_count_required' }),
    ]));
  });

  it('refresh pending excludes already confirmed mappings and chunks provider reads by ten', () => {
    const plan = buildBulkSyncStatusPlan(items, stores, true);
    expect(plan.pairs).toEqual([{ sku: 'SKU-A', storeId: '1', variantId: 10 }]);
    expect(chunkBulkSyncPairs(Array.from({ length: 21 }, (_, index) => ({ sku: `S-${index}`, storeId: '1', variantId: index + 1 })))).toHaveLength(3);
  });

  it('summarizes why store mappings were skipped for ordinary staff', () => {
    expect(summarizeBulkSyncSkips([
      { sku: 'A', storeId: '1', variantId: 1, reason: 'sku_not_mapped' },
      { sku: 'B', storeId: '1', variantId: 2, reason: 'sku_not_mapped' },
      { sku: 'C', storeId: '4', variantId: 3, reason: 'authorization_expired' },
    ])).toBe('2 SKU Not Mapped · 1 Authorization Expired');
  });
});
