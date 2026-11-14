// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {ShellDesignControls} from './ShellDesignEditor';
import type {ShellDesign} from '../../cms/shellDesign';
import {shellDesignDefaults} from '../../cms/shellDesign';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({})}));
it('toggles utilities and preserves legacy data without retired choices',async()=>{
 let latest:ShellDesign={...shellDesignDefaults('service'),style:'soft' as const,appearance:'dark' as const,showThemeSwitch:true,ctaLabel:'Enquire',ctaUrl:'/contact'};
 function Wrapper(){const[v,set]=useState(latest);return <ShellDesignControls value={v} onChange={n=>{latest=n;set(n)}}/>}
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);await act(async()=>root.render(<Wrapper/>));
 act(()=>node.querySelector<HTMLButtonElement>('[role="switch"][aria-label="Account icon"]')!.click());expect(latest.showAccount).toBe(true);
 const click=(text:string)=>act(()=>[...node.querySelectorAll<HTMLButtonElement>('button')].find(b=>b.textContent?.startsWith(text))!.click());
 click('Ecommerce');expect(latest.business).toBe('commerce');expect(latest.ctaUrl).toBe('/contact');
 expect(node.textContent).not.toContain('Header & footer style');expect(node.textContent).not.toContain('Appearance');expect(node.textContent).not.toContain('Show light / dark switch');expect(latest.style).toBe('soft');expect(latest.appearance).toBe('dark');expect(latest.showThemeSwitch).toBe(true);expect(latest.showAccount).toBe(true);
 await act(async()=>root.unmount());node.remove();
});