// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {AICustomerMemoryControls} from './AICustomerMemoryControls';
it('memory opt in preserves retention and exposes bounded input',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const host=document.createElement('div'),root=createRoot(host),change=vi.fn();
 try{
 await act(async()=>root.render(<AICustomerMemoryControls mode="capabilities" onChange={change}/>));
 const toggle=host.querySelector('[role="switch"]') as HTMLInputElement;expect(toggle.checked).toBe(false);
 await act(async()=>toggle.click());expect(change).toHaveBeenCalledWith({enabled:true,retentionDays:30});
 await act(async()=>root.render(<AICustomerMemoryControls mode="limits" onChange={change}/>));
 expect(host.querySelector('input')?.getAttribute('min')).toBe('7');expect(host.querySelector('input')?.getAttribute('max')).toBe('90');
 }finally{await act(async()=>root.unmount());}
});
