// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {WhatsAppAccountPage} from './WhatsAppAccountPage';
import {whatsappOAuthNotice} from '../../integration/whatsappAccounts';
HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};
HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
const {addToast,state}=vi.hoisted(()=>({addToast:vi.fn(),state:{data:null as any,loading:false,error:'',refresh:vi.fn(),manage:vi.fn(),select:vi.fn()}}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:vi.fn(),addToast})}));
vi.mock('../../integration/whatsappAccounts',async original=>({...await original(),useWhatsAppAccounts:()=>state}));
let root:ReturnType<typeof createRoot>|undefined;
afterEach(async()=>{if(root)await act(async()=>root!.unmount());root=undefined;document.body.innerHTML='';addToast.mockClear();state.error='';state.data=null;state.select.mockClear();});
async function render(){(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);root=createRoot(host);await act(async()=>root!.render(<WhatsAppAccountPage/>));}
it.each(Object.entries(whatsappOAuthNotice))('OAuth result %s uses one Toast and consumes only callback query',async(key,message)=>{
 window.history.replaceState({},'',`/admin/social/whatsapp?whatsapp=${key}&keep=1#section`);await render();
 expect(addToast).toHaveBeenCalledTimes(1);expect(addToast.mock.calls[0][0].message).toBe(message);
 expect(document.body.textContent).not.toContain(message);expect(window.location.search).toBe('?keep=1');expect(window.location.hash).toBe('#section');
});
it('operation errors notify again after a subsequent request clears the error',async()=>{
 window.history.replaceState({},'','/admin/social/whatsapp');state.error='Operation failed';await render();expect(addToast).toHaveBeenCalledTimes(1);
 state.error='';await act(async()=>root!.render(<WhatsAppAccountPage/>));state.error='Operation failed';await act(async()=>root!.render(<WhatsAppAccountPage/>));expect(addToast).toHaveBeenCalledTimes(2);
});

it('independent card toggles submit the full selected Page set once',async()=>{
 window.history.replaceState({},'','/admin/social/whatsapp');
 state.data={configured:true,canManage:true,oauthPermitted:true,pages:[{id:'111',name:'One'},{id:'222',name:'Two'}],accounts:[]};await render();
 const cards=Array.from(document.querySelectorAll<HTMLButtonElement>('[aria-pressed]'));
 await act(async()=>{cards[0].click();cards[1].click();});expect(cards.map(c=>c.getAttribute('aria-pressed'))).toEqual(['true','true']);
 const submit=Array.from(document.querySelectorAll<HTMLButtonElement>('button')).find(b=>b.textContent==='Connect selected accounts (2)')!;
 await act(async()=>submit.click());expect(state.select).toHaveBeenCalledWith(['111','222']);
 await act(async()=>cards[0].click());expect(cards.map(c=>c.getAttribute('aria-pressed'))).toEqual(['false','true']);
});

it('denied Hub permission disables OAuth without hiding account management',async()=>{
 window.history.replaceState({},'','/admin/social/whatsapp');
 state.data={configured:true,canManage:true,oauthPermitted:false,oauthReason:'Authorization paused',pages:[],accounts:[]};await render();
 const connect=Array.from(document.querySelectorAll<HTMLButtonElement>('button')).find(b=>b.textContent==='Connect WhatsApp')!;
 expect(connect.disabled).toBe(true);expect(document.querySelector('a[href="/admin/social/whatsapp/authorize"]')).toBeNull();
 expect(document.body.textContent).toContain('Authorization paused');
});
