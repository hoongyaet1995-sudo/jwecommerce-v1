// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {ReportTabs} from './ReportTabs';
import {ReportsHub} from './ReportsHub';
let section='reports-sales';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({currentSection:section})}));
vi.mock('./SalesBusinessReport',()=>({SalesBusinessReport:({navigation}:any)=><section>{navigation}<h1>Sales</h1></section>}));
vi.mock('./OperationalBusinessReport',()=>({OperationalBusinessReport:({navigation}:any)=><section>{navigation}<h1>Orders</h1></section>}));
vi.mock('./ReportStaffAccess',()=>({ReportStaffAccess:()=> <div>Staff report access</div>}));
async function mount(element:React.ReactNode,check:(host:HTMLDivElement)=>void){(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);const root=createRoot(host);try{await act(async()=>root.render(element));check(host);}finally{await act(async()=>root.unmount());host.remove();}}
it('keeps seven report categories, filters and active state without Insights',async()=>{history.replaceState(null,'','/admin/reports/sales?from=2026-10-01&to=2026-10-31&currency=SGD&page=4');await mount(<ReportTabs active="sales"/>,host=>{const nav=host.querySelector('[aria-label="Report categories"]')!;const links=Array.from(nav.querySelectorAll('a'));expect(links).toHaveLength(7);expect(links[0].textContent).toBe('Sales & revenue');expect(links[0].getAttribute('href')).toContain('currency=SGD');expect(links[0].getAttribute('href')).not.toContain('page=');expect(nav.className).toContain('overflow-x-auto');expect(host.textContent).not.toContain('AI Business Insights');expect(host.querySelector('a[href="/admin/reports/insights"]')).toBeNull();expect(nav.querySelectorAll('[aria-current="page"]')).toHaveLength(1);});});
it('retains Sales and staff access without an AI entry',async()=>{section='reports-sales';await mount(<ReportsHub/>,host=>{expect(host.textContent).toContain('Sales');expect(host.textContent).toContain('Staff report access');expect(host.textContent).not.toContain('AI Business Insights');});});
it('does not revive the AI UI for a stale Insights section',async()=>{section='reports-insights';await mount(<ReportsHub/>,host=>{expect(host.textContent).not.toContain('AI chat');expect(host.textContent).toContain('Sales');});});
