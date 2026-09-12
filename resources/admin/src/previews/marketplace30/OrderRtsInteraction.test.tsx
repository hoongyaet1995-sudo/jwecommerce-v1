// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {it, expect, vi} from 'vitest';
import {OrderInspectionDrawer} from './OrderInspectionDrawer';
import type {MarketplaceOrderDetail} from '../../integration/marketplaceOperations';
import {marketplaceRequest} from '../../integration/marketplaceData';
vi.mock('../../integration/marketplaceData', () => ({marketplaceRequest: vi.fn()}));

it('packs once without submitting RTS and distinguishes snapshot failure', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const request=vi.mocked(marketplaceRequest);
  request.mockResolvedValueOnce({status:'succeeded',snapshot_refreshed:false});
  const order: MarketplaceOrderDetail={id:7,store_id:3,external_order_id:'100',platform_status:'pending',
    platform:'lazada',shop_name:'Test',currency:'MYR',total_amount:'10',platform_updated_at:100,fetched_at:'',
    lines:[],shipping_carrier:null,packages:[],pack_item_ids:['101','102'],rts_packages:[],
    capabilities:{fulfillment:false,airway_bill:false}};
  const container=document.createElement('div');const root=createRoot(container);
  try {
    await act(async()=>root.render(<OrderInspectionDrawer order={order} onClose={()=>{}}/>));
    const button=Array.from(container.querySelectorAll('button')).find(b=>b.textContent==='Pack Order')!;
    await act(async()=>{button.click();button.click();});
    expect(request).toHaveBeenCalledTimes(1);
    expect(request).toHaveBeenCalledWith('/orders/7/pack','POST',{requestKey:expect.any(String),itemIds:['101','102'],allocationType:'TFS'});
    expect(button.textContent).toContain('Packed; order sync failed.');
    expect(button.disabled).toBe(true);
  } finally {await act(async()=>root.unmount());vi.unstubAllGlobals();vi.clearAllMocks();}
});

it.each([['rts', true], ['rts', false], ['pack', true], ['pack', false]] as const)('reconciles %s without resending, confirmed=%s', async (operation, confirmed) => {
  const label = operation === 'pack' ? 'Packing' : 'RTS';
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const request = vi.mocked(marketplaceRequest);
  request.mockResolvedValueOnce({confirmed});
  const order: MarketplaceOrderDetail = {id: 7, store_id: 3, external_order_id: '100', platform_status: 'packed',
    platform: 'lazada', shop_name: 'Test', currency: 'MYR', total_amount: '10', platform_updated_at: 100,
    fetched_at: '', lines: [], shipping_carrier: null, packages: [], rts_packages: [],
    fulfillment_attempts: [{id: 11, operation, status: 'uncertain', created_at: '2026-09-08T08:00:00Z', updated_at: '2026-09-08T08:00:00Z'}],
    capabilities: {fulfillment: false, airway_bill: false}};
  const container = document.createElement('div'); const root = createRoot(container);
  try {
    await act(async () => root.render(<OrderInspectionDrawer order={order} onClose={() => {}}/>));
    expect(container.textContent).toContain('Saved fulfillment task #11');
    const button = Array.from(container.querySelectorAll('button')).find(b => b.textContent === `Check ${label} Status`)!;
    expect(button.disabled).toBe(false);
    await act(async () => {button.click(); button.click();});
    expect(request).toHaveBeenCalledTimes(1);
    expect(request).toHaveBeenCalledWith('/orders/7/fulfillment-attempts/11/reconcile', 'POST');
    expect(button.textContent).toContain(confirmed ? `${label} confirmed.` : `${label} still unconfirmed.`);
    expect(button.disabled).toBe(true);
  } finally {await act(async () => root.unmount()); vi.unstubAllGlobals(); vi.clearAllMocks();}
});

it('submits eligible packages once and keeps an uncertain outcome disabled', async () => {
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  const request = vi.mocked(marketplaceRequest);
  request.mockRejectedValueOnce(new Error('Connection lost'));
  const order: MarketplaceOrderDetail = {id: 7, store_id: 3, external_order_id: '100', platform_status: 'packed',
    platform: 'lazada', shop_name: 'Test store', currency: 'MYR', total_amount: '10', platform_updated_at: 100,
    fetched_at: '', lines: [], shipping_carrier: null, packages: [], rts_packages: ['PK1'],
    capabilities: {fulfillment: true, airway_bill: false}};
  const container = document.createElement('div'); const root = createRoot(container);
  try {
    await act(async () => root.render(<OrderInspectionDrawer order={order} onClose={() => {}}/>));
    const button = Array.from(container.querySelectorAll('button')).find(b => b.textContent === 'Dispatch')!;
    await act(async () => {button.click(); button.click();});
    expect(request).toHaveBeenCalledTimes(1);
    expect(request).toHaveBeenCalledWith('/orders/7/ready-to-ship', 'POST', {packages: ['PK1'], requestKey: expect.any(String)});
    expect(button.disabled).toBe(true);
    expect(button.textContent).toContain('reconcile before retrying');
  } finally {await act(async () => root.unmount()); vi.unstubAllGlobals(); vi.clearAllMocks();}
});
