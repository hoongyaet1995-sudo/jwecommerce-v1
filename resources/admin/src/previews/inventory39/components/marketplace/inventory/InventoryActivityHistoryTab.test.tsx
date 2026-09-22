// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import { InventoryActivityHistoryTab } from './InventoryActivityHistoryTab';

afterEach(() => { document.body.innerHTML = ''; });

it('shows an employee-facing activity summary without exposing the raw audit payload', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const item: any = {
    id: 'operation-1', timestamp: '2026-09-22T03:47:46Z', actionType: 'actual_intake',
    masterSku: 'PS-NIG-500', productName: 'PS5 Pro', variantName: 'Night Grey / 500GB',
    targetScope: 'JW Ecom Storefront', quantityChange: 3, previousStock: 8, resultingStock: 11,
    operator: 'JW Studio Owner', reason: 'Supplier delivery', notes: 'PO received',
    status: 'succeeded', providerSyncStatus: 'confirmed', referenceId: 'operation-1',
    detailsPayload: { operation_id: 'operation-1', balance_id: 38, version: 2, secret_debug_value: 'hidden' },
  };

  await act(async () => root.render(<InventoryActivityHistoryTab logs={[item]} />));
  const details = host.querySelector('button[title="View activity details"]') as HTMLButtonElement;
  expect(details).not.toBeNull();
  await act(async () => details.click());

  expect(host.textContent).toContain('Activity Details');
  expect(host.textContent).toContain('PS-NIG-500');
  expect(host.textContent).toContain('JW Ecom Storefront');
  expect(host.textContent).toContain('Supplier delivery');
  expect(host.textContent).toContain('PO received');
  expect(host.textContent).toContain('Done');
  expect(host.textContent).not.toContain('operation-1');
  expect(host.textContent).not.toContain('Payload Audit Inspection');
  expect(host.textContent).not.toContain('secret_debug_value');
  expect(host.querySelector('pre')).toBeNull();

  await act(async () => root.unmount());
});
