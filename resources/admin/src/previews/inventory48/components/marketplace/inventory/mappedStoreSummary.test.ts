import { describe, expect, it } from 'vitest';
import { mappedStoreSummaries } from './mappedStoreSummary';

const stores: any[] = [
  { id: 'website', platform: 'webstore', name: 'JW Storefront' },
  { id: '11', platform: 'lazada', name: 'Mapped Lazada' },
  { id: '12', platform: 'shopee', name: 'Unmapped Shopee' },
];

describe('mappedStoreSummaries', () => {
  it('shows only mapped marketplace stores and a JW Storefront with its own ledger', () => {
    const result = mappedStoreSummaries({ mappedStoreIds: ['11'] } as any, stores as any, {
      website: { availableStock: 120, isCounted: true },
      '11': { availableStock: 20, isCounted: true },
      '12': { availableStock: 8, isCounted: true },
    } as any);
    expect(result.map(row => [row.store.id, row.availableStock])).toEqual([['website', 120], ['11', 20]]);
  });

  it('does not display uncounted stock as zero and flags a failed sync', () => {
    const result = mappedStoreSummaries({ mappedStoreIds: ['11'] } as any, stores as any, {
      '11': { availableStock: null, isCounted: false, syncTasks: [{ status: 'failed' }] },
    } as any);
    expect(result).toMatchObject([{ availableStock: null, needsAttention: true }]);
  });
});
