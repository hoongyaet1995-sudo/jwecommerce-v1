// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { FocusedManageStockDrawer } from './FocusedManageStockDrawer';

let host: HTMLDivElement;
let root: Root;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.restoreAllMocks(); });

const item = { id: '55', masterSku: 'BKJS-Pink', productName: 'Baju Kurung Joshua', variantName: 'Pink',
  imageUrl: '/products/pink.jpg', variantId: 55, mappedStoreIds: ['3'] } as any;
const stores = [{ id: 'web', name: 'JW Storefront', platform: 'webstore' },
  { id: '3', name: 'Lazada Test', platform: 'lazada', environment: 'sandbox' },
  { id: '4', name: 'Unmapped Shopee', platform: 'shopee' }];
const click = async (label: string) => {
  const button = [...host.querySelectorAll('button')].find(node => node.textContent?.includes(label));
  expect(button, label).toBeDefined();
  await act(async () => button!.click());
};

it('shows only Current Stock in every Manage Stock summary when the single ledger is active', async () => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('/sync') ? { status: 'not_synced', can_check: false } :
      { status: 'verified', topology: 'no_warehouse', facilities: [] } }) as Response));
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}}
    item={{ ...item, singleCurrentStock: true }} mode="independent"
    stores={[{ id: '3', name: 'Shopee Test', platform: 'shopee' }]}
    storeBalances={{ 3: { currentStock: 12, physicalStock: 12, reservedStock: 0,
      availableStock: 12, isCounted: true } }} />));
  await click('Shopee Test');
  await click('Default Inventory');
  expect(host.textContent).toContain('Current Stock12');
  expect(host.textContent).not.toMatch(/Physical stock|Reserved for orders|Available to sell/);
});

it('opens Shopee default inventory as a selectable location and preserves store ledger action targets', async () => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('/sync') ? { status: 'synced', can_check: true }
      : { status: 'verified', topology: 'no_warehouse', facilities: [], error_code: 'default_inventory_fallback' } }) as Response));
  const intake = vi.fn(); const audit = vi.fn(); const transfer = vi.fn();
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={[{ id: '3', name: 'Shopee Test', platform: 'shopee' }]}
    storeBalances={{ 3: { physicalStock: 20, reservedStock: 2, availableStock: 18, isCounted: true } }}
    onSelectIntake={intake} onSelectAdjustment={audit} onSelectTransfer={transfer} />));
  await click('Shopee Test');
  expect(host.textContent).not.toContain('Warehouse verification');
  await click('Default Inventory');
  expect(host.textContent).toContain('Physical stock20');
  await click('Manage this stock');
  await click('Actual Stock Intake');
  await click('Stock Audit Adjustment');
  await click('Transfer Stock');
  expect(intake).toHaveBeenCalledWith(item, '3', 'direct:3');
  expect(audit).toHaveBeenCalledWith(item, '3', 'direct:3');
  expect(transfer).toHaveBeenCalledWith(item, '3', 'direct:3');
});

it('shows mapped stores only, then opens an exact warehouse First Count with real product and platform identity', async () => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('test-facility-acceptance') ? { test_acceptance_enabled: false } :
      String(input).includes('/sync') ? { status: 'not_synced', can_check: false, can_resync: false } :
        { status: 'verified', topology: 'multi_warehouse', facilities: [
          { id: 7, name: 'Warehouse A', provider_id: 'WH-A', inventory_warehouse_id: 19,
            counted: false, physical: null, reserved: null, available: null },
          { id: 8, name: 'Warehouse B', provider_id: 'WH-B', inventory_warehouse_id: 20,
            counted: true, physical: 5, reserved: 0, available: 5 },
        ] } }) as Response));
  const firstCount = vi.fn();
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={stores} onSelectFirstCount={firstCount} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  expect(host.textContent).toContain('Lazada Test');
  expect(host.textContent).not.toContain('Unmapped Shopee');
  expect(host.querySelector('img[alt="Baju Kurung Joshua"]')?.getAttribute('src')).toBe('/products/pink.jpg');
  expect(host.querySelector('[aria-label="Lazada Logo"]')).not.toBeNull();
  await click('Lazada Test');
  expect(host.textContent).toContain('Warehouse A');
  expect(host.textContent).not.toContain('Physical stock0');
  await click('Warehouse A');
  await click('Manage this stock');
  await click('First Count this location');
  expect(firstCount).toHaveBeenCalledWith(item, '3', '7');
});

