// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { LocationFirstCountBaseline } from './LocationFirstCountBaseline';

let host: HTMLDivElement;
let root: Root;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.unstubAllGlobals(); });

const item = { id: '55', masterSku: 'TEST-BLACK', productName: 'Mapped item', variantId: 55 } as any;
const click = async (label: string) => {
  const button = [...host.querySelectorAll('button')].find(node => node.textContent?.trim() === label);
  expect(button, label).toBeDefined();
  await act(async () => button!.click());
};

it('imports only the preselected Marketplace location without an allocation step', async () => {
  const requests: Array<{ url: string; body: any }> = [];
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input); requests.push({ url, body: init?.body ? JSON.parse(String(init.body)) : null });
    const body = url.endsWith('/observe')
      ? { quantity: 20, observed_at: '2026-09-29T10:00:00Z', provider_location_id: 'WH-A' }
      : url.endsWith('/commit')
        ? { status: 'saved', current_stock: 20, provider_sync_status: 'matched_at_import' }
        : { facilities: [{ id: 7, name: 'Warehouse A' }] };
    return { ok: true, json: async () => body } as Response;
  }));
  const saved = vi.fn(async () => {});
  await act(async () => root.render(<LocationFirstCountBaseline item={item} storeId="3" facilityId="7"
    storeName="Lazada Test" facilityName="Warehouse 7" onClose={() => {}} onSaved={saved} />));
  expect(host.textContent).toContain('Warehouse A');
  expect(host.textContent).not.toContain('Where should the stock go?');
  await click('Read Marketplace stock');
  expect(host.textContent).toContain('20 units');
  await click('Review First Count');
  expect(host.textContent).toContain('No initial stock push will be sent');
  await click('Confirm First Count');
  expect(saved).toHaveBeenCalledOnce();
  expect(host.textContent).toContain('Marketplace readback matched this baseline');
  const commit = requests.find(request => request.url.endsWith('/commit'));
  expect(commit?.body).toMatchObject({ store_key: '3', facility_id: 7,
    variant_id: 55, source: 'marketplace', quantity: 20, expected_provider_quantity: 20 });
});
