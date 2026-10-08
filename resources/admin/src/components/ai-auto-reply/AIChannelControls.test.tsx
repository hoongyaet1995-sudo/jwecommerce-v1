// @vitest-environment jsdom
import React,{act} from 'react';import {createRoot} from 'react-dom/client';import {it,expect,vi} from 'vitest';
import {AIChannelControls} from './AIChannelControls';import {DEFAULT_AI_AUTO_REPLY_CONFIG} from '../../data/defaultAIAutoReply';
const state=vi.hoisted(()=>({config:null as any,save:vi.fn()}));vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({aiAutoReplyConfig:state.config,saveAIAutoReplyConfig:state.save,addToast:vi.fn()})}));
it('requires confirmation and preserves independent channel choices',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;state.config=structuredClone(DEFAULT_AI_AUTO_REPLY_CONFIG);state.config.personality.isEnabled=false;state.save.mockResolvedValue(true);
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);try{
 await act(async()=>root.render(<AIChannelControls/>));expect(host.querySelector<HTMLButtonElement>('[aria-label="TikTok AI"]')!.disabled).toBe(true);
 await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Facebook AI"]')!.click());expect(state.save).not.toHaveBeenCalled();expect(host.querySelector('[role=dialog]')).toBeNull();expect(document.querySelector('[role=dialog]')).not.toBeNull();
 await act(async()=>[...document.querySelectorAll('button')].find(b=>b.textContent==='Confirm')!.click());expect(state.save.mock.calls[0][0].aiChannels.facebook).toBe(false);expect(state.save.mock.calls[0][0].aiChannels.instagram).toBe(true);
 expect(host.textContent).not.toContain('Pause AI Auto-Reply');expect(host.textContent).not.toContain('Paused by master');expect(host.textContent).toContain('AI enabled');
 }finally{await act(async()=>root.unmount());host.remove();}
});
