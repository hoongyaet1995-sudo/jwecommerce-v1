// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { SharedPoolManageStockDrawer } from './SharedPoolManageStockDrawer';
import { storeInventoryApi } from '../../../../../integration/storeInventory';

let host: HTMLDivElement;
let root: Root;
const item = { id: 'variant:7', variantId: 7, masterSku: 'SKU-7', productName: 'Product', variantName: 'Blue', imageUrl: '' } as any;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  vi.spyOn(storeInventoryApi, 'sharedFirstCountMarketplaceStock').mockResolvedValue({stores:[],available_total:0,all_read:true});
  vi.spyOn(storeInventoryApi, 'operation').mockResolvedValue({external_stages:[]});
  host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.restoreAllMocks(); });
const click = async (label: string) => {
  const button = [...host.querySelectorAll('button')].find(node => node.textContent?.trim() === label);
  expect(button, label).toBeDefined();
  await act(async () => button!.click());
};

it('starts a single Shared Pool SKU First Count and accepts zero without choosing a store', async () => {
  const save = vi.fn(async () => ({operation_id:'operation-7',external_stages:[]}));
  await act(async () => root.render(<SharedPoolManageStockDrawer isOpen item={{ ...item, currentStock: null }} onClose={() => {}} onFirstCount={save} />));
  expect(host.textContent).toContain('Not counted');
  expect(host.textContent).not.toContain('Choose a store');
  await click('First Count this SKU');
  const input = host.querySelector<HTMLInputElement>('#shared-first-count-quantity')!;
  await act(async () => {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
    setter.call(input, '0'); input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await click('Review First Count');
  await click('Confirm First Count');
  expect(save).toHaveBeenCalledWith(expect.objectContaining({ masterSku: 'SKU-7' }), 0);
  expect(host.textContent).toContain('First Count recorded');
});

it('keeps counted Shared Pool actions distinct from Independent store and transfer actions', async () => {
  await act(async () => root.render(<SharedPoolManageStockDrawer isOpen item={{ ...item, currentStock: 40 }} onClose={() => {}} onFirstCount={vi.fn(async () => ({operation_id:'unused'}))} />));
  expect(host.textContent).toContain('JW Current Stock · Shared Pool40');
  expect(host.textContent).not.toContain('First Count this SKU');
  await click('Manage this stock');
  expect(host.textContent).toContain('Actual Stock Intake');
  expect(host.textContent).toContain('Stock Audit Adjustment');
  expect(host.textContent).toContain('View JW stock ledger');
  expect([...host.querySelectorAll('button')].some(button => button.textContent?.includes('Transfer'))).toBe(false);
});
