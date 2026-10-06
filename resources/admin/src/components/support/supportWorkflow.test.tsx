// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ConversationListPanel} from './ConversationListPanel';
import {ChatWorkspacePanel} from './ChatWorkspacePanel';

const state=vi.hoisted(()=>({supportConversations:[] as any[],supportDrafts:{},supportBusy:false}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>state}));
it('shows AI only in Pending and requested or active human only in Human across channels',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 state.supportConversations=['open','waiting_human','human_mode','resolved'].map((status,i)=>({id:String(i),status,channel:i%2?'facebook':'web_guest',customer:{name:status,avatar:'/images/customer.png',tags:[]},messages:[],snippet:'',createdAt:new Date().toISOString(),unreadCount:1}));
 const host=document.createElement('div'),root=createRoot(host);
 const props={selectedChannel:'all',setSelectedChannel:vi.fn(),setStatusFilter:vi.fn(),searchQuery:'',setSearchQuery:vi.fn(),onSelectConversation:vi.fn(),stats:{}} as any;
 await act(async()=>root.render(<ConversationListPanel {...props} statusFilter="open"/>));
 expect(host.querySelectorAll('[id^="conversation-item-"]')).toHaveLength(1);
 expect(host.querySelector('#conversation-item-0')).not.toBeNull();
 await act(async()=>root.render(<ConversationListPanel {...props} statusFilter="human_mode"/>));
 expect(host.querySelectorAll('[id^="conversation-item-"]')).toHaveLength(2);
 expect(host.querySelector('#conversation-item-1')).not.toBeNull();
 expect(host.querySelector('#conversation-item-2')).not.toBeNull();
 await act(async()=>root.unmount());
});
it('offers the same composer before takeover and Hand to AI after takeover for Messenger',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'),root=createRoot(host);
 const conversation={id:'ready',status:'open',channel:'facebook',canReply:true,customer:{name:'Customer',avatar:'/images/customer.png'},messages:[],internalNotes:[]} as any;
 await act(async()=>root.render(<ChatWorkspacePanel conversation={conversation} onBackToList={vi.fn()} isContextOpen={false} onToggleContext={vi.fn()}/>));
 expect(host.querySelector('textarea')).not.toBeNull();
 await act(async()=>root.render(<ChatWorkspacePanel conversation={{...conversation,status:'human_mode'}} onBackToList={vi.fn()} isContextOpen={false} onToggleContext={vi.fn()}/>));
 expect(host.querySelector('#btn-hand-back-ai')).toBeNull();
 await act(async()=>(host.querySelector('#btn-support-actions') as HTMLButtonElement).click());
 expect(host.querySelector('#btn-hand-back-ai')).not.toBeNull();
 await act(async()=>root.unmount());
});

it('shows scoped platform ID and handle in customer details with a copy control',async()=>{
 const {CustomerContextPanel}=await import('./CustomerContextPanel');
 const {renderToStaticMarkup}=await import('react-dom/server');
 const conversation={channel:'instagram',customer:{name:'Buyer',avatar:'https://example.test/a.jpg',handle:'buyer',platformUserId:'12345'},internalNotes:[]} as any;
 const html=renderToStaticMarkup(<CustomerContextPanel conversation={conversation} onClose={vi.fn()}/>);
 expect(html).toContain('12345');expect(html).toContain('@buyer');expect(html).toContain('Copy platform user ID');
});

it.each([
 ['human_mode','#btn-hand-back-ai','handBackToAI'],
 ['open','#btn-take-over-chat','takeOverConversation'],
 ['human_mode','button[aria-label="Archive conversation"]','archiveConversation'],
 ['human_mode','#btn-resolve-chat','resolveConversation'],
])('requires confirmation for %s %s',async(status,selector,method)=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const callback=vi.fn().mockResolvedValue(true);(state as any)[method]=callback;
 const host=document.createElement('div');document.body.appendChild(host);const root=createRoot(host);
 const conversation={id:'confirm-me',status,channel:'web_guest',customer:{name:'Buyer',avatar:'/images/customer.png'},messages:[],internalNotes:[]} as any;
 await act(async()=>root.render(<ChatWorkspacePanel conversation={conversation} onBackToList={vi.fn()} isContextOpen={false} onToggleContext={vi.fn()}/>));
 await act(async()=>(host.querySelector('#btn-support-actions') as HTMLButtonElement).click());
 await act(async()=>(host.querySelector(selector) as HTMLButtonElement).click());
 expect(callback).not.toHaveBeenCalled();expect(host.querySelector('[role="dialog"]')).not.toBeNull();
 await act(async()=>(host.querySelector('[data-support-confirm-cancel]') as HTMLButtonElement).click());
 expect(callback).not.toHaveBeenCalled();
 await act(async()=>(host.querySelector('#btn-support-actions') as HTMLButtonElement).click());
 await act(async()=>(host.querySelector(selector) as HTMLButtonElement).click());
 await act(async()=>(host.querySelector('[data-support-confirm-submit]') as HTMLButtonElement).click());
 expect(callback).toHaveBeenCalledTimes(1);expect(callback.mock.calls[0][0]).toBe('confirm-me');
 await act(async()=>root.unmount());host.remove();
});

it('shows PDF only on supported channels and respects server capability',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div'),root=createRoot(host);const c={id:'pdf',status:'human_mode',channel:'facebook',canReply:true,customer:{name:'Customer',avatar:'/images/customer.png'},messages:[]} as any;
 await act(async()=>root.render(<ChatWorkspacePanel conversation={c} onBackToList={()=>{}} isContextOpen={false} onToggleContext={()=>{}}/>));expect(host.querySelector('[aria-label="Attach PDF"]')).not.toBeNull();
 await act(async()=>root.render(<ChatWorkspacePanel conversation={{...c,channel:'instagram'}} onBackToList={()=>{}} isContextOpen={false} onToggleContext={()=>{}}/>));expect(host.querySelector('[aria-label="Attach PDF"]')).toBeNull();
 await act(async()=>root.render(<ChatWorkspacePanel conversation={{...c,mediaCapabilities:{images:true,documents:false,audio:false}}} onBackToList={()=>{}} isContextOpen={false} onToggleContext={()=>{}}/>));expect(host.querySelector('[aria-label="Attach PDF"]')).toBeNull();await act(async()=>root.unmount());
});

it('renders document messages in the actual chat stream',async()=>{
 const host=document.createElement('div'),root=createRoot(host);const c:any={id:'pdf-render',status:'human_mode',channel:'web_guest',customer:{name:'Customer',avatar:'/images/customer.png'},messages:[{id:'doc',senderRole:'customer',type:'document',content:'Receipt',attachments:[{id:'file',type:'document',status:'ready',url:'/private/pdf',name:'receipt.pdf',sizeBytes:1000}]}]};
 await act(async()=>root.render(<ChatWorkspacePanel conversation={c} onBackToList={()=>{}} isContextOpen={false} onToggleContext={()=>{}}/>));
 expect(host.querySelector('[aria-label="Download document receipt.pdf"]')).not.toBeNull();await act(async()=>root.unmount());
});
