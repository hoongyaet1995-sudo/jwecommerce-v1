// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ShellDesignControls} from './ShellDesignEditor';
import {shellDesignDefaults} from '../../cms/shellDesign';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({})}));
it('toggles utilities, changes style and preserves contact across business switches',async()=>{
 let latest={...shellDesignDefaults('service'),ctaLabel:'Enquire',ctaUrl:'/contact'};
 function Wrapper(){const[v,set]=useState(latest);return <ShellDesignControls value={v} onChange={n=>{latest=n;set(n)}}/>}
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);await act(async()=>root.render(<Wrapper/>));
 act(()=>node.querySelector<HTMLButtonElement>('[role="switch"][aria-label="Account icon"]')!.click());expect(latest.showAccount).toBe(true);
 const click=(text:string)=>act(()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.startsWith(text))!.click());
 click('Ecommerce');expect(latest.business).toBe('commerce');expect(latest.ctaUrl).toBe('/contact');
 click('☾ Dark');expect(latest.appearance).toBe('dark');click('Soft & refined');expect(latest.style).toBe('soft');expect(latest.showAccount).toBe(true);
 await act(async()=>root.unmount());node.remove();
});
