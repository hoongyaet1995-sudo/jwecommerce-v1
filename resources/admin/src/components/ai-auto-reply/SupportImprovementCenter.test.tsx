// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SupportImprovementCenter} from './SupportImprovementCenter';
const api=vi.hoisted(()=>({overview:vi.fn(),drafts:vi.fn(),save:vi.fn(),review:vi.fn(),create:vi.fn()}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:vi.fn()})}));
vi.mock('../../integration/supportImprovement',()=>({supportImprovement:api}));
const d={id:1,question:'Can you assemble a bookcase?',answer:'Ask our team for a quote.',category:'others',channel:'storefront',status:'Needs review',version:1,conversationId:'source-id',sourceMessageId:2,staffAnswer:'Ask our team for a quote.',createdAt:'2026-10-08',reviewedAt:null,publishedFaqId:null};
const setup=async()=>{(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);const root=createRoot(host);await act(async()=>root.render(<SupportImprovementCenter/>));return{host,root,button:(text:string)=>[...host.querySelectorAll('button')].find(b=>b.textContent===text)!}};
it('uses empty real data and gives a truthful error instead of fake zero metrics',async()=>{
 api.drafts.mockResolvedValue({drafts:[],pending:0,categories:[]});api.overview.mockRejectedValue(new Error('Service unavailable'));
 const{host,root,button}=await setup();try{expect(host.querySelector('[role=alert]')!.textContent).toContain('Service unavailable');expect(host.textContent).not.toContain('12 questions');await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent?.includes('View performance'))!.click());expect(host.textContent).toContain('—');}finally{await act(async()=>root.unmount());host.remove()}
});
it('keeps older pending answers reachable when the first page has none',async()=>{
 api.overview.mockResolvedValue({counts:{received:0,answered:0,improve:0,handoff:0,failures:0},questions:[],failures:[],limited:false});
 api.drafts.mockResolvedValueOnce({drafts:[],pending:1,categories:[{id:'others',label:'Others'}],nextCursor:200}).mockResolvedValueOnce({drafts:[d],pending:1,categories:[{id:'others',label:'Others'}],nextCursor:null});
 const{host,root,button}=await setup();try{await act(async()=>button('Review 1 answers').click());await act(async()=>button('Load older answers').click());expect(host.querySelector<HTMLInputElement>('#draft-question')!.value).toBe(d.question);expect(api.drafts).toHaveBeenCalledWith(200);}finally{await act(async()=>root.unmount());host.remove()}
});
it('requires saved changes and confirmation before publishing, then refreshes server status',async()=>{
 api.overview.mockResolvedValue({counts:{received:1,answered:0,improve:1,handoff:0,failures:0},questions:[],failures:[],limited:false});api.drafts.mockResolvedValue({drafts:[d],pending:1,categories:[{id:'others',label:'Others'}]});api.save.mockResolvedValue({draft:{...d,version:2}});api.review.mockResolvedValue({draft:{...d,status:'Published',version:3}});
 const{host,root,button}=await setup();try{await act(async()=>button('Review 1 answers').click());expect((button('Approve and publish') as HTMLButtonElement).disabled).toBe(true);
 const check=host.querySelector<HTMLInputElement>('input[type=checkbox]')!;await act(async()=>check.click());await act(async()=>button('Approve and publish').click());expect(api.review).not.toHaveBeenCalled();expect(host.querySelector('[role=dialog]')!.textContent).toContain('Publish this answer?');
 api.drafts.mockResolvedValue({drafts:[{...d,status:'Published'}],pending:0,categories:[]});await act(async()=>button('Confirm publish').click());expect(api.review).toHaveBeenCalledWith(expect.objectContaining({id:1}),true);expect(host.textContent).toContain('0 awaiting review');expect(host.textContent).toContain('No answers to review here');
 }finally{await act(async()=>root.unmount());host.remove()}
});
