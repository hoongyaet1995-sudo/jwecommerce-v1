// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import {normalizeAIAutoReplyConfig} from './normalizeAIAutoReply';
import {AIPersonalityTab} from '../components/ai-auto-reply/AIPersonalityTab';
const state = vi.hoisted(() => ({config:null as any}));
vi.mock('../context/AdminContext', () => ({useAdmin:() => ({aiAutoReplyConfig:state.config,updateAIAutoReplyConfig:vi.fn(),addToast:vi.fn()})}));
it('enables mission and localized greetings/fallbacks while keeping state-dependent messages disabled', async () => {
  (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
  state.config=normalizeAIAutoReplyConfig({personality:{brandMissionPrompt:'Brand fixture',unknownAnswerMessage:{en:'English unknown',ms:'Malay unknown',zh:'Chinese unknown'},serviceUnavailableMessage:{en:'English unavailable',ms:'Malay unavailable',zh:'Chinese unavailable'}}});
  const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
  try {
    await act(async()=>root.render(<AIPersonalityTab/>));
    const mission=host.querySelector<HTMLTextAreaElement>('#ai-system-prompt-card textarea')!;
    expect(host.querySelector<HTMLInputElement>('#ai-system-prompt-card input[type=range]')?.disabled).toBe(false);expect(mission.disabled).toBe(false);expect(mission.value).toBe('Brand fixture');
    const english=[...host.querySelectorAll('textarea')].find(t=>t.value==='English unknown')!;
    expect(english.disabled).toBe(false);
    const chinese=[...host.querySelectorAll('button')].find(b=>b.textContent==='中文 (Chinese)')!;
    await act(async()=>chinese.click());
    expect([...host.querySelectorAll('textarea')].some(t=>t.value==='Chinese unknown'&&!t.disabled)).toBe(true);
    expect([...host.querySelectorAll('textarea')].some(t=>t.value==='Chinese unavailable'&&!t.disabled)).toBe(true);
    expect([...host.querySelectorAll('textarea')].find(t=>t.value===state.config.personality.offlineMessage.zh)?.disabled).toBe(true);
  } finally {await act(async()=>root.unmount());host.remove();}
});
