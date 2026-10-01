// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
vi.mock('../../../../integration/storeInventory',()=>({storeInventoryApi:{facilities:vi.fn()}}));
import {storeInventoryApi} from '../../../../integration/storeInventory';
import {SharedPoolStoreCard, sharedDestinationReadback} from './SharedPoolStoreCard';

const item:any={id:'21',variantId:7,masterSku:'SKU-7',currentStock:0,totalPhysicalStock:0,physicalStockStatus:'verified',currentStockVersion:3};
const store:any={id:'11',platform:'lazada',nickname:'Mapped Lazada',connectionState:'connected',isManagedByJw:true};
const history=[{detailsPayload:{external_stages:[{id:'t',inventory_balance_id:21,product_variant_id:7,marketplace_store_id:11,marketplace_store_facility_id:5,balance_version:3,target_quantity:0,status:'confirmed',created_at:'2026-09-30',provider_receipt:JSON.stringify({observed_quantity:0,provider_location_id:'W5'})}]}}];
it('uses exact Shared receipts, retains zero, and never infers or sums a destination quantity',()=>{
 expect(sharedDestinationReadback(item,store,{id:5,provider_id:'W5'},history)).toMatchObject({quantity:0,status:'Synced'});
 expect(sharedDestinationReadback(item,store,{id:6,provider_id:'W6'},history)).toMatchObject({quantity:null});
 expect(sharedDestinationReadback({...item,currentStockVersion:4},store,{id:5,provider_id:'W5'},history).status).not.toBe('Synced');
 expect(sharedDestinationReadback(item,store,{id:5,provider_id:'wrong'},history).quantity).toBeNull();
});
it('keeps the card read-only and exposes individual warehouse detail on focus and expansion',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 vi.mocked(storeInventoryApi.facilities).mockResolvedValue({status:'verified',topology:'multi_warehouse',facilities:[{id:5,name:'Main warehouse',provider_id:'W5',shared_pool_takeover_enabled:true},{id:6,name:'Other warehouse',provider_id:'W6',shared_pool_takeover_enabled:false}]});
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<SharedPoolStoreCard item={item} store={store} history={history} syncState="not_synced" />));
  expect(host.textContent).toContain('2 warehouses');
  expect(host.querySelector('[role="button"]')).toBeNull();
  const button=host.querySelector('button')!;
  await act(async()=>button.focus());
  expect(host.textContent).toContain('Main warehouse');expect(host.textContent).toContain('0 units');
  expect(host.textContent).toContain('Unknown');expect(host.textContent).toContain('Takeover paused');
  expect(host.textContent).not.toContain('Check Stock');expect(host.textContent).not.toContain('Resync');
 }finally{await act(async()=>root.unmount());host.remove()}
});

it('distinguishes verified store-level stock from discovery failure and refreshes after a new snapshot',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 vi.mocked(storeInventoryApi.facilities).mockResolvedValueOnce({status:'verified',topology:'no_warehouse',facilities:[]})
  .mockRejectedValueOnce(new Error('Warehouse discovery failed'));
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<SharedPoolStoreCard item={item} store={store} history={[]} syncState="not_synced" />));
  expect(host.textContent).toContain('Unknown');
  await act(async()=>host.querySelector('button')!.focus());
  expect(host.textContent).toContain('No separate warehouses');
  await act(async()=>root.render(<SharedPoolStoreCard item={{...item}} store={store} history={[]} syncState="not_synced" />));
  expect(host.textContent).toContain('Warehouse discovery failed');
  expect(host.textContent).not.toContain('No separate warehouses');
 }finally{await act(async()=>root.unmount());host.remove()}
});

it('retains the exact failure reason without claiming the ledger or another warehouse was reversed',()=>{
 const failed=[{detailsPayload:{external_stages:[{...history[0].detailsPayload.external_stages[0],status:'failed',provider_receipt:JSON.stringify({code:'provider_unavailable',message:'Marketplace temporarily unavailable'})}]}}];
 expect(sharedDestinationReadback(item,store,{id:5,provider_id:'W5'},failed)).toMatchObject({quantity:null,status:'Sync failed',reason:'Marketplace temporarily unavailable'});
});

it('reads a verified store-level destination with its persisted provider location identity',()=>{
 const direct=[{detailsPayload:{external_stages:[{...history[0].detailsPayload.external_stages[0],marketplace_store_facility_id:null,provider_location_id:'DEFAULT',provider_receipt:JSON.stringify({observed_quantity:0,provider_location_id:'DEFAULT'})}]}}];
 expect(sharedDestinationReadback(item,store,null,direct)).toMatchObject({quantity:0,status:'Synced'});
});
