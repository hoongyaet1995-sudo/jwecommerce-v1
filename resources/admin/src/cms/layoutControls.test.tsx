// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from '../components/pages/SectionInspector';
import {layoutControlCapabilities} from './layoutControls';
vi.mock('../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],pages:[],addToast:vi.fn()})}));
vi.mock('../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(v:number)=>String(v)})}));
vi.mock('../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
globalThis.IS_REACT_ACT_ENVIRONMENT=true;
it('respects fixed Corporate CSS rather than treating unused variables/classes as working controls',()=>{
 expect(layoutControlCapabilities({id:'s',type:'hero_banner',title:'Hero',isVisible:true,heroBannerPreset:'corporate-split-composite'}).spacing).toBe(false);
 expect(layoutControlCapabilities({id:'s',type:'media_gallery',title:'Cards',isVisible:true,galleryPreset:'staggered-focus-cards'}).alignment).toEqual([]);
});
it('hides ignored Market Hero spacing/alignment and exposes supported sparse rich-text controls',async()=>{
 vi.stubGlobal('fetch',vi.fn(async()=>({ok:true,json:async()=>({data:[],palettes:[]})})));
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const show=async(section:any)=>{await act(async()=>root.render(<SectionInspector key={section.type} pageId="layout-qa" section={section} onChange={vi.fn()} onMediaUpload={vi.fn()} onClose={vi.fn()} onDelete={vi.fn()}/>));await act(async()=>[...node.querySelectorAll<HTMLButtonElement>('[role=tab]')].find(n=>n.textContent==='design')!.click());const visible=node.cloneNode(true) as HTMLElement;visible.querySelectorAll('[hidden]').forEach(n=>n.remove());return visible.textContent;};
 try{
  expect(await show({id:'s',type:'shop_navigation',title:'Keep',isVisible:true,shopNavigationPreset:'market-hero',paddingY:'spacious',textAlignment:'right'})).not.toContain('Vertical Spacing');
  expect(node.querySelector('[aria-label="Text alignment right"]')).toBeNull();
  expect(await show({id:'s',type:'rich_text',title:'Keep',isVisible:true})).toContain('Vertical Spacing');
 }finally{await act(async()=>root.unmount());node.remove();vi.unstubAllGlobals();}
});
