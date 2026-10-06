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
