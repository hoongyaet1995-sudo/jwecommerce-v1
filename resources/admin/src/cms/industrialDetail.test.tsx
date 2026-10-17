// @vitest-environment jsdom
import React,{act} from 'react';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
import {IndustrialGallery} from '../components/pages/IndustrialDetailSections';
import {createPageTemplateSections} from './pageTemplates';
import {repeatedItemFields} from './repeatedItemContract';
vi.mock('../components/pages/motion/CmsMotionWrapper',()=>({MotionItem:({children}:any)=><div>{children}</div>,MotionMedia:({children}:any)=><div>{children}</div>}));
it('filters authored groups, reveals more, and restores focus after keyboard lightbox',async()=>{
 (globalThis as any).IS_REACT_ACT_ENVIRONMENT=true;
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const section:any={id:'gallery',type:'media_gallery',title:'Gallery',isVisible:true,galleryPreset:'album-browser',galleryInitialCount:1,galleryLoadCount:1,imageSlots:[{target:'item:b:imageUrl',mediaAlt:'Detailed door alt'}],items:[{id:'a',title:'Exterior',imageUrl:'https://example.test/a.jpg',category:'Exteriors'},{id:'b',title:'Door',imageUrl:'https://example.test/b.jpg',category:'Details'},{id:'c',title:'Interior',imageUrl:'https://example.test/c.jpg',category:'Details'}]};
 await act(async()=>root.render(<IndustrialGallery section={section}/>));
 const button=(name:string)=>Array.from(node.querySelectorAll('button')).find(b=>b.textContent?.trim()===name)!;
 expect(node.querySelectorAll('[data-gallery-entry]')).toHaveLength(1);
 await act(async()=>button('Details').click());expect(node.textContent).toContain('Door');expect(node.querySelector('[data-gallery-entry]')?.textContent).not.toContain('Exterior');
 await act(async()=>button('Load more images').click());expect(node.querySelectorAll('[data-gallery-entry]')).toHaveLength(2);
 const opener=node.querySelector<HTMLButtonElement>('[aria-label="Open Door"]')!;opener.focus();await act(async()=>opener.click());expect(document.querySelector('[role="dialog"]')).not.toBeNull();expect(document.querySelector('[role="dialog"] img')?.getAttribute('alt')).toBe('Detailed door alt');
 await act(async()=>document.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true})));expect(document.querySelector('[role="dialog"]')?.textContent).toContain('Interior');
 await act(async()=>document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true})));expect(document.querySelector('[role="dialog"]')).toBeNull();expect(document.activeElement).toBe(opener);
 await act(async()=>root.unmount());node.remove();
});
it('creates independent detail/gallery drafts and offers sparse authored fields',()=>{
 const a=createPageTemplateSections('logistics-solution-detail',{},'a'),b=createPageTemplateSections('logistics-solution-detail',{},'b');
 expect(a.length).toBeGreaterThan(5);expect(a[0].id).not.toBe(b[0].id);expect(a.some(s=>s.galleryPreset==='industrial-mosaic')).toBe(true);
 expect(createPageTemplateSections('logistics-media-gallery',{},'c').some(s=>s.galleryPreset==='album-browser')).toBe(true);
 expect(repeatedItemFields({id:'g',type:'media_gallery',title:'',galleryPreset:'album-browser'} as any)).toContain('category');
});
