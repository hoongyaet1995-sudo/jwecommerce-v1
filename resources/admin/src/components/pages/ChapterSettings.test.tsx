// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {ChapterSettings} from './ChapterSettings';
import type {PageSection} from '../../types';
it('can change delivery chapter to story without discarding retained photos or manual rows',()=>{
 const element=document.createElement('div');document.body.append(element);const root=createRoot(element);
 let next:PageSection|undefined;
 const section:PageSection={id:'chapter',type:'chapter_block',title:'Delivery',isVisible:true,chapterPreset:'delivery',items:[{id:'photo',title:'Photo',imageUrl:''},{id:'row',title:'Destination',deliveryEstimate:'Confirmed',deliveryFee:'Checkout'}]};
 act(()=>root.render(<ChapterSettings section={section} mode="design" onChange={value=>{next=value}}/>));
 act(()=>(element.querySelector('[data-choice-value="story"]') as HTMLButtonElement).click());
 expect(next?.chapterPreset).toBe('story');expect(next?.items).toEqual(section.items);
 act(()=>root.unmount());element.remove();
});

it('deletes a delivery photo without deleting manual rows or retaining its orphan image slot',()=>{
 (globalThis as {IS_REACT_ACT_ENVIRONMENT?:boolean}).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');const root=createRoot(node);let saved:PageSection|undefined;
 const section:PageSection={id:'chapter',type:'chapter_block',title:'Delivery',isVisible:true,chapterPreset:'delivery',deliverySource:'manual',items:[{id:'photo',title:'Photo',imageUrl:''},{id:'row',title:'Destination',deliveryEstimate:'Confirmed',deliveryFee:'Checkout'}],imageSlots:[{target:'item:photo:imageUrl',imageFit:'cover'}]};
 act(()=>root.render(<ChapterSettings section={section} mode="content" onChange={value=>{saved=value}}/>));
 act(()=>(node.querySelector('[data-inspector-item="photo"] button[aria-label="Delete"]') as HTMLButtonElement).click());
 expect(saved?.items).toEqual([section.items![1]]);expect(saved?.imageSlots).toEqual([]);act(()=>root.unmount());
});
