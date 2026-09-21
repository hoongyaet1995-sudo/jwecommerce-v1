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


describe('catalog-driven inventory', () => {
  it('includes uncounted draft and active variants without balances or marketplace mappings', () => {
    const items = adaptInventory({mode:'independent', configuration_version:1, stores:[], totals:{}, data:[], mode_catalog:{items:[
      {variant_id:20,sku:'DRAFT',product:'Draft product',variant:'Default',product_status:'draft',mapped_stores:[]},
      {variant_id:21,sku:'ACTIVE',product:'Active product',variant:'Blue',product_status:'active',mapped_stores:[]},
    ]}});
    expect(items.map(i=>i.masterSku)).toEqual(['DRAFT','ACTIVE']);
    expect(items[0].id).toBe('variant:20');
    expect(items[0].physicalStockStatus).toBe('unknown');
    expect(items[0].totalPhysicalStock).toBeNull();
    expect(items[0].catalogStatus).toBe('draft');
  });
});
