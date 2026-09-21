import { describe, expect, it } from 'vitest';
import { adaptInventory } from './storeInventory';

describe('store inventory adapter', () => {
  it('does not reopen first count because an unmapped store account is uncounted', () => {
    const overview: any = {
      mode: 'independent', configuration_version: 1, stores: [], totals: {},
      mode_catalog: {items: [{variant_id: 10, mapped_stores: []}]},
      data: [
        {balance_id: 1, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: null, account_key: 'website', platform: 'website', physical: 22, reserved: 0, available: 22, counted: true, allocation_eligible: true},
        {balance_id: 2, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: 9, store_name: 'Unmapped store', account_key: 'marketplace:9', platform: 'lazada', physical: null, reserved: null, available: null, counted: false, allocation_eligible: false},
      ],
    };

    const [item] = adaptInventory(overview);
    expect(item.hasUncountedAccounts).toBe(false);
    expect(item.physicalStockStatus).toBe('verified');
    expect(item.totalPhysicalStock).toBe(22);
    expect(item.availableStock).toBe(22);
  });
});
