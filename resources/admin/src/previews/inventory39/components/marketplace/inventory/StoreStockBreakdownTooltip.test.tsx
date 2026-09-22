// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { StoreStockBreakdownTooltip } from './StoreStockBreakdownTooltip';

let host: HTMLDivElement;
let root: Root;

beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  host = document.createElement('div');
  document.body.append(host);
  root = createRoot(host);
});

afterEach(async () => {
  await act(async () => root.unmount());
  host.remove();
});

describe('StoreStockBreakdownTooltip', () => {
  it('keeps store quantities inside an accessible tooltip trigger', async () => {
    await act(async () => {
      root.render(<StoreStockBreakdownTooltip balances={[
        { storeId: 'website', storeName: 'JW Storefront', storeNickname: 'JW Storefront', physicalStock: 20 },
        { storeId: 'shopee', storeName: 'Shopee Official', storeNickname: 'Shopee Official MY', physicalStock: 0 },
      ] as any} />);
    });

    const trigger = host.querySelector('button')!;
    expect(trigger.getAttribute('aria-label')).toBe('View stock allocation by store');
    const tooltipId = trigger.getAttribute('aria-describedby');
    expect(tooltipId).toBeTruthy();
    expect(host.querySelector('[role="tooltip"]')?.id).toBe(tooltipId);
    expect(host.textContent).toContain('Stock by store');
    expect(host.textContent).toContain('JW Storefront');
    expect(host.textContent).toContain('20 units');
    expect(host.textContent).toContain('Shopee Official MY');
    expect(host.textContent).toContain('0 units');
    expect(host.querySelector('[role="tooltip"]')?.className).toContain('group-hover:visible');
    expect(host.querySelector('[role="tooltip"]')?.className).toContain('group-focus-within:visible');
  });
});
