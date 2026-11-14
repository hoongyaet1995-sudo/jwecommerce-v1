import React from 'react';
import {createRoot} from 'react-dom/client';
import {AdminProvider} from '../../src/context/AdminContext';
import {FacebookAccountPage} from '../../src/components/support/FacebookAccountPage';
import '../../src/index.css';
window.__JW_ADMIN__={user:{name:'Test Admin',email:'test@example.test',avatar:null},csrfToken:'test',logoutUrl:'/logout'};
let fail=false;
const pages=[{id:'test-page-1',name:'Test Page One'},{id:'test-page-2',name:'Test Page Two'}];
type FixtureAccount = {id:string;pageId:string;name?:string;state:string;checkReason?:string;checkedAt:string|null;permissions:string[];messagingReady:boolean};
let data: {configured:boolean;canManage:boolean;pages:typeof pages;accounts:FixtureAccount[]}={configured:true,canManage:true,pages,accounts:[{id:"test-page-1",pageId:"test-page-1",name:"Test Page One",state:"permission_required",checkReason:"Page read permission is missing. Reconnect and grant Page access.",checkedAt:new Date().toISOString(),permissions:[],messagingReady:false}]};
window.fetch=async(input,init)=>{
 if(!String(input).includes('/social/facebook'))return new Response('{}',{status:404});
 if(String(input).endsWith('/check'))return new Response(JSON.stringify({account:{...data.accounts[0],checkedAt:new Date().toISOString()}}),{status:200});
 if(String(input).endsWith('/disconnect'))data={...data,accounts:data.accounts.map(a=>({...a,state:'disconnected',checkReason:'This Page is disconnected from this store.'}))};
 if(init?.method==='DELETE')data={...data,accounts:[]};
 if(String(input)==='/admin/api/social/facebook/select'){
  const pageIds=JSON.parse(String(init?.body)).page_ids;
  if(fail){fail=false;return new Response(JSON.stringify({message:'Test connection failure. Try again.'}),{status:422});}
  data={...data,pages:[],accounts:pages.filter(p=>pageIds.includes(p.id)).map(p=>({id:p.id,pageId:p.id,name:p.name,state:'connected',checkedAt:null,permissions:[],messagingReady:false}))};
 }
 return new Response(JSON.stringify(String(input).includes('/social/facebook')?data:{}),{status:200});
};
createRoot(document.getElementById('root')!).render(<AdminProvider><main className="min-h-screen bg-neutral-50 dark:bg-neutral-950 p-8"><button onClick={()=>document.documentElement.classList.toggle('dark')}>Toggle theme</button><p>Local test fixture — no Meta connection</p><FacebookAccountPage/></main></AdminProvider>);
