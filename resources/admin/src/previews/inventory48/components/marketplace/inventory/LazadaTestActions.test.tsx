// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot, Root} from 'react-dom/client';
import {afterEach, beforeEach, expect, it, vi} from 'vitest';
import {ManageStockDrawer} from './ManageStockDrawer';

let host:HTMLDivElement;
let root:Root;
beforeEach(()=>{
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  host=document.createElement('div');document.body.append(host);root=createRoot(host);
});
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.restoreAllMocks()});

it('hides First Count when server reports a competing legacy balance',async()=>{
  vi.stubGlobal('fetch',vi.fn(async (input:RequestInfo|URL)=>{
    const url=String(input);
    const body=url.includes('test-facility-acceptance')
      ? {test_acceptance_enabled:true,eligible:false,blockers:[{code:'legacy_stock_present'}],
          facilities:[{id:7,eligible:true,counted:false,blockers:[]}]}
      : url.includes('/sync')
        ? {status:'not_synced',can_check:false,can_resync:false}
        : {status:'verified',topology:'single_warehouse',facilities:[{
            id:7,name:'Test warehouse',provider_id:'WH-A',inventory_warehouse_id:19,
            counted:false,physical:null,reserved:null,available:null,
          }]};
    return {ok:true,json:async()=>body} as Response;
  }));
  await act(async()=>root.render(<ManageStockDrawer isOpen onClose={()=>{}}
    item={{masterSku:'TEST-1',productName:'Test product',variantId:55,mappedStoreIds:['3']} as any}
    mode="independent" stores={[{id:'3',name:'Lazada Test',platform:'lazada'}]} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  expect(host.textContent).toContain('Legacy stock exists for this SKU');
  expect([...host.querySelectorAll('button')].some(button=>button.textContent?.trim()==='First Count')).toBe(false);
});

it.each([
  {counted:false,physical:null,available:null,secondCounted:false,expected:['First Count'],hidden:['Actual Intake (+Qty)','Audit Adjustment','Transfer Stock']},
  {counted:true,physical:5,available:5,secondCounted:false,expected:['Actual Intake (+Qty)','Audit Adjustment'],hidden:['First Count','Transfer Stock']},
  {counted:true,physical:5,available:5,secondCounted:true,expected:['Allocate Stock','Actual Intake (+Qty)','Audit Adjustment','Transfer Stock'],hidden:['First Count']},
])('shows only eligible facility actions for count state $counted and second counted $secondCounted',async scenario=>{
  vi.stubGlobal('fetch',vi.fn(async (input:RequestInfo|URL)=>{
    const url=String(input);
    const facilities=[{id:7,name:'Warehouse A',provider_id:'WH-A',inventory_warehouse_id:19,
      counted:scenario.counted,physical:scenario.physical,reserved:0,available:scenario.available},
      {id:8,name:'Warehouse B',provider_id:'WH-B',inventory_warehouse_id:20,
        counted:scenario.secondCounted,physical:scenario.secondCounted?2:null,reserved:0,
        available:scenario.secondCounted?2:null}];
    const body=url.includes('test-facility-acceptance')
      ? {test_acceptance_enabled:true,eligible:true,blockers:[],facilities:facilities.map(facility=>({
          id:facility.id,eligible:true,counted:facility.counted,blockers:[],
        }))}
      : url.includes('/sync') ? {status:'not_synced',can_check:false,can_resync:false}
        : {status:'verified',topology:'multi_warehouse',facilities};
    return {ok:true,json:async()=>body} as Response;
  }));
  await act(async()=>root.render(<ManageStockDrawer isOpen onClose={()=>{}}
    item={{masterSku:'TEST-1',productName:'Test product',variantId:55,mappedStoreIds:['3']} as any}
    mode="independent" stores={[{id:'3',name:'Lazada Test',platform:'lazada'}]} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  const buttons=[...host.querySelectorAll('button')].map(button=>button.textContent?.trim());
  for(const label of scenario.expected)expect(buttons).toContain(label);
  for(const label of scenario.hidden)expect(buttons).not.toContain(label);
});

it('routes warehouse allocation to the selected facility instead of a store-total count',async()=>{
  vi.stubGlobal('fetch',vi.fn(async (input:RequestInfo|URL)=>{
    const url=String(input);
    const facilities=[7,8].map(id=>({id,name:`Warehouse ${id}`,provider_id:`WH-${id}`,
      inventory_warehouse_id:id+10,counted:true,physical:5,reserved:0,available:5}));
    const body=url.includes('test-facility-acceptance')
      ? {test_acceptance_enabled:true,eligible:true,blockers:[],facilities:facilities.map(f=>({id:f.id,eligible:true,counted:true,blockers:[]}))}
      : url.includes('/sync') ? {status:'not_synced',can_check:false,can_resync:false}
        : {status:'verified',topology:'multi_warehouse',facilities};
    return {ok:true,json:async()=>body} as Response;
  }));
  const allocate=vi.fn();
  await act(async()=>root.render(<ManageStockDrawer isOpen onClose={()=>{}}
    item={{masterSku:'TEST-1',productName:'Test product',variantId:55,mappedStoreIds:['3']} as any}
    mode="independent" stores={[{id:'3',name:'Lazada Test',platform:'lazada'}]}
    onSelectAllocateStock={allocate} />));
  await act(async()=>{await Promise.resolve();await Promise.resolve()});
  const button=[...host.querySelectorAll('button')].find(node=>node.textContent?.trim()==='Allocate Stock');
  expect(button).toBeDefined();
  await act(async()=>button?.click());
  expect(allocate).toHaveBeenCalledWith(expect.objectContaining({masterSku:'TEST-1'}),'3','7');
});
