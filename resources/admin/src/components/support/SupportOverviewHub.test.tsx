// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {SupportOverviewHub} from './SupportOverviewHub';
const {state,navigate}=vi.hoisted(()=>({state:{facebook:{data:{accounts:[]},loading:false,error:''} as any,instagram:{data:{accounts:[]},loading:false,error:''} as any,whatsapp:{data:{accounts:[]},loading:false,error:''} as any,support:{supportConversations:[],supportLoading:false,supportError:''} as any},navigate:vi.fn()}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:navigate})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({})}));
vi.mock('../../integration/facebookAccounts',()=>({useFacebookAccounts:()=>state.facebook}));
vi.mock('../../integration/instagramAccounts',()=>({useInstagramAccounts:()=>state.instagram}));
vi.mock('../../integration/whatsappAccounts',()=>({useWhatsAppAccounts:()=>state.whatsapp}));
vi.mock('../../integration/supportChat',()=>({useSupportChat:()=>state.support}));
HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};
HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
let root:ReturnType<typeof createRoot>;
afterEach(async()=>{await act(async()=>root.unmount());document.body.innerHTML='';state.facebook={data:{accounts:[]},loading:false,error:''};state.instagram={data:{accounts:[]},loading:false,error:''};state.whatsapp={data:{accounts:[]},loading:false,error:''};state.support={supportConversations:[],supportLoading:false,supportError:''};navigate.mockClear();});
async function render(){(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);root=createRoot(host);await act(async()=>root.render(<SupportOverviewHub/>));const card=Array.from(document.querySelectorAll<HTMLElement>('[role="button"]')).find(c=>c.textContent?.includes('Connect Multichannel Account'))!;await act(async()=>card.click());}
it.each([
 [{accounts:[{state:'connected',messagingReady:true}]},false,'','Ready to chat'],
 [{accounts:[{state:'authorization_expired',messagingReady:false}]},false,'','Action required'],
 [{accounts:[{state:'disconnected',messagingReady:false}]},false,'','Setup required'],
 [null,true,'','Checking...'],
 [null,false,'Unavailable','Unable to verify'],
])('Instagram status reflects its own connection data',async(data,loading,error,label)=>{state.instagram={data,loading,error};await render();const button=Array.from(document.querySelectorAll<HTMLButtonElement>('dialog button')).find(b=>b.textContent?.startsWith('Instagram'))!;expect(button.textContent).toContain(label);await act(async()=>button.click());expect(navigate).toHaveBeenCalledWith('support-instagram');});
it('overview includes both connected channels without claiming messaging readiness',async()=>{state.facebook.data={accounts:[{state:'connected',messagingReady:false}]};state.instagram.data={accounts:[{state:'connected',messagingReady:false}]};await render();expect(document.querySelector('[aria-label="Facebook connected"]')).not.toBeNull();expect(document.querySelector('[aria-label="Instagram connected"]')).not.toBeNull();expect(document.body.textContent).not.toContain('Instagram, TikTok and WhatsApp setup will follow');});
it('opens TikTok preparation without claiming an available connection',async()=>{await render();const button=Array.from(document.querySelectorAll<HTMLButtonElement>('dialog button')).find(b=>b.textContent?.startsWith('TikTok'))!;expect(button.textContent).toContain('Awaiting TikTok approval');await act(async()=>button.click());expect(navigate).toHaveBeenCalledWith('support-tiktok');});

it('shows unread and waiting-human counts and connected WhatsApp',async()=>{
 state.whatsapp.data={accounts:[{state:'connected',messagingReady:true}]};state.support.supportConversations=[{status:'open',unreadCount:3},{status:'waiting_human',unreadCount:2},{status:'human_mode',unreadCount:0},{status:'waiting_human',isArchived:true,unreadCount:9}];await render();
 expect(document.querySelector('[aria-label="Unread Messages"]')!.textContent).toContain('5');expect(document.querySelector('[aria-label="Needs Human Support"]')!.textContent).toContain('1');expect(document.querySelector('[aria-label="WhatsApp connected"]')).not.toBeNull();
 const wa=Array.from(document.querySelectorAll<HTMLButtonElement>('dialog button')).find(b=>b.textContent?.startsWith('WhatsApp'))!;expect(wa.textContent).toContain('Ready to chat');expect(wa.querySelector('span.bg-cyan-100')).not.toBeNull();
});
it('does not display zero on support failure',async()=>{state.support.supportError='Unavailable';await render();expect(document.querySelector('[aria-label="Unread Messages"]')!.textContent).toContain('Unable to load');});
