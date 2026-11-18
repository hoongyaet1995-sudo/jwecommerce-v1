// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {FloatingTextToolbar} from './FloatingTextToolbar';
import {editTextStyle,roleScope,targetRole} from '../../cms/textToolbar';
import type {PageSection} from '../../types';
(globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
const section={id:'sample',type:'hero_banner',title:'Existing heading',content:'Existing copy',items:[{id:'a',title:'Existing item'}],typography:{title:{fontWeight:500}},customColors:{titleColor:'#123456'}} as PageSection;
it('updates canonical role fields immutably and keeps existing content, items and color ownership',()=>{
 const copy=JSON.stringify(section),next=editTextStyle(section,'title',{fontSize:54,color:'#abc123'});
 expect(JSON.stringify(section)).toBe(copy);expect(next.title).toBe(section.title);expect(next.items).toEqual(section.items);expect(next.customColors).toEqual(section.customColors);
 expect(next.typography?.title).toMatchObject({fontSize:54,fontWeight:500,color:'#abc123',colorOverride:true});
 const reset=editTextStyle(next,'title',{color:undefined});expect(reset.typography?.title?.color).toBeUndefined();expect(reset.typography?.title?.colorOverride).toBeUndefined();expect(reset.typography?.title?.inheritPage).toBe(true);
});
it('selects a registered parent role rather than arbitrary inner spans and explains shared item scope',()=>{
 const root=document.createElement('div');root.innerHTML='<h1 data-cms-text="title"><span>Heading</span></h1><p data-cms-text="not-a-role">Unknown</p>';
 expect(targetRole(section,root.querySelector('span')!)).toBe('title');expect(targetRole(section,root.querySelector('p')!)).toBeUndefined();expect(roleScope('itemTitle')).toContain('All items');
});
it('Bold shares the weight field and restores the previous weight; alignment uses the existing section field',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let latest=section;
 function Sample(){const[s,set]=useState(section);latest=s;return <FloatingTextToolbar section={s} role="title" position={{left:0,top:0,width:600}} computed={{fontSize:36,fontWeight:500,color:'#101a3a'}} onChange={set} onClose={()=>{}}/>;}
 await act(async()=>root.render(<Sample/>));const bold=document.querySelector<HTMLButtonElement>('[aria-label="Bold"]')!;
 await act(async()=>bold.click());expect(latest.typography?.title?.fontWeight).toBe(700);await act(async()=>bold.click());expect(latest.typography?.title?.fontWeight).toBe(500);
 await act(async()=>document.querySelector<HTMLButtonElement>('[aria-label="Toolbar alignment right"]')!.click());expect(latest.textAlignment).toBe('right');expect(latest.typography?.title).toEqual(section.typography?.title);
 expect(document.querySelector('[role="toolbar"]')?.textContent).not.toContain('Highlight');expect(document.querySelector('[role="toolbar"]')?.textContent).not.toContain('Tagging');
 await act(async()=>root.unmount());host.remove();
});

it('maps selected nested heading text and rejects item titles',async()=>{
 const {headingSelection}=await import('../../cms/textToolbar');const h=document.createElement('h1');h.dataset.cmsText='title';h.innerHTML='Existing <span>heading</span>';document.body.append(h);
 const r=document.createRange();r.selectNodeContents(h.querySelector('span')!);const s=window.getSelection()!;s.removeAllRanges();s.addRange(r);
 expect(headingSelection(section,h,s)).toEqual({start:9,end:16,text:'heading'});h.dataset.cmsItem='0';expect(headingSelection(section,h,s)).toBeUndefined();s.removeAllRanges();h.remove();
});
it('emphasizes only the selection while preserving copy and typography',async()=>{
 const host=document.createElement('div');document.body.append(host);const root=createRoot(host);let latest=section;
 await act(async()=>root.render(<FloatingTextToolbar section={section} role="title" selection={{start:9,end:16,text:'heading'}} position={{left:0,top:0,width:760}} computed={{fontSize:36,fontWeight:500,color:'#101a3a'}} onChange={s=>{latest=s}} onClose={()=>{}}/>));
 await act(async()=>document.querySelector<HTMLButtonElement>('[aria-label="Emphasize selection"]')!.click());expect(latest.titleEmphasis).toEqual([{start:9,end:16}]);expect(latest.title).toBe(section.title);expect(latest.typography).toEqual(section.typography);await act(async()=>root.unmount());host.remove();
});
