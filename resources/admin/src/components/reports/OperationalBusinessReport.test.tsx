// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {OperationalBusinessReport} from './OperationalBusinessReport';

it('binds cohort attention counts and discloses the converted enquiry sample',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 history.replaceState(null,'','/admin/reports/orders?from=2026-11-02&to=2026-11-02&currency=MYR');
 const base={filters:{from:'2026-11-02',to:'2026-11-02',currency:'MYR',search:null,page:1},as_of:'2026-11-04T00:00:00Z',basis:'Cohort evidence',coverage:{marketplaces:'not connected'},records:{total:0,last_page:1,data:[]}};
 const orders={...base,lifecycle:{orders_created:4,orders_with_confirmed_payments:1,orders_dispatched:1,orders_delivered:0,payment_review_orders:1,processing_without_dispatch:2,undated_confirmed_payments_excluded:0,undated_completed_refunds_excluded:0}};
 const enquiries={...base,lifecycle:{enquiries_created:3,enquiries_quoted:2,enquiries_accepted:1,paid_quote_orders:1,open_enquiries:3,average_conversion_hours:34,conversion_sample_size:1,conversion_invalid_time_pairs:0}};
 const metricRecords={...orders,records:{total:1,last_page:1,current_page:1,data:[{id:9,number:'MATCH-PAID',customer_id:1,order_status:'paid',created_at:'2026-11-02 01:00:00',ordered_minor:1000}]}};
 const fetchMock=vi.fn(async(url:string)=>({ok:true,status:200,json:async()=>url.includes('metric=paid_orders')?metricRecords:url.includes('/enquiries?')?enquiries:orders}));vi.stubGlobal('fetch',fetchMock);
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{await act(async()=>root.render(<OperationalBusinessReport type="orders"/>));const panel=Array.from(host.querySelectorAll('h2')).find(h=>h.textContent==='Needs attention')?.closest('section');expect(panel?.textContent).toContain('Payment review1');expect(panel?.textContent).toContain('Processing without dispatch2');expect(panel?.textContent).toContain('Open enquiries3');expect(host.textContent).toContain('34 hours across 1 valid converted enquiries');
 await act(async()=>{(Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.startsWith('Paid orders')) as HTMLButtonElement).click()});
 expect(fetchMock.mock.calls.some(([url])=>url.includes('metric=paid_orders'))).toBe(true);expect(fetchMock.mock.calls.some(([url])=>url.includes('search=null'))).toBe(false);expect(host.querySelector('[role=dialog]')?.textContent).toContain('MATCH-PAID');expect(host.querySelector('[role=dialog] a')?.getAttribute('href')).toContain('metric=paid_orders');}
 finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('keeps previous results in their original currency while a different currency loads',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 history.replaceState(null,'','/admin/reports/customers?from=2026-11-02&to=2026-11-02&currency=MYR');
 const payload={filters:{from:'2026-11-02',to:'2026-11-02',currency:'MYR',page:1,search:''},as_of:'2026-11-04T00:00:00Z',basis:'Fixture',coverage:{guest_orders:0,undated_confirmed_payments:0,marketplaces:'not verified'},stats:{purchasing_customers:1,new_customers:1,returning_customers:0,unclassified_customers:0,average_orders_per_buyer:1},trend:[{day:'2026-11-02',new_customers:1,returning_customers:0}],records:{total:1,last_page:1,data:[{customer_id:1,paid_orders:1,net_paid_minor:1000,last_payment_in_period:'2026-11-02 01:00:00',cohort:'new'}]}};
 const fetchMock=vi.fn().mockResolvedValueOnce({ok:true,status:200,json:async()=>payload}).mockImplementationOnce(()=>new Promise(()=>{}));
 vi.stubGlobal('fetch',fetchMock);
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<OperationalBusinessReport type="customers"/>));
  expect(host.querySelector('table')?.textContent).toContain('RM');
  const currency=host.querySelector('[aria-label="Currency"]') as HTMLSelectElement;
  await act(async()=>{currency.value='SGD';currency.dispatchEvent(new Event('change',{bubbles:true}));});
  const click=async(text:string)=>act(async()=>{(Array.from(host.querySelectorAll('button')).find(b=>b.textContent===text) as HTMLButtonElement).click();});
  await click('Filters');await click('Apply filters');
  expect(fetchMock).toHaveBeenCalledTimes(2);
  expect(host.textContent).toContain('Previous results remain visible');
  expect(host.querySelector('table')?.textContent).toContain('RM');
  expect(host.querySelector('table')?.textContent).not.toContain('SGD');
 }finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('shows tracking coverage instead of duplicating campaign KPIs in the attention panel',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 history.replaceState(null,'','/admin/reports/marketing?from=2026-11-02&to=2026-11-02&currency=MYR');
 const payload={filters:{from:'2026-11-02',to:'2026-11-02',currency:'MYR',search:'',page:1},as_of:'2026-11-04T00:00:00Z',basis:'Private campaign evidence',coverage:{collection:'disabled',unconfirmed_attributions:2,inconsistent_paid_rows:0,retention:{event_days:90},marketplaces:'not connected'},stats:{sessions:3,checkouts:1,attributed_paid_orders:0,attributed_net_minor:null},trend:[],records:{total:0,last_page:1,data:[]}};
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue({ok:true,status:200,json:async()=>payload}));
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{await act(async()=>root.render(<OperationalBusinessReport type="marketing"/>));const heading=Array.from(host.querySelectorAll('h2')).find(h=>h.textContent==='Tracking coverage');const panel=heading?.closest('section');expect(panel?.textContent).toContain('Collectiondisabled');expect(panel?.textContent).toContain('Unconfirmed evidence2');expect(panel?.textContent).toContain('Ad spend / ROASNot available');}
 finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
