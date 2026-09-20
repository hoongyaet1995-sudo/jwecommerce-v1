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
  expect(host.textContent).toContain('Verified (32 units)');
  expect(host.textContent).not.toContain('Not Counted (未盘点)');

  await act(async () => root.unmount());
  host.remove();
});

it('continues to mode switch preparation after a successful count', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const continuePreparation = vi.fn();
  const item: any = { id: '', variantId: 6, masterSku: 'SKU-SOLIS-OAK-STD', productName: 'Solis', variantName: 'Oak', catalogStockSnapshot: 22, totalPhysicalStock: null, reservedStock: 0, storeAssociations: [] };

  await act(async () => root.render(
    <InitialStockCountModal
      isOpen
      onClose={vi.fn()}
      item={item}
      mode="shared"
      stores={[]}
      onSaveCount={vi.fn().mockResolvedValue(undefined)}
      onContinueModeSwitchPreparation={continuePreparation}
    />
  ));
  const input = host.querySelector('input[type="number"]') as HTMLInputElement;
  await act(async () => {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
    setter.call(input, '22');
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Proceed to Review')) as HTMLButtonElement).click());
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Confirm & Save Physical Count')) as HTMLButtonElement).click());

  const continueButton = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Continue Mode Switch Preparation')) as HTMLButtonElement;
  expect(continueButton).toBeTruthy();
  await act(async () => continueButton.click());
  expect(continuePreparation).toHaveBeenCalledOnce();

  await act(async () => root.unmount());
  host.remove();
});
