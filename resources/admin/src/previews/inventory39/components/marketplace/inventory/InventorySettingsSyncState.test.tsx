// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import { InventorySettingsModal } from './InventorySettingsModal';

it('shows automatic enrollment sync states without takeover controls', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const stores: any[] = [
    { id: 'website', name: 'JW Ecom Storefront', nickname: 'JW Storefront', platform: 'webstore', platformDisplayName: 'JW Ecom Storefront', connectionState: 'connected', isManagedByJw: true, inventorySyncState: 'active' },
    { id: '1', name: 'Shopee Test', nickname: 'Shopee Test', platform: 'shopee', platformDisplayName: 'Shopee', connectionState: 'connected', isManagedByJw: true, inventorySyncState: 'setup_required' },
    { id: '2', name: 'Lazada Test', nickname: 'Lazada Test', platform: 'lazada', platformDisplayName: 'Lazada', connectionState: 'connected', isManagedByJw: true, inventorySyncState: 'paused' },
  ];
  await act(async () => root.render(<InventorySettingsModal isOpen currentMode="shared" stores={stores} inventory={[]}
    modeCatalog={{items: [], summary: {total: 0, ready: 0, blocked: 0, excluded: 0}}} onClose={vi.fn()}
    onSwitchMode={vi.fn()} onToggleStoreManagement={vi.fn()} />));
  const tab = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Store Inventory Sync'))!;
  await act(async () => tab.click());
  expect(host.textContent).toContain('Inventory Active');
  expect(host.textContent).toContain('Setup Required');
  expect(host.textContent).toContain('Sync Paused');
  expect(host.textContent).toContain('Resolve Setup');
  expect(host.textContent).toContain('Resume Sync');
  expect(host.textContent).not.toContain('Takeover Inventory');
  expect(host.textContent).not.toContain('Not Managed by JW');
  await act(async () => root.unmount());
  host.remove();
});
