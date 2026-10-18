// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import {SearchVisibilitySettings} from './SearchVisibilitySettings';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({setCurrentSection:vi.fn()})}));
it('refreshes all content with one action without requesting or showing a task list',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 vi.useFakeTimers();
 const run={id:3,total:120,completed:0,skipped:0,failed:0,active:true,status:'running',waitingForAllowance:false};
 const state={settings:{version:1,mode:'auto',preview:false,daily_limit:100},origin:'https://store.example',brand:'Actual store',ready:true,model:'configured',counts:{product:80,page:15,category:15,collection:10},refresh:null};
 let polls=0;
 const fetch=vi.fn(async(url:string,options?:RequestInit)=>{
  if(url.endsWith('/advance')){
   polls++;
   return new Response(JSON.stringify(polls===1?{message:'Transient rate limit'}:{...run,active:false,completed:120,status:'completed'}),{status:polls===1?429:200});
  }
  return new Response(JSON.stringify(url.endsWith('/refresh')?run:state),{status:options?.method==='POST'?202:200});
 });
 vi.stubGlobal('fetch',fetch);
 const div=document.createElement('div');document.body.append(div);const root=createRoot(div);
 await act(async()=>root.render(<SearchVisibilitySettings/>));
 expect(div.textContent).not.toContain('Recent optimization tasks');
 expect(div.textContent).not.toContain('Maximum AI tasks per day');
 expect(div.textContent).toContain('Saving content does not call AI');
 expect(div.textContent).not.toContain('nightly');
 expect(div.textContent).toContain('Keep this page open');
 expect(div.textContent).toContain('all products, pages, collections and categories');
 const button=[...div.querySelectorAll('button')].find(b=>b.textContent==='Refresh SEO')!;
 await act(async()=>button.click());
 expect(button.disabled).toBe(true);
 expect(button.textContent).toBe('Refreshing SEO…');
 expect(div.textContent).toContain('120 records ready');
 expect(fetch.mock.calls.filter(([url,opts])=>url.endsWith('/refresh')&&opts?.method==='POST')).toHaveLength(1);
 expect(fetch.mock.calls.some(([url])=>url.includes('/tasks'))).toBe(false);
 await act(async()=>vi.advanceTimersByTimeAsync(1000));
 expect(div.textContent).toContain('Transient rate limit');
 const resume=[...div.querySelectorAll('button')].find(b=>b.textContent==='Resume SEO')!;
 await act(async()=>resume.click());
 await act(async()=>vi.advanceTimersByTimeAsync(1000));
 expect(div.textContent).not.toContain('Transient rate limit');
 expect(div.textContent).toContain('SEO refreshed across 120 records');
 await act(async()=>root.unmount());div.remove();vi.unstubAllGlobals();vi.useRealTimers();
});
