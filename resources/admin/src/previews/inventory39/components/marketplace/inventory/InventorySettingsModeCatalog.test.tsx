// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import { InventorySettingsModal } from './InventorySettingsModal';

it('shows the complete categorized SKU catalog and allocates only ready physical stock', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const Component = InventorySettingsModal as any;
  const catalog = {
    summary: { total: 5, ready: 1, blocked: 1, excluded: 3 },
    items: [
      { variant_id: 1, sku: 'READY', product: 'Ready Product', variant: 'Default', product_status: 'active', classification: 'ready_to_allocate', physical_stock: 12, reserved_stock: 0, counted: true, mapped_stores: [] },
      { variant_id: 2, sku: 'COUNT', product: 'Needs Count', variant: 'Default', product_status: 'active', classification: 'awaiting_first_count', physical_stock: null, reserved_stock: null, counted: false, mapped_stores: [] },
      { variant_id: 3, sku: 'TEST', product: 'Sandbox Product', variant: 'Default', product_status: 'draft', classification: 'sandbox_test', physical_stock: 5, reserved_stock: 0, counted: true, mapped_stores: [] },
      { variant_id: 4, sku: 'ARCHIVE', product: 'Archived Product', variant: 'Default', product_status: 'archived', classification: 'excluded_status', physical_stock: 3, reserved_stock: 0, counted: true, mapped_stores: [] },
      { variant_id: 5, sku: 'TAKEOVER', product: 'Unmanaged Listing', variant: 'Default', product_status: 'active', classification: 'store_not_managed', physical_stock: null, reserved_stock: null, counted: false, mapped_stores: [{ id: 9, name: 'Shopee Test', management_status: 'not_managed' }] },
    ],
  };
  const stores: any[] = [
    { id: 'website', name: 'JW Storefront', nickname: 'JW Storefront', platform: 'webstore', connectionState: 'connected', isManagedByJw: true },
    { id: '9', name: 'Shopee Test', nickname: 'Shopee Test', platform: 'shopee', connectionState: 'connected', isManagedByJw: true },
  ];

  await act(async () => root.render(<Component isOpen currentMode="shared" stores={stores} inventory={[]} modeCatalog={catalog}
    onClose={vi.fn()} onSwitchMode={vi.fn()} onToggleStoreManagement={vi.fn()} onSelectSkuForCount={vi.fn()} />));
  const click = async (label: string) => {
    const button = Array.from(host.querySelectorAll('button')).find(item => item.textContent?.includes(label))!;
    expect(button).toBeTruthy();
    await act(async () => button.click());
  };
  await click('Switch to Independent Stock Mode');
  await click('Proceed to Store Allocation');

  expect(host.textContent).toContain('5 Total');
  expect(host.textContent).toContain('1 Ready');
  expect(host.textContent).toContain('1 Blocked');
  expect(host.textContent).toContain('3 Excluded');
  expect(host.textContent).toContain('Ready to Allocate');
  expect(host.textContent).toContain('Awaiting First Count');
  expect(host.textContent).toContain('Sandbox / Test');
  expect(host.textContent).toContain('Draft / Archived');
  expect(host.textContent).toContain('Store Not Managed by JW');
  expect(host.querySelectorAll('input[type="number"]')).toHaveLength(2);

  await act(async () => root.unmount());
  host.remove();
});
