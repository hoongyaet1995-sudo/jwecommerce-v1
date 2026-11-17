// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PageTemplateModal } from './PageTemplateModal';

let root: Root;
afterEach(()=>{if(root)act(()=>root.unmount());document.body.innerHTML='';});
describe('template settings availability',()=>{
  it.each(['loading','error'] as const)('keeps Blank usable while settings are %s',state=>{
    (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
    const node=document.createElement('div');document.body.append(node);root=createRoot(node);
    const onCreate=vi.fn();
    act(()=>root.render(<PageTemplateModal settingsState={state} context={{}} busy={false} onClose={()=>{}} onCreate={onCreate}/>));
    const article=(name:string)=>[...node.querySelectorAll('article')].find(e=>e.querySelector('h3')?.textContent===name)!;
    expect(article('About Us').querySelector('button')?.disabled).toBe(true);
    expect(article('Shipping').querySelector('button')?.disabled).toBe(true);
    const blankSelect=[...article('Blank Page').querySelectorAll('button')].find(b=>b.textContent==='Select')!;
    expect(blankSelect.disabled).toBe(false);
    act(()=>blankSelect.click());
    const create=[...node.querySelectorAll<HTMLButtonElement>('footer button')].find(b=>b.textContent==='Use Template →')!;
    expect(create.disabled).toBe(false);
    act(()=>create.click());expect(onCreate).toHaveBeenCalledWith('blank');
    expect(node.textContent?.match(/Coming in the next template batch/g)).toHaveLength(1);
  });
});

it('shows a retryable settings failure without enabling nonblank templates',()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');document.body.append(node);root=createRoot(node);
 const retry=vi.fn(),create=vi.fn();
 act(()=>root.render(<PageTemplateModal {...({settingsState:'error',settingsError:'Shop profile is temporarily unavailable (HTTP 500).',onRetry:retry,context:{},busy:false,onClose:()=>{},onCreate:create} as any)}/>));
 expect(node.textContent).toContain('Shop profile is temporarily unavailable');
 const button=node.querySelector<HTMLButtonElement>('[aria-label="Retry loading settings"]');expect(button).not.toBeNull();
 act(()=>button!.click());expect(retry).toHaveBeenCalledTimes(1);expect(create).not.toHaveBeenCalled();
 const article=[...node.querySelectorAll('article')].find(e=>e.querySelector('h3')?.textContent==='JW Commerce')!;
 expect([...article.querySelectorAll('button')].every(b=>b.disabled)).toBe(true);
 act(()=>root.render(<PageTemplateModal {...({settingsState:'loading',onRetry:retry,context:{},busy:false,onClose:()=>{},onCreate:create}as any)}/>));
 expect(node.querySelector<HTMLButtonElement>('[aria-label="Retry loading settings"]')!.disabled).toBe(true);
});
