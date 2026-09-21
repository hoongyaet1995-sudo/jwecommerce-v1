import { describe, expect, it } from 'vitest';
import {
  describeMarketplaceSyncState,
  readInventoryAdjustmentTarget,
  resolveInventoryAdjustmentItem,
} from './MarketplaceInventoryPage';

const inventory = [
  { id: 'balance-8', variantId: 41, masterSku: 'SKU-OTHER' },
  { id: 'balance-9', variantId: 52, masterSku: 'SKU-TARGET' },
] as any[];

describe('inventory adjustment deep link', () => {
  it('accepts only the adjustment action with a stable variant id', () => {
    expect(readInventoryAdjustmentTarget('?variant_id=52&action=adjustment')).toEqual({ variantId: '52' });
    expect(readInventoryAdjustmentTarget('?variant_id=52&action=intake')).toBeNull();
    expect(readInventoryAdjustmentTarget('?action=adjustment')).toBeNull();
  });

  it('resolves the exact SKU by variant identity instead of array position or SKU text', () => {
    expect(resolveInventoryAdjustmentItem(inventory, '52')?.masterSku).toBe('SKU-TARGET');
    expect(resolveInventoryAdjustmentItem(inventory, '999')).toBeNull();
  });
});

describe('marketplace sync state labels', () => {
  it('calls stock synced only after provider confirmation', () => {
    expect(describeMarketplaceSyncState('confirmed', 8)).toEqual({ label: 'Confirmed: 8', tone: 'success' });
    expect(describeMarketplaceSyncState('queued', 8).label).toBe('Queued: 8');
    expect(describeMarketplaceSyncState(undefined, 8).label).toBe('Not synced');
  });

  it('distinguishes authorization and uncertain outcomes', () => {
    expect(describeMarketplaceSyncState('auth_expired', 8).label).toBe('Reconnect required');
    expect(describeMarketplaceSyncState('uncertain', 8).label).toBe('Reconciliation required');
  });
});
