// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {ChapterSettings} from './ChapterSettings';
import type {PageSection} from '../../types';
it('can change delivery chapter to story without mixing photos with manual rows',()=>{
 const element=document.createElement('div');document.body.append(element);const root=createRoot(element);
 let next:PageSection|undefined;
 const section:PageSection={id:'chapter',type:'chapter_block',title:'Delivery',isVisible:true,chapterPreset:'delivery',items:[{id:'photo',title:'Photo',imageUrl:''},{id:'row',title:'Destination',deliveryEstimate:'Confirmed',deliveryFee:'Checkout'}]};
 act(()=>root.render(<ChapterSettings section={section} mode="design" onChange={value=>{next=value}}/>));
 const select=element.querySelector('select')!;act(()=>{select.value='story';select.dispatchEvent(new Event('change',{bubbles:true}))});
 expect(next?.chapterPreset).toBe('story');expect(next?.items).toBeUndefined();
 act(()=>root.unmount());element.remove();
});
