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
    await render({facilities:[{id:4,name:'MY warehouse',providerId:'MY-WH-4'},{id:5,name:'Second warehouse',providerId:'MY-WH-5'}],onSelectFacility:select});
    expect(host.textContent).toContain('Which stock location?');
    expect(host.textContent).not.toContain('Resync Stock');
    const control=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('MY warehouse'))!;
    await act(async()=>{control.click()});
    expect(select).toHaveBeenCalledWith(4);
  });

  it('opens an exact warehouse directly and returns to the warehouse list',async()=>{
    const facilities=[{id:4,name:'MY warehouse',providerId:'MY-WH-4'},{id:5,name:'Second warehouse',providerId:'MY-WH-5'}];
    await render({facilities,selectedFacilityId:4,syncInfo:{...state,status:'sync_failed',jwAvailableStock:20,marketplaceReportedStock:10,difference:10,canResync:true}});
    expect(host.textContent).toContain('MY warehouse · MY-WH-4');
    expect(host.textContent).not.toContain('Which stock location?');
    const back=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('All warehouses'))!;
    await act(async()=>back.click());
    expect(host.textContent).toContain('Which stock location?');
    expect(host.textContent).toContain('Second warehouse');
  });

  it('shows mapped stores as a separate step without offering provider writes while loading',async()=>{
    const second={id:'10',platform:'shopee',name:'Shopee MY'};
    const selectStore=vi.fn().mockResolvedValue(undefined);
    await render({availableStores:[base.store,second],onSelectStore:selectStore});
    const allStores=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('All stores'))!;
    await act(async()=>allStores.click());
    expect(host.textContent).toContain('Which marketplace?');
    const target=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Shopee MY'))!;
    await act(async()=>target.click());
    expect(selectStore).toHaveBeenCalledWith(second);
    await render({isLoadingTopology:true,syncInfo:{...state,status:'sync_failed',jwAvailableStock:5,canResync:true}});
    expect(host.textContent).toContain('Loading store and warehouse stock status');
    expect(host.textContent).not.toContain('Resync Stock');
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
    expect(host.textContent).not.toContain('warehouse.error_not_in_whitelist');
    expect(host.textContent).not.toContain('Resync Stock');
    expect(host.textContent).not.toContain('Check Status');
  });

  it('keeps technical provider failures out of the resync flow',async()=>{
    const resync=vi.fn().mockRejectedValue(new Error('IllegalWarehouse: provider internal detail'));
    await render({syncInfo:{...state,status:'sync_failed',jwAvailableStock:5,
      marketplaceReportedStock:0,difference:5,canResync:true,
      failureReason:'IllegalWarehouse: provider internal detail'},onResync:resync});
    expect(host.textContent).not.toContain('IllegalWarehouse');
    const retry=[...host.querySelectorAll('button')].find(button=>button.textContent?.trim()==='Resync Stock')!;
    await act(async()=>retry.click());
    const confirm=[...host.querySelectorAll('button')].find(button=>button.textContent?.includes('Resync 5 Units'))!;
    await act(async()=>confirm.click());
    expect(resync).toHaveBeenCalledOnce();
    expect(host.textContent).not.toContain('IllegalWarehouse');
    expect(host.textContent).toContain('Resync Stock');
  });

  it('does not call unknown stock a mismatch before First Count',async()=>{
    await render({syncInfo:{...state,status:'not_synced',failureReason:'Complete First Count.'}});
    expect(host.textContent).toContain('Marketplace stock has not been reconciled');
    expect(host.textContent).not.toContain('Marketplace stock does not match JW inventory');
  });

  it('opens First Count for the selected uncounted warehouse instead of offering resync',async()=>{
    const firstCount=vi.fn();
    await render({syncInfo:{...state,status:'not_synced'},selectedFacilityId:8,onFirstCount:firstCount});
    const button=[...host.querySelectorAll('button')].find(b=>b.textContent?.trim()==='First Count')!;
    expect(button).toBeTruthy();
    expect(button.disabled).toBe(false);
    expect(host.textContent).not.toContain('Resync Stock');
    await act(async()=>button.click());
    expect(firstCount).toHaveBeenCalledOnce();
  });

  it('keeps resync for a counted warehouse that has not synced',async()=>{
    await render({syncInfo:{...state,status:'not_synced',jwAvailableStock:5,canResync:true},selectedFacilityId:8});
    expect([...host.querySelectorAll('button')].some(b=>b.textContent?.trim()==='Resync Stock')).toBe(true);
    expect([...host.querySelectorAll('button')].some(b=>b.textContent?.trim()==='First Count')).toBe(false);
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
