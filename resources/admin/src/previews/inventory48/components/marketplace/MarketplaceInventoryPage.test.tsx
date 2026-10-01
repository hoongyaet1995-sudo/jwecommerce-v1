// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
vi.mock('../../../../integration/storeInventory',()=>({storeInventoryApi:{latestStockRetrieval:vi.fn().mockResolvedValue(null),facilities:vi.fn().mockResolvedValue({status:'verified',topology:'no_warehouse',facilities:[]})}}));

const inventoryItem: any = {
  id: 'variant:7', variantId: 7, productId: 4, masterSku: 'SKU-7', productName: 'Baju Kurung Joshua',
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
import {previewReview} from './inventory/batchSync/previewReview';
import {storeInventoryApi} from '../../../../integration/storeInventory';

let host: HTMLDivElement;
let root: Root;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); });

it('opens the actual selected inventory preview, preserves per-location quantities and closes/reopens without batch requests', async () => {
  const balances=[{storeId:11,facilityId:51,providerFacilityId:'A',poolKind:'facility',counted:true,quantity:0},{storeId:11,facilityId:52,providerFacilityId:'B',poolKind:'facility',counted:true,quantity:23}];
  context.marketplaceInventory=[{...inventoryItem,batchPreviewBalances:balances}];
  try {
    const shared=previewReview([{...inventoryItem,batchPreviewBalances:[{poolKind:'shared',counted:true,quantity:37},...balances]}],context.inventoryStores,'shared');
    expect(shared.targets.every(row=>row.quantity===37&&row.disposition==='skipped')).toBe(true);
    expect(shared.counts.validDestinations).toBe(0);
    expect(previewReview([{...inventoryItem,batchPreviewBalances:[]}],context.inventoryStores,'shared').targets[0].quantity).toBeNull();
    await act(async()=>root.render(<MarketplaceInventoryPage/>));
    await act(async()=>host.querySelector<HTMLInputElement>('[aria-label="Select Baju Kurung Joshua and all its SKUs"]')!.click());
    const launch=()=>[...host.querySelectorAll<HTMLButtonElement>('button')].find(button=>button.textContent?.includes('Batch Sync Selected'))!;
    await act(async()=>launch().click());
    const dialog=()=>host.querySelector<HTMLElement>('[role="dialog"]')!;
    expect(dialog().textContent).toContain('UI preview — batch sync is not connected');
    expect(dialog().querySelector('[data-target-quantity="0"]')).not.toBeNull();
    expect(dialog().querySelector('[data-target-quantity="23"]')).not.toBeNull();
    expect(dialog().textContent).toContain('0Eligible SKUs');
    const start=[...dialog().querySelectorAll<HTMLButtonElement>('button')].find(button=>button.textContent?.includes('Start sync'))!;
    expect(start.disabled).toBe(true);
    await act(async()=>start.click());
    await act(async()=>dialog().querySelector<HTMLButtonElement>('[aria-label="Close"]')!.click());
    expect(host.querySelector('[role="dialog"]')).toBeNull();
    await act(async()=>launch().click());
    expect(dialog().textContent).toContain('SKU-7');
    expect(storeInventoryApi.facilities).not.toHaveBeenCalled();
    expect(context.addToast).not.toHaveBeenCalled();
  } finally {context.marketplaceInventory=[inventoryItem];}
});

it('shows the mapped store total and opens its existing Manage Stock flow without showing unmapped stores', async () => {
  await act(async () => root.render(<MarketplaceInventoryPage />));

  expect(host.textContent).toContain('Mapped Stores');
  expect(host.textContent).toContain('Mapped Lazada');
  expect(host.textContent).toContain('40 units');
  expect(host.querySelector('tbody tr td:nth-child(6)')?.textContent).not.toContain('Unmapped Shopee');
  const store = host.querySelector<HTMLButtonElement>('[aria-label="View Mapped Lazada stock for SKU-7"]');
  expect(store).not.toBeNull();
  await act(async () => store!.click());
  expect(host.querySelector('[data-testid="manage-stock-target"]')?.textContent).toBe('11');
});

