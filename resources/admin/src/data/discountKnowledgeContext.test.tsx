// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {DiscountKnowledgeCard} from '../components/ai-auto-reply/DiscountKnowledgeCard';
const state=vi.hoisted(()=>({config:{knowledgeScope:{discountKnowledge:{isEnabled:false,activeIds:[] as string[]}}},update:vi.fn()}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({aiAutoReplyConfig:state.config,updateAIAutoReplyConfig:state.update})}));
vi.mock('../integration/supportFaq',()=>({supportFaqRequest:()=>Promise.resolve({discountRecords:[{id:'7',name:'Actual offer',code:'REAL10',benefit:'10% OFF',status:'Live',minimumSpend:100,endsAt:null,perCustomerLimit:1,description:'Official rule'}]})}));
it('reads source cards and toggles only AI access',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{await act(async()=>root.render(<DiscountKnowledgeCard/>));expect(host.textContent).toContain('Actual offer');expect(host.textContent).not.toContain('Add Voucher');expect(host.querySelector('a')?.getAttribute('href')).toBe('/admin/promotions');await act(async()=>host.querySelector<HTMLButtonElement>('button[aria-pressed]')!.click());expect(state.update.mock.calls[0][0](state.config).knowledgeScope.discountKnowledge.activeIds).toEqual(['7']);await act(async()=>Array.from(host.querySelectorAll('button')).find(b=>b.textContent==='Combo')!.click());expect(host.textContent).toContain('no connected operational data source');}
 finally{await act(async()=>root.unmount());host.remove()}
});
