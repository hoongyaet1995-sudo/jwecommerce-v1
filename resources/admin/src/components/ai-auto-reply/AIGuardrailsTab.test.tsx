// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {AIGuardrailsTab} from './AIGuardrailsTab';
import {DEFAULT_AI_AUTO_REPLY_CONFIG} from '../../data/defaultAIAutoReply';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({aiAutoReplyConfig:structuredClone(DEFAULT_AI_AUTO_REPLY_CONFIG),updateAIAutoReplyConfig:vi.fn(),addToast:vi.fn()})}));
it('allows operational pricing edits while unsupported controls remain disabled',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 try{
  await act(async()=>root.render(<AIGuardrailsTab/>));
  expect(host.querySelector('[aria-label="Firm Pricing Response zh"]')?.matches(':disabled')).toBe(false);
  expect(host.querySelector('#bargaining-guardrail-card input')?.matches(':disabled')).toBe(false);
  expect(host.querySelector('#bargaining-guardrail-card button')?.matches(':disabled')).toBe(true);
  expect(host.querySelector('#competitor-guardrail-card input')?.matches(':disabled')).toBe(true);
 }finally{await act(async()=>root.unmount());host.remove();}
});
