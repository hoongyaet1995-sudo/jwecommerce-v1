// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {CustomerContextPanel} from './CustomerContextPanel';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({addStaffNote:vi.fn(),addToast:vi.fn(),supportBusy:false})}));
it('shows saved reply evidence separately from teammate notes',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div'),root=createRoot(host);
 const conversation={customer:{name:'Customer',avatar:'/images/support-person.svg'},internalNotes:[],messages:[{id:'1',timestamp:'07 Oct 10:00',aiTrace:{decision:'partial',settingsVersion:7,tools:[{tool:'product.search',status:'unavailable',sourceIds:[]},{tool:'faq.search',status:'success',sourceIds:['shipping']}],citedSourceIds:['shipping'],retrievedSourceIds:['shipping'],clarification:null,escalation:null}}]} as any;
 try{await act(async()=>root.render(<CustomerContextPanel conversation={conversation} onClose={()=>{}}/>));
 expect(host.textContent).toContain('AI Reply Evidence');expect(host.textContent).toContain('Product search');expect(host.textContent).toContain('Query unavailable');expect(host.textContent).toContain('shipping');expect(host.textContent).toContain('Private Staff Notes (0)');
 }finally{await act(async()=>root.unmount());}
});
