// @vitest-environment jsdom
import type {PageSection} from '../../types';
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,afterEach} from 'vitest';
import {PageRuntimeProvider,createAdminPageRuntimePayload} from '../../cms/PageRuntimeContext';
import {shippingEditorialSections} from '../../cms/shippingEditorialTemplate';
import {SectionRenderer} from './SectionRenderer';
import {ShippingContentSettings} from './ShippingSettings';
import type {CmsPage} from '../../types';
(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
afterEach(()=>document.body.innerHTML='');
it('renders live rates, usable FAQ controls and the scoped process entries',()=>{
 const sections=shippingEditorialSections('test');const page={id:'qa',title:'QA',slug:'qa',status:'draft',sections} as CmsPage;
 const payload=createAdminPageRuntimePayload(page,{products:[],categories:[],collections:[]},'MYR',[{id:'rate',name:'Standard',zoneRegion:'peninsular',isActive:true,rateType:'flat',estimatedDays:'Configured estimate'}]);
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 act(()=>root.render(<PageRuntimeProvider payload={payload}>{sections.map(section=><SectionRenderer key={section.id} section={section}/>)}</PageRuntimeProvider>));
 expect(node.textContent).toContain('Peninsular Malaysia — Standard');expect(node.textContent).toContain('Configured estimate');
 const buttons=[...node.querySelectorAll('[data-minimal-faq] button')];expect(buttons[0].getAttribute('aria-expanded')).toBe('true');
 act(()=>buttons[1].dispatchEvent(new MouseEvent('click',{bubbles:true})));expect(buttons[1].getAttribute('aria-expanded')).toBe('true');
 expect(node.querySelectorAll('[data-editorial-story] [data-cms-item]')).toHaveLength(6);
 act(()=>root.unmount());
});
it('edits and reorders manual delivery rows while preserving IDs',()=>{
 let section:PageSection={...shippingEditorialSections('test')[2],deliverySource:'manual' as const,items:[{id:'one',title:'One',deliveryEstimate:'A',deliveryFee:'B'},{id:'two',title:'Two',deliveryEstimate:'C',deliveryFee:'D'}]};
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const render=()=>root.render(<ShippingContentSettings section={section} onChange={patch=>{section={...section,...patch};render();}}/>);
 act(render);const up=[...node.querySelectorAll('button')].find(button=>button.textContent==='Up')!;
 act(()=>up.dispatchEvent(new MouseEvent('click',{bubbles:true})));expect(section.items![0].id).toBe('two');expect(section.items![0].deliveryEstimate).toBe('C');
 act(()=>root.unmount());
});
