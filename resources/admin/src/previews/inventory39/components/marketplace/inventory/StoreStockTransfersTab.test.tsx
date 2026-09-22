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

it('does not report pending channel work as both stores synced', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const pendingTransfer: any = {
    id: 'operation-1', transferNumber: 'TR-1', timestamp: new Date().toISOString(),
    masterSku: 'AUR-PINE-BLK', productName: 'Aurora Headphones', variantName: 'Black', imageUrl: '',
    sourceStoreId: 'website', sourceStoreName: 'JW Storefront', sourcePlatform: 'webstore',
    destinationStoreId: '12', destinationStoreName: 'Lazada Test', destinationPlatform: 'lazada',
    quantity: 2, operator: 'Admin', reason: 'Rebalance', ledgerStatus: 'succeeded',
    sourcePlatformSyncStatus: 'succeeded', destinationPlatformSyncStatus: 'pending',
  };

  await act(async () => root.render(
    <StoreStockTransfersTab
      inventory={inventory}
      stores={stores}
      storeBalances={storeBalances}
      transfersList={[pendingTransfer]}
      onExecuteTransfer={vi.fn()}
      onRetryPlatformSync={vi.fn()}
    />
  ));

  expect(host.textContent).toContain('Awaiting Channel Confirmation');
  expect(host.textContent).not.toContain('Both Stores Synced');
  await act(async () => root.unmount());
});

it('opens a pending history operation with freshly loaded channel stages', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const pendingTransfer: any = {
    id: 'operation-history', transferNumber: 'TR-HISTORY', timestamp: new Date().toISOString(),
    masterSku: 'AUR-PINE-BLK', productName: 'Aurora Headphones', variantName: 'Black', imageUrl: '',
    sourceStoreId: 'website', sourceStoreName: 'JW Storefront', sourcePlatform: 'webstore',
    destinationStoreId: '12', destinationStoreName: 'Lazada Test', destinationPlatform: 'lazada',
    quantity: 2, operator: 'Admin', reason: 'Rebalance', ledgerStatus: 'succeeded',
    sourcePlatformSyncStatus: 'succeeded', destinationPlatformSyncStatus: 'pending',
  };
  const onOpenTransferExecution = vi.fn().mockResolvedValue({
    ...pendingTransfer,
    destinationPlatformSyncStatus: 'failed',
    externalStages: [
      { id: 'source-stage', stage: 'source', status: 'confirmed' },
      { id: 'destination-stage', stage: 'destination', status: 'auth_expired', store_id: 12 },
    ],
  });

  await act(async () => root.render(
    <StoreStockTransfersTab inventory={inventory} stores={stores} storeBalances={storeBalances}
      transfersList={[pendingTransfer]} onExecuteTransfer={vi.fn()} onRetryPlatformSync={vi.fn()}
      onOpenTransferExecution={onOpenTransferExecution} />
  ));

  const pending = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Awaiting Channel Confirmation'))!;
  expect(pending).toBeDefined();
  await act(async () => pending.click());

  expect(onOpenTransferExecution).toHaveBeenCalledWith(pendingTransfer);
  expect(host.textContent).toContain('Store Transfer Execution Pipeline');
  expect(host.textContent).toContain('Authorization Expired');
  expect(host.textContent).toContain('Reconnect Store');

  await act(async () => root.unmount());
});

it('shows source confirmation delay on the source stage without blaming the destination', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const onExecuteTransfer = vi.fn().mockResolvedValue({
    id: 'operation-2', transferNumber: 'TR-2', timestamp: new Date().toISOString(),
    masterSku: 'AUR-PINE-BLK', productName: 'Aurora Headphones', variantName: 'Black', imageUrl: '',
    sourceStoreId: 'website', sourceStoreName: 'JW Storefront', sourcePlatform: 'webstore',
    destinationStoreId: '12', destinationStoreName: 'Lazada Test', destinationPlatform: 'lazada',
    quantity: 1, operator: 'Admin', reason: 'Rebalance', ledgerStatus: 'succeeded',
    sourcePlatformSyncStatus: 'awaiting_confirmation', destinationPlatformSyncStatus: 'succeeded',
    sourceFailureReason: 'Channel confirmation is still pending.',
  });

  await act(async () => root.render(
    <StoreStockTransfersTab
      inventory={inventory}
      stores={stores}
      storeBalances={storeBalances}
      transfersList={[]}
      onExecuteTransfer={onExecuteTransfer}
      onRetryPlatformSync={vi.fn()}
    />
  ));

  const execute = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Execute Store Stock Transfer'))!;
  await act(async () => execute.click());

  const sourceTitle = Array.from(host.querySelectorAll('div')).find(element => element.textContent === 'Step 2: Source Channel Stock Update (JW Storefront)')!;
  const destinationTitle = Array.from(host.querySelectorAll('div')).find(element => element.textContent === 'Step 3: Destination Channel Stock Update (Lazada)')!;
  const sourceStage = sourceTitle.parentElement!.parentElement!.parentElement!;
  const destinationStage = destinationTitle.parentElement!.parentElement!.parentElement!;
  expect(sourceStage.textContent).toContain('Channel confirmation is still pending.');
  expect(sourceStage.textContent).toContain('Awaiting Confirmation');
  expect(destinationStage.textContent).toContain('Confirmed synced with destination store.');
  expect(destinationStage.textContent).not.toContain('API Gateway timeout');

  await act(async () => root.unmount());
});

it('uses the shared authorization wording and reconnect recovery action', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const onReconnectStore = vi.fn();
  const onExecuteTransfer = vi.fn().mockResolvedValue({
    id: 'operation-auth', transferNumber: 'TR-AUTH', timestamp: new Date().toISOString(),
    masterSku: 'AUR-PINE-BLK', productName: 'Aurora Headphones', variantName: 'Black', imageUrl: '',
    sourceStoreId: 'website', sourceStoreName: 'JW Storefront', sourcePlatform: 'webstore',
    destinationStoreId: '12', destinationStoreName: 'Lazada Test', destinationPlatform: 'lazada',
    quantity: 1, operator: 'Admin', reason: 'Rebalance', ledgerStatus: 'succeeded',
    sourcePlatformSyncStatus: 'succeeded', destinationPlatformSyncStatus: 'failed',
    externalStages: [
      { id: 'source-stage', stage: 'source', status: 'not_applicable' },
      { id: 'destination-stage', stage: 'destination', status: 'auth_expired' },
    ],
  });

  await act(async () => root.render(
    <StoreStockTransfersTab inventory={inventory} stores={stores} storeBalances={storeBalances} transfersList={[]}
      onExecuteTransfer={onExecuteTransfer} onRetryPlatformSync={vi.fn()} onReconnectStore={onReconnectStore} />
  ));
  const execute = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Execute Store Stock Transfer'))!;
  await act(async () => execute.click());

  expect(host.textContent).toContain('Authorization Expired');
  const reconnect = Array.from(host.querySelectorAll('button')).find(button => button.textContent === 'Reconnect Store')!;
  await act(async () => reconnect.click());
  expect(onReconnectStore).toHaveBeenCalledWith('12');

  await act(async () => root.unmount());
});
