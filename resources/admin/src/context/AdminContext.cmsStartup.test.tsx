// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
const api=vi.hoisted(()=>({fetchOrders:vi.fn().mockResolvedValue([])}));
vi.mock('../integration/orderData',()=>({...api}));
import {AdminProvider,useAdmin} from './AdminContext';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('defers operational reads on CMS routes and loads them when navigating to Orders',async()=>{
 window.history.replaceState({},'', '/admin/pages');
 window.__JW_ADMIN__={user:{name:'Admin',email:'admin@example.test',avatar:null},csrfToken:'test',logoutUrl:'/logout',ordersUrl:'/admin/api/orders'};
 vi.stubGlobal('fetch',vi.fn(async()=>new Response('{}',{status:503})));
 let admin!:ReturnType<typeof useAdmin>;
 function Probe(){admin=useAdmin();return null;}
 const host=document.createElement('div');const root=createRoot(host);
 try{
  await act(async()=>root.render(<AdminProvider><Probe/></AdminProvider>));
  expect(api.fetchOrders).not.toHaveBeenCalled();
  await act(async()=>admin.setCurrentSection('orders'));
  expect(api.fetchOrders).toHaveBeenCalledTimes(1);
 }finally{await act(async()=>root.unmount());vi.unstubAllGlobals();delete window.__JW_ADMIN__;window.history.replaceState({},'', '/');}
});
