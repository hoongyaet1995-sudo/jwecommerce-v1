// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({})}));
import {marketplaceOperations} from '../integration/marketplaceOperations';
import {OperationalOrders} from './OperationalOrders';
afterEach(()=>{vi.restoreAllMocks();vi.unstubAllGlobals();});
it('fetches thirty days only after an eligible store is selected',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.spyOn(Date,'now').mockReturnValue(1800000000000);
 vi.spyOn(marketplaceOperations,'connections').mockResolvedValue({stores:[{id:'7',platform:'shopee',shopName:'Selected store',status:'connected'} as any],capabilities:{shopee:{orders:true}} as any});
 vi.spyOn(marketplaceOperations,'orders').mockResolvedValue({data:[],total:0,current_page:1,last_page:1,per_page:15});
 vi.spyOn(marketplaceOperations,'orderSummary').mockResolvedValue({total:0,statuses:{}});
 const fetch=vi.spyOn(marketplaceOperations,'fetchOrders').mockResolvedValue({runId:5});
 vi.spyOn(marketplaceOperations,'orderRun').mockResolvedValue({id:5,status:'succeeded',pages_completed:3,orders_processed:0} as any);
 const container=document.createElement('div');const root=createRoot(container);
 try{
  await act(async()=>root.render(<OperationalOrders/>));
  const button=Array.from(container.querySelectorAll('button')).find(b=>b.textContent?.includes('Fetch Orders'))!;
  expect(button.disabled).toBe(true);
  const selector=container.querySelector<HTMLSelectElement>('[aria-label="Filter orders by store"]')!;
  await act(async()=>{selector.value='7';selector.dispatchEvent(new Event('change',{bubbles:true}));});
  await act(async()=>button.click());
  expect(fetch).toHaveBeenCalledExactlyOnceWith('7',1797408000,1800000000);
  expect(button.textContent).toContain('30 days');
 }finally{await act(async()=>root.unmount());}
});
it('reports connection failure instead of presenting an empty connected-store fact',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 vi.spyOn(marketplaceOperations,'connections').mockRejectedValue(new Error('unavailable'));
 vi.spyOn(marketplaceOperations,'orders').mockResolvedValue({data:[],total:0,current_page:1,last_page:1,per_page:15});
 vi.spyOn(marketplaceOperations,'orderSummary').mockResolvedValue({total:0,statuses:{}});
 const container=document.createElement('div');const root=createRoot(container);
 try{await act(async()=>root.render(<OperationalOrders/>));
 expect(container.querySelector('[role="alert"]')?.textContent).toContain('Store connections could not be loaded');
 expect(container.textContent).not.toContain('All Mapped');
 }finally{await act(async()=>root.unmount());}
});
it('keeps the selected order after an older detail request returns and clears it on close',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const rows=[1,2].map(id=>({id,store_id:1,external_order_id:`ORDER-${id}`,platform:'shopee' as const,shop_name:'Test',platform_status:'READY_TO_SHIP',currency:'MYR',total_amount:'10.00',platform_updated_at:200,fetched_at:''}));
 vi.spyOn(marketplaceOperations,'connections').mockResolvedValue({stores:[{id:'7',platform:'shopee',shopName:'Second store'} as any],capabilities:{} as any});
 vi.spyOn(marketplaceOperations,'orders').mockResolvedValue({data:rows,total:2,current_page:1,last_page:1,per_page:15});
 vi.spyOn(marketplaceOperations,'orderSummary').mockResolvedValue({total:2,statuses:{READY_TO_SHIP:2}});
 const detail=(id:number)=>({data:{...rows[id-1],lines:[],packages:[],shipping_carrier:null,capabilities:{fulfillment:false,airway_bill:false},recipient:{name:`CUSTOMER-${id}`} as any}});
 let old!:(value:ReturnType<typeof detail>)=>void;
 vi.spyOn(marketplaceOperations,'orderDetail').mockImplementationOnce(()=>new Promise(resolve=>{old=resolve;})).mockResolvedValueOnce(detail(2));
 const container=document.createElement('div');const root=createRoot(container);
 const button=(text:string)=>Array.from(container.querySelectorAll('button')).find(b=>b.textContent===text)!;
 try{
  await act(async()=>root.render(<OperationalOrders/>));
  await act(async()=>button('ORDER-1').click());
  await act(async()=>button('ORDER-2').click());
  expect(container.textContent).toContain('CUSTOMER-2');
  await act(async()=>old(detail(1)));
  expect(container.textContent).not.toContain('CUSTOMER-1');
  expect(container.textContent).toContain('CUSTOMER-2');
  await act(async()=>container.querySelector<HTMLButtonElement>('[aria-label="Close order details"]')!.click());
  expect(container.textContent).not.toContain('CUSTOMER-2');
  const selector=container.querySelector<HTMLSelectElement>('[aria-label="Filter orders by store"]')!;
  expect(selector).not.toBeNull();
  await act(async()=>{selector.value='7';selector.dispatchEvent(new Event('change',{bubbles:true}));});
  expect(marketplaceOperations.orders).toHaveBeenLastCalledWith(expect.objectContaining({storeId:'7',page:1}));
 }finally{await act(async()=>root.unmount());}
});
