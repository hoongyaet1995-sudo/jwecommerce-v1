// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, expect, it, vi } from 'vitest';
import { StoreStockTransfersTab } from './StoreStockTransfersTab';

const stores: any[] = [
  { id: 'website', platform: 'webstore', platformDisplayName: 'JW Storefront', name: 'JW Storefront', nickname: 'Web', region: 'MY', connectionState: 'connected', isManagedByJw: true, readStatus: 'ok', syncStatus: 'synced' },
  { id: '12', platform: 'lazada', platformDisplayName: 'Lazada', name: 'Lazada Test', nickname: 'Lazada Test', region: 'MY', connectionState: 'connected', isManagedByJw: true, readStatus: 'ok', syncStatus: 'synced' },
];

const inventory: any[] = [
  { id: '1', masterSku: 'AUR-PINE-BLK', barcode: '111', productName: 'Aurora Headphones', variantName: 'Black', imageUrl: '', warehouseLocation: 'A1', totalPhysicalStock: 8, reservedStock: 0, safetyStock: 0, availableStock: 8, reorderPoint: 0, costPrice: 0, sellingPrice: 0, channelMappings: {}, autoSyncEnabled: true, historyLog: [] },
  { id: '2', masterSku: 'MUSE-GREEN-M', barcode: '222', productName: 'Muse Casual Collection', variantName: 'Green / M', imageUrl: '', warehouseLocation: 'B2', totalPhysicalStock: 5, reservedStock: 0, safetyStock: 0, availableStock: 5, reorderPoint: 0, costPrice: 0, sellingPrice: 0, channelMappings: {}, autoSyncEnabled: true, historyLog: [] },
];

const storeBalances: any = {
  'AUR-PINE-BLK': { website: { physicalStock: 5, reservedStock: 0, availableStock: 5 }, 12: { physicalStock: 3, reservedStock: 0, availableStock: 3 } },
  'MUSE-GREEN-M': { website: { physicalStock: 4, reservedStock: 0, availableStock: 4 }, 12: { physicalStock: 1, reservedStock: 0, availableStock: 1 } },
};

afterEach(() => { document.body.innerHTML = ''; });

it('opens the paginated catalog, searches by product data, and selects the requested SKU', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);

  await act(async () => root.render(
    <StoreStockTransfersTab
      inventory={inventory}
      stores={stores}
      storeBalances={storeBalances}
      transfersList={[]}
      onExecuteTransfer={vi.fn()}
      onRetryPlatformSync={vi.fn()}
    />
  ));

  const browse = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Browse Full Catalog'))!;
  await act(async () => browse.click());
  expect(host.textContent).toContain('Select Product / Master SKU from Catalog');

  const modalSearch = host.querySelector('input[placeholder^="Filter by SKU, Product Name"]') as HTMLInputElement;
  expect(modalSearch).not.toBeNull();
  await act(async () => {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
    setter.call(modalSearch, 'Muse Casual');
    modalSearch.dispatchEvent(new Event('input', { bubbles: true }));
  });
  const catalogModal = modalSearch.closest('div.fixed')!;
  expect(catalogModal.textContent).toContain('MUSE-GREEN-M');
  expect(catalogModal.textContent).not.toContain('AUR-PINE-BLK');

  const select = Array.from(catalogModal.querySelectorAll('button')).find(button => button.textContent?.includes('Select SKU'))!;
  await act(async () => select.click());
  expect(host.textContent).toContain('MUSE-GREEN-M');
  expect(host.textContent).toContain('Selected SKU');

  await act(async () => root.unmount());
});

it('does not contain prototype timers or hard-coded source store ids', () => {
  const source = readFileSync(resolve(process.cwd(), 'src/previews/inventory39/components/marketplace/inventory/StoreStockTransfersTab.tsx'), 'utf8');
  expect(source).not.toContain('setTimeout(');
  expect(source).not.toContain("useState<string>('webstore')");
  expect(source).not.toContain("useState<string>('conn-shopee-01')");
});
