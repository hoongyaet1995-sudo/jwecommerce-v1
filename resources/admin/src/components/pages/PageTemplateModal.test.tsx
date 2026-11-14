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
