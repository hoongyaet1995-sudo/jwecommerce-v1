// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { StockSyncStoreButton } from './StockSyncStoreButton';

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

describe('StockSyncStoreButton', () => {
  it('is an obvious full-row button and opens stock sync details', async () => {
    const onOpen = vi.fn();
    await act(async () => {
      root.render(<StockSyncStoreButton storeName="Shopee Official" onOpen={onOpen}>Confirmed: 25</StockSyncStoreButton>);
    });

    const button = host.querySelector('button')!;
    expect(button.getAttribute('aria-label')).toBe('View stock sync details for Shopee Official');
    expect(button.getAttribute('title')).toBe('View stock sync details');
    expect(button.className).toContain('cursor-pointer');
    expect(button.className).toContain('hover:border-violet-400');

    await act(async () => button.click());
    expect(onOpen).toHaveBeenCalledOnce();
  });
});
