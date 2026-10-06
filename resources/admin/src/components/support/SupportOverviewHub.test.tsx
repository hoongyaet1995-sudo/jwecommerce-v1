// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {SupportOverviewHub} from './SupportOverviewHub';
const {state,navigate}=vi.hoisted(()=>({state:{facebook:{data:{accounts:[]},loading:false,error:''} as any,instagram:{data:{accounts:[]},loading:false,error:''} as any},navigate:vi.fn()}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:navigate})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({})}));
vi.mock('../../integration/facebookAccounts',()=>({useFacebookAccounts:()=>state.facebook}));
vi.mock('../../integration/instagramAccounts',()=>({useInstagramAccounts:()=>state.instagram}));
HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};
HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
let root:ReturnType<typeof createRoot>;
afterEach(async()=>{await act(async()=>root.unmount());document.body.innerHTML='';state.facebook={data:{accounts:[]},loading:false,error:''};state.instagram={data:{accounts:[]},loading:false,error:''};navigate.mockClear();});
async function render(){(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);root=createRoot(host);await act(async()=>root.render(<SupportOverviewHub/>));const card=Array.from(document.querySelectorAll<HTMLElement>('[role="button"]')).find(c=>c.textContent?.includes('Connect Multichannel Account'))!;await act(async()=>card.click());}
it.each([
 [{accounts:[{state:'connected',messagingReady:true}]},false,'','Ready to chat'],
 [{accounts:[{state:'authorization_expired',messagingReady:false}]},false,'','Action required'],
 [{accounts:[{state:'disconnected',messagingReady:false}]},false,'','Setup required'],
 [null,true,'','Checking...'],
 [null,false,'Unavailable','Unable to verify'],
])('Instagram status reflects its own connection data',async(data,loading,error,label)=>{state.instagram={data,loading,error};await render();const button=Array.from(document.querySelectorAll<HTMLButtonElement>('dialog button')).find(b=>b.textContent?.startsWith('Instagram'))!;expect(button.textContent).toContain(label);await act(async()=>button.click());expect(navigate).toHaveBeenCalledWith('support-instagram');});
it('overview includes both connected channels without claiming messaging readiness',async()=>{state.facebook.data={accounts:[{state:'connected',messagingReady:false}]};state.instagram.data={accounts:[{state:'connected',messagingReady:false}]};await render();expect(document.body.textContent).toContain('Facebook & Instagram connected');expect(document.body.textContent).not.toContain('Instagram, TikTok and WhatsApp setup will follow');});
it('opens TikTok preparation without claiming an available connection',async()=>{await render();const button=Array.from(document.querySelectorAll<HTMLButtonElement>('dialog button')).find(b=>b.textContent?.startsWith('TikTok'))!;expect(button.textContent).toContain('等待 Tiktok 授权');await act(async()=>button.click());expect(navigate).toHaveBeenCalledWith('support-tiktok');});
