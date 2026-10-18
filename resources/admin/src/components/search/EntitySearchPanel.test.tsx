// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {EntitySearchPanel} from './EntitySearchPanel';
it('uses real saved metadata by default and keeps manual fields in collapsed advanced settings',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const fetch=vi.fn(async()=>new Response(JSON.stringify({metadata:{title:'Saved product title',description:'Actual description',source:'system',version:0,titleLocked:false,descriptionLocked:false,excluded:false,topics:[]},facts:{name:'Product'},origin:'https://store.example',url:'https://store.example/products/actual',task:null}),{status:200}));vi.stubGlobal('fetch',fetch);
 const div=document.createElement('div');document.body.append(div);const root=createRoot(div);
 await act(async()=>root.render(<EntitySearchPanel kind="product" id="1"/>));
 expect(div.textContent).toContain('Saved product title');expect(div.textContent).not.toContain('auroracommerce.io');expect(div.querySelector('details')?.open).toBe(false);expect(div.querySelectorAll('input[type=text]')).toHaveLength(0);
 const check=div.querySelector<HTMLInputElement>('input[type=checkbox]')!;await act(async()=>check.click());expect(div.querySelectorAll('input[type=text]')).toHaveLength(1);
 await act(async()=>root.unmount());div.remove();vi.unstubAllGlobals();
});
