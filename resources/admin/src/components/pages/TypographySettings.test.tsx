// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {TypographySettings} from './TypographySettings';
it('accepts complete numeric input on blur and rejects out-of-range input without saving it',()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div'),root=createRoot(node);document.body.append(node);
 const onChange=vi.fn();act(()=>root.render(<TypographySettings section={{id:'x',type:'rich_text',title:'Title',isVisible:true}} selectedRole="title" onSelectRole={()=>{}} onChange={onChange}/>));
 const input=node.querySelector<HTMLInputElement>('[data-typography-role="title"] [aria-label="Text size"]')!;
 act(()=>{input.value='5';input.dispatchEvent(new Event('input',{bubbles:true}));});expect(onChange).not.toHaveBeenCalled();
 act(()=>{input.value='56';input.dispatchEvent(new FocusEvent('focusout',{bubbles:true}));});expect(onChange.mock.calls[0][0].typography.title.fontSize).toBe(56);
 onChange.mockClear();act(()=>{input.value='999';input.dispatchEvent(new FocusEvent('focusout',{bubbles:true}));});expect(onChange).not.toHaveBeenCalled();
 act(()=>root.unmount());node.remove();
});
