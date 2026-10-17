// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from '../components/pages/SectionInspector';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import type {PageSection} from '../types';
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:String})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
it('switches sparse album/mosaic/album with stable image groups and real entry operations',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 let section:PageSection={id:'sparse',type:'media_gallery',title:'Sparse',isVisible:true,galleryPreset:'album-browser',items:[]};
 const render=async()=>{await act(async()=>root.render(<SectionInspector pageId="test" section={section} onChange={value=>{section=value;void render()}} onMediaUpload={vi.fn()} onDelete={vi.fn()} onClose={vi.fn()}/>));};
 await render();const button=(label:string)=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.trim()===label)!;
 await act(async()=>button('Add Photo').click());expect(section.items).toHaveLength(1);expect(node.textContent).toContain('Image group');
 section.items![0].category='Details';const id=section.items![0].id;
 for(const preset of ['industrial-mosaic','album-browser'] as const){section={...section,galleryPreset:preset};await render();expect(section.items![0].id).toBe(id);expect(section.items![0].category).toBe('Details');expect(!!node.querySelector('[aria-label="Image group"]')).toBe(preset==='album-browser');}
 await act(async()=>button('Add Photo').click());await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 2 up"]')!.click());expect(section.items![1].id).toBe(id);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());expect(section.items).toHaveLength(1);
 await act(async()=>root.unmount());node.remove();expect(SECTION_TEMPLATES.some(t=>t.defaultData.galleryPreset==='album-browser')).toBe(true);
});
