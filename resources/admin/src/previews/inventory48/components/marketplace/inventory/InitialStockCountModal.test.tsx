// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { InitialStockCountModal } from './InitialStockCountModal';

let host: HTMLDivElement;
let root: Root;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.restoreAllMocks(); });

const item = { id: '55', masterSku: 'TEST-1', productName: 'Test product', variantId: 55,
  mappedStoreIds: ['3'], storeAssociations: [{ connectionId: '3', storeName: 'Lazada Test', platform: 'lazada', channelStockSnapshot: 11 }] } as any;
const stores = [{ id: '3', name: 'Lazada Test', platform: 'lazada', connectionState: 'connected' },
  { id: '4', name: 'Unmapped Store', platform: 'shopee', connectionState: 'connected' },
  { id: '5', name: 'TikTok Shop', platform: 'tiktok', isComingSoon: true }] as any;

const mockFacilities = () => vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => ({ ok: true, json: async () =>
  String(input).endsWith('/sync/check') ? { status: 'synced', marketplace_reported_stock: 6 }
    : String(input).includes('/stores/4/') ? { status: 'verified', topology: 'no_warehouse', facilities: [] }
      : { status: 'verified', topology: 'multi_warehouse', facilities: [
        { id: 7, name: 'Warehouse A', provider_id: 'WH-A', inventory_warehouse_id: 19, counted: false, physical: null, reserved: 0, available: null },
        { id: 8, name: 'Warehouse B', provider_id: 'WH-B', inventory_warehouse_id: 20, counted: false, physical: null, reserved: 0, available: null },
      ] } }) as Response));
const click = async (label: string) => {
  const button = [...host.querySelectorAll('button')].find(node => node.textContent?.includes(label));
  expect(button, label).toBeTruthy();
  await act(async () => button!.click());
};
const enter = async (label: string, value: string) => {
  const input = host.querySelector(`input[aria-label="${label}"]`) as HTMLInputElement;
  expect(input, label).toBeTruthy();
  await act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
};

it('allocates one independent verified count through the existing atomic batch payload', async () => {
  mockFacilities();
  const save = vi.fn(async () => {});
  await act(async () => root.render(<InitialStockCountModal isOpen onClose={() => {}} item={item}
    mode="independent" stores={stores} initialTargetStoreId="3" initialTargetWarehouseId="7" onSaveCount={save} />));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  expect(host.textContent).toContain('Lazada Test');
  expect(host.textContent).not.toContain('Unmapped Store');
  expect(host.textContent).not.toContain('TikTok Shop');
  expect(host.querySelector('input[aria-label="Verified physical quantity"]')).toHaveProperty('value', '');
  await enter('Verified physical quantity', '10');
  await click('Allocate stock');
  expect(host.textContent).toContain('10 verified − 0 allocated');
  await click('Choose warehouses');
  await enter('Lazada Test Warehouse A first count', '7');
  await enter('Lazada Test Warehouse B first count', '3');
  await click('Done with warehouses');
  expect(host.textContent).toContain('All units allocated');
  await click('Review allocation');
  expect(host.textContent).toContain('Warehouse breakdown');
  await click('Confirm allocation');
  expect(save).toHaveBeenCalledWith(expect.objectContaining({
    sku: 'TEST-1', physicalCount: 10,
    batchAllocations: expect.objectContaining({ 'facility:3:7': 7, 'facility:3:8': 3 }),
  }));
  expect((save.mock.calls as unknown as Array<[Record<string, any>]>)[0][0].batchAllocations).not.toHaveProperty('marketplace:4');
  expect(host.textContent).toContain('Stock allocation completed');
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  expect(host.textContent).toContain('Warehouse A');
  expect(host.textContent).toContain('Synced');
  expect(host.textContent).toContain('Refresh status');
});

it('preserves a warehouse draft when store data refreshes', async () => {
  mockFacilities();
  const render = (name: string) => root.render(<InitialStockCountModal isOpen onClose={() => {}} item={item}
    mode="independent" stores={[{ id: '3', name, platform: 'lazada', connectionState: 'connected' } as any]}
    initialTargetStoreId="3" initialTargetWarehouseId="7" onSaveCount={vi.fn()} />);
  await act(async () => render('Lazada Test'));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  await enter('Verified physical quantity', '20');
  await click('Allocate stock');
  await click('Choose warehouses');
  await enter('Lazada Test Warehouse A first count', '12');
  await act(async () => render('Lazada Test'));
  await act(async () => { await Promise.resolve(); await Promise.resolve(); });
  expect(host.querySelector('input[aria-label="Lazada Test Warehouse A first count"]')).toHaveProperty('value', '12');
  await click('Done with warehouses');
  await click('Back');
  expect(host.querySelector('input[aria-label="Verified physical quantity"]')).toHaveProperty('value', '20');
});
