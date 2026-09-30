// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot,type Root} from 'react-dom/client';
import {afterEach,beforeEach,expect,it,vi} from 'vitest';
import {SharedPoolStockActionModal} from './SharedPoolStockActionModal';
import {storeInventoryApi} from '../../../../../integration/storeInventory';

let host:HTMLDivElement,root:Root;
const item={id:'variant:7',variantId:7,masterSku:'SKU-7',productName:'Product',currentStock:50,imageUrl:''} as any;
beforeEach(()=>{sessionStorage.clear();localStorage.clear();(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;host=document.createElement('div');document.body.append(host);root=createRoot(host)});
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.restoreAllMocks()});
const click=async(text:string)=>{const button=[...host.querySelectorAll('button')].find(node=>node.textContent?.trim()===text);expect(button,text).toBeDefined();await act(async()=>button!.click())};
const input=async(value:string)=>{const field=host.querySelector<HTMLInputElement>('input[type=number]')!;await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(field,value);field.dispatchEvent(new Event('input',{bubbles:true}))})};

it('reviews Shared Pool audit as an absolute total and confirms once',async()=>{
  const draft=vi.spyOn(storeInventoryApi,'reviewDraft').mockResolvedValue({id:'review-7',before:{current_stock:50},after:{current_stock:47}});
  const confirm=vi.spyOn(storeInventoryApi,'confirmReview').mockResolvedValue({operation_id:'operation-7',external_stages:[]});
  vi.spyOn(storeInventoryApi,'operation').mockResolvedValue({operation_id:'operation-7',external_stages:[]});
  const saved=vi.fn(async()=>{});
  await act(async()=>root.render(<SharedPoolStockActionModal item={item} initialAction="intake" onClose={()=>{}} onSaved={saved}/>));
  await click('Audit Count Adjustment');await input('47');await click('Review Adjustment');
  expect(draft).toHaveBeenCalledWith('correction',expect.objectContaining({variant_id:7,confirmed_total:47,channel_key:'website'}));
  expect(host.textContent).toContain('50 → 47');
  await click('Confirm Stock Audit Adjustment');
  expect(confirm).toHaveBeenCalledTimes(1);expect(saved).toHaveBeenCalledTimes(1);
  expect(host.textContent).toContain('JW Inventory Ledger · Done');
});

it('retains the intake confirmation identity while pending and after a lost response',async()=>{
  vi.spyOn(storeInventoryApi,'reviewDraft').mockResolvedValue({id:'receipt-review',before:{current_stock:50},after:{current_stock:60}});
  let rejectRequest!:(error:Error)=>void;
  const confirm=vi.spyOn(storeInventoryApi,'confirmReview').mockImplementationOnce(()=>new Promise((_,reject)=>{rejectRequest=reject})).mockResolvedValue({operation_id:'saved',external_stages:[]});
  vi.spyOn(storeInventoryApi,'operation').mockResolvedValue({operation_id:'saved',external_stages:[]});
  const close=vi.fn();
  await act(async()=>root.render(<SharedPoolStockActionModal item={item} initialAction="intake" onClose={close} onSaved={async()=>{}}/>));
  await input('10');await click('Review Intake');await click('Confirm Actual Stock Intake');
  const edit=[...host.querySelectorAll('button')].find(node=>node.textContent==='Edit')!;
  expect(edit.disabled).toBe(true);
  expect(host.querySelector<HTMLButtonElement>('button[aria-label=Close]')!.disabled).toBe(true);
  const identity=confirm.mock.calls[0];
  expect(localStorage.length).toBe(1);
  await act(async()=>rejectRequest(new Error('Response lost')));
  expect(edit.disabled).toBe(true);
  expect(host.querySelector<HTMLButtonElement>('button[aria-label=Close]')!.disabled).toBe(true);
  await act(async()=>root.unmount());root=createRoot(host);
  await act(async()=>root.render(<SharedPoolStockActionModal item={item} initialAction="intake" onClose={close} onSaved={async()=>{}}/>));
  await click('Confirm Actual Stock Intake');
  expect(confirm.mock.calls[1]).toEqual(identity);
  expect(close).not.toHaveBeenCalled();
});

it('allows a fresh review after a definitive stale-balance rejection',async()=>{
  vi.spyOn(storeInventoryApi,'reviewDraft').mockResolvedValue({id:'stale',before:{current_stock:50},after:{current_stock:60}});
  vi.spyOn(storeInventoryApi,'confirmReview').mockRejectedValue(Object.assign(new Error('Inventory changed'),{status:409,code:'balance_changed'}));
  await act(async()=>root.render(<SharedPoolStockActionModal item={item} initialAction="intake" onClose={()=>{}} onSaved={async()=>{}}/>));
  await input('10');await click('Review Intake');await click('Confirm Actual Stock Intake');
  expect([...host.querySelectorAll('button')].find(node=>node.textContent==='Edit')!.disabled).toBe(false);
  expect(localStorage.length).toBe(0);
});
