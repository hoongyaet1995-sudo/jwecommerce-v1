import { describe, expect, it } from 'vitest';
import { adaptInventory, adaptStores } from './storeInventory';

const overview: any = {
  mode: 'independent',
  configuration_version: 1,
  totals: {},
  stores: [{ id: 11, name: 'Shopee MY', platform: 'shopee', connection_status: 'connected', sync_state: 'active' }],
  mode_catalog: {
    items: [{
      variant_id: 7,
      sku: 'SKU-7',
      product: 'Product 7',
      variant: 'Default',
      mapped_stores: [{ id: 11 }],
    }],
  },
  data: [{
    balance_id: 70,
    variant_id: 7,
    sku: 'SKU-7',
    product: 'Product 7',
    variant: 'Default',
    account_key: 'marketplace:11',
    store_id: 11,
    store_name: 'Shopee MY',
    platform: 'shopee',
    physical: 8,
    reserved: 0,
    available: 8,
    counted: true,
    allocation_eligible: true,
    connection_status: 'connected',
    sync_readiness: { ready: false, blockers: [{ code: 'authorization_expired' }] },
    external_sync: { status: 'auth_expired', tasks: [{ id: 'task-1', status: 'auth_expired' }] },
  }],
};

describe('store inventory adapters', () => {
  it('does not reopen first count because an unmapped store account is uncounted', () => {
    const [item] = adaptInventory({
      mode: 'independent', configuration_version: 1, stores: [], totals: {},
      mode_catalog: { items: [{ variant_id: 10, mapped_stores: [] }] },
      data: [
        { balance_id: 1, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: null, account_key: 'website', platform: 'website', physical: 22, reserved: 0, available: 22, counted: true, allocation_eligible: true },
        { balance_id: 2, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: 9, store_name: 'Unmapped store', account_key: 'marketplace:9', platform: 'lazada', physical: null, reserved: null, available: null, counted: false, allocation_eligible: false },
      ],
    } as any);

    expect(item.hasUncountedAccounts).toBe(false);
    expect(item.physicalStockStatus).toBe('verified');
    expect(item.totalPhysicalStock).toBe(22);
    expect(item.availableStock).toBe(22);
  });

  it('shows total company physical stock across independent ledgers while ATP excludes unmapped stores', () => {
    const [item] = adaptInventory({
      mode: 'independent', configuration_version: 1, stores: [], totals: {},
      mode_catalog: { items: [{ variant_id: 10, mapped_stores: [] }] },
      data: [
        { balance_id: 1, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: null, account_key: 'shared', platform: 'website', physical: 32, reserved: 0, available: 32, counted: true, allocation_eligible: false },
        { balance_id: 2, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: null, account_key: 'website', platform: 'website', physical: 50, reserved: 0, available: 50, counted: true, allocation_eligible: true },
        { balance_id: 3, variant_id: 10, sku: 'SKU-10', product: 'Product', variant: 'Default', store_id: 9, store_name: 'Unmapped store', account_key: 'marketplace:9', platform: 'lazada', physical: 6, reserved: 0, available: 6, counted: true, allocation_eligible: false },
      ],
    } as any);

    expect(item.totalPhysicalStock).toBe(56);
    expect(item.reservedStock).toBe(0);
    expect(item.availableStock).toBe(50);
  });

  it('includes uncounted draft and active variants without balances or marketplace mappings', () => {
    const items = adaptInventory({ mode: 'independent', configuration_version: 1, stores: [], totals: {}, data: [], mode_catalog: { items: [
      { variant_id: 20, sku: 'DRAFT', product: 'Draft product', variant: 'Default', product_status: 'draft', mapped_stores: [] },
      { variant_id: 21, sku: 'ACTIVE', product: 'Active product', variant: 'Blue', product_status: 'active', thumbnail_url: '/admin/inventory/thumbnails/21?v=abc', mapped_stores: [] },
    ] } } as any);

    expect(items.map(item => item.masterSku)).toEqual(['DRAFT', 'ACTIVE']);
    expect(items[0].id).toBe('variant:20');
    expect(items[0].physicalStockStatus).toBe('unknown');
    expect(items[1].imageUrl).toBe('/admin/inventory/thumbnails/21?v=abc');
    expect(items[0].totalPhysicalStock).toBeNull();
    expect(items[0].catalogStatus).toBe('draft');
  });

  it('preserves per-SKU readiness and persisted provider status', () => {
    const [item] = adaptInventory(overview);

    expect(item.storeAssociations[0].syncReadiness.ready).toBe(false);
    expect(item.storeAssociations[0].lastSyncStatus).toBe('auth_expired');
  });

  it('does not infer confirmed sync from an active store configuration', () => {
    const stores = adaptStores(overview);

    expect(stores.find(store => store.id === '11')?.syncStatus).toBe('pending');
  });

  it('exposes a partially ready store when only some mapped SKUs are eligible', () => {
    const stores = adaptStores({ ...overview, mode_catalog: { items: [
      { variant_id: 7, mapped_stores: [{ id: 11, sync_readiness: { ready: true, blockers: [] } }] },
      { variant_id: 8, mapped_stores: [{ id: 11, sync_readiness: { ready: false, blockers: [{ code: 'reservation_conflict' }] } }] },
    ] } } as any);

    expect(stores.find(store => store.id === '11')?.inventorySyncState).toBe('partially_ready');
  });
});
