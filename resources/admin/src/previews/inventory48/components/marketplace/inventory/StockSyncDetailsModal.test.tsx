// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot, Root} from 'react-dom/client';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {StockSyncDetailsModal} from './StockSyncDetailsModal';

let host:HTMLDivElement;
let root:Root;
beforeEach(()=>{
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  host=document.createElement('div');document.body.append(host);root=createRoot(host);
});
afterEach(async()=>{await act(async()=>root.unmount());host.remove()});

const state:any={sku:'SKU-1',storeId:'9',status:'select_facility',jwAvailableStock:null,
  marketplaceReportedStock:null,difference:null,lastConfirmedSyncAt:null,lastCheckedAt:'',
  canCheck:false,canResync:false};
const base:any={isOpen:true,onClose:vi.fn(),item:{masterSku:'SKU-1',productName:'Product'},
  store:{id:'9',platform:'lazada',name:'Lazada'},syncInfo:state};
async function render(props:any){await act(async()=>root.render(<StockSyncDetailsModal {...base} {...props}/>))}

describe('warehouse-aware stock sync details',()=>{
  it('requires an exact warehouse before showing provider actions',async()=>{
    const select=vi.fn().mockResolvedValue(undefined);
    await render({facilities:[{id:4,name:'MY warehouse',providerId:'MY-WH-4'}],onSelectFacility:select});
    expect(host.textContent).toContain('Select a warehouse to inspect exact stock');
    expect(host.textContent).toContain('Not counted');
    expect(host.textContent).not.toContain('Resync Stock');
    const control=host.querySelector('#sync-target-facility') as HTMLSelectElement;
    await act(async()=>{control.value='4';control.dispatchEvent(new Event('change',{bubbles:true}))});
    expect(select).toHaveBeenCalledWith(4);
  });

  it('shows mapping recovery without a fabricated marketplace quantity',async()=>{
    const mapping=vi.fn();
    await render({syncInfo:{...state,status:'sku_not_mapped',jwAvailableStock:19},onNavigateToSkuMapping:mapping});
    expect(host.textContent).toContain('Unknown');
    const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.includes('Go to SKU Mapping'))!;
    await act(async()=>button.click());
    expect(mapping).toHaveBeenCalledWith('SKU-1','9');
  });

  it('keeps provider actions unavailable while warehouse verification is pending',async()=>{
    await render({syncInfo:{...state,status:'verification_pending',failureReason:'warehouse.error_not_in_whitelist'}});
    expect(host.textContent).toContain('warehouse.error_not_in_whitelist');
    expect(host.textContent).not.toContain('Resync Stock');
    expect(host.textContent).not.toContain('Check Status');
  });

  it('does not call unknown stock a mismatch before First Count',async()=>{
    await render({syncInfo:{...state,status:'not_synced',failureReason:'Complete First Count.'}});
    expect(host.textContent).toContain('Marketplace stock has not been reconciled');
    expect(host.textContent).not.toContain('Marketplace stock does not match JW inventory');
  });

  it('offers readback for awaiting confirmation and retry only when permitted',async()=>{
    const check=vi.fn().mockResolvedValue({...state,status:'synced',jwAvailableStock:8,
      marketplaceReportedStock:8,difference:0,canCheck:true});
    await render({syncInfo:{...state,status:'awaiting_confirmation',jwAvailableStock:8,canCheck:true},onCheckStatus:check});
    expect(host.textContent).not.toContain('Resync Stock');
    const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.trim()==='Check Status')!;
    await act(async()=>button.click());
    expect(check).toHaveBeenCalledOnce();
  });

  it('routes disconnected stores to Connect Store',async()=>{
    const connect=vi.fn();
    await render({syncInfo:{...state,status:'auth_expired'},onNavigateToStoreSettings:connect});
    const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.trim()==='Reconnect Store')!;
    await act(async()=>button.click());
    expect(connect).toHaveBeenCalledWith('9');
  });

  it('does not keep a historical synced badge after test access expires during check',async()=>{
    const check=vi.fn().mockRejectedValue(new Error('Lazada test access expired. Renew Test Access in Connect Store, then check this warehouse again.'));
    await render({syncInfo:{...state,status:'synced',jwAvailableStock:0,marketplaceReportedStock:0,
      difference:0,canCheck:true},onCheckStatus:check});
    const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.trim()==='Check Status')!;
    await act(async()=>button.click());
    expect(host.textContent).toContain('Store authorization expired');
    expect(host.textContent).toContain('Previous quantities and sync times are historical');
    expect(host.textContent).not.toContain('Stock is synchronized');
  });
});
