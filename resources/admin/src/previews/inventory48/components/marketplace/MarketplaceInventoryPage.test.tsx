// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const inventoryItem: any = {
  id: 'variant:7', variantId: 7, masterSku: 'SKU-7', productName: 'Baju Kurung Joshua',
  variantName: 'Pink', barcode: '', imageUrl: '', physicalStockStatus: 'verified',
  totalPhysicalStock: 42, reservedStock: 2, availableStock: 40, mappedStoreIds: ['11'],
  storeAssociations: [], historyLog: [], channelMappings: {},
};
const context: any = {
  marketplaceInventory: [inventoryItem], connections: [], marketplaceOrders: [],
  inventoryMode: 'independent', inventoryActivityHistory: [],
  inventoryStores: [
    { id: 'website', platform: 'webstore', name: 'JW Storefront', nickname: 'JW Storefront', connectionState: 'connected' },
    { id: '11', platform: 'lazada', name: 'Mapped Lazada', nickname: 'Mapped Lazada', connectionState: 'connected' },
    { id: '12', platform: 'shopee', name: 'Unmapped Shopee', nickname: 'Unmapped Shopee', connectionState: 'connected' },
  ],
  inventoryStoreBalances: { 'SKU-7': {
    '11': { isCounted: true, availableStock: 40, facilityCount: 2 },
    '12': { isCounted: true, availableStock: 9 },
  } },
  addToast: vi.fn(), reloadInventory: vi.fn(), setCurrentSection: vi.fn(),
};
vi.mock('../../context/AdminContext', () => ({ useAdmin: () => context }));
vi.mock('./inventory/FocusedManageStockDrawer', () => ({
  FocusedManageStockDrawer: ({ isOpen, initialStoreId }: any) => isOpen
    ? <div data-testid="manage-stock-target">{initialStoreId}</div> : null,
}));

import { MarketplaceInventoryPage } from './MarketplaceInventoryPage';

let host: HTMLDivElement;
let root: Root;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); });

it('shows the mapped store total and opens its existing Manage Stock flow without showing unmapped stores', async () => {
  await act(async () => root.render(<MarketplaceInventoryPage />));

  expect(host.textContent).toContain('Mapped stores · JW stock');
  expect(host.textContent).toContain('Mapped Lazada');
  expect(host.textContent).toContain('40 units');
  expect(host.querySelector('tbody tr td:nth-child(6)')?.textContent).not.toContain('Unmapped Shopee');
  const store = host.querySelector<HTMLButtonElement>('[aria-label="View Mapped Lazada stock for SKU-7"]');
  expect(store).not.toBeNull();
  await act(async () => store!.click());
  expect(host.querySelector('[data-testid="manage-stock-target"]')?.textContent).toBe('11');
});
