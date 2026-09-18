// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SearchableSourcePicker} from './SearchableSourcePicker';
it('shows all on open, filters names and IDs, clears and selects',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const node=document.createElement('div'),root=createRoot(node),change=vi.fn();
 await act(async()=>root.render(<SearchableSourcePicker sourceType="category" sourceId="" onChange={change} collections={[]} categories={[{id:'11',name:'Boots',slug:'boots'},{id:'22',name:'Games',slug:'games'}]}/>));
 await act(async()=>node.querySelector('button')!.click());
 expect(node.textContent).toContain('Boots');expect(node.textContent).toContain('Games');
 const input=node.querySelector('input')!;
 const type=async(value:string)=>act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,value);input.dispatchEvent(new Event('input',{bubbles:true}));});
 await type('22');expect(node.textContent).not.toContain('Boots');expect(node.textContent).toContain('Games');
 await type('nothing');expect(node.textContent).toContain('No matching categories');
 await type('');expect(node.textContent).toContain('Boots');
 await type('boo');expect(node.textContent).not.toContain('Games');
 await act(async()=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.includes('Boots'))!.click());
 expect(change).toHaveBeenCalledWith('11','Boots');expect(node.querySelector('input')).toBeNull();
 await act(async()=>node.querySelector('button')!.click());expect(node.textContent).toContain('Games');
 await act(async()=>root.unmount());
});
it('opens parent categories and commits only a selected leaf',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const node=document.createElement('div'),root=createRoot(node),change=vi.fn();
 await act(async()=>root.render(<SearchableSourcePicker sourceType="category" sourceId="" onChange={change} collections={[]} categories={[{id:'300014',name:'Muslim Fashion',slug:'fashion',disabled:true,parentId:'0'},{id:'77',name:'Dresses',slug:'dresses',parentId:'300014'}]}/>));
 await act(async()=>node.querySelector('button')!.click());
 await act(async()=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.includes('View subcategories'))!.click());
 expect(change).not.toHaveBeenCalled();expect(node.textContent).toContain('All categories');expect(node.textContent).toContain('Dresses');
 await act(async()=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.includes('Dresses'))!.click());
 expect(change).toHaveBeenCalledWith('77','Muslim Fashion > Dresses');expect(node.querySelector('input')).toBeNull();
 await act(async()=>root.unmount());
});

it('searches and labels identical leaf names by their full marketplace category path',async()=>{
 vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
 const node=document.createElement('div'),root=createRoot(node),change=vi.fn();
 await act(async()=>root.render(<SearchableSourcePicker sourceType="category" sourceId="" onChange={change} collections={[]} categories={[
  {id:'100',name:"Women's Clothing",slug:'women',disabled:true,parentId:'0'},
  {id:'1740',name:'Dresses',slug:'dresses',parentId:'100'},
  {id:'200',name:"Kids' Fashion",slug:'kids',disabled:true,parentId:'0'},
  {id:'7275',name:'Dresses',slug:'dresses',parentId:'200'},
 ]}/>));
 await act(async()=>node.querySelector('button')!.click());
 const input=node.querySelector('input')!;
 await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,'dresses');input.dispatchEvent(new Event('input',{bubbles:true}));});
 expect(node.textContent).toContain("Women's Clothing > Dresses");
 expect(node.textContent).toContain("Kids' Fashion > Dresses");
 await act(async()=>Array.from(node.querySelectorAll('button')).find(button=>button.textContent?.includes("Women's Clothing > Dresses"))!.click());
 expect(change).toHaveBeenCalledWith('1740',"Women's Clothing > Dresses");
 await act(async()=>root.unmount());
});
