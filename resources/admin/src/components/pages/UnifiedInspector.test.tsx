// @vitest-environment jsdom
import React,{act} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {SectionInspector} from './SectionInspector';
import {SECTION_TEMPLATES} from './AddSectionModal';
vi.mock('../../context/AdminContext',()=>({useAdmin:()=>({products:[],categories:[],collections:[],addToast:vi.fn()})}));
vi.mock('../../context/useAdminCurrency',()=>({useAdminCurrency:()=>({currencyCode:'MYR',money:(value:number)=>String(value)})}));
vi.mock('../../integration/adminBootstrap',()=>({readAdminBootstrap:()=>({})}));
vi.mock('../../integration/couponData',()=>({fetchCoupons:async()=>({promotions:[]})}));
const props={pageId:'sample',onChange:vi.fn(),onMediaUpload:vi.fn(),onDelete:vi.fn(),onClose:vi.fn()};
it('mounts every preset with all original control groups and three categories',()=>{
 for(const template of SECTION_TEMPLATES){
  const html=renderToStaticMarkup(<SectionInspector {...props} section={{...template.defaultData,id:'sample',isVisible:true}}/>);
  expect(html.match(/data-inspector-group=/g),template.title).toHaveLength(27);
  expect(html.match(/role="tab"/g),template.title).toHaveLength(3);
 }
});
it('keeps image assets in Content, geometry in Design and container exceptions in Advanced',async()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const template=SECTION_TEMPLATES.find(item=>item.title==='Editorial Cover')!;
 await act(async()=>root.render(<SectionInspector {...props} selectedElement={{target:'title',role:'title',revision:1}} section={{...template.defaultData,id:'sample',isVisible:true}}/>));
 const tabs=[...node.querySelectorAll<HTMLButtonElement>('[role="tab"]')];
 expect(tabs[0].getAttribute('aria-selected')).toBe('true');
 expect(node.querySelector('[aria-label="Cover introduction"]')?.closest('[hidden]')).toBeNull();
 act(()=>tabs[1].click());
 expect(tabs[1].getAttribute('aria-selected')).toBe('true');
 expect(node.querySelector('[aria-label="Text font"]')?.closest('[hidden]')).toBeNull();
 const assets=[...node.querySelectorAll<HTMLInputElement>('[aria-label="Image alternative text"]')];
 expect(assets).toHaveLength(1);expect(assets[0].closest('[hidden]')).not.toBeNull();
 const ratios=[...node.querySelectorAll('[aria-label="Image ratio"]')];expect(ratios).toHaveLength(1);
 act(()=>tabs[2].click());
 expect(node.querySelector('[aria-label="Container mode"]')?.closest('[hidden]')).toBeNull();
 act(()=>root.unmount());node.remove();
});
