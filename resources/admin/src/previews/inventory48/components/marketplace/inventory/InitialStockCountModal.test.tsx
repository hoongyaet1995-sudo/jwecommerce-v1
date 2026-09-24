// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot, Root} from 'react-dom/client';
import {afterEach, beforeEach, expect, it, vi} from 'vitest';
import {InitialStockCountModal} from './InitialStockCountModal';

let host:HTMLDivElement;
let root:Root;
beforeEach(()=>{
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  host=document.createElement('div');document.body.append(host);root=createRoot(host);
});
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.restoreAllMocks()});

it('conserves an independent verified total across mapped warehouse inputs',async()=>{
  vi.stubGlobal('fetch',vi.fn(async (input:RequestInfo|URL)=>({ok:true,json:async()=>String(input).endsWith('/sync/check')
    ? {marketplace_reported_stock:6} : String(input).includes('/stores/4/')
      ? {status:'verified',topology:'no_warehouse',facilities:[]} : {status:'verified',topology:'multi_warehouse',facilities:[
    {id:7,name:'Warehouse A',provider_id:'WH-A',inventory_warehouse_id:19,counted:false,physical:null,reserved:0,available:null},
    {id:8,name:'Warehouse B',provider_id:'WH-B',inventory_warehouse_id:20,counted:false,physical:null,reserved:0,available:null},
  ]}} as Response)));
  const save=vi.fn(async()=>{});
  await act(async()=>root.render(<InitialStockCountModal isOpen onClose={()=>{}}
    item={{id:'55',masterSku:'TEST-1',productName:'Test product',variantId:55,mappedStoreIds:['3'],
      storeAssociations:[{connectionId:'3',storeName:'Lazada Test',platform:'lazada',channelStockSnapshot:11}]} as any}
    mode="independent" stores={[{id:'3',name:'Lazada Test',platform:'lazada'} as any,
      {id:'4',name:'Unmapped Store',platform:'shopee'} as any]}
    initialTargetStoreId="3" initialTargetWarehouseId="7" onSaveCount={save} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  expect(host.textContent).toContain('Lazada Test');
  expect(host.textContent).toContain('12 units (2 WH)');
  expect(host.textContent).toContain('Live Conservation Summary');
  expect(host.textContent).toContain('Allocate Stock');
  expect(host.querySelectorAll('input[aria-label^="Lazada Test"][aria-label$="first count"]')).toHaveLength(2);
  expect(host.textContent).toContain('Warehouse Awarehouse_code: WH-A');
  const reference = [...host.querySelectorAll('span')].find(node=>node.textContent==='External Platform Reference Quantities (For Reference Only)')?.parentElement?.parentElement?.parentElement;
  expect(reference?.textContent).not.toContain('Unmapped Store');
  expect(host.querySelector('input[placeholder^="Enter verified physical units"]')).toHaveProperty('value','');
  const enter=async(input:HTMLInputElement,value:string)=>act(async()=>{
    const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!;
    setter.call(input,value);input.dispatchEvent(new Event('input',{bubbles:true}));
  });
  await enter(host.querySelector('input[placeholder^="Enter verified physical units"]') as HTMLInputElement,'10');
  await enter(host.querySelector('input[aria-label="Lazada Test Warehouse A first count"]') as HTMLInputElement,'7');
  await enter(host.querySelector('input[aria-label="Lazada Test Warehouse B first count"]') as HTMLInputElement,'3');
  expect(host.textContent).toContain('Verified Total');
  expect(host.textContent).toContain('Status: Balanced');
  const click=async(label:string)=>{
    const button=[...host.querySelectorAll('button')].find(node=>node.textContent?.includes(label));
    expect(button).toBeTruthy();
    await act(async()=>button!.click());
  };
  await click('Proceed to Review');
  await click('Confirm & Allocate Stock');
  expect(save).toHaveBeenCalledWith(expect.objectContaining({
    sku:'TEST-1',physicalCount:10,
    batchAllocations:expect.objectContaining({'facility:3:7':7,'facility:3:8':3}),
  }));
});

it('preserves a warehouse draft when store data refreshes', async () => {
  const fetchMock=vi.fn(async (_input:RequestInfo|URL) => ({ok:true,json:async()=>({status:'verified',topology:'multi_warehouse',facilities:[
    {id:7,name:'Warehouse A',provider_id:'WH-A',inventory_warehouse_id:19,counted:false,physical:null,reserved:0,available:null},
  ]})} as Response));
  vi.stubGlobal('fetch', fetchMock);
  const item={id:'55',masterSku:'TEST-1',productName:'Test product',variantId:55,mappedStoreIds:['3']} as any;
  const render=(name:string)=>root.render(<InitialStockCountModal isOpen onClose={()=>{}} item={item}
    mode="independent" stores={[{id:'3',name,platform:'lazada'} as any]}
    initialTargetStoreId="3" initialTargetWarehouseId="7" onSaveCount={vi.fn()} />);
  await act(async()=>render('Lazada Test'));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  const input=host.querySelector('input[aria-label="Lazada Test Warehouse A first count"]') as HTMLInputElement;
  await act(async()=>{
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,'12');
    input.dispatchEvent(new Event('input',{bubbles:true}));
  });
  expect(host.querySelector('input[placeholder^="Enter verified physical units"]')).toHaveProperty('value','');
  const total=host.querySelector('input[placeholder^="Enter verified physical units"]') as HTMLInputElement;
  await act(async()=>{
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(total,'20');
    total.dispatchEvent(new Event('input',{bubbles:true}));
  });
  const facilityReads=fetchMock.mock.calls.filter(([url])=>String(url).endsWith('/facilities')).length;
  await act(async()=>render('Lazada Test'));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  expect(fetchMock.mock.calls.filter(([url])=>String(url).endsWith('/facilities'))).toHaveLength(facilityReads);
  expect(host.querySelector('input[aria-label="Lazada Test Warehouse A first count"]')).toHaveProperty('value','12');
  expect(host.querySelector('input[placeholder^="Enter verified physical units"]')).toHaveProperty('value','20');
});
