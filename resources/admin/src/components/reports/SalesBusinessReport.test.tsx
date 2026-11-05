// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {SalesBusinessReport} from './SalesBusinessReport';
it('does not display database or server details from a failed report response',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 history.replaceState(null,'','/admin/reports/tax?from=2026-11-02&to=2026-11-02&currency=MYR');
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue({ok:false,status:500,json:async()=>({message:'SQLSTATE private_database internal query'})}));
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{await act(async()=>root.render(<SalesBusinessReport financial/>));expect(host.textContent).toContain('Report unavailable. Please retry later.');expect(host.textContent).not.toContain('SQLSTATE');expect(host.textContent).not.toContain('private_database');}
 finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});

it('explains currency-scoped undated omissions separately from global incomplete money',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 history.replaceState(null,'','/admin/reports/sales?from=2026-10-01&to=2026-10-31&currency=MYR');
 const data={totals:{confirmed_minor:0,refunded_minor:0,net_minor:0,paid_orders:0,average_order_minor:null},trend:[],methods:[],records:{total:0,data:[],last_page:1},coverage:{undated_payments:7,undated_refunds:0,incomplete_payment_money_all_currencies:4}};
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue({ok:true,status:200,json:async()=>data}));
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{await act(async()=>root.render(<SalesBusinessReport/>));expect(host.textContent).toContain('Across recorded history in MYR: 7 confirmed payments');expect(host.textContent).toContain('search does not narrow this warning');expect(host.textContent).toContain('Across all currencies, 4 confirmed payments');expect(host.textContent).toContain('may overlap the undated count');}
 finally{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
