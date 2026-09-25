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
  { id: '3', name: 'Lazada Test', platform: 'lazada' },
  { id: '4', name: 'Unmapped Shopee', platform: 'shopee' }];
const click = async (label: string) => {
  const button = [...host.querySelectorAll('button')].find(node => node.textContent?.includes(label));
  expect(button, label).toBeDefined();
  await act(async () => button!.click());
};

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

it('keeps a test facility blocked when the existing eligibility check rejects it', async () => {
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
  expect(manage?.disabled).toBe(true);
});
