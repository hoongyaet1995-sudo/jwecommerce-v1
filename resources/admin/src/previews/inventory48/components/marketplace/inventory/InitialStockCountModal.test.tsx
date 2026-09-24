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

it('lists mapped stores as references while keeping a targeted facility count blank',async()=>{
  vi.stubGlobal('fetch',vi.fn(async (input:RequestInfo|URL)=>({ok:true,json:async()=>String(input).endsWith('/sync')
    ? {marketplace_reported_stock:6} : {status:'verified',topology:'multi_warehouse',facilities:[
    {id:7,name:'Warehouse A',provider_id:'WH-A',inventory_warehouse_id:19,counted:false,physical:null,reserved:0,available:null},
    {id:8,name:'Warehouse B',provider_id:'WH-B',inventory_warehouse_id:20,counted:false,physical:null,reserved:0,available:null},
  ]}} as Response)));
  await act(async()=>root.render(<InitialStockCountModal isOpen onClose={()=>{}}
    item={{id:'55',masterSku:'TEST-1',productName:'Test product',variantId:55,mappedStoreIds:['3'],
      storeAssociations:[{connectionId:'3',storeName:'Lazada Test',platform:'lazada',channelStockSnapshot:11}]} as any}
    mode="independent" stores={[{id:'3',name:'Lazada Test',platform:'lazada'} as any,
      {id:'4',name:'Unmapped Store',platform:'shopee'} as any]}
    initialTargetStoreId="3" initialTargetWarehouseId="7" onSaveCount={vi.fn()} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  expect(host.textContent).toContain('Lazada Test');
  expect(host.textContent).toContain('6 units');
  expect(host.textContent).toContain('Warehouse A (warehouse_code: WH-A)');
  const reference = [...host.querySelectorAll('span')].find(node=>node.textContent==='External Platform Reference Quantities (For Reference Only)')?.parentElement?.parentElement?.parentElement;
  expect(reference?.textContent).not.toContain('Unmapped Store');
  expect(host.querySelector('input[placeholder^="Enter verified physical units"]')).toHaveProperty('value','');
});
