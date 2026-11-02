// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {describe,it,expect,vi,afterEach} from 'vitest';
const cats=[{id:'1',name:'Parent',parentId:null,slug:'parent',status:'active',productCount:0},{id:'2',name:'Child',parentId:'1',slug:'child',status:'active',productCount:0},{id:'3',name:'Leaf',parentId:'2',slug:'leaf',status:'active',productCount:0}];
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({categories:cats,saveCategory:vi.fn(),deleteCategory:vi.fn(),refreshCatalog:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({money:(n:number)=>'RM '+n})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({catalogUrl:'/admin/api/catalog',csrfToken:'test'})}));
import {CategoryAdmin} from './CategoryAdmin';
let root:any,host:HTMLDivElement;
afterEach(async()=>{await act(async()=>root?.unmount());host?.remove();vi.restoreAllMocks()});
async function mount(url:string){history.replaceState({},'',url);vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);vi.stubGlobal('fetch',vi.fn().mockResolvedValue({ok:true,status:200,json:async()=>({data:[],total:0,page:1,lastPage:1})}));host=document.createElement('div');document.body.append(host);root=createRoot(host);await act(async()=>root.render(<CategoryAdmin/>))}
describe('Category hierarchy navigation',()=>{
 it('shows only immediate children and preserves the layer in the URL',async()=>{await mount('/admin/catalog/categories');expect(host.textContent).toContain('Parent');expect(host.querySelector('tbody')?.textContent).not.toContain('Child');const parent=[...host.querySelectorAll('button')].find(b=>b.textContent==='Parent')!;await act(async()=>parent.click());expect(location.search).toBe('?parent=1');expect(host.querySelector('tbody')?.textContent).toContain('Child');expect(host.querySelector('tbody')?.textContent).not.toContain('Leaf')});
 it('opens the saved leaf layer without a create-subcategory action',async()=>{await mount('/admin/catalog/categories?parent=3');expect(host.querySelector('nav')?.textContent).toContain('Parent');expect(host.querySelector('h2')?.textContent).toBe('Leaf');expect(host.textContent).not.toContain('Create subcategory');expect(host.textContent).toContain('Link Product');expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/3/products?page=1'),expect.anything())});
});
