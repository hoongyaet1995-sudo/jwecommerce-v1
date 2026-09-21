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

it('allocates one verified total across mapped independent stores and blocks unmapped inputs', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const item: any = { id: '1', variantId: 9, masterSku: 'ALLOC-1', productName: 'Allocate me', variantName: 'Default', totalPhysicalStock: 10,
    reservedStock: 0, mappedStoreIds: ['11'], storeAssociations: [] };
  const stores: any[] = [
    {id:'website',name:'JW Ecom Storefront',nickname:'JW Storefront',platform:'webstore',platformDisplayName:'JW Storefront',connectionState:'connected'},
    {id:'11',name:'Mapped Shopee',nickname:'Mapped Shopee',platform:'shopee',platformDisplayName:'Shopee',connectionState:'connected'},
    {id:'12',name:'Unmapped Lazada',nickname:'Unmapped Lazada',platform:'lazada',platformDisplayName:'Lazada',connectionState:'connected'},
  ];

  await act(async () => root.render(<InitialStockCountModal isOpen onClose={vi.fn()} item={item} mode="independent" stores={stores} onSaveCount={vi.fn()} />));

  expect(host.textContent).toContain('Allocate Stock');
  expect(host.textContent).toContain('SKU Not Mapped');
  expect(host.textContent).not.toContain('Demo Scenarios');
  const unmappedRow = Array.from(host.querySelectorAll('div')).find(node => node.textContent?.includes('Unmapped Lazada') && node.textContent?.includes('SKU Not Mapped'))!;
  expect((unmappedRow.querySelector('input') as HTMLInputElement).disabled).toBe(true);
  const assignRemaining = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Assign Remaining')) as HTMLButtonElement;
  await act(async () => assignRemaining.click());
  const proceed = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Proceed to Review')) as HTMLButtonElement;
  expect(proceed.disabled).toBe(false);

  await act(async () => root.unmount());
  host.remove();
});

it('allows an independently verified zero count when every store allocation is zero', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div'); document.body.append(host); const root = createRoot(host);
  const item: any = { id: '2', variantId: 10, masterSku: 'EMPTY-1', productName: 'Empty', variantName: 'Default', totalPhysicalStock: null, reservedStock: 0, mappedStoreIds: [], storeAssociations: [] };
  const stores: any[] = [{id:'website',name:'JW Ecom Storefront',nickname:'JW Storefront',platform:'webstore',platformDisplayName:'JW Storefront',connectionState:'connected'}];
  await act(async () => root.render(<InitialStockCountModal isOpen onClose={vi.fn()} item={item} mode="independent" stores={stores} onSaveCount={vi.fn()} />));
  const inputs = Array.from(host.querySelectorAll('input[type="number"]')) as HTMLInputElement[];
  await act(async () => { for (const input of inputs) { const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!; setter.call(input,'0'); input.dispatchEvent(new Event('input',{bubbles:true})); } });
  const proceed = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Proceed to Review')) as HTMLButtonElement;
  expect(proceed.disabled).toBe(false);
  await act(async () => root.unmount()); host.remove();
});

it('keeps the success receipt open when saving refreshes the store collection', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div'); document.body.append(host); const root = createRoot(host);
  const item: any = { id: '3', variantId: 11, masterSku: 'ALLOC-REFRESH', productName: 'Refresh allocation', variantName: 'Default', totalPhysicalStock: 22, reservedStock: 0, mappedStoreIds: [], storeAssociations: [] };
  const baseStores: any[] = [{id:'website',name:'JW Ecom Storefront',nickname:'JW Storefront',platform:'webstore',platformDisplayName:'JW Storefront',connectionState:'connected'}];

  const close = vi.fn();
  const save = vi.fn().mockResolvedValue({ledger_status:'applied',external_stages:[]});
  await act(async () => root.render(<InitialStockCountModal isOpen onClose={close} item={item} mode="independent" stores={baseStores} onSaveCount={save} />));
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Assign Remaining')) as HTMLButtonElement).click());
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Proceed to Review')) as HTMLButtonElement).click());
  await act(async () => (Array.from(host.querySelectorAll('button')).find(button => button.textContent?.includes('Confirm & Allocate Stock')) as HTMLButtonElement).click());

  expect(host.textContent).toContain('Stock Allocation Completed');
  expect(host.textContent).toContain('JW Central Inventory Ledger: Updated & Conserved');
  await act(async () => root.render(<InitialStockCountModal isOpen onClose={close} item={item} mode="independent" stores={[...baseStores]} onSaveCount={save} />));
  expect(host.textContent).toContain('Stock Allocation Completed');

  await act(async () => root.unmount()); host.remove();
});
