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
it('switches sparse industrial card/process/card presets preserving entries and editable capabilities',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 let section:PageSection={id:'sparse',type:'feature_cards',title:'Sparse',isVisible:true,featureCardsPreset:'industrial-cards',items:[]};
 const render=async()=>{await act(async()=>root.render(<SectionInspector pageId="test" section={section} onChange={value=>{section=value;void render()}} onMediaUpload={vi.fn()} onDelete={vi.fn()} onClose={vi.fn()}/>));};
 await render();expect(node.textContent).toContain('Add Card');
 const button=(label:string)=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.trim()===label)!;
 await act(async()=>button('Add Card').click());expect(section.items).toHaveLength(1);
 expect(node.textContent).toContain('Button label');expect(node.textContent).toContain('Destination');
 const originalId=section.items![0].id;
 for(const preset of ['industrial-process','industrial-cards'] as const){section={...section,featureCardsPreset:preset};await render();expect(section.items![0].id).toBe(originalId);expect(node.textContent?.includes('Button label')).toBe(preset==='industrial-cards');}
 await act(async()=>button('Add Card').click());expect(section.items).toHaveLength(2);
 await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Move item 2 up"]')!.click());expect(section.items![1].id).toBe(originalId);
 await act(async()=>node.querySelector<HTMLButtonElement>('[title="Delete item"]')!.click());expect(section.items).toHaveLength(1);
 await act(async()=>root.unmount());node.remove();
 expect(SECTION_TEMPLATES.filter(t=>['industrial-cards','industrial-process'].includes(t.defaultData.featureCardsPreset??''))).toHaveLength(2);
});