it('opens the preselected warehouse action page from marketplace sync without visiting Intake or Audit', async () => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('/sync') ? { status: 'not_synced', can_check: false, can_resync: false }
      : { status: 'verified', topology: 'multi_warehouse', facilities: [
        { id: 7, name: 'Warehouse A', provider_id: 'WH-A', inventory_warehouse_id: 19,
          counted: false, physical: null, reserved: null, available: null },
        { id: 8, name: 'Warehouse B', provider_id: 'WH-B', inventory_warehouse_id: 20,
          counted: true, physical: 5, reserved: 0, available: 5 },
      ] } }) as Response));
  const firstCount = vi.fn();
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={[{ ...stores[1], environment: 'production' }]}
    initialStoreId="3" initialWarehouseId="7" initialActionsOpen onSelectFirstCount={firstCount} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  expect(host.textContent).toContain('Manage this stock');
  expect(host.textContent).toContain('First Count this location');
  expect(host.textContent).not.toContain('Actual Stock Intake');
  await click('First Count this location');
  expect(firstCount).toHaveBeenCalledWith(item, '3', '7');
});

it('skips warehouses for a verified no-warehouse store and keeps existing Intake and Audit callbacks', async () => {
  const intake = vi.fn(); const audit = vi.fn();
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={stores.slice(0, 1)}
    storeBalances={{ web: { physicalStock: 10, reservedStock: 2, availableStock: 8, isCounted: true } }}
    onSelectIntake={intake} onSelectAdjustment={audit} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  await click('JW Storefront');
  expect(host.textContent).toContain('Store ledger');
  expect(host.textContent).not.toContain('Platform warehouses');
  await click('Manage this stock');
  await click('Actual Stock Intake');
  await click('Stock Audit Adjustment');
  expect(intake).toHaveBeenCalledWith(item, 'web', 'direct:web');
  expect(audit).toHaveBeenCalledWith(item, 'web', 'direct:web');
});

it('keeps Manage this stock visible and opens actions when a test facility has an eligibility warning', async () => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('test-facility-acceptance')
      ? { test_acceptance_enabled: true, eligible: false, facilities: [{ id: 7, eligible: false }] }
      : String(input).includes('/sync') ? { status: 'not_synced', can_check: false, can_resync: false }
        : { status: 'verified', topology: 'single_warehouse', facilities: [
          { id: 7, name: 'Warehouse A', provider_id: 'WH-A', inventory_warehouse_id: 19,
            counted: false, physical: null, reserved: null, available: null },
        ] } }) as Response));
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={[stores[1]]} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  await click('Lazada Test');
  await click('Warehouse A');
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  const manage = [...host.querySelectorAll('button')].find(button => button.textContent?.trim() === 'Manage this stock');
  expect(manage?.disabled).toBe(false);
  await click('Manage this stock');
  expect(host.textContent).toContain('First Count this location');
});

it('keeps Manage this stock available while the marketplace result needs confirmation', async () => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('/sync') ? { status: 'awaiting_confirmation', can_check: true, can_resync: true }
      : { status: 'verified', topology: 'no_warehouse', facilities: [] } }) as Response));
  const intake = vi.fn();
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={[{ id: '3', name: 'Shopee Test', platform: 'shopee' }]}
    storeBalances={{ 3: { physicalStock: 30, reservedStock: 0, availableStock: 30, isCounted: true } }}
    onSelectIntake={intake} />));
  await click('Shopee Test');
  await click('Default Inventory');
  expect(host.textContent).toContain('Marketplace result needs confirmation');
  const manage = [...host.querySelectorAll('button')].find(button => button.textContent?.trim() === 'Manage this stock');
  expect(manage?.disabled).toBe(false);
  await click('Manage this stock');
  await click('Actual Stock Intake');
  expect(intake).toHaveBeenCalledWith(item, '3', 'direct:3');
});

