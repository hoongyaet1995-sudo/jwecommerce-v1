// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {AIResponsePolicyControls} from './AIResponsePolicyControls';
it('updates only the selected business preference',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;const host=document.createElement('div'),root=createRoot(host),change=vi.fn();
 try{await act(async()=>root.render(<AIResponsePolicyControls value={{partialAnswers:true,clarificationEnabled:true}} onChange={change}/>));
 expect(host.querySelectorAll('[role="switch"]')).toHaveLength(2);
 await act(async()=>(host.querySelector('[aria-label="Keep confirmed partial answers"]') as HTMLInputElement).click());
 expect(change).toHaveBeenCalledWith({partialAnswers:false,clarificationEnabled:true});
 expect(host.textContent).toContain('Identity verification');
 }finally{await act(async()=>root.unmount());}
});
