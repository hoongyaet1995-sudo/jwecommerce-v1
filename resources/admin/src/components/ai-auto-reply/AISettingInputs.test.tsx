// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {AIKeywordInput,AISettingToggle} from './AISettingInputs';
it('adds trimmed phrases with Enter, rejects duplicates and removes chips',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const Demo=()=>{const [values,setValues]=useState(['hello']);return <AIKeywordInput label="Greeting phrases" values={values} onChange={setValues} maxLength={80}/>;};
 try{
 await act(async()=>root.render(<Demo/>));
 const input=host.querySelector('input')!;
 const enter=async(value:string)=>{await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,value);input.dispatchEvent(new Event('input',{bubbles:true}));});await act(async()=>input.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true})));};
 await enter('  good morning  ');expect(host.querySelector('[aria-label="Remove good morning"]')).not.toBeNull();
 await enter('HELLO');expect(host.querySelectorAll('[aria-label^="Remove "]')).toHaveLength(2);
 await act(async()=>(host.querySelector('[aria-label="Remove hello"]') as HTMLButtonElement).click());expect(host.querySelector('[aria-label="Remove hello"]')).toBeNull();
 }finally{await act(async()=>root.unmount());host.remove();}
});
it('switch preserves native label activation and boolean changes',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 const Demo=()=>{const [on,setOn]=useState(true);return <label><AISettingToggle checked={on} onChange={e=>setOn(e.target.checked)}/>Order status</label>;};
 try{await act(async()=>root.render(<Demo/>));const input=host.querySelector('input')!;expect(input.getAttribute('role')).toBe('switch');expect(input.checked).toBe(true);await act(async()=>host.querySelector('label')!.click());expect(input.checked).toBe(false);}finally{await act(async()=>root.unmount());host.remove();}
});
