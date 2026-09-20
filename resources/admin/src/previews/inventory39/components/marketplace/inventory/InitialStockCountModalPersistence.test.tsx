// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import { InitialStockCountModal } from './InitialStockCountModal';

it('shows success only after the server save resolves', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  let completeSave!: () => void;
  const save = vi.fn(() => new Promise<void>(resolve => { completeSave = resolve; }));
  const item: any = { id: '', variantId: 5, masterSku: 'SKU-SOLIS-WAL-STD', productName: 'Solis', variantName: 'Walnut', catalogStockSnapshot: 32, totalPhysicalStock: null, reservedStock: 0, storeAssociations: [] };

  await act(async () => root.render(<InitialStockCountModal isOpen onClose={vi.fn()} item={item} mode="shared" stores={[]} onSaveCount={save} />));
  expect(host.textContent).toContain('JW Catalog Reference');
  expect(host.textContent).toContain('32 units');
  const input = host.querySelector('input[type="number"]') as HTMLInputElement;
  await act(async () => {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
    setter.call(input, '32');
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Proceed to Review')) as HTMLButtonElement).click());
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Confirm & Save Physical Count')) as HTMLButtonElement).click());

  expect(save).toHaveBeenCalledOnce();
  expect(host.textContent).not.toContain('Physical Stock Count Successfully Saved');
  await act(async () => completeSave());
  expect(host.textContent).toContain('Physical Stock Count Successfully Saved');

  await act(async () => root.unmount());
  host.remove();
});
