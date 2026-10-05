// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {afterEach,expect,it,vi} from 'vitest';
import {FacebookAccountPage} from './FacebookAccountPage';
import {facebookOAuthNotice} from '../../integration/facebookAccounts';
HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};
const {addToast,state}=vi.hoisted(()=>({addToast:vi.fn(),state:{data:null,loading:false,error:'',refresh:vi.fn(),manage:vi.fn(),select:vi.fn()}}));
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:vi.fn(),addToast})}));
vi.mock('../../integration/facebookAccounts',async original=>({...await original(),useFacebookAccounts:()=>state}));
let root:ReturnType<typeof createRoot>|undefined;
afterEach(async()=>{if(root)await act(async()=>root!.unmount());root=undefined;document.body.innerHTML='';addToast.mockClear();state.error='';});
async function render(){(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div');document.body.append(host);root=createRoot(host);await act(async()=>root!.render(<FacebookAccountPage/>));}
it.each(Object.entries(facebookOAuthNotice))('OAuth result %s uses one Toast and consumes only callback query',async(key,message)=>{
 window.history.replaceState({},'',`/admin/social/facebook?facebook=${key}&keep=1#section`);await render();
 expect(addToast).toHaveBeenCalledTimes(1);expect(addToast.mock.calls[0][0].message).toBe(message);
 expect(document.body.textContent).not.toContain(message);expect(window.location.search).toBe('?keep=1');expect(window.location.hash).toBe('#section');
});
it('operation errors notify again after a subsequent request clears the error',async()=>{
 window.history.replaceState({},'','/admin/social/facebook');state.error='Operation failed';await render();expect(addToast).toHaveBeenCalledTimes(1);
 state.error='';await act(async()=>root!.render(<FacebookAccountPage/>));state.error='Operation failed';await act(async()=>root!.render(<FacebookAccountPage/>));expect(addToast).toHaveBeenCalledTimes(2);
});
