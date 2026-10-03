// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {normalizeAIAutoReplyConfig} from './normalizeAIAutoReply';
import {AIKnowledgeScopeTab} from '../components/ai-auto-reply/AIKnowledgeScopeTab';
const state=vi.hoisted(()=>({config:null as any,update:vi.fn()}));
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({aiAutoReplyConfig:state.config,updateAIAutoReplyConfig:state.update,addToast:vi.fn()})}));
it('enables universal product controls outside disconnected sources',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;state.config=normalizeAIAutoReplyConfig({});const host=document.createElement('div');const root=createRoot(host);document.body.append(host);
 try{await act(async()=>root.render(<AIKnowledgeScopeTab/>));const card=host.querySelector('#product-catalog-scope-card')!;expect(card.closest('fieldset[disabled]')).toBeNull();expect(card.textContent).toContain('Product Details & Specifications');expect(card.textContent).not.toContain('Heavyweight');const range=card.querySelector<HTMLInputElement>('input[type=range]')!;expect(range.min).toBe('1');expect(range.max).toBe('5');const toggle=card.querySelector('button')!;await act(async()=>toggle.click());expect(state.update).toHaveBeenCalled();const update=state.update.mock.calls[0][0];expect(update(state.config).knowledgeScope.productCatalog.isEnabled).toBe(false);}
 finally{await act(async()=>root.unmount());host.remove();}
});
