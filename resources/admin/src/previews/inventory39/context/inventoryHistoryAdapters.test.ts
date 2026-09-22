import { expect, it } from 'vitest';
import { adaptInventoryHistoryEntry, restoreTransferHistory } from './inventoryHistoryAdapters';

it('restores confirmed transfer stages and store identities after a full page refresh', () => {
  const backend = {
    id: 'operation-1', created_at: '2026-09-22 10:00:00', kind: 'transfer',
    ledger_status: 'applied', actor: 'JW Studio Owner',
    result: {
      activity: {
        master_sku: 'MCC-Black-S', product_name: 'MUSE CASUAL COLLECTION',
        source_store: { id: '2', name: 'Shopee Source', platform: 'shopee' },
        destination_store: { id: '3', name: 'Lazada MY', platform: 'lazada' },
        quantity_change: 5, reason: 'Store Stock Rebalance',
      },
      transfer: {
        master_sku: 'MCC-Black-S', product_name: 'MUSE CASUAL COLLECTION', quantity: 5,
        source_store: { id: '2', name: 'Shopee Source', platform: 'shopee' },
        destination_store: { id: '3', name: 'Lazada MY', platform: 'lazada' },
        reason: 'Store Stock Rebalance',
      },
      external_stages: [
        { id: 'source', stage: 'source', status: 'confirmed' },
        { id: 'destination', stage: 'destination', status: 'confirmed' },
      ],
    },
  };

  const activity = adaptInventoryHistoryEntry(backend);
  const [transfer] = restoreTransferHistory([activity]);

  expect(activity.detailsPayload).toBe(backend.result);
  expect(transfer.sourceStoreName).toBe('Shopee Source');
  expect(transfer.destinationStoreName).toBe('Lazada MY');
  expect(transfer.sourcePlatformSyncStatus).toBe('succeeded');
  expect(transfer.destinationPlatformSyncStatus).toBe('succeeded');
});

it('replaces an older pending history row with the refreshed confirmed row', () => {
  const current: any[] = [{ id: 'operation-1', sourcePlatformSyncStatus: 'pending', destinationPlatformSyncStatus: 'pending' }];
  const refreshed: any[] = [{ id: 'operation-1', sourcePlatformSyncStatus: 'succeeded', destinationPlatformSyncStatus: 'succeeded' }];

  const merged = restoreTransferHistory.merge(current, refreshed);

  expect(merged).toHaveLength(1);
  expect(merged[0].destinationPlatformSyncStatus).toBe('succeeded');
});
