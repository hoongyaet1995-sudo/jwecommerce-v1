// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {PhysicalCountModal} from './PhysicalCountModal';

it('prevents editing quantities until the authoritative balance has loaded',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 let resolve!:(value:Response)=>void;
 vi.stubGlobal('fetch',vi.fn(()=>new Promise<Response>(done=>{resolve=done;})));
 try{
  await act(async()=>root.render(<PhysicalCountModal isOpen inventoryMode="shared_pool"
   item={{id:'17',masterSku:'SKU',productName:'Product',physicalStockStatus:'verified',totalPhysicalStock:25}} onClose={()=>{}}/>));
  expect((host.querySelector('input[type="number"]') as HTMLInputElement).disabled).toBe(true);
  expect((host.querySelector('input[type="text"]') as HTMLInputElement).disabled).toBe(true);
  await act(async()=>resolve(new Response(JSON.stringify({current_page:1,last_page:1,total:1,data:[{
   balance_id:1,variant_id:17,pool_kind:'shared',physical_stock:25,safety_stock:2,reserved_stock:null,
   basic_wms:{owned_reserved:0,available:23},
  }]}))));
  expect((host.querySelector('input[type="number"]') as HTMLInputElement).disabled).toBe(false);
  expect(host.textContent).toContain('Current: 25 units');
 }finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('offers active warehouses for an initial count without writing on open',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({data:[],current_page:1,last_page:1,total:0})));
 vi.stubGlobal('fetch',request);
 try{
  await act(async()=>root.render(<PhysicalCountModal isOpen inventoryMode="independent_warehouses"
   warehouses={[{id:'3',code:'TEST',name:'Test warehouse',status:'active'},{id:'4',code:'OLD',name:'Inactive warehouse',status:'inactive'}]}
   item={{id:'17',masterSku:'SKU',productName:'Product'}} onClose={()=>{}}/>));
  expect(host.textContent).toContain('Test warehouse');
  expect(host.textContent).not.toContain('Inactive warehouse');
  expect(host.textContent).not.toContain('No physical balance is configured');
  expect(request).toHaveBeenCalledTimes(1);
  expect(request.mock.calls[0][1].method).toBeUndefined();
  expect(host.querySelector('input[type="number"]')?.getAttribute('value')).toBe('');
 }finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('uses Basic quantities for warehouse selection and input estimates after a manual operation',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue(new Response(JSON.stringify({current_page:1,last_page:1,total:1,data:[{
  balance_id:1,variant_id:17,pool_kind:'warehouse',warehouse_id:9,warehouse_code:'TEST-WH',warehouse_name:'Test warehouse',
  physical_stock:22,reserved_stock:9,safety_stock:2,atp:null,
  basic_wms:{owned_reserved:0,available:20},
 }]}))));
 try{
  await act(async()=>root.render(<PhysicalCountModal isOpen item={{id:'17',masterSku:'SKU',productName:'Product'}} onClose={()=>{}}/>));
  expect(host.textContent).toContain('Basic Available: 20');
  expect(host.textContent).toContain('Projected Basic Available (excludes unallocated orders): 30 units');
 }finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('opens the source modal but blocks review when the SKU has no configured balance',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);
 const root=createRoot(host);
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({data:[],total:0,current_page:1,last_page:1})));
 vi.stubGlobal('fetch',request);
 try {
  await act(async()=>root.render(<PhysicalCountModal isOpen item={{id:'17',masterSku:'SKU',productName:'Product'}} onClose={()=>{}}/>));
  expect(request.mock.calls[0][0]).toBe('/admin/api/inventory/balances?variantId=17&page=1');
  expect(host.textContent).toContain('No physical balance is configured');
  expect(host.textContent).toContain('Warehouse Stock Operation');
  const review=Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.includes('Proceed to Review'));
  expect(review?.disabled).toBe(true);
  expect(host.querySelector('input[type="number"]')?.getAttribute('value')).toBe('');
 } finally {await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
