// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot, Root} from 'react-dom/client';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {StockSyncDetailsModal} from './StockSyncDetailsModal';

const initial:any={sku:'SKU-1',storeId:'1',status:'not_synced',jwAvailableStock:22,marketplaceReportedStock:null,difference:null,lastConfirmedSyncAt:null,lastCheckedAt:null,canCheck:true,canResync:true};
let host:HTMLDivElement;
let root:Root;
beforeEach(() => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  host=document.createElement('div');document.body.append(host);root=createRoot(host);
});
afterEach(async () => {await act(async()=>root.unmount());host.remove();});
async function mount(overrides:any={}) {
  const props:any={isOpen:true,onClose:vi.fn(),item:{masterSku:'SKU-1',productName:'Product'},store:{id:'1',platform:'shopee',name:'Shopee'},syncInfo:initial,onRefresh:vi.fn().mockResolvedValue(initial),onCheck:vi.fn().mockResolvedValue(initial),onResync:vi.fn(),...overrides};
  await act(async()=>{root.render(<StockSyncDetailsModal {...props}/>);});
  return props;
}
async function click(label:string) {
  const button=[...host.querySelectorAll('button')].find(button=>button.textContent?.trim()===label);
  expect(button, label).toBeDefined();
  await act(async()=>{button!.click();});
}
describe('real stock recovery transitions',()=>{
  it('checks unknown stock without making a write or fabricating zero',async()=>{
    const props=await mount();
    expect(host.textContent).toContain('Unknown');
    await click('Check Status');
    expect(props.onCheck).toHaveBeenCalledOnce();
    expect(props.onResync).not.toHaveBeenCalled();
    expect(host.textContent).not.toContain('Stock Sync Successful');
  });
  it('does not show success when the provider has not confirmed',async()=>{
    const uncertain={...initial,status:'awaiting_confirmation',canResync:false};
    const props=await mount({onResync:vi.fn().mockResolvedValue(uncertain),onCheck:vi.fn().mockResolvedValue(uncertain)});
    await click('Resync Stock');await click('Resync 22 Units');
    expect(props.onResync).toHaveBeenCalledOnce();
    expect(props.onCheck).toHaveBeenCalledOnce();
    expect(host.textContent).toContain('Awaiting Confirmation');
    expect([...host.querySelectorAll('button')].some(b=>b.textContent?.trim()==='Resync Stock')).toBe(false);
  });
  it('keeps the same idempotency key after a lost response',async()=>{
    const props=await mount({onResync:vi.fn().mockRejectedValue(new Error('Connection lost'))});
    await click('Resync Stock');await click('Resync 22 Units');
    expect(host.textContent).toContain('Connection lost');
    await click('Resync Stock');await click('Resync 22 Units');
    expect(props.onResync.mock.calls[0][0]).toBe(props.onResync.mock.calls[1][0]);
  });
  it('disables resync when backend readiness rejects the action',async()=>{
    const props=await mount({syncInfo:{...initial,status:'sync_failed',canResync:false}});
    const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.trim()==='Resync Stock')!;
    expect(button.disabled).toBe(true);
    await act(async()=>button.click());
    expect(props.onResync).not.toHaveBeenCalled();
  });
});
