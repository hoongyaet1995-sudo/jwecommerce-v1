// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {AddSectionModal} from './AddSectionModal';
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('previews A → B → A without adding, then inserts the last selected shared layout once',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node),onAdd=vi.fn();
 await act(async()=>root.render(<AddSectionModal isOpen onClose={()=>{}} onAdd={onAdd} categories={[]} collections={[]}/>));
 const click=async(text:string)=>act(async()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.trim()===text)!.click());
 await act(async()=>[...node.querySelectorAll('button')].find(b=>b.querySelector('h3')?.textContent==='Connected Hero')!.click());
 const source=()=>node.querySelector('iframe')!.getAttribute('src');
 expect(source()).toContain('layout=hero-centered');
 await click('Split Hero');expect(source()).toContain('layout=hero-split');
 await click('Centered Hero');expect(source()).toContain('layout=hero-centered');
 await click('Mobile');expect(source()).toContain('width=390');
 expect(onAdd).not.toHaveBeenCalled();
 await click('Insert selected layout');expect(onAdd).toHaveBeenCalledTimes(1);expect(onAdd.mock.calls[0][0].connectedLayout).toBe('hero-centered');
 await act(async()=>root.unmount());node.remove();
});