it('opens the exact-location Stock Take recovery instead of silently bypassing an order blocker', async () => {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('stock-take-recovery') ? {
      eligible: false, blockers: [{ code: 'open_test_store_order' }],
      orders: [{ id: 82, reference: 'ORDER-82', status: 'packed', line_key: 'LINE-82', historical: false, can_review_historical: true }],
    } : String(input).includes('test-facility-acceptance') ? {
      test_acceptance_enabled: true, eligible: false, blockers: [{ code: 'open_test_store_order' }],
      facilities: [{ id: 7, eligible: true }],
    } : String(input).includes('/sync') ? { status: 'not_synced', can_check: false, can_resync: false }
      : { status: 'verified', topology: 'single_warehouse', facilities: [
        { id: 7, name: 'Warehouse A', provider_id: 'WH-A', inventory_warehouse_id: 19,
          counted: false, physical: null, reserved: null, available: null },
      ] } }) as Response);
  vi.stubGlobal('fetch', fetchMock);
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={[stores[1]]} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  await click('Lazada Test'); await click('Warehouse A');
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  await click('Resolve stock issue');
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  expect(host.textContent).toContain('Open orders need review');
  expect(fetchMock.mock.calls.some(([input]) => String(input).includes('/stores/3/facilities/7/skus/55/stock-take-recovery'))).toBe(true);
  await click('Continue');
  expect(host.textContent).toContain('ORDER-82');
  expect(host.textContent).toContain('Accept oversell risk');
  expect(host.textContent).toContain('LINE-82');
});

it.each([
  { sku: 'BKJS-Pink', counted: true, physical: 20, expected: 'Actual Stock Intake' },
  { sku: 'BKJS-Blue', counted: false, physical: null, expected: 'First Count this location' },
])('allows a production Lazada location to manage $sku based on its own count', async ({ sku, counted, physical, expected }) => {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('/sync') ? { status: 'not_synced', can_check: false, can_resync: false }
      : { status: 'verified', topology: 'single_warehouse', facilities: [
        { id: 7, name: 'Production warehouse', provider_id: 'dropshipping', inventory_warehouse_id: 19,
          counted, physical, reserved: counted ? 0 : null, available: physical },
      ] } }) as Response);
  vi.stubGlobal('fetch', fetchMock);
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}}
    item={{ ...item, masterSku: sku }} mode="independent"
    stores={[{ id: '3', name: 'Production Lazada', platform: 'lazada', environment: 'production' }]} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  await click('Production Lazada');
  await click('Production warehouse');
  await click('Manage this stock');
  expect(host.textContent).toContain(expected);
  expect(fetchMock.mock.calls.some(([input]) => String(input).includes('test-facility-acceptance'))).toBe(false);
});

it.each([
  { status: 'sync_failed', expected: ['Check status', 'Manage this stock'], absent: [] },
  { status: 'awaiting_confirmation', expected: ['Check status', 'Manage this stock'], absent: [] },
  { status: 'synced', expected: ['Manage this stock'], absent: ['Check status'] },
])('shows the approved recovery actions for $status', async ({ status, expected, absent }) => {
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
    String(input).includes('test-facility-acceptance')
      ? { test_acceptance_enabled: true, eligible: status !== 'awaiting_confirmation', facilities: [{ id: 7, eligible: status !== 'awaiting_confirmation' }] }
      : String(input).includes('/sync') ? { status, can_check: true, can_resync: status === 'sync_failed' }
        : { status: 'verified', topology: 'single_warehouse', facilities: [
          { id: 7, name: 'Warehouse A', provider_id: 'WH-A', inventory_warehouse_id: 19,
            counted: true, physical: 10, reserved: 0, available: 10 },
        ] } }) as Response));
  await act(async () => root.render(<FocusedManageStockDrawer isOpen onClose={() => {}} item={item}
    mode="independent" stores={[stores[1]]} initialStoreId="3" initialWarehouseId="7" />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  const buttons = [...host.querySelectorAll('footer button')].map(button => button.textContent?.trim());
  for (const label of expected) expect(buttons).toContain(label);
  for (const label of absent) expect(buttons).not.toContain(label);
});