it('shows only mapped shared-pool stores and a confirmed state for the current JW balance', async () => {
  context.inventoryMode = 'shared';
  context.marketplaceInventory = [{ ...inventoryItem, sharedStoreSyncStates: { '11': 'synced' } }];
  try {
    await act(async () => root.render(<MarketplaceInventoryPage />));
    const cell = host.querySelector('[data-testid=connected-stores]');
    expect(cell?.textContent).toContain('JW Storefront');
    expect(cell?.textContent).toContain('Mapped Lazada');
    expect(cell?.textContent).toContain('Shared ledger');
    expect(cell?.textContent).not.toContain('Unmapped Shopee');
    expect(cell?.textContent).not.toContain('SKU Not Mapped');
  } finally {
    context.inventoryMode = 'independent';
    context.marketplaceInventory = [inventoryItem];
  }
});

it('selects all variants from one product row while keeping SKU actions separate', async () => {
  context.marketplaceInventory = [inventoryItem, {...inventoryItem, id:'variant:8', variantId:8,
    masterSku:'SKU-8', variantName:'Beige'}];
  await act(async () => root.render(<MarketplaceInventoryPage />));
  expect(host.textContent).toContain('2 SKUs');
  expect(host.querySelectorAll('button[aria-label^="View Mapped Lazada stock for"]')).toHaveLength(2);
  const checkbox = host.querySelector<HTMLInputElement>('input[aria-label="Select Baju Kurung Joshua and all its SKUs"]');
  expect(checkbox).not.toBeNull();
  await act(async () => { checkbox!.click(); });
  expect(host.textContent).toContain('1 product(s) selected · 2 SKU(s) included');
  context.marketplaceInventory = [inventoryItem];
});

it('renders a separate card and table header for every product', async () => {
  context.marketplaceInventory = [{...inventoryItem, singleCurrentStock: true}, {...inventoryItem, id:'variant:9', variantId:9,
    productId: 5, productName: 'Another Product', masterSku: 'SKU-9', singleCurrentStock: true}];
  await act(async () => root.render(<MarketplaceInventoryPage />));
  const cards = host.querySelectorAll('section[aria-label]');
  expect(cards).toHaveLength(2);
  for (const card of cards) {
    expect(card.querySelectorAll('thead')).toHaveLength(1);
    expect(card.querySelector('thead')?.textContent).toContain('Current Stock');
    expect(card.querySelector('thead')?.textContent).toContain('Mapped Stores');
    expect(card.querySelectorAll('tbody tr')).toHaveLength(1);
  }
  const collapse = cards[0].querySelector<HTMLButtonElement>('button[aria-label^="Collapse"]');
  await act(async () => collapse!.click());
  expect(cards[0].querySelector('table')).toBeNull();
  expect(cards[1].querySelector('table')).not.toBeNull();
  context.marketplaceInventory = [inventoryItem];
});

it('offers First Count for an unknown shared total and Manage Stock for zero, with no store stock action',async()=>{
 context.inventoryMode='shared';
 context.marketplaceInventory=[{...inventoryItem,currentStock:null,totalPhysicalStock:null,physicalStockStatus:'unknown'}];
 try {
  await act(async()=>root.render(<MarketplaceInventoryPage />));
  expect(host.querySelector('thead')?.textContent).toContain('Shared Stock');
  expect(host.querySelector('thead')?.textContent).not.toContain('Reserved');
  expect(host.textContent).toContain('Awaiting first count');
  const first=Array.from(host.querySelectorAll('button')).find(button=>button.textContent==='First Count')!;
  expect(first).toBeDefined();
  await act(async()=>first.click());
  expect(host.querySelector('[data-testid=manage-stock-target]')).not.toBeNull();
  expect(host.querySelector('[data-testid=connected-stores] [role=button]')).toBeNull();
  context.marketplaceInventory=[{...inventoryItem,currentStock:0,totalPhysicalStock:0}];
  await act(async()=>root.render(<MarketplaceInventoryPage />));
  expect(host.textContent).toContain('0 units');
  expect(Array.from(host.querySelectorAll('button')).some(button=>button.textContent==='First Count')).toBe(false);
  expect(Array.from(host.querySelectorAll('button')).some(button=>button.textContent==='Manage Stock')).toBe(true);
 } finally {context.inventoryMode='independent';context.marketplaceInventory=[inventoryItem]}
});
